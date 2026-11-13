<?php
declare(strict_types=1);

namespace Dravit\Filters;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Bộ dựng câu lệnh `WP_Query` dùng chung 1-1 cho cả SSR Main Query (`pre_get_posts`)
 * và REST Endpoint `/wp-json/dravit/v1/catalog` (docs/05 §3.1 & §5.1, ADR D-20).
 */
final class QueryBuilder implements ServiceProviderInterface {
    private static ?int $showroom_ttid = null;

    public function register(): void {
        add_action('pre_get_posts', [$this, 'configure_main_archive_query'], 20, 1);
        add_filter('posts_clauses', [$this, 'filter_posts_clauses'], 20, 2);
    }

    /**
     * Xây dựng mảng đối số chuẩn cho `new \WP_Query($args)`.
     *
     * @param array<string, mixed>|null $facets_config
     * @return array<string, mixed>
     */
    public static function buildQueryArgs(UrlState $state, ?array $facets_config = null): array {
        return [
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'posts_per_page'      => $state->perPage(),
            'paged'               => $state->page(),
            'tax_query'           => self::buildTaxQuery($state, $facets_config),
            'drv_catalog_sort'    => $state->sort(),
        ];
    }

    /**
     * Xây dựng `tax_query` chuẩn: `AND` giữa các nhóm facet khác nhau, `IN` (`OR`) giữa các giá trị trong cùng 1 nhóm.
     *
     * @param array<string, mixed>|null $facets_config
     * @return array<int|string, mixed>
     */
    public static function buildTaxQuery(UrlState $state, ?array $facets_config = null): array {
        $tax_query = [
            'relation' => 'AND',
        ];

        $base_tax  = $state->baseTaxonomy();
        $base_slug = $state->baseSlug();

        if ($base_tax === 'product_cat') {
            $tax_query[] = [
                'taxonomy'         => 'product_cat',
                'field'            => 'slug',
                'terms'            => [$base_slug],
                'include_children' => ($base_slug === 'gach'),
                'operator'         => 'IN',
            ];
        } else {
            $tax_query[] = [
                'taxonomy' => $base_tax,
                'field'    => 'slug',
                'terms'    => [$base_slug],
                'operator' => 'IN',
            ];
        }

        $exclude_ttids = self::getExcludedVisibilityTermTaxIds();
        if ($exclude_ttids !== []) {
            $tax_query[] = [
                'taxonomy' => 'product_visibility',
                'field'    => 'term_taxonomy_id',
                'terms'    => $exclude_ttids,
                'operator' => 'NOT IN',
            ];
        }

        $defs = FacetRegistry::getFacets($facets_config);
        foreach ($state->facets() as $param => $values) {
            if ($values === [] || !isset($defs[$param])) {
                continue;
            }

            if ($param === 'showroom') {
                $tax_query[] = [
                    'taxonomy' => 'drv_flag',
                    'field'    => 'slug',
                    'terms'    => ['showroom'],
                    'operator' => 'IN',
                ];
                continue;
            }

            $tax_query[] = [
                'taxonomy' => $defs[$param]['taxonomy'],
                'field'    => 'slug',
                'terms'    => $values,
                'operator' => 'IN',
            ];
        }

        return $tax_query;
    }

