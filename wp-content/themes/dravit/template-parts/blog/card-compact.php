<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Thẻ ngang thu gọn cho cột giữa Bento (mockup `.drv-post-compact`).
 *
 * @var array<string, mixed> $args
 */
$card = isset($args['card']) && is_array($args['card']) ? $args['card'] : [];
if ($card === []) {
    return;
}

$heading_tag = in_array(($args['heading_tag'] ?? 'h3'), ['h2', 'h3', 'h4'], true) ? (string) $args['heading_tag'] : 'h3';
$title       = (string) ($card['title'] ?? '');
$url         = (string) ($card['url'] ?? '');
$cat_name    = (string) ($card['cat_name'] ?? '');
$reading     = (string) ($card['reading_label'] ?? '');
$img_url     = (string) ($card['img_url'] ?? '');
$img_alt     = (string) ($card['img_alt'] ?? $title);
?>
<article class="drv-post-compact">
    <div class="drv-post-compact__thumb">
        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($img_alt); ?>" width="92" height="92" loading="lazy" decoding="async">
    </div>
    <div class="drv-post-compact__content">
        <?php if ($cat_name !== '') : ?>
            <span class="drv-post-card__cat"><?php echo esc_html($cat_name); ?></span>
        <?php endif; ?>
        <<?php echo esc_attr($heading_tag); ?> class="drv-post-compact__title">
            <a href="<?php echo esc_url($url); ?>" class="drv-post-compact__link"><?php echo esc_html($title); ?></a>
        </<?php echo esc_attr($heading_tag); ?>>
        <?php if ($reading !== '') : ?>
            <span class="drv-post-card__meta"><?php echo esc_html($reading); ?></span>
        <?php endif; ?>
    </div>
</article>
