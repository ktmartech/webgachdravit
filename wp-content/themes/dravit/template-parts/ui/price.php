<?php
declare(strict_types=1);

use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * UI Primitive hiển thị giá m² chính + giá viên phụ hoặc trạng thái "Liên hệ báo giá" (docs/04 §2.2, D-20).
 * Hỗ trợ `context = 'card' | 'single'`.
 *
 * @var array<string, mixed> $args
 */
$product = $args['product'] ?? null;
$context = (string) ($args['context'] ?? 'card');

if ($product instanceof \WC_Product) {
    $data     = PriceState::toCardData($product);
    $kg_box   = max(0.0, (float) ($product->get_meta('drv_kg_per_box', true) ?: $product->get_meta('drv_weight_kg_per_box', true)));
} elseif (is_array($product)) {
    $data     = $product;
    $kg_box   = max(0.0, (float) ($data['kg_per_box'] ?? 0.0));
} else {
    $data     = $args;
    $kg_box   = max(0.0, (float) ($data['kg_per_box'] ?? 0.0));
}

$price_m2     = (int) round((float) ($data['price_m2'] ?? 0));
$old_price_m2 = (int) round((float) ($data['regular_price_m2'] ?? 0));
$price_piece  = (int) round((float) ($data['price_piece'] ?? 0));
$per_box      = max(1, (int) ($data['pieces_per_box'] ?? 3));
$m2_box       = (float) ($data['m2_per_box'] ?? 1.44);
$unit_label   = (string) ($data['unit_label'] ?? 'viên');
$stock_boxes  = isset($data['stock_boxes']) ? (int) $data['stock_boxes'] : null;
$state        = (string) ($data['price_state'] ?? '');

$is_quote = ($state === PriceState::QUOTE_ONLY || $price_m2 <= 0);
$is_sale  = (!$is_quote && $old_price_m2 > $price_m2);
$discount = ($is_sale && $old_price_m2 > 0) ? (int) round((1 - ($price_m2 / $old_price_m2)) * 100) : 0;

if (in_array($context, ['single', 'pdp'], true)) :
    // Mockup 03: dòng phụ gọn "≈ 246.400đ/viên"; quy cách thùng/kg hiển thị trong ghi chú của <drv-unit-calc>.
    $sla_text = (string) drv_setting('order.sla_text', '');
    ?>
    <?php if ($is_quote) : ?>
        <div class="drv-pdp-price-block drv-pdp-price-block--quote u-tabular">
            <div class="drv-pdp-price-main">
                <?php drv_ui('icon', ['name' => 'tag', 'weight' => 'fill', 'size' => 'sm', 'class' => 'drv-pdp-price-tag']); ?>
                <span class="drv-pdp-price-m2">Liên hệ báo giá</span>
            </div>
            <div class="drv-pdp-price-piece">
                <?php echo esc_html($sla_text); ?>
            </div>
        </div>
    <?php else : ?>
        <div class="drv-pdp-price-block u-tabular">
            <div class="drv-pdp-price-main">
                <span class="drv-pdp-price-m2<?php echo $is_sale ? ' drv-pdp-price-m2--sale' : ''; ?>">
                    <?php echo esc_html(drv_format_vnd($price_m2)); ?>/m²
                </span>
                <?php if ($is_sale) : ?>
                    <del class="drv-pdp-price-old"><?php echo esc_html(drv_format_vnd($old_price_m2)); ?>/m²</del>
                    <?php if ($discount > 0) : ?>
                        <span class="drv-pdp-price-discount">-<?php echo esc_html((string) $discount); ?>%</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="drv-pdp-price-piece">
                ≈ <?php echo esc_html(drv_format_vnd($price_piece) . '/' . $unit_label); ?>
            </div>
        </div>
    <?php endif; ?>
    <?php
    return;
endif;
?>
<div class="drv-card__prices u-tabular">
    <?php if ($is_quote) : ?>
        <div class="drv-card__price-main">
            <span class="drv-card__price-contact">
                <?php drv_ui('icon', ['name' => 'tag', 'weight' => 'fill', 'size' => 'xs']); ?>
                <span>Liên hệ báo giá</span>
            </span>
        </div>
        <span class="drv-card__price-piece">
            <?php echo esc_html(drv_format_decimal($m2_box) . ' m² ≈ 1 thùng (' . $per_box . ' ' . $unit_label . ')'); ?>
        </span>
    <?php else : ?>
        <div class="drv-card__price-main">
            <span class="drv-card__price-m2<?php echo $is_sale ? ' drv-card__price-m2--sale' : ''; ?>">
                <?php echo esc_html(drv_format_vnd($price_m2)); ?>/m²
            </span>
            <?php if ($is_sale) : ?>
                <del class="drv-card__price-old"><?php echo esc_html(drv_format_vnd($old_price_m2)); ?>/m²</del>
            <?php endif; ?>
        </div>
        <span class="drv-card__price-piece">
            ≈ <?php echo esc_html(drv_format_vnd($price_piece) . '/' . $unit_label); ?><?php echo ($stock_boxes !== null && $stock_boxes > 0) ? esc_html(' · ' . $stock_boxes . ' thùng') : ''; ?>
        </span>
    <?php endif; ?>
</div>
