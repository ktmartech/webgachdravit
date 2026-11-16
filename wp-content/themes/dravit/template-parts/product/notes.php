<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * 3 Lưu ý trách nhiệm thi công & vận chuyển động theo thông số từng mẫu gạch (`docs/07 §5.3`, `D-26`, `D-30`).
 *
 * @var array<string, mixed> $args
 */
$notes = isset($args['notes']) && is_array($args['notes']) ? $args['notes'] : [];
if ($notes === []) {
    return;
}
?>
<div class="drv-pdp-notes-stack">
    <?php foreach ($notes as $note) :
        if (!is_array($note)) {
            continue;
        }
        $n_title   = trim((string) ($note['title'] ?? ''));
        $n_body    = trim((string) ($note['body'] ?? ''));
        $n_variant = (string) ($note['variant'] ?? 'default');
        if ($n_title === '' || $n_body === '') {
            continue;
        }
        $item_cls = 'drv-pdp-note-item' . ($n_variant === 'warn' ? ' drv-pdp-note-item--warn' : '');
        ?>
        <div class="<?php echo esc_attr($item_cls); ?>">
            <strong class="u-text-navy"><?php echo esc_html($n_title); ?></strong>
            <span> <?php echo esc_html($n_body); ?></span>
        </div>
    <?php endforeach; ?>
</div>
