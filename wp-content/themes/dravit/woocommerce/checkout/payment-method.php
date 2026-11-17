<?php
/**
 * Thẻ phương thức thanh toán `.drv-pay-card` (05-checkout.html §04, docs/04 §8, D-22).
 * BACS: KHÔNG hiện số tài khoản / QR ở bước này (VietQR chỉ mở khi đơn đã chốt phí ship, trạng thái on-hold).
 *
 * @package Dravit
 * @version 3.5.0
 *
 * @var \WC_Payment_Gateway $gateway
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$gid     = (string) $gateway->id;
$is_bacs = $gid === 'bacs';
$is_cod  = $gid === 'cod';
$title   = $is_bacs ? 'Chuyển khoản ngân hàng qua mã VietQR' : ($is_cod ? 'Thanh toán khi nhận hàng (COD)' : $gateway->get_title());
$icon    = $is_bacs ? 'qr-code' : ($is_cod ? 'hand-coins' : 'credit-card');
?>
<label class="drv-pay-card wc_payment_method payment_method_<?php echo esc_attr($gid); ?>" for="payment_method_<?php echo esc_attr($gid); ?>">
    <span class="drv-pay-card__head">
        <span class="drv-pay-card__radio-label">
            <input id="payment_method_<?php echo esc_attr($gid); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr($gid); ?>" <?php checked($gateway->chosen, true); ?> data-order_button_text="<?php echo esc_attr((string) $gateway->order_button_text); ?>">
            <?php drv_ui('icon', ['name' => $icon]); ?>
            <span><?php echo esc_html($title); ?></span>
        </span>
        <?php if ($is_cod) : ?>
            <span class="drv-badge drv-badge--outline">Kiểm hàng rồi trả tiền</span>
        <?php endif; ?>
    </span>

    <?php if ($is_bacs) : ?>
        <span class="drv-pay-card__desc">
            Quét mã QR trên ứng dụng ngân hàng, số tiền và nội dung chuyển khoản <strong>DRV{mã đơn}</strong> được điền sẵn.
        </span>
        <span class="drv-pay-card__detail payment_box payment_method_bacs">
            <span class="l-cluster l-cluster--xs u-text-navy">
                <?php drv_ui('icon', ['name' => 'check-circle', 'weight' => 'fill', 'size' => 'xs']); ?>
                <strong>Bạn chưa cần chuyển khoản ở bước này.</strong>
            </span>
            <span class="u-text-muted">Sau khi nhân viên gọi xác nhận lô hàng và phí vận chuyển, mã VietQR với tổng tiền cuối cùng sẽ hiện ở trang đơn hàng và gửi qua Email/Zalo.</span>
        </span>
    <?php elseif ($is_cod) : ?>
        <span class="drv-pay-card__desc">
            Thanh toán tiền mặt hoặc chuyển khoản cho tài xế sau khi đồng kiểm số thùng và mã lô tại công trình.
        </span>
        <span class="drv-pay-card__detail payment_box payment_method_cod">
            <span class="u-text-muted">Tổng tiền thu khi giao (gồm phí vận chuyển) được xác nhận qua điện thoại trước khi xuất xe.</span>
        </span>
    <?php elseif ($gateway->has_fields() || $gateway->get_description()) : ?>
        <span class="drv-pay-card__detail payment_box payment_method_<?php echo esc_attr($gid); ?>">
            <?php $gateway->payment_fields(); ?>
        </span>
    <?php endif; ?>
</label>
