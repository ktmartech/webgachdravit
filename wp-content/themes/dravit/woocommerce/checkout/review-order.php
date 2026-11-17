<?php
/**
 * Tóm tắt đơn ở Checkout — fragment AJAX `update_order_review` (docs/04 §5: mọi dòng quy đổi `thùng · m² · kg`,
 * tổng diện tích, tổng khối lượng, gợi ý xe và nhãn phí vận chuyển báo sau PHẢI nằm trong template này để
 * WooCommerce không ghi đè mất khi khách đổi Tỉnh/Phường hoặc phương thức thanh toán).
 *
 * Root giữ class `woocommerce-checkout-review-order-table` để `checkout.js` của WooCommerce thay fragment.
 *
 * @package Dravit
 * @version 11.2.2
 */
declare(strict_types=1);

use Dravit\Units\PriceState;
use Dravit\Woo\CartPresenter;
use Dravit\Woo\QuoteShippingMethod;
use Dravit\Woo\Wards;

defined('ABSPATH') || exit;

$cart     = WC()->cart;
$lines    = CartPresenter::fromCart($cart);
$summary  = CartPresenter::summarize(array_values($lines), CartPresenter::vehicleThresholds());
$province = WC()->customer ? (string) WC()->customer->get_billing_state() : '';
$prov_lbl = Wards::provinces()[$province] ?? 'Chưa chọn';
$subtotal = (int) round((float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax());
$gateway  = (string) (WC()->session ? WC()->session->get('chosen_payment_method') : '');
$pay_lbl  = $gateway === 'cod' ? 'COD - thanh toán khi nhận hàng' : 'Chuyển khoản VietQR (sau khi chốt đơn)';
?>
<div
    class="woocommerce-checkout-review-order-table drv-review"
    data-drv-review
    data-line-count="<?php echo esc_attr((string) $summary['line_count']); ?>"
    data-total-m2="<?php echo esc_attr(drv_format_decimal($summary['total_m2'], 2)); ?>">

    <?php do_action('woocommerce_review_order_before_cart_contents'); ?>
    <div class="drv-checkout-items" role="list" aria-label="Sản phẩm trong đơn hàng">
        <?php
        foreach ($cart->get_cart() as $key => $cart_item) {
            $product = apply_filters('woocommerce_cart_item_product', $cart_item['data'] ?? null, $cart_item, $key);
            if (!$product instanceof \WC_Product || !$product->exists() || (int) ($cart_item['quantity'] ?? 0) <= 0) {
                continue;
            }
            if (!apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $key)) {
                continue;
            }
            $line   = $lines[$key] ?? CartPresenter::line(CartPresenter::snapshotFromProduct($product), (int) $cart_item['quantity'], $cart_item);
            $unit   = (string) ($line['unit_label'] ?? 'viên');
            $spec   = [sprintf('%s (%d %s', (string) $line['box_text'], (int) $cart_item['quantity'], $unit)];
            $m2     = (float) ($line['m2_qty'] ?? 0);
            $spec[0] .= $m2 > 0 ? ' · ' . drv_format_decimal($m2, 2) . ' m²)' : ')';
            if ((float) ($line['kg'] ?? 0) > 0) {
                $spec[] = drv_format_decimal((float) $line['kg'], 1) . ' kg';
            }
            if (($line['shade'] ?? '') !== '') {
                $spec[] = 'Shade ' . $line['shade'];
            }
            $unit_price = (int) ($line['price_m2'] ?? 0) > 0
                ? drv_format_vnd((int) $line['price_m2']) . '/m²'
                : drv_format_vnd((int) ($line['price_piece'] ?? 0)) . '/' . $unit;
            $thumb = $product->get_image([56, 56], ['class' => 'drv-checkout-item__thumb', 'loading' => 'lazy', 'decoding' => 'async']);
            if ((int) $product->get_image_id() <= 0) {
                $fallback = PriceState::resolveFallbackImages($product->get_id());
                if ($fallback['primary'] !== '') {
                    $thumb = sprintf('<img src="%s" width="56" height="56" loading="lazy" decoding="async" alt="" class="drv-checkout-item__thumb">', esc_url($fallback['primary']));
                }
            }
            ?>
            <div class="drv-checkout-item <?php echo esc_attr((string) apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $key)); ?>" role="listitem">
                <?php echo wp_kses_post($thumb); ?>
                <div class="drv-checkout-item__body">
                    <div class="drv-checkout-item__header-row">
                        <span class="drv-checkout-item__name"><?php echo wp_kses_post((string) apply_filters('woocommerce_cart_item_name', $product->get_name(), $cart_item, $key)); ?></span>
                        <strong class="drv-checkout-item__total u-tabular"><?php echo wp_kses_post((string) apply_filters('woocommerce_cart_item_subtotal', $cart->get_product_subtotal($product, (int) $cart_item['quantity']), $cart_item, $key)); ?></strong>
                    </div>
                    <div class="drv-checkout-item__sub-row">
                        <span class="drv-checkout-item__spec u-tabular"><?php echo esc_html(implode(' · ', $spec)); ?></span>
                        <span class="drv-checkout-item__unit u-tabular"><?php echo esc_html($unit_price); ?></span>
                    </div>
                    <?php echo wc_get_formatted_cart_item_data($cart_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC escapes internally. ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>
    <?php do_action('woocommerce_review_order_after_cart_contents'); ?>

    <div class="drv-cart-metrics" aria-label="Tổng hợp khối lượng">
        <div class="drv-cart-metric">
            <span class="drv-cart-metric__label">Tổng diện tích</span>
            <strong class="drv-cart-metric__val u-text-moss u-tabular"><?php echo esc_html(drv_format_decimal($summary['total_m2'], 2) . ' m²'); ?></strong>
        </div>
        <div class="drv-cart-metric">
            <span class="drv-cart-metric__label">Số thùng</span>
            <strong class="drv-cart-metric__val u-tabular"><?php echo esc_html($summary['boxes_text']); ?></strong>
        </div>
        <div class="drv-cart-metric">
            <span class="drv-cart-metric__label">Tổng trọng lượng</span>
            <strong class="drv-cart-metric__val u-tabular"><?php echo esc_html(drv_format_decimal($summary['total_kg'], 0) . ' kg'); ?></strong>
        </div>
        <div class="drv-cart-metric">
            <span class="drv-cart-metric__label">Tỉnh / TP nhận hàng</span>
            <strong class="drv-cart-metric__val u-text-navy" data-checkout-province-name><?php echo esc_html($prov_lbl); ?></strong>
        </div>
    </div>

    <div class="l-stack l-stack--xs drv-review__rows">
        <div class="drv-summary-row cart-subtotal">
            <span class="u-text-muted"><?php echo esc_html(sprintf('Tạm tính vật tư (%d mã):', $summary['line_count'])); ?></span>
            <strong class="u-text-navy u-tabular"><?php echo esc_html(drv_format_vnd($subtotal)); ?></strong>
        </div>

        <?php foreach ($cart->get_fees() as $fee) : ?>
            <div class="drv-summary-row fee">
                <span class="u-text-muted"><?php echo esc_html($fee->name); ?></span>
                <strong class="u-tabular"><?php wc_cart_totals_fee_html($fee); ?></strong>
            </div>
        <?php endforeach; ?>

        <?php if ($summary['vehicle_hint'] !== '') : ?>
            <div class="drv-summary-row">
                <span class="u-text-muted">Xe giao dự kiến:</span>
                <strong class="u-text-navy u-text-xs"><?php echo esc_html($summary['vehicle_hint']); ?></strong>
            </div>
        <?php endif; ?>

        <div class="drv-summary-row">
            <span class="u-text-muted">Thanh toán:</span>
            <strong class="u-text-navy u-text-xs" data-checkout-pay-summary><?php echo esc_html($pay_lbl); ?></strong>
        </div>

        <?php do_action('woocommerce_review_order_before_shipping'); ?>
        <div class="drv-summary-row shipping">
            <span class="u-text-muted"><?php echo esc_html('Phí vận chuyển (' . drv_format_decimal($summary['total_kg'], 0) . ' kg):'); ?></span>
            <span class="u-text-moss u-bold"><?php echo esc_html(QuoteShippingMethod::label()); ?></span>
            <?php
            if ($cart->needs_shipping()) {
                $chosen = WC()->session ? (array) WC()->session->get('chosen_shipping_methods', []) : [];
                foreach (WC()->shipping()->get_packages() as $i => $package) {
                    $rates = isset($package['rates']) && is_array($package['rates']) ? $package['rates'] : [];
                    if ($rates === []) {
                        continue;
                    }
                    $rate_id = isset($chosen[$i], $rates[$chosen[$i]]) ? (string) $chosen[$i] : (string) array_key_first($rates);
                    printf(
                        '<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d" value="%2$s" class="shipping_method">',
                        (int) $i,
                        esc_attr($rate_id)
                    );
                }
            }
            ?>
        </div>
        <?php do_action('woocommerce_review_order_after_shipping'); ?>
    </div>

    <?php do_action('woocommerce_review_order_before_order_total'); ?>
    <div class="drv-summary-row drv-summary-row--total order-total" aria-live="polite">
        <div class="l-stack l-stack--2xs">
            <span>Tổng tạm tính (chưa gồm phí vận chuyển)</span>
            <span class="u-text-xs u-text-muted">Giá đã gồm VAT</span>
        </div>
        <span class="u-text-sale u-text-price u-tabular" data-review-total><?php wc_cart_totals_order_total_html(); ?></span>
    </div>
    <?php do_action('woocommerce_review_order_after_order_total'); ?>
</div>
