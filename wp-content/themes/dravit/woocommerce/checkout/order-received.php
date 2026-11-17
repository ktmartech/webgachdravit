<?php
/**
 * Thông điệp "đã nhận đơn" (WooCommerce gọi với `$order = false` khi cần đăng nhập / xác nhận email
 * trước khi xem đơn — `WC_Shortcode_Checkout::order_received()`). Trang đơn đầy đủ nằm ở `thankyou.php`.
 *
 * @package Dravit
 * @version 8.8.0
 *
 * @var \WC_Order|false $order
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$message = (string) apply_filters(
    'woocommerce_thankyou_order_received_text',
    'Cảm ơn Quý khách. Đơn hàng đã được tiếp nhận.',
    $order
);
?>
<div class="drv-order-gate__head">
    <div class="drv-order-notice drv-order-notice--pending" role="status">
        <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-seal-check"></i></div>
        <div class="l-stack l-stack--2xs">
            <h1 class="drv-order-notice__title"><?php echo wp_kses_post($message); ?></h1>
            <p class="drv-order-notice__text">Để bảo mật thông tin đơn hàng, vui lòng xác minh trước khi xem chi tiết. Nhân viên DRAVIT vẫn sẽ gọi xác nhận lô gạch và phí vận chuyển như bình thường.</p>
        </div>
    </div>
</div>
