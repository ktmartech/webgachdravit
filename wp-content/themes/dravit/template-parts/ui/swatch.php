<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$items       = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
$axis        = (string) ($args['axis'] ?? 'size');
$group_label = trim((string) ($args['group_label'] ?? ''));
if ($items === []) {
    return;
}
?>
<div class="l-stack l-stack--xs" role="group" <?php echo $group_label !== '' ? 'aria-label="' . esc_attr($group_label) . '"' : ''; ?>>
    <?php if ($group_label !== '') : ?>
        <span class="u-text-xs u-bold u-text-muted"><?php echo esc_html($group_label); ?></span>
    <?php endif; ?>
    <div class="l-cluster l-cluster--xs">
        <?php foreach ($items as $item) :
            if (!is_array($item)) {
                continue;
            }
            $label     = trim((string) ($item['label'] ?? ''));
            $url       = trim((string) ($item['url'] ?? ''));
            $active    = !empty($item['active']);
            $thumb_url = isset($item['thumb_url']) && is_string($item['thumb_url']) ? trim($item['thumb_url']) : '';
            $hex       = isset($item['hex']) && is_string($item['hex']) ? trim($item['hex']) : '';
            if ($label === '') {
                continue;
            }

            $base_cls = ($axis === 'color') ? 'drv-color-swatch' : 'drv-swatch-pill';
            $cls      = $active ? $base_cls . ' is-active' : $base_cls;

            ob_start();
            if ($axis === 'color' && $thumb_url !== '') {
                printf(
                    '<img src="%s" width="28" height="28" loading="lazy" decoding="async" alt="" class="drv-color-swatch__thumb">',
                    esc_url($thumb_url)
                );
            } elseif ($axis === 'color' && $hex !== '') {
                printf('<span class="drv-color-swatch__dot" style="background-color:%s"></span>', esc_attr($hex));
            }
            echo '<span>' . esc_html($label) . '</span>';
            $inner = (string) ob_get_clean();

            if ($active || $url === '') {
                printf(
                    '<span class="%s" %s>%s</span>',
                    esc_attr($cls),
                    $active ? 'aria-current="page"' : '',
                    $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                );
            } else {
                printf(
                    '<a href="%s" class="%s">%s</a>',
                    esc_url(untrailingslashit($url)),
                    esc_attr($cls),
                    $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                );
            }
        endforeach; ?>
    </div>
</div>

