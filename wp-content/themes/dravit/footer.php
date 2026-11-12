<?php
declare(strict_types=1);

use Dravit\Core\Template;

defined('ABSPATH') || exit;

do_action('litespeed_tag_add', 'drv_global_shell');

$is_distraction_free_checkout = drv_is_woo_active()
    && function_exists('is_checkout')
    && is_checkout()
    && (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received'));

if (!$is_distraction_free_checkout) {
    Template::render_part('template-parts/footer/dock');
}
?>
<footer class="drv-footer">
    <div class="l-container">
        <?php
        if (!$is_distraction_free_checkout) {
            Template::render_part('template-parts/footer/columns');
        }
        Template::render_part('template-parts/footer/bottom-bar');
        ?>
    </div>
</footer>
<?php
if (!$is_distraction_free_checkout) {
    Template::render_part('template-parts/footer/mobile-action-bar');
    Template::render_part('template-parts/header/mobile-drawer');
    Template::render_part('template-parts/footer/quote-modal');
    Template::render_part('template-parts/footer/minicart-drawer');
}
?>
<div id="drv-toast-region" class="drv-toast-region" aria-live="polite" aria-atomic="false"></div>
<?php
wp_footer();
?>
</body>
</html>
