<?php
declare(strict_types=1);

use Dravit\Core\Template;

defined('ABSPATH') || exit;

do_action('litespeed_tag_add', 'drv_global_shell');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('drv-checkout-page'); ?>>
<?php wp_body_open(); ?>
<a href="#main-content" class="drv-skip-link">Chuyển đến biểu mẫu thanh toán</a>
<?php
Template::render_part('template-parts/checkout/topbar');

