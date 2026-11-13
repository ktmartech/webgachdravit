<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Units\UnitRegistry;

defined('ABSPATH') || exit;

/**
 * Tự động đồng bộ Giá/Đơn vị (`AreaPack`), Thuộc tính suy dẫn (`pa_kich-thuoc`, `pa_do-day`, `pa_do-hut-nuoc`, `drv_price_band`, `drv_flag`),
 * 28 Smart Collections (`product_cat`), `PrimaryCategory` và Cache Invalidation khi lưu sản phẩm (docs/03 §3.3, docs/21 Step 2.2).
 */
final class CollectionSync implements ServiceProviderInterface {
    /** @var array<int, bool> Chống đệ quy theo ID sản phẩm */
    private static array $syncing = [];

    public function register(): void {
        add_action('woocommerce_before_product_object_save', [$this, 'before_product_save'], 20, 1);
        add_action('woocommerce_after_product_object_save', [$this, 'after_product_save'], 20, 1);
    }

    /**
     * Chạy TRƯỚC khi WC ghi xuống DB -> tính giá `_regular_price`, `_sale_price`, `_price`, `drv_m2_per_box`,
     * chuẩn hóa đơn vị mua `D-24` và gắn `\WC_Product_Attribute` suy dẫn trong đúng 1 chu kỳ save duy nhất.
     */
    public function before_product_save(\WC_Product $product): void {
        if ($product->is_type('variation')) {
            return;
        }

        $line = (string) $product->get_meta('drv_line', true);
        if (!in_array($line, ['gach', 'phu_kien'], true)) {
            $line = 'gach';
            $product->update_meta_data('drv_line', 'gach');
        }

        $pricing_model = (string) $product->get_meta('drv_pricing_model', true);
        if ($pricing_model === '') {
            $pricing_model = ($line === 'gach') ? 'area_pack' : 'piece';
            $product->update_meta_data('drv_pricing_model', $pricing_model);
        }

        UnitRegistry::modelForProduct($product)->sync_wc_prices($product);

        if ($line === 'gach') {
            $this->attach_derived_wc_attributes($product);
        }
    }

    /**
     * Chạy SAU khi WC đã có `$product->get_id()` hợp lệ -> gán term suy dẫn, tính 28 `product_cat`,
     * đồng bộ `PrimaryCategory` và xóa cache liên quan.
     */
    public function after_product_save(\WC_Product $product): void {
        $product_id = $product->get_id();
        if ($product_id <= 0 || $product->is_type('variation') || !empty(self::$syncing[$product_id])) {
            return;
        }

        self::$syncing[$product_id] = true;
        try {
            $this->syncProduct($product);
        } finally {
            unset(self::$syncing[$product_id]);
        }
    }

