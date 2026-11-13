<?php
declare(strict_types=1);

use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Thẻ sản phẩm hợp nhất duy nhất toàn hệ thống (ADR D-20, D-24, D-26, docs/15 §6.1–6.2).
 * CẤM nút Wishlist (.drv-card__wish) và các thuộc tính lọc giả trên DOM.
 *
 * @var array<string, mixed> $args
 */
$src         = $args['product'] ?? $args['data'] ?? null;
$priority    = !empty($args['priority_load']);
$heading_tag = (($args['heading_tag'] ?? 'h3') === 'h2') ? 'h2' : 'h3';

if ($src instanceof \WC_Product) {
    $d = PriceState::toCardData($src);
} elseif (is_array($src)) {
    $d = $src;
} else {
    return;
}

$id           = (int) ($d['id'] ?? 0);
$sku          = (string) ($d['sku'] ?? '');
$title        = (string) ($d['title'] ?? '');
$url          = (string) ($d['url'] ?? home_url('/gach'));
$price_m2     = (int) round((float) ($d['price_m2'] ?? 0));
$old_price_m2 = (int) round((float) ($d['regular_price_m2'] ?? 0));
$price_piece  = (int) round((float) ($d['price_piece'] ?? 0));
$per_box      = max(1, (int) ($d['pieces_per_box'] ?? 3));
$qty_min      = max(1, (int) ($d['qty_min'] ?? 1));
$default_unit = (string) ($d['default_buy_unit'] ?? 'm2');
$round_mode   = (string) ($d['round_mode'] ?? 'box');
$unit_label   = (string) ($d['unit_label'] ?? 'viên');
$m2_box       = (float) ($d['m2_per_box'] ?? 1.92);
$shelf        = trim((string) ($d['showroom_shelf'] ?? ''));
$card_label   = trim((string) ($d['card_label'] ?? ''));
$is_in_stock  = array_key_exists('in_stock', $d) ? (bool) $d['in_stock'] : true;
$stock_boxes  = isset($d['stock_boxes']) ? (int) $d['stock_boxes'] : null;
$low_stock    = !empty($d['low_stock']);
$img_primary  = (string) ($d['img_primary'] ?? '');
$img_hover    = (string) ($d['img_hover'] ?? '');
$tags         = isset($d['tags']) && is_array($d['tags']) ? $d['tags'] : [];

if ($title === '') {
    return;
}

if ($img_primary === '') {
    $img_primary = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
}

$is_quote = (($d['price_state'] ?? '') === PriceState::QUOTE_ONLY || $price_m2 <= 0);
$is_sale  = (!$is_quote && $old_price_m2 > $price_m2);
$is_out   = !$is_in_stock;

$price_state = (string) ($d['price_state'] ?? '');
if ($price_state === '') {
    if ($is_quote) {
        $price_state = PriceState::QUOTE_ONLY;
    } elseif ($is_out) {
        $price_state = PriceState::OUT_OF_STOCK;
    } elseif ($is_sale) {
        $price_state = PriceState::SALE;
    } else {
        $price_state = PriceState::PRICED;
    }
}

$quick_pieces = isset($d['quick_pieces'])
    ? max(1, (int) $d['quick_pieces'])
    : Calc::quickAddPieces([
        'defaultBuyUnit' => $default_unit,
        'roundMode'      => $round_mode,
        'piecesPerBox'   => $per_box,
        'qtyMin'         => $qty_min,
    ]);

$default_m2 = isset($d['default_m2'])
    ? (float) $d['default_m2']
    : round(($m2_box / $per_box) * $quick_pieces, 4);

