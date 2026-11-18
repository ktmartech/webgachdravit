<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */
declare(strict_types=1);

use Dravit\Catalog\ProductViewData;
use Dravit\Core\Template;

defined('ABSPATH') || exit;

global $product;

if (!$product instanceof \WC_Product && function_exists('wc_get_product')) {
    $product = wc_get_product(get_the_ID());
}

if (!$product instanceof \WC_Product) {
    return;
}

if (post_password_required()) {
    echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

$view = ProductViewData::forProduct($product);
?>
<div id="product-<?php the_ID(); ?>"
     <?php wc_product_class('drv-pdp-main', $product); ?>
     data-drv-pdp-root
     data-product-id="<?php echo esc_attr((string) $view['id']); ?>"
     data-sku="<?php echo esc_attr((string) $view['sku']); ?>"
     data-price-state="<?php echo esc_attr((string) $view['price_state']); ?>">
    <div class="l-container">
        <?php if (function_exists('wc_print_notices')) : ?>
            <div class="drv-pdp-notices">
                <?php wc_print_notices(); ?>
            </div>
        <?php endif; ?>

        <div class="drv-pdp-layout">
            <div class="drv-pdp-left">
                <?php
                Template::render_part('template-parts/product/gallery', [
                    'product' => $product,
                    'view'    => $view,
                ]);
                ?>
            </div>

            <aside class="drv-pdp-right" aria-label="Thông tin giá, quy đổi số lượng và thông số kỹ thuật">
                <?php
                Template::render_part('template-parts/product/buy-box', [
                    'product' => $product,
                    'view'    => $view,
                ]);

                Template::render_part('template-parts/product/spec-panel', [
                    'product' => $product,
                    'view'    => $view,
                ]);
                ?>
            </aside>
        </div>

        <div class="drv-pdp-bottom">
            <?php
            if (!empty($view['similar_ids'])) {
                $primary_cat = $view['primary_cat'];
                drv_section('product-grid-block', [
                    'mode'        => 'custom',
                    'framed'      => true,
                    'section_id'  => 'sec-similar-products',
                    'eyebrow'     => 'Gợi ý đồng bộ không gian',
                    'title'       => 'Các mẫu gạch tương tự',
                    'description' => 'Thuộc nhóm ' . ($primary_cat['name'] ?? 'Gạch ốp lát'),
                    'cta_label'   => 'Xem thêm ' . mb_strtolower((string) ($primary_cat['name'] ?? 'danh mục')),
                    'cta_path'    => '/' . ltrim((string) ($primary_cat['slug'] ?? 'gach'), '/'),
                    'product_ids' => $view['similar_ids'],
                    'limit'       => 8,
                    'columns'     => 4,
                    'show_notice' => false,
                ]);
            }

            drv_section('product-grid-block', [
                'mode'               => 'viewed',
                'framed'             => true,
                'section_id'         => 'sec-recently-viewed',
                'title'              => 'Sản phẩm đã xem gần đây',
                'cta_label'          => '',
                'current_product_id' => (int) $view['id'],
                'limit'              => 8,
                'columns'            => 4,
                'show_notice'        => false,
            ]);
            ?>
        </div>
    </div>
</div>

<?php
Template::render_part('template-parts/product/consult-modal', [
    'product_id' => (int) $product->get_id(),
    'sku'        => (string) $product->get_sku(),
    'name'       => (string) $product->get_name(),
]);

Template::render_part('template-parts/product/sticky-bar', [
    'product' => $product,
    'view'    => $view,
]);
