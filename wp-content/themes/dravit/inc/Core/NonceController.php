<?php
declare(strict_types=1);

namespace Dravit\Core;

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

        $payload = [
            'nonces' => [
                'lead'         => wp_create_nonce('drv_lead_submit'),
                'quote'        => wp_create_nonce('drv_quote_submit'),
                'advisor'      => wp_create_nonce('drv_advisor_submit'),
                'wc_store_api' => wp_create_nonce('wc_store_api'),
            ],
            'ts'     => time(),
            'ttl'    => 600,
        ];

        $response = new \WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
