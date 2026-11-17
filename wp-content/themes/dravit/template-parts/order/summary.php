<?php
/**
 * Cột phải (sticky): tóm tắt thanh toán + CTA Zalo / Hotline + cam kết (docs/layout/06-order-received.html).
 */
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;
use Dravit\Woo\OrderView;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$view = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
if ($view === []) {
    return;
}

$hotline   = (string) drv_setting('contact.hotline', '');
$tel       = (string) preg_replace('/[^0-9+]/', '', $hotline);
$zalo      = (string) preg_replace('/\D/', '', (string) drv_setting('contact.zalo', ''));
$line_cnt  = count((array) $view['lines']);
$ship_text = (string) drv_setting('order.shipping_label', 'Báo sau khi xác nhận đơn');
$kg        = (float) $view['total_kg'];
$cancelled = $view['state'] === OrderView::STATE_CANCELLED;
?>
<aside class="drv-summary-box drv-summary-box--sticky" aria-labelledby="order-summary-heading">
    <div class="l-cluster l-cluster--between u-bd-bottom drv-summary-box__head">
        <h2 id="order-summary-heading" class="u-text-base u-bold u-text-navy">Tóm tắt thanh toán</h2>
        <span class="drv-badge drv-badge--outline u-tabular">#<?php echo esc_html((string) $view['number']); ?></span>
    </div>

    <div class="l-stack l-stack--xs">
        <div class="drv-summary-row">
            <span class="u-text-muted">Tạm tính vật tư (<?php echo esc_html((string) $line_cnt); ?> mã):</span>
            <strong class="u-text-navy u-tabular"><?php echo esc_html(drv_format_vnd((int) $view['subtotal'])); ?></strong>
        </div>
        <div class="drv-summary-row">
            <span class="u-text-muted">Phí vận chuyển<?php echo $kg > 0 ? ' (' . esc_html(drv_format_decimal($kg, 0)) . ' kg)' : ''; ?>:</span>
            <?php if ($view['has_ship_fee']) : ?>
                <strong class="u-text-navy u-tabular"><?php echo esc_html(drv_format_vnd((int) $view['ship_total'])); ?></strong>
            <?php else : ?>
                <span class="u-text-moss u-bold"><?php echo esc_html($ship_text); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="drv-summary-row drv-summary-row--total">
        <div class="l-stack l-stack--2xs">
            <span><?php echo $view['has_ship_fee'] ? 'Tổng thanh toán:' : 'Tạm tính (chưa gồm phí ship):'; ?></span>
            <span class="u-text-xs u-text-muted">(Đã gồm thuế VAT)</span>
        </div>
        <span class="u-text-sale u-text-price u-tabular"><?php echo esc_html(drv_format_vnd((int) $view['total'])); ?></span>
    </div>

    <div class="l-stack l-stack--xs">
        <div class="drv-order-actions-grid">
            <?php if ($zalo !== '') : ?>
                <a href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" target="_blank" rel="noopener" class="drv-btn drv-btn--moss drv-btn--md">
                    <i class="ph-bold ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                    <span><?php echo $cancelled ? 'Zalo hỗ trợ đặt lại' : 'Chat Zalo'; ?></span>
                </a>
            <?php endif; ?>
            <?php if ($tel !== '') : ?>
                <a href="<?php echo esc_url('tel:' . $tel); ?>" class="drv-btn drv-btn--outline drv-btn--md u-tabular" aria-label="<?php echo esc_attr('Gọi Hotline ' . $hotline); ?>">
                    <i class="ph ph-phone-call drv-icon--xs" aria-hidden="true"></i>
                    <span>Hotline</span>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?php echo esc_url(MegaMenuRenderer::collection_url('gach')); ?>" class="drv-btn drv-btn--ghost drv-btn--sm drv-btn--full">
            <i class="ph ph-arrow-left drv-icon--xs" aria-hidden="true"></i>
            <span>Tiếp tục xem sản phẩm gạch</span>
        </a>
    </div>

    <div class="drv-checkout-guarantee" role="note" aria-label="Cam kết dịch vụ của DRAVIT">
        <div class="l-cluster l-cluster--xs">
            <i class="ph-fill ph-seal-check drv-icon--sm u-text-moss" aria-hidden="true"></i>
            <strong class="u-text-navy u-text-xs">Cam kết dịch vụ từ DRAVIT</strong>
        </div>
        <ul class="drv-checkout-guarantee__list">
            <li class="drv-checkout-guarantee__item">
                <i class="ph-bold ph-check drv-icon--xs u-text-moss" aria-hidden="true"></i>
                <span>Giao gạch cùng mã lô nung, kiểm tra trước khi xuất kho.</span>
            </li>
            <li class="drv-checkout-guarantee__item">
                <i class="ph-bold ph-check drv-icon--xs u-text-moss" aria-hidden="true"></i>
                <span>Báo phí vận chuyển trước, chỉ thanh toán khi Quý khách đồng ý.</span>
            </li>
            <li class="drv-checkout-guarantee__item">
                <i class="ph-bold ph-check drv-icon--xs u-text-moss" aria-hidden="true"></i>
                <span>Đồng kiểm số thùng và mã lô khi nhận hàng tại công trình.</span>
            </li>
        </ul>
    </div>
</aside>
