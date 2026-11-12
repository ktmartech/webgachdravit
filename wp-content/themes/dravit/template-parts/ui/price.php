<?php
declare(strict_types=1);

use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * UI Primitive hiển thị giá m² chính + giá viên phụ hoặc trạng thái "Liên hệ báo giá" (docs/04 §2.2, Test U-15).
 *
 * @var array<string, mixed> $args
 */
$product = $args['product'] ?? null;

if ($product instanceof \WC_Product) {
    $data = PriceState::toCardData($product);
} elseif (is_array($product)) {
    $data = $product;
} else {
    $data = $args;
}

$price_m2     = (int) round((float) ($data['price_m2'] ?? 0));
$old_price_m2 = (int) round((float) ($data['regular_price_m2'] ?? 0));
$price_piece  = (int) round((float) ($data['price_piece'] ?? 0));
$per_box      = max(1, (int) ($data['pieces_per_box'] ?? 3));
$m2_box       = (float) ($data['m2_per_box'] ?? 1.44);
$unit_label   = (string) ($data['unit_label'] ?? 'viên');
$stock_boxes  = isset($data['stock_boxes']) ? (int) $data['stock_boxes'] : null;
$in_stock     = array_key_exists('in_stock', $data) ? (bool) $data['in_stock'] : true;
$state        = (string) ($data['price_state'] ?? '');

$is_quote = ($state === PriceState::QUOTE_ONLY || $price_m2 <= 0);
$is_sale  = (!$is_quote && $old_price_m2 > $price_m2);
?>
<div class="drv-card__prices u-tabular">
    <?php if ($is_quote) : ?>
        <div class="drv-card__price-main">
            <span class="drv-card__price-contact">
                <?php drv_ui('icon', ['name' => 'tag', 'weight' => 'fill', 'size' => 'xs']); ?>
                <span>Liên hệ báo giá</span>
            </span>
        </div>
        <div class="drv-card__price-piece">
            1 thùng = <?php echo esc_html(drv_format_decimal($m2_box)); ?> m² (<?php echo esc_html((string) $per_box . ' ' . $unit_label); ?>)
            <?php if ($stock_boxes !== null && $stock_boxes > 0) : ?>
                · Còn <?php echo esc_html((string) $stock_boxes); ?> thùng
            <?php elseif ($in_stock) : ?>
                · Có sẵn mẫu
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="drv-card__price-main">
            <span class="drv-card__price-m2<?php echo $is_sale ? ' drv-card__price-m2--sale' : ''; ?>">
                <?php echo esc_html(drv_format_vnd($price_m2)); ?>/m²
            </span>
            <?php if ($is_sale) : ?>
                <del class="drv-card__price-old"><?php echo esc_html(drv_format_vnd($old_price_m2)); ?>/m²</del>
            <?php endif; ?>
        </div>
        <div class="drv-card__price-piece">
            ≈ <?php echo esc_html(drv_format_vnd($price_piece) . '/' . $unit_label); ?>
            <?php if ($stock_boxes !== null && $stock_boxes > 0) : ?>
                · Còn <?php echo esc_html((string) $stock_boxes); ?> thùng
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
