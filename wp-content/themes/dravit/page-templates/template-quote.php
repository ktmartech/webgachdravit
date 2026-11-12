<?php
/**
 * Template Name: Danh sách Báo giá BOQ
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<section class="l-container l-section">
    <h1><?php the_title(); ?></h1>
    <?php drv_section('boq-workspace'); ?>
</section>
<?php
get_footer();

