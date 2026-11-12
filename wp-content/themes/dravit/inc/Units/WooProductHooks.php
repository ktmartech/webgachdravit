<?php
declare(strict_types=1);

namespace Dravit\Units;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Ràng buộc thương mại WooCommerce cho hệ đơn vị D-20 & D-24:
 * - Khóa `is_purchasable = false` cho sản phẩm `quote_only`.
 * - Cưỡng bức `drv_qty_min`, `drv_qty_step` và tồn kho trên cả Classic Form lẫn Store API.
 * - Tắt Coupon ở Phase 1 & chuẩn hóa ký hiệu tiền `đ` sát số (docs/04 §2, §4).
 */
final class WooProductHooks implements ServiceProviderInterface {
    public function register(): void {
        add_filter('woocommerce_currency_symbol', [$this, 'filter_currency_symbol'], 20, 2);
        add_filter('woocommerce_coupons_enabled', '__return_false', 20);
        add_filter('woocommerce_is_purchasable', [$this, 'filter_is_purchasable'], 20, 2);
        add_filter('woocommerce_quantity_input_args', [$this, 'filter_quantity_input_args'], 20, 2);
        add_filter('woocommerce_store_api_product_quantity_minimum', [$this, 'filter_store_api_min'], 20, 2);
        add_filter('woocommerce_store_api_product_quantity_multiple_of', [$this, 'filter_store_api_step'], 20, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 20, 3);
        add_filter('woocommerce_update_cart_validation', [$this, 'validate_update_cart'], 20, 4);
    }

    public function filter_currency_symbol(string $symbol, string $currency): string {
        return $currency === 'VND' ? 'đ' : $symbol;
    }

    public function filter_is_purchasable(bool $purchasable, \WC_Product $product): bool {
        if (PriceState::resolve($product) === PriceState::QUOTE_ONLY) {
            return false;
        }
        return $purchasable;
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filter_quantity_input_args(array $args, \WC_Product $product): array {
        $q = UnitRegistry::modelForProduct($product)->quantity_args($product);

        $args['min_value'] = $q['min'];
        $args['step']      = $q['step'];
        if ($q['max'] !== null) {
            $args['max_value'] = $q['max'];
        }
        if (isset($args['input_value']) && (int) $args['input_value'] < $q['min']) {
            $args['input_value'] = $q['min'];
        }

        return $args;
    }

    public function filter_store_api_min(int|float $value, \WC_Product $product): int {
        unset($value);
        return UnitRegistry::modelForProduct($product)->quantity_args($product)['min'];
    }

    public function filter_store_api_step(int|float $value, \WC_Product $product): int {
        unset($value);
        return UnitRegistry::modelForProduct($product)->quantity_args($product)['step'];
    }

    public function validate_add_to_cart(bool $passed, int $product_id, int|float $quantity): bool {
        if (!$passed) {
            return false;
        }
        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
        if (!$product instanceof \WC_Product) {
            return false;
        }
        return $this->validateProductQuantity($product, (int) $quantity);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function validate_update_cart(bool $passed, string $cart_item_key, array $values, int|float $quantity): bool {
        unset($cart_item_key);
        if (!$passed) {
            return false;
        }
        $product = $values['data'] ?? null;
        if (!$product instanceof \WC_Product) {
            return $passed;
        }
        return $this->validateProductQuantity($product, (int) $quantity);
    }

    public function validateProductQuantity(\WC_Product $product, int $qty): bool {
        if (PriceState::resolve($product) === PriceState::QUOTE_ONLY) {
            $this->addError('Sản phẩm này áp dụng báo giá theo khối lượng công trình, vui lòng thêm vào Danh sách báo giá.');
            return false;
        }

        $q          = UnitRegistry::modelForProduct($product)->quantity_args($product);
        $unit_label = $q['unit_label'];

        if ($qty < $q['min']) {
            $this->addError(sprintf(
                'Số lượng tối thiểu cho mẫu %s là %d %s.',
                $product->get_name(),
                $q['min'],
                $unit_label
            ));
            return false;
        }

        if ($q['step'] > 1 && ($qty % $q['step']) !== 0) {
            $next_valid = (int) (ceil(($qty - Calc::EPSILON) / $q['step']) * $q['step']);
            $this->addError(sprintf(
                'Mẫu %s xuất chẵn thùng (%d %s/thùng). Vui lòng chọn %d %s thay vì %d %s.',
                $product->get_name(),
                $q['step'],
                $unit_label,
                $next_valid,
                $unit_label,
                $qty,
                $unit_label
            ));
            return false;
        }

        if ($q['max'] !== null && $qty > $q['max']) {
            $this->addError(sprintf(
                'Tồn kho hiện tại của mẫu %s còn %d %s, không đủ số lượng %d %s bạn chọn.',
                $product->get_name(),
                $q['max'],
                $unit_label,
                $qty,
                $unit_label
            ));
            return false;
        }

        return true;
    }

    private function addError(string $message): void {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, 'error');
        }
    }
}

