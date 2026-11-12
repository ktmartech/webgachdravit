<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$raw_name = (string) ($args['name'] ?? '');
$clean    = (string) preg_replace('/^ph-/', '', strtolower(trim($raw_name)));
$clean    = (string) preg_replace('/[^a-z0-9-]/', '', $clean);
if ($clean === '') {
    return;
}

$weight = (string) ($args['weight'] ?? 'regular');
$prefix = match ($weight) {
    'bold'  => 'ph-bold',
    'fill'  => 'ph-fill',
    default => 'ph',
};

$size    = (string) ($args['size'] ?? 'md');
$classes = [$prefix, 'ph-' . $clean, 'drv-icon'];
if (in_array($size, ['xs', 'sm', 'lg'], true)) {
    $classes[] = 'drv-icon--' . $size;
}

$extra_class = trim((string) ($args['class'] ?? ''));
if ($extra_class !== '') {
    $classes[] = $extra_class;
}

$label = isset($args['label']) && is_string($args['label']) ? trim($args['label']) : '';
if ($label !== '') {
    printf(
        '<i class="%s" role="img" aria-label="%s"></i>',
        esc_attr(implode(' ', $classes)),
        esc_attr($label)
    );
    return;
}

printf('<i class="%s" aria-hidden="true"></i>', esc_attr(implode(' ', $classes)));