    /**
     * Hàm thuần sinh mệnh đề `JOIN` & `ORDER BY`:
     * - Đẩy sản phẩm hết hàng (`outofstock`) xuống cuối danh sách.
     * - Hỗ trợ danh mục hỗn hợp SP có giá (`priced`) & SP Liên hệ báo giá (`quote_only`):
     *   + `moi-ve` (mặc định): Đề xuất nổi bật (`menu_order ASC, post_date DESC`).
     *   + `showroom-truoc`: Ưu tiên sản phẩm có mẫu kệ thực tế tại Showroom (`drv_flag = showroom`).
     *   + `kho-lon` / `kho-nho`: Sắp theo diện tích viên gạch (`drv_piece_w_mm * drv_piece_h_mm`) cho cả SP có giá & báo giá.
     *   + `gia-tang` / `gia-giam`: Sắp theo giá hiệu lực trên m² và LUÔN đẩy SP báo giá (`<= 0đ`) xuống sau SP có giá (ADR D-20).
     *
     * @return array{join: string, orderby: string}
     */
    public static function buildSortSqlClauses(
        string $sort,
        string $posts_table,
        string $postmeta_table,
        string $term_rel_table,
        int $outofstock_ttid = 0,
        int $showroom_ttid = 0
    ): array {
        $joins       = [];
        $order_parts = [];

        if ($outofstock_ttid > 0) {
            $joins[]       = sprintf(
                'LEFT JOIN %s AS drv_oos ON (drv_oos.object_id = %s.ID AND drv_oos.term_taxonomy_id = %d)',
                $term_rel_table,
                $posts_table,
                $outofstock_ttid
            );
            $order_parts[] = 'CASE WHEN drv_oos.object_id IS NOT NULL THEN 1 ELSE 0 END ASC';
        }

        if ($sort === 'showroom-truoc') {
            if ($showroom_ttid > 0) {
                $joins[]       = sprintf(
                    'LEFT JOIN %s AS drv_sr ON (drv_sr.object_id = %s.ID AND drv_sr.term_taxonomy_id = %d)',
                    $term_rel_table,
                    $posts_table,
                    $showroom_ttid
                );
                $order_parts[] = 'CASE WHEN drv_sr.object_id IS NOT NULL THEN 0 ELSE 1 END ASC';
            } else {
                $joins[]       = sprintf(
                    "LEFT JOIN %s AS drv_sr_meta ON (drv_sr_meta.post_id = %s.ID AND drv_sr_meta.meta_key = 'drv_showroom_shelf')",
                    $postmeta_table,
                    $posts_table
                );
                $order_parts[] = "CASE WHEN drv_sr_meta.meta_value IS NOT NULL AND drv_sr_meta.meta_value <> '' THEN 0 ELSE 1 END ASC";
            }
            $order_parts[] = "{$posts_table}.menu_order ASC";
            $order_parts[] = "{$posts_table}.post_date DESC";
            $order_parts[] = "{$posts_table}.ID DESC";
        } elseif ($sort === 'kho-lon' || $sort === 'kho-nho') {
            $joins[] = sprintf(
                "LEFT JOIN %s AS drv_pw ON (drv_pw.post_id = %s.ID AND drv_pw.meta_key = 'drv_piece_w_mm')",
                $postmeta_table,
                $posts_table
            );
            $joins[] = sprintf(
                "LEFT JOIN %s AS drv_ph ON (drv_ph.post_id = %s.ID AND drv_ph.meta_key = 'drv_piece_h_mm')",
                $postmeta_table,
                $posts_table
            );

            $w_expr        = "CAST(COALESCE(drv_pw.meta_value, '0') AS UNSIGNED)";
            $h_expr        = "CAST(COALESCE(drv_ph.meta_value, '0') AS UNSIGNED)";
            $area_expr     = "({$w_expr} * {$h_expr})";
            $max_edge_expr = "GREATEST({$w_expr}, {$h_expr})";
            $dir           = ($sort === 'kho-nho') ? 'ASC' : 'DESC';

            $order_parts[] = "CASE WHEN {$area_expr} <= 0 THEN 1 ELSE 0 END ASC";
            $order_parts[] = "{$area_expr} {$dir}";
            $order_parts[] = "{$max_edge_expr} {$dir}";
            $order_parts[] = "{$posts_table}.menu_order ASC";
            $order_parts[] = "{$posts_table}.ID DESC";
        } elseif ($sort === 'gia-tang' || $sort === 'gia-giam') {
            $joins[] = sprintf(
                "LEFT JOIN %s AS drv_pm2 ON (drv_pm2.post_id = %s.ID AND drv_pm2.meta_key = 'drv_price_m2')",
                $postmeta_table,
                $posts_table
            );
            $joins[] = sprintf(
                "LEFT JOIN %s AS drv_sm2 ON (drv_sm2.post_id = %s.ID AND drv_sm2.meta_key = 'drv_sale_price_m2')",
                $postmeta_table,
                $posts_table
            );
            $joins[] = sprintf(
                "LEFT JOIN %s AS drv_wcs ON (drv_wcs.post_id = %s.ID AND drv_wcs.meta_key = '_sale_price')",
                $postmeta_table,
                $posts_table
            );
            $joins[] = sprintf(
                "LEFT JOIN %s AS drv_wcp ON (drv_wcp.post_id = %s.ID AND drv_wcp.meta_key = '_price')",
                $postmeta_table,
                $posts_table
            );

            $eff_m2_expr = "CASE
                WHEN drv_wcs.meta_value IS NOT NULL
                     AND drv_wcs.meta_value <> ''
                     AND drv_wcp.meta_value = drv_wcs.meta_value
                     AND CAST(COALESCE(drv_sm2.meta_value, '0') AS UNSIGNED) > 0
                THEN CAST(drv_sm2.meta_value AS UNSIGNED)
                ELSE CAST(COALESCE(drv_pm2.meta_value, '0') AS UNSIGNED)
            END";

            $direction     = ($sort === 'gia-giam') ? 'DESC' : 'ASC';
            $order_parts[] = "CASE WHEN ({$eff_m2_expr}) <= 0 THEN 1 ELSE 0 END ASC";
            $order_parts[] = "({$eff_m2_expr}) {$direction}";
            $order_parts[] = "{$posts_table}.menu_order ASC";
            $order_parts[] = "{$posts_table}.ID DESC";
        } else {
            $order_parts[] = "{$posts_table}.menu_order ASC";
            $order_parts[] = "{$posts_table}.post_date DESC";
            $order_parts[] = "{$posts_table}.ID DESC";
        }

        return [
            'join'    => implode("\n", $joins),
            'orderby' => implode(', ', $order_parts),
        ];
    }

