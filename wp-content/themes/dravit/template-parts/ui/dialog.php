<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$id        = sanitize_html_class((string) ($args['id'] ?? 'drv-dialog'));
$title     = trim((string) ($args['title'] ?? ''));
$eyebrow   = isset($args['eyebrow']) && is_string($args['eyebrow']) ? trim($args['eyebrow']) : '';
$variant   = (string) ($args['variant'] ?? 'modal');
$body_html = (string) ($args['body_html'] ?? '');
$foot_html = (string) ($args['foot_html'] ?? '');
$extra_cls = trim((string) ($args['class'] ?? ''));

$classes = ['drv-dialog'];
if (in_array($variant, ['drawer-right', 'drawer-left', 'sheet-bottom'], true)) {
    $classes[] = 'drv-dialog--' . $variant;
}
if ($extra_cls !== '') {
    $classes[] = $extra_cls;
}
$title_id = $id . '-title';
?>
<dialog id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>" aria-labelledby="<?php echo esc_attr($title_id); ?>">
    <div class="drv-dialog__head">
        <div class="l-stack l-stack--xs">
            <?php if ($eyebrow !== '') : ?>
                <span class="u-text-xs u-bold u-uppercase u-text-moss"><?php echo esc_html($eyebrow); ?></span>
            <?php endif; ?>
            <h2 id="<?php echo esc_attr($title_id); ?>" class="u-text-lg"><?php echo esc_html($title); ?></h2>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng hộp thoại">
            <?php drv_ui('icon', ['name' => 'x']); ?>
        </button>
    </div>
    <div class="drv-dialog__body">
        <?php echo wp_kses_post($body_html); ?>
    </div>
    <?php if ($foot_html !== '') : ?>
        <div class="drv-dialog__foot">
            <?php echo wp_kses_post($foot_html); ?>
        </div>
    <?php endif; ?>
</dialog>

