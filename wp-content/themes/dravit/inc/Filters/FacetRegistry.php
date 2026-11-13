<?php
declare(strict_types=1);

namespace Dravit\Filters;

use Dravit\Core\Config;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Từ điển trung tâm cho 14 tiêu chí lọc (`FLT-01..14`), phân nhóm UI Sidebar/Drawer,
 * tự động ẩn chiều 1 trùng danh mục hiện hành (`F-14`) và kiểm soát thẻ HTML chống Spider-Trap (`M-13`).
 */
final class FacetRegistry {
    /**
     * Cấu hình thứ tự nhóm Accordion trên Sidebar / Mobile Sheet (docs/05 §2.2, 02-catalog-collection.html).
     * 3 nhóm đầu tiên (`default_open => true`) và mọi nhóm đang có giá trị chọn sẽ mở sẵn.
     *
     * @return array<int, array{id: string, title: string, params: array<int, string>, default_open: bool}>
     */
    public static function getUiGroups(): array {
        return [
            [
                'id'           => 'kich-thuoc',
                'title'        => 'Kích thước (cm)',
                'params'       => ['kich-thuoc'],
                'default_open' => true,
            ],
            [
                'id'           => 'be-mat',
                'title'        => 'Bề mặt gạch',
                'params'       => ['be-mat'],
                'default_open' => true,
            ],
            [
                'id'           => 'mau-sac',
                'title'        => 'Tông màu chủ đạo',
                'params'       => ['mau-sac'],
                'default_open' => true,
            ],
            [
                'id'           => 'van-chat-lieu',
                'title'        => 'Hệ vân & Chất liệu',
                'params'       => ['van-gach', 'chat-lieu'],
                'default_open' => false,
            ],
            [
                'id'           => 'gia',
                'title'        => 'Khoảng giá (đ/m²)',
                'params'       => ['gia'],
                'default_open' => false,
            ],
            [
                'id'           => 'cong-nang',
                'title'        => 'Công năng ốp lát',
                'params'       => ['cong-nang'],
                'default_open' => false,
            ],
            [
                'id'           => 'khong-gian',
                'title'        => 'Không gian ứng dụng',
                'params'       => ['khong-gian'],
                'default_open' => false,
            ],
            [
                'id'           => 'dcof',
                'title'        => 'Chỉ số chống trượt (R)',
                'params'       => ['dcof'],
                'default_open' => false,
            ],
            [
                'id'           => 'do-hut-nuoc',
                'title'        => 'Độ hút nước E (%)',
                'params'       => ['do-hut-nuoc'],
                'default_open' => false,
            ],
            [
                'id'           => 'do-day',
                'title'        => 'Độ dày & Chịu lực',
                'params'       => ['do-day'],
                'default_open' => false,
            ],
            [
                'id'           => 'bst-xuat-xu',
                'title'        => 'Bộ sưu tập & Xuất xứ',
                'params'       => ['bo-suu-tap', 'xuat-xu'],
                'default_open' => false,
            ],
            [
                'id'           => 'phong-cach',
                'title'        => 'Phong cách thiết kế',
                'params'       => ['phong-cach'],
                'default_open' => false,
            ],
            [
                'id'           => 'hinh-dang',
                'title'        => 'Hình dạng viên gạch',
                'params'       => ['hinh-dang'],
                'default_open' => false,
            ],
        ];
    }

