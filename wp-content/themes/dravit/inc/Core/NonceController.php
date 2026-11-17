<?php
declare(strict_types=1);

namespace Dravit\Core;

use Dravit\Leads\AntiSpamGuard;
use Dravit\Leads\LeadController;
use Dravit\Quote\QuoteController;

defined('ABSPATH') || exit;

/**
 * Endpoint cấp Nonce tươi chống LiteSpeed Cache (`GET /wp-json/dravit/v1/nonce` - docs/15 §2.4).
 */
final class NonceController implements ServiceProviderInterface {
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('dravit/v1', '/nonce', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_nonce'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_get_nonce(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        if (function_exists('do_action')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache official hook.
            do_action('litespeed_control_set_nocache', 'dravit-fresh-nonce');
        }

        $origin = trim((string) $request->get_header('origin'));
        if ($origin !== '') {
            $origin_check = RestSecurity::verifyOrigin($request);
            if ($origin_check instanceof \WP_Error) {
                return $origin_check;
            }
        }

        // `ts` + `sig` (HMAC) phục vụ Time-trap lớp 4: server tự xác thực mốc thời gian, client không giả được (D-30).
        $time_token = AntiSpamGuard::issueTimeToken();
        $payload    = [
            'nonces' => [
                'lead'         => wp_create_nonce(LeadController::NONCE_ACTION),
                'quote'        => wp_create_nonce(QuoteController::NONCE_ACTION),
                'advisor'      => wp_create_nonce('drv_advisor_submit'),
                // Không cấp `wc_store_api` ở đây: request REST không kèm `X-WP-Nonce` bị WP coi là khách (user 0)
                // nên nonce lệch với Store API khi khách đã đăng nhập. JS lấy header `Nonce` của GET /wc/store/v1/cart.
            ],
            'ts'     => $time_token['ts'],
            'sig'    => $time_token['sig'],
            'ttl'    => 600,
        ];

        $response = new \WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
