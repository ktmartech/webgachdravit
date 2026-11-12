<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$shown    = max(0, (int) ($args['shown'] ?? 0));
$total    = max(0, (int) ($args['total'] ?? 0));
$next_url = isset($args['next_url']) && is_string($args['next_url']) && $args['next_url'] !== '' ? $args['next_url'] : null;
$per_page = max(1, (int) ($args['per_page'] ?? 24));

if ($total <= 0) {
    return;
}

$shown_capped = min($shown, $total);
$pct          = (int) round(($shown_capped / $total) * 100);
$remaining    = max(0, $total - $shown_capped);
$next_batch   = min($per_page, $remaining);
?>
<div class="drv-load-more-wrap" data-drv-load-more-wrap>
    <p class="u-text-xs u-text-muted u-tabular">
        Đang hiển thị <strong><?php echo esc_html((string) $shown_capped); ?></strong> / <strong><?php echo esc_html((string) $total); ?></strong> mẫu gạch
    </p>
    <div class="drv-load-more__progress" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $pct); ?>" aria-valuemin="0" aria-valuemax="100" style="--_pct: <?php echo esc_attr($pct . '%'); ?>">
        <span class="drv-load-more__bar"></span>
    </div>
    <?php if ($remaining > 0 && $next_url !== null) : ?>
        <a href="<?php echo esc_url($next_url); ?>" rel="next" class="drv-btn drv-btn--outline" data-drv-load-more>
            <span>Xem thêm <?php echo esc_html((string) $next_batch); ?> mẫu gạch</span>
            <?php drv_ui('icon', ['name' => 'arrow-down', 'size' => 'xs']); ?>
        </a>
    <?php endif; ?>
</div>

