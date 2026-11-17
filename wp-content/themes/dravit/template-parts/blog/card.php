<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Card bài viết Cẩm nang (mockup `07-blog-archive.html` → `.drv-post-card`), cả thẻ là 1 link kéo dãn qua tiêu đề.
 *
 * @var array<string, mixed> $args
 */
$card = isset($args['card']) && is_array($args['card']) ? $args['card'] : [];
if ($card === []) {
    return;
}

$heading_tag   = in_array(($args['heading_tag'] ?? 'h3'), ['h2', 'h3', 'h4'], true) ? (string) $args['heading_tag'] : 'h3';
$priority_load = !empty($args['priority_load']);
$is_feature    = !empty($args['feature']);
$show_author   = !empty($args['show_author']);

$title         = (string) ($card['title'] ?? '');
$url           = (string) ($card['url'] ?? '');
$excerpt       = (string) ($card['excerpt'] ?? '');
$cat_name      = (string) ($card['cat_name'] ?? '');
$reading_label = (string) ($card['reading_label'] ?? '');
$author_name   = (string) ($card['author_name'] ?? '');
$img_url       = (string) ($card['img_url'] ?? '');
$img_alt       = (string) ($card['img_alt'] ?? $title);
$silo_pills    = isset($card['silo_pills']) && is_array($card['silo_pills']) ? $card['silo_pills'] : [];
$extra_class   = trim((string) ($args['class'] ?? ''));
$meta          = $reading_label . ($show_author && $author_name !== '' ? ' · ' . $author_name : '');
?>
<article class="drv-post-card<?php echo $is_feature ? ' drv-post-card--feature' : ''; ?><?php echo $extra_class !== '' ? ' ' . esc_attr($extra_class) : ''; ?>">
    <div class="drv-post-card__media">
        <img src="<?php echo esc_url($img_url); ?>"
             alt="<?php echo esc_attr($img_alt); ?>"
             width="<?php echo $is_feature ? '640' : '400'; ?>"
             height="<?php echo $is_feature ? '360' : '267'; ?>"
             <?php echo $priority_load ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
             decoding="async">
    </div>

    <div class="drv-post-card__body">
        <?php if ($cat_name !== '') : ?>
            <span class="drv-post-card__cat"><?php echo esc_html($cat_name); ?></span>
        <?php endif; ?>

        <<?php echo esc_attr($heading_tag); ?> class="drv-post-card__title">
            <a href="<?php echo esc_url($url); ?>" class="drv-post-card__link<?php echo $is_feature ? ' u-text-navy' : ''; ?>"><?php echo esc_html($title); ?></a>
        </<?php echo esc_attr($heading_tag); ?>>

        <?php if ($excerpt !== '') : ?>
            <p class="drv-post-card__excerpt"><?php echo esc_html($excerpt); ?></p>
        <?php endif; ?>

        <?php if ($meta !== '') : ?>
            <span class="drv-post-card__meta"><?php echo esc_html($meta); ?></span>
        <?php endif; ?>
    </div>

    <?php if ($silo_pills !== []) : ?>
        <div class="drv-cro-pills">
            <?php if ($is_feature) : ?>
                <span class="u-text-muted">Vật tư:</span>
            <?php endif; ?>
            <?php foreach (array_slice($silo_pills, 0, 2) as $pill) : ?>
                <?php if (!is_array($pill)) {
                    continue;
                } ?>
                <a href="<?php echo esc_url((string) ($pill['url'] ?? '')); ?>" class="drv-cro-pill-tag">
                    <span><?php echo esc_html((string) ($pill['label'] ?? '')); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
