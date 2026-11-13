<?php
declare(strict_types=1);

namespace Dravit\Seo;

use Dravit\Catalog\PrimaryCategory;
use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Chuẩn hóa Breadcrumb duy nhất toàn hệ thống (docs/06 §2, IL-04):
 * - Gỡ bỏ `woocommerce_breadcrumb()` trùng lặp.
 * - Tắt `/san-pham` trong Rank Math Breadcrumb; chèn `Gạch` (`/gach`) và `PrimaryCategory` trên PDP.
 * - Chèn `Cẩm nang` (`/cam-nang`) trên bài viết đơn và chuyên mục Cẩm nang.
 * - Cung cấp mảng `getCurrentTrail()` cho `drv_ui('breadcrumb')`.
 */
final class Breadcrumbs implements ServiceProviderInterface {
    public function register(): void {
        add_action('init', [$this, 'remove_woo_breadcrumb'], 20);
        add_filter('rank_math/frontend/breadcrumb/is_using_shop_base', '__return_false');
        add_filter('rank_math/frontend/breadcrumb/items', [$this, 'filter_rank_math_items'], 20, 1);
    }

    public function remove_woo_breadcrumb(): void {
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
    }

    /**
     * Chuẩn hóa mảng crumbs của Rank Math `[[label, url, ...], ...]`.
     *
     * @param array<int, array<int|string, mixed>> $crumbs
     * @return array<int, array<int|string, mixed>>
     */
    public function filter_rank_math_items(array $crumbs): array {
        $trail = self::getCurrentTrail();
        if ($trail === []) {
            foreach ($crumbs as &$crumb) {
                if (isset($crumb[1]) && is_string($crumb[1]) && $crumb[1] !== '') {
                    $crumb[1] = UrlNormalizer::untrailingslashUrl($crumb[1]);
                }
            }
            unset($crumb);
            return $crumbs;
        }

        $formatted = [];
        foreach ($trail as $item) {
            $formatted[] = [
                $item['label'],
                $item['url'],
                'hide_in_schema' => false,
            ];
        }

        return $formatted;
    }

    /**
     * Xây dựng đường dẫn Breadcrumb chuẩn theo ngữ cảnh trang hiện tại.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function getCurrentTrail(): array {
        if (!function_exists('home_url') || (function_exists('is_front_page') && is_front_page() && !is_home())) {
            return [];
        }

        $home_url = UrlNormalizer::untrailingslashUrl(home_url('/'));
        $gach_url = UrlNormalizer::untrailingslashUrl(home_url('/gach'));
        $blog_url = UrlNormalizer::untrailingslashUrl(home_url('/cam-nang'));

        $trail = [
            ['label' => 'Trang chủ', 'url' => $home_url !== '' ? $home_url : '/'],
        ];

        if (drv_is_woo_active() && is_shop()) {
            $trail[] = ['label' => 'Gạch', 'url' => $gach_url];
            return $trail;
        }

        if (drv_is_woo_active() && is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                if ($term->slug === 'gach') {
                    $trail[] = ['label' => 'Gạch', 'url' => $gach_url];
                } else {
                    $trail[] = ['label' => 'Gạch', 'url' => $gach_url];
                    $trail[] = [
                        'label' => $term->name,
                        'url'   => UrlNormalizer::untrailingslashUrl(home_url('/' . $term->slug)),
                    ];
                }
            }
            return $trail;
        }

        if (is_tax(['pa_bo-suu-tap', 'pa_xuat-xu'])) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $trail[]  = ['label' => 'Gạch', 'url' => $gach_url];
                $link     = get_term_link($term);
                $term_url = is_string($link) ? UrlNormalizer::untrailingslashUrl($link) : '';
                $trail[]  = ['label' => $term->name, 'url' => $term_url];
            }
            return $trail;
        }

        if (drv_is_woo_active() && is_product()) {
            $product_id = get_queried_object_id();
            $trail[]    = ['label' => 'Gạch', 'url' => $gach_url];

            $primary_term = self::resolveProductPrimaryTerm($product_id);
            if ($primary_term instanceof \WP_Term && $primary_term->slug !== 'gach') {
                $trail[] = [
                    'label' => $primary_term->name,
                    'url'   => UrlNormalizer::untrailingslashUrl(home_url('/' . $primary_term->slug)),
                ];
            }

            $trail[] = [
                'label' => get_the_title($product_id),
                'url'   => UrlNormalizer::untrailingslashUrl((string) get_permalink($product_id)),
            ];
            return $trail;
        }

        if (is_home()) {
            $trail[] = ['label' => 'Cẩm nang', 'url' => $blog_url];
            return $trail;
        }

        if (is_category()) {
            $term = get_queried_object();
            $trail[] = ['label' => 'Cẩm nang', 'url' => $blog_url];
            if ($term instanceof \WP_Term) {
                $link     = get_term_link($term);
                $term_url = is_string($link) ? UrlNormalizer::untrailingslashUrl($link) : '';
                $trail[]  = ['label' => $term->name, 'url' => $term_url];
            }
            return $trail;
        }

        if (is_singular('post')) {
            $post_id = get_queried_object_id();
            $trail[] = ['label' => 'Cẩm nang', 'url' => $blog_url];

            $cats = get_the_category($post_id);
            if (is_array($cats) && isset($cats[0]) && $cats[0] instanceof \WP_Term && $cats[0]->slug !== 'uncategorized') {
                $cat_link = get_term_link($cats[0]);
                $trail[]  = [
                    'label' => $cats[0]->name,
                    'url'   => is_string($cat_link) ? UrlNormalizer::untrailingslashUrl($cat_link) : '',
                ];
            }

            $trail[] = [
                'label' => get_the_title($post_id),
                'url'   => UrlNormalizer::untrailingslashUrl((string) get_permalink($post_id)),
            ];
            return $trail;
        }

        if (is_search()) {
            $q       = trim((string) get_search_query());
            $label   = $q !== '' ? sprintf('Tìm kiếm: "%s"', $q) : 'Tìm kiếm sản phẩm';
            $trail[] = ['label' => $label, 'url' => ''];
            return $trail;
        }

        if (is_page()) {
            $page_id = get_queried_object_id();
            $trail[] = [
                'label' => get_the_title($page_id),
                'url'   => UrlNormalizer::untrailingslashUrl((string) get_permalink($page_id)),
            ];
            return $trail;
        }

        return [];
    }

    private static function resolveProductPrimaryTerm(int $product_id): ?\WP_Term {
        if ($product_id <= 0) {
            return null;
        }

        $primary_tid = (int) get_post_meta($product_id, 'rank_math_primary_product_cat', true);
        if ($primary_tid > 0) {
            $term = get_term($primary_tid, 'product_cat');
            if ($term instanceof \WP_Term && $term->slug !== 'gach') {
                return $term;
            }
        }

        $terms = get_the_terms($product_id, 'product_cat');
        if (!is_array($terms) || $terms === []) {
            return null;
        }

        $slugs = [];
        $by_slug = [];
        foreach ($terms as $t) {
            if ($t instanceof \WP_Term) {
                $slugs[]            = $t->slug;
                $by_slug[$t->slug]  = $t;
            }
        }

        $best_slug = PrimaryCategory::resolvePrimarySlug($slugs, Config::get('collections'));
        return ($best_slug !== null && isset($by_slug[$best_slug])) ? $by_slug[$best_slug] : null;
    }
}
