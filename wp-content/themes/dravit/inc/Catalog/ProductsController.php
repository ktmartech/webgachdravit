<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Quote\BoqService;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * REST Endpoint `GET /wp-json/dravit/v1/products` (docs/15 §2.3):
 * - Hydrate theo `include=101,102,...` (giữ thứ tự) hoặc `skus=DRV-1,DRV-2` (migrate dữ liệu báo giá cũ).
 * - Duyệt / tìm cho Modal chọn gạch BOQ: `search` (không dấu), `size` (`pa_kich-thuoc`), `space` (`pa_khong-gian`), `page`, `per_page`.
 * - Trả `items` (card data + quy cách BOQ: `area_piece`, `kg_per_box`, `size_label`, `color_name`, `swatch_hex`...)
 *   và tùy chọn `cards_html` dựng bằng chính `drv_ui('product-card')` (Single Source of Markup).
 */
final class ProductsController implements ServiceProviderInterface {
    private const MAX_IDS = 30;

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $text = ['type' => 'string', 'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field'];
        register_rest_route('dravit/v1', '/products', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_products'],
            'permission_callback' => '__return_true',
            'args'                => [
                'include'    => $text,
                'skus'       => $text,
                'search'     => $text,
                'size'       => $text,
                'space'      => $text,
                'pattern'    => $text,
                'surface'    => $text,
                'page'       => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 200],
                'per_page'   => ['type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => self::MAX_IDS],
                'cards_html' => ['type' => 'boolean', 'default' => false],
            ],
        ]);
    }

    public function handle_get_products(\WP_REST_Request $request): \WP_REST_Response {
        $want_html = (bool) $request->get_param('cards_html');
        $ids       = self::parseIds((string) $request->get_param('include'));
        $skus      = self::parseSkus((string) $request->get_param('skus'));
        $page      = max(1, (int) $request->get_param('page'));
        $per_page  = max(1, min(self::MAX_IDS, (int) $request->get_param('per_page')));
        $total     = 0;
        $max_pages = 1;

        if ($ids === [] && $skus !== [] && function_exists('wc_get_product_id_by_sku')) {
            foreach ($skus as $sku) {
                $pid = (int) wc_get_product_id_by_sku($sku);
                if ($pid > 0 && !in_array($pid, $ids, true)) {
                    $ids[] = $pid;
                }
            }
        }

        $is_browse = $ids === [] && $skus === [];
        if ($is_browse) {
            [$ids, $total, $max_pages] = self::browse(
                (string) $request->get_param('search'),
                sanitize_title((string) $request->get_param('size')),
                sanitize_title((string) $request->get_param('space')),
                sanitize_title((string) $request->get_param('pattern')),
                sanitize_title((string) $request->get_param('surface')),
                $page,
                $per_page
            );
        }

        $items      = [];
        $cards_list = [];
        if ($ids !== [] && function_exists('wc_get_product')) {
            foreach ($ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product instanceof \WC_Product || $product->get_status() !== 'publish' || $product->get_catalog_visibility() === 'hidden') {
                    continue;
                }
                $items[] = self::itemData($product);
                if ($want_html) {
                    $cards_list[] = drv_ui_html('product-card', ['product' => $product, 'context' => 'related', 'heading_tag' => 'h3']);
                }
            }
        }

        $resp = new \WP_REST_Response([
            'items'      => $items,
            'total'      => $is_browse ? $total : count($items),
            'page'       => $is_browse ? $page : 1,
            'max_pages'  => $is_browse ? $max_pages : 1,
            'cards_html' => implode("\n", $cards_list),
        ], 200);

        $resp->header('X-Robots-Tag', 'noindex, nofollow');
        $resp->header('Cache-Control', 'public, max-age=180');

        return $resp;
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemData(\WC_Product $product): array {
        $card = PriceState::toCardData($product);
        $boq  = BoqService::productData($product);
        $id   = $product->get_id();

        $color_term = self::firstTerm($id, 'pa_mau-sac');
        $color_name = trim((string) $product->get_meta('drv_color_name', true));
        if ($color_name === '' && $color_term instanceof \WP_Term) {
            $color_name = $color_term->name;
        }
        $swatch_hex = $color_term instanceof \WP_Term ? (string) get_term_meta($color_term->term_id, 'drv_swatch_hex', true) : '';
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $swatch_hex) !== 1) {
            $swatch_hex = '';
        }
        $surface = self::firstTerm($id, 'pa_be-mat');
        $dcof    = self::firstTerm($id, 'pa_dcof');

        return $card + [
            'name'          => $product->get_name(),
            'thumb'         => (string) ($card['img_primary'] ?? ''),
            'area_piece'    => $boq['area_piece'],
            'kg_per_box'    => $boq['kg_per_box'],
            'kg_per_piece'  => $boq['pieces_per_box'] > 0 ? round($boq['kg_per_box'] / $boq['pieces_per_box'], 3) : 0.0,
            'size_label'    => $boq['size_label'],
            'color_name'    => $color_name,
            'swatch_hex'    => $swatch_hex,
            'surface_label' => $surface instanceof \WP_Term ? $surface->name : '',
            'dcof_label'    => $dcof instanceof \WP_Term ? $dcof->name : '',
            'stock_qty'     => $boq['stock_qty'],
            'is_tile'       => (string) ($product->get_meta('drv_line', true) ?: 'gach') === 'gach',
        ];
    }

    /**
     * @return array{0: array<int, int>, 1: int, 2: int}
     */
    private static function browse(string $search, string $size, string $space, string $pattern, string $surface, int $page, int $per_page): array {
        if (!class_exists('WP_Query')) {
            return [[], 0, 1];
        }

        $meta_query = [
            'relation' => 'OR',
            ['key' => 'drv_line', 'value' => 'gach'],
            ['key' => 'drv_line', 'compare' => 'NOT EXISTS'],
        ];
        $args = [
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'paged'               => $page,
            'fields'              => 'ids',
            'ignore_sticky_posts' => true,
            'orderby'             => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'meta_query'          => [$meta_query], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        ];

        $tax = [];
        if ($size !== '') {
            $tax[] = ['taxonomy' => 'pa_kich-thuoc', 'field' => 'slug', 'terms' => [$size]];
        }
        if ($space !== '') {
            $tax[] = ['taxonomy' => 'pa_khong-gian', 'field' => 'slug', 'terms' => [$space]];
        }
        if ($pattern !== '') {
            $tax[] = ['taxonomy' => 'pa_van-gach', 'field' => 'slug', 'terms' => [$pattern]];
        }
        if ($surface !== '') {
            $tax[] = ['taxonomy' => 'pa_be-mat', 'field' => 'slug', 'terms' => [$surface]];
        }
        if ($tax !== []) {
            $args['tax_query'] = ['relation' => 'AND'] + $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        }

        $q = mb_substr(trim($search), 0, 60);
        if ($q !== '') {
            $folded               = SearchNormalizer::asciiFold($q);
            $args['meta_query'][] = [
                'relation' => 'OR',
                ['key' => SearchIndex::META_KEY, 'value' => $folded, 'compare' => 'LIKE'],
                ['key' => '_sku', 'value' => $q, 'compare' => 'LIKE'],
            ];
            $args['meta_query']['relation'] = 'AND';
        }

        $query = new \WP_Query($args);
        return [array_map('intval', $query->posts), (int) $query->found_posts, max(1, (int) $query->max_num_pages)];
    }

    /**
     * @return array<int, int>
     */
    public static function parseIds(string $raw): array {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $pid = absint(trim($part));
            if ($pid > 0 && !in_array($pid, $ids, true)) {
                $ids[] = $pid;
            }
            if (count($ids) >= self::MAX_IDS) {
                break;
            }
        }
        return $ids;
    }

    /**
     * @return array<int, string>
     */
    public static function parseSkus(string $raw): array {
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $sku = strtoupper((string) preg_replace('/[^A-Za-z0-9_.\-]/', '', trim($part)));
            if ($sku !== '' && !in_array($sku, $out, true)) {
                $out[] = $sku;
            }
            if (count($out) >= self::MAX_IDS) {
                break;
            }
        }
        return $out;
    }

    private static function firstTerm(int $product_id, string $taxonomy): ?\WP_Term {
        $terms = function_exists('get_the_terms') ? get_the_terms($product_id, $taxonomy) : false;
        return is_array($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term ? $terms[0] : null;
    }
}
