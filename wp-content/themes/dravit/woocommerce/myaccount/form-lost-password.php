<?php
/**
 * Quên mật khẩu: nhập SĐT hoặc email → WooCommerce gửi link đặt lại tới email tài khoản.
 * SĐT được `Dravit\Woo\AccountAuth::map_phone_lost_password()` đổi sang tên đăng nhập trước khi WC xử lý.
 *
 * @package Dravit
 * @version 9.2.0
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

get_template_part('template-parts/account/shell-open', null, [
    'title' => 'Quên mật khẩu',
    'lead'  => 'Nhập số điện thoại hoặc email của tài khoản. DRAVIT sẽ gửi link đặt mật khẩu mới tới email đã đăng ký.',
]);
?>
<div class="drv-auth-card">
    <div class="drv-auth-notices">
        <?php do_action('woocommerce_before_lost_password_form'); ?>
    </div>

    <form method="post" class="woocommerce-ResetPassword lost_reset_password drv-auth-form" novalidate>
        <div class="drv-field">
            <label class="drv-label" for="user_login">Số điện thoại hoặc email <span class="drv-label__req" aria-hidden="true">*</span></label>
            <input class="drv-input woocommerce-Input input-text" type="text" name="user_login" id="user_login"
                   autocomplete="username" autocapitalize="off" spellcheck="false" required aria-required="true"
                   placeholder="0912 345 678 hoặc ten@email.com">
        </div>

        <?php do_action('woocommerce_lostpassword_form'); ?>

        <input type="hidden" name="wc_reset_password" value="true">
        <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>
        <button type="submit" class="drv-btn drv-btn--primary drv-btn--md drv-btn--full woocommerce-Button" value="Gửi link đặt lại mật khẩu">
            <span>Gửi link đặt lại mật khẩu</span>
            <i class="ph ph-envelope-simple drv-icon--xs" aria-hidden="true"></i>
        </button>
    </form>
    <p class="drv-auth-switch">
        <a href="<?php echo esc_url(untrailingslashit((string) wc_get_page_permalink('myaccount'))); ?>"><i class="ph ph-arrow-left" aria-hidden="true"></i> Quay lại đăng nhập</a>
    </p>
</div>
<?php
get_template_part('template-parts/account/shell-close');

do_action('woocommerce_after_lost_password_form');
