<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Hero gọn của khu Cẩm nang (mockup `07-blog-archive.html` Section 1): breadcrumb + link công cụ + H1.
 *
 * @var array<string, mixed> $args
 */
$title      = trim((string) ($args['title'] ?? ''));
$tools_link = array_key_exists('tools_link', $args) ? (bool) $args['tools_link'] : true;
?>
<section class="drv-blog-hero" aria-labelledby="blog-page-title">
    <div class="l-container">
        <div class="drv-blog-hero__top">
            <?php drv_ui('breadcrumb'); ?>

            <?php if ($tools_link) : ?>
                <a href="<?php echo esc_url(untrailingslashit(home_url('/cam-nang')) . '#quick-tools'); ?>" class="drv-link u-text-xs u-hide-mobile">
                    <i class="ph ph-calculator drv-icon--xs" aria-hidden="true"></i>
                    <span>Máy tính số viên &amp; Bảng chống trượt R9–R13</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="drv-blog-hero__bar">
            <h1 id="blog-page-title" class="drv-blog-hero__title"><?php echo esc_html(mb_strtoupper($title)); ?></h1>
        </div>
    </div>
</section>
