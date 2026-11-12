<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Khởi tạo idempotent 14 thuộc tính toàn cục `pa_*`, 2 taxonomy ẩn (`drv_price_band`, `drv_flag`)
 * và 28 Smart Collections `product_cat` (docs/03 §2–§3, docs/14 §4, docs/21 Step 2.1).
 */
final class Seeder {
    /**
     * Chạy toàn bộ quy trình seed taxonomy & danh mục chuẩn của Dravit.
     *
     * @return array{attributes: int, terms: int, collections: int}
     */
    public function run(): array {
        $this->rename_default_uncategorized();

        $tax_provider = new Taxonomies();
        $tax_provider->register_internal_taxonomies();

        $facets     = Config::get('facets');
        $attr_count = 0;
        $term_count = 0;

        // 1. Seed 14 Global Attributes `pa_*` và canonical terms.
        $attributes = isset($facets['attributes']) && is_array($facets['attributes']) ? $facets['attributes'] : [];
        foreach ($attributes as $attr_slug => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $this->ensure_wc_attribute((string) $attr_slug, $cfg);
            $attr_count++;

            $tax   = (string) ($cfg['taxonomy'] ?? ('pa_' . $attr_slug));
            $terms = isset($cfg['terms']) && is_array($cfg['terms']) ? $cfg['terms'] : [];
            $term_count += $this->seed_taxonomy_terms($tax, $terms);
        }

        // 2. Seed `drv_price_band` & `drv_flag`.
        $internal = isset($facets['internal_taxonomies']) && is_array($facets['internal_taxonomies'])
            ? $facets['internal_taxonomies']
            : [];
        foreach ($internal as $tax => $cfg) {
            if (!is_array($cfg) || !taxonomy_exists((string) $tax)) {
                continue;
            }
            $terms = isset($cfg['terms']) && is_array($cfg['terms']) ? $cfg['terms'] : [];
            $term_count += $this->seed_taxonomy_terms((string) $tax, $terms);
        }

        // 3. Seed 28 Smart Collections trong `product_cat`.
        $collections_cfg  = Config::get('collections');
        $collection_count = $this->seed_collections($collections_cfg);

        delete_transient('drv_mega_html');
        delete_transient('drv_cat_stats');
        flush_rewrite_rules(false);

        return [
            'attributes'  => $attr_count,
            'terms'       => $term_count,
            'collections' => $collection_count,
        ];
    }

