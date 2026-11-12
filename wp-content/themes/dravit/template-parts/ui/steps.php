<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$steps = isset($args['steps']) && is_array($args['steps']) ? $args['steps'] : [];
if ($steps === []) {
    return;
}
?>
<ol class="drv-cart-steps" aria-label="Tiến trình đặt hàng">
    <?php foreach ($steps as $idx => $step) :
        if (!is_array($step)) {
            continue;
        }
        $num   = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
        $label = trim((string) ($step['label'] ?? ''));
        $sub   = trim((string) ($step['sub'] ?? ''));
        $state = (string) ($step['state'] ?? 'upcoming');
        $url   = isset($step['url']) && is_string($step['url']) ? trim($step['url']) : '';

        $cls = ['drv-cart-step'];
        if ($state === 'current') {
            $cls[] = 'is-active';
        } elseif ($state === 'done') {
            $cls[] = 'is-done';
        }

        $tag = ($url !== '' && $state === 'done') ? 'a' : 'div';
        ?>
        <li>
            <<?php echo esc_attr($tag); ?>
                <?php echo $tag === 'a' ? 'href="' . esc_url($url) . '"' : ''; ?>
                class="<?php echo esc_attr(implode(' ', $cls)); ?>"
                <?php echo $state === 'current' ? 'aria-current="step"' : ''; ?>>
                <span class="drv-cart-step__num"><?php echo esc_html($num); ?></span>
                <span class="drv-cart-step__text">
                    <span class="drv-cart-step__title"><?php echo esc_html($label); ?></span>
                    <?php if ($sub !== '') : ?>
                        <span class="drv-cart-step__sub"><?php echo esc_html($sub); ?></span>
                    <?php endif; ?>
                </span>
            </<?php echo esc_attr($tag); ?>>
        </li>
    <?php endforeach; ?>
</ol>

