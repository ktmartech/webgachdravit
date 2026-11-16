<?php
/**
 * The Template for displaying all single products
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     1.6.4
 */
declare(strict_types=1);

use Dravit\Core\Integrations\LiteSpeedCompat;

defined('ABSPATH') || exit;

if (class_exists(LiteSpeedCompat::class)) {
    LiteSpeedCompat::add_cache_tag('drv_catalog');
    if (function_exists('get_the_ID') && get_the_ID() > 0) {
        LiteSpeedCompat::add_cache_tag('drv_product_' . get_the_ID());
    }
}

get_header();
?>
<main id="main-content" class="drv-pdp-page">
    <div class="drv-pdp-breadcrumb-bar">
        <div class="l-container">
            <?php drv_ui('breadcrumb'); ?>
        </div>
    </div>

    <?php
    while (have_posts()) :
        the_post();
        wc_get_template_part('content', 'single-product');
    endwhile;
    ?>
</main>
<?php
get_footer();
