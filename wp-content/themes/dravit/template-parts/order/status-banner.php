<?php
/**
 * Hero trạng thái đơn + timeline 4 bước (docs/layout/06-order-received.html, docs/04 §9, docs/18 §4.3).
 * Trạng thái render 100% từ `$order->get_status()` (STRIP-04: không có thanh demo đổi trạng thái).
 *
 * Desktop 2 cột: trái = trạng thái · mã đơn · bước tiếp theo; phải = lưới 4 ô dữ kiện đơn (ron 1px).
 * Mobile: xếp dọc, lưới dữ kiện 2×2, timeline ngang 4 nút (ẩn dòng phụ).
 */
declare(strict_types=1);

use Dravit\Woo\OrderView;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$view = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
if ($view === []) {
    return;
}

$state    = (string) $view['state'];
$heading  = (array) $view['heading'];
$number   = (string) $view['number'];
$phone    = (string) $view['phone'];
$sla      = (string) drv_setting('order.sla_text', '');
$created  = $view['created'] instanceof \WC_DateTime ? $view['created'] : null;
$tone     = (string) ($heading['tone'] ?? 'amber');
$timeline = (array) $view['timeline'];
$icon     = match ($state) {
    OrderView::STATE_CANCELLED => 'x-circle',
    OrderView::STATE_TRANSFER  => 'qr-code',
    default                    => 'seal-check',
};

// Tỉ lệ đường tiến trình giữa nút đầu và nút cuối (0 → 100%).
$active_idx = 0;
foreach ($timeline as $i => $step) {
    if (in_array((string) $step['state'], ['done', 'active'], true)) {
        $active_idx = (int) $i;
    }
}
$progress = count($timeline) > 1 ? round($active_idx / (count($timeline) - 1) * 100, 2) : 0;

$has_fee     = (bool) $view['has_ship_fee'];
$total_label = $has_fee ? 'Tổng thanh toán' : 'Tạm tính';
$facts       = [
    ['label' => 'Ngày đặt', 'value' => $created ? $created->date_i18n('d/m/Y · H:i') : '—', 'icon' => 'calendar-blank'],
    ['label' => 'Thanh toán', 'value' => (string) $view['payment'] === 'cod' ? 'COD - trả khi nhận hàng' : 'Chuyển khoản VietQR', 'icon' => 'credit-card'],
    ['label' => $total_label, 'value' => drv_format_vnd((int) $view['total']), 'icon' => 'receipt', 'strong' => true, 'note' => $has_fee ? 'Đã gồm phí vận chuyển' : 'Chưa gồm phí ship'],
    ['label' => 'Giao đến', 'value' => (string) $view['province'] !== '' ? (string) $view['province'] : '—', 'icon' => 'truck'],
];

$lead = match ($state) {
    OrderView::STATE_TRANSFER  => sprintf('Phí vận chuyển đã được cập nhật. Vui lòng chuyển khoản %s theo hướng dẫn bên dưới.', drv_format_vnd((int) $view['total'])),
    OrderView::STATE_FULFIL    => $view['payment'] === 'cod'
        ? sprintf('Đơn đã chốt phí vận chuyển. Vui lòng chuẩn bị %s tiền mặt khi nhận gạch.', drv_format_vnd((int) $view['total']))
        : 'DRAVIT đã nhận thanh toán và đang soạn hàng, đóng đai, xếp xe giao công trình.',
    OrderView::STATE_CANCELLED => 'Đơn hàng này đã hủy và tồn kho đã được hoàn lại. Liên hệ DRAVIT nếu Quý khách muốn đặt lại.',
    default                    => '',
};
?>
<div class="drv-order-banner drv-order-banner--<?php echo esc_attr($state); ?>" role="region" aria-label="Tóm tắt trạng thái đơn hàng">
    <div class="drv-order-banner__top">
        <div class="drv-order-banner__main">
            <div class="drv-order-banner__icon" aria-hidden="true">
                <i class="ph-fill ph-<?php echo esc_attr($icon); ?>"></i>
            </div>
            <div class="drv-order-banner__body">
                <div class="drv-order-banner__status-row">
                    <span class="drv-order-banner__success-title"><?php echo esc_html((string) $heading['title']); ?></span>
                    <span class="drv-badge drv-badge--<?php echo esc_attr($tone); ?>" data-order-status-badge><?php echo esc_html((string) $heading['badge']); ?></span>
                </div>
                <h1 class="drv-order-banner__id">
                    <span>Đơn hàng <span class="u-tabular">#<?php echo esc_html($number); ?></span></span>
                    <?php drv_ui('copy-inline', ['value' => $number, 'aria_label' => 'Sao chép mã đơn hàng']); ?>
                </h1>
                <?php if ($state === OrderView::STATE_PENDING) : ?>
                    <p class="drv-order-banner__sub">
                        Đơn hàng đã được giữ tồn kho. Nhân viên DRAVIT sẽ gọi số
                        <strong class="u-text-navy u-tabular"><?php echo esc_html($phone !== '' ? $phone : 'Quý khách đã để lại'); ?></strong>
                        để xác nhận lô gạch và báo phí vận chuyển trước khi thanh toán.
                    </p>
                    <?php if ($sla !== '') : ?>
                        <p class="drv-order-banner__sla">
                            <i class="ph ph-clock" aria-hidden="true"></i>
                            <span><?php echo esc_html($sla); ?></span>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="drv-order-banner__sub"><?php echo esc_html($lead); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <dl class="drv-order-facts">
            <?php foreach ($facts as $fact) : ?>
                <div class="drv-order-fact">
                    <dt class="drv-order-fact__label">
                        <i class="ph ph-<?php echo esc_attr($fact['icon']); ?>" aria-hidden="true"></i>
                        <?php echo esc_html($fact['label']); ?>
                    </dt>
                    <dd class="drv-order-fact__val<?php echo !empty($fact['strong']) ? ' drv-order-fact__val--strong' : ''; ?>"><?php echo esc_html($fact['value']); ?></dd>
                    <?php if (!empty($fact['note'])) : ?>
                        <dd class="drv-order-fact__note"><?php echo esc_html((string) $fact['note']); ?></dd>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>

    <nav class="drv-timeline" aria-label="Tiến trình xử lý đơn hàng">
        <ol class="drv-timeline__track" style="--drv-progress: <?php echo esc_attr((string) $progress); ?>%">
            <?php foreach ($timeline as $step) :
                $st = (string) $step['state'];
                ?>
                <li class="drv-timeline__step is-<?php echo esc_attr($st === 'cancelled' ? 'pending is-cancelled' : $st); ?>"<?php echo $st === 'active' ? ' aria-current="step"' : ''; ?>>
                    <div class="drv-timeline__node">
                        <i class="ph-bold ph-<?php echo esc_attr($st === 'cancelled' ? 'x' : (string) $step['icon']); ?>" aria-hidden="true"></i>
                    </div>
                    <div class="drv-timeline__label">
                        <span class="drv-timeline__title"><?php echo esc_html((string) $step['title']); ?></span>
                        <span class="drv-timeline__sub"><?php echo esc_html((string) $step['sub']); ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>
