<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$term = $args['term'] ?? null;
if (is_object($term) && isset($term->name, $term->slug)) {
    $name  = (string) $term->name;
    $count = isset($term->count) ? (int) $term->count : 0;
    $url   = isset($args['url']) && is_string($args['url']) && $args['url'] !== ''
        ? $args['url']
        : home_url('/' . $term->slug);
} elseif (is_array($term)) {
    $name  = (string) ($term['name'] ?? '');
    $count = (int) ($term['count'] ?? 0);
    $url   = (string) ($term['url'] ?? ($args['url'] ?? ''));
} else {
    $name  = (string) ($args['name'] ?? '');
    $count = (int) ($args['count'] ?? 0);
    $url   = (string) ($args['url'] ?? '');
}

if ($name === '' || $url === '') {
    return;
}

$thumb_id  = (int) ($args['thumb_id'] ?? 0);
$thumb_url = isset($args['thumb_url']) && is_string($args['thumb_url']) ? $args['thumb_url'] : '';
$active    = !empty($args['active']);

if ($thumb_url === '' && $thumb_id > 0 && function_exists('wp_get_attachment_image_url')) {
    $thumb_url = (string) wp_get_attachment_image_url($thumb_id, 'drv_card_sm');
}
if ($thumb_url === '') {
    $thumb_url = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
}

$classes = ['drv-silo-tile'];
if ($active) {
    $classes[] = 'is-active';
}
?>
<a href="<?php echo esc_url(untrailingslashit($url)); ?>"
   class="<?php echo esc_attr(implode(' ', $classes)); ?>"
   <?php echo $active ? 'aria-current="page"' : ''; ?>>
    <img src="<?php echo esc_url($thumb_url); ?>"
         width="48"
         height="48"
         loading="lazy"
         decoding="async"
         alt="<?php echo esc_attr($name); ?>"
         class="drv-silo-tile__thumb">
    <div class="l-stack l-stack--xs">
        <span class="drv-silo-tile__name"><?php echo esc_html($name); ?></span>
        <?php if ($count > 0) : ?>
            <span class="drv-silo-tile__count u-tabular"><?php echo esc_html((string) $count); ?> mẫu</span>
        <?php endif; ?>
    </div>
</a>

