<?php
/**
 * Khối thanh toán theo trạng thái (docs/18 §4.3 ma trận A/B/C/D, D-22, C-5, C-13).
 *
 * - A (`cho-xac-nhan`): KHÔNG xuất ảnh QR, KHÔNG xuất số tài khoản lên DOM.
 * - B (`bacs` + `on-hold`): slot `qr` → hook `drv_order_payment_box` (Woo\BankQR sẽ gắn ở phase HPOS Ops & VietQR).
 * - C: COD → số tiền chuẩn bị khi nhận; BACS → đã nhận thanh toán.
 * - D: thông báo hủy.
 */
declare(strict_types=1);

use Dravit\Woo\OrderView;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$view  = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
$order = $args['order'] ?? null;
if ($view === []) {
    return;
}

$state = (string) $view['state'];
$slot  = (string) $view['payment_slot'];
$phone = (string) $view['phone'];
$total = drv_format_vnd((int) $view['total']);
$due   = max(1, (int) drv_setting('order.transfer_due_days', 3));
?>
<div class="drv-order-notice drv-order-notice--<?php echo esc_attr($state); ?>" id="drv-order-payment" role="status">
    <?php if ($state === OrderView::STATE_PENDING) : ?>
        <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-phone-call"></i></div>
        <div class="l-stack l-stack--2xs">
            <h2 class="drv-order-notice__title">Chưa cần thanh toán lúc này</h2>
            <p class="drv-order-notice__text">
                Đơn hàng #<?php echo esc_html((string) $view['number']); ?> đã được giữ tồn kho. Nhân viên điều vận DRAVIT sẽ gọi số
                <strong class="u-tabular"><?php echo esc_html($phone); ?></strong> trong giờ làm việc để xác nhận lô gạch và báo phí vận chuyển tốt nhất.
                <?php if ($view['payment'] === 'bacs') : ?>
                    Thông tin chuyển khoản và mã VietQR sẽ mở tại trang này và gửi qua Email/Zalo ngay sau khi chốt phí giao.
                <?php else : ?>
                    Quý khách thanh toán tiền mặt cho nhân viên giao hàng sau khi đồng kiểm số thùng và mã lô.
                <?php endif; ?>
            </p>
        </div>
    <?php elseif ($slot === 'qr') : ?>
        <?php
        ob_start();
        /**
         * Phase HPOS Ops & VietQR: `Woo\BankQR` gắn vào đây (chỉ khi BACS + on-hold — `OrderView::paymentSlot() === 'qr'`).
         *
         * @param \WC_Order            $order
         * @param array<string, mixed> $view
         */
        do_action('drv_order_payment_box', $order, $view);
        $qr_html = (string) ob_get_clean();
        ?>
        <?php if ($qr_html !== '') : ?>
            <?php echo $qr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Callback tự escape. ?>
        <?php else : ?>
            <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-receipt"></i></div>
            <div class="l-stack l-stack--2xs">
                <h2 class="drv-order-notice__title">Chờ chuyển khoản <?php echo esc_html($total); ?></h2>
                <p class="drv-order-notice__text">
                    Nhân viên DRAVIT đã gửi thông tin tài khoản qua Zalo/điện thoại khi xác nhận đơn. Nội dung chuyển khoản:
                    <strong class="u-tabular"><?php echo esc_html((string) $view['transfer_ref']); ?></strong>
                    <?php drv_ui('copy-inline', ['value' => (string) $view['transfer_ref'], 'aria_label' => 'Sao chép nội dung chuyển khoản']); ?>.
                    Đơn được giữ <?php echo esc_html((string) $due); ?> ngày kể từ khi xác nhận. Sau khi chuyển khoản, kế toán DRAVIT kiểm tra và cập nhật trạng thái trong giờ làm việc; Quý khách có thể gửi ảnh biên lai qua Zalo để được ưu tiên xếp xe.
                </p>
            </div>
        <?php endif; ?>
    <?php elseif ($slot === 'paid') : ?>
        <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-check-circle"></i></div>
        <div class="l-stack l-stack--2xs">
            <h2 class="drv-order-notice__title">Đã nhận thanh toán chuyển khoản <?php echo esc_html($total); ?></h2>
            <p class="drv-order-notice__text">Kho DRAVIT đang soạn hàng theo đúng mã lô đã xác nhận và sẽ gọi trước khi xe xuất phát.</p>
        </div>
    <?php elseif ($slot === 'cod') : ?>
        <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-truck"></i></div>
        <div class="l-stack l-stack--2xs">
            <h2 class="drv-order-notice__title">Chuẩn bị <?php echo esc_html($total); ?> khi nhận hàng</h2>
            <p class="drv-order-notice__text">Tổng đã gồm phí vận chuyển. Vui lòng đồng kiểm số thùng và mã lô trước khi thanh toán cho nhân viên giao hàng.</p>
        </div>
    <?php else : ?>
        <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-x-circle"></i></div>
        <div class="l-stack l-stack--2xs">
            <h2 class="drv-order-notice__title">Đơn hàng đã hủy</h2>
            <p class="drv-order-notice__text">Không cần thanh toán cho đơn này. Liên hệ Hotline hoặc Zalo bên cạnh để được hỗ trợ đặt lại.</p>
        </div>
    <?php endif; ?>
</div>
