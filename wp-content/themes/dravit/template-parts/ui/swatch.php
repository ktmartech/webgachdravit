<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * UI Primitive Swatch SSR 3 trục (`color` | `size` | `surface`) liên kết trực tiếp qua `drv_series` (`D-26`, Test P-3).
 * Tuyệt đối không dùng `<button>` đổi SKU/giá giả phía client.
 *
 * @var array<string, mixed> $args
 */
$items               = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
$axis                = (string) ($args['axis'] ?? 'size');
$group_label         = trim((string) ($args['group_label'] ?? ''));
$current_value_label = trim((string) ($args['current_value_label'] ?? ''));

if ($items === []) {
    return;
}

$is_color = ($axis === 'color');
$wrap_cls = $is_color ? 'drv-pdp-variant-group' : 'drv-pdp-variant-row';
$list_cls = $is_color ? 'drv-pdp-color-list' : 'drv-pdp-pill-list';
?>
<div class="<?php echo esc_attr($wrap_cls); ?>" role="group" <?php echo $group_label !== '' ? 'aria-label="' . esc_attr($group_label) . '"' : ''; ?>>
    <?php if ($group_label !== '') : ?>
        <span class="drv-pdp-variant-label">
            <?php echo esc_html($group_label . ':'); ?>
            <?php if ($is_color && $current_value_label !== '') : ?>
                <strong><?php echo esc_html($current_value_label); ?></strong>
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <div class="<?php echo esc_attr($list_cls); ?>">
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

            $base_cls = $is_color ? 'drv-color-swatch' : 'drv-swatch-pill';
            $cls      = $active ? $base_cls . ' is-active' : $base_cls;

            ob_start();
            if ($is_color && $thumb_url !== '') {
                printf(
                    '<img src="%s" width="28" height="28" loading="lazy" decoding="async" alt="" class="drv-color-swatch__thumb">',
                    esc_url($thumb_url)
                );
            } elseif ($is_color && $hex !== '') {
                printf(
                    '<span class="drv-color-swatch__dot" style="--_swatch-hex: %s;" aria-hidden="true"></span>',
                    esc_attr($hex)
                );
            }
            echo '<span>' . esc_html($label) . '</span>';
            $inner = (string) ob_get_clean();

            if ($active || $url === '') {
                printf(
                    '<span class="%s" %s>%s</span>',
                    esc_attr($cls),
                    $active ? 'aria-current="page"' : '',
                    $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
                );
            } else {
                printf(
                    '<a href="%s" class="%s">%s</a>',
                    esc_url(untrailingslashit($url)),
                    esc_attr($cls),
                    $inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
                );
            }
        endforeach; ?>
    </div>
</div>
