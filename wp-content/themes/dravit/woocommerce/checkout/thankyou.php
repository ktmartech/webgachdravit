<?php
/**
 * Trang Đặt hàng thành công `/thanh-toan/dat-hang-thanh-cong/{id}?key=...` (docs/layout/06-order-received.html,
 * docs/04 §9, docs/18 §4.3, docs/16 STRIP-04/STRIP-22).
 *
 * Trạng thái hiển thị render 100% từ `$order->get_status()` qua `Woo\OrderView` (HPOS CRUD):
 * A Chờ xác nhận · B Chờ chuyển khoản (BACS) · C Đang xử lý/Hoàn thành · D Hủy.
 * WooCommerce đã xác thực `order key` trước khi nạp template này.
 *
 * @package Dravit
 * @version 11.2.0
 *
 * @var \WC_Order|false $order
 */
declare(strict_types=1);

use Dravit\Woo\OrderView;

defined('ABSPATH') || exit;

$home    = untrailingslashit(home_url('/'));
$hotline = (string) drv_setting('contact.hotline', '');
?>
<section class="l-section l-section--white u-bd-b drv-checkout-hero-sec">
    <div class="l-container l-stack l-stack--md">
        <?php
        drv_ui('breadcrumb', [
            'items' => [
                ['label' => 'Trang chủ', 'url' => $home],
                ['label' => 'Hoàn tất đặt hàng'],
            ],
        ]);

if (!$order instanceof \WC_Order) :
    ?>
        <div class="drv-order-notice drv-order-notice--pending" role="status">
            <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-seal-check"></i></div>
            <div class="l-stack l-stack--2xs">
                <h1 class="drv-order-notice__title">Cảm ơn Quý khách. Đơn hàng đã được tiếp nhận.</h1>
                <p class="drv-order-notice__text">Nhân viên <?php echo esc_html((string) drv_setting('company.brand_name', '')); ?> sẽ liên hệ xác nhận lô gạch và phí vận chuyển trong giờ làm việc<?php echo $hotline !== '' ? ' · Hotline ' . esc_html($hotline) : ''; ?>.</p>
            </div>
        </div>
    </div>
</section>
    <?php
    return;
endif;

do_action('woocommerce_before_thankyou', $order->get_id());

if ($order->has_status('failed')) :
    ?>
        <div class="drv-order-notice drv-order-notice--cancelled" role="alert">
            <div class="drv-order-notice__icon" aria-hidden="true"><i class="ph ph-warning-circle"></i></div>
            <div class="l-stack l-stack--xs">
                <h1 class="drv-order-notice__title">Không thể hoàn tất đơn hàng #<?php echo esc_html($order->get_order_number()); ?></h1>
                <p class="drv-order-notice__text">Giao dịch bị từ chối. Quý khách vui lòng thử lại hoặc gọi Hotline để được hỗ trợ đặt hàng.</p>
                <div class="l-cluster l-cluster--xs">
                    <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="drv-btn drv-btn--primary drv-btn--sm">Thử thanh toán lại</a>
                    <a href="<?php echo esc_url(untrailingslashit((string) wc_get_cart_url())); ?>" class="drv-btn drv-btn--outline drv-btn--sm">Về giỏ hàng</a>
                </div>
            </div>
        </div>
    </div>
</section>
    <?php
    return;
endif;

$view = OrderView::fromOrder($order);

// Timeline 4 bước trong banner là chỉ báo tiến trình duy nhất (không lặp thanh 3 bước giỏ/checkout).
get_template_part('template-parts/order/status-banner', null, ['view' => $view]);
?>
    </div>
</section>

<section class="l-section" aria-label="Chi tiết đơn hàng">
    <div class="l-container">
        <div class="drv-checkout-layout">
            <div class="drv-checkout-main l-stack l-stack--lg">
                <?php
                get_template_part('template-parts/order/payment-box', null, ['view' => $view, 'order' => $order]);
                get_template_part('template-parts/order/items-ledger', null, ['view' => $view]);
                ?>

                <div class="drv-order-info-card">
                    <div class="l-cluster l-cluster--between u-bd-bottom drv-order-info-card__head">
                        <h2 class="u-text-base u-bold u-text-navy">Thông tin nhận hàng tại công trình</h2>
                    </div>
                    <div class="drv-order-info-grid">
                        <div class="l-stack l-stack--xs">
                            <span class="u-text-xs u-text-muted">Người nhận hàng:</span>
                            <strong class="u-text-sm u-text-navy"><?php echo esc_html((string) $view['name']); ?></strong>
                            <span class="u-text-xs u-tabular">Điện thoại: <strong><?php echo esc_html((string) $view['phone']); ?></strong></span>
                            <?php if ((string) $view['email'] !== '') : ?>
                                <span class="u-text-xs u-text-muted">Email: <?php echo esc_html((string) $view['email']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="l-stack l-stack--xs">
                            <span class="u-text-xs u-text-muted">Địa chỉ giao gạch:</span>
                            <strong class="u-text-sm u-text-navy"><?php echo esc_html((string) $view['address']); ?></strong>
                            <span class="u-text-xs u-text-muted"><?php echo esc_html(implode(', ', array_filter([(string) $view['ward'], (string) $view['province']]))); ?></span>
                            <?php if ((string) $view['note'] !== '') : ?>
                                <span class="u-text-xs u-text-moss">Ghi chú: <strong><?php echo esc_html((string) $view['note']); ?></strong></span>
                            <?php endif; ?>
                            <?php if ((string) $view['ship_vehicle'] !== '') : ?>
                                <span class="u-text-xs">Phương tiện giao: <strong><?php echo esc_html((string) $view['ship_vehicle']); ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($view['vat']['required']) : ?>
                        <div class="u-bd-top l-stack l-stack--2xs drv-order-info-card__vat">
                            <span class="u-text-xs u-bold u-text-navy">Thông tin xuất hóa đơn VAT:</span>
                            <p class="u-text-xs u-text-muted">
                                <strong><?php echo esc_html((string) $view['vat']['company']); ?></strong>
                                · MST: <strong class="u-tabular"><?php echo esc_html((string) $view['vat']['tax_id']); ?></strong>
                                · <?php echo esc_html((string) $view['vat']['address']); ?>
                                <?php echo (string) $view['vat']['email'] !== '' ? ' · ' . esc_html((string) $view['vat']['email']) : ''; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php get_template_part('template-parts/order/summary', null, ['view' => $view]); ?>
        </div>
    </div>
</section>
<?php
// Bảng chi tiết đơn mặc định của WC trùng nội dung với template này → gỡ, vẫn giữ hook cho analytics/pixel.
remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id());
do_action('woocommerce_thankyou', $order->get_id());
