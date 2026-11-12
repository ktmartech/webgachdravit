<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

final class Cleanup implements ServiceProviderInterface {
    public function register(): void {
        add_action('init', [$this, 'clean_head']);
        add_filter('the_generator', '__return_empty_string');
        add_filter('emoji_svg_url', '__return_false');
        add_action('wp_enqueue_scripts', [$this, 'prevent_unregistered_variation_notice'], 0);
        add_action('wp_enqueue_scripts', [$this, 'optimize_frontend_core_assets'], 20);
        add_filter('heartbeat_settings', [$this, 'throttle_admin_heartbeat']);
    }

    public function clean_head(): void {
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    /**
     * Ngăn lỗi WP 6.9+/7.1 Classic Theme + theme.json: `block-style-variation-styles` gọi `global-styles`
     * trước khi `global-styles` được đăng ký ở `wp_footer`, đồng thời gỡ inline `:root` trùng với `tokens.css` (D-01).
     */
    public function prevent_unregistered_variation_notice(): void {
        if (is_admin()) {
            return;
        }

        remove_action('wp_enqueue_scripts', 'wp_enqueue_block_style_variation_styles', 1);
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
    }

    /**
     * Tắt Heartbeat ngoài frontend và gỡ block CSS mặc định trên các trang cửa hàng thuần PHP.
     * Tuyệt đối KHÔNG gỡ wildcard script/style của plugin bên thứ 3 (D-31, 21 §5).
     */
    public function optimize_frontend_core_assets(): void {
        if (!is_admin()) {
            wp_deregister_script('heartbeat');
            wp_dequeue_style('block-style-variation-styles');
        }

        $needs_block_styles = is_singular('post') || (is_page() && !self::is_commerce_or_app_page());
        if (!$needs_block_styles) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('classic-theme-styles');
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function throttle_admin_heartbeat(array $settings): array {
        $settings['interval'] = 60;
        return $settings;
    }

    private static function is_commerce_or_app_page(): bool {
        if (is_front_page()) {
            return true;
        }

        if (drv_is_woo_active() && (is_cart() || is_checkout() || is_account_page() || is_woocommerce())) {
            return true;
        }

        return is_page_template([
            'page-templates/template-contact.php',
            'page-templates/template-quote.php',
            'page-templates/template-ai-finder.php',
        ]);
    }
}

