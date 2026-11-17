<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Mục lục tự động `<drv-toc>` (mockup `08-blog-single.html`): Desktop Sticky có thanh tiến độ đọc, Mobile `<dialog>` bottom sheet.
 *
 * @var array<string, mixed> $args
 */
$items = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
if ($items === []) {
    return;
}

$mode  = isset($args['mode']) && $args['mode'] === 'mobile' ? 'mobile' : 'desktop';
$title = isset($args['title']) && trim((string) $args['title']) !== ''
    ? trim((string) $args['title'])
    : 'Mục lục bài viết';

$links = [];
foreach ($items as $item) {
    $id  = (string) ($item['id'] ?? '');
    $txt = (string) ($item['text'] ?? '');
    if ($id !== '' && $txt !== '') {
        $links[] = ['id' => $id, 'text' => $txt];
    }
}
if ($links === []) {
    return;
}
?>
<?php if ($mode === 'mobile') : ?>
    <dialog id="drv-mobile-toc" class="drv-dialog-bottom" aria-labelledby="mobile-toc-title">
        <div class="drv-dialog-bottom__head">
            <div class="l-cluster l-cluster--xs">
                <i class="ph-fill ph-list-bullets drv-icon--xs u-text-moss" aria-hidden="true"></i>
                <strong id="mobile-toc-title" class="u-text-sm"><?php echo esc_html(sprintf('%s (%d phần)', $title, count($links))); ?></strong>
            </div>
            <button type="button" class="drv-btn drv-btn--outline drv-btn--sm" data-drv-action="dialog-close" aria-label="Đóng mục lục" style="padding: 4px 8px; color: var(--c-ivory); border-color: rgba(255,255,255,0.3);">
                <i class="ph ph-x drv-icon--xs" aria-hidden="true"></i>
            </button>
        </div>
        <div style="padding: var(--s-3); max-height: 65vh; overflow-y: auto;">
            <drv-toc data-drv-toc data-toc-mobile="1">
                <nav class="drv-toc__nav" aria-label="Mục lục bài viết di động">
                    <?php foreach ($links as $lnk) : ?>
                        <a href="#<?php echo esc_attr($lnk['id']); ?>" class="drv-toc__link" data-toc-link="<?php echo esc_attr($lnk['id']); ?>"><?php echo esc_html($lnk['text']); ?></a>
                    <?php endforeach; ?>
                </nav>
            </drv-toc>
        </div>
    </dialog>
<?php else : ?>
    <drv-toc class="drv-toc" id="article-toc" data-drv-toc>
        <div class="drv-toc__head">
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="ph-bold ph-list-numbers drv-icon--xs" aria-hidden="true"></i>
                <span><?php echo esc_html($title); ?></span>
            </span>
            <span class="u-text-xs" data-reading-progress style="color: var(--c-champagne); font-weight: 700; font-variant-numeric: tabular-nums;">0%</span>
        </div>
        <div class="drv-toc__progress-track" aria-hidden="true">
            <div class="drv-toc__progress-fill" data-reading-progress-bar></div>
        </div>
        <nav class="drv-toc__nav" aria-label="<?php echo esc_attr($title); ?>">
            <?php foreach ($links as $lnk) : ?>
                <a href="#<?php echo esc_attr($lnk['id']); ?>" class="drv-toc__link" data-toc-link="<?php echo esc_attr($lnk['id']); ?>">
                    <span><?php echo esc_html($lnk['text']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </drv-toc>
<?php endif; ?>
