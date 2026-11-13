<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

/**
 * Bảo mật chung cho toàn bộ REST API `/wp-json/dravit/v1/*`:
 * - Gắn header `X-Robots-Tag: noindex, nofollow` (M-11, docs/05 §6.2).
 * - Rate-limit theo IP bằng Transient nhẹ, trả chuẩn `429 drv_rate_limited` + `Retry-After` (docs/15 §1.2).
 */
final class RestSecurity implements ServiceProviderInterface {
    public function register(): void {
        add_filter('rest_post_dispatch', [$this, 'attach_robots_header'], 20, 3);
    }

    /**
     * @param mixed            $result
     * @param \WP_REST_Server  $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function attach_robots_header(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed {
        unset($server);
        $route = $request->get_route();
        if (str_starts_with($route, '/dravit/v1')) {
            $response = rest_ensure_response($result);
            if ($response instanceof \WP_REST_Response) {
                $response->header('X-Robots-Tag', 'noindex, nofollow');
                return $response;
            }
        }

        return $result;
    }

    /**
     * Trích xuất địa chỉ IP client an toàn (chỉ lấy IP đầu tiên hợp lệ).
     */
    public static function getClientIp(): string {
        $candidates = [];
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded    = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_FORWARDED_FOR']));
            $parts        = explode(',', $forwarded);
            $candidates[] = trim($parts[0]);
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']));
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '127.0.0.1';
    }

    /**
     * Kiểm tra giới hạn tần suất gọi API theo IP.
     */
    public static function checkRateLimit(string $bucket, int $max_requests, int $window_seconds): ?\WP_Error {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return null;
        }

        $ip  = self::getClientIp();
        $key = 'drv_rl_' . substr(md5($bucket . '|' . $ip), 0, 20);
        $now = time();

        $record = get_transient($key);
        if (!is_array($record) || !isset($record['count'], $record['reset_at']) || (int) $record['reset_at'] <= $now) {
            set_transient($key, [
                'count'    => 1,
                'reset_at' => $now + $window_seconds,
            ], $window_seconds);
            return null;
        }

        $count    = (int) $record['count'] + 1;
        $reset_at = (int) $record['reset_at'];
        $ttl      = max(1, $reset_at - $now);

        if ($count > $max_requests) {
            return new \WP_Error(
                'drv_rate_limited',
                'Bạn thao tác quá nhanh, vui lòng thử lại sau ít giây.',
                [
                    'status'      => 429,
                    'retry_after' => $ttl,
                ]
            );
        }

        set_transient($key, [
            'count'    => $count,
            'reset_at' => $reset_at,
        ], $ttl);

        return null;
    }
}