    private function rename_default_uncategorized(): void {
        if (!taxonomy_exists('product_cat')) {
            return;
        }

        $default_id = (int) get_option('default_product_cat', 0);
        $term       = $default_id > 0 ? get_term($default_id, 'product_cat') : get_term_by('slug', 'uncategorized', 'product_cat');
        if (!$term instanceof \WP_Term) {
            return;
        }

        if ($term->slug === 'uncategorized' || strtolower($term->name) === 'uncategorized') {
            wp_update_term($term->term_id, 'product_cat', [
                'name' => 'Chưa phân loại',
                'slug' => 'chua-phan-loai',
            ]);
        }

        update_term_meta($term->term_id, 'rank_math_robots', ['noindex']);
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function ensure_wc_attribute(string $attr_slug, array $cfg): void {
        if (!function_exists('wc_create_attribute') || !function_exists('wc_attribute_taxonomy_id_by_name')) {
            return;
        }

        $label        = (string) ($cfg['label'] ?? $attr_slug);
        $has_archives = !empty($cfg['public_archive']);
        $archive_base = (string) ($cfg['archive_base'] ?? $attr_slug);
        $tax          = 'pa_' . $attr_slug;
        $attr_id      = wc_attribute_taxonomy_id_by_name($attr_slug);

        $args = [
            'name'         => $label,
            'slug'         => $attr_slug,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => $has_archives,
        ];

        if ($attr_id > 0 && function_exists('wc_update_attribute')) {
            wc_update_attribute($attr_id, $args);
        } elseif ($attr_id <= 0) {
            wc_create_attribute($args);
            delete_transient('wc_attribute_taxonomies');
        }

        if (!taxonomy_exists($tax)) {
            register_taxonomy($tax, ['product'], [
                'label'              => $label,
                'public'             => $has_archives,
                'publicly_queryable' => $has_archives,
                'has_archive'        => $has_archives,
                'hierarchical'       => false,
                'show_ui'            => true,
                'query_var'          => $has_archives,
                'rewrite'            => $has_archives
                    ? ['slug' => $archive_base, 'with_front' => false, 'hierarchical' => false]
                    : false,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $terms
     */
    private function seed_taxonomy_terms(string $taxonomy, array $terms): int {
        if (!taxonomy_exists($taxonomy)) {
            return 0;
        }

        $count = 0;
        $order = 0;
        foreach ($terms as $slug => $meta_cfg) {
            if (!is_array($meta_cfg)) {
                continue;
            }
            $order++;
            $name     = (string) ($meta_cfg['name'] ?? $slug);
            $existing = get_term_by('slug', (string) $slug, $taxonomy);

            if ($existing instanceof \WP_Term) {
                $tid = (int) $existing->term_id;
            } else {
                $inserted = wp_insert_term($name, $taxonomy, ['slug' => (string) $slug]);
                if (is_wp_error($inserted) || !isset($inserted['term_id'])) {
                    continue;
                }
                $tid = (int) $inserted['term_id'];
            }

            $meta_map = [
                'drv_filter_label' => (string) ($meta_cfg['filter_label'] ?? ''),
                'drv_short_label'  => (string) ($meta_cfg['short_label'] ?? ''),
                'drv_swatch_hex'   => (string) ($meta_cfg['swatch_hex'] ?? ''),
                'drv_icon'         => (string) ($meta_cfg['icon'] ?? ''),
            ];

            foreach ($meta_map as $m_key => $m_val) {
                if ($m_val !== '' && (string) get_term_meta($tid, $m_key, true) === '') {
                    update_term_meta($tid, $m_key, $m_val);
                }
            }

            if (str_starts_with($taxonomy, 'pa_') && (string) get_term_meta($tid, 'order', true) === '') {
                update_term_meta($tid, 'order', (string) $order);
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, array<string, mixed>> $collections_cfg
     */
    private function seed_collections(array $collections_cfg): int {
        if (!taxonomy_exists('product_cat') || $collections_cfg === []) {
            return 0;
        }

        $count   = 0;
        $root_id = 0;

        if (isset($collections_cfg['gach'])) {
            $root_id = $this->upsert_collection_term('gach', $collections_cfg['gach'], 0);
            if ($root_id > 0) {
                $count++;
            }
        }

        foreach ($collections_cfg as $slug => $item) {
            if ($slug === 'gach') {
                continue;
            }
            $parent_slug = (string) ($item['parent'] ?? '');
            $parent_id   = ($parent_slug === 'gach' && $root_id > 0) ? $root_id : 0;
            $tid         = $this->upsert_collection_term((string) $slug, $item, $parent_id);
            if ($tid > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function upsert_collection_term(string $slug, array $item, int $parent_id): int {
        $name     = (string) ($item['name'] ?? $slug);
        $existing = get_term_by('slug', $slug, 'product_cat');

        if ($existing instanceof \WP_Term) {
            $tid = (int) $existing->term_id;
            if ((int) $existing->parent !== $parent_id) {
                wp_update_term($tid, 'product_cat', ['parent' => $parent_id]);
            }
        } else {
            $inserted = wp_insert_term($name, 'product_cat', [
                'slug'        => $slug,
                'parent'      => $parent_id,
                'description' => (string) ($item['preview_desc'] ?? ''),
            ]);
            if (is_wp_error($inserted) || !isset($inserted['term_id'])) {
                return 0;
            }
            $tid = (int) $inserted['term_id'];
        }

        // Các khóa kỹ thuật luôn đồng bộ chuẩn theo config.
        update_term_meta($tid, 'drv_collection_id', (string) ($item['id'] ?? ''));
        update_term_meta($tid, 'drv_group', (string) ($item['group'] ?? ''));

        // Các khóa nội dung & SEO chỉ gán mặc định khi Admin chưa điền tay.
        $optional_defaults = [
            'drv_h1'                => (string) ($item['h1'] ?? $name),
            'drv_intro'             => (string) ($item['preview_desc'] ?? ''),
            'drv_menu_badge'        => (string) ($item['preview_badge'] ?? ''),
            'drv_menu_desc'         => (string) ($item['preview_desc'] ?? ''),
            'rank_math_title'       => sprintf('%s Chính Hãng Báo Giá Tại Kho | DRAVIT', $name),
            'rank_math_description' => (string) ($item['preview_desc'] ?? ''),
        ];

        foreach ($optional_defaults as $meta_key => $default_val) {
            if ($default_val !== '' && trim((string) get_term_meta($tid, $meta_key, true)) === '') {
                update_term_meta($tid, $meta_key, $default_val);
            }
        }

        return $tid;
    }
}

