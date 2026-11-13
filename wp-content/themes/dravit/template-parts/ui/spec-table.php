<?php
declare(strict_types=1);

use Dravit\Seo\InternalLinks;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$rows    = isset($args['rows']) && is_array($args['rows']) ? $args['rows'] : [];
$sku     = isset($args['sku']) && is_string($args['sku']) ? trim($args['sku']) : '';
$caption = isset($args['caption']) && is_string($args['caption']) ? trim($args['caption']) : '';
$limit   = max(0, (int) ($args['limit'] ?? 0));

if ($rows === [] && $sku === '') {
    return;
}

$visible_rows = ($limit > 0 && count($rows) > $limit) ? array_slice($rows, 0, $limit) : $rows;
?>
<table class="drv-spec-table">
    <?php if ($caption !== '') : ?>
        <caption class="u-sr-only"><?php echo esc_html($caption); ?></caption>
    <?php endif; ?>
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

            // IL-03: Nếu dòng thông số truyền `taxonomy` + `terms` / `term_slug`, tự động gắn link Silo tĩnh (0 `?param`).
            if ($td === '' && isset($row['taxonomy']) && is_string($row['taxonomy'])) {
                $tax = $row['taxonomy'];
                if (isset($row['terms']) && is_array($row['terms'])) {
                    $rendered_terms = [];
                    foreach ($row['terms'] as $term_item) {
                        if (!is_array($term_item)) {
                            continue;
                        }
                        $t_name = trim((string) ($term_item['name'] ?? ''));
                        $t_slug = trim((string) ($term_item['slug'] ?? ''));
                        if ($t_name === '') {
                            continue;
                        }
                        $silo_url = $t_slug !== '' ? InternalLinks::resolveTermUrl($tax, $t_slug) : null;
                        if ($silo_url !== null) {
                            $rendered_terms[] = sprintf('<a href="%s" class="drv-link">%s</a>', esc_url($silo_url), esc_html($t_name));
                        } else {
                            $rendered_terms[] = esc_html($t_name);
                        }
                    }
                    $td = implode(', ', $rendered_terms);
                } elseif (isset($row['term_slug'], $row['term_name'])) {
                    $t_name   = trim((string) $row['term_name']);
                    $t_slug   = trim((string) $row['term_slug']);
                    $silo_url = ($t_slug !== '' && $t_name !== '') ? InternalLinks::resolveTermUrl($tax, $t_slug) : null;
                    $td       = $silo_url !== null
                        ? sprintf('<a href="%s" class="drv-link">%s</a>', esc_url($silo_url), esc_html($t_name))
                        : esc_html($t_name);
                }
            }

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
