<?php
declare(strict_types=1);

use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Máy tính đa đơn vị `<drv-unit-calc>` (`m² | Thùng | Viên`) dùng chung lõi `Dravit\Units\Calc` (`03-product-single.html`, ADR D-20, D-24, docs/15 §5.1).
 * Cam kết:
 * - Chỉ hiển thị các tab có trong `drv_buy_units`; ẩn thanh tab khi chỉ có 1 đơn vị (`U-16`).
 * - Bố cục 1 dòng gọn gàng tương tự mockup `03-product-single.html` (`[ - ] [ 10 ] [ + ] m² ≈ 18 viên`).
 * - Tôn trọng `drv_round_mode` (`box` | `piece`), `drv_qty_min`, `drv_qty_step` và hao hụt `0% / 5% / 10%`.
 * - Chế độ `quote_only` tính đủ viên/thùng/m²/kg nhưng ẩn dòng tiền `0đ` (`U-11`).
 * - Không bao giờ gắn thông số giả `DRV-8801M` khi thiếu dữ liệu (`P-9`, `STRIP-12`).
 *
 * @var array<string, mixed> $args
 */
$src = $args['product'] ?? null;

if ($src instanceof \WC_Product) {
    $id          = $src->get_id();
    $sku         = trim((string) $src->get_sku());
    $price_state = PriceState::resolve($src);
    $unit_cfg    = PriceState::readUnitConfig($src);
    $reg_m2      = max(0, (int) $src->get_meta('drv_price_m2', true));
    $sale_m2     = max(0, (int) $src->get_meta('drv_sale_price_m2', true));
    $is_quote    = ($price_state === PriceState::QUOTE_ONLY);
    $price_m2    = $is_quote
        ? 0
        : (($price_state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2);
    $price_piece = $is_quote ? 0 : max(0, (int) round((float) $src->get_price()));
    $piece_w     = max(0, (int) $src->get_meta('drv_piece_w_mm', true));
    $piece_h     = max(0, (int) $src->get_meta('drv_piece_h_mm', true));
    $per_box     = $unit_cfg['pieces_per_box'];
    $weight_box  = max(0.0, (float) ($src->get_meta('drv_kg_per_box', true) ?: $src->get_meta('drv_weight_kg_per_box', true)));
    $buy_units   = $unit_cfg['buy_units'];
    $def_unit    = $unit_cfg['default_buy_unit'];
    $round_mode  = $unit_cfg['round_mode'];
    $qty_min     = $unit_cfg['qty_min'];
    $qty_step    = $unit_cfg['qty_step'];
    $unit_label  = $unit_cfg['unit_label'];
    $raw_waste   = trim((string) $src->get_meta('drv_waste_rate', true));
    $waste_def   = ($raw_waste !== '' && is_numeric($raw_waste))
        ? (int) round((float) $raw_waste)
        : (int) drv_setting('order.waste_default', 5);
} else {
    $arr         = is_array($src) ? $src : $args;
    $id          = (int) ($arr['id'] ?? 0);
    $sku         = trim((string) ($arr['sku'] ?? ''));
    $price_m2    = max(0, (int) round((float) ($arr['price_m2'] ?? 0)));
    $price_state = (string) ($arr['price_state'] ?? ($price_m2 > 0 ? PriceState::PRICED : PriceState::QUOTE_ONLY));
    $is_quote    = ($price_state === PriceState::QUOTE_ONLY || $price_m2 <= 0);
    $piece_w     = max(0, (int) ($arr['piece_w'] ?? ($arr['drv_piece_w_mm'] ?? 0)));
    $piece_h     = max(0, (int) ($arr['piece_h'] ?? ($arr['drv_piece_h_mm'] ?? 0)));
    $per_box     = max(1, (int) ($arr['pieces_per_box'] ?? 1));
    $weight_box  = max(0.0, (float) ($arr['weight_per_box'] ?? ($arr['kg_per_box'] ?? 0.0)));
    $raw_units   = isset($arr['buy_units']) && is_array($arr['buy_units']) ? array_map('strval', $arr['buy_units']) : ['m2', 'thung'];
    $norm_cfg    = PriceState::normalizeBuyUnitConfig(
        $raw_units,
        (string) ($arr['default_buy_unit'] ?? 'm2'),
        (string) ($arr['round_mode'] ?? 'box'),
        $per_box,
        (int) ($arr['qty_min'] ?? 0)
    );
    $buy_units   = $norm_cfg['buy_units'];
    $def_unit    = $norm_cfg['default_buy_unit'];
    $round_mode  = $norm_cfg['round_mode'];
    $qty_min     = $norm_cfg['qty_min'];
    $qty_step    = $norm_cfg['qty_step'];
    $unit_label  = trim((string) ($arr['unit_label'] ?? 'viên')) ?: 'viên';
    $area_tmp    = Calc::pieceAreaM2($piece_w, $piece_h);
    $price_piece = $is_quote ? 0 : (isset($arr['price_piece']) ? max(0, (int) $arr['price_piece']) : Calc::pricePerPiece($price_m2, $area_tmp));
    $waste_def   = isset($arr['waste_default']) ? (int) round((float) $arr['waste_default']) : 5;
}

if ($piece_w <= 0 || $piece_h <= 0) {
    return;
}

$waste_options = isset($args['waste_options']) && is_array($args['waste_options'])
    ? array_values(array_unique(array_map('intval', $args['waste_options'])))
    : [0, 5, 10];
if (!in_array($waste_def, $waste_options, true)) {
    $waste_def = in_array(5, $waste_options, true) ? 5 : ($waste_options[0] ?? 0);
}

$default_m2 = max(0.5, (float) ($args['default_area_m2'] ?? 10.0));
$area_piece = Calc::pieceAreaM2($piece_w, $piece_h);
$m2_box     = round($area_piece * $per_box, 4);

if ($def_unit === 'vien' && in_array('vien', $buy_units, true)) {
    $init = Calc::calcFromPieces([
        'pieces'       => $qty_min,
        'areaPiece'    => $area_piece,
        'piecesPerBox' => $per_box,
        'roundMode'    => $round_mode,
        'qtyMin'       => $qty_min,
        'priceM2'      => $price_m2,
        'kgPerBox'     => $weight_box,
        'unitLabel'    => $unit_label,
    ]);
    $default_m2 = max(0.5, round($init['actualM2'], 2));
    $init_boxes = max(1, $init['fullBoxes']);
} elseif ($def_unit === 'thung' && in_array('thung', $buy_units, true)) {
    $init_boxes = max(1, (int) ceil(($qty_min - Calc::EPSILON) / $per_box));
    $init       = Calc::calcFromBoxes([
        'boxes'        => $init_boxes,
        'areaPiece'    => $area_piece,
        'piecesPerBox' => $per_box,
        'qtyMin'       => $qty_min,
        'priceM2'      => $price_m2,
        'kgPerBox'     => $weight_box,
        'unitLabel'    => $unit_label,
    ]);
    $default_m2 = max(0.5, round($init['actualM2'], 2));
} else {
    $def_unit   = in_array('m2', $buy_units, true) ? 'm2' : $buy_units[0];
    $init       = Calc::calcFromM2([
        'areaM2'       => $default_m2,
        'wastePct'     => (float) $waste_def,
        'areaPiece'    => $area_piece,
        'piecesPerBox' => $per_box,
        'roundMode'    => $round_mode,
        'qtyMin'       => $qty_min,
        'priceM2'      => $price_m2,
        'kgPerBox'     => $weight_box,
        'unitLabel'    => $unit_label,
    ]);
    $init_boxes = max(1, $init['fullBoxes']);
}

if (!$is_quote && $price_piece > 0) {
    $init['pricePiece'] = $price_piece;
    $init['totalVnd']   = $init['pieces'] * $price_piece;
}

$uid        = 'drv-calc-' . ($id > 0 ? $id : sanitize_html_class($sku !== '' ? $sku : (string) wp_rand(10, 999)));
$units_json = (string) wp_json_encode(array_values($buy_units));
$unit_names = [
    'm2'    => 'Theo m²',
    'thung' => 'Theo thùng',
    'vien'  => 'Theo ' . $unit_label,
];
$show_tabs = count($buy_units) > 1;
?>
<drv-unit-calc
    class="drv-pdp-calc drv-calc-host"
    data-product-id="<?php echo esc_attr((string) $id); ?>"
    data-sku="<?php echo esc_attr($sku); ?>"
    data-price-state="<?php echo esc_attr($price_state); ?>"
    data-price-m2="<?php echo esc_attr((string) $price_m2); ?>"
    data-price-piece="<?php echo esc_attr((string) $price_piece); ?>"
    data-piece-w="<?php echo esc_attr((string) $piece_w); ?>"
    data-piece-h="<?php echo esc_attr((string) $piece_h); ?>"
    data-area-piece="<?php echo esc_attr((string) $area_piece); ?>"
    data-pieces-per-box="<?php echo esc_attr((string) $per_box); ?>"
    data-kg-per-box="<?php echo esc_attr((string) $weight_box); ?>"
    data-weight-per-box="<?php echo esc_attr((string) $weight_box); ?>"
    data-unit-label="<?php echo esc_attr($unit_label); ?>"
    data-buy-units="<?php echo esc_attr($units_json); ?>"
    data-default-unit="<?php echo esc_attr($def_unit); ?>"
    data-round-mode="<?php echo esc_attr($round_mode); ?>"
    data-qty-min="<?php echo esc_attr((string) $qty_min); ?>"
    data-qty-step="<?php echo esc_attr((string) $qty_step); ?>"
    data-waste-default="<?php echo esc_attr((string) $waste_def); ?>">
    <div class="drv-calc__bar" <?php echo $show_tabs ? '' : 'hidden'; ?>>
        <span class="drv-calc__bar-label" aria-hidden="true">Mua theo:</span>
        <div class="drv-calc__tabs" role="tablist" aria-label="Chọn đơn vị tính số lượng" <?php echo $show_tabs ? '' : 'hidden'; ?>>
            <?php foreach ($buy_units as $u_key) :
                $is_tab_active = ($u_key === $def_unit);
                $tab_id        = $uid . '-tab-' . $u_key;
                $panel_id      = $uid . '-panel-' . $u_key;
                ?>
                <button type="button"
                        id="<?php echo esc_attr($tab_id); ?>"
                        role="tab"
                        class="drv-calc__tab<?php echo $is_tab_active ? ' is-active' : ''; ?>"
                        aria-selected="<?php echo $is_tab_active ? 'true' : 'false'; ?>"
                        aria-controls="<?php echo esc_attr($panel_id); ?>"
                        tabindex="<?php echo $is_tab_active ? '0' : '-1'; ?>"
                        data-calc-unit-tab="<?php echo esc_attr($u_key); ?>">
                    <?php echo esc_html($unit_names[$u_key] ?? $u_key); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (in_array('m2', $buy_units, true)) : ?>
        <div id="<?php echo esc_attr($uid . '-panel-m2'); ?>"
             role="tabpanel"
             aria-labelledby="<?php echo esc_attr($uid . '-tab-m2'); ?>"
             class="drv-calc__panel"
             data-calc-unit-panel="m2"
             <?php echo $def_unit === 'm2' ? '' : 'hidden'; ?>>
            <div class="drv-pdp-calc__controls">
                <div class="drv-pdp-stepper">
                    <button type="button" class="drv-pdp-stepper__btn" data-calc-step="-1" data-calc-target="m2" aria-label="Giảm diện tích m²">
                        <?php drv_ui('icon', ['name' => 'minus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    </button>
                    <label for="<?php echo esc_attr($uid . '-m2'); ?>" class="u-sr-only">Diện tích cần ốp lát (m²)</label>
                    <input id="<?php echo esc_attr($uid . '-m2'); ?>"
                           type="number"
                           inputmode="decimal"
                           class="drv-pdp-stepper__input u-tabular"
                           value="<?php echo esc_attr((string) $default_m2); ?>"
                           min="0.5"
                           step="0.5"
                           data-calc-input="m2"
                           data-calc-m2>
                    <button type="button" class="drv-pdp-stepper__btn" data-calc-step="1" data-calc-target="m2" aria-label="Tăng diện tích m²">
                        <?php drv_ui('icon', ['name' => 'plus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    </button>
                </div>
                <span class="drv-pdp-calc__unit">m²</span>

                <span class="drv-pdp-calc__approx" aria-hidden="true">≈</span>

                <div class="drv-pdp-pieces-box">
                    <strong class="drv-pdp-calc__pieces-val" data-calc-inline-pieces><?php echo esc_html((string) $init['pieces']); ?></strong>
                    <span class="drv-pdp-calc__unit"><?php echo esc_html($unit_label); ?></span>
                </div>

                <div class="drv-calc__waste-field" data-calc-waste-wrap>
                    <label for="<?php echo esc_attr($uid . '-waste'); ?>" class="drv-calc__opt-label">Hao hụt</label>
                    <select id="<?php echo esc_attr($uid . '-waste'); ?>" class="drv-calc__waste-select u-tabular" data-calc-waste>
                        <?php foreach ($waste_options as $w_opt) : ?>
                            <option value="<?php echo esc_attr((string) $w_opt); ?>" <?php selected($waste_def, $w_opt); ?>>
                                <?php echo esc_html($w_opt . '%'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array('thung', $buy_units, true)) : ?>
        <div id="<?php echo esc_attr($uid . '-panel-thung'); ?>"
             role="tabpanel"
             aria-labelledby="<?php echo esc_attr($uid . '-tab-thung'); ?>"
             class="drv-calc__panel"
             data-calc-unit-panel="thung"
             <?php echo $def_unit === 'thung' ? '' : 'hidden'; ?>>
            <div class="drv-pdp-calc__controls">
                <div class="drv-pdp-stepper">
                    <button type="button" class="drv-pdp-stepper__btn" data-calc-step="-1" data-calc-target="thung" aria-label="Giảm 1 thùng">
                        <?php drv_ui('icon', ['name' => 'minus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    </button>
                    <label for="<?php echo esc_attr($uid . '-thung'); ?>" class="u-sr-only">Số thùng nguyên cần đặt</label>
                    <input id="<?php echo esc_attr($uid . '-thung'); ?>"
                           type="number"
                           inputmode="numeric"
                           class="drv-pdp-stepper__input u-tabular"
                           value="<?php echo esc_attr((string) $init_boxes); ?>"
                           min="1"
                           step="1"
                           data-calc-input="thung"
                           data-calc-boxes>
                    <button type="button" class="drv-pdp-stepper__btn" data-calc-step="1" data-calc-target="thung" aria-label="Tăng 1 thùng">
                        <?php drv_ui('icon', ['name' => 'plus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    </button>
                </div>
                <span class="drv-pdp-calc__unit">thùng</span>

                <span class="drv-pdp-calc__approx" aria-hidden="true">=</span>

                <div class="drv-pdp-pieces-box">
                    <strong class="drv-pdp-calc__pieces-val" data-calc-inline-pieces><?php echo esc_html((string) $init['pieces']); ?></strong>
                    <span class="drv-pdp-calc__unit"><?php echo esc_html($unit_label); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array('vien', $buy_units, true)) : ?>
        <div id="<?php echo esc_attr($uid . '-panel-vien'); ?>"
             role="tabpanel"
             aria-labelledby="<?php echo esc_attr($uid . '-tab-vien'); ?>"
             class="drv-calc__panel"
             data-calc-unit-panel="vien"
             <?php echo $def_unit === 'vien' ? '' : 'hidden'; ?>>
            <div class="drv-pdp-calc__controls">
                <div class="drv-pdp-stepper">
                    <button type="button" class="drv-pdp-stepper__btn" data-calc-step="<?php echo esc_attr((string) (-$qty_step)); ?>" data-calc-target="vien" aria-label="Giảm số <?php echo esc_attr($unit_label); ?>">
                        <?php drv_ui('icon', ['name' => 'minus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    </button>
                    <label for="<?php echo esc_attr($uid . '-vien'); ?>" class="u-sr-only">Số <?php echo esc_html($unit_label); ?> cần đặt (tối thiểu <?php echo esc_html((string) $qty_min); ?>)</label>
                    <input id="<?php echo esc_attr($uid . '-vien'); ?>"
                           type="number"
                           inputmode="numeric"
                           class="drv-pdp-stepper__input u-tabular"
                           value="<?php echo esc_attr((string) $init['pieces']); ?>"
                           min="<?php echo esc_attr((string) $qty_min); ?>"
                           step="<?php echo esc_attr((string) $qty_step); ?>"
                           data-calc-input="vien"
                           data-calc-pieces>
                    <button type="button" class="drv-pdp-stepper__btn" data-calc-step="<?php echo esc_attr((string) $qty_step); ?>" data-calc-target="vien" aria-label="Tăng số <?php echo esc_attr($unit_label); ?>">
                        <?php drv_ui('icon', ['name' => 'plus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    </button>
                </div>
                <span class="drv-pdp-calc__unit"><?php echo esc_html($unit_label); ?></span>

                <span class="drv-pdp-calc__approx" aria-hidden="true">≈</span>

                <div class="drv-pdp-pieces-box">
                    <strong class="drv-pdp-calc__pieces-val" data-calc-inline-m2><?php echo esc_html(drv_format_decimal($init['actualM2'], 2) . ' m²'); ?></strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($round_mode === 'piece') : ?>
        <label class="drv-check drv-calc__round-check">
            <input type="checkbox" class="drv-check__input" data-calc-round-box checked>
            <span><?php echo esc_html(sprintf('Làm tròn chẵn thùng (%d %s)', $per_box, $unit_label)); ?></span>
        </label>
    <?php endif; ?>

    <div class="drv-pdp-calc__live" aria-live="polite">
        <p class="drv-pdp-calc__note">
            Thực nhận là
            <strong class="u-text-navy u-tabular" data-calc-out="actual-m2"><?php echo esc_html(drv_format_decimal($init['actualM2'], 2) . ' m²'); ?></strong>
            (<span class="u-tabular" data-calc-out="pieces-boxes"><?php echo esc_html($init['pieces'] . ' ' . $unit_label . ' = ' . $init['boxText']); ?></span><?php if ($weight_box > 0.0) : ?> · <span class="u-tabular" data-calc-out="kg">≈ <?php echo esc_html(drv_format_decimal($init['totalKg'], 1) . ' kg'); ?></span><?php endif; ?>;
            <?php
            echo esc_html(
                ($round_mode === 'box' ? 'gạch không bán lẻ, đóng nguyên thùng ' : '1 thùng = ')
                . $per_box . ' ' . $unit_label . ' = ' . drv_format_decimal($m2_box, 2) . ' m²'
            );
            ?>)
            <span class="drv-pdp-calc__raw-sub" data-calc-out="raw-need" data-calc-raw-need <?php echo $def_unit === 'm2' ? '' : 'hidden'; ?>>
                <?php
                echo esc_html(
                    $waste_def > 0
                        ? sprintf('· Đã gồm %d%% hao hụt cắt gạch: %s m²', $waste_def, drv_format_decimal($init['needM2'], 2))
                        : sprintf('· Diện tích tịnh chưa cộng hao hụt: %s m²', drv_format_decimal($init['needM2'], 2))
                );
                ?>
            </span>
        </p>

        <?php if (!$is_quote) : ?>
            <div class="drv-pdp-calc__total-row">
                <div>
                    <span class="drv-pdp-calc__total-label">Tổng tiền</span>
                    <span class="drv-pdp-calc__ship-sub">Phí vận chuyển báo sau xác nhận</span>
                </div>
                <output class="drv-pdp-calc__total-val u-tabular" data-calc-out="total-vnd" data-calc-total>
                    <?php echo esc_html(drv_format_vnd($init['totalVnd'])); ?>
                </output>
            </div>
        <?php else : ?>
            <div class="drv-pdp-calc__quote-row">
                <?php drv_ui('icon', ['name' => 'info', 'weight' => 'fill', 'size' => 'sm']); ?>
                <p>
                    Cần <strong class="u-tabular" data-calc-boxes-quote><?php echo esc_html($init['boxText']); ?></strong>
                    (<strong class="u-tabular" data-calc-m2-quote><?php echo esc_html(drv_format_decimal($init['actualM2'], 2) . ' m²'); ?></strong>) cho diện tích này.
                    Gửi yêu cầu để nhận báo giá chiết khấu theo khối lượng và phí vận chuyển.
                </p>
            </div>
        <?php endif; ?>
    </div>
</drv-unit-calc>
