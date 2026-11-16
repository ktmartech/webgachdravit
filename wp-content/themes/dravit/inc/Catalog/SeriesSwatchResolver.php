<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Bộ giải Biến thể cùng dòng (`drv_series`) sang danh sách thẻ `<a href>` SSR chuẩn `D-26` (Test P-3, STRIP-13):
 * - Hỗ trợ tối đa 3 trục: `color` (Màu sắc / tông vân), `size` (Kích thước), `surface` (Bề mặt & chống trượt).
 * - Chỉ hiển thị trục khi có `>= 2` giá trị khác nhau trong cùng dòng `drv_series`.
 * - Khi đổi 1 trục, tự động ưu tiên chọn SKU anh em giữ nguyên các trục còn lại gần nhất với SKU đang xem.
 * - Tuyệt đối không ghép mã SKU giả hay đổi giá phía client.
 */
final class SeriesSwatchResolver {
    /**
     * Giải danh sách trục swatch SSR cho sản phẩm hiện tại từ CSDL WordPress.
     *
     * @return array<int, array{
     *     axis: 'color'|'size'|'surface',
     *     group_label: string,
     *     current_value_label: string,
     *     items: array<int, array{label: string, url: string, active: bool, hex: string, thumb_url: string}>
     * }>
     */
    public static function resolveForProduct(\WC_Product $product): array {
        $series = sanitize_title(trim((string) $product->get_meta('drv_series', true)));
        if ($series === '') {
            return [];
        }

        if (!function_exists('get_posts') || !function_exists('wc_get_product')) {
            return [];
        }

        $post_ids = get_posts([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => 60,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
            'meta_query'             => [
                [
                    'key'   => 'drv_series',
                    'value' => $series,
                ],
            ],
            'orderby'                => [
                'menu_order' => 'ASC',
                'ID'         => 'ASC',
            ],
        ]);

        if (!is_array($post_ids) || count($post_ids) < 2) {
            return [];
        }

        $facets_cfg   = Config::get('facets');
        $color_swatches = $facets_cfg['attributes']['mau-sac']['terms'] ?? [];

        $records = [];
        foreach ($post_ids as $raw_id) {
            $pid = (int) $raw_id;
            $sib = ($pid === $product->get_id()) ? $product : wc_get_product($pid);
            if (!$sib instanceof \WC_Product) {
                continue;
            }
            if ($sib->get_catalog_visibility() === 'hidden') {
                continue;
            }

            $records[] = self::extractSiblingRecord($sib, is_array($color_swatches) ? $color_swatches : []);
        }

        return self::buildAxesFromRecords($product->get_id(), $records);
    }

