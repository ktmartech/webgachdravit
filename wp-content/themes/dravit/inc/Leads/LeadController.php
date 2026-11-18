<?php
declare(strict_types=1);

namespace Dravit\Leads;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Endpoint hợp nhất `POST /wp-json/dravit/v1/lead` cho mọi form tư vấn ngoài BOQ (docs/15 §2.6)
 * + fallback `admin-post.php?action=drv_submit_lead` khi tắt JS (docs/17 §4.4, Q-09).
 */
final class LeadController implements ServiceProviderInterface {
    public const NONCE_ACTION = 'drv_lead_submit';

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_post_nopriv_drv_submit_lead', [$this, 'handle_classic']);
        add_action('admin_post_drv_submit_lead', [$this, 'handle_classic']);
    }

    public function register_routes(): void {
        register_rest_route('dravit/v1', '/lead', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_rest'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_rest(\WP_REST_Request $request): \WP_REST_Response {
        self::noCache();
        $payload = $request->get_json_params();
        $payload = is_array($payload) && $payload !== [] ? $payload : $request->get_body_params();
        $meta    = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        $guard = AntiSpamGuard::verify($request, self::NONCE_ACTION, $meta);
        if ($guard instanceof \WP_Error) {
            return $guard->get_error_code() === AntiSpamGuard::CODE_HONEYPOT
                ? self::fakeSuccess('LD')
                : self::errorResponse($guard);
        }

        $result = self::submit($payload, (string) ($payload['source'] ?? ''), [
            'page_url' => (string) ($meta['page_url'] ?? ''),
            'utm'      => $meta['utm'] ?? [],
            'ip_hash'  => $guard,
            'channel'  => 'rest',
        ]);

        if ($result instanceof \WP_Error) {
            return self::errorResponse($result);
        }

        $phone = (string) $result['data']['phone'];
        $resp  = new \WP_REST_Response([
            'ok'        => true,
            'lead_id'   => $result['id'],
            'lead_code' => $result['code'],
            'message'   => sprintf(
                'Đã tiếp nhận yêu cầu #%s. Chuyên viên DRAVIT sẽ liên hệ số %s. %s.',
                $result['code'],
                $phone,
                (string) drv_setting('order.sla_text', '')
            ),
        ], 201);
        $resp->header('Cache-Control', 'no-store');
        return $resp;
    }

    /**
     * Xử lý dùng chung REST / admin-post / Fluent Forms: validate -> lưu `drv_lead` -> email -> `drv/lead/created`.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context page_url, utm, ip_hash, channel, extra_meta
     * @return array{id: int, code: string, data: array<string, mixed>}|\WP_Error
     */
    public static function submit(array $input, string $source, array $context = []): array|\WP_Error {
        $source = LeadValidator::normalizeSource($source);
        if ($source === '' || $source === 'boq') {
            return new \WP_Error('drv_invalid_param', 'Nguồn biểu mẫu không hợp lệ.', ['status' => 400, 'param' => 'source']);
        }
        if ($source === 'ai_handoff' && !drv_setting('features.ai_enabled', false)) {
            return new \WP_Error('drv_not_found', 'Tính năng đang tắt.', ['status' => 404]);
        }

        $checked = LeadValidator::validate($input, $source, self::provinceCodes());
        if ($checked['errors'] !== []) {
            return self::validationError($checked['errors']);
        }

        $data  = $checked['data'];
        $extra = [
            '_drv_page_url' => self::sameSiteUrl((string) ($context['page_url'] ?? '')),
            '_drv_utm'      => LeadValidator::sanitizeUtm($context['utm'] ?? []),
            '_drv_ip_hash'  => (string) ($context['ip_hash'] ?? AntiSpamGuard::clientIpHash()),
            '_drv_channel'  => (string) ($context['channel'] ?? 'rest'),
        ];
        if (isset($context['extra_meta']) && is_array($context['extra_meta'])) {
            $extra = array_merge($extra, $context['extra_meta']);
        }

        $created = LeadRepository::create($source, $data, $extra);
        if ($created instanceof \WP_Error) {
            return $created;
        }

        if (empty($context['skip_notify'])) {
            LeadNotifier::send($created['id']);
        }
        do_action('drv/lead/created', $created['id'], $data + ['lead_type' => $source, 'code' => $created['code']]);

        return $created + ['data' => $data];
    }

    /**
     * Fallback không JS: `<form method="post" action="admin-post.php">` + `action=drv_submit_lead`.
     */
    public function handle_classic(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Form public cache tĩnh: dùng Origin + honeypot + rate-limit thay nonce (D-30).
        $post    = wp_unslash($_POST);
        $post    = is_array($post) ? $post : [];
        $return  = self::sameSiteUrl((string) ($post['_drv_return'] ?? wp_get_referer())) ?: home_url('/lien-he');
        $return  = (string) preg_replace('/#.*$/', '', remove_query_arg(['lead_sent', 'lead_error', 'code'], $return));
        $origin  = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_ORIGIN'])) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER'])) : '';
        // phpcs:enable

        $guard = AntiSpamGuard::verifyClassic($post, $origin, $referer);
        if ($guard instanceof \WP_Error) {
            $is_bot = $guard->get_error_code() === AntiSpamGuard::CODE_HONEYPOT;
            $args   = $is_bot ? ['lead_sent' => '1', 'code' => 'LD-' . gmdate('Ymd') . '-0000'] : ['lead_error' => $guard->get_error_code()];
            wp_safe_redirect(add_query_arg($args, $return) . '#drv-lead-feedback', 303);
            exit;
        }

        $result = self::submit($post, (string) ($post['source'] ?? 'contact'), [
            'page_url' => $return,
            'ip_hash'  => $guard,
            'channel'  => 'admin-post',
        ]);

        $args = $result instanceof \WP_Error
            ? ['lead_error' => $result->get_error_code()]
            : ['lead_sent' => '1', 'code' => $result['code']];

        wp_safe_redirect(add_query_arg($args, $return) . '#drv-lead-feedback', 303);
        exit;
    }

    /**
     * @param array<string, string> $fields
     */
    public static function validationError(array $fields, string $message = 'Vui lòng kiểm tra lại thông tin liên hệ.'): \WP_Error {
        return new \WP_Error('drv_validation_failed', $message, ['status' => 422, 'fields' => $fields]);
    }

    /**
     * Dựng response lỗi chuẩn docs/15 §1.2 (kèm `Retry-After` cho `429`).
     */
    public static function errorResponse(\WP_Error $error): \WP_REST_Response {
        $data   = $error->get_error_data();
        $data   = is_array($data) ? $data : [];
        $status = (int) ($data['status'] ?? 400);

        $resp = new \WP_REST_Response([
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data'    => $data + ['status' => $status],
        ], $status);
        $resp->header('Cache-Control', 'no-store');
        if ($status === 429 && isset($data['retry_after'])) {
            $resp->header('Retry-After', (string) (int) $data['retry_after']);
        }
        return $resp;
    }

    /**
     * Honeypot: trả giả lập thành công để bot không dò ra cơ chế chặn (không lưu DB, không gửi mail).
     */
    public static function fakeSuccess(string $prefix): \WP_REST_Response {
        $code = sprintf('%s-%s-%04d', $prefix, gmdate('Ymd'), random_int(1000, 9999));
        $resp = new \WP_REST_Response([
            'ok'         => true,
            'lead_id'    => 0,
            'lead_code'  => $code,
            'quote_code' => $code,
            'message'    => 'Đã tiếp nhận yêu cầu.',
        ], 201);
        $resp->header('Cache-Control', 'no-store');
        return $resp;
    }

    /**
     * @return array<int, string>
     */
    public static function provinceCodes(): array {
        $file = DRV_DIR . '/config/provinces-vn.php';
        $map  = is_readable($file) ? require $file : [];
        return is_array($map) ? array_map('strval', array_keys($map)) : [];
    }

    public static function sameSiteUrl(string $url): string {
        $url = trim($url);
        if ($url === '' || !function_exists('home_url')) {
            return '';
        }
        $clean = esc_url_raw($url);
        $host  = wp_parse_url($clean, PHP_URL_HOST);
        $home  = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return is_string($host) && is_string($home) && strcasecmp($host, $home) === 0 ? $clean : '';
    }

    public static function noCache(): void {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache official hook.
        do_action('litespeed_control_set_nocache', 'dravit lead submit');
    }
}
