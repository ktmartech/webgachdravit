<?php
/**
 * Template Name: Liên hệ & Showroom
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<section class="l-container l-section">
    <h1><?php the_title(); ?></h1>
    <?php drv_section('contact-hub'); ?>
</section>
<?php
get_footer();

