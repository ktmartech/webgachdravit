<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<section class="l-container l-section">
    <h1><?php echo esc_html('Không tìm thấy trang (404)'); ?></h1>
    <p><?php echo esc_html('Đường dẫn bạn truy cập không tồn tại hoặc đã được chuyển hướng.'); ?></p>
</section>
<?php
get_footer();