    /**
     * Đồng bộ toàn phần 1 đối tượng `WC_Product` (dùng cho hook lưu SP, `SaleSync`, `ToolsResync` & WP-CLI).
     *
     * @return array<int, string> Danh sách slug Smart Collections được gán
     */
    public function syncProduct(\WC_Product $product): array {
        $product_id = $product->get_id();
        if ($product_id <= 0) {
            return [];
        }

        $line = (string) ($product->get_meta('drv_line', true) ?: 'gach');

        // 1. Suy dẫn và gán term cho các taxonomy kỹ thuật & nội bộ.
        $w_mm          = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm          = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $thickness_mm  = (float) $product->get_meta('drv_thickness_mm', true);
        $water_abs     = $product->get_meta('drv_water_absorption', true);
        $shelf         = trim((string) $product->get_meta('drv_showroom_shelf', true));

        $reg_m2        = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2       = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $is_on_sale    = $product->is_on_sale() && $reg_m2 > 0;
        $eff_m2        = ($is_on_sale && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2;

        if ($line === 'gach') {
            $size_slug = AttributeDeriver::deriveSizeSlug($w_mm, $h_mm);
            if ($size_slug !== '' && taxonomy_exists('pa_kich-thuoc')) {
                $size_tid = $this->ensure_term(
                    'pa_kich-thuoc',
                    $size_slug,
                    AttributeDeriver::deriveSizeLabel($w_mm, $h_mm),
                    ['drv_short_label' => AttributeDeriver::deriveSizeShortLabel($w_mm, $h_mm)]
                );
                $this->set_terms_if_changed($product_id, $size_tid > 0 ? [$size_tid] : [], 'pa_kich-thuoc');
            }

            $thick_slug = AttributeDeriver::deriveThicknessSlug($thickness_mm);
            if ($thick_slug !== '' && taxonomy_exists('pa_do-day')) {
                $thick_tid = $this->find_term_id('pa_do-day', $thick_slug);
                if ($thick_tid > 0) {
                    $this->set_terms_if_changed($product_id, [$thick_tid], 'pa_do-day');
                }
            }

            $water_slug = AttributeDeriver::deriveWaterAbsorptionSlug(is_scalar($water_abs) ? (string) $water_abs : null);
            if ($water_slug !== '' && taxonomy_exists('pa_do-hut-nuoc')) {
                $water_tid = $this->find_term_id('pa_do-hut-nuoc', $water_slug);
                if ($water_tid > 0) {
                    $this->set_terms_if_changed($product_id, [$water_tid], 'pa_do-hut-nuoc');
                }
            }
        }

        if (taxonomy_exists('drv_price_band')) {
            $band_slug = AttributeDeriver::derivePriceBandSlug($eff_m2);
            $band_tid  = $this->find_term_id('drv_price_band', $band_slug);
            if ($band_tid > 0) {
                $this->set_terms_if_changed($product_id, [$band_tid], 'drv_price_band');
            }
        }

        if (taxonomy_exists('drv_flag')) {
            $flag_slugs = AttributeDeriver::deriveFlagSlugs($shelf);
            $flag_tids  = [];
            foreach ($flag_slugs as $f_slug) {
                $tid = $this->find_term_id('drv_flag', $f_slug);
                if ($tid > 0) {
                    $flag_tids[] = $tid;
                }
            }
            $this->set_terms_if_changed($product_id, $flag_tids, 'drv_flag');
        }

        // 2. Xây ngữ cảnh thuộc tính để chạy `RuleMatcher` cho 28 Smart Collections.
        $collections_cfg = Config::get('collections');
        $context         = $this->build_rule_context($product_id, $line, $w_mm, $h_mm, $is_on_sale);
        $matched_slugs   = RuleMatcher::matchCollections($collections_cfg, $context);

        // 3. Bảo toàn mọi `product_cat` thủ công nằm ngoài 28 Smart Collections & loại `uncategorized`/`chua-phan-loai` khi đã có danh mục.
        $managed_slugs  = array_keys($collections_cfg);
        $current_terms  = wp_get_object_terms($product_id, 'product_cat');
        $manual_tids    = [];
        $manual_slugs   = [];
        $default_cat_id = (int) get_option('default_product_cat', 0);

        if (is_array($current_terms)) {
            foreach ($current_terms as $term) {
                if (!$term instanceof \WP_Term) {
                    continue;
                }
                if (in_array($term->slug, $managed_slugs, true)) {
                    continue;
                }
                if ($term->term_id === $default_cat_id || in_array($term->slug, ['uncategorized', 'chua-phan-loai'], true)) {
                    continue;
                }
                $manual_tids[]  = $term->term_id;
                $manual_slugs[] = $term->slug;
            }
        }

        $slug_to_tid  = $this->map_collection_term_ids($managed_slugs);
        $matched_tids = [];
        foreach ($matched_slugs as $m_slug) {
            if (isset($slug_to_tid[$m_slug])) {
                $matched_tids[] = $slug_to_tid[$m_slug];
            }
        }

        $final_tids = array_values(array_unique(array_merge($matched_tids, $manual_tids)));
        if ($final_tids === [] && $default_cat_id > 0) {
            $final_tids = [$default_cat_id];
        }

        $cat_changed = $this->set_terms_if_changed($product_id, $final_tids, 'product_cat');

        // 4. Đồng bộ Danh mục chính (`PrimaryCategory`, ADR D-27) & chỉ mục tìm kiếm không dấu `_drv_search_index`.
        $all_assigned_slugs = array_values(array_unique(array_merge($matched_slugs, $manual_slugs)));
        PrimaryCategory::syncForProduct($product_id, $all_assigned_slugs, $slug_to_tid, $collections_cfg);
        SearchIndex::syncProduct($product);

        // 5. Invalidate cache & bắn action mở rộng.
        $this->invalidate_caches($product_id, $cat_changed);
        do_action('drv/catalog/synced', $product_id, $matched_slugs);

        return $matched_slugs;
    }

    private function attach_derived_wc_attributes(\WC_Product $product): void {
        $w_mm         = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm         = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $thickness_mm = (float) $product->get_meta('drv_thickness_mm', true);
        $water_abs    = $product->get_meta('drv_water_absorption', true);

        $derived_map = [];

        $size_slug = AttributeDeriver::deriveSizeSlug($w_mm, $h_mm);
        if ($size_slug !== '' && taxonomy_exists('pa_kich-thuoc')) {
            $tid = $this->ensure_term(
                'pa_kich-thuoc',
                $size_slug,
                AttributeDeriver::deriveSizeLabel($w_mm, $h_mm),
                ['drv_short_label' => AttributeDeriver::deriveSizeShortLabel($w_mm, $h_mm)]
            );
            if ($tid > 0) {
                $derived_map['pa_kich-thuoc'] = [$tid];
            }
        }

        $thick_slug = AttributeDeriver::deriveThicknessSlug($thickness_mm);
        if ($thick_slug !== '' && taxonomy_exists('pa_do-day')) {
            $tid = $this->find_term_id('pa_do-day', $thick_slug);
            if ($tid > 0) {
                $derived_map['pa_do-day'] = [$tid];
            }
        }

        $water_slug = AttributeDeriver::deriveWaterAbsorptionSlug(is_scalar($water_abs) ? (string) $water_abs : null);
        if ($water_slug !== '' && taxonomy_exists('pa_do-hut-nuoc')) {
            $tid = $this->find_term_id('pa_do-hut-nuoc', $water_slug);
            if ($tid > 0) {
                $derived_map['pa_do-hut-nuoc'] = [$tid];
            }
        }

        if ($derived_map === [] || !function_exists('wc_attribute_taxonomy_id_by_name')) {
            return;
        }

        $attributes = $product->get_attributes();
        if (!is_array($attributes)) {
            $attributes = [];
        }

        foreach ($derived_map as $tax => $tids) {
            $attr_id = wc_attribute_taxonomy_id_by_name($tax);
            if ($attr_id <= 0) {
                continue;
            }
            $wc_attr = new \WC_Product_Attribute();
            $wc_attr->set_id($attr_id);
            $wc_attr->set_name($tax);
            $wc_attr->set_options($tids);
            $wc_attr->set_position(count($attributes));
            $wc_attr->set_visible(true);
            $wc_attr->set_variation(false);
            $attributes[$tax] = $wc_attr;
        }

        $product->set_attributes($attributes);
    }

    /**
     * @return array{line: string, attrs: array<string, array<int, string>>, flags: array<string, bool>}
     */
    private function build_rule_context(int $product_id, string $line, int $w_mm, int $h_mm, bool $is_on_sale): array {
        $rule_taxonomies = [
            'pa_cong-nang',
            'pa_khong-gian',
            'pa_van-gach',
            'pa_chat-lieu',
            'pa_kich-thuoc',
        ];

        $attrs = [];
        foreach ($rule_taxonomies as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $slugs = wp_get_object_terms($product_id, $tax, ['fields' => 'slugs']);
            if (is_array($slugs) && $slugs !== []) {
                $attrs[$tax] = array_values(array_map('strval', $slugs));
            }
        }

        return [
            'line'  => $line,
            'attrs' => $attrs,
            'flags' => [
                'on_sale'      => $is_on_sale,
                'large_format' => AttributeDeriver::isLargeFormat($w_mm, $h_mm),
            ],
        ];
    }

    /**
     * Chỉ gọi `wp_set_object_terms()` khi tập ID thực sự thay đổi -> tránh query thừa và vòng lặp hook.
     *
     * @param array<int, int> $new_term_ids
     */
    private function set_terms_if_changed(int $product_id, array $new_term_ids, string $taxonomy): bool {
        $existing = wp_get_object_terms($product_id, $taxonomy, ['fields' => 'ids']);
        $old_ids  = is_array($existing) ? array_map('intval', $existing) : [];
        $new_ids  = array_values(array_unique(array_map('intval', $new_term_ids)));

        sort($old_ids);
        sort($new_ids);

        if ($old_ids === $new_ids) {
            return false;
        }

        wp_set_object_terms($product_id, $new_ids, $taxonomy, false);
        return true;
    }

    /**
     * @param array<int, string> $slugs
     * @return array<string, int>
     */
    private function map_collection_term_ids(array $slugs): array {
        if ($slugs === [] || !taxonomy_exists('product_cat')) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'slug'       => $slugs,
            'hide_empty' => false,
        ]);