$context = (string) ($args['context'] ?? 'catalog');
if ($context === 'advisor') {
    $advisor_meta = isset($args['advisor_meta']) && is_array($args['advisor_meta']) ? $args['advisor_meta'] : [];
    $match_score  = max(50, min(99, (int) ($advisor_meta['match_score'] ?? 90)));
    $dcof_level   = (int) ($advisor_meta['dcof_level'] ?? 0);
    $stock_badge  = (string) ($advisor_meta['stock_label'] ?? ($is_out ? 'Đặt trước' : 'Sẵn kho'));
    $water_label  = trim((string) ($advisor_meta['water_label'] ?? ''));
    $size_label   = trim((string) ($advisor_meta['size_label'] ?? ($tags[0] ?? '')));
    $surf_label   = trim((string) ($advisor_meta['surface_label'] ?? ($tags[1] ?? '')));
    $why_text     = trim((string) ($advisor_meta['why'] ?? ''));
    $calc_box     = isset($advisor_meta['calc']) && is_array($advisor_meta['calc']) ? $advisor_meta['calc'] : null;

    $calc_boxes  = $calc_box !== null ? max(1, (int) ($calc_box['boxes'] ?? 1)) : 1;
    $calc_pieces = $calc_box !== null ? max(1, (int) ($calc_box['pieces'] ?? $per_box)) : $per_box;
    $calc_m2     = $calc_box !== null ? (float) ($calc_box['actual_m2'] ?? $m2_box) : $m2_box;
    $est_total   = (!$is_quote && $calc_box !== null && !empty($calc_box['est_total_vnd']))
        ? (int) $calc_box['est_total_vnd']
        : null;

    $fmt_m2_box = rtrim(rtrim(number_format($m2_box, 2, ',', '.'), '0'), ',');
    $fmt_actual = rtrim(rtrim(number_format($calc_m2, 2, ',', '.'), '0'), ',');

    $quote_json = (string) ($advisor_meta['quote_json'] ?? '');
    $spec_json  = (string) ($advisor_meta['spec_json'] ?? '');
    ?>
    <article class="drv-aif-card"
             data-product-id="<?php echo esc_attr((string) $id); ?>"
             data-sku="<?php echo esc_attr($sku); ?>"
             data-price-state="<?php echo esc_attr($price_state); ?>">
        <div class="drv-aif-card__media">
            <a class="drv-aif-card__media-link" href="<?php echo esc_url(untrailingslashit($url)); ?>" tabindex="-1" aria-hidden="true">
                <img class="drv-aif-card__img"
                     src="<?php echo esc_url($img_primary); ?>"
                     width="400"
                     height="300"
                     decoding="async"
                     <?php echo $priority ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
                     alt="<?php echo esc_attr($title); ?>">
            </a>

            <div class="drv-aif-card__badges-top">
                <div class="drv-aif-card__badges-row">
                    <?php if ($sku !== '') : ?>
                        <span class="drv-aif-badge drv-aif-badge--sku u-tabular"><?php echo esc_html($sku); ?></span>
                    <?php endif; ?>
                    <span class="drv-aif-badge drv-aif-badge--match u-tabular"><?php echo esc_html($match_score . '% khớp'); ?></span>
                </div>
                <span class="drv-aif-badge <?php echo esc_attr($is_out ? 'drv-aif-badge--order' : 'drv-aif-badge--stock'); ?>">
                    <?php echo esc_html($stock_badge); ?>
                </span>
            </div>

            <?php if ($dcof_level > 0 || $water_label !== '') : ?>
                <div class="drv-aif-card__badges-bottom">
                    <?php if ($dcof_level > 0) : ?>
                        <span class="drv-aif-badge drv-aif-badge--dcof">
                            <i class="ph-fill ph-shield-check" aria-hidden="true"></i>
                            <span><?php echo esc_html('DCOF R' . $dcof_level); ?></span>
                        </span>
                    <?php else : ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if ($water_label !== '') : ?>
                        <span class="drv-aif-badge drv-aif-badge--dark"><?php echo esc_html($water_label); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="drv-aif-card__body">
            <<?php echo esc_attr($heading_tag); ?> class="drv-aif-card__title">
                <a class="drv-aif-card__title-link" href="<?php echo esc_url(untrailingslashit($url)); ?>">
                    <?php echo esc_html($title); ?>
                </a>
            </<?php echo esc_attr($heading_tag); ?>>

            <div class="drv-aif-card__specs">
                <?php if ($size_label !== '') : ?>
                    <span class="drv-aif-spec-pill"><?php echo esc_html($size_label); ?></span>
                <?php endif; ?>
                <?php if ($surf_label !== '') : ?>
                    <span class="drv-aif-spec-pill"><?php echo esc_html($surf_label); ?></span>
                <?php endif; ?>
                <span class="drv-aif-spec-pill u-tabular">
                    <?php echo esc_html($per_box . ' ' . $unit_label . '/thùng · ' . $fmt_m2_box . 'm²'); ?>
                </span>
            </div>

            <?php if ($why_text !== '') : ?>
                <p class="drv-aif-card__desc"><?php echo esc_html($why_text); ?></p>
            <?php endif; ?>

            <div class="drv-aif-card__calc">
                <div class="drv-aif-card__calc-grid">
                    <div>
                        <div class="drv-aif-card__calc-label">
                            <?php echo esc_html($is_sale ? 'Đơn giá ưu đãi' : 'Đơn giá niêm yết'); ?>
                        </div>
                        <?php if ($is_quote) : ?>
                            <div class="drv-aif-card__price drv-aif-card__price--quote">Liên hệ báo giá</div>
                        <?php else : ?>
                            <div class="drv-aif-card__price u-tabular">
                                <?php echo esc_html(number_format($price_m2, 0, ',', '.')); ?>đ<span>/m²</span>
                            </div>
                            <?php if ($est_total !== null) : ?>
                                <div class="drv-aif-card__calc-sub u-tabular">
                                    <?php echo esc_html('Tạm tính ' . number_format($est_total, 0, ',', '.') . 'đ'); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="drv-aif-card__calc-right">
                        <div class="drv-aif-card__boxes u-tabular"><?php echo esc_html($calc_boxes . ' thùng'); ?></div>
                        <div class="drv-aif-card__calc-sub u-tabular">
                            <?php echo esc_html($calc_pieces . ' ' . $unit_label . ' · phủ ' . $fmt_actual . ' m²'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="drv-aif-card__actions">
                <button type="button"
                        class="drv-btn drv-btn--outline drv-btn--sm"
                        data-aif-open-spec="<?php echo esc_attr($spec_json); ?>">
                    <i class="ph-bold ph-sliders-horizontal drv-icon--xs" aria-hidden="true"></i>
                    <span>Thông số</span>
                </button>
                <button type="button"
                        class="drv-btn drv-btn--primary drv-btn--sm"
                        data-drv-ai-add-quote="<?php echo esc_attr($quote_json); ?>"
                        data-aif-quote-boxes="<?php echo esc_attr((string) $calc_boxes); ?>">
                    <i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i>
                    <span>Thêm báo giá</span>
                </button>
            </div>
        </div>
    </article>
    <?php
    return;
}

$card_classes = ['drv-card', 'drv-card--quote'];
if ($is_out) {
    $card_classes[] = 'drv-card--out';
}
if ($is_quote) {
    $card_classes[] = 'drv-card--quote-only';
} else {
    $card_classes[] = 'drv-card--priced';
    if ($is_sale) {
        $card_classes[] = 'drv-card--sale';
    }
}

$badges = [];
if ($is_out) {
    $badges[] = ['text' => 'Tạm hết kho', 'cls' => 'drv-card__chip--label'];
} else {
    if ($is_sale && $old_price_m2 > 0) {
        $pct      = (int) round((($old_price_m2 - $price_m2) / $old_price_m2) * 100);
        $badges[] = ['text' => '-' . $pct . '%', 'cls' => 'drv-card__chip--sale'];
    }
    if ($low_stock) {
        $badges[] = ['text' => 'Sắp hết lô', 'cls' => 'drv-card__chip--label'];
    } elseif ($card_label !== '') {
        $badges[] = ['text' => $card_label, 'cls' => 'drv-card__chip--label'];
    }
}
?>
<article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
         <?php echo $shelf !== '' ? 'data-showroom="true"' : ''; ?>
         data-product-id="<?php echo esc_attr((string) $id); ?>"
         data-sku="<?php echo esc_attr($sku); ?>"
         data-price-state="<?php echo esc_attr($price_state); ?>"
         data-price-m2="<?php echo esc_attr((string) $price_m2); ?>"
         data-default-pieces="<?php echo esc_attr((string) $quick_pieces); ?>"
         data-default-m2="<?php echo esc_attr((string) $default_m2); ?>">
    <div class="drv-card__media">
        <?php if ($badges !== []) : ?>
            <div class="drv-card__badges">
                <?php foreach ($badges as $b) : ?>
                    <span class="drv-card__chip <?php echo esc_attr($b['cls']); ?>"><?php echo esc_html($b['text']); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <img class="drv-card__img drv-card__img--primary"
             src="<?php echo esc_url($img_primary); ?>"
             width="400"
             height="400"
             decoding="async"
             <?php echo $priority ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
             alt="<?php echo esc_attr($title); ?>">
        <?php if ($img_hover !== '') : ?>
            <img class="drv-card__img drv-card__img--hover"
                 src="<?php echo esc_url($img_hover); ?>"
                 width="400"
                 height="400"
                 loading="lazy"
                 decoding="async"
                 alt="">
        <?php endif; ?>
    </div>

    <div class="drv-card__body">
        <div class="drv-card__head">
            <div class="drv-card__tags">
                <?php foreach (array_slice($tags, 0, 2) as $t) : ?>
                    <span class="drv-card__tag"><?php echo esc_html((string) $t); ?></span>
                <?php endforeach; ?>
            </div>
            <?php if ($sku !== '') : ?>
                <span class="drv-card__sku u-tabular"><?php echo esc_html($sku); ?></span>
            <?php endif; ?>
        </div>

        <<?php echo esc_attr($heading_tag); ?> class="drv-card__title">
            <a class="drv-card__link" href="<?php echo esc_url(untrailingslashit($url)); ?>"><?php echo esc_html($title); ?></a>
        </<?php echo esc_attr($heading_tag); ?>>

        <?php
        $avail_override = trim((string) ($d['avail_text'] ?? ''));
        ?>
        <p class="drv-card__avail">
            <?php if ($avail_override !== '') : ?>
                <?php echo esc_html($avail_override); ?>
            <?php elseif ($context === 'deals' && !$is_out && $stock_boxes !== null && $stock_boxes > 0) : ?>
                <?php $fmt_stock_m2 = rtrim(rtrim(number_format($stock_boxes * $m2_box, 2, ',', '.'), '0'), ','); ?>
                Còn <strong><?php echo esc_html($stock_boxes . ' thùng'); ?></strong> (<?php echo esc_html($fmt_stock_m2 . ' m²'); ?>)<?php echo $shelf !== '' ? esc_html(' · Kệ ' . $shelf) : ''; ?>
            <?php elseif ($is_out) : ?>
                Đặt trước theo lô sản xuất
            <?php elseif ($shelf !== '') : ?>
                Sẵn mẫu thật tại <strong><?php echo esc_html('Kệ ' . $shelf); ?></strong>
            <?php else : ?>
                Sẵn hàng tại <strong>Kho TP.HCM</strong>
            <?php endif; ?>
        </p>

        <div class="drv-card__footer">
            <?php
            drv_ui('price', [
                'product' => [
                    'price_state'      => $price_state,
                    'price_m2'         => $price_m2,
                    'regular_price_m2' => $old_price_m2,
                    'price_piece'      => $price_piece,
                    'pieces_per_box'   => $per_box,
                    'm2_per_box'       => $m2_box,
                    'unit_label'       => $unit_label,
                    'stock_boxes'      => $stock_boxes,
                    'in_stock'         => $is_in_stock,
                ],
            ]);
            ?>

            <?php if ($is_quote || $is_out) : ?>
                <button type="button"
                        class="drv-btn drv-btn--primary drv-card__buy"
                        data-drv-action="quote-add"
                        data-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-title="<?php echo esc_attr($title); ?>"
                        data-drv-pieces="<?php echo esc_attr((string) $quick_pieces); ?>"
                        data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                        aria-label="<?php echo esc_attr('Thêm ' . ($sku !== '' ? $sku : $title) . ' vào danh sách báo giá'); ?>">
                    <?php drv_ui('icon', ['name' => 'plus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    <span><?php echo esc_html($is_out ? 'Báo giá lô mới' : 'Thêm báo giá'); ?></span>
                </button>
            <?php else : ?>
                <button type="button"
                        class="drv-btn drv-btn--primary drv-card__buy"
                        data-drv-action="add-to-cart"
                        data-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-pieces="<?php echo esc_attr((string) $quick_pieces); ?>"
                        data-drv-open-drawer="true"
                        aria-label="<?php echo esc_attr('Thêm ' . ($sku !== '' ? $sku : $title) . ' vào giỏ hàng'); ?>">
                    <?php drv_ui('icon', ['name' => 'shopping-cart-simple', 'size' => 'xs']); ?>
                    <span>Thêm giỏ</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
