<?php
declare(strict_types=1);

use Dravit\Seo\Breadcrumbs;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$items = isset($args['items']) && is_array($args['items']) && $args['items'] !== []
    ? $args['items']
    : Breadcrumbs::getCurrentTrail();

if ($items === []) {
    return;
}
$last_idx = count($items) - 1;
?>
<nav class="drv-breadcrumb" aria-label="Đường dẫn">
    <ol class="drv-breadcrumb__list">
        <?php foreach ($items as $idx => $item) :
            if (!is_array($item)) {
                continue;
            }
            $label  = trim((string) ($item['label'] ?? ''));
            $url    = isset($item['url']) && is_string($item['url']) ? trim($item['url']) : '';
            $is_end = ($idx === $last_idx);
            if ($label === '') {
                continue;
            }
            ?>
            <li class="drv-breadcrumb__item">
                <?php if (!$is_end && $url !== '') : ?>
                    <a href="<?php echo esc_url($url); ?>" class="drv-breadcrumb__link"><?php echo esc_html($label); ?></a>
                    <span class="drv-breadcrumb__sep" aria-hidden="true">/</span>
                <?php else : ?>
                    <span class="drv-breadcrumb__current" aria-current="page"><?php echo esc_html($label); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
