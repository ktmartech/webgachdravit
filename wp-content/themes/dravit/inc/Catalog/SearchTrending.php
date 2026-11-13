<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Cơ chế Hybrid 3 tầng cho khối "Sản phẩm tìm kiếm nhiều" (Phương án A, tuân thủ D-07 không tạo Custom Table):
 * - Tầng 1: Admin ghim tay (`drv_settings.search.pinned_product_ids`).
 * - Tầng 2: Tự học theo lượt click thực tế từ tìm kiếm (`_drv_search_hits >= MIN_LEARNED_HITS`), gom đệm qua transient.
 * - Tầng 3: Cold-start cho website mới (SP còn hàng có giá niêm yết -> WC Featured -> Bán chạy -> Mới nhất).
 */
final class SearchTrending {
    public const META_HITS             = '_drv_search_hits';
    public const BUFFER_TRANSIENT      = 'drv_srch_hit_buf';
    public const LAST_FLUSH_TRANSIENT  = 'drv_srch_hit_flush_ts';
    public const DEDUP_PREFIX          = 'drv_srch_dedup_';
    public const MIN_LEARNED_HITS      = 3;
    public const MAX_PINNED            = 4;
    public const DEFAULT_LIMIT         = 4;
    public const FLUSH_INTERVAL        = 900;
    public const FLUSH_BATCH_THRESHOLD = 20;
    public const DEDUP_TTL             = 300;

    /**
     * Trả về danh sách ID sản phẩm đang được Admin ghim hợp lệ (tối đa `MAX_PINNED = 4`, loại bỏ SP đã xóa/thùng rác).
     *
     * @return array<int, int>
     */
    public static function getPinnedIds(): array {
        $raw = drv_setting('search.pinned_product_ids', []);
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $item) {
            $pid = max(0, (int) $item);
            if ($pid <= 0 || in_array($pid, $clean, true)) {
                continue;
            }
            if (function_exists('get_post_type') && function_exists('get_post_status')) {
                $status = get_post_status($pid);
                if (get_post_type($pid) !== 'product' || $status === false || $status === 'trash') {
                    continue;
                }
            }
            $clean[] = $pid;
            if (count($clean) >= self::MAX_PINNED) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Ghim hoặc gỡ ghim 1 sản phẩm với cơ chế chặn cứng khi đã đủ `MAX_PINNED` (4/4).
     *
     * @return array{ok: bool, status: string, pinned_ids: array<int, int>}
     */
    public static function setProductPinned(int $product_id, bool $should_pin): array {
        $pinned = self::getPinnedIds();
        if ($product_id <= 0) {
            return ['ok' => false, 'status' => 'invalid', 'pinned_ids' => $pinned];
        }

        $existing_idx = array_search($product_id, $pinned, true);

        if ($should_pin) {
            if ($existing_idx !== false) {
                return ['ok' => true, 'status' => 'already_pinned', 'pinned_ids' => $pinned];
            }
            if (count($pinned) >= self::MAX_PINNED) {
                return ['ok' => false, 'status' => 'limit_reached', 'pinned_ids' => $pinned];
            }
            $pinned[] = $product_id;
            self::persistPinnedIds($pinned);

            return ['ok' => true, 'status' => 'pinned', 'pinned_ids' => $pinned];
        }

        if ($existing_idx === false) {
            return ['ok' => true, 'status' => 'unchanged', 'pinned_ids' => $pinned];
        }

        unset($pinned[$existing_idx]);
        $pinned = array_values($pinned);
        self::persistPinnedIds($pinned);

        return ['ok' => true, 'status' => 'unpinned', 'pinned_ids' => $pinned];
    }

    /**
     * Trả về thông tin tóm tắt (ID, SKU, Tên, Link sửa, Tồn kho) của các sản phẩm đang ghim để hiển thị trên WP-Admin.
     *
     * @param array<int, int>|null $ids
     * @return array<int, array{id: int, sku: string, title: string, edit_url: string, in_stock: bool}>
     */
    public static function describePinnedProducts(?array $ids = null): array {
        $target_ids = $ids ?? self::getPinnedIds();
        $items      = [];

        foreach ($target_ids as $pid) {
            $id = max(0, (int) $pid);
            if ($id <= 0) {
                continue;
            }
            $sku      = function_exists('get_post_meta') ? trim((string) get_post_meta($id, '_sku', true)) : '';
            $title    = function_exists('get_the_title') ? trim((string) get_the_title($id)) : '';
            $stock    = function_exists('get_post_meta') ? (string) get_post_meta($id, '_stock_status', true) : 'instock';
            $edit_url = function_exists('get_edit_post_link') ? (string) get_edit_post_link($id, 'raw') : '';

            $items[] = [
                'id'       => $id,
                'sku'      => $sku !== '' ? $sku : ('#' . $id),
                'title'    => $title !== '' ? $title : ('Sản phẩm #' . $id),
                'edit_url' => $edit_url,
                'in_stock' => ($stock === '' || $stock === 'instock'),
            ];
        }

        return $items;
    }

    /**
     * @param array<int, int> $pinned_ids
     */
    private static function persistPinnedIds(array $pinned_ids): void {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }
        $stored = get_option(\Dravit\Core\Settings::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];
        if (!isset($stored['search']) || !is_array($stored['search'])) {
            $stored['search'] = [];
        }
        $stored['search']['pinned_product_ids'] = array_slice(array_values($pinned_ids), 0, self::MAX_PINNED);
        update_option(\Dravit\Core\Settings::OPTION_KEY, $stored);
        \Dravit\Core\Settings::flush_cache();
        SearchController::purgeInitialCache();
    }

