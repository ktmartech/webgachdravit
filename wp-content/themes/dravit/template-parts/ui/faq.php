<?php
declare(strict_types=1);

use Dravit\Seo\FaqResolver;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$raw_items       = $args['items'] ?? [];
$valid_items     = FaqResolver::parseFaqItems($raw_items);
$open_first      = array_key_exists('open_first', $args) ? (bool) $args['open_first'] : true;
$register_schema = !array_key_exists('register_schema', $args) || !empty($args['register_schema']);

if ($valid_items === []) {
    return;
}

if ($register_schema && FaqResolver::getRegisteredItems() === []) {
    FaqResolver::registerItems($valid_items);
}
?>
<div class="drv-faq">
    <?php foreach ($valid_items as $idx => $item) : ?>
        <details class="drv-faq__item" <?php echo ($open_first && $idx === 0) ? 'open' : ''; ?>>
            <summary class="drv-faq__q">
                <span><?php echo esc_html($item['q']); ?></span>
                <?php drv_ui('icon', ['name' => 'caret-down', 'size' => 'sm', 'class' => 'drv-faq__caret']); ?>
            </summary>
            <div class="drv-faq__a">
                <?php echo wp_kses_post(wpautop($item['a'])); ?>
            </div>
        </details>
    <?php endforeach; ?>
</div>
