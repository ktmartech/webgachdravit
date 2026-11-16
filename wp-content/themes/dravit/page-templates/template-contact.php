<?php
/**
 * Template Name: Liên hệ & Showroom
 *
 * Trang `/lien-he` (`09-contact.html`): thông tin pháp nhân, showroom và form Lead `contact_page` (D-31).
 *
 * @package Dravit
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<main id="main-content" class="drv-page drv-page--contact">
    <?php drv_section('contact-hub', ['title' => get_the_title() ?: 'Liên hệ & Showroom DRAVIT']); ?>
</main>
<?php
get_footer();