    /**
     * Hàm thuần hợp nhất ID 3 tầng ưu tiên không trùng lặp, giữ nguyên thứ tự từng tầng.
     *
     * @param array<int, int> $pinned_ids   Tầng 1: ID do Admin ghim tay.
     * @param array<int, int> $learned_ids  Tầng 2: ID có lượt click tìm kiếm `>= MIN_LEARNED_HITS` giảm dần.
     * @param array<int, int> $fallback_ids Tầng 3: ID Cold-start (Featured / Bestseller / Mới nhất).
     * @return array<int, int>
     */
    public static function mergeTierIds(
        array $pinned_ids,
        array $learned_ids,
        array $fallback_ids,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        $safe_limit = max(1, min(12, $limit));
        $merged     = [];
        $seen       = [];

        foreach ([$pinned_ids, $learned_ids, $fallback_ids] as $tier) {
            foreach ($tier as $raw_id) {
                $id = (int) $raw_id;
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $merged[]  = $id;
                if (count($merged) >= $safe_limit) {
                    return $merged;
                }
            }
        }

        return $merged;
    }

    /**
     * Truy vấn danh sách ID sản phẩm "Sản phẩm tìm kiếm nhiều" theo Hybrid 3 tầng trên DB thực tế.
     *
     * @return array<int, int>
     */
    public static function resolveTrendingIds(int $limit = self::DEFAULT_LIMIT): array {
        global $wpdb;

        $safe_limit = max(1, min(12, $limit));
        if (!isset($wpdb) || !$wpdb instanceof \wpdb) {
            return [];
        }

        // Tầng 1: Kiểm tra danh sách Admin ghim tay còn publish & instock (giữ đúng thứ tự ghim).
        $pinned_ids = [];
        foreach (self::getPinnedIds() as $pid) {
            if (self::isEligibleProduct($pid)) {
                $pinned_ids[] = $pid;
            }
        }

        if (count($pinned_ids) >= $safe_limit) {
            return array_slice(array_values(array_unique($pinned_ids)), 0, $safe_limit);
        }

        // Tầng 2: Các sản phẩm đạt ngưỡng lượt click tìm kiếm thực tế (`_drv_search_hits >= MIN_LEARNED_HITS`).
        $learned_sql = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_hits ON (pm_hits.post_id = p.ID AND pm_hits.meta_key = %s)
             LEFT JOIN {$wpdb->postmeta} pm_stock ON (pm_stock.post_id = p.ID AND pm_stock.meta_key = '_stock_status')
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND COALESCE(pm_stock.meta_value, 'instock') = 'instock'
               AND CAST(pm_hits.meta_value AS UNSIGNED) >= %d
             ORDER BY CAST(pm_hits.meta_value AS UNSIGNED) DESC, p.ID DESC
             LIMIT %d",
            self::META_HITS,
            self::MIN_LEARNED_HITS,
            $safe_limit * 2
        );

        $learned_col = $wpdb->get_col($learned_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared directly above
        $learned_ids = is_array($learned_col) ? array_map('intval', $learned_col) : [];

        // Tầng 3: Cold-start cho website mới (ưu tiên SP có giá m² minh bạch -> WC Featured -> Bán chạy -> Thứ tự menu/ID).
        $featured_tt_id = self::resolveFeaturedTermTaxonomyId();
        $fallback_sql   = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_stock ON (pm_stock.post_id = p.ID AND pm_stock.meta_key = '_stock_status')
             LEFT JOIN {$wpdb->postmeta} pm_price ON (pm_price.post_id = p.ID AND pm_price.meta_key = 'drv_price_m2')
             LEFT JOIN {$wpdb->postmeta} pm_sales ON (pm_sales.post_id = p.ID AND pm_sales.meta_key = 'total_sales')
             LEFT JOIN {$wpdb->term_relationships} tr_feat
               ON (tr_feat.object_id = p.ID AND tr_feat.term_taxonomy_id = %d)
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND COALESCE(pm_stock.meta_value, 'instock') = 'instock'
             ORDER BY
               (CASE WHEN CAST(COALESCE(pm_price.meta_value, '0') AS UNSIGNED) > 0 THEN 1 ELSE 0 END) DESC,
               (CASE WHEN tr_feat.object_id IS NOT NULL THEN 1 ELSE 0 END) DESC,
               CAST(COALESCE(pm_sales.meta_value, '0') AS UNSIGNED) DESC,
               p.menu_order ASC,
               p.ID ASC
             LIMIT %d",
            $featured_tt_id,
            $safe_limit * 3
        );

