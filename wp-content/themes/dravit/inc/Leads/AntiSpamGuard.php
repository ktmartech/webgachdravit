<?php
declare(strict_types=1);

namespace Dravit\Leads;

use Dravit\Core\RestSecurity;

defined('ABSPATH') || exit;

/**
 * Chống spam 5 lớp cho mọi POST public `/quote/submit`, `/lead` dưới LiteSpeed Page Cache (D-30, docs/15 §1.3, docs/17 §4.2):
 * 1. Origin / Referer cùng host `home_url()`.
 * 2. Honeypot `drv_website` (bot điền -> giả lập thành công, không lưu).
 * 3. Nonce tươi lấy từ `GET /dravit/v1/nonce` qua header `X-DRV-Nonce`.
 * 4. Time-trap HMAC: `ts` do server ký lúc cấp nonce, bắt buộc `>= 3s` và `<= 24h`.
 * 5. Rate-limit theo IP băm HMAC (`quote.rate_limit_15m` lượt / 15 phút).
 */
final class AntiSpamGuard {
    public const MIN_FILL_SECONDS = 3;
    public const MAX_TOKEN_AGE    = 86400;
    public const RATE_WINDOW      = 900;

    public const CODE_HONEYPOT = 'drv_spam_honeypot';

    public static function signTs(int $ts, string $key): string {
        return hash_hmac('sha256', 'drv-ts|' . $ts, $key);
    }

    /**
     * @return array{ts: int, sig: string}
     */
    public static function issueTimeToken(?int $now = null): array {
        $ts = $now ?? time();
        return ['ts' => $ts, 'sig' => self::signTs($ts, self::secret())];
    }

    /**
     * Trả mã lỗi (`drv_bad_token` | `drv_too_fast` | `drv_token_expired`) hoặc `null` nếu hợp lệ.
     */
    public static function checkTimeTrap(int $ts, string $sig, int $now, string $key): ?string {
        if ($ts <= 0 || $sig === '' || !hash_equals(self::signTs($ts, $key), $sig)) {
            return 'drv_bad_token';
        }
        $elapsed = $now - $ts;
        if ($elapsed < self::MIN_FILL_SECONDS) {
            return 'drv_too_fast';
        }
        if ($elapsed > self::MAX_TOKEN_AGE) {
            return 'drv_token_expired';
        }
        return null;
    }

    public static function isHoneypotTripped(mixed $value): bool {
        return is_scalar($value) && trim((string) $value) !== '';
    }

    public static function hashIp(string $ip, string $salt): string {
        return hash_hmac('sha256', $ip, $salt);
    }

    public static function clientIpHash(): string {
        $salt = function_exists('wp_salt') ? wp_salt('auth') : 'drv-test-salt';
        return self::hashIp(RestSecurity::getClientIp(), $salt);
    }

    /**
     * Kiểm tra đủ 5 lớp cho 1 REST request. Trả `ip_hash` nếu hợp lệ hoặc `WP_Error`.
     *
     * @param array<string, mixed> $meta Khối `meta` trong payload (`_drv_ts`, `_drv_sig`, `drv_website`).
     */
    public static function verify(\WP_REST_Request $request, string $nonce_action, array $meta): string|\WP_Error {
        $origin_error = self::verifyStrictOrigin(
            (string) $request->get_header('origin'),
            (string) $request->get_header('referer')
        );
        if ($origin_error instanceof \WP_Error) {
            return $origin_error;
        }

        if (self::isHoneypotTripped($meta['drv_website'] ?? ($request->get_param('drv_website') ?? ''))) {
            return new \WP_Error(self::CODE_HONEYPOT, 'Yêu cầu bị từ chối.', ['status' => 200]);
        }

        $nonce = (string) ($request->get_header('x_drv_nonce') ?: $request->get_header('x-drv-nonce'));
        if ($nonce === '' || !function_exists('wp_verify_nonce') || wp_verify_nonce($nonce, $nonce_action) === false) {
            return new \WP_Error(
                'drv_bad_nonce',
                'Phiên biểu mẫu đã hết hạn. Hệ thống đang làm mới, vui lòng bấm gửi lại.',
                ['status' => 403, 'retryable' => true]
            );
        }

        $trap = self::checkTimeTrap((int) ($meta['_drv_ts'] ?? 0), (string) ($meta['_drv_sig'] ?? ''), time(), self::secret());
        if ($trap !== null) {
            return new \WP_Error(
                'drv_too_fast',
                $trap === 'drv_token_expired'
                    ? 'Phiên biểu mẫu đã quá hạn. Vui lòng tải lại trang và gửi lại.'
                    : 'Thao tác gửi quá nhanh. Vui lòng kiểm tra lại thông tin và thử lại sau vài giây.',
                ['status' => 403, 'retryable' => $trap !== 'drv_too_fast']
            );
        }

        $ip_hash = self::clientIpHash();
        $limited = self::consumeRateLimit($ip_hash);
        if ($limited instanceof \WP_Error) {
            return $limited;
        }

        return $ip_hash;
    }

