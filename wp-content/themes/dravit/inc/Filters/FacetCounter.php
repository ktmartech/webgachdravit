<?php
declare(strict_types=1);

namespace Dravit\Filters;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Bộ đếm động số lượng sản phẩm theo nguyên tắc "Except-self" (docs/05 §5.2, Test F-9)
 * kèm lưu đệm Transient theo phiên bản `drv_cache_ver` (TTL 12 giờ) và thống kê Hero danh mục.
 */
final class FacetCounter implements ServiceProviderInterface {
    private const CACHE_TTL = 43200; // 12 hours

    public function register(): void {
        add_action('drv/catalog/synced', [self::class, 'bumpCacheVersion'], 20, 0);
        add_action('woocommerce_product_set_stock', [self::class, 'bumpCacheVersion'], 20, 0);
        add_action('woocommerce_product_set_stock_status', [self::class, 'bumpCacheVersion'], 20, 0);
        add_action('created_product_cat', [self::class, 'bumpCacheVersion'], 20, 0);
        add_action('edited_product_cat', [self::class, 'bumpCacheVersion'], 20, 0);
        add_action('delete_product_cat', [self::class, 'bumpCacheVersion'], 20, 0);
    }

    public static function bumpCacheVersion(): void {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }
        $ver = (int) get_option('drv_cache_ver', 1);
        update_option('drv_cache_ver', $ver + 1, true);
    }

    public static function getCacheVersion(): int {
        return function_exists('get_option') ? max(1, (int) get_option('drv_cache_ver', 1)) : 1;
    }

    /**
     * Thuật toán thuần tính số đếm "Except-self" cho toàn bộ 14 nhóm lọc:
     * - Với nhóm `F`: một sản phẩm `P` trong tập nền danh mục được đếm vào các term của `F`
     *   nếu và chỉ nếu `P` thỏa mãn TẤT CẢ các nhóm đang lọc `G != F` (`AND` giữa các nhóm, `OR` trong từng nhóm).
     * - Nhờ bỏ điều kiện tự lọc của chính `F`, các lựa chọn anh em trong cùng nhóm `F` không bị về `(0)` khi chọn 1 mục (`F-9`).
     *
     * @param array<int, int>                                $base_product_ids
     * @param array<int, array<string, array<int, string>>>  $product_term_map `$pid => [$taxonomy => [$slug, ...]]`
     * @param array<string, array<int, string>>              $active_facets    `$param => [$slug, ...]`
     * @param array<string, mixed>|null                      $facets_config
     * @return array<string, array<string, int>>             `$param => [$term_slug => $count]`
     */
    public static function computeExceptSelfCounts(
        array $base_product_ids,
        array $product_term_map,
        array $active_facets,
        ?array $facets_config = null
    ): array {
        $defs   = FacetRegistry::getFacets($facets_config);
        $counts = [];

        foreach ($defs as $param => $def) {
            $counts[$param] = [];
            foreach (array_keys($def['terms']) as $term_slug) {
                $counts[$param][(string) $term_slug] = 0;
            }
        }

        if ($base_product_ids === []) {
            return $counts;
        }

        // Chuẩn hóa điều kiện lọc đang bật sang tên taxonomy thực tế.
        $normalized_active = [];
        foreach ($active_facets as $param => $vals) {
            if ($vals === [] || !isset($defs[$param])) {
                continue;
            }
            $normalized_active[$param] = [
                'taxonomy' => $defs[$param]['taxonomy'],
                'terms'    => ($param === 'showroom') ? ['showroom'] : $vals,
            ];
        }

        foreach ($defs as $target_param => $target_def) {
            $target_tax = $target_def['taxonomy'];

            foreach ($base_product_ids as $pid) {
                if (!self::matchesOtherFacets($pid, $target_param, $normalized_active, $product_term_map)) {
                    continue;
                }

                $assigned_slugs = $product_term_map[$pid][$target_tax] ?? [];
                foreach ($assigned_slugs as $slug) {
                    $counts[$target_param][$slug] = ($counts[$target_param][$slug] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * Đếm tổng số sản phẩm khớp TOÀN BỘ bộ lọc hiện tại (phục vụ chế độ xem trước nhanh `fragment=count` trên Mobile Sheet).
     *
     * @param array<int, int>                                $base_product_ids
     * @param array<int, array<string, array<int, string>>>  $product_term_map
     * @param array<string, array<int, string>>              $active_facets
     * @param array<string, mixed>|null                      $facets_config
     */
    public static function countMatchingProducts(
        array $base_product_ids,
        array $product_term_map,
        array $active_facets,
        ?array $facets_config = null
    ): int {
        if ($base_product_ids === []) {
            return 0;
        }
        if ($active_facets === []) {
            return count($base_product_ids);
        }

        $defs              = FacetRegistry::getFacets($facets_config);
        $normalized_active = [];
        foreach ($active_facets as $param => $vals) {
            if ($vals === [] || !isset($defs[$param])) {
                continue;
            }
            $normalized_active[$param] = [
                'taxonomy' => $defs[$param]['taxonomy'],
                'terms'    => ($param === 'showroom') ? ['showroom'] : $vals,
            ];
        }

        $matched = 0;
        foreach ($base_product_ids as $pid) {
            if (self::matchesOtherFacets($pid, '', $normalized_active, $product_term_map)) {
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * Lấy bảng số đếm Except-self cho `UrlState` hiện tại (có đọc/ghi Transient 12 giờ).
     *
     * @return array<string, array<string, int>>
     */
    public static function getCounts(UrlState $state): array {
        $ver       = self::getCacheVersion();
        $cache_key = 'drv_facets_' . $ver . '_' . md5($state->cacheSignature());

        if (function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $base_ids = self::getBaseProductIds($state);
        $term_idx = self::getCatalogTermIndex();
        $counts   = self::computeExceptSelfCounts($base_ids, $term_idx, $state->facets());

        if (function_exists('set_transient')) {
            set_transient($cache_key, $counts, self::CACHE_TTL);
        }

        return $counts;
    }

    /**
     * Đếm nhanh tổng sản phẩm khớp `UrlState` mà không cần khởi tạo toàn bộ đối tượng `WC_Product`.
     */
    public static function getQuickTotal(UrlState $state): int {
        $base_ids = self::getBaseProductIds($state);
        if (!$state->hasActiveFilters()) {
            return count($base_ids);
        }
        $term_idx = self::getCatalogTermIndex();
        return self::countMatchingProducts($base_ids, $term_idx, $state->facets());
    }

    /**
     * Thống kê tổng quan của trang danh mục gốc (Tổng mẫu, Giá m² thấp nhất > 0, Số mẫu có kệ Showroom) cho Hero Silo.
     *
     * @return array{total: int, min_price_m2: int, showroom_count: int}
     */
    public static function getBaseStats(UrlState $state): array {
        $ver       = self::getCacheVersion();
        $cache_key = 'drv_fstats_' . $ver . '_' . md5($state->base());

        if (function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_array($cached) && isset($cached['total'], $cached['min_price_m2'], $cached['showroom_count'])) {
                return [
                    'total'          => (int) $cached['total'],
                    'min_price_m2'   => (int) $cached['min_price_m2'],
                    'showroom_count' => (int) $cached['showroom_count'],
                ];
            }
        }

        $base_ids = self::getBaseProductIds($state);
        $term_idx = self::getCatalogTermIndex();
        $prices   = self::getCatalogPriceM2Index();

        $showroom_count = 0;
        $min_price_m2   = 0;

        foreach ($base_ids as $pid) {
            $flags = $term_idx[$pid]['drv_flag'] ?? [];
            if (in_array('showroom', $flags, true)) {
                $showroom_count++;
            }

            $eff_m2 = $prices[$pid] ?? 0;
            if ($eff_m2 > 0 && ($min_price_m2 === 0 || $eff_m2 < $min_price_m2)) {
                $min_price_m2 = $eff_m2;
            }
        }

        $stats = [
            'total'          => count($base_ids),
            'min_price_m2'   => $min_price_m2,
            'showroom_count' => $showroom_count,
        ];

        if (function_exists('set_transient')) {
            set_transient($cache_key, $stats, self::CACHE_TTL);
        }

        return $stats;
    }

    /**
     * Lấy danh sách ID sản phẩm thuộc trang danh mục gốc (chưa áp tham số query lọc).
     *
     * @return array<int, int>
     */
    public static function getBaseProductIds(UrlState $state): array {
        if (!class_exists(\WP_Query::class)) {
            return [];
        }

        $ver       = self::getCacheVersion();
        $cache_key = 'drv_fbase_' . $ver . '_' . md5($state->base());

        if (function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return array_values(array_map('intval', $cached));
            }
        }

        $clean_state = UrlState::fromArray($state->base(), [], 1, 24);
        $query_args  = [
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query'              => QueryBuilder::buildTaxQuery($clean_state),
        ];

        $q   = new \WP_Query($query_args);
        $ids = is_array($q->posts) ? array_values(array_map('intval', $q->posts)) : [];

        if (function_exists('set_transient')) {
            set_transient($cache_key, $ids, self::CACHE_TTL);
        }

        return $ids;
    }

    /**
     * Tải chỉ mục quan hệ `(product_id => [taxonomy => [slug, ...]])` của toàn bộ 16 taxonomy bộ lọc bằng đúng 1 câu lệnh `$wpdb->prepare()` tĩnh.
     *
     * @return array<int, array<string, array<int, string>>>
     */
    private static function getCatalogTermIndex(): array {
        global $wpdb;
        if (!isset($wpdb) || !$wpdb instanceof \wpdb) {
            return [];
        }

        $ver       = self::getCacheVersion();
        $cache_key = 'drv_ftermidx_' . $ver;

        if (function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $sql = $wpdb->prepare(
            "SELECT tr.object_id, tt.taxonomy, t.slug
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE p.post_type = %s
               AND p.post_status = %s
               AND tt.taxonomy IN (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            'product',
            'publish',
            'pa_cong-nang',
            'pa_khong-gian',
            'pa_van-gach',
            'pa_chat-lieu',
            'pa_kich-thuoc',
            'pa_bo-suu-tap',
            'pa_xuat-xu',
            'pa_mau-sac',
            'pa_be-mat',
            'pa_phong-cach',
            'pa_hinh-dang',
            'pa_dcof',
            'pa_do-hut-nuoc',
            'pa_do-day',
            'drv_price_band',
            'drv_flag'
        );

        $rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared statically above
        $map  = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid  = (int) ($row['object_id'] ?? 0);
                $tax  = (string) ($row['taxonomy'] ?? '');
                $slug = (string) ($row['slug'] ?? '');
                if ($pid <= 0 || $tax === '' || $slug === '') {
                    continue;
                }
                $map[$pid][$tax][] = $slug;
            }
        }

        if (function_exists('set_transient')) {
            set_transient($cache_key, $map, self::CACHE_TTL);
        }

        return $map;
    }

    /**
     * Tải bảng giá m² hiệu lực của các sản phẩm `publish` để phục vụ thống kê `min_price_m2` trên Hero.
     *
     * @return array<int, int>
     */
    private static function getCatalogPriceM2Index(): array {
        global $wpdb;
        if (!isset($wpdb) || !$wpdb instanceof \wpdb) {
            return [];
        }

        $ver       = self::getCacheVersion();
        $cache_key = 'drv_fpricem2_' . $ver;

        if (function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return array_map('intval', $cached);
            }
        }

        $sql = $wpdb->prepare(
            "SELECT p.ID,
                    pm_reg.meta_value AS reg_m2,
                    pm_sale.meta_value AS sale_m2,
                    pm_wc_sale.meta_value AS wc_sale,
                    pm_wc_price.meta_value AS wc_price
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_reg ON (pm_reg.post_id = p.ID AND pm_reg.meta_key = %s)
             LEFT JOIN {$wpdb->postmeta} pm_sale ON (pm_sale.post_id = p.ID AND pm_sale.meta_key = %s)
             LEFT JOIN {$wpdb->postmeta} pm_wc_sale ON (pm_wc_sale.post_id = p.ID AND pm_wc_sale.meta_key = %s)
             LEFT JOIN {$wpdb->postmeta} pm_wc_price ON (pm_wc_price.post_id = p.ID AND pm_wc_price.meta_key = %s)
             WHERE p.post_type = %s
               AND p.post_status = %s",
            'drv_price_m2',
            'drv_sale_price_m2',
            '_sale_price',
            '_price',
            'product',
            'publish'
        );

        $rows   = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared statically above
        $prices = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $pid      = (int) ($row['ID'] ?? 0);
                $reg_m2   = max(0, (int) ($row['reg_m2'] ?? 0));
                $sale_m2  = max(0, (int) ($row['sale_m2'] ?? 0));
                $wc_sale  = trim((string) ($row['wc_sale'] ?? ''));
                $wc_price = trim((string) ($row['wc_price'] ?? ''));

                if ($pid <= 0 || $reg_m2 <= 0) {
                    continue;
                }

                $is_sale_active = ($wc_sale !== '' && $wc_sale === $wc_price && $sale_m2 > 0 && $sale_m2 < $reg_m2);
                $prices[$pid]   = $is_sale_active ? $sale_m2 : $reg_m2;
            }
        }

        if (function_exists('set_transient')) {
            set_transient($cache_key, $prices, self::CACHE_TTL);
        }

        return $prices;
    }

    /**
     * @param array<string, array{taxonomy: string, terms: array<int, string>}> $normalized_active
     * @param array<int, array<string, array<int, string>>>                     $product_term_map
     */
    private static function matchesOtherFacets(
        int $product_id,
        string $excluded_param,
        array $normalized_active,
        array $product_term_map
    ): bool {
        foreach ($normalized_active as $param => $cond) {
            if ($param === $excluded_param) {
                continue;
            }

            $product_slugs = $product_term_map[$product_id][$cond['taxonomy']] ?? [];
            if ($product_slugs === []) {
                return false;
            }

            $matched_one = false;
            foreach ($cond['terms'] as $wanted) {
                if (in_array($wanted, $product_slugs, true)) {
                    $matched_one = true;
                    break;
                }
            }

            if (!$matched_one) {
                return false;
            }
        }

        return true;
    }
}

