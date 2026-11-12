<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$src          = $args['product'] ?? null;
$value_pieces = max(1, (int) ($args['value_pieces'] ?? 1));
$input_name   = (string) ($args['input_name'] ?? 'quantity');
$aria_label   = (string) ($args['aria_label'] ?? 'Số lượng');

if ($src instanceof \WC_Product) {
    $model       = 'area_pack';
    $price_unit  = (float) $src->get_price();
    $area_piece  = ((float) $src->get_meta('drv_width_mm') * (float) $src->get_meta('drv_height_mm')) / 1000000;
    $per_box     = max(1, (int) ($src->get_meta('drv_pieces_per_box') ?: 3));
    $weight_pc   = ((float) $src->get_meta('drv_weight_per_box')) / $per_box;
    $unit_label  = (string) ($src->get_meta('drv_unit_label') ?: 'viên');
    $round_mode  = (string) ($src->get_meta('drv_round_mode') ?: 'box');
} else {
    $arr        = is_array($src) ? $src : $args;
    $model      = (string) ($arr['model'] ?? 'area_pack');
    $price_unit = (float) ($arr['price_unit'] ?? 0);
    $area_piece = (float) ($arr['area_piece'] ?? 0.64);
    $per_box    = max(1, (int) ($arr['pieces_per_box'] ?? 3));
    $weight_pc  = (float) ($arr['weight_piece'] ?? 14);
    $unit_label = (string) ($arr['unit_label'] ?? 'viên');
    $round_mode = (string) ($arr['round_mode'] ?? 'piece');
}

$step = ($round_mode === 'box') ? $per_box : 1;
?>
<drv-qty-input
    data-model="<?php echo esc_attr($model); ?>"
    data-price-unit="<?php echo esc_attr((string) $price_unit); ?>"
    data-area-piece="<?php echo esc_attr((string) $area_piece); ?>"
    data-pieces-per-box="<?php echo esc_attr((string) $per_box); ?>"
    data-weight-piece="<?php echo esc_attr((string) $weight_pc); ?>"
    data-unit-label="<?php echo esc_attr($unit_label); ?>">
    <div class="l-cluster l-cluster--xs">
        <div class="drv-stepper">
            <button type="button" class="drv-stepper__btn drv-stepper__btn--prev" data-qty-step="<?php echo esc_attr((string) (-$step)); ?>" aria-label="<?php echo esc_attr('Giảm ' . $step . ' ' . $unit_label); ?>">
                <?php drv_ui('icon', ['name' => 'minus', 'size' => 'xs']); ?>
            </button>
            <input type="number" name="<?php echo esc_attr($input_name); ?>" class="drv-stepper__input" value="<?php echo esc_attr((string) $value_pieces); ?>" min="<?php echo esc_attr((string) $step); ?>" step="<?php echo esc_attr((string) $step); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">
            <button type="button" class="drv-stepper__btn drv-stepper__btn--next" data-qty-step="<?php echo esc_attr((string) $step); ?>" aria-label="<?php echo esc_attr('Tăng ' . $step . ' ' . $unit_label); ?>">
                <?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?>
            </button>
        </div>
        <?php if ($model === 'area_pack' && $round_mode === 'piece' && $per_box > 1) : ?>
            <button type="button" class="drv-btn drv-btn--sm drv-btn--soft" data-qty-add-box>
                <?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?>
                <span>1 thùng (<?php echo esc_html((string) $per_box . 'v'); ?>)</span>
            </button>
        <?php endif; ?>
    </div>
</drv-qty-input>