    /**
     * Lấy toàn bộ bản đồ cấu hình các tham số lọc hợp lệ, sắp xếp theo `order` tăng dần.
     *
     * @param array<string, mixed>|null $facets_config
     * @return array<string, array{
     *     code: string,
     *     param: string,
     *     taxonomy: string,
     *     label: string,
     *     order: int,
     *     group: string,
     *     ui: string,
     *     public_archive: bool,
     *     archive_base: string,
     *     redirect_map: array<string, string>,
     *     static_map: array<string, string>,
     *     terms: array<string, array{name: string, filter_label: string, short_label: string, swatch_hex: string, icon: string}>
     * }>
     */
    public static function getFacets(?array $facets_config = null): array {
        $cfg = $facets_config ?? Config::get('facets');
        $out = [];

        $attrs = isset($cfg['attributes']) && is_array($cfg['attributes']) ? $cfg['attributes'] : [];
        foreach ($attrs as $key => $def) {
            if (!is_array($def)) {
                continue;
            }
            $param       = (string) ($def['param'] ?? $key);
            $static_map  = isset($def['static_map']) && is_array($def['static_map']) ? $def['static_map'] : [];
            $redir_map   = isset($def['redirect_map']) && is_array($def['redirect_map']) ? $def['redirect_map'] : $static_map;
            $out[$param] = [
                'code'           => (string) ($def['code'] ?? ''),
                'param'          => $param,
                'taxonomy'       => (string) ($def['taxonomy'] ?? ('pa_' . $key)),
                'label'          => (string) ($def['label'] ?? $param),
                'order'          => (int) ($def['order'] ?? 50),
                'group'          => (string) ($def['group'] ?? 'seo'),
                'ui'             => (string) ($def['ui'] ?? 'checkbox'),
                'public_archive' => !empty($def['public_archive']),
                'archive_base'   => (string) ($def['archive_base'] ?? $param),
                'redirect_map'   => self::stringifyMap($redir_map),
                'static_map'     => self::stringifyMap($static_map),
                'terms'          => self::normalizeTermsConfig(isset($def['terms']) && is_array($def['terms']) ? $def['terms'] : []),
            ];
        }

        $internal = isset($cfg['internal_taxonomies']) && is_array($cfg['internal_taxonomies']) ? $cfg['internal_taxonomies'] : [];
        foreach ($internal as $tax => $def) {
            if (!is_array($def)) {
                continue;
            }
            $param       = (string) ($def['param'] ?? $tax);
            $out[$param] = [
                'code'           => (string) ($def['code'] ?? ''),
                'param'          => $param,
                'taxonomy'       => (string) $tax,
                'label'          => (string) ($def['label'] ?? $param),
                'order'          => (int) ($def['order'] ?? 130),
                'group'          => (string) ($def['group'] ?? 'tech'),
                'ui'             => $param === 'showroom' ? 'toggle' : 'checkbox',
                'public_archive' => false,
                'archive_base'   => '',
                'redirect_map'   => [],
                'static_map'     => [],
                'terms'          => self::normalizeTermsConfig(isset($def['terms']) && is_array($def['terms']) ? $def['terms'] : []),
            ];
        }

        uasort($out, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $out;
    }

    /**
     * Lấy danh sách tùy chọn sắp xếp hợp lệ (`moi-ve` là mặc định, ẩn khỏi URL).
     *
     * @param array<string, mixed>|null $facets_config
     * @return array<string, string>
     */
    public static function getSortOptions(?array $facets_config = null): array {
        $cfg  = $facets_config ?? Config::get('facets');
        $raw  = isset($cfg['sort_options']) && is_array($cfg['sort_options']) ? $cfg['sort_options'] : [];
        $sort = [];
        foreach ($raw as $k => $label) {
            if (is_scalar($label)) {
                $sort[(string) $k] = (string) $label;
            }
        }
        if ($sort === []) {
            $sort = [
                'moi-ve'         => 'Đề xuất nổi bật',
                'showroom-truoc' => 'Ưu tiên mẫu tại Showroom',
                'kho-lon'        => 'Khổ gạch: Lớn → Nhỏ',
                'kho-nho'        => 'Khổ gạch: Nhỏ → Lớn',
                'gia-tang'       => 'Giá niêm yết: Thấp → Cao',
                'gia-giam'       => 'Giá niêm yết: Cao → Thấp',
            ];
        }
        return $sort;
    }

    /**
     * Lấy đầy đủ danh sách term của 1 facet (hợp nhất từ điển chuẩn `config/facets.php` và term mới thêm trong WP Admin).
     *
     * @param array<string, mixed>|null $facets_config
     * @return array<string, array{name: string, filter_label: string, short_label: string, swatch_hex: string, icon: string}>
     */
    public static function getTermsForFacet(string $param, ?array $facets_config = null): array {
        $facets = self::getFacets($facets_config);
        if (!isset($facets[$param])) {
            return [];
        }

        $def   = $facets[$param];
        $terms = $def['terms'];
        $tax   = $def['taxonomy'];

        if (function_exists('taxonomy_exists') && function_exists('get_terms') && taxonomy_exists($tax)) {
            $db_terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => false,
            ]);
            if (is_array($db_terms)) {
                foreach ($db_terms as $term) {
                    if (!$term instanceof \WP_Term || $term->slug === '') {
                        continue;
                    }
                    $slug = $term->slug;
                    $tid  = (int) $term->term_id;

                    $meta_filter = function_exists('get_term_meta') ? trim((string) get_term_meta($tid, 'drv_filter_label', true)) : '';
                    $meta_short  = function_exists('get_term_meta') ? trim((string) get_term_meta($tid, 'drv_short_label', true)) : '';
                    $meta_swatch = function_exists('get_term_meta') ? trim((string) get_term_meta($tid, 'drv_swatch_hex', true)) : '';
                    $meta_icon   = function_exists('get_term_meta') ? trim((string) get_term_meta($tid, 'drv_icon', true)) : '';

                    if (isset($terms[$slug])) {
                        if ($meta_filter !== '') {
                            $terms[$slug]['filter_label'] = $meta_filter;
                        }
                        if ($meta_short !== '') {
                            $terms[$slug]['short_label'] = $meta_short;
                        }
                        if ($meta_swatch !== '') {
                            $terms[$slug]['swatch_hex'] = $meta_swatch;
                        }
                        if ($meta_icon !== '') {
                            $terms[$slug]['icon'] = $meta_icon;
                        }
                    } else {
                        $terms[$slug] = [
                            'name'         => $term->name,
                            'filter_label' => $meta_filter !== '' ? $meta_filter : $term->name,
                            'short_label'  => $meta_short !== '' ? $meta_short : $term->name,
                            'swatch_hex'   => $meta_swatch,
                            'icon'         => $meta_icon,
                        ];
                    }
                }
            }
        }

