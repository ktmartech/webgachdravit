<?php
/**
 * Kiểm chứng tự động toàn bộ điều kiện nghiệm thu Gate 0 (Step 0).
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit("Script này phải được chạy qua WP-CLI eval-file.\n");
}

$checks = [];

$checks['active_theme_is_dravit'] = (get_stylesheet() === 'dravit' && get_template() === 'dravit');
$checks['wp_debug_enabled']       = (defined('WP_DEBUG') && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true);
$checks['env_is_local']           = (wp_get_environment_type() === 'local');
$checks['permalink_zero_slash']   = (get_option('permalink_structure') === '/cam-nang/%postname%');
$checks['category_base_chu_de']   = (get_option('category_base') === 'chu-de');
$checks['tag_base_the']           = (get_option('tag_base') === 'the');

$hpos_active = class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)
    && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$checks['woo_hpos_active']        = $hpos_active;
$checks['woo_currency_vnd']       = (get_option('woocommerce_currency') === 'VND' && (int) get_option('woocommerce_price_num_decimals') === 0);
$checks['woo_vn_only']            = (get_option('woocommerce_specific_allowed_countries') === ['VN']);

$cart_page     = get_post((int) get_option('woocommerce_cart_page_id'));
$checkout_page = get_post((int) get_option('woocommerce_checkout_page_id'));
$checks['classic_cart_shortcode']     = ($cart_page instanceof WP_Post && $cart_page->post_name === 'gio-hang' && str_contains($cart_page->post_content, '[woocommerce_cart]'));
$checks['classic_checkout_shortcode'] = ($checkout_page instanceof WP_Post && $checkout_page->post_name === 'thanh-toan' && str_contains($checkout_page->post_content, '[woocommerce_checkout]'));

$contact_url = get_permalink(get_page_by_path('lien-he'));
$checks['zero_trailing_slash_permalink'] = is_string($contact_url) && str_ends_with($contact_url, '/lien-he');

$rm_general = (array) get_option('rank-math-options-general', []);
$checks['rank_math_remove_cat_base'] = (($rm_general['wc_remove_category_base'] ?? '') === 'on' && ($rm_general['wc_remove_product_base'] ?? '') === 'off');

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

echo json_encode([
    'all_passed' => empty($failed),
    'checks'     => $checks,
    'failed'     => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if (!empty($failed)) {
    exit(1);
}