        $fallback_col = $wpdb->get_col($fallback_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared directly above
        $fallback_ids = is_array($fallback_col) ? array_map('intval', $fallback_col) : [];

        return self::mergeTierIds($pinned_ids, $learned_ids, $fallback_ids, $safe_limit);
    }

    /**
     * Ghi nhận 1 lượt click vào sản phẩm từ tìm kiếm vào bộ đệm transient (chống spam cùng IP trong 5 phút).
     *
     * @return array{recorded: bool, buffered_total: int, flushed: bool}
     */
    public static function recordProductClick(int $product_id, string $client_fingerprint = ''): array {
        if ($product_id <= 0 || !self::isEligibleProduct($product_id, false)) {
            return ['recorded' => false, 'buffered_total' => 0, 'flushed' => false];
        }

        if ($client_fingerprint !== '' && function_exists('get_transient') && function_exists('set_transient')) {
            $dedup_key = self::DEDUP_PREFIX . substr(md5($client_fingerprint . ':' . $product_id), 0, 20);
            if (get_transient($dedup_key) !== false) {
                return ['recorded' => false, 'buffered_total' => 0, 'flushed' => false];
            }
            set_transient($dedup_key, 1, self::DEDUP_TTL);
        }

        $buf = function_exists('get_transient') ? get_transient(self::BUFFER_TRANSIENT) : [];
        $buf = is_array($buf) ? $buf : [];

        $buf[$product_id] = max(0, (int) ($buf[$product_id] ?? 0)) + 1;

        if (function_exists('set_transient')) {
            set_transient(self::BUFFER_TRANSIENT, $buf, DAY_IN_SECONDS);
        }

        $buffered_total = 0;
        foreach ($buf as $delta) {
            $buffered_total += max(0, (int) $delta);
        }

        $last_flush = function_exists('get_transient') ? (int) get_transient(self::LAST_FLUSH_TRANSIENT) : 0;
        $now        = time();
        $flushed    = false;

        if ($buffered_total >= self::FLUSH_BATCH_THRESHOLD || ($now - $last_flush) >= self::FLUSH_INTERVAL) {
            self::flushBufferNow();
            $flushed = true;
        }

        return [
            'recorded'       => true,
            'buffered_total' => $buffered_total,
            'flushed'        => $flushed,
        ];
    }

    /**
     * Đẩy toàn bộ lượt click đang gom trong bộ đệm transient xuống `post_meta` (`_drv_search_hits`).
     *
     * @return int Số sản phẩm đã được cộng dồn điểm vào DB.
     */
    public static function flushBufferNow(): int {
        if (!function_exists('get_transient') || !function_exists('delete_transient') || !function_exists('update_post_meta')) {
            return 0;
        }

        $buf = get_transient(self::BUFFER_TRANSIENT);
        delete_transient(self::BUFFER_TRANSIENT);
        set_transient(self::LAST_FLUSH_TRANSIENT, time(), DAY_IN_SECONDS);

        if (!is_array($buf) || $buf === []) {
            return 0;
        }

        $updated_products = 0;
        $crossed_tier2    = false;

        foreach ($buf as $raw_pid => $raw_delta) {
            $pid   = max(0, (int) $raw_pid);
            $delta = max(0, (int) $raw_delta);
            if ($pid <= 0 || $delta <= 0 || !self::isEligibleProduct($pid, false)) {
                continue;
            }

            $current  = max(0, (int) get_post_meta($pid, self::META_HITS, true));
            $next_val = $current + $delta;
            update_post_meta($pid, self::META_HITS, $next_val);
            $updated_products++;

            if ($next_val >= self::MIN_LEARNED_HITS) {
                $crossed_tier2 = true;
            }
        }

        if ($crossed_tier2) {
            SearchController::purgeInitialCache();
        }

        return $updated_products;
    }

    private static function isEligibleProduct(int $product_id, bool $require_instock = true): bool {
        if ($product_id <= 0 || !function_exists('get_post_type') || !function_exists('get_post_status')) {
            return false;
        }
        if (get_post_type($product_id) !== 'product' || get_post_status($product_id) !== 'publish') {
            return false;
        }
        if ($require_instock && function_exists('get_post_meta')) {
            $stock = (string) get_post_meta($product_id, '_stock_status', true);
            if ($stock !== '' && $stock !== 'instock') {
                return false;
            }
        }
        return true;
    }

    private static function resolveFeaturedTermTaxonomyId(): int {
        if (!function_exists('get_term_by') || !function_exists('taxonomy_exists') || !taxonomy_exists('product_visibility')) {
            return 0;
        }
        $term = get_term_by('name', 'featured', 'product_visibility');
        return ($term instanceof \WP_Term) ? (int) $term->term_taxonomy_id : 0;
    }
}