    /**
     * Hàm thuần (Pure Function) xây dựng tối đa 3 trục swatch từ danh sách bản ghi SKU anh em.
     *
     * @param array<int, array{
     *     id: int,
     *     url: string,
     *     color_key: string,
     *     color_label: string,
     *     color_hex: string,
     *     thumb_url: string,
     *     size_key: string,
     *     size_label: string,
     *     size_area: float,
     *     surface_key: string,
     *     surface_label: string
     * }> $records
     * @return array<int, array{
     *     axis: 'color'|'size'|'surface',
     *     group_label: string,
     *     current_value_label: string,
     *     items: array<int, array{label: string, url: string, active: bool, hex: string, thumb_url: string}>
     * }>
     */
    public static function buildAxesFromRecords(int $current_id, array $records): array {
        if (count($records) < 2) {
            return [];
        }

        $current = null;
        foreach ($records as $rec) {
            if ((int) ($rec['id'] ?? 0) === $current_id) {
                $current = $rec;
                break;
            }
        }
        if ($current === null) {
            return [];
        }

        $axes = [];

        // 1. Trục Màu sắc / Tông vân (`color`)
        $color_groups = [];
        foreach ($records as $rec) {
            $key = trim((string) ($rec['color_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $color_groups[$key][] = $rec;
        }

        if (count($color_groups) >= 2 && $current['color_key'] !== '') {
            $items = [];
            foreach ($color_groups as $c_key => $candidates) {
                $picked = self::pickClosestSibling($candidates, $current, ['size_key', 'surface_key']);
                $items[] = [
                    'label'     => $picked['color_label'],
                    'url'       => UrlNormalizer::untrailingslashUrl($picked['url']),
                    'active'    => ($c_key === $current['color_key']),
                    'hex'       => $picked['color_hex'],
                    'thumb_url' => $picked['thumb_url'],
                ];
            }
            $axes[] = [
                'axis'                => 'color',
                'group_label'         => 'Màu sắc',
                'current_value_label' => $current['color_label'],
                'items'               => $items,
            ];
        }

        // 2. Trục Kích thước (`size`) — sắp theo diện tích tăng dần
        $size_groups = [];
        foreach ($records as $rec) {
            $key = trim((string) ($rec['size_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $size_groups[$key][] = $rec;
        }

        if (count($size_groups) >= 2 && $current['size_key'] !== '') {
            uasort(
                $size_groups,
                static fn (array $a, array $b): int => (($a[0]['size_area'] ?? 0.0) <=> ($b[0]['size_area'] ?? 0.0))
            );

            $items = [];
            foreach ($size_groups as $s_key => $candidates) {
                $picked = self::pickClosestSibling($candidates, $current, ['color_key', 'surface_key']);
                $items[] = [
                    'label'     => $picked['size_label'],
                    'url'       => UrlNormalizer::untrailingslashUrl($picked['url']),
                    'active'    => ($s_key === $current['size_key']),
                    'hex'       => '',
                    'thumb_url' => '',
                ];
            }
            $axes[] = [
                'axis'                => 'size',
                'group_label'         => 'Kích thước',
                'current_value_label' => $current['size_label'],
                'items'               => $items,
            ];
        }

        // 3. Trục Bề mặt (`surface`)
        $surface_groups = [];
        foreach ($records as $rec) {
            $key = trim((string) ($rec['surface_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $surface_groups[$key][] = $rec;
        }

        if (count($surface_groups) >= 2 && $current['surface_key'] !== '') {
            $items = [];
            foreach ($surface_groups as $sf_key => $candidates) {
                $picked = self::pickClosestSibling($candidates, $current, ['color_key', 'size_key']);
                $items[] = [
                    'label'     => $picked['surface_label'],
                    'url'       => UrlNormalizer::untrailingslashUrl($picked['url']),
                    'active'    => ($sf_key === $current['surface_key']),
                    'hex'       => '',
                    'thumb_url' => '',
                ];
            }
            $axes[] = [
                'axis'                => 'surface',
                'group_label'         => 'Bề mặt',
                'current_value_label' => $current['surface_label'],
                'items'               => $items,
            ];
        }

        return $axes;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed>             $current
     * @param array<int, string>               $match_keys
     * @return array<string, mixed>
     */
    private static function pickClosestSibling(array $candidates, array $current, array $match_keys): array {
        foreach ($candidates as $cand) {
            if ((int) ($cand['id'] ?? 0) === (int) ($current['id'] ?? -1)) {
                return $cand;
            }
        }

        $best       = $candidates[0];
        $best_score = -1;

        foreach ($candidates as $cand) {
            $score = 0;
            foreach ($match_keys as $idx => $mk) {
                $weight = ($idx === 0) ? 2 : 1;
                if (($cand[$mk] ?? '') !== '' && ($cand[$mk] ?? '') === ($current[$mk] ?? '')) {
                    $score += $weight;
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best       = $cand;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $color_swatches
     * @return array{
     *     id: int,
     *     url: string,
     *     color_key: string,
     *     color_label: string,
     *     color_hex: string,
     *     thumb_url: string,
     *     size_key: string,
     *     size_label: string,
     *     size_area: float,
     *     surface_key: string,
     *     surface_label: string
     * }
     */
    private static function extractSiblingRecord(\WC_Product $product, array $color_swatches): array {
        $id  = $product->get_id();
        $url = function_exists('get_permalink') && $id > 0
            ? (string) get_permalink($id)
            : '/san-pham/' . $product->get_slug();

        // Màu sắc: ưu tiên `drv_color_name`, dự phòng `pa_mau-sac`.
        $color_name = trim((string) $product->get_meta('drv_color_name', true));
        $color_term = self::firstTerm($id, 'pa_mau-sac');
        $color_slug = $color_term['slug'];
        $color_key  = $color_name !== '' ? sanitize_title($color_name) : $color_slug;
        $color_lbl  = $color_name !== '' ? $color_name : $color_term['name'];
        $color_hex  = '';

        if ($color_term['term_id'] > 0 && function_exists('get_term_meta')) {
            $color_hex = trim((string) get_term_meta($color_term['term_id'], 'drv_swatch_hex', true));
        }
        if ($color_hex === '' && $color_slug !== '' && isset($color_swatches[$color_slug]['swatch_hex'])) {
            $raw_hex   = (string) $color_swatches[$color_slug]['swatch_hex'];
            $color_hex = ($raw_hex !== 'multi') ? $raw_hex : '';
        }

        $thumb_url = '';
        $img_id    = (int) $product->get_image_id();
        if ($img_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $u         = wp_get_attachment_image_url($img_id, 'thumbnail');
            $thumb_url = is_string($u) ? $u : '';
        }

        // Kích thước: đọc từ `pa_kich-thuoc` hoặc `drv_piece_w_mm` × `drv_piece_h_mm`.
        $w_mm      = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm      = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $size_term = self::firstTerm($id, 'pa_kich-thuoc');
        $size_key  = $size_term['slug'];
        $size_lbl  = $size_term['name'];

        if ($size_key === '' && $w_mm > 0 && $h_mm > 0) {
            $w_cm     = rtrim(rtrim(number_format($w_mm / 10, 1, '.', ''), '0'), '.');
            $h_cm     = rtrim(rtrim(number_format($h_mm / 10, 1, '.', ''), '0'), '.');
            $size_key = str_replace('.', '-', $w_cm) . 'x' . str_replace('.', '-', $h_cm);
            $size_lbl = str_replace('.', ',', $w_cm) . '×' . str_replace('.', ',', $h_cm) . ' cm';
        } elseif ($size_lbl !== '' && !str_contains(mb_strtolower($size_lbl), 'cm')) {
            $size_lbl .= ' cm';
        }

        // Bề mặt + Chống trượt.
        $surf_term = self::firstTerm($id, 'pa_be-mat');
        $dcof_term = self::firstTerm($id, 'pa_dcof');
        $surf_key  = $surf_term['slug'];
        $surf_lbl  = $surf_term['name'];
        if ($surf_lbl !== '' && $dcof_term['slug'] !== '') {
            $r_code   = strtoupper($dcof_term['slug']);
            $surf_key .= '-' . $dcof_term['slug'];
            if (!str_contains(strtoupper($surf_lbl), $r_code)) {
                $surf_lbl .= ' (' . $r_code . ')';
            }
        }

        return [
            'id'            => $id,
            'url'           => $url,
            'color_key'     => $color_key,
            'color_label'   => $color_lbl,
            'color_hex'     => $color_hex,
            'thumb_url'     => $thumb_url,
            'size_key'      => $size_key,
            'size_label'    => $size_lbl,
            'size_area'     => ($w_mm > 0 && $h_mm > 0) ? (($w_mm * $h_mm) / 1_000_000.0) : 0.0,
            'surface_key'   => $surf_key,
            'surface_label' => $surf_lbl,
        ];
    }

    /**
     * @return array{term_id: int, slug: string, name: string}
     */
    private static function firstTerm(int $product_id, string $taxonomy): array {
        if ($product_id <= 0 || !function_exists('get_the_terms') || !function_exists('taxonomy_exists') || !taxonomy_exists($taxonomy)) {
            return ['term_id' => 0, 'slug' => '', 'name' => ''];
        }
        $terms = get_the_terms($product_id, $taxonomy);
        if (!is_array($terms) || $terms === [] || !$terms[0] instanceof \WP_Term) {
            return ['term_id' => 0, 'slug' => '', 'name' => ''];
        }

        return [
            'term_id' => (int) $terms[0]->term_id,
            'slug'    => $terms[0]->slug,
            'name'    => $terms[0]->name,
        ];
    }
}
