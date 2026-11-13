<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Khối bài viết hướng dẫn chọn gạch chuẩn SEO chân trang danh mục (`drv_seo_footer`, docs/06 §7, docs/21 Step 6).
 * Chỉ hiển thị trên trang 1 sạch không có tham số lọc để tránh trùng lặp nội dung.
 *
 * @var array<string, mixed> $args
 */
$content = trim((string) ($args['content'] ?? ''));
$heading = trim((string) ($args['heading'] ?? 'Kinh nghiệm chọn quy cách & thi công đồng lô'));

if ($content === '') {
    return;
}
?>
<section class="l-section l-section--white drv-seo-footer-sec" aria-labelledby="drv-seo-footer-heading">
    <div class="l-container">
        <div class="drv-seo-footer" data-drv-seo-expandable>
            <h2 id="drv-seo-footer-heading" class="drv-seo-footer__title"><?php echo esc_html($heading); ?></h2>
            <div class="drv-seo-footer__content drv-prose u-text-sm" data-seo-footer-body>
                <?php echo wp_kses_post(wpautop($content)); ?>
            </div>
            <button type="button"
                    class="drv-btn drv-btn--outline drv-btn--sm drv-seo-footer__toggle"
                    data-seo-footer-toggle
                    aria-expanded="false"
                    hidden>
                <span data-seo-footer-toggle-label>Xem đầy đủ hướng dẫn</span>
                <i class="ph ph-caret-down drv-icon--xs" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</section>

