<?php
/**
 * Template Name: Trợ Lý AI Tìm Gạch
 *
 * @package Dravit
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

$ai_enabled = (bool) drv_setting('features.ai_enabled', true);
if (!$ai_enabled) {
    status_header(404);
    nocache_headers();
}

get_header();
?>
<main id="main-content" class="drv-page drv-page--ai-finder">
    <?php if ($ai_enabled) : ?>
        <?php drv_section('ai-finder-workspace'); ?>
    <?php else : ?>
        <?php
        drv_section('error-404-recovery', [
            'eyebrow' => 'Tính năng đang bảo trì',
            'heading' => 'Trợ lý AI Tìm Gạch đang tạm đóng cập nhật',
            'message' => 'Quý khách vui lòng sử dụng Bộ lọc 14 tiêu chí kỹ thuật tại kho gạch để tra cứu mẫu sẵn kho theo không gian, kích thước và chỉ số Chống trượt R.',
            'is_h1'   => true,
        ]);
        ?>
    <?php endif; ?>
</main>
<?php
get_footer();
