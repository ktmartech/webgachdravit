<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * REST Controller cho Trợ lý AI Tìm Gạch (`docs/13` §10, `docs/15` §2.5):
 * - `POST /wp-json/dravit/v1/advisor`
 * - `POST /wp-json/dravit/v1/advisor/feedback`
 */
final class AdvisorController implements ServiceProviderInterface {
    private const PAGE_OPTION_KEY = 'drv_ai_finder_page_id';
    private const PAGE_SLUG       = 'tro-ly-ai-tim-gach';
    private const PAGE_TEMPLATE   = 'page-templates/template-ai-finder.php';

    public function register(): void {
        add_action('init', [$this, 'ensureSystemPage'], 20);
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Khởi tạo idempotent trang hệ thống `/tro-ly-ai-tim-gach` gán sẵn `template-ai-finder.php` (chỉ chạy 1 lần).
     */
    public function ensureSystemPage(): void {
        if (!function_exists('get_option') || !function_exists('wp_insert_post')) {
            return;
        }

        $cachedId = (int) get_option(self::PAGE_OPTION_KEY, 0);
        if ($cachedId > 0 && function_exists('get_post_status') && get_post_status($cachedId) === 'publish') {
            return;
        }

        $existing = function_exists('get_page_by_path')
            ? get_page_by_path(self::PAGE_SLUG, OBJECT, 'page')
            : null;

        if ($existing instanceof \WP_Post) {
            $pageId = (int) $existing->ID;
            if ($existing->post_status !== 'publish' && function_exists('wp_update_post')) {
                wp_update_post(['ID' => $pageId, 'post_status' => 'publish']);
            }
        } else {
            $inserted = wp_insert_post([
                'post_title'   => 'Trợ Lý AI Tìm Gạch & Dự Toán Số Thùng',
                'post_name'    => self::PAGE_SLUG,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ], true);

            if (is_wp_error($inserted) || (int) $inserted <= 0) {
                return;
            }
            $pageId = (int) $inserted;
        }

        update_post_meta($pageId, '_wp_page_template', self::PAGE_TEMPLATE);
        update_post_meta($pageId, '_drv_system_page', 1);
        update_option(self::PAGE_OPTION_KEY, $pageId, true);
    }

    public function registerRoutes(): void {
        register_rest_route('dravit/v1', '/advisor', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleAdvisorQuery'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dravit/v1', '/advisor/feedback', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleAdvisorFeedback'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handleAdvisorQuery(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        if (function_exists('do_action')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache official hook.
            do_action('litespeed_control_set_nocache', 'dravit-advisor');
        }

        $jsonBody = $request->get_json_params();
        $body     = is_array($jsonBody) && $jsonBody !== [] ? $jsonBody : $request->get_body_params();
        if (!is_array($body)) {
            $body = [];
        }

        $guardErr = Guard::checkAdvisorRequest($request, $body);
        if ($guardErr instanceof \WP_Error) {
            return $guardErr;
        }

        $service = new AdvisorService();
        $payload = $service->handleQuery([
            'message'   => isset($body['message']) && is_string($body['message']) ? sanitize_textarea_field($body['message']) : '',
            'slots'     => isset($body['slots']) && is_array($body['slots']) ? $body['slots'] : [],
            'waste_pct' => isset($body['waste_pct']) ? (int) $body['waste_pct'] : 5,
            'from_chip' => !empty($body['from_chip']),
        ]);

        $response = new \WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    public function handleAdvisorFeedback(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        if (function_exists('do_action')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache official hook.
            do_action('litespeed_control_set_nocache', 'dravit-advisor-feedback');
        }

        $jsonBody = $request->get_json_params();
        $body     = is_array($jsonBody) && $jsonBody !== [] ? $jsonBody : $request->get_body_params();
        if (!is_array($body)) {
            $body = [];
        }

        $guardErr = Guard::checkFeedbackRequest($request, $body);
        if ($guardErr instanceof \WP_Error) {
            return $guardErr;
        }

        $requestId = isset($body['request_id']) ? (int) $body['request_id'] : 0;
        $rating    = isset($body['rating']) && is_string($body['rating']) ? sanitize_key($body['rating']) : '';
        $reason    = isset($body['reason']) && is_string($body['reason']) ? sanitize_text_field($body['reason']) : '';

        if ($requestId <= 0 || !in_array($rating, ['up', 'down'], true)) {
            return new \WP_Error('drv_invalid_feedback', 'Dữ liệu đánh giá không hợp lệ.', ['status' => 400]);
        }

        $ok       = (new AdvisorService())->recordFeedback($requestId, $rating, $reason);
        $response = new \WP_REST_Response(['ok' => $ok], 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
