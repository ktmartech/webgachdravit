<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<section class="l-container l-section">
    <h1><?php echo esc_html('Gạch Ốp Lát Chính Hãng DRAVIT'); ?></h1>
    <p><?php echo esc_html((string) drv_setting('company.tagline', 'Thẩm mỹ từ những đường nét nhỏ nhất.')); ?></p>
</section>
<?php
get_footer();

