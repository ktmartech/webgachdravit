<?php
declare(strict_types=1);

use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Máy tính quy đổi `m² ⇄ thùng ⇄ viên` dùng chung lõi `Dravit\Units\Calc` (ADR D-24, D-33, docs/15 §5.1).
 *
 * @var array<string, mixed> $args
 */
$src = $args['product'] ?? null;

if ($src instanceof \WC_Product) {
    $id          = $src->get_id();
    $sku         = (string) $src->get_sku();
    $state       = PriceState::resolve($src);
    $unit_cfg    = PriceState::readUnitConfig($src);
    $reg_m2      = max(0, (int) $src->get_meta('drv_price_m2', true));
    $sale_m2     = max(0, (int) $src->get_meta('drv_sale_price_m2', true));
    $price_m2    = ($state === PriceState::QUOTE_ONLY)
        ? 0
        : (($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2);
    $piece_w     = max(1, (int) $src->get_meta('drv_piece_w_mm', true));
    $piece_h     = max(1, (int) $src->get_meta('drv_piece_h_mm', true));
    $per_box     = $unit_cfg['pieces_per_box'];
    $weight_box  = max(0.0, (float) $src->get_meta('drv_kg_per_box', true));
    $buy_units   = $unit_cfg['buy_units'];
    $def_unit    = $unit_cfg['default_buy_unit'];
    $round_mode  = $unit_cfg['round_mode'];
    $qty_min     = $unit_cfg['qty_min'];
    $unit_label  = $unit_cfg['unit_label'];
} else {
    $arr        = is_array($src) ? $src : $args;
    $id         = (int) ($arr['id'] ?? 0);
    $sku        = (string) ($arr['sku'] ?? '');
    $price_m2   = max(0, (int) round((float) ($arr['price_m2'] ?? 385000)));
    $piece_w    = max(1, (int) ($arr['piece_w'] ?? ($arr['drv_piece_w_mm'] ?? 600)));
    $piece_h    = max(1, (int) ($arr['piece_h'] ?? ($arr['drv_piece_h_mm'] ?? 600)));
    $per_box    = max(1, (int) ($arr['pieces_per_box'] ?? 4));
    $weight_box = max(0.0, (float) ($arr['weight_per_box'] ?? ($arr['kg_per_box'] ?? 30)));
    $raw_units  = isset($arr['buy_units']) && is_array($arr['buy_units']) ? array_map('strval', $arr['buy_units']) : ['m2', 'thung'];
    $norm_cfg   = PriceState::normalizeBuyUnitConfig(
        $raw_units,
        (string) ($arr['default_buy_unit'] ?? 'm2'),
        (string) ($arr['round_mode'] ?? 'box'),
        $per_box,
        (int) ($arr['qty_min'] ?? 0)
    );
    $buy_units  = $norm_cfg['buy_units'];
    $def_unit   = $norm_cfg['default_buy_unit'];
    $round_mode = $norm_cfg['round_mode'];
    $qty_min    = $norm_cfg['qty_min'];
    $unit_label = (string) ($arr['unit_label'] ?? 'viên');
}

$default_m2 = max(0.5, (float) ($args['default_area_m2'] ?? 10.0));
$area_piece = Calc::pieceAreaM2($piece_w, $piece_h);
$m2_box     = round($area_piece * $per_box, 4);

$init = Calc::calcFromM2([
    'areaM2'       => $default_m2,
    'wastePct'     => 5.0,
    'areaPiece'    => $area_piece,
    'piecesPerBox' => $per_box,
    'roundMode'    => $round_mode,
    'qtyMin'       => $qty_min,
    'priceM2'      => $price_m2,
    'kgPerBox'     => $weight_box,
    'unitLabel'    => $unit_label,
]);

$uid        = 'drv-calc-' . ($id > 0 ? $id : sanitize_html_class($sku !== '' ? $sku : (string) wp_rand(10, 999)));
$units_csv  = implode(',', $buy_units);
?>
<drv-unit-calc
    data-product-id="<?php echo esc_attr((string) $id); ?>"
    data-sku="<?php echo esc_attr($sku); ?>"
    data-price-m2="<?php echo esc_attr((string) $price_m2); ?>"
    data-piece-w="<?php echo esc_attr((string) $piece_w); ?>"
    data-piece-h="<?php echo esc_attr((string) $piece_h); ?>"
    data-area-piece="<?php echo esc_attr((string) $area_piece); ?>"
    data-pieces-per-box="<?php echo esc_attr((string) $per_box); ?>"
    data-weight-per-box="<?php echo esc_attr((string) $weight_box); ?>"
    data-buy-units="<?php echo esc_attr($units_csv); ?>"
    data-default-unit="<?php echo esc_attr($def_unit); ?>"
    data-round-mode="<?php echo esc_attr($round_mode); ?>"
    data-qty-min="<?php echo esc_attr((string) $qty_min); ?>"
    data-unit-label="<?php echo esc_attr($unit_label); ?>">
    <div class="drv-calc">
        <div class="l-cluster l-cluster--between">
            <span class="u-text-sm u-bold">BỘ TÍNH SỐ LƯỢNG &amp; QUY ĐỔI THÙNG</span>
            <?php
            drv_ui('badge', [
                'text'    => sprintf(
                    '1 thùng = %d %s = %s m²%s',
                    $per_box,
                    $unit_label,
                    drv_format_decimal($m2_box),
                    $weight_box > 0 ? ' (' . drv_format_decimal($weight_box, 0) . ' kg)' : ''
                ),
                'variant' => 'outline',
            ]);
            ?>
        </div>

        <div class="drv-calc__inputs">
            <div class="drv-field">
                <label for="<?php echo esc_attr($uid . '-m2'); ?>" class="drv-label">Diện tích cần dùng (m²)</label>
                <div class="drv-stepper">
                    <button type="button" class="drv-stepper__btn drv-stepper__btn--prev" data-calc-step="-1" data-calc-target="m2" aria-label="Giảm 1 m²"><?php drv_ui('icon', ['name' => 'minus', 'size' => 'xs']); ?></button>
                    <input id="<?php echo esc_attr($uid . '-m2'); ?>" type="number" class="drv-stepper__input" value="<?php echo esc_attr((string) $default_m2); ?>" min="0.5" step="0.5" data-calc-m2>
                    <button type="button" class="drv-stepper__btn drv-stepper__btn--next" data-calc-step="1" data-calc-target="m2" aria-label="Tăng 1 m²"><?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?></button>
                </div>
            </div>

            <div class="drv-calc__swap-icon" aria-hidden="true">
                <?php drv_ui('icon', ['name' => 'arrows-left-right']); ?>
            </div>

            <div class="drv-field">
                <label for="<?php echo esc_attr($uid . '-pieces'); ?>" class="drv-label">Số <?php echo esc_html($unit_label); ?> giao dịch</label>
                <div class="drv-stepper">
                    <button type="button" class="drv-stepper__btn drv-stepper__btn--prev" data-calc-step="<?php echo esc_attr((string) (-($round_mode === 'box' ? $per_box : 1))); ?>" data-calc-target="pieces" aria-label="Giảm số lượng"><?php drv_ui('icon', ['name' => 'minus', 'size' => 'xs']); ?></button>
                    <input id="<?php echo esc_attr($uid . '-pieces'); ?>" type="number" class="drv-stepper__input" value="<?php echo esc_attr((string) $init['pieces']); ?>" min="<?php echo esc_attr((string) $qty_min); ?>" step="<?php echo esc_attr((string) ($round_mode === 'box' ? $per_box : 1)); ?>" data-calc-pieces>
                    <button type="button" class="drv-stepper__btn drv-stepper__btn--next" data-calc-step="<?php echo esc_attr((string) ($round_mode === 'box' ? $per_box : 1)); ?>" data-calc-target="pieces" aria-label="Tăng số lượng"><?php drv_ui('icon', ['name' => 'plus', 'size' => 'xs']); ?></button>
                </div>
            </div>
        </div>

        <div class="drv-calc__options">
            <label class="drv-check">
                <input type="checkbox" checked data-calc-waste>
                <span>Cộng 5% hao hụt cắt gạch</span>
            </label>
            <label class="drv-check">
                <input type="checkbox" <?php checked($round_mode === 'box'); ?> <?php disabled(!in_array('vien', $buy_units, true)); ?> data-calc-round-box>
                <span>Làm tròn chẵn thùng (<?php echo esc_html((string) $per_box . ' ' . $unit_label); ?>)</span>
            </label>
        </div>

        <div class="drv-calc__summary" aria-live="polite">
            <div class="u-text-xs u-text-muted" data-calc-raw-need>
                Diện tích tính cả 5% hao hụt cắt gạch: <?php echo esc_html(drv_format_decimal($init['needM2'])); ?> m²
            </div>
            <div class="u-text-sm">
                <span>Thực nhận: </span>
                <strong class="u-text-moss" data-calc-summary>
                    <?php
                    $kg_txt = $init['totalKg'] > 0 ? ' · ' . drv_format_decimal($init['totalKg'], 0) . ' kg' : '';
                    echo esc_html(sprintf(
                        '%d %s = %s (%s m²%s)',
                        $init['pieces'],
                        $unit_label,
                        $init['boxText'],
                        drv_format_decimal($init['actualM2']),
                        $kg_txt
                    ));
                    ?>
                </strong>
            </div>
            <div class="drv-calc__total-row">
                <div>
                    <div class="u-text-sm u-bold">TỔNG TẠM TÍNH:</div>
                    <div class="u-text-xs u-text-muted">(Phí vận chuyển báo sau khi xác nhận đơn)</div>
                </div>
                <output class="drv-calc__total-price u-tabular" data-calc-total>
                    <?php echo esc_html($price_m2 > 0 ? drv_format_vnd($init['totalVnd']) : 'Liên hệ báo giá'); ?>
                </output>
            </div>
        </div>
    </div>
</drv-unit-calc>
