<?php
declare(strict_types=1);

namespace Dravit\Units;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Ràng buộc thương mại WooCommerce cho hệ đơn vị D-20, D-24 & D-30:
 * - Khóa `is_purchasable = false` cho sản phẩm `quote_only`.
 * - Cưỡng bức `drv_qty_min`, `drv_qty_step` và tồn kho trên cả Classic Form lẫn Store API.
 * - Lưu ngữ cảnh tính toán (`drv_m2_input`, `drv_waste_pct`, `drv_input_unit`) vào Cart Item Session.
 * - Hỗ trợ nút "Mua ngay" (`drv_buy_now=1`) chuyển thẳng sang `/thanh-toan` kể cả khi tắt JS (`P-7`).
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
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_calc_meta'], 20, 2);
        add_filter('woocommerce_store_api_add_to_cart_data', [$this, 'add_store_api_cart_item_meta'], 20, 2);
        add_filter('woocommerce_add_to_cart_redirect', [$this, 'filter_add_to_cart_redirect'], 20, 1);
    }

    /**
     * Hàm thuần quyết định URL chuyển hướng sau khi bấm "Mua ngay" (`drv_buy_now=1` - ADR D-30, Test P-7).
     *
     * @param array<string, mixed> $post_data
     */
    public static function resolveBuyNowRedirect(
        string $default_url,
        array $post_data,
        string $checkout_url,
        bool $has_error_notices = false
    ): string {
        if ($has_error_notices) {
            return $default_url;
        }

        $raw_flag = isset($post_data['drv_buy_now']) && is_scalar($post_data['drv_buy_now'])
            ? trim((string) $post_data['drv_buy_now'])
            : '';

        if ($raw_flag === '1' && trim($checkout_url) !== '') {
            return UrlNormalizer::untrailingslashUrl($checkout_url);
        }

        return $default_url;
    }

    /**
     * Hàm thuần làm sạch metadata máy tính gạch (`drv_m2_input`, `drv_waste_pct`, `drv_input_unit`) khi thêm vào giỏ.
     *
     * @param array<string, mixed> $raw
     * @return array<string, int|float|string>
     */
    public static function extractCartItemCalcMeta(array $raw): array {
        $meta = [];

        $m2_raw = $raw['drv_m2_input'] ?? ($raw['m2_input'] ?? null);
        if (is_scalar($m2_raw)) {
            $m2 = round((float) str_replace(',', '.', trim((string) $m2_raw)), 2);
            if ($m2 > 0.0 && $m2 <= 100000.0) {
                $meta['drv_m2_input'] = $m2;
            }
        }

        $waste_raw = $raw['drv_waste_pct'] ?? ($raw['waste_pct'] ?? null);
        if (is_scalar($waste_raw) && trim((string) $waste_raw) !== '') {
            $waste = (int) round((float) $waste_raw);
            if (in_array($waste, [0, 5, 10], true)) {
                $meta['drv_waste_pct'] = $waste;
            }
        }

        $unit_raw = $raw['drv_input_unit'] ?? ($raw['input_unit'] ?? null);
        if (is_scalar($unit_raw)) {
            $u = strtolower(trim((string) $unit_raw));
            if (in_array($u, ['m2', 'thung', 'vien'], true)) {
                $meta['drv_input_unit'] = $u;
            }
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $cart_item_data
     * @return array<string, mixed>
     */
    public function add_cart_item_calc_meta(array $cart_item_data, int $product_id): array {
        unset($product_id);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates add-to-cart request prior to this filter.
        $extracted = self::extractCartItemCalcMeta($_POST);
        foreach ($extracted as $k => $v) {
            $cart_item_data[$k] = $v;
        }
        return $cart_item_data;
    }

    /**
     * @param array<string, mixed> $add_to_cart_data
     * @param \WP_REST_Request     $request
     * @return array<string, mixed>
     */
    public function add_store_api_cart_item_meta(array $add_to_cart_data, \WP_REST_Request $request): array {
        $drv_payload = $request->get_param('drv_cart_data');
        if (!is_array($drv_payload)) {
            $ext         = $request->get_param('extensions');
            $drv_payload = (is_array($ext) && isset($ext['drv']) && is_array($ext['drv'])) ? $ext['drv'] : [];
        }
        if ($drv_payload === []) {
            return $add_to_cart_data;
        }

        $extracted = self::extractCartItemCalcMeta($drv_payload);
        if ($extracted !== []) {
            $existing                            = isset($add_to_cart_data['cart_item_data']) && is_array($add_to_cart_data['cart_item_data'])
                ? $add_to_cart_data['cart_item_data']
                : [];
            $add_to_cart_data['cart_item_data'] = array_merge($existing, $extracted);
        }

        return $add_to_cart_data;
    }

    public function filter_add_to_cart_redirect(string $url): string {
        $has_errors   = function_exists('wc_notice_count') && wc_notice_count('error') > 0;
        $checkout_url = function_exists('wc_get_checkout_url') ? (string) wc_get_checkout_url() : home_url('/thanh-toan');

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce processes add-to-cart form before redirect filter.
        return self::resolveBuyNowRedirect($url, $_POST, $checkout_url, $has_errors);
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

