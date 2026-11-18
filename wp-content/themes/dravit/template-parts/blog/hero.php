<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Hero gọn của khu Cẩm nang (mockup `07-blog-archive.html` Section 1): breadcrumb + H1.
 *
 * @var array<string, mixed> $args
 */
$title      = trim((string) ($args['title'] ?? ''));
?>
<section class="drv-blog-hero" aria-labelledby="blog-page-title">
    <div class="l-container">
        <div class="drv-blog-hero__top">
            <?php drv_ui('breadcrumb'); ?>
        </div>

        <div class="drv-blog-hero__bar">
            <h1 id="blog-page-title" class="drv-blog-hero__title"><?php echo esc_html(mb_strtoupper($title)); ?></h1>
        </div>
    </div>
</section>
