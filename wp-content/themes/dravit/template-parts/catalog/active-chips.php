<?php
declare(strict_types=1);

use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Thanh hiển thị các Chip bộ lọc đang kích hoạt + nút Xóa tất cả (dùng chung SSR & AJAX `chips_html`).
 * Tuyệt đối không gắn `<a href*="?">` đa tham số để chống Spider-Trap (Test M-13).
 *
 * @var array<string, mixed> $args
 */
$state        = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$archive_name = trim((string) ($args['archive_name'] ?? 'Gạch ốp lát'));
$chips        = $state->activeChips();
?>
<?php if ($chips === []) : ?>
    <span class="drv-active-chips__empty u-text-xs u-text-muted">
        Đang xem toàn bộ mẫu <?php echo esc_html(mb_strtolower($archive_name)); ?>
    </span>
<?php else : ?>
    <?php foreach ($chips as $chip) : ?>
        <button type="button"
                class="drv-active-chip"
                data-filter-remove-param="<?php echo esc_attr($chip['param']); ?>"
                data-filter-remove-val="<?php echo esc_attr($chip['value']); ?>"
                aria-label="<?php echo esc_attr('Gỡ bộ lọc ' . $chip['group_label'] . ': ' . $chip['label']); ?>">
            <span class="drv-active-chip__group"><?php echo esc_html($chip['group_label']); ?>:</span>
            <strong class="drv-active-chip__val"><?php echo esc_html($chip['label']); ?></strong>
            <i class="ph-bold ph-x drv-icon--xs" aria-hidden="true"></i>
        </button>
    <?php endforeach; ?>

    <a href="<?php echo esc_url($state->baseUrl()); ?>"
       class="drv-active-chips__clear"
       data-filter-reset>
        <i class="ph-bold ph-arrow-counter-clockwise drv-icon--xs" aria-hidden="true"></i>
        <span>Xóa tất cả</span>
    </a>
<?php endif; ?>

