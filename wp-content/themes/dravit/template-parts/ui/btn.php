<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$label      = (string) ($args['label'] ?? '');
$href       = isset($args['href']) && is_string($args['href']) && $args['href'] !== '' ? $args['href'] : null;
$raw_type   = (string) ($args['type'] ?? 'button');
$type       = in_array($raw_type, ['button', 'submit', 'reset'], true) ? $raw_type : 'button';
$variant    = (string) ($args['variant'] ?? 'primary');
$size       = (string) ($args['size'] ?? 'md');
$icon_left  = isset($args['icon_left']) && is_string($args['icon_left']) ? trim($args['icon_left']) : '';
$icon_right = isset($args['icon_right']) && is_string($args['icon_right']) ? trim($args['icon_right']) : '';
$icon_only  = !empty($args['icon_only']);
$full       = !empty($args['full']);
$disabled   = !empty($args['disabled']);
$action     = isset($args['action']) && is_string($args['action']) ? trim($args['action']) : '';
$target     = isset($args['target']) && is_string($args['target']) ? trim($args['target']) : '';
$extra_cls  = trim((string) ($args['class'] ?? ''));
$raw_attrs  = isset($args['attrs']) && is_array($args['attrs']) ? $args['attrs'] : [];

if ($variant === 'link') {
    $classes = ['drv-link'];
} else {
    $classes = ['drv-btn'];
    if (in_array($variant, ['moss', 'outline', 'soft', 'ghost', 'amber'], true)) {
        $classes[] = 'drv-btn--' . $variant;
    }
    if (in_array($size, ['xs', 'sm', 'lg'], true)) {
        $classes[] = 'drv-btn--' . $size;
    }
    if ($icon_only) {
        $classes[] = 'drv-btn--icon';
    }
    if ($full) {
        $classes[] = 'drv-btn--full';
    }
}

if ($extra_cls !== '') {
    $classes[] = $extra_cls;
}

$attr_pairs = ['class="' . esc_attr(implode(' ', $classes)) . '"'];
if ($action !== '') {
    $attr_pairs[] = 'data-drv-action="' . esc_attr($action) . '"';
}
if ($target !== '') {
    $attr_pairs[] = 'data-drv-target="' . esc_attr($target) . '"';
}
if ($icon_only && !isset($raw_attrs['aria-label']) && $label !== '') {
    $raw_attrs['aria-label'] = $label;
}

foreach ($raw_attrs as $attr_key => $attr_val) {
    $safe_key = (string) preg_replace('/[^a-zA-Z0-9_:-]/', '', (string) $attr_key);
    if ($safe_key === '' || $attr_val === false || $attr_val === null) {
        continue;
    }
    if ($attr_val === true) {
        $attr_pairs[] = $safe_key;
        continue;
    }
    $attr_pairs[] = sprintf('%s="%s"', $safe_key, esc_attr((string) $attr_val));
}

$icon_size = ($size === 'xs' || $size === 'sm') ? 'xs' : 'sm';
ob_start();
if ($icon_left !== '') {
    drv_ui('icon', ['name' => $icon_left, 'size' => $icon_size]);
}
if (!$icon_only && $label !== '') {
    echo '<span>' . esc_html($label) . '</span>';
}
if ($icon_right !== '') {
    drv_ui('icon', ['name' => $icon_right, 'size' => $icon_size]);
}
$inner_html = (string) ob_get_clean();

if ($href !== null && !$disabled) {
    printf(
        '<a href="%s" %s>%s</a>',
        esc_url($href),
        implode(' ', $attr_pairs),
        $inner_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside buffer
    );
    return;
}

if ($disabled) {
    $attr_pairs[] = 'disabled';
}

printf(
    '<button type="%s" %s>%s</button>',
    esc_attr($type),
    implode(' ', $attr_pairs),
    $inner_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside buffer
);