    public function configure_main_archive_query(\WP_Query $query): void {
        if ((function_exists('is_admin') && is_admin()) || !$query->is_main_query()) {
            return;
        }

        if (!self::isCatalogArchiveQuery($query)) {
            return;
        }

        $state = UrlState::fromCurrentRequest();
        $args  = self::buildQueryArgs($state);

        $query->set('post_type', 'product');
        $query->set('post_status', 'publish');
        $query->set('posts_per_page', $args['posts_per_page']);
        $query->set('paged', $args['paged']);
        $query->set('tax_query', $args['tax_query']);
        $query->set('drv_catalog_sort', $args['drv_catalog_sort']);
    }

    /**
     * @param array<string, string> $clauses
     * @return array<string, string>
     */
    public function filter_posts_clauses(array $clauses, \WP_Query $query): array {
        $sort = $query->get('drv_catalog_sort');
        if (!is_string($sort) || $sort === '') {
            return $clauses;
        }

        global $wpdb;
        if (!isset($wpdb) || !$wpdb instanceof \wpdb) {
            return $clauses;
        }

        $built = self::buildSortSqlClauses(
            $sort,
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->term_relationships,
            self::getOutOfStockTermTaxId(),
            self::getShowroomTermTaxId()
        );

        $existing_join = $clauses['join'] ?? '';
        if (
            $built['join'] !== ''
            && !str_contains($existing_join, 'drv_pm2')
            && !str_contains($existing_join, 'drv_pw')
            && !str_contains($existing_join, 'drv_sr')
            && !str_contains($existing_join, 'drv_oos')
        ) {
            $clauses['join'] = $existing_join . "\n" . $built['join'];
        }
        $clauses['orderby'] = $built['orderby'];

        if (empty($clauses['groupby'])) {
            $clauses['groupby'] = "{$wpdb->posts}.ID";
        }

        return $clauses;
    }

    private static function isCatalogArchiveQuery(\WP_Query $query): bool {
        if (function_exists('is_shop') && $query->is_post_type_archive('product')) {
            return true;
        }

        if ($query->is_tax('product_cat')) {
            return true;
        }

        if ($query->is_tax(['pa_bo-suu-tap', 'pa_xuat-xu'])) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    private static function getExcludedVisibilityTermTaxIds(): array {
        if (!function_exists('wc_get_product_visibility_term_ids')) {
            return [];
        }

        $vis = wc_get_product_visibility_term_ids();
        if (!is_array($vis) || empty($vis['exclude-from-catalog'])) {
            return [];
        }

        return [(int) $vis['exclude-from-catalog']];
    }

    private static function getOutOfStockTermTaxId(): int {
        if (!function_exists('wc_get_product_visibility_term_ids')) {
            return 0;
        }

        $vis = wc_get_product_visibility_term_ids();
        return (is_array($vis) && !empty($vis['outofstock'])) ? (int) $vis['outofstock'] : 0;
    }

    private static function getShowroomTermTaxId(): int {
        if (self::$showroom_ttid !== null) {
            return self::$showroom_ttid;
        }
        if (!function_exists('get_term_by') || !function_exists('taxonomy_exists') || !taxonomy_exists('drv_flag')) {
            return 0;
        }

        $term                = get_term_by('slug', 'showroom', 'drv_flag');
        self::$showroom_ttid = ($term instanceof \WP_Term) ? (int) $term->term_taxonomy_id : 0;

        return self::$showroom_ttid;
    }
}
