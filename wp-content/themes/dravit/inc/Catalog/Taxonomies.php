<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Đăng ký 2 taxonomy ẩn (`drv_price_band`, `drv_flag`) và khóa trạng thái archive 14 thuộc tính `pa_*` (docs/03 §2, docs/14 §4).
 */
final class Taxonomies implements ServiceProviderInterface {
    public function register(): void {
        add_action('init', [$this, 'register_internal_taxonomies'], 5);
        add_action('init', [$this, 'register_attribute_taxonomy_hooks'], 1);
    }

    public function register_internal_taxonomies(): void {
        if (!post_type_exists('product') && !drv_is_woo_active()) {
            return;
        }

        register_taxonomy('drv_price_band', ['product'], [
            'label'              => 'Khoảng giá (Dravit)',
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_nav_menus'  => false,
            'show_tagcloud'      => false,
            'show_in_rest'       => false,
            'hierarchical'       => false,
            'query_var'          => false,
            'rewrite'            => false,
        ]);

        register_taxonomy('drv_flag', ['product'], [
            'label'              => 'Cờ kỹ thuật (Dravit)',
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_nav_menus'  => false,
            'show_tagcloud'      => false,
            'show_in_rest'       => false,
            'hierarchical'       => false,
            'query_var'          => false,
            'rewrite'            => false,
        ]);
    }

    public function register_attribute_taxonomy_hooks(): void {
        $facets = Config::get('facets');
        $attrs  = isset($facets['attributes']) && is_array($facets['attributes']) ? $facets['attributes'] : [];

        foreach ($attrs as $slug => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $tax            = (string) ($cfg['taxonomy'] ?? ('pa_' . $slug));
            $public_archive = !empty($cfg['public_archive']);
            $archive_base   = (string) ($cfg['archive_base'] ?? $slug);

            add_filter(
                'woocommerce_taxonomy_args_' . $tax,
                static function (array $args) use ($public_archive, $archive_base): array {
                    if ($public_archive) {
                        $args['public']             = true;
                        $args['publicly_queryable'] = true;
                        $args['has_archive']        = true;
                        $args['rewrite']            = [
                            'slug'         => $archive_base,
                            'with_front'   => false,
                            'hierarchical' => false,
                        ];
                    } else {
                        $args['public']             = false;
                        $args['publicly_queryable'] = false;
                        $args['has_archive']        = false;
                        $args['rewrite']            = false;
                    }
                    return $args;
                }
            );
        }
    }
}

