<?php
declare(strict_types=1);

use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * 1 dòng giỏ hàng `/gio-hang` (04-cart.html · docs/04 §4): Tên + quy cách · Giá/m² chính + giá/viên phụ ·
 * `<drv-qty-input>` (số nguyên viên) · Dòng quy đổi `thùng · m² · kg` LUÔN hiển thị · Cảnh báo lẻ viên (round_mode=piece).
 *
 * @var array<string, mixed> $args
 */
$line      = isset($args['line']) && is_array($args['line']) ? $args['line'] : [];
$cart_item = isset($args['cart_item']) && is_array($args['cart_item']) ? $args['cart_item'] : [];
$key       = (string) ($line['key'] ?? '');
$product   = $line['product'] ?? null;
if ($key === '' || !$product instanceof \WC_Product) {
    return;
}

$product_id = (int) ($line['product_id'] ?? $product->get_id());
$qty        = (int) ($line['qty'] ?? 1);
$unit_label = (string) ($line['unit_label'] ?? 'viên');
$ppb        = max(1, (int) ($line['pieces_per_box'] ?? 1));
$sku        = (string) $product->get_sku();
$name       = (string) apply_filters('woocommerce_cart_item_name', $product->get_name(), $cart_item, $key);
$permalink  = (string) apply_filters('woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink($cart_item) : '', $cart_item, $key);
$permalink  = $permalink !== '' ? untrailingslashit($permalink) : '';
$price_m2   = (int) ($line['price_m2'] ?? 0);
$reg_m2     = (int) ($line['regular_price_m2'] ?? 0);
$price_pc   = (int) ($line['price_piece'] ?? 0);
$is_area    = (float) ($line['area_piece'] ?? 0) > 0.0;
$is_sale    = ($line['price_state'] ?? '') === PriceState::SALE && $reg_m2 > $price_m2;
$loose      = (int) ($line['loose_pieces'] ?? 0);
$need_next  = (int) ($line['needed_for_next_box'] ?? 0);
$boxes      = (int) ($line['boxes'] ?? 0);
$m2_qty     = (float) ($line['m2_qty'] ?? 0);
$kg         = (float) ($line['kg'] ?? 0);
$shade      = (string) ($line['shade'] ?? '');
$size_label = (string) ($line['size_label'] ?? '');

$conv_parts = [(string) ($line['box_text'] ?? '')];
if ($m2_qty > 0) {
    $conv_parts[] = drv_format_decimal($m2_qty, 2) . ' m²';
}
if ($kg > 0) {
    $conv_parts[] = drv_format_decimal($kg, 1) . ' kg';
}

$thumb_html = (string) apply_filters(
    'woocommerce_cart_item_thumbnail',
    $product->get_image('woocommerce_thumbnail', ['class' => 'drv-cart-item__thumb', 'loading' => 'lazy', 'decoding' => 'async']),
    $cart_item,
    $key
);
if ((int) $product->get_image_id() <= 0) {
    $fallback = PriceState::resolveFallbackImages($product_id);
    if ($fallback['primary'] !== '') {
        $thumb_html = sprintf(
            '<img src="%s" width="80" height="80" loading="lazy" decoding="async" alt="%s" class="drv-cart-item__thumb">',
            esc_url($fallback['primary']),
            esc_attr($name)
        );
    }
}

$open_detail = $ppb > 1 && ($line['round_mode'] ?? 'box') === 'piece' && $loose > 0;
$remove_url = function_exists('wc_get_cart_remove_url') ? (string) wc_get_cart_remove_url($key) : '';
?>
<article class="drv-cart-item woocommerce-cart-form__cart-item <?php echo esc_attr((string) apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $key)); ?>" data-cart-row data-cart-key="<?php echo esc_attr($key); ?>" data-cart-sku="<?php echo esc_attr($sku); ?>">
    <div class="drv-cart-item__product">
        <?php if ($permalink !== '') : ?>
            <a href="<?php echo esc_url($permalink); ?>" tabindex="-1" aria-hidden="true"><?php echo wp_kses_post($thumb_html); ?></a>
        <?php else : ?>
            <?php echo wp_kses_post($thumb_html); ?>
        <?php endif; ?>
        <div class="drv-cart-item__info">
            <div class="drv-cart-item__meta">
                <?php if ($sku !== '') : ?>
                    <span class="drv-cart-item__meta-sku"><?php echo esc_html($sku); ?></span>
                <?php endif; ?>
                <?php if ($size_label !== '') : ?>
                    <span aria-hidden="true">·</span>
                    <span class="drv-cart-item__meta-tag"><?php echo esc_html($size_label); ?></span>
                <?php endif; ?>
                <?php if ($shade !== '') : ?>
                    <span aria-hidden="true">·</span>
                    <span class="drv-cart-item__meta-tag"><?php echo esc_html('Shade ' . $shade); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($permalink !== '') : ?>
                <a href="<?php echo esc_url($permalink); ?>" class="drv-cart-item__title" data-row-title><?php echo esc_html($name); ?></a>
            <?php else : ?>
                <span class="drv-cart-item__title" data-row-title><?php echo esc_html($name); ?></span>
            <?php endif; ?>
            <?php
            do_action('woocommerce_after_cart_item_name', $cart_item, $key);
            // Thông tin meta bổ sung của plugin khác (variation, add-ons...).
            echo wc_get_formatted_cart_item_data($cart_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC escapes internally.
            ?>
            <button type="button"
                    class="drv-cart-detail-toggle<?php echo $open_detail ? ' is-expanded' : ''; ?>"
                    data-drv-action="cart-toggle-row-detail"
                    aria-expanded="<?php echo $open_detail ? 'true' : 'false'; ?>">
                <i class="ph ph-caret-down drv-icon--xs" aria-hidden="true"></i>
                <span data-toggle-label><?php echo $open_detail ? 'Thu gọn chi tiết' : 'Xem chi tiết & quy đổi'; ?></span>
            </button>
        </div>
    </div>

    <div class="drv-cart-item__price-col">
        <span class="u-text-xs u-text-muted u-only-mobile">Đơn giá:</span>
        <?php if ($is_area && $price_m2 > 0) : ?>
            <span class="drv-cart-item__price-main u-tabular">
                <?php echo esc_html(drv_format_vnd($price_m2)); ?><small class="u-text-xs u-text-muted">/m²</small>
            </span>
            <?php if ($is_sale) : ?>
                <del class="u-text-xs u-text-muted u-tabular"><?php echo esc_html(drv_format_vnd($reg_m2) . '/m²'); ?></del>
            <?php endif; ?>
            <span class="drv-cart-item__price-sub u-tabular"><?php echo esc_html('≈ ' . drv_format_vnd($price_pc) . '/' . $unit_label); ?></span>
        <?php else : ?>
            <span class="drv-cart-item__price-main u-tabular">
                <?php echo esc_html(drv_format_vnd($price_pc)); ?><small class="u-text-xs u-text-muted"><?php echo esc_html('/' . $unit_label); ?></small>
            </span>
        <?php endif; ?>
    </div>

    <div class="drv-cart-item__qty-col">
        <?php
        if ($product->is_sold_individually()) {
            printf('<input type="hidden" name="cart[%s][qty]" value="1">', esc_attr($key));
            echo '<span class="u-text-sm u-bold">1</span>';
        } else {
            drv_ui('qty-input', [
                'line'         => $line,
                'cart_key'     => $key,
                'product_id'   => $product_id,
                'value_pieces' => $qty,
                'input_name'   => 'cart[' . $key . '][qty]',
                'input_id'     => 'drv-cart-qty-' . substr($key, 0, 10),
                'sku'          => $sku,
                'aria_label'   => sprintf('Số %s %s', $unit_label, $sku !== '' ? $sku : $name),
            ]);
        }
        ?>
    </div>

    <div class="drv-cart-item__total-col" aria-live="polite">
        <div class="drv-cart-item__total-head">
            <span class="u-text-xs u-text-muted u-only-mobile">Thành tiền:</span>
            <?php if ($remove_url !== '') : ?>
                <a
                    href="<?php echo esc_url($remove_url); ?>"
                    class="drv-cart-item__del-btn"
                    data-cart-remove
                    data-product-id="<?php echo esc_attr((string) $product_id); ?>"
                    aria-label="<?php echo esc_attr('Xóa ' . $name . ' khỏi giỏ hàng'); ?>"
                    title="Xóa mặt hàng này">
                    <?php drv_ui('icon', ['name' => 'trash']); ?>
                </a>
            <?php endif; ?>
        </div>
        <strong class="drv-cart-item__subtotal u-tabular" data-row-subtotal><?php echo esc_html(drv_format_vnd((int) ($line['line_total'] ?? 0))); ?></strong>
        <span class="u-text-xs u-text-muted u-hide-mobile">Đã gồm VAT</span>
    </div>

    <div class="drv-cart-drawer" data-cart-row-drawer<?php echo $open_detail ? '' : ' hidden'; ?>>
        <div class="drv-cart-drawer__grid">
            <?php if ($is_area) : ?>
                <div class="drv-cart-drawer__cell">
                    <span class="drv-cart-drawer__cell-title">
                        <?php drv_ui('icon', ['name' => 'package', 'size' => 'xs']); ?>
                        <span>Quy cách đóng thùng</span>
                    </span>
                    <p class="drv-cart-item__pack-spec">
                        <span>
                            <strong><?php echo esc_html(sprintf('1 thùng = %d %s = %s m²', $ppb, $unit_label, drv_format_decimal((float) ($line['m2_per_box'] ?? 0), 2))); ?></strong>
                            <?php if ((float) ($line['kg_per_box'] ?? 0) > 0) : ?>
                                <?php echo esc_html(' · ' . drv_format_decimal((float) $line['kg_per_box'], 1) . ' kg/thùng'); ?>
                            <?php endif; ?>
                        </span>
                    </p>
                </div>
            <?php endif; ?>

            <div class="drv-cart-drawer__cell">
                <span class="drv-cart-drawer__cell-title">
                    <?php drv_ui('icon', ['name' => 'tag', 'size' => 'xs']); ?>
                    <span>Đơn giá quy đổi</span>
                </span>
                <div class="drv-cart-drawer__prices u-tabular">
                    <span class="drv-cart-item__price-sub"><?php echo esc_html(drv_format_vnd($price_pc) . '/' . $unit_label); ?></span>
                    <?php if ($ppb > 1) : ?>
                        <span class="drv-cart-item__price-sub"><?php echo esc_html(drv_format_vnd($price_pc * $ppb) . '/thùng'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="drv-cart-drawer__cell">
                <span class="drv-cart-drawer__cell-title">
                    <?php drv_ui('icon', ['name' => 'arrows-left-right', 'size' => 'xs']); ?>
                    <span>Quy đổi khối lượng</span>
                </span>
                <div class="drv-cart-drawer__actions">
                    <div class="drv-cart-conv-pill u-tabular" aria-live="polite">
                        <?php drv_ui('icon', ['name' => 'scales', 'size' => 'xs']); ?>
                        <span data-row-conversion><?php echo esc_html('= ' . implode(' · ', array_filter($conv_parts))); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($ppb > 1 && ($line['round_mode'] ?? 'box') === 'piece') : ?>
            <div class="drv-cart-loose-warn" data-row-loose-warning role="status" <?php echo $loose > 0 ? '' : 'hidden'; ?>>
                <span class="l-cluster l-cluster--xs">
                    <?php drv_ui('icon', ['name' => 'warning-circle', 'weight' => 'fill', 'size' => 'xs']); ?>
                    <span data-row-loose-text>
                        <?php
                        echo esc_html(sprintf(
                            '%d thùng + %d %s lẻ: nên lấy chẵn thùng để đồng màu lô và dự phòng cắt gạch (thêm %d %s).',
                            $boxes,
                            $loose,
                            $unit_label,
                            $need_next,
                            $unit_label
                        ));
                        ?>
                    </span>
                </span>
                <span class="l-cluster l-cluster--xs">
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-qty-round-box hidden>
                        <span>Làm tròn chẵn thùng</span>
                    </button>
                    <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm" data-qty-add-box hidden>
                        <span>+1 thùng</span>
                    </button>
                </span>
            </div>
        <?php endif; ?>
    </div>
</article>
