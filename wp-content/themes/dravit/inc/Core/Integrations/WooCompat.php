<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

final class WooCompat implements ServiceProviderInterface {
    public function register(): void {
        add_action('before_woocommerce_init', [$this, 'declare_features_compatibility']);
        add_filter('woocommerce_enqueue_styles', '__return_empty_array');
        add_action('wp_enqueue_scripts', [$this, 'dequeue_unused_woo_assets'], 30);
    }

    /**
     * Khai báo tương thích HPOS 100% (D-22) và khóa Cart/Checkout Blocks để ép dùng Classic Shortcode (D-15).
     */
    public function declare_features_compatibility(): void {
        if (!class_exists(FeaturesUtil::class)) {
            return;
        }

        $theme_entry = DRV_DIR . '/functions.php';
        FeaturesUtil::declare_compatibility('custom_order_tables', $theme_entry, true);
        FeaturesUtil::declare_compatibility('cart_checkout_blocks', $theme_entry, false);
    }

    /**
     * Gỡ CSS/JS thừa của WooCommerce trên các trang không phải giỏ hàng/thanh toán/tài khoản (08 §2-3).
     */
    public function dequeue_unused_woo_assets(): void {
        if (is_admin()) {
            return;
        }

        wp_dequeue_style('wc-blocks-style');
        wp_dequeue_style('wc-blocks-vendors-style');

        $is_transactional = function_exists('is_cart')
            && (is_cart() || is_checkout() || is_account_page());

        if (!$is_transactional) {
            wp_dequeue_script('wc-cart-fragments');
            wp_dequeue_script('sourcebuster-js');
            wp_dequeue_script('wc-order-attribution');
        }
    }
}

