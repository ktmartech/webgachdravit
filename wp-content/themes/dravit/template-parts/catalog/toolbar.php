<?php
declare(strict_types=1);

use Dravit\Core\Template;
use Dravit\Filters\FacetRegistry;
use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Thanh công cụ Sticky phía trên lưới sản phẩm:
 * - Toggle thu/mở Sidebar Desktop (3 ⇄ 4 cột)
 * - Nút mở Bottom Sheet Bộ lọc Mobile kèm badge số tiêu chí đang lọc
 * - Đếm tổng mẫu trực tiếp + vùng `aria-live="polite"` chuẩn WCAG 2.2 AA (`F-12`)
 * - Select sắp xếp `FLT-14` (`sort`)
 * - CẤM xuất badge debug `robots:` của bản prototype (`STRIP-05`)
 *
 * @var array<string, mixed> $args
 */
$state        = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$shown        = max(0, (int) ($args['shown'] ?? 0));
$total        = max(0, (int) ($args['total'] ?? 0));
$archive_name = trim((string) ($args['archive_name'] ?? 'Gạch ốp lát'));
$sort_options = FacetRegistry::getSortOptions();
$current_sort = $state->sort();
$active_count = $state->activeCount();
?>
<div class="drv-cat-toolbar<?php echo $state->hasActiveFilters() ? ' has-active-filters' : ''; ?>" data-drv-catalog-toolbar>
    <div class="drv-cat-toolbar__top">
        <div class="l-cluster l-cluster--sm drv-cat-toolbar__left">
            <button type="button"
                    class="drv-btn drv-btn--outline drv-btn--sm drv-desktop-filter-toggle"
                    data-drv-toggle-sidebar
                    aria-expanded="true"
                    aria-controls="drv-filter-sidebar"
                    title="Ẩn hoặc hiện cột bộ lọc để chuyển chế độ xem 3 - 4 mẫu trên một hàng">
                <i class="ph ph-sidebar-simple drv-icon--xs" aria-hidden="true"></i>
                <span data-sidebar-toggle-label>Ẩn bộ lọc</span>
            </button>

            <span class="drv-toolbar-divider drv-desktop-filter-toggle" aria-hidden="true"></span>

            <button type="button"
                    class="drv-btn drv-btn--secondary drv-btn--sm drv-mobile-filter-trigger"
                    data-drv-open-filter-sheet
                    aria-controls="drv-filter-sheet"
                    aria-expanded="false">
                <i class="ph-fill ph-sliders-horizontal drv-icon--xs" aria-hidden="true"></i>
                <span>Bộ lọc</span>
                <span class="drv-mobile-filter-trigger__badge u-tabular"
                      data-mobile-filter-count
                      <?php echo $active_count > 0 ? '' : 'hidden'; ?>>
                    <?php echo esc_html((string) $active_count); ?>
                </span>
            </button>

            <p class="u-text-sm drv-cat-toolbar__count">
                <span class="drv-cat-toolbar__count-prefix">Đang hiển thị </span>
                <strong class="u-tabular" data-catalog-shown><?php echo esc_html((string) min($shown, $total)); ?></strong>
                <span> / </span>
                <strong class="u-tabular" data-catalog-total><?php echo esc_html((string) $total); ?></strong>
                <span> mẫu</span>
                <span class="drv-cat-toolbar__count-suffix"> <?php echo esc_html(mb_strtolower($archive_name)); ?></span>
            </p>

            <span class="drv-spin-ring drv-spin-ring--sm drv-toolbar-spinner" data-catalog-spinner aria-hidden="true"></span>

            <div id="drv-catalog-live-status" class="u-sr-only" role="status" aria-live="polite" aria-atomic="true">
                <?php echo esc_html(sprintf('Đã tìm thấy %d sản phẩm.', $total)); ?>
            </div>
        </div>

        <div class="l-cluster l-cluster--xs drv-cat-toolbar__sort">
            <label for="drv-catalog-sort" class="u-text-xs u-bold u-text-muted drv-cat-toolbar__sort-label">Sắp xếp:</label>
            <select id="drv-catalog-sort"
                    name="sort"
                    form="drv-catalog-filter-form"
                    class="drv-select drv-cat-toolbar__sort-select"
                    data-catalog-sort
                    aria-label="Sắp xếp danh sách mẫu gạch">
                <?php foreach ($sort_options as $sort_key => $sort_label) : ?>
                    <option value="<?php echo esc_attr($sort_key); ?>" <?php selected($current_sort, $sort_key); ?>>
                        <?php echo esc_html($sort_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="drv-cat-toolbar__bottom" data-catalog-chips-row>
        <div id="drv-active-chips" class="drv-active-chips" aria-label="Các bộ lọc đang áp dụng">
            <?php
            Template::render_part('template-parts/catalog/active-chips', [
                'state'        => $state,
                'archive_name' => $archive_name,
            ]);
            ?>
        </div>
    </div>
</div>