    /**
     * Kiểm tra nhẹ cho fallback `admin-post.php` khi tắt JS (docs/17 §4.4): Origin/Referer + honeypot + rate-limit.
     *
     * @param array<string, mixed> $post
     */
    public static function verifyClassic(array $post, string $origin, string $referer): string|\WP_Error {
        $origin_error = self::verifyStrictOrigin($origin, $referer);
        if ($origin_error instanceof \WP_Error) {
            return $origin_error;
        }
        if (self::isHoneypotTripped($post['drv_website'] ?? '')) {
            return new \WP_Error(self::CODE_HONEYPOT, 'Yêu cầu bị từ chối.', ['status' => 200]);
        }
        $ip_hash = self::clientIpHash();
        $limited = self::consumeRateLimit($ip_hash);
        return $limited instanceof \WP_Error ? $limited : $ip_hash;
    }

    /**
     * Lớp 1: bắt buộc có Origin hoặc Referer và host phải khớp `home_url()`.
     */
    public static function verifyStrictOrigin(string $origin, string $referer): ?\WP_Error {
        $home_host = self::hostOf(function_exists('home_url') ? (string) home_url('/') : '');
        $req_host  = $origin !== '' ? self::hostOf($origin) : self::hostOf($referer);

        if ($home_host === '' || $req_host === '' || strcasecmp($req_host, $home_host) !== 0) {
            return new \WP_Error('drv_bad_origin', 'Yêu cầu không hợp lệ từ nguồn bên ngoài.', ['status' => 403, 'retryable' => false]);
        }
        return null;
    }

    /**
     * Lớp 5: đếm lượt gửi theo IP băm trong cửa sổ 15 phút.
     */
    public static function consumeRateLimit(string $ip_hash): ?\WP_Error {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return null;
        }

        $limit = max(1, (int) drv_setting('quote.rate_limit_15m', 5));
        $key   = 'drv_rl_lead_' . substr($ip_hash, 0, 32);
        $rec   = get_transient($key);
        $now   = time();

        if (!is_array($rec) || (int) ($rec['reset_at'] ?? 0) <= $now) {
            $rec = ['count' => 0, 'reset_at' => $now + self::RATE_WINDOW];
        }

        if ((int) $rec['count'] >= $limit) {
            $hotline = (string) drv_setting('contact.hotline', '');
            return new \WP_Error(
                'drv_rate_limited',
                sprintf('Bạn đã gửi tối đa %d yêu cầu trong 15 phút. Cần hỗ trợ gấp vui lòng gọi %s.', $limit, $hotline),
                ['status' => 429, 'retry_after' => max(1, (int) $rec['reset_at'] - $now)]
            );
        }

        $rec['count'] = (int) $rec['count'] + 1;
        set_transient($key, $rec, max(1, (int) $rec['reset_at'] - $now));
        return null;
    }

    private static function hostOf(string $url): string {
        if ($url === '') {
            return '';
        }
        $host = function_exists('wp_parse_url') ? wp_parse_url($url, PHP_URL_HOST) : parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    private static function secret(): string {
        return function_exists('wp_salt') ? wp_salt('nonce') : 'drv-test-secret';
    }
}