        if (!is_array($terms)) {
            return [];
        }

        $map = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $map[$term->slug] = (int) $term->term_id;
            }
        }
        return $map;
    }

    private function find_term_id(string $taxonomy, string $slug): int {
        $term = get_term_by('slug', $slug, $taxonomy);
        return ($term instanceof \WP_Term) ? (int) $term->term_id : 0;
    }

    /**
     * @param array<string, string> $meta
     */
    private function ensure_term(string $taxonomy, string $slug, string $label, array $meta = []): int {
        $existing_id = $this->find_term_id($taxonomy, $slug);
        if ($existing_id > 0) {
            return $existing_id;
        }

        $inserted = wp_insert_term($label !== '' ? $label : $slug, $taxonomy, ['slug' => $slug]);
        if (is_wp_error($inserted) || !isset($inserted['term_id'])) {
            return 0;
        }

        $tid = (int) $inserted['term_id'];
        foreach ($meta as $k => $v) {
            if ($v !== '') {
                update_term_meta($tid, $k, $v);
            }
        }
        return $tid;
    }

    private function invalidate_caches(int $product_id, bool $cat_changed): void {
        $cur_ver = (int) get_option('drv_cache_ver', 1);
        update_option('drv_cache_ver', $cur_ver + 1, true);

        delete_transient('drv_cat_stats');
        if ($cat_changed) {
            delete_transient('drv_mega_html');
        }

        do_action('litespeed_purge', 'drv_catalog');
        do_action('litespeed_purge', 'drv_prod_' . $product_id);
    }
}

