<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * 3 thẻ cam kết kiểm hàng & vận chuyển dưới bảng kê giỏ hàng (mockup `04-cart.html`).
 * Số ngày giữ lô đọc từ `drv_settings.order.lot_hold_days` (mặc định 15) để sửa đồng bộ ở Admin.
 */
$hold_days = max(1, (int) drv_setting('order.lot_hold_days', 15));

$cards = [
    [
        'icon'  => 'seal-check',
        'title' => 'Trùng 100% Lô màu',
        'desc'  => 'Kiểm tra mã lô nung (Shade) & cỡ gạch (Caliber) trên từng vỏ thùng trước khi xuất kho.',
    ],
    [
        'icon'  => 'truck',
        'title' => 'Bảo hiểm 1 đổi 1 mẻ cạnh',
        'desc'  => 'Bọc góc chống sốc. Phát sinh nứt vỡ do vận chuyển được bù mới 100% miễn phí hoặc trừ hóa đơn.',
    ],
    [
        'icon'  => 'warehouse',
        'title' => sprintf('Giữ lô miễn phí %d ngày', $hold_days),
        'desc'  => 'Chốt giữ lô gạch đẹp ngay hôm nay và hẹn giao cuốn chiếu đúng tiến độ thợ cán nền.',
    ],
];
?>
<div class="drv-cart-guarantees" aria-label="Cam kết kiểm hàng và vận chuyển">
    <?php foreach ($cards as $card) : ?>
        <div class="drv-cart-guarantee-card">
            <div class="drv-cart-guarantee-card__head">
                <i class="ph-fill ph-<?php echo esc_attr($card['icon']); ?> drv-icon" aria-hidden="true"></i>
                <h3 class="drv-cart-guarantee-card__title"><?php echo esc_html($card['title']); ?></h3>
            </div>
            <p class="drv-cart-guarantee-card__desc"><?php echo esc_html($card['desc']); ?></p>
        </div>
    <?php endforeach; ?>
</div>
