<?php
declare(strict_types=1);

namespace Dravit\Filters;

use Dravit\Core\Config;
use Dravit\Core\Template;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Bộ xuất dữ liệu cấu trúc & HTML fragments đồng nhất 100% giữa SSR (`archive-product.php`)
 * và REST API (`/wp-json/dravit/v1/catalog`) (docs/05 §5, docs/15 §2.1, Test M-13, F-9, F-13, F-14).
 */
final class Renderer {
    /** @var array<int, string> Các nhóm kỹ thuật/giá ít lựa chọn luôn giữ thang chuẩn để đối chiếu */
    private const FIXED_SCALE_PARAMS = [
        'showroom',
        'gia',
        'dcof',
        'do-hut-nuoc',
        'do-day',
    ];

    /**
     * Dựng cấu trúc dữ liệu hiển thị cho cả JSON `facets` lẫn template `filters-inner.php`.
     *
     * @param array<string, array<string, int>>      $counts      Số đếm Except-self theo trạng thái lọc hiện tại
     * @param array<string, array<string, int>>|null $base_counts Số đếm nền của danh mục gốc khi chưa áp bộ lọc phụ
     * @param array<string, mixed>|null              $facets_config
     * @return array{
     *     facets: array<int, array<string, mixed>>,
     *     showroom: array<string, mixed>|null,
     *     ui_groups: array<int, array<string, mixed>>
     * }
     */
    public static function buildFilterViewData(
        UrlState $state,
        array $counts,
        ?array $base_counts = null,
        ?array $facets_config = null
    ): array {
        $f_cfg          = $facets_config ?? Config::get('facets');
        $defs           = FacetRegistry::getFacets($f_cfg);
        $active_facets  = $state->facets();
        $hidden_facets  = $state->hiddenFacets();
        $hidden_terms   = $state->hiddenTerms();
        $has_any_active = $state->hasAnyActiveParam();

        $default_open_params = ['kich-thuoc', 'be-mat', 'mau-sac'];
        $facet_items_by_key  = [];

        foreach ($defs as $param => $def) {
            if (in_array($param, $hidden_facets, true)) {
                continue;
            }

            $terms_map      = FacetRegistry::getTermsForFacet($param, $f_cfg);
            $locked_slugs   = $hidden_terms[$param] ?? [];
            $selected_slugs = $active_facets[$param] ?? [];
            $is_fixed_scale = in_array($param, self::FIXED_SCALE_PARAMS, true);
            $options        = [];

            foreach ($terms_map as $slug => $t_info) {
                if (in_array($slug, $locked_slugs, true)) {
                    continue;
                }

                $input_val  = ($param === 'showroom') ? '1' : $slug;
                $is_checked = in_array($input_val, $selected_slugs, true) || in_array($slug, $selected_slugs, true);
                $cur_count  = (int) ($counts[$param][$slug] ?? 0);
                $base_count = $base_counts !== null
                    ? (int) ($base_counts[$param][$slug] ?? 0)
                    : $cur_count;

                // Với các thuộc tính rộng (32 kích thước, 17 vân...), chỉ hiển thị các giá trị thực sự tồn tại trong Silo gốc
                // để giao diện gọn sạch, nhưng giữ cố định xuyên suốt khi lọc chéo (count -> 0 chuyển sang disabled, không giật layout F-9).
                if (!$is_fixed_scale && $base_counts !== null && $base_count <= 0 && !$is_checked) {
                    continue;
                }

                $is_disabled = ($cur_count <= 0 && !$is_checked);
                $href        = FacetRegistry::resolveOptionHref(
                    $param,
                    $input_val,
                    $state->baseTaxonomy(),
                    $state->baseSlug(),
                    $state->baseUrl(),
                    $has_any_active,
                    $cur_count,
                    $f_cfg
                );

                $options[] = [
                    'slug'        => $input_val,
                    'term_slug'   => $slug,
                    'label'       => $t_info['filter_label'] !== '' ? $t_info['filter_label'] : $t_info['name'],
                    'short_label' => $t_info['short_label'] !== '' ? $t_info['short_label'] : $t_info['name'],
                    'swatch_hex'  => $t_info['swatch_hex'],
                    'icon'        => $t_info['icon'],
                    'count'       => $cur_count,
                    'checked'     => $is_checked,
                    'disabled'    => $is_disabled,
                    'href'        => $href,
                ];
            }

            if ($options === [] && $param !== 'showroom') {
                continue;
            }

            $is_open = in_array($param, $default_open_params, true) || $selected_slugs !== [];

            $facet_items_by_key[$param] = [
                'param'          => $param,
                'code'           => $def['code'],
                'label'          => $def['label'],
                'group'          => $def['group'],
                'ui'             => $def['ui'],
                'is_open'        => $is_open,
                'selected_count' => count($selected_slugs),
                'options'        => $options,
            ];
        }

        $showroom_facet = $facet_items_by_key['showroom'] ?? null;
        $ui_groups      = [];
        $open_assigned  = 0;

        foreach (FacetRegistry::getUiGroups() as $g_cfg) {
            $sub_facets     = [];
            $selected_total = 0;

            foreach ($g_cfg['params'] as $p_key) {
                if (!isset($facet_items_by_key[$p_key])) {
                    continue;
                }
                $sub_facets[]    = $facet_items_by_key[$p_key];
                $selected_total += (int) $facet_items_by_key[$p_key]['selected_count'];
            }

            if ($sub_facets === []) {
                continue;
            }

            $should_open = ($open_assigned < 3) || ($selected_total > 0) || $g_cfg['default_open'];
            if ($should_open && $open_assigned < 3) {
                $open_assigned++;
            }

            $ui_groups[] = [
                'id'             => $g_cfg['id'],
                'title'          => $g_cfg['title'],
                'is_open'        => $should_open,
                'selected_count' => $selected_total,
                'facets'         => $sub_facets,
            ];
        }

        return [
            'facets'    => array_values($facet_items_by_key),
            'showroom'  => $showroom_facet,
            'ui_groups' => $ui_groups,
        ];
    }

