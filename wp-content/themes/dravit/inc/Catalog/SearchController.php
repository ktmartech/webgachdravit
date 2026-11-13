<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;
use Dravit\Core\RestSecurity;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Seo\UrlNormalizer;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Bộ điều khiển Tìm kiếm Thông minh Không dấu (REST `/wp-json/dravit/v1/search` + đồng bộ SSR `search.php`)
 * (docs/15 §2.2, docs/19 §3.3–3.5, docs/21 Step 3.3).
 */
class SearchController implements ServiceProviderInterface {
    private const CACHE_TTL = 600;

    /** @var array<int, int>|null Bộ nhớ đệm danh sách ID khớp cho SSR search query */
    private ?array $ssr_matched_ids = null;

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('pre_get_posts', [$this, 'configure_ssr_search_query'], 20);
        add_filter('posts_search', [$this, 'override_ssr_posts_search'], 20, 2);
        add_filter('posts_orderby', [$this, 'override_ssr_posts_orderby'], 20, 2);
    }

    public function register_routes(): void {
        register_rest_route('dravit/v1', '/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_search'],
            'permission_callback' => '__return_true',
            'args'                => [
                'q'     => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => static fn (mixed $v): string => mb_substr(sanitize_text_field((string) $v), 0, 80),
                ],
                'limit' => [
                    'type'              => 'integer',
                    'default'           => 6,
                    'sanitize_callback' => static fn (mixed $v): int => max(1, min(12, (int) $v)),
                ],
            ],
        ]);
    }

    public function handle_search(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $rate_err = RestSecurity::checkRateLimit('search', 60, 60);
        if ($rate_err instanceof \WP_Error) {
            $err_data    = $rate_err->get_error_data();
            $retry_after = is_array($err_data) && isset($err_data['retry_after']) ? (int) $err_data['retry_after'] : 60;
            $resp        = new \WP_REST_Response([
                'code'    => $rate_err->get_error_code(),
                'message' => $rate_err->get_error_message(),
                'data'    => ['status' => 429],
            ], 429);
            $resp->header('Retry-After', (string) $retry_after);
            return $resp;
        }

        $raw_q = trim((string) $request->get_param('q'));
        $limit = max(1, min(12, (int) ($request->get_param('limit') ?? 6)));

        $popular = drv_setting('search.popular_keywords', ['80x80', 'Vân đá Marble', 'Terrazzo', 'Khổ lớn 60x120', 'Chống trượt R10']);
        $popular = is_array($popular) ? array_values(array_map('strval', $popular)) : [];

        // Truy vấn rỗng hoặc < 2 ký tự: trả tức thì từ khóa phổ biến & Silo nổi bật mà không đụng bảng sản phẩm.
        if (mb_strlen($raw_q) < 2) {
            $featured_slugs = drv_setting('search.featured_silos', ['gach-lat-nen', 'gach-phong-khach', 'gach-80x80', 'da-nung-ket']);
            $featured_slugs = is_array($featured_slugs) ? array_map('strval', $featured_slugs) : [];

            $empty_payload = [
                'query'            => $raw_q,
                'popular_keywords' => $popular,
                'silos'            => $this->hydrate_silos($featured_slugs),
                'products'         => [],
                'total'            => 0,
                'view_all'         => UrlNormalizer::untrailingslashUrl(home_url('/gach')),
            ];

            return $this->make_cached_response($empty_payload);
        }

        $norm_q    = SearchNormalizer::normalize($raw_q);
        $cache_ver = (int) get_option('drv_cache_ver', 1);
        $cache_key = 'drv_srch_' . $cache_ver . '_' . substr(md5($norm_q . '|' . $limit), 0, 18);

        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $cached['query'] = $raw_q;
            return $this->make_cached_response($cached);
        }

        $collections_cfg = Config::get('collections');
        $matched_silos   = SearchNormalizer::matchCollectionSlugs($raw_q, $collections_cfg, 4);

        $db_result = self::queryRankedProducts($raw_q, $limit);
        $products  = [];

        if (function_exists('wc_get_product')) {
            foreach ($db_result['ids'] as $pid) {
                $product = wc_get_product($pid);
                if (!$product instanceof \WC_Product || $product->get_status() !== 'publish') {
                    continue;
                }
                $products[] = self::formatSearchProduct($product);
            }
        }

        $view_all = add_query_arg([
            's'         => $raw_q,
            'post_type' => 'product',
        ], home_url('/'));

        $payload = [
            'query'            => $raw_q,
            'popular_keywords' => $popular,
            'silos'            => $this->hydrate_silos($matched_silos),
            'products'         => $products,
            'total'            => $db_result['total'],
            'view_all'         => $view_all,
        ];

        set_transient($cache_key, $payload, self::CACHE_TTL);

        return $this->make_cached_response($payload);
    }

    /**
     * Truy vấn danh sách ID sản phẩm khớp theo trọng số ưu tiên (SKU > Tên SP > `_drv_search_index` không dấu + tồn kho).
     * Sử dụng 100% `$wpdb->prepare()` với câu lệnh tĩnh an toàn tuyệt đối.
     *
     * @return array{ids: array<int, int>, total: int}
     */
    public static function queryRankedProducts(string $raw_query, int $limit = 6): array {
        global $wpdb;

        $tokens = SearchNormalizer::tokenize($raw_query, 6);
        if ($tokens === [] || !isset($wpdb) || !$wpdb instanceof \wpdb) {
            return ['ids' => [], 'total' => 0];
        }

        $token_likes = [];
        foreach ($tokens as $tok) {
            $token_likes[] = '%' . $wpdb->esc_like($tok) . '%';
        }
        while (count($token_likes) < 6) {
            $token_likes[] = $token_likes[0];
        }

        $clean_raw   = trim($raw_query);
        $sku_exact   = strtoupper((string) preg_replace('/\s+/', '', $clean_raw));
        $sku_prefix  = $wpdb->esc_like($sku_exact) . '%';
        $sku_contain = '%' . $wpdb->esc_like($sku_exact) . '%';
        $title_like  = '%' . $wpdb->esc_like($clean_raw) . '%';
        $safe_limit  = max(1, min(500, $limit));

        $count_sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_idx ON (pm_idx.post_id = p.ID AND pm_idx.meta_key = %s)
             LEFT JOIN {$wpdb->postmeta} pm_sku ON (pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku')
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND (
                   pm_sku.meta_value LIKE %s
                   OR p.post_title LIKE %s
                   OR (
                       pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                   )
               )",
            SearchIndex::META_KEY,
            $sku_contain,
            $title_like,
            $token_likes[0],
            $token_likes[1],
            $token_likes[2],
            $token_likes[3],
            $token_likes[4],
            $token_likes[5]
        );

        $total = (int) $wpdb->get_var($count_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared directly above
        if ($total <= 0) {
            return ['ids' => [], 'total' => 0];
        }

        $ids_sql = $wpdb->prepare(
            "SELECT p.ID,
                    (
                        CASE
                            WHEN UPPER(pm_sku.meta_value) = %s THEN 100
                            WHEN pm_sku.meta_value LIKE %s THEN 90
                            WHEN pm_sku.meta_value LIKE %s THEN 80
                            WHEN p.post_title LIKE %s THEN 70
                            ELSE 50
                        END
                        + CASE WHEN pm_stock.meta_value = 'instock' THEN 5 ELSE 0 END
                    ) AS drv_score
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_idx ON (pm_idx.post_id = p.ID AND pm_idx.meta_key = %s)
             LEFT JOIN {$wpdb->postmeta} pm_sku ON (pm_sku.post_id = p.ID AND pm_sku.meta_key = '_sku')
             LEFT JOIN {$wpdb->postmeta} pm_stock ON (pm_stock.post_id = p.ID AND pm_stock.meta_key = '_stock_status')
             WHERE p.post_type = 'product'
               AND p.post_status = 'publish'
               AND (
                   pm_sku.meta_value LIKE %s
                   OR p.post_title LIKE %s
                   OR (
                       pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                       AND pm_idx.meta_value LIKE %s
                   )
               )
             GROUP BY p.ID
             ORDER BY MAX(drv_score) DESC, p.ID DESC
             LIMIT %d",
            $sku_exact,
            $sku_prefix,
            $sku_contain,
            $title_like,
            SearchIndex::META_KEY,
            $sku_contain,
            $title_like,
            $token_likes[0],
            $token_likes[1],
            $token_likes[2],
            $token_likes[3],
            $token_likes[4],
            $token_likes[5],
            $safe_limit
        );

        $rows = $wpdb->get_results($ids_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared directly above
        if (!is_array($rows)) {
            return ['ids' => [], 'total' => $total];
        }

        $ids = [];
        foreach ($rows as $row) {
            if (is_object($row) && isset($row->ID)) {
                $ids[] = (int) $row->ID;
            }
        }

        return [
            'ids'   => $ids,
            'total' => $total,
        ];
    }

    /**
     * Chuẩn hóa đối tượng `\WC_Product` sang JSON item của `/dravit/v1/search` (docs/15 §2.2).
     *
     * @return array<string, mixed>
     */
    public static function formatSearchProduct(\WC_Product $product): array {
        $card = PriceState::toCardData($product);
        $pid  = $product->get_id();

        $w_mm = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $size = ($w_mm > 0 && $h_mm > 0) ? AttributeDeriver::deriveSizeLabel($w_mm, $h_mm) : '';

        $surface_label = '';
        if ($pid > 0 && function_exists('get_the_terms')) {
            $s_terms = get_the_terms($pid, 'pa_be-mat');
            if (is_array($s_terms) && isset($s_terms[0]) && $s_terms[0] instanceof \WP_Term) {
                $short         = trim((string) get_term_meta($s_terms[0]->term_id, 'drv_short_label', true));
                $surface_label = $short !== '' ? $short : $s_terms[0]->name;
            }
        }

        $thumb = (string) ($card['img_primary'] ?? '');
        if ($thumb === '') {
            $thumb = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
        }

        return [
            'id'               => $pid,
            'sku'              => (string) ($card['sku'] ?? ''),
            'name'             => (string) ($card['title'] ?? ''),
            'url'              => UrlNormalizer::untrailingslashUrl((string) ($card['url'] ?? '')),
            'thumb'            => $thumb,
            'size'             => $size,
            'surface_label'    => $surface_label,
            'price_state'      => (string) ($card['price_state'] ?? PriceState::PRICED),
            'price_m2'         => (int) ($card['price_m2'] ?? 0),
            'regular_price_m2' => (int) ($card['regular_price_m2'] ?? 0),
            'price_piece'      => (int) ($card['price_piece'] ?? 0),
            'unit_label'       => (string) ($card['unit_label'] ?? 'viên'),
            'stock_label'      => !empty($card['in_stock']) ? 'Sẵn kho' : 'Đặt trước',
            'showroom_shelf'   => (string) ($card['showroom_shelf'] ?? ''),
        ];
    }

    public function configure_ssr_search_query(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }

        $query->set('post_type', 'product');
        $query->set('post_status', 'publish');
        $query->set('posts_per_page', max(12, (int) drv_setting('catalog.per_page', 24)));
    }

    public function override_ssr_posts_search(string $search_sql, \WP_Query $query): string {
        global $wpdb;

        if (is_admin() || !$query->is_main_query() || !$query->is_search() || !$wpdb instanceof \wpdb) {
            return $search_sql;
        }

        $raw_s = trim((string) $query->get('s'));
        if ($raw_s === '') {
            return $search_sql;
        }

        $result                = self::queryRankedProducts($raw_s, 300);
        $this->ssr_matched_ids = $result['ids'];

        if ($this->ssr_matched_ids === []) {
            return ' AND 1=0 ';
        }

        $id_list = implode(',', array_map('intval', $this->ssr_matched_ids));
        return " AND {$wpdb->posts}.ID IN ({$id_list}) ";
    }

    public function override_ssr_posts_orderby(string $orderby_sql, \WP_Query $query): string {
        global $wpdb;

        if (
            is_admin()
            || !$query->is_main_query()
            || !$query->is_search()
            || !is_array($this->ssr_matched_ids)
            || $this->ssr_matched_ids === []
            || !$wpdb instanceof \wpdb
        ) {
            return $orderby_sql;
        }

        $id_list = implode(',', array_map('intval', $this->ssr_matched_ids));
        return "FIELD({$wpdb->posts}.ID, {$id_list}) ASC";
    }

    /**
     * @param array<int, string> $slugs
     * @return array<int, array<string, mixed>>
     */
    private function hydrate_silos(array $slugs): array {
        $collections  = Config::get('collections');
        $group_labels = [
            'root'     => 'Danh mục gốc',
            'function' => 'Công năng',
            'space'    => 'Không gian',
            'material' => 'Vân & Chất liệu',
            'size'     => 'Kích thước',
        ];

        $items = [];
        foreach ($slugs as $slug) {
            $clean_slug = strtolower(trim($slug));
            if ($clean_slug === '' || !isset($collections[$clean_slug])) {
                continue;
            }
            $cfg   = $collections[$clean_slug];
            $id    = 0;
            $count = 0;
            $thumb = '';

            if (function_exists('get_term_by') && function_exists('taxonomy_exists') && taxonomy_exists('product_cat')) {
                $term = get_term_by('slug', $clean_slug, 'product_cat');
                if ($term instanceof \WP_Term) {
                    $id       = (int) $term->term_id;
                    $count    = (int) $term->count;
                    $thumb_id = (int) get_term_meta($id, 'thumbnail_id', true);
                    if ($thumb_id > 0 && function_exists('wp_get_attachment_image_url')) {
                        $thumb = (string) wp_get_attachment_image_url($thumb_id, 'thumbnail');
                    }
                }
            }

            if ($thumb === '') {
                $preview_img = (string) ($cfg['preview_img'] ?? 'tile-marble-calacatta.svg');
                $thumb       = DRV_URI . '/assets/images/placeholders/' . $preview_img;
            }

            $group_key = (string) ($cfg['group'] ?? 'function');

            $items[] = [
                'id'    => $id,
                'slug'  => $clean_slug,
                'name'  => (string) ($cfg['name'] ?? $clean_slug),
                'url'   => UrlNormalizer::untrailingslashUrl(home_url('/' . $clean_slug)),
                'group' => $group_labels[$group_key] ?? 'Danh mục',
                'count' => $count,
                'thumb' => $thumb,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function make_cached_response(array $payload): \WP_REST_Response {
        do_action('litespeed_tag_add', 'drv_catalog');

        $response = new \WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'public, max-age=300');
        $response->header('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

