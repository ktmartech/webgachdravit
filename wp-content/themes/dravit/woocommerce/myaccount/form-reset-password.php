<?php
/**
 * Đặt mật khẩu mới từ link email (quy tắc mật khẩu kiểm tra phía server: `AccountAuth::validate_password_reset`).
 *
 * @package Dravit
 * @version 9.2.0
 *
 * @var array{key: string, login: string} $args
 */
declare(strict_types=1);

use Dravit\Woo\AccountIdentity;

defined('ABSPATH') || exit;

get_template_part('template-parts/account/shell-open', null, [
    'title' => 'Đặt mật khẩu mới',
    'lead'  => 'Tạo mật khẩu mới cho tài khoản DRAVIT của Quý khách.',
    'crumb' => 'Đặt mật khẩu mới',
]);
?>
<div class="drv-auth-card">
    <div class="drv-auth-notices">
        <?php do_action('woocommerce_before_reset_password_form'); ?>
    </div>

    <form method="post" class="woocommerce-ResetPassword lost_reset_password drv-auth-form" novalidate>
        <div class="drv-field">
            <label class="drv-label" for="password_1">Mật khẩu mới <span class="drv-label__req" aria-hidden="true">*</span></label>
            <div class="drv-auth-password">
                <input type="password" class="drv-input woocommerce-Input input-text" name="password_1" id="password_1"
                       autocomplete="new-password" minlength="<?php echo esc_attr((string) AccountIdentity::PASSWORD_MIN); ?>"
                       required aria-required="true" aria-describedby="password_1_hint">
                <button type="button" class="drv-auth-password__toggle" data-drv-password-toggle aria-controls="password_1" aria-pressed="false" hidden>
                    <i class="ph ph-eye" aria-hidden="true"></i><span>Hiện</span>
                </button>
            </div>
            <span class="drv-auth-hint" id="password_1_hint">Tối thiểu <?php echo esc_html((string) AccountIdentity::PASSWORD_MIN); ?> ký tự, có cả chữ và số.</span>
        </div>
        <div class="drv-field">
            <label class="drv-label" for="password_2">Nhập lại mật khẩu mới <span class="drv-label__req" aria-hidden="true">*</span></label>
            <input type="password" class="drv-input woocommerce-Input input-text" name="password_2" id="password_2"
                   autocomplete="new-password" required aria-required="true">
        </div>

        <input type="hidden" name="reset_key" value="<?php echo esc_attr((string) $args['key']); ?>">
        <input type="hidden" name="reset_login" value="<?php echo esc_attr((string) $args['login']); ?>">

        <?php do_action('woocommerce_resetpassword_form'); ?>

        <input type="hidden" name="wc_reset_password" value="true">
        <?php wp_nonce_field('reset_password', 'woocommerce-reset-password-nonce'); ?>
        <button type="submit" class="drv-btn drv-btn--primary drv-btn--md drv-btn--full woocommerce-Button" value="Lưu mật khẩu mới">
            <span>Lưu mật khẩu mới</span>
        </button>
    </form>
</div>
<?php
get_template_part('template-parts/account/shell-close');

do_action('woocommerce_after_reset_password_form');
