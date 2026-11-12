<?php
declare(strict_types=1);

use Dravit\Core\Template;

defined('ABSPATH') || exit;

do_action('litespeed_tag_add', 'drv_global_shell');

$is_distraction_free_checkout = drv_is_woo_active()
    && function_exists('is_checkout')
    && is_checkout()
    && (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received'));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a href="#main-content" class="drv-skip-link">Chuyển đến nội dung chính</a>
<?php
if ($is_distraction_free_checkout) {
    Template::render_part('template-parts/checkout/topbar');
} else {
    Template::render_part('template-parts/header/site-header');
}
