<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Hành vi phiên giỏ hàng (docs/04 §4, docs/14 §7.1):
 * - Cùng `product_id` luôn gộp về 1 dòng (không tách theo `drv_m2_input`/`drv_waste_pct`).
 * - Khách chỉnh số lượng bằng stepper → xóa ngữ cảnh m² đã nhập trên PDP (về `0`).
 * - Gỡ cross-sells & cart_totals mặc định (template `cart/cart.php` tự dựng khối tổng khối lượng).
 */
final class CartHooks implements ServiceProviderInterface {
    /** Các khóa ngữ cảnh máy tính PDP không được tham gia vào cart_id. */
    public const CONTEXT_KEYS = ['drv_m2_input', 'drv_waste_pct', 'drv_input_unit'];

    public function register(): void {
        add_filter('woocommerce_cart_id', [$this, 'filter_cart_id'], 20, 5);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'reset_calc_context'], 20, 3);
        add_action('init', [$this, 'unhook_default_collaterals'], 30);
    }

    /**
     * Hàm thuần: cart_id chỉ phụ thuộc sản phẩm/biến thể + dữ liệu không thuộc ngữ cảnh máy tính.
     *
     * @param array<string, mixed> $variation
     * @param array<string, mixed> $cart_item_data
     */
    public static function stableCartId(int $product_id, int $variation_id, array $variation, array $cart_item_data): string {
        foreach (self::CONTEXT_KEYS as $k) {
            unset($cart_item_data[$k]);
        }

        $parts = [(string) $product_id];
        if ($variation_id > 0) {
            $parts[] = (string) $variation_id;
        }
        if ($variation !== []) {
            ksort($variation);
            $parts[] = (string) wp_json_encode($variation);
        }
        if ($cart_item_data !== []) {
            ksort($cart_item_data);
            $parts[] = (string) wp_json_encode($cart_item_data);
        }

        return md5(implode('|', $parts));
    }

    /**
     * @param array<string, mixed> $variation
     * @param array<string, mixed> $cart_item_data
     */
    public function filter_cart_id(string $cart_id, int $product_id, int $variation_id = 0, array $variation = [], array $cart_item_data = []): string {
        unset($cart_id);
        return self::stableCartId($product_id, $variation_id, $variation, $cart_item_data);
    }

    public function reset_calc_context(string $cart_item_key, int|float $quantity, int|float $old_quantity): void {
        if ((int) $quantity === (int) $old_quantity || !function_exists('WC') || !WC()->cart) {
            return;
        }
        $contents = WC()->cart->cart_contents;
        if (!isset($contents[$cart_item_key])) {
            return;
        }
        $contents[$cart_item_key]['drv_m2_input']   = 0.0;
        $contents[$cart_item_key]['drv_input_unit'] = 'vien';
        WC()->cart->set_cart_contents($contents);
    }

    public function unhook_default_collaterals(): void {
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10);
        remove_action('woocommerce_cart_is_empty', 'wc_empty_cart_message', 10);
        // Nút "Tiến hành đặt hàng" do template tự dựng; giữ action cho plugin khác móc vào.
        remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        // Phase 1: không gợi ý vật tư mua kèm (D-26).
        remove_action('woocommerce_after_cart_table', 'woocommerce_cross_sell_display');
    }
}
