<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$label     = trim((string) ($args['label'] ?? ''));
if ($label === '') {
    return;
}

$href      = isset($args['href']) && is_string($args['href']) && $args['href'] !== '' ? $args['href'] : null;
$count     = isset($args['count']) && is_numeric($args['count']) ? (int) $args['count'] : null;
$active    = !empty($args['active']);
$disabled  = !empty($args['disabled']);
$square    = !empty($args['square']);
$icon      = isset($args['icon']) && is_string($args['icon']) ? trim($args['icon']) : '';
$action    = isset($args['action']) && is_string($args['action']) ? trim($args['action']) : '';
$extra_cls = trim((string) ($args['class'] ?? ''));
$raw_attrs = isset($args['attrs']) && is_array($args['attrs']) ? $args['attrs'] : [];

$classes = ['drv-chip'];
if ($square) {
    $classes[] = 'drv-chip--square';
}
if ($active) {
    $classes[] = 'is-active';
}
if ($extra_cls !== '') {
    $classes[] = $extra_cls;
}

$attr_pairs = ['class="' . esc_attr(implode(' ', $classes)) . '"'];
if ($action !== '') {
    $attr_pairs[] = 'data-drv-action="' . esc_attr($action) . '"';
}
foreach ($raw_attrs as $k => $v) {
    $safe_k = (string) preg_replace('/[^a-zA-Z0-9_:-]/', '', (string) $k);
    if ($safe_k !== '' && is_scalar($v)) {
        $attr_pairs[] = sprintf('%s="%s"', $safe_k, esc_attr((string) $v));
    }
}

ob_start();
if ($icon !== '') {
    drv_ui('icon', ['name' => $icon, 'size' => 'xs']);
}
echo '<span>' . esc_html($label) . '</span>';
if ($count !== null && $count > 0) {
    printf('<span class="drv-chip__count u-tabular">%d</span>', $count);
}
$inner = (string) ob_get_clean();

if ($href !== null && !$disabled) {
    if ($active) {
        $attr_pairs[] = 'aria-current="page"';
    }
    printf(
        '<a href="%s" %s>%s</a>',
        esc_url($href),
        implode(' ', $attr_pairs),
        $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    );
    return;
}

$attr_pairs[] = 'aria-pressed="' . ($active ? 'true' : 'false') . '"';
if ($disabled) {
    $attr_pairs[] = 'disabled';
}
printf(
    '<button type="button" %s>%s</button>',
    implode(' ', $attr_pairs),
    $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

