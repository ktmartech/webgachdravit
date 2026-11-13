<?php
declare(strict_types=1);

use Dravit\Seo\Breadcrumbs;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$raw_items = isset($args['items']) && is_array($args['items']) && $args['items'] !== []
    ? $args['items']
    : Breadcrumbs::getCurrentTrail();

$items = [];
foreach ($raw_items as $raw_item) {
    if (!is_array($raw_item)) {
        continue;
    }
    $label = trim((string) ($raw_item['label'] ?? ''));
    if ($label === '') {
        continue;
    }
    $items[] = [
        'label' => $label,
        'url'   => isset($raw_item['url']) && is_string($raw_item['url']) ? trim($raw_item['url']) : '',
    ];
}

if ($items === []) {
    return;
}

$variant = sanitize_key((string) ($args['variant'] ?? ''));
$extra   = trim((string) ($args['class'] ?? ''));
$classes = ['drv-breadcrumb'];

if ($variant !== '' && in_array($variant, ['bar', 'inverse'], true)) {
    $classes[] = 'drv-breadcrumb--' . $variant;
}
if ($extra !== '') {
    foreach (preg_split('/\s+/', $extra) ?: [] as $cls) {
        $clean = sanitize_html_class($cls);
        if ($clean !== '') {
            $classes[] = $clean;
        }
    }
}

$last_idx = count($items) - 1;
?>
<nav class="<?php echo esc_attr(implode(' ', $classes)); ?>" aria-label="Đường dẫn">
    <ol class="drv-breadcrumb__list">
        <?php foreach ($items as $idx => $item) :
            $is_end = ($idx === $last_idx);
            ?>
            <li class="drv-breadcrumb__item<?php echo $is_end ? ' drv-breadcrumb__item--current' : ''; ?>">
                <?php if (!$is_end && $item['url'] !== '') : ?>
                    <a href="<?php echo esc_url($item['url']); ?>" class="drv-breadcrumb__link"><?php echo esc_html($item['label']); ?></a>
                <?php else : ?>
                    <span class="drv-breadcrumb__current" aria-current="page"><?php echo esc_html($item['label']); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
