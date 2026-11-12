<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$rows        = isset($args['rows']) && is_array($args['rows']) ? $args['rows'] : [];
$metrics     = isset($args['metrics']) && is_array($args['metrics']) ? $args['metrics'] : [];
$total_label = (string) ($args['total_label'] ?? 'Tổng tạm tính:');
$total_html  = (string) ($args['total_html'] ?? '0đ');
$footnote    = isset($args['footnote']) && is_string($args['footnote']) ? trim($args['footnote']) : '';
$sticky      = !empty($args['sticky']);
?>
<div class="drv-summary-box<?php echo $sticky ? ' drv-summary-box--sticky' : ''; ?>" aria-live="polite">
    <?php if ($metrics !== []) : ?>
        <div class="drv-cart-metrics">
            <?php foreach ($metrics as $m) :
                if (!is_array($m)) {
                    continue;
                }
                ?>
                <div class="drv-cart-metric">
                    <span class="drv-cart-metric__label"><?php echo esc_html((string) ($m['label'] ?? '')); ?></span>
                    <span class="drv-cart-metric__val"><?php echo esc_html((string) ($m['value'] ?? '')); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($rows as $row) :
        if (!is_array($row)) {
            continue;
        }
        $r_label   = (string) ($row['label'] ?? '');
        $r_val     = (string) ($row['value_html'] ?? ($row['value'] ?? ''));
        $is_muted  = !empty($row['muted']);
        $highlight = !empty($row['highlight']);
        ?>
        <div class="drv-summary-row">
            <span class="<?php echo $is_muted ? 'u-text-muted' : ''; ?>"><?php echo esc_html($r_label); ?></span>
            <span class="<?php echo $highlight ? 'u-text-moss u-bold' : 'u-bold'; ?>"><?php echo wp_kses_post($r_val); ?></span>
        </div>
    <?php endforeach; ?>

    <div class="drv-summary-row drv-summary-row--total">
        <span><?php echo esc_html($total_label); ?></span>
        <span class="u-text-sale u-tabular"><?php echo wp_kses_post($total_html); ?></span>
    </div>

    <?php if ($footnote !== '') : ?>
        <p class="u-text-xs u-text-muted"><?php echo esc_html($footnote); ?></p>
    <?php endif; ?>
</div>

