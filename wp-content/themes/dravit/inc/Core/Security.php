<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

/**
 * Gia cố bảo mật mức ứng dụng (Step 11, docs/09 §4 Preflight, rule `dravit-security-testing`):
 * - Header an toàn cơ bản trên mọi phản hồi frontend (CSP/HSTS để cấu hình ở máy chủ vì site còn dùng inline script của plugin).
 * - Ẩn danh sách người dùng khỏi REST với khách (chống dò tên đăng nhập `admin`).
 * - Tắt XML-RPC.
 */
final class Security implements ServiceProviderInterface {
    /** @var array<string, string> */
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options'        => 'SAMEORIGIN',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function register(): void {
        add_action('send_headers', [$this, 'send_security_headers']);
        add_filter('rest_endpoints', [$this, 'hide_user_endpoints_from_guests']);
        add_filter('xmlrpc_enabled', '__return_false');
    }

    public function send_security_headers(): void {
        if (headers_sent()) {
            return;
        }
        foreach (self::HEADERS as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public function hide_user_endpoints_from_guests(array $endpoints): array {
        if (is_user_logged_in()) {
            return $endpoints;
        }
        foreach (array_keys($endpoints) as $route) {
            if (str_starts_with((string) $route, '/wp/v2/users')) {
                unset($endpoints[$route]);
            }
        }
        return $endpoints;
    }
}
