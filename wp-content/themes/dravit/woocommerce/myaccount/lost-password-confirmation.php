<?php
/**
 * Đã gửi email đặt lại mật khẩu (WooCommerce luôn hiện màn này dù tài khoản có tồn tại hay không — chống dò tài khoản).
 *
 * @package Dravit
 * @version 3.9.0
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

get_template_part('template-parts/account/shell-open', null, [
    'title' => 'Kiểm tra email của Quý khách',
    'crumb' => 'Quên mật khẩu',
]);
?>
<div class="drv-auth-card">
    <?php do_action('woocommerce_before_lost_password_confirmation_message'); ?>
    <div class="drv-auth-done" role="status">
        <span class="drv-auth-done__icon" aria-hidden="true"><i class="ph ph-envelope-simple"></i></span>
        <div class="l-stack l-stack--2xs">
            <h2 class="drv-auth-panel__title">Đã gửi link đặt lại mật khẩu</h2>
            <p class="drv-auth-done__text">
                <?php echo esc_html((string) apply_filters('woocommerce_lost_password_confirmation_message', 'Nếu thông tin trùng khớp với một tài khoản, email chứa link đặt mật khẩu mới sẽ đến trong vài phút. Vui lòng kiểm tra cả hộp thư Spam/Quảng cáo. Link chỉ dùng được một lần.')); ?>
            </p>
        </div>
    </div>
    <?php do_action('woocommerce_after_lost_password_confirmation_message'); ?>
    <p class="drv-auth-switch">
        <a href="<?php echo esc_url(untrailingslashit((string) wc_get_page_permalink('myaccount'))); ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i> Quay lại đăng nhập</a>
    </p>
</div>
<?php
get_template_part('template-parts/account/shell-close');
