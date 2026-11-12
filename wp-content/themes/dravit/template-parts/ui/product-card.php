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
$src         = $args['product'] ?? null;
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

$card_classes = ['drv-card'];
if ($is_out) {
    $card_classes[] = 'drv-card--out';
}
if ($is_quote) {
    $card_classes[] = 'drv-card--quote';
} else {
    $card_classes[] = 'drv-card--priced';
    if ($is_sale) {
        $card_classes[] = 'drv-card--sale';
    }
}

$badges = [];
if ($is_out) {
    $badges[] = ['text' => 'Tạm hết kho', 'cls' => 'drv-card__chip--label'];
} elseif ($is_sale && $old_price_m2 > 0) {
    $pct      = (int) round((($old_price_m2 - $price_m2) / $old_price_m2) * 100);
    $sale_lbl = '-' . $pct . '%' . ($card_label !== '' ? ' ' . $card_label : '');
    $badges[] = ['text' => $sale_lbl, 'cls' => 'drv-card__chip--sale'];
} elseif ($low_stock) {
    $badges[] = ['text' => 'Sắp hết lô', 'cls' => 'drv-card__chip--label'];
} elseif ($card_label !== '') {
    $badges[] = ['text' => $card_label, 'cls' => 'drv-card__chip--label'];
}
if ($shelf !== '' && count($badges) < 2) {
    $badges[] = ['text' => 'Kệ ' . $shelf, 'cls' => 'drv-card__chip--showroom'];
}
?>
<article class="<?php echo esc_attr(implode(' ', $card_classes)); ?>"
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

        <a href="<?php echo esc_url(untrailingslashit($url)); ?>" tabindex="-1" aria-hidden="true">
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
        </a>
    </div>

    <div class="drv-card__body">
        <div class="drv-card__head">
            <div class="drv-card__tags">
                <?php foreach (array_slice($tags, 0, 3) as $t) : ?>
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

        <p class="drv-card__avail">
            <?php echo esc_html($is_out ? 'Đặt trước theo lô sản xuất' : 'Có sẵn tại kho'); ?>
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
                        class="drv-btn drv-btn--sm drv-card__buy"
                        data-drv-action="quote-add"
                        data-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-title="<?php echo esc_attr($title); ?>"
                        data-drv-pieces="<?php echo esc_attr((string) $quick_pieces); ?>"
                        aria-label="<?php echo esc_attr('Thêm ' . ($sku !== '' ? $sku : $title) . ' vào danh sách báo giá'); ?>">
                    <?php drv_ui('icon', ['name' => 'plus', 'weight' => 'bold', 'size' => 'xs']); ?>
                    <span><?php echo esc_html($is_out ? 'Báo giá lô mới' : 'Báo giá'); ?></span>
                </button>
            <?php else : ?>
                <button type="button"
                        class="drv-btn drv-btn--sm drv-card__buy"
                        data-drv-action="add-to-cart"
                        data-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-pieces="<?php echo esc_attr((string) $quick_pieces); ?>"
                        data-drv-open-drawer="true"
                        aria-label="<?php echo esc_attr('Thêm ' . ($sku !== '' ? $sku : $title) . ' vào giỏ hàng'); ?>">
                    <?php drv_ui('icon', ['name' => 'shopping-cart-simple', 'weight' => 'bold', 'size' => 'xs']); ?>
                    <span>Thêm giỏ</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
