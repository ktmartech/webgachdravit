<?php
/**
 * `/tai-khoan` khi chưa đăng nhập: 2 tab Đăng nhập (SĐT hoặc email) · Tạo tài khoản (Họ tên, SĐT, Email, Mật khẩu).
 * Không JS: 2 form xếp dọc; có JS (`account.js`): tab chuyển qua lại, giữ `#dang-ky` trên URL.
 * Backend: `Dravit\Woo\AccountAuth` (đăng nhập bằng SĐT, kiểm tra đăng ký, lỗi chung chung).
 *
 * @package Dravit
 * @version 9.9.0
 */
declare(strict_types=1);

use Dravit\Woo\AccountIdentity;

defined('ABSPATH') || exit;

$can_register = 'yes' === get_option('woocommerce_enable_myaccount_registration');

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Chỉ đọc lại giá trị đã nhập để hiển thị khi lỗi; WC đã xử lý nonce.
$posted = static function (string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
};
$active = $can_register && isset($_POST['register']) ? 'register' : 'login';
// phpcs:enable

get_template_part('template-parts/account/shell-open', null, [
    'title' => $can_register ? 'Đăng nhập hoặc tạo tài khoản' : 'Đăng nhập tài khoản',
    'lead'  => 'Đăng nhập bằng số điện thoại hoặc email đã dùng khi đăng ký.',
    'crumb' => 'Tài khoản',
]);
?>
<div class="drv-auth-card" data-drv-auth data-active="<?php echo esc_attr($active); ?>">
    <?php if ($can_register) : ?>
        <div class="drv-auth-tabs" role="tablist" aria-label="Đăng nhập hoặc tạo tài khoản" hidden data-drv-auth-tabs>
            <button type="button" class="drv-auth-tab" role="tab" id="drv-tab-login" aria-controls="dang-nhap" aria-selected="<?php echo $active === 'login' ? 'true' : 'false'; ?>" data-drv-auth-tab="login">
                Đăng nhập
            </button>
            <button type="button" class="drv-auth-tab" role="tab" id="drv-tab-register" aria-controls="dang-ky" aria-selected="<?php echo $active === 'register' ? 'true' : 'false'; ?>" data-drv-auth-tab="register">
                Tạo tài khoản
            </button>
        </div>
    <?php endif; ?>

    <div class="drv-auth-notices">
        <?php do_action('woocommerce_before_customer_login_form'); ?>
    </div>

    <section class="drv-auth-panel" id="dang-nhap" role="tabpanel" aria-labelledby="drv-tab-login" data-drv-auth-panel="login">
        <h2 class="drv-auth-panel__title">Đăng nhập</h2>
        <form class="woocommerce-form woocommerce-form-login login drv-auth-form" method="post" novalidate>
            <?php do_action('woocommerce_login_form_start'); ?>

            <div class="drv-field">
                <label class="drv-label" for="username">Số điện thoại hoặc email <span class="drv-label__req" aria-hidden="true">*</span></label>
                <input type="text" class="drv-input woocommerce-Input input-text" name="username" id="username"
                       autocomplete="username" autocapitalize="off" spellcheck="false" required aria-required="true"
                       placeholder="0912 345 678 hoặc ten@email.com"
                       value="<?php echo esc_attr($posted('username')); ?>">
            </div>

            <div class="drv-field">
                <div class="drv-auth-label-row">
                    <label class="drv-label" for="password">Mật khẩu <span class="drv-label__req" aria-hidden="true">*</span></label>
                    <a class="drv-auth-link" href="<?php echo esc_url(untrailingslashit(wp_lostpassword_url())); ?>">Quên mật khẩu?</a>
                </div>
                <div class="drv-auth-password">
                    <input class="drv-input woocommerce-Input input-text" type="password" name="password" id="password"
                           autocomplete="current-password" required aria-required="true">
                    <button type="button" class="drv-auth-password__toggle" data-drv-password-toggle aria-controls="password" aria-pressed="false" hidden>
                        <i class="ph ph-eye" aria-hidden="true"></i><span>Hiện</span>
                    </button>
                </div>
            </div>

            <?php do_action('woocommerce_login_form'); ?>

            <label class="drv-check drv-auth-remember">
                <input name="rememberme" type="checkbox" id="rememberme" value="forever">
                <span>Ghi nhớ đăng nhập trên thiết bị này</span>
            </label>

            <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
            <button type="submit" class="drv-btn drv-btn--primary drv-btn--md drv-btn--full woocommerce-form-login__submit" name="login" value="Đăng nhập">
                <span>Đăng nhập</span>
                <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
            </button>

            <?php do_action('woocommerce_login_form_end'); ?>
        </form>
        <?php if ($can_register) : ?>
            <p class="drv-auth-switch">Chưa có tài khoản? <a href="#dang-ky" data-drv-auth-tab="register">Tạo tài khoản trong 1 phút</a></p>
        <?php endif; ?>
    </section>

    <?php if ($can_register) : ?>
        <section class="drv-auth-panel" id="dang-ky" role="tabpanel" aria-labelledby="drv-tab-register" data-drv-auth-panel="register">
            <h2 class="drv-auth-panel__title">Tạo tài khoản</h2>
            <form method="post" class="woocommerce-form woocommerce-form-register register drv-auth-form" novalidate <?php do_action('woocommerce_register_form_tag'); ?>>
                <?php do_action('woocommerce_register_form_start'); ?>

                <div class="drv-field">
                    <label class="drv-label" for="drv_reg_name">Họ và tên <span class="drv-label__req" aria-hidden="true">*</span></label>
                    <input type="text" class="drv-input input-text" name="drv_reg_name" id="drv_reg_name" autocomplete="name"
                           maxlength="<?php echo esc_attr((string) AccountIdentity::NAME_MAX); ?>" required aria-required="true"
                           placeholder="Ví dụ: Nguyễn Văn An" value="<?php echo esc_attr($posted('drv_reg_name')); ?>">
                </div>

                <div class="drv-auth-grid">
                    <div class="drv-field">
                        <label class="drv-label" for="drv_reg_phone">Số điện thoại <span class="drv-label__req" aria-hidden="true">*</span></label>
                        <input type="tel" class="drv-input input-text u-tabular" name="drv_reg_phone" id="drv_reg_phone" autocomplete="tel"
                               inputmode="tel" required aria-required="true" aria-describedby="drv_reg_phone_hint"
                               placeholder="0912 345 678" value="<?php echo esc_attr($posted('drv_reg_phone')); ?>">
                        <span class="drv-auth-hint" id="drv_reg_phone_hint">Dùng để đăng nhập và nhận cuộc gọi xác nhận đơn.</span>
                    </div>
                    <div class="drv-field">
                        <label class="drv-label" for="reg_email">Email <span class="drv-label__req" aria-hidden="true">*</span></label>
                        <input type="email" class="drv-input woocommerce-Input input-text" name="email" id="reg_email" autocomplete="email"
                               autocapitalize="off" spellcheck="false" required aria-required="true" aria-describedby="reg_email_hint"
                               placeholder="ten@email.com" value="<?php echo esc_attr($posted('email')); ?>">
                        <span class="drv-auth-hint" id="reg_email_hint">Nhận thông báo đơn hàng và link đặt lại mật khẩu.</span>
                    </div>
                </div>

                <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
                    <div class="drv-field">
                        <label class="drv-label" for="reg_username">Tên đăng nhập <span class="drv-label__req" aria-hidden="true">*</span></label>
                        <input type="text" class="drv-input woocommerce-Input input-text" name="username" id="reg_username" autocomplete="username" required value="<?php echo esc_attr($posted('username')); ?>">
                    </div>
                <?php endif; ?>

                <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
                    <div class="drv-field">
                        <label class="drv-label" for="reg_password">Mật khẩu <span class="drv-label__req" aria-hidden="true">*</span></label>
                        <div class="drv-auth-password">
                            <input type="password" class="drv-input woocommerce-Input input-text" name="password" id="reg_password"
                                   autocomplete="new-password" minlength="<?php echo esc_attr((string) AccountIdentity::PASSWORD_MIN); ?>"
                                   required aria-required="true" aria-describedby="reg_password_hint">
                            <button type="button" class="drv-auth-password__toggle" data-drv-password-toggle aria-controls="reg_password" aria-pressed="false" hidden>
                                <i class="ph ph-eye" aria-hidden="true"></i><span>Hiện</span>
                            </button>
                        </div>
                        <span class="drv-auth-hint" id="reg_password_hint">Tối thiểu <?php echo esc_html((string) AccountIdentity::PASSWORD_MIN); ?> ký tự, có cả chữ và số.</span>
                    </div>
                <?php else : ?>
                    <p class="drv-auth-hint">Mật khẩu đăng nhập sẽ được gửi tới email của Quý khách.</p>
                <?php endif; ?>

                <div class="drv-hp" aria-hidden="true">
                    <label for="<?php echo esc_attr(AccountIdentity::HONEYPOT); ?>">Website công ty</label>
                    <input type="text" name="<?php echo esc_attr(AccountIdentity::HONEYPOT); ?>" id="<?php echo esc_attr(AccountIdentity::HONEYPOT); ?>" tabindex="-1" autocomplete="off" value="">
                </div>

                <?php do_action('woocommerce_register_form'); ?>

                <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
                <button type="submit" class="drv-btn drv-btn--primary drv-btn--md drv-btn--full woocommerce-form-register__submit" name="register" value="Tạo tài khoản">
                    <span>Tạo tài khoản</span>
                    <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                </button>

                <?php do_action('woocommerce_register_form_end'); ?>
            </form>
            <p class="drv-auth-switch">Đã có tài khoản? <a href="#dang-nhap" data-drv-auth-tab="login">Đăng nhập</a></p>
        </section>
    <?php endif; ?>
</div>
<?php
get_template_part('template-parts/account/shell-close');

do_action('woocommerce_after_customer_login_form');
