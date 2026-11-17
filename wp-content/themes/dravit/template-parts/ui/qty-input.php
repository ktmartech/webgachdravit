<?php
declare(strict_types=1);

use Dravit\Woo\CartPresenter;

defined('ABSPATH') || exit;

/**
 * UI primitive `<drv-qty-input>` (docs/15 §5.2, D-24): bộ chỉnh số lượng Giỏ hàng & Mini-cart.
 * Giá trị input luôn là SỐ NGUYÊN VIÊN; `- / +` nhảy theo `qty_step` (box = pieces_per_box, piece = 1).
 *
 * Props:
 * - `line`        Mảng `CartPresenter::line()` (ưu tiên) hoặc `product` (`WC_Product`) / mảng demo UI-kit.
 * - `cart_key`    Khóa dòng giỏ WooCommerce (bật đồng bộ Store API `update-item`).
 * - `product_id`, `value_pieces`, `input_name`, `aria_label`, `sku`.
 *
 * @var array<string, mixed> $args
 */
$line = isset($args['line']) && is_array($args['line']) ? $args['line'] : null;
$src  = $args['product'] ?? null;

if ($line === null && $src instanceof \WC_Product) {
    $line = CartPresenter::line(CartPresenter::snapshotFromProduct($src), max(1, (int) ($args['value_pieces'] ?? 1)));
}
if ($line === null) {
    // Dữ liệu demo UI-kit (mảng phẳng).
    $arr  = is_array($src) ? $src : $args;
    $ppb  = max(1, (int) ($arr['pieces_per_box'] ?? 3));
    $mode = (string) ($arr['round_mode'] ?? 'piece');
    $line = [
        'model'          => (string) ($arr['model'] ?? 'area_pack'),
        'price_piece'    => (int) ($arr['price_unit'] ?? 0),
        'area_piece'     => (float) ($arr['area_piece'] ?? 0.64),
        'pieces_per_box' => $ppb,
        'kg_per_piece'   => (float) ($arr['weight_piece'] ?? 14),
        'unit_label'     => (string) ($arr['unit_label'] ?? 'viên'),
        'round_mode'     => $mode,
        'qty_min'        => $mode === 'box' ? $ppb : 1,
        'qty_step'       => $mode === 'box' ? $ppb : 1,
        'max_stock'      => null,
    ];
}

$ppb        = max(1, (int) ($line['pieces_per_box'] ?? 1));
$round_mode = (string) ($line['round_mode'] ?? 'box');
$step       = max(1, (int) ($line['qty_step'] ?? ($round_mode === 'box' ? $ppb : 1)));
$qty_min    = max(1, (int) ($line['qty_min'] ?? $step));
$max_stock  = isset($line['max_stock']) && $line['max_stock'] !== null ? (int) $line['max_stock'] : null;
$unit_label = (string) ($line['unit_label'] ?? 'viên');
$value      = max($qty_min, (int) ($args['value_pieces'] ?? ($line['qty'] ?? $qty_min)));
$cart_key   = (string) ($args['cart_key'] ?? '');
$product_id = (int) ($args['product_id'] ?? 0);
$input_name = (string) ($args['input_name'] ?? 'quantity');
$sku        = trim((string) ($args['sku'] ?? ''));
$aria_label = (string) ($args['aria_label'] ?? ('Số lượng ' . $unit_label . ($sku !== '' ? ' ' . $sku : '')));
$input_id   = (string) ($args['input_id'] ?? ('drv-qty-' . substr(md5($cart_key !== '' ? $cart_key : uniqid('', true)), 0, 8)));
$show_box   = !empty($args['show_add_box']) && $ppb > 1;
$step_label = $round_mode === 'box' ? '1 thùng (' . $ppb . ' ' . $unit_label . ')' : '1 ' . $unit_label;
?>
<drv-qty-input
    <?php if ($cart_key !== '') : ?>data-cart-key="<?php echo esc_attr($cart_key); ?>"<?php endif; ?>
    <?php if ($product_id > 0) : ?>data-product-id="<?php echo esc_attr((string) $product_id); ?>"<?php endif; ?>
    data-model="<?php echo esc_attr((string) ($line['model'] ?? 'area_pack')); ?>"
    data-round-mode="<?php echo esc_attr($round_mode); ?>"
    data-pieces-per-box="<?php echo esc_attr((string) $ppb); ?>"
    data-area-piece="<?php echo esc_attr((string) ($line['area_piece'] ?? 0)); ?>"
    data-kg-per-piece="<?php echo esc_attr((string) ($line['kg_per_piece'] ?? 0)); ?>"
    data-price-unit="<?php echo esc_attr((string) ($line['price_piece'] ?? 0)); ?>"
    data-qty-min="<?php echo esc_attr((string) $qty_min); ?>"
    data-qty-step="<?php echo esc_attr((string) $step); ?>"
    <?php if ($max_stock !== null) : ?>data-max-stock="<?php echo esc_attr((string) $max_stock); ?>"<?php endif; ?>
    data-unit-label="<?php echo esc_attr($unit_label); ?>">
    <div class="drv-stepper drv-stepper--cart">
        <button type="button" class="drv-stepper__btn drv-stepper__btn--prev" data-qty-step="-1" aria-label="<?php echo esc_attr('Giảm ' . $step_label); ?>">
            <?php drv_ui('icon', ['name' => 'minus', 'size' => 'xs']); ?>
        </button>
        <input
            id="<?php echo esc_attr($input_id); ?>"
            type="number"
            name="<?php echo esc_attr($input_name); ?>"
            class="drv-stepper__input u-tabular"
            value="<?php echo esc_attr((string) $value); ?>"
            min="<?php echo esc_attr((string) $qty_min); ?>"
            step="<?php echo esc_attr((string) $step); ?>"
            <?php if ($max_stock !== null && $max_stock > 0) : ?>max="<?php echo esc_attr((string) $max_stock); ?>"<?php endif; ?>
            inputmode="numeric"
            aria-label="<?php echo esc_attr($aria_label); ?>">
        <button type="button" class="drv-stepper__btn drv-stepper__btn--next" data-qty-step="1" aria-label="<?php echo esc_attr('Tăng ' . $step_label); ?>">
            <?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?>
        </button>
    </div>
    <?php if ($show_box) : ?>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--soft" data-qty-add-box>
            <?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?>
            <span><?php echo esc_html('1 thùng (' . $ppb . ' ' . $unit_label . ')'); ?></span>
        </button>
    <?php endif; ?>
</drv-qty-input>
