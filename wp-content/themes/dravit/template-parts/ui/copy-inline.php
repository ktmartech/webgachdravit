<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$value = trim((string) ($args['value'] ?? ''));
if ($value === '') {
    return;
}

$label      = isset($args['label']) && is_string($args['label']) ? trim($args['label']) : '';
$feedback   = (string) ($args['feedback'] ?? 'Đã chép');
$aria_label = (string) ($args['aria_label'] ?? ('Sao chép ' . $value));
?>
<button type="button"
        class="drv-btn-copy-inline"
        data-drv-action="copy-text"
        data-drv-copy-text="<?php echo esc_attr($value); ?>"
        data-drv-feedback="<?php echo esc_attr($feedback); ?>"
        aria-label="<?php echo esc_attr($aria_label); ?>">
    <?php if ($label !== '') : ?>
        <span class="u-tabular"><?php echo esc_html($label); ?></span>
    <?php endif; ?>
    <?php drv_ui('icon', ['name' => 'copy', 'size' => 'xs']); ?>
</button>

