<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$text = trim((string) ($args['text'] ?? ($args['label'] ?? '')));
if ($text === '') {
    return;
}

$variant   = (string) ($args['variant'] ?? 'default');
$icon      = isset($args['icon']) && is_string($args['icon']) ? trim($args['icon']) : '';
$extra_cls = trim((string) ($args['class'] ?? ''));

$classes = ['drv-badge'];
if (in_array($variant, ['sale', 'showroom', 'moss', 'champagne', 'outline', 'success', 'warning'], true)) {
    $classes[] = 'drv-badge--' . $variant;
}
if ($extra_cls !== '') {
    $classes[] = $extra_cls;
}
?>
<span class="<?php echo esc_attr(implode(' ', $classes)); ?>">
    <?php if ($icon !== '') : ?>
        <?php drv_ui('icon', ['name' => $icon, 'size' => 'xs']); ?>
    <?php endif; ?>
    <span><?php echo esc_html($text); ?></span>
</span>

