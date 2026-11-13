<?php
declare(strict_types=1);

use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Khung trạng thái rỗng khi tổ hợp bộ lọc trả về 0 sản phẩm (docs/07 §4, docs/21 Step 6).
 * Tích hợp CTA xóa bộ lọc nhanh + mở Modal Báo Giá mang sẵn tiêu chí đang chọn.
 *
 * @var array<string, mixed> $args
 */
$state        = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$archive_name = trim((string) ($args['archive_name'] ?? 'Gạch ốp lát'));
$criteria_str = $state->buildCriteriaSummary($archive_name);
?>
<div class="drv-catalog-empty" role="region" aria-label="Không tìm thấy mẫu gạch khớp điều kiện">
    <div class="drv-catalog-empty__icon" aria-hidden="true">
        <i class="ph ph-magnifying-glass-minus drv-icon--lg"></i>
    </div>

    <div class="l-stack l-stack--xs drv-catalog-empty__body">
        <h2 class="drv-catalog-empty__title">Chưa có mẫu gạch khớp đồng thời các tiêu chí vừa chọn</h2>
        <p class="drv-catalog-empty__desc">
            Một số lô gạch dự án hoặc mẫu mới nhập kho có thể chưa kịp cập nhật đủ bộ thông số trên website. Bạn có thể nới bớt điều kiện lọc hoặc gửi yêu cầu để kỹ thuật viên tra kho trực tiếp trong 30 phút.
        </p>
        <?php if ($state->hasActiveFilters()) : ?>
            <p class="drv-catalog-empty__criteria u-text-xs u-text-muted">
                <strong>Tiêu chí đang chọn:</strong> <?php echo esc_html($criteria_str); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="l-cluster drv-catalog-empty__actions">
        <?php if ($state->hasActiveFilters()) : ?>
            <a href="<?php echo esc_url($state->baseUrl()); ?>"
               class="drv-btn drv-btn--outline"
               data-filter-reset>
                <i class="ph ph-arrow-counter-clockwise drv-icon--xs" aria-hidden="true"></i>
                <span>Xóa tất cả bộ lọc</span>
            </a>
        <?php endif; ?>

        <button type="button"
                class="drv-btn drv-btn--moss"
                data-drv-action="dialog-open"
                data-drv-target="#drv-quote-modal"
                data-drv-quote-criteria="<?php echo esc_attr($criteria_str); ?>">
            <i class="ph-bold ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
            <span>Nhờ kho tìm mẫu tương đương</span>
        </button>
    </div>
</div>

