<?php
declare(strict_types=1);

use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Vỏ `<dialog id="drv-filter-sheet">` cho màn hình Mobile/Tablet `< 1024px` (docs/05 §4.2, Test F-8, F-13, STRIP-25).
 * Không chứa bất kỳ `<form>` hay `<input>` trùng lặp nào khi SSR — `<drv-filter-form>` sẽ di chuyển vào `[data-filter-sheet-slot]`.
 *
 * @var array<string, mixed> $args
 */
$state        = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$total_found  = max(0, (int) ($args['total_found'] ?? 0));
$active_count = $state->activeCount();
?>
<dialog id="drv-filter-sheet"
        class="drv-dialog drv-dialog--bottom-sheet drv-filter-sheet"
        aria-labelledby="mobile-filter-title">
    <div class="drv-filter-sheet__handle" aria-hidden="true"></div>

    <div class="drv-dialog__head drv-filter-sheet__head">
        <div class="drv-filter-sheet__title-wrap">
            <h2 id="mobile-filter-title" class="drv-dialog__title">
                <i class="ph-fill ph-sliders-horizontal drv-icon--sm" aria-hidden="true"></i>
                <span>Bộ lọc gạch</span>
                <span class="drv-filter-sheet__badge u-tabular" data-drawer-active-badge>
                    <?php echo esc_html($active_count > 0 ? $active_count . ' đang chọn' : '14 tiêu chí'); ?>
                </span>
            </h2>
        </div>

        <div class="drv-filter-sheet__head-actions">
            <button type="button" class="drv-filter-sheet__collapse-btn" data-drv-collapse-facets-mobile>
                <i class="ph ph-arrows-in-line-vertical drv-icon--xs" aria-hidden="true"></i>
                <span data-collapse-facets-mobile-label>Thu gọn</span>
            </button>
            <button type="button"
                    class="drv-btn drv-btn--ghost drv-btn--icon drv-filter-sheet__close"
                    data-filter-sheet-close
                    aria-label="Đóng bộ lọc">
                <i class="ph ph-x drv-icon--md" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div id="drv-drawer-active-bar" class="drv-filter-sheet__active-bar" <?php echo $active_count > 0 ? '' : 'hidden'; ?>>
        <div class="drv-filter-sheet__active-chips" data-drawer-active-chips aria-label="Tiêu chí đang chọn"></div>
    </div>

    <div class="drv-dialog__body drv-filter-sheet__body" data-filter-sheet-slot></div>

    <div class="drv-dialog__foot drv-filter-sheet__foot">
        <div class="drv-filter-sheet__foot-grid">
            <button type="button"
                    class="drv-btn drv-btn--outline drv-filter-sheet__reset-btn"
                    data-filter-sheet-reset
                    <?php disabled($active_count === 0); ?>>
                <i class="ph ph-arrow-counter-clockwise drv-icon--xs" aria-hidden="true"></i>
                <span>Thiết lập lại</span>
            </button>
            <button type="button"
                    class="drv-btn drv-btn--primary drv-filter-sheet__apply-btn"
                    data-filter-sheet-apply>
                <span class="drv-spin-ring drv-spin-ring--sm drv-spin-ring--light drv-sheet-spinner" aria-hidden="true"></span>
                <span>Xem <strong class="u-tabular" data-sheet-preview-count><?php echo esc_html((string) $total_found); ?></strong> mẫu gạch phù hợp</span>
            </button>
        </div>
    </div>
</dialog>

