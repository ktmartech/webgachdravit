<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$rows  = isset($args['rows']) && is_array($args['rows']) ? $args['rows'] : [];
$sku   = isset($args['sku']) && is_string($args['sku']) ? trim($args['sku']) : '';
$limit = max(0, (int) ($args['limit'] ?? 0));

if ($rows === [] && $sku === '') {
    return;
}

$visible_rows = ($limit > 0 && count($rows) > $limit) ? array_slice($rows, 0, $limit) : $rows;
?>
<table class="drv-spec-table">
    <tbody>
        <?php if ($sku !== '') : ?>
            <tr>
                <th scope="row">Mã sản phẩm (SKU)</th>
                <td>
                    <span class="l-cluster l-cluster--sm">
                        <strong class="u-tabular"><?php echo esc_html($sku); ?></strong>
                        <?php drv_ui('copy-inline', ['value' => $sku, 'aria_label' => 'Sao chép mã ' . $sku]); ?>
                    </span>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($visible_rows as $row) :
            if (!is_array($row)) {
                continue;
            }
            $th = trim((string) ($row['label'] ?? ''));
            $td = trim((string) ($row['value_html'] ?? ($row['value'] ?? '')));
            if ($th === '' || $td === '') {
                continue;
            }
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($th); ?></th>
                <td><?php echo wp_kses_post($td); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

