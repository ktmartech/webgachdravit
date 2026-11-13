<?php
declare(strict_types=1);

namespace Dravit\Advisor\Log;

use Dravit\Advisor\Contracts\AdvisorLogInterface;
use Dravit\Advisor\Support\PiiRedactor;
use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Lưu nhật ký truy vấn Trợ lý AI đã ẩn PII vào CPT nội bộ `drv_ai_query` (0 bảng DB riêng, tự dọn sau 90 ngày - ADR D-07, D-43).
 */
final class CptAdvisorLogger implements AdvisorLogInterface, ServiceProviderInterface {
    public const POST_TYPE = 'drv_ai_query';
    public const CRON_HOOK = 'drv_advisor_cleanup_logs';

    public function register(): void {
        add_action('init', [$this, 'registerPostType'], 20);
        add_action('init', [$this, 'ensureCleanupCron'], 35);
        add_action(self::CRON_HOOK, [$this, 'runDailyCleanup']);
    }

    public function registerPostType(): void {
        if (!function_exists('register_post_type')) {
            return;
        }

        $showMenu = drv_is_woo_active() ? 'woocommerce' : 'tools.php';

        register_post_type(self::POST_TYPE, [
            'labels'              => [
                'name'          => 'Nhật ký Trợ lý AI',
                'singular_name' => 'Truy vấn Trợ lý AI',
                'search_items'  => 'Tìm truy vấn AI',
                'not_found'     => 'Chưa có truy vấn AI nào được ghi nhận.',
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => (bool) drv_setting('features.ai_enabled', false),
            'show_in_menu'        => $showMenu,
            'show_in_rest'        => false,
            'supports'            => ['title', 'custom-fields'],
            'capability_type'     => 'post',
            'capabilities'        => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap'        => true,
        ]);
    }

    public function ensureCleanupCron(): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 7200, 'daily', self::CRON_HOOK);
        }
    }

    public function runDailyCleanup(): void {
        $rules = Config::get('ai-rules');
        $days  = max(7, (int) ($rules['log_retention_days'] ?? 90));
        $this->cleanupOldLogs($days);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function logQuery(array $record): int {
        if (!function_exists('wp_insert_post')) {
            return 0;
        }

        $rawQuery      = isset($record['message']) && is_string($record['message']) ? $record['message'] : '';
        $redactedQuery = PiiRedactor::redact($rawQuery);
        $intent        = isset($record['intent']) && is_string($record['intent']) ? $record['intent'] : 'recommend';
        $provider      = isset($record['provider']) && is_string($record['provider']) ? $record['provider'] : 'rule_only';

        $title = sprintf(
            '[%s] %s',
            strtoupper($intent),
            $redactedQuery !== '' ? mb_substr($redactedQuery, 0, 80, 'UTF-8') : '(Chọn chip nhanh)'
        );

        $postId = wp_insert_post([
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'private',
            'post_title'   => $title,
            'post_content' => $redactedQuery,
        ], true);

        if (is_wp_error($postId) || (int) $postId <= 0) {
            return 0;
        }

        $id = (int) $postId;
        update_post_meta($id, '_drv_ai_intent', sanitize_key($intent));
        update_post_meta($id, '_drv_ai_provider', sanitize_key($provider));
        update_post_meta($id, '_drv_ai_degraded', !empty($record['degraded']) ? '1' : '0');
        update_post_meta($id, '_drv_ai_latency_ms', (int) ($record['latency_ms'] ?? 0));
        update_post_meta($id, '_drv_ai_catalog_ver', sanitize_text_field((string) ($record['catalog_version'] ?? '')));
        update_post_meta($id, '_drv_ai_slots', (string) wp_json_encode($record['slots'] ?? [], JSON_UNESCAPED_UNICODE));
        update_post_meta($id, '_drv_ai_item_ids', (string) wp_json_encode($record['item_ids'] ?? []));
        update_post_meta($id, '_drv_ai_kb_refs', (string) wp_json_encode($record['kb_refs'] ?? []));

        return $id;
    }

    public function recordFeedback(int $requestId, string $rating, string $reason = ''): bool {
        if ($requestId <= 0 || !in_array($rating, ['up', 'down'], true) || !function_exists('get_post')) {
            return false;
        }

        $post = get_post($requestId);
        if (!($post instanceof \WP_Post) || $post->post_type !== self::POST_TYPE) {
            return false;
        }

        update_post_meta($requestId, '_drv_feedback_rating', $rating);
        if ($reason !== '') {
            update_post_meta($requestId, '_drv_feedback_reason', PiiRedactor::redact(sanitize_text_field($reason)));
        }
        update_post_meta($requestId, '_drv_feedback_at', gmdate('c'));

        return true;
    }

    public function cleanupOldLogs(int $days = 90): int {
        if (!function_exists('get_posts') || !function_exists('wp_delete_post')) {
            return 0;
        }

        $cutoff  = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $deleted = 0;

        /** @var array<int, int> $oldIds */
        $oldIds = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'date_query'     => [
                [
                    'column' => 'post_date_gmt',
                    'before' => $cutoff,
                ],
            ],
        ]);

        foreach ($oldIds as $oldId) {
            if (wp_delete_post((int) $oldId, true)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

