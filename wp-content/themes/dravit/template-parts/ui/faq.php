<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$items      = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
$open_first = array_key_exists('open_first', $args) ? (bool) $args['open_first'] : true;
if ($items === []) {
    return;
}
?>
<div class="drv-faq">
    <?php foreach ($items as $idx => $item) :
        if (!is_array($item)) {
            continue;
        }
        $q = trim((string) ($item['q'] ?? ''));
        $a = trim((string) ($item['a'] ?? ''));
        if ($q === '' || $a === '') {
            continue;
        }
        ?>
        <details class="drv-faq__item" <?php echo ($open_first && $idx === 0) ? 'open' : ''; ?>>
            <summary class="drv-faq__q">
                <span><?php echo esc_html($q); ?></span>
                <?php drv_ui('icon', ['name' => 'caret-down', 'size' => 'sm', 'class' => 'drv-faq__caret']); ?>
            </summary>
            <div class="drv-faq__a">
                <?php echo wp_kses_post(wpautop($a)); ?>
            </div>
        </details>
    <?php endforeach; ?>
</div>

