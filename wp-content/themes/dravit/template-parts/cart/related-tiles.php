<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * "Mẫu gạch phối đồng bộ cho phòng khác" cuối trang giỏ hàng (mockup `04-cart.html` Section 3, `IL-06`: chỉ gạch cùng silo).
 *
 * @var array<string, mixed> $args
 */
$ids = isset($args['ids']) && is_array($args['ids']) ? $args['ids'] : [];
if ($ids === []) {
    return;
}
?>
<section class="l-section u-pt-0" aria-labelledby="cart-related-heading">
    <div class="l-container">
        <div class="drv-pdp-panel">
            <div class="drv-pdp-panel__head">
                <h2 id="cart-related-heading" class="drv-pdp-panel__title">Mẫu gạch phối đồng bộ cho nhà tắm, phòng ngủ &amp; ban công</h2>
                <span class="u-text-xs u-text-muted">Chọn thêm gạch đồng bộ cho các phòng khác</span>
            </div>

            <div class="drv-pdp-mini-grid" data-drv-stagger>
                <?php foreach ($ids as $product_id) :
                    $product = wc_get_product((int) $product_id);
                    if (!$product instanceof \WC_Product) {
                        continue;
                    }
                    drv_ui('product-card', ['product' => $product]);
                endforeach; ?>
            </div>
        </div>
    </div>
</section>
