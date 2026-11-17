<?php
/**
 * Xác minh email để xem đơn của khách vãng lai (WooCommerce yêu cầu khi mở lại link đơn ở phiên khác).
 *
 * @package Dravit
 * @version 7.9.0
 *
 * @var bool   $failed_submission Đã gửi nhưng email không khớp.
 * @var string $verify_url        URL nhận form.
 */
declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<form name="checkout" method="post" class="woocommerce-form woocommerce-verify-email drv-order-gate__form" action="<?php echo esc_url($verify_url); ?>">
    <?php
    wp_nonce_field('wc_verify_email', 'check_submission');

    if ($failed_submission) {
        wc_print_notice('Email chưa khớp với đơn hàng. Vui lòng kiểm tra và thử lại, hoặc gọi Hotline để được hỗ trợ.', 'error');
    }
    ?>
    <p class="drv-order-gate__lead">
        Nhập email đã dùng khi đặt hàng để xem đơn, hoặc
        <a href="<?php echo esc_url(untrailingslashit((string) wc_get_page_permalink('myaccount'))); ?>">đăng nhập tài khoản</a>.
    </p>

    <div class="drv-field">
        <label class="drv-label" for="email">Email đặt hàng <span class="u-text-sale" aria-hidden="true">*</span></label>
        <input type="email" class="drv-input input-text" name="email" id="email" autocomplete="email" required inputmode="email" placeholder="vd: ten@congty.vn">
    </div>

    <button type="submit" class="drv-btn drv-btn--primary drv-btn--md drv-btn--full" name="verify" value="1">Xem đơn hàng</button>
</form>