        return $terms;
    }

    /**
     * Xác định các nhóm facet hoặc giá trị term phải tự động ẩn theo trang danh mục gốc hiện tại (`F-14`, docs/05 §2.2):
     * - Ẩn hoàn toàn nhóm `kich-thuoc` trên danh mục kích thước đơn (`/gach-60x60`, `/gach-80x80`, `/gach-30x60`, `/gach-60x120`);
     *   giữ nguyên trên `/gach-15x80-20x100` để lọc tiếp khổ thanh gỗ con.
     * - Ẩn giá trị đơn đang khóa chiều 1 (VD: ẩn `phong-khach` trong `khong-gian` khi đang ở `/gach-phong-khach`).
     * - Ẩn hoàn toàn nhóm `bo-suu-tap` khi đang ở `/bo-suu-tap/*` và ẩn `xuat-xu` khi đang ở `/xuat-xu/*`.
     *
     * @param array<string, mixed>|null $collections_config
     * @return array{hidden_facets: array<int, string>, hidden_terms: array<string, array<int, string>>}
     */
    public static function resolveHiddenContext(
        string $base_taxonomy,
        string $base_slug,
        ?array $collections_config = null
    ): array {
        $hidden_facets = [];
        $hidden_terms  = [];

        if ($base_taxonomy === 'pa_bo-suu-tap') {
            $hidden_facets[] = 'bo-suu-tap';
            return ['hidden_facets' => $hidden_facets, 'hidden_terms' => $hidden_terms];
        }

        if ($base_taxonomy === 'pa_xuat-xu') {
            $hidden_facets[] = 'xuat-xu';
            return ['hidden_facets' => $hidden_facets, 'hidden_terms' => $hidden_terms];
        }

        if ($base_taxonomy !== 'product_cat' || $base_slug === '' || $base_slug === 'gach') {
            return ['hidden_facets' => [], 'hidden_terms' => []];
        }

        $collections = $collections_config ?? Config::get('collections');
        $rule        = $collections[$base_slug]['rule'] ?? null;
        if (!is_array($rule) || !isset($rule['any']) || !is_array($rule['any']) || count($rule['any']) !== 1) {
            return ['hidden_facets' => [], 'hidden_terms' => []];
        }

        $cond = $rule['any'][0];
        if (!is_array($cond) || !isset($cond['attr'], $cond['in']) || !is_array($cond['in'])) {
            return ['hidden_facets' => [], 'hidden_terms' => []];
        }

        $attr  = (string) $cond['attr'];
        $param = str_starts_with($attr, 'pa_') ? substr($attr, 3) : $attr;
        $vals  = array_values(array_map('strval', $cond['in']));

        if (count($vals) === 1) {
            if ($param === 'kich-thuoc') {
                // Gạch chỉ có 1 khổ kích thước duy nhất -> ẩn toàn bộ nhóm Kích thước.
                $hidden_facets[] = 'kich-thuoc';
            }
            $hidden_terms[$param] = [$vals[0]];
        }

        return [
            'hidden_facets' => $hidden_facets,
            'hidden_terms'  => $hidden_terms,
        ];
    }

    /**
     * Giải đường dẫn tĩnh khi người dùng đang đứng ở `/gach` và chỉ chọn duy nhất 1 giá trị của 1 facet (`R-3`).
     *
     * @param array<string, mixed>|null $facets_config
     */
    public static function resolveSingleStaticPath(
        string $param,
        string $value,
        ?array $facets_config = null
    ): ?string {
        if ($param === 'bo-suu-tap') {
            return '/bo-suu-tap/' . $value;
        }
        if ($param === 'xuat-xu') {
            return '/xuat-xu/' . $value;
        }

        $facets = self::getFacets($facets_config);
        if (!isset($facets[$param])) {
            return null;
        }

        if (isset($facets[$param]['redirect_map'][$value])) {
            return '/' . ltrim($facets[$param]['redirect_map'][$value], '/');
        }

        return null;
    }

    /**
     * Giải thuộc tính `href` cho từng mục lọc theo chuẩn chống Spider-Trap (`M-13`, docs/05 §6.3, docs/15 §2.1):
     * - Trang đã có bất kỳ tham số lọc/sort nào (`$has_any_active === true`): trả `null` (render `<input>`/`<button>` thuần).
     * - Nhóm `tech` (`FLT-10..14`): KHÔNG BAO GIỜ có `<a href>` (trả `null`).
     * - Mục `count === 0`: không gắn link (trả `null`).
     * - Trang tĩnh 0 tham số + nhóm `seo`: nếu ở `/gach` và giá trị khớp trang tĩnh riêng -> trỏ thẳng URL tĩnh sạch;
     *   ngược lại trỏ `{base_url}?{param}={slug}` (tối đa 1 chiều lọc duy nhất).
     *
     * @param array<string, mixed>|null $facets_config
     */
    public static function resolveOptionHref(
        string $param,
        string $term_slug,
        string $base_taxonomy,
        string $base_slug,
        string $base_url,
        bool $has_any_active,
        int $count,
        ?array $facets_config = null
    ): ?string {
        if ($has_any_active || $count <= 0) {
            return null;
        }

        $facets = self::getFacets($facets_config);
        if (!isset($facets[$param]) || $facets[$param]['group'] !== 'seo') {
            return null;
        }

        if ($base_taxonomy === 'product_cat' && $base_slug === 'gach') {
            $static_path = self::resolveSingleStaticPath($param, $term_slug, $facets_config);
            if ($static_path !== null) {
                return self::buildSiteUrl($static_path, $base_url);
            }
        }

        return UrlNormalizer::untrailingslashUrl($base_url) . '?' . rawurlencode($param) . '=' . rawurlencode($term_slug);
    }

    /**
     * @param array<string, mixed> $raw_map
     * @return array<string, string>
     */
    private static function stringifyMap(array $raw_map): array {
        $out = [];
        foreach ($raw_map as $k => $v) {
            if (is_scalar($v)) {
                $out[(string) $k] = (string) $v;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $raw_terms
     * @return array<string, array{name: string, filter_label: string, short_label: string, swatch_hex: string, icon: string}>
     */
    private static function normalizeTermsConfig(array $raw_terms): array {
        $normalized = [];
        foreach ($raw_terms as $slug => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name                      = (string) ($item['name'] ?? $slug);
            $normalized[(string) $slug] = [
                'name'         => $name,
                'filter_label' => (string) ($item['filter_label'] ?? $name),
                'short_label'  => (string) ($item['short_label'] ?? $name),
                'swatch_hex'   => (string) ($item['swatch_hex'] ?? ''),
                'icon'         => (string) ($item['icon'] ?? ''),
            ];
        }
        return $normalized;
    }

    private static function buildSiteUrl(string $path, string $reference_base_url): string {
        $parts = function_exists('wp_parse_url') ? wp_parse_url($reference_base_url) : parse_url($reference_base_url);
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
            return $origin . '/' . ltrim(rtrim($path, '/'), '/');
        }

        if (function_exists('home_url')) {
            return UrlNormalizer::untrailingslashUrl(home_url($path));
        }

        return 'https://gachdravit.vn/' . ltrim(rtrim($path, '/'), '/');
    }
}

