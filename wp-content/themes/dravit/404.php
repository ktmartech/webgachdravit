<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<main id="main-content">
    <?php
    drv_section('error-404-recovery', [
        'eyebrow' => 'Mã lỗi 404',
        'heading' => 'Không tìm thấy trang bạn yêu cầu',
        'message' => 'Đường dẫn có thể đã được chuyển sang danh mục mới hoặc mẫu gạch đã ngừng phân phối. Bạn có thể tra cứu trực tiếp theo kích thước, công năng hoặc gửi ảnh mẫu cho kỹ thuật viên.',
        'is_h1'   => true,
    ]);
    ?>
</main>
<?php
get_footer();