    /**
     * Render HTML các thẻ sản phẩm hoặc khung Empty State khi 0 kết quả.
     *
     * @param array<int, \WC_Product> $products
     */
    public static function renderItemsHtml(
        array $products,
        int $start_index,
        UrlState $state,
        string $archive_name
    ): string {
        if ($products === []) {
            if ($start_index > 0) {
                return '';
            }
            return self::capturePart('template-parts/catalog/empty', [
                'state'        => $state,
                'archive_name' => $archive_name,
            ]);
        }

        ob_start();
        foreach ($products as $idx => $product) {
            if (!$product instanceof \WC_Product) {
                continue;
            }
            drv_ui('product-card', [
                'product'       => $product,
                'priority_load' => ($start_index === 0 && $idx < 4),
                'heading_tag'   => 'h2',
            ]);
        }
        return (string) ob_get_clean();
    }

    /**
     * Render phần thân bên trong `<form data-filter-form-el>` (dùng chung SSR & AJAX `facets_html`).
     *
     * @param array{facets: array<int, array<string, mixed>>, showroom: array<string, mixed>|null, ui_groups: array<int, array<string, mixed>>} $view_data
     */
    public static function renderFacetsHtml(UrlState $state, array $view_data, int $total_found): string {
        return self::capturePart('template-parts/catalog/filters-inner', [
            'state'       => $state,
            'showroom'    => $view_data['showroom'],
            'ui_groups'   => $view_data['ui_groups'],
            'total_found' => $total_found,
        ]);
    }

    /**
     * Render thanh chip đang lọc (`#drv-active-chips`).
     */
    public static function renderChipsHtml(UrlState $state, string $archive_name): string {
        return self::capturePart('template-parts/catalog/active-chips', [
            'state'        => $state,
            'archive_name' => $archive_name,
        ]);
    }

    /**
     * Trích xuất metadata rút gọn của từng sản phẩm khi client gửi `include_items_meta=1` (docs/15 §2.1).
     *
     * @param array<int, \WC_Product> $products
     * @return array<int, array<string, mixed>>
     */
    public static function buildItemsMeta(array $products): array {
        $items = [];
        foreach ($products as $product) {
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $card    = PriceState::toCardData($product);
            $items[] = [
                'id'          => $card['id'],
                'sku'         => $card['sku'],
                'title'       => $card['title'],
                'url'         => $card['url'],
                'price_state' => $card['price_state'],
                'price_m2'    => $card['price_m2'],
                'price_piece' => $card['price_piece'],
                'in_stock'    => $card['in_stock'],
            ];
        }
        return $items;
    }

    /**
     * Giải tên hiển thị của danh mục gốc hiện hành (`Gạch Phòng Khách`, `Dravit Signature`...).
     */
    public static function resolveArchiveName(UrlState $state): string {
        $tax  = $state->baseTaxonomy();
        $slug = $state->baseSlug();

        if (function_exists('get_term_by') && function_exists('taxonomy_exists') && taxonomy_exists($tax)) {
            $term = get_term_by('slug', $slug, $tax);
            if ($term instanceof \WP_Term && trim($term->name) !== '') {
                return trim($term->name);
            }
        }

        if ($tax === 'product_cat') {
            $collections = Config::get('collections');
            if (isset($collections[$slug]['name']) && is_string($collections[$slug]['name'])) {
                return $collections[$slug]['name'];
            }
        }

        $facets    = FacetRegistry::getFacets();
        $param_key = str_starts_with($tax, 'pa_') ? substr($tax, 3) : $tax;
        if (isset($facets[$param_key]['terms'][$slug]['name'])) {
            return $facets[$param_key]['terms'][$slug]['name'];
        }

        return 'Gạch Ốp Lát';
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function capturePart(string $slug, array $args): string {
        return Template::get_part_html($slug, $args);
    }
}
