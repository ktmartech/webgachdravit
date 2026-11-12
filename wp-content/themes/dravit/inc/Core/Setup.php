<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

final class Setup implements ServiceProviderInterface {
    public function register(): void {
        add_action('after_setup_theme', [$this, 'setup_theme']);
        add_filter('language_attributes', [$this, 'force_vietnamese_lang'], 20);
        add_filter('user_trailingslashit', [$this, 'untrailingslash_non_root'], 20, 2);
        add_filter('intermediate_image_sizes_advanced', [$this, 'prune_unused_image_sizes']);
        add_filter('big_image_size_threshold', static fn (): int => 2560);
        add_filter('wp_editor_set_quality', static fn (): int => 80);
    }

    public function setup_theme(): void {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support(
            'html5',
            ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']
        );
        add_theme_support('responsive-embeds');
        add_theme_support('editor-styles');
        add_theme_support('custom-logo', [
            'height'      => 60,
            'width'       => 220,
            'flex-height' => true,
            'flex-width'  => true,
        ]);

        // Kích thước ảnh chuẩn hóa theo docs/08_Performance_Hosting.md §4.
        add_image_size('drv_card_sm', 240, 240, true);
        add_image_size('drv_zoom', 2000, 2000, false);
        add_image_size('drv_hero', 1920, 1080, true);
        add_image_size('drv_hero_m', 828, 1035, true);
        add_image_size('drv_post', 1200, 800, true);

        // Tích hợp WooCommerce không nạp kèm thư viện gallery cũ (flexslider/zoom/photoswipe v4).
        if (drv_is_woo_active()) {
            add_theme_support('woocommerce', [
                'thumbnail_image_width'         => 480,
                'single_image_width'            => 1000,
                'gallery_thumbnail_image_width' => 160,
                'product_grid'                  => [
                    'default_rows'    => 6,
                    'min_rows'        => 1,
                    'default_columns' => 4,
                    'min_columns'     => 2,
                    'max_columns'     => 4,
                ],
            ]);
        }
    }

    /**
     * Khóa cứng đơn ngôn ngữ Tiếng Việt (`lang="vi"`, ADR D-01).
     */
    public function force_vietnamese_lang(string $output): string {
        if (preg_match('/\blang="[^"]*"/i', $output) === 1) {
            return (string) preg_replace('/\blang="[^"]*"/i', 'lang="vi"', $output);
        }

        return trim('lang="vi" ' . $output);
    }

    /**
     * Gỡ các kích thước ảnh trung gian dư thừa của WP để tiết kiệm dung lượng đĩa.
     *
     * @param array<string, mixed> $sizes
     * @return array<string, mixed>
     */
    public function prune_unused_image_sizes(array $sizes): array {
        unset($sizes['1536x1536'], $sizes['2048x2048'], $sizes['medium_large']);
        return $sizes;
    }

    /**
     * Ép toàn bộ URL nội bộ không dùng dấu `/` cuối trừ trang gốc `/` (ADR D-21).
     */
    public function untrailingslash_non_root(string $url, string $type): string {
        unset($type);
        if ($url === '/') {
            return '/';
        }

        return untrailingslashit($url);
    }
}
