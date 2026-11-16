<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Khối biến thể cùng dòng `drv_series` render 100% bằng thẻ `<a href>` SSR qua `drv_ui('swatch')` (`D-26`, Test P-3).
 *
 * @var array<string, mixed> $args
 */
$swatches = isset($args['swatches']) && is_array($args['swatches']) ? $args['swatches'] : [];
if ($swatches === []) {
    return;
}
?>
<div class="drv-pdp-variants" aria-label="Tùy chọn màu sắc, kích thước và bề mặt cùng dòng gạch">
    <?php foreach ($swatches as $axis_group) :
        if (!is_array($axis_group) || empty($axis_group['items'])) {
            continue;
        }
        drv_ui('swatch', [
            'axis'                => (string) ($axis_group['axis'] ?? 'size'),
            'group_label'         => (string) ($axis_group['group_label'] ?? ''),
            'current_value_label' => (string) ($axis_group['current_value_label'] ?? ''),
            'items'               => $axis_group['items'],
        ]);
    endforeach; ?>
</div>
