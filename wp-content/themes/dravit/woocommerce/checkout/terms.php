<?php
/**
 * Ô đồng ý điều khoản (docs/04 §5.1, C-9): bắt buộc, KHÔNG tick sẵn khi tải trang, link `/dieu-khoan`.
 *
 * @package Dravit
 * @version 3.4.0
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!apply_filters('woocommerce_checkout_show_terms', true) || !function_exists('wc_terms_and_conditions_checkbox_enabled')) {
    return;
}

do_action('woocommerce_checkout_before_terms_and_conditions');
?>
<div class="woocommerce-terms-and-conditions-wrapper drv-field">
    <?php do_action('woocommerce_checkout_terms_and_conditions'); ?>
    <?php if (wc_terms_and_conditions_checkbox_enabled()) :
        $terms_id  = wc_terms_and_conditions_page_id();
        $terms_url = $terms_id > 0 ? untrailingslashit((string) get_permalink($terms_id)) : untrailingslashit(home_url('/dieu-khoan'));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Chỉ giữ trạng thái tick khi form lỗi (không JS).
        $checked = !empty($_POST['terms']);
        ?>
        <p class="form-row validate-required" id="terms_field">
            <label class="drv-check woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="terms">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms" id="terms" <?php checked($checked); ?> required aria-required="true" aria-describedby="err-terms" data-error-empty="Vui lòng đồng ý Điều khoản mua hàng để tiếp tục.">
                <span class="u-text-xs">
                    Tôi đã kiểm tra thông tin đơn hàng và đồng ý với
                    <a href="<?php echo esc_url($terms_url); ?>" class="u-bold u-text-moss" target="_blank" rel="noopener">Điều khoản mua hàng</a>
                    của DRAVIT.
                </span>
            </label>
            <input type="hidden" name="terms-field" value="1">
            <span id="err-terms" class="drv-field__error" role="alert" hidden></span>
        </p>
    <?php endif; ?>
</div>
<?php
do_action('woocommerce_checkout_after_terms_and_conditions');
