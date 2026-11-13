<?php
/**
 * The template for displaying product content within loops
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

global $product;

if (!$product instanceof \WC_Product || !$product->is_visible()) {
    return;
}

drv_ui('product-card', [
    'product'     => $product,
    'heading_tag' => 'h2',
]);

