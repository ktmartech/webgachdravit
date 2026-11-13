<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Xây dựng và đồng bộ chỉ mục tìm kiếm không dấu `_drv_search_index` trên từng sản phẩm
 * (docs/14 §2.3, docs/19 §3.4, docs/21 Step 3.3).
 */
final class SearchIndex {
    public const META_KEY = '_drv_search_index';

    /** @var array<int, string> */
    private const INDEXED_TAXONOMIES = [
        'product_cat',
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
    ];

    /**
     * Hàm thuần ghép và chuẩn hóa chuỗi chỉ mục tìm kiếm từ dữ liệu sản phẩm.
     *
     * @param array{
     *   sku?: string,
     *   name?: string,
     *   series?: string,
     *   shelf?: string,
     *   w_mm?: int,
     *   h_mm?: int,
     *   terms?: array<int, string>,
     *   extra?: string
     * } $data
     */
    public static function buildIndexString(array $data): string {
        $chunks = [];

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku !== '') {
            $sku_lower = strtolower($sku);
            $sku_compact = (string) preg_replace('/[^a-z0-9]/', '', $sku_lower);
            $sku_digits  = (string) preg_replace('/^[a-z]+-?/', '', $sku_lower);
            $chunks[]    = $sku_lower;
            $chunks[]    = $sku_compact;
            if ($sku_digits !== '') {
                $chunks[] = $sku_digits;
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $chunks[] = SearchNormalizer::normalize($name);
        }

        $series = trim((string) ($data['series'] ?? ''));
        if ($series !== '') {
            $chunks[] = SearchNormalizer::normalize($series);
        }

        $shelf = trim((string) ($data['shelf'] ?? ''));
        if ($shelf !== '') {
            $chunks[] = 'ke ' . SearchNormalizer::normalize($shelf);
            $chunks[] = (string) preg_replace('/[^a-z0-9]/', '', strtolower($shelf));
        }

        $w_mm = max(0, (int) ($data['w_mm'] ?? 0));
        $h_mm = max(0, (int) ($data['h_mm'] ?? 0));
        if ($w_mm > 0 && $h_mm > 0) {
            $min_mm    = min($w_mm, $h_mm);
            $max_mm    = max($w_mm, $h_mm);
            $cm_slug   = AttributeDeriver::deriveSizeSlug($w_mm, $h_mm);
            $chunks[]  = $cm_slug;
            $chunks[]  = $min_mm . 'x' . $max_mm;
            $chunks[]  = $max_mm . 'x' . $min_mm;
            if (str_contains($cm_slug, '-')) {
                $chunks[] = str_replace('-', '.', $cm_slug);
            }
        }

        $terms = isset($data['terms']) && is_array($data['terms']) ? $data['terms'] : [];
        foreach ($terms as $t) {
            $norm_t = SearchNormalizer::normalize((string) $t);
            if ($norm_t !== '') {
                $chunks[] = $norm_t;
            }
        }

        $extra = trim((string) ($data['extra'] ?? ''));
        if ($extra !== '') {
            $chunks[] = SearchNormalizer::normalize($extra);
        }

        $tokens = [];
        foreach ($chunks as $chunk) {
            foreach (explode(' ', trim($chunk)) as $word) {
                if ($word !== '') {
                    $tokens[$word] = true;
                }
            }
        }

        return implode(' ', array_keys($tokens));
    }

    /**
     * Trích xuất toàn bộ trường văn bản & taxonomy của `\WC_Product` để tạo chuỗi chỉ mục.
     */
    public static function buildForProduct(\WC_Product $product): string {
        $product_id = $product->get_id();
        $term_texts = [];

        if ($product_id > 0 && function_exists('wp_get_object_terms') && function_exists('taxonomy_exists')) {
            foreach (self::INDEXED_TAXONOMIES as $tax) {
                if (!taxonomy_exists($tax)) {
                    continue;
                }
                $terms = wp_get_object_terms($product_id, $tax);
                if (!is_array($terms)) {
                    continue;
                }
                foreach ($terms as $term) {
                    if ($term instanceof \WP_Term) {
                        $term_texts[] = $term->name;
                        $term_texts[] = str_replace('-', ' ', $term->slug);
                    }
                }
            }
        }

        return self::buildIndexString([
            'sku'    => (string) $product->get_sku(),
            'name'   => $product->get_name(),
            'series' => (string) $product->get_meta('drv_series', true),
            'shelf'  => (string) $product->get_meta('drv_showroom_shelf', true),
            'w_mm'   => (int) $product->get_meta('drv_piece_w_mm', true),
            'h_mm'   => (int) $product->get_meta('drv_piece_h_mm', true),
            'terms'  => $term_texts,
            'extra'  => wp_strip_all_tags($product->get_short_description()),
        ]);
    }

    /**
     * Tính và lưu `_drv_search_index` xuống post meta nếu có thay đổi.
     */
    public static function syncProduct(\WC_Product $product): string {
        $product_id = $product->get_id();
        $index_str  = self::buildForProduct($product);

        if ($product_id > 0 && function_exists('get_post_meta') && function_exists('update_post_meta')) {
            $existing = (string) get_post_meta($product_id, self::META_KEY, true);
            if ($existing !== $index_str) {
                update_post_meta($product_id, self::META_KEY, $index_str);
            }
        }

        return $index_str;
    }
}

