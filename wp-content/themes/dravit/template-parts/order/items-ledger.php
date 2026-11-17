<?php
/**
 * Bảng dòng sản phẩm đã đặt + lưới 4 ô khối lượng (snapshot `_drv_*` trên order item — U-8).
 */
declare(strict_types=1);

use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$view  = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
$lines = (array) ($view['lines'] ?? []);
if ($lines === []) {
    return;
}

$boxes = (int) $view['total_boxes'];
?>
<div class="drv-checkout-block" id="drv-order-items-section">
    <div class="drv-checkout-block__head">
        <h2 class="drv-checkout-block__title">Chi tiết sản phẩm đã đặt (<?php echo esc_html((string) count($lines)); ?> mã)</h2>
    </div>
    <div class="drv-checkout-block__body">
        <ul class="drv-checkout-items drv-order-items" aria-label="Danh sách sản phẩm trong đơn hàng">
            <?php foreach ($lines as $line) :
                $product = $line['product'] instanceof \WC_Product ? $line['product'] : null;
                $thumb   = '';
                if ($product) {
                    $thumb = $product->get_image([56, 56], ['class' => 'drv-checkout-item__thumb', 'loading' => 'lazy', 'decoding' => 'async']);
                    if ((int) $product->get_image_id() <= 0) {
                        $fallback = PriceState::resolveFallbackImages($product->get_id());
                        if ($fallback['primary'] !== '') {
                            $thumb = sprintf('<img src="%s" width="56" height="56" loading="lazy" decoding="async" alt="" class="drv-checkout-item__thumb">', esc_url($fallback['primary']));
                        }
                    }
                }
                $sku  = (string) $line['sku'];
                $spec = ($sku !== '' ? 'SKU: ' . $sku . ' · ' : '') . (string) $line['spec'];
                ?>
                <li class="drv-checkout-item">
                    <?php echo wp_kses_post($thumb); ?>
                    <div class="drv-checkout-item__body">
                        <div class="drv-checkout-item__header-row">
                            <?php if ((string) $line['url'] !== '') : ?>
                                <a class="drv-checkout-item__name" href="<?php echo esc_url(untrailingslashit((string) $line['url'])); ?>"><?php echo esc_html((string) $line['name']); ?></a>
                            <?php else : ?>
                                <span class="drv-checkout-item__name"><?php echo esc_html((string) $line['name']); ?></span>
                            <?php endif; ?>
                            <strong class="drv-checkout-item__total u-tabular"><?php echo esc_html(drv_format_vnd((int) $line['total'])); ?></strong>
                        </div>
                        <div class="drv-checkout-item__sub-row">
                            <span class="drv-checkout-item__spec u-tabular"><?php echo esc_html($spec); ?></span>
                            <span class="drv-checkout-item__unit u-tabular"><?php echo esc_html((string) $line['unit_price']); ?></span>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="drv-cart-metrics" aria-label="Tổng hợp khối lượng đối soát">
            <div class="drv-cart-metric">
                <span class="drv-cart-metric__label">Tổng diện tích gạch</span>
                <strong class="drv-cart-metric__val u-text-moss"><?php echo esc_html(drv_format_decimal((float) $view['total_m2'], 2)); ?> m²</strong>
            </div>
            <div class="drv-cart-metric">
                <span class="drv-cart-metric__label">Tổng số thùng</span>
                <strong class="drv-cart-metric__val"><?php echo esc_html($boxes > 0 ? $boxes . ' thùng' : '—'); ?></strong>
            </div>
            <div class="drv-cart-metric">
                <span class="drv-cart-metric__label">Tổng tải trọng</span>
                <strong class="drv-cart-metric__val"><?php echo esc_html(drv_format_decimal((float) $view['total_kg'], 0)); ?> kg</strong>
            </div>
            <div class="drv-cart-metric">
                <span class="drv-cart-metric__label">Khu vực nhận hàng</span>
                <strong class="drv-cart-metric__val u-text-navy"><?php echo esc_html((string) $view['province'] ?: '—'); ?></strong>
            </div>
        </div>
    </div>
</div>
