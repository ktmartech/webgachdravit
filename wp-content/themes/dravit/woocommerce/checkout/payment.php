<?php
/**
 * Khối "04. Phương thức thanh toán" (05-checkout.html). Nút đặt hàng & điều khoản nằm ở cột tóm tắt bên phải
 * (`form-checkout.php`), nên template này chỉ dựng danh sách phương thức — vẫn giữ root
 * `.woocommerce-checkout-payment` để fragment AJAX của WooCommerce thay thế đúng chỗ.
 *
 * @package Dravit
 * @version 10.9.0
 *
 * @var array<string, \WC_Payment_Gateway> $available_gateways
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!wp_doing_ajax()) {
    do_action('woocommerce_review_order_before_payment');
}
?>
<div id="payment" class="woocommerce-checkout-payment">
    <?php if (WC()->cart && WC()->cart->needs_payment()) : ?>
        <div class="drv-pay-list wc_payment_methods payment_methods methods" role="radiogroup" aria-labelledby="checkout-block-payment">
            <?php
            if (!empty($available_gateways)) {
                foreach ($available_gateways as $gateway) {
                    wc_get_template('checkout/payment-method.php', ['gateway' => $gateway]);
                }
            } else {
                echo '<p class="woocommerce-notice woocommerce-notice--info woocommerce-info">Hiện chưa có phương thức thanh toán khả dụng. Vui lòng gọi hotline để được hỗ trợ đặt hàng.</p>';
            }
            ?>
        </div>
    <?php endif; ?>
</div>
<?php
if (!wp_doing_ajax()) {
    do_action('woocommerce_review_order_after_payment');
}
