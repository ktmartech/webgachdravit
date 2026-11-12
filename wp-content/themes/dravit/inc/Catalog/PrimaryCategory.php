<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Thuật toán chọn & đồng bộ Danh mục chính `PrimaryCategory` theo trọng số `priority` (ADR D-27, docs/03 §3.3).
 */
final class PrimaryCategory {
    /**
     * Chọn slug danh mục chính từ tập `$matched_slugs` theo trọng số `priority` cao nhất.
     * - Bỏ qua danh mục gốc `gach` trừ khi SP chỉ thuộc duy nhất `gach`.
     * - Giữ nguyên `$manual_slug` nếu `$is_manual === true` và `$manual_slug` vẫn nằm trong tập hợp lệ (khác `gach`).
     *
     * @param array<int, string>                  $matched_slugs
     * @param array<string, array<string, mixed>> $collections_config
     */
    public static function resolvePrimarySlug(
        array $matched_slugs,
        array $collections_config,
        ?string $manual_slug = null,
        bool $is_manual = false
    ): ?string {
        if ($matched_slugs === []) {
            return null;
        }

        $candidates = array_values(array_filter(
            $matched_slugs,
            static fn(string $slug): bool => $slug !== 'gach'
        ));

        if ($candidates === []) {
            return in_array('gach', $matched_slugs, true) ? 'gach' : $matched_slugs[0];
        }

        if ($is_manual && $manual_slug !== null && $manual_slug !== '' && $manual_slug !== 'gach') {
            if (in_array($manual_slug, $candidates, true)) {
                return $manual_slug;
            }
        }

        $best_slug     = $candidates[0];
        $best_priority = (int) ($collections_config[$best_slug]['priority'] ?? -1);

        foreach ($candidates as $slug) {
            $prio = (int) ($collections_config[$slug]['priority'] ?? 0);
            if ($prio > $best_priority) {
                $best_priority = $prio;
                $best_slug     = $slug;
            }
        }

        return $best_slug;
    }

    /**
     * Đồng bộ `rank_math_primary_product_cat` và cờ `drv_primary_auto` trên sản phẩm thực tế.
     *
     * @param array<int, string>                  $all_assigned_slugs Tập tất cả slug `product_cat` hiện gán cho SP
     * @param array<string, int>                  $slug_to_term_id    Bản đồ `slug => term_id`
     * @param array<string, array<string, mixed>> $collections_config
     */
    public static function syncForProduct(
        int $product_id,
        array $all_assigned_slugs,
        array $slug_to_term_id,
        array $collections_config
    ): ?int {
        if ($product_id <= 0 || $all_assigned_slugs === []) {
            return null;
        }

        $current_term_id = (int) get_post_meta($product_id, 'rank_math_primary_product_cat', true);
        $auto_flag       = (string) get_post_meta($product_id, 'drv_primary_auto', true);
        $is_manual       = ($current_term_id > 0 && $auto_flag === '0');

        $term_id_to_slug = array_flip($slug_to_term_id);
        $current_slug    = $term_id_to_slug[$current_term_id] ?? null;

        // Nếu Admin tự chọn 1 danh mục thủ công ngoài 28 Smart Collections và nó vẫn đang gán vào SP -> giữ nguyên.
        if ($is_manual && $current_term_id > 0 && $current_slug === null) {
            $manual_term = get_term($current_term_id, 'product_cat');
            if ($manual_term instanceof \WP_Term && in_array($manual_term->slug, $all_assigned_slugs, true) && $manual_term->slug !== 'gach') {
                return $current_term_id;
            }
        }

        $resolved_slug = self::resolvePrimarySlug(
            $all_assigned_slugs,
            $collections_config,
            is_string($current_slug) ? $current_slug : null,
            $is_manual
        );

        if ($resolved_slug === null || !isset($slug_to_term_id[$resolved_slug])) {
            return null;
        }

        $resolved_term_id = $slug_to_term_id[$resolved_slug];
        $was_reassigned   = ($is_manual && $current_term_id !== $resolved_term_id);

        if ($current_term_id !== $resolved_term_id) {
            update_post_meta($product_id, 'rank_math_primary_product_cat', $resolved_term_id);
        }

        if (!$is_manual || $was_reassigned) {
            update_post_meta($product_id, 'drv_primary_auto', '1');
        }

        if ($was_reassigned && function_exists('set_transient')) {
            $new_name = (string) ($collections_config[$resolved_slug]['name'] ?? $resolved_slug);
            set_transient(
                'drv_primary_reassigned_' . $product_id,
                sprintf('Danh mục chính cũ không còn khớp thông số sản phẩm nên hệ thống đã tự động chuyển sang "%s".', $new_name),
                60
            );
        }

        return $resolved_term_id;
    }
}

