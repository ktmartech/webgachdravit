<?php
declare(strict_types=1);

use Dravit\Content\BlogSetup;

defined('ABSPATH') || exit;

/**
 * Sub-nav chuyên mục (chip) + ô tìm kiếm (mockup `07-blog-archive.html` Section 2).
 * Tìm kiếm là GET server-side `/?s=...&post_type=post` (docs/20 §1.3).
 *
 * @var array<string, mixed> $args
 */
$active_slug  = isset($args['active_slug']) ? trim((string) $args['active_slug']) : '';
$search_query = isset($args['search_query']) ? trim((string) $args['search_query']) : '';
$items        = BlogSetup::getSubnavItems($active_slug);
?>
<nav class="drv-blog-subnav" aria-label="Chuyên mục Cẩm nang và Tìm kiếm">
    <div class="l-container drv-blog-subnav__inner">
        <div class="drv-blog-tabs">
            <?php foreach ($items as $item) : ?>
                <a href="<?php echo esc_url($item['url']); ?>"
                   class="drv-chip<?php echo $item['active'] ? ' is-active' : ''; ?>"
                   <?php echo $item['active'] ? 'aria-current="page"' : ''; ?>>
                    <i class="ph ph-<?php echo esc_attr($item['icon']); ?> drv-icon--xs" aria-hidden="true"></i>
                    <span><?php echo esc_html($item['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="drv-blog-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <input type="hidden" name="post_type" value="post">
            <i class="ph ph-magnifying-glass drv-blog-search__icon" aria-hidden="true"></i>
            <label for="blog-search-input" class="u-sr-only">Tìm bài viết hoặc thông số kỹ thuật</label>
            <input id="blog-search-input"
                   type="search"
                   name="s"
                   class="drv-blog-search__input"
                   value="<?php echo esc_attr($search_query); ?>"
                   placeholder="Tìm chủ đề, chỉ số R10, 80x80..."
                   autocomplete="off">
        </form>
    </div>
</nav>
