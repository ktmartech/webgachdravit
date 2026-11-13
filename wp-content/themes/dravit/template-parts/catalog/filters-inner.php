<?php
declare(strict_types=1);

use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Thân bên trong `<form id="drv-catalog-filter-form" data-filter-form-el>` (dùng chung 1-1 cho SSR & AJAX `facets_html`).
 * Thực thi nghiêm ngặt:
 * - Chống Spider-Trap (`M-13`): Chỉ gắn `<a href>` cho nhóm `seo` khi trang hiện tại 0 tham số; nhóm `tech` hoặc khi đã có tham số chỉ dùng `<label>`.
 * - Mục `count === 0` giữ nguyên vị trí với thuộc tính `disabled` (`F-9`).
 * - Nút `<button type="submit">` hỗ trợ trình duyệt tắt JavaScript (`F-4`).
 *
 * @var array<string, mixed> $args
 */
$state     = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$showroom  = isset($args['showroom']) && is_array($args['showroom']) ? $args['showroom'] : null;
$ui_groups = isset($args['ui_groups']) && is_array($args['ui_groups']) ? $args['ui_groups'] : [];
?>
<div class="drv-filter-sidebar__head">
    <span class="drv-filter-sidebar__title">
        <i class="ph-fill ph-sliders-horizontal drv-icon--sm" aria-hidden="true"></i>
        <span>Bộ lọc</span>
    </span>

    <div class="drv-filter-sidebar__actions">
        <button type="button"
                class="drv-filter-sidebar__collapse-btn"
                data-drv-collapse-facets
                title="Thu gọn hoặc mở rộng toàn bộ các nhóm tiêu chí">
            <i class="ph ph-arrows-in-line-vertical drv-icon--xs" aria-hidden="true"></i>
            <span data-collapse-facets-label>Thu gọn</span>
        </button>

        <a href="<?php echo esc_url($state->baseUrl()); ?>"
           class="drv-filter-sidebar__reset"
           data-filter-reset
           <?php echo $state->hasActiveFilters() ? '' : 'hidden'; ?>>
            Xóa lọc
        </a>
    </div>
</div>

<?php if ($showroom !== null && !empty($showroom['options'][0]) && is_array($showroom['options'][0])) :
    $sr_opt      = $showroom['options'][0];
    $sr_checked  = !empty($sr_opt['checked']);
    $sr_disabled = !empty($sr_opt['disabled']);
    $sr_count    = (int) ($sr_opt['count'] ?? 0);
    ?>
    <div class="drv-filter-group drv-filter-group--showroom" data-facet-group="showroom">
        <label class="drv-filter-showroom-card<?php echo $sr_disabled ? ' is-disabled' : ''; ?>">
            <input type="checkbox"
                   class="drv-check__input"
                   name="showroom"
                   value="1"
                   data-facet-param="showroom"
                   <?php checked($sr_checked); ?>
                   <?php disabled($sr_disabled); ?>>
            <span class="drv-filter-showroom-card__content">
                <span class="drv-filter-showroom-card__title">
                    <i class="ph-fill ph-seal-check drv-icon--xs" aria-hidden="true"></i>
                    <span>Có mẫu tại Showroom</span>
                </span>
                <span class="drv-filter-showroom-card__sub">Xem trực tiếp bề mặt</span>
            </span>
            <span class="drv-filter-showroom-card__count u-tabular"><?php echo esc_html((string) $sr_count); ?></span>
        </label>
    </div>
<?php endif; ?>

<?php
$group_index = 0;
foreach ($ui_groups as $group) :
    if (!is_array($group) || empty($group['facets']) || !is_array($group['facets'])) {
        continue;
    }
    $group_index++;
    $group_id       = (string) ($group['id'] ?? ('grp-' . $group_index));
    $group_title    = (string) ($group['title'] ?? '');
    $is_open        = !empty($group['is_open']);
    $selected_count = max(0, (int) ($group['selected_count'] ?? 0));
    $sub_facets     = $group['facets'];
    $has_multi_sub  = count($sub_facets) > 1;
    ?>
    <details class="drv-filter-group" data-facet-group="<?php echo esc_attr($group_id); ?>" <?php echo $is_open ? 'open' : ''; ?>>
        <summary class="drv-filter-group__title">
            <span><?php echo esc_html($group_index . '. ' . $group_title); ?></span>
            <span class="drv-filter-group__meta">
                <span class="drv-filter-group__selected-badge u-tabular"
                      data-group-selected-count
                      <?php echo $selected_count > 0 ? '' : 'hidden'; ?>>
                    <?php echo esc_html((string) $selected_count); ?>
                </span>
                <i class="ph ph-caret-down drv-icon--xs drv-filter-group__caret" aria-hidden="true"></i>
            </span>
        </summary>

        <div class="drv-filter-group__body">
            <?php foreach ($sub_facets as $sub_idx => $facet) :
                if (!is_array($facet) || empty($facet['options']) || !is_array($facet['options'])) {
                    continue;
                }
                $param   = (string) ($facet['param'] ?? '');
                $ui_type = (string) ($facet['ui'] ?? 'checkbox');
                $f_label = (string) ($facet['label'] ?? $param);
                ?>
                <?php if ($has_multi_sub) : ?>
                    <p class="drv-filter-subgroup-label<?php echo $sub_idx > 0 ? ' drv-filter-subgroup-label--spaced' : ''; ?>">
                        <?php echo esc_html($f_label); ?>
                    </p>
                <?php endif; ?>

                <?php if ($ui_type === 'pills' || $ui_type === 'swatch') : ?>
                    <div class="drv-filter-chips-grid">
                        <?php foreach ($facet['options'] as $opt) :
                            if (!is_array($opt)) {
                                continue;
                            }
                            $slug       = (string) ($opt['slug'] ?? '');
                            $short_lbl  = (string) ($opt['short_label'] ?? $slug);
                            $full_lbl   = (string) ($opt['label'] ?? $short_lbl);
                            $swatch_hex = (string) ($opt['swatch_hex'] ?? '');
                            $count      = (int) ($opt['count'] ?? 0);
                            $checked    = !empty($opt['checked']);
                            $disabled   = !empty($opt['disabled']);
                            $href       = isset($opt['href']) && is_string($opt['href']) && $opt['href'] !== '' ? $opt['href'] : null;
                            $input_id   = 'flt-' . $param . '-' . $slug;
                            $pill_cls   = 'drv-chip drv-chip--square drv-facet-pill';
                            if ($checked) {
                                $pill_cls .= ' is-active';
                            }
                            if ($disabled) {
                                $pill_cls .= ' is-disabled';
                            }
                            ?>
                            <div class="<?php echo esc_attr($pill_cls); ?>">
                                <input type="checkbox"
                                       id="<?php echo esc_attr($input_id); ?>"
                                       class="drv-facet-pill__input u-sr-only"
                                       name="<?php echo esc_attr($param . '[]'); ?>"
                                       value="<?php echo esc_attr($slug); ?>"
                                       data-facet-param="<?php echo esc_attr($param); ?>"
                                       aria-label="<?php echo esc_attr($full_lbl . ' (' . $count . ')'); ?>"
                                       <?php checked($checked); ?>
                                       <?php disabled($disabled); ?>>
                                <?php if ($href !== null) : ?>
                                    <a href="<?php echo esc_url($href); ?>"
                                       class="drv-facet-pill__link"
                                       data-facet-link
                                       data-target-input="<?php echo esc_attr($input_id); ?>">
                                        <?php if ($ui_type === 'swatch' && $swatch_hex !== '') : ?>
                                            <span class="drv-swatch-dot<?php echo $swatch_hex === 'multi' ? ' drv-swatch-dot--multi' : ''; ?>"
                                                  <?php echo ($swatch_hex !== 'multi') ? 'style="--_swatch: ' . esc_attr($swatch_hex) . ';"' : ''; ?>
                                                  aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <span class="drv-facet-pill__text"><?php echo esc_html($short_lbl); ?></span>
                                        <span class="drv-chip__count u-tabular">(<?php echo esc_html((string) $count); ?>)</span>
                                    </a>
                                <?php else : ?>
                                    <label for="<?php echo esc_attr($input_id); ?>" class="drv-facet-pill__link">
                                        <?php if ($ui_type === 'swatch' && $swatch_hex !== '') : ?>
                                            <span class="drv-swatch-dot<?php echo $swatch_hex === 'multi' ? ' drv-swatch-dot--multi' : ''; ?>"
                                                  <?php echo ($swatch_hex !== 'multi') ? 'style="--_swatch: ' . esc_attr($swatch_hex) . ';"' : ''; ?>
                                                  aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <span class="drv-facet-pill__text"><?php echo esc_html($short_lbl); ?></span>
                                        <span class="drv-chip__count u-tabular">(<?php echo esc_html((string) $count); ?>)</span>
                                    </label>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="drv-filter-check-list">
                        <?php foreach ($facet['options'] as $opt) :
                            if (!is_array($opt)) {
                                continue;
                            }
                            $slug     = (string) ($opt['slug'] ?? '');
                            $label    = (string) ($opt['label'] ?? $slug);
                            $icon     = trim((string) ($opt['icon'] ?? ''));
                            $count    = (int) ($opt['count'] ?? 0);
                            $checked  = !empty($opt['checked']);
                            $disabled = !empty($opt['disabled']);
                            $href     = isset($opt['href']) && is_string($opt['href']) && $opt['href'] !== '' ? $opt['href'] : null;
                            $input_id = 'flt-' . $param . '-' . $slug;
                            ?>
                            <div class="drv-check<?php echo $disabled ? ' drv-check--disabled' : ''; ?>">
                                <input type="checkbox"
                                       id="<?php echo esc_attr($input_id); ?>"
                                       class="drv-check__input"
                                       name="<?php echo esc_attr($param . '[]'); ?>"
                                       value="<?php echo esc_attr($slug); ?>"
                                       data-facet-param="<?php echo esc_attr($param); ?>"
                                       aria-label="<?php echo esc_attr($label . ' (' . $count . ')'); ?>"
                                       <?php checked($checked); ?>
                                       <?php disabled($disabled); ?>>
                                <?php if ($href !== null) : ?>
                                    <a href="<?php echo esc_url($href); ?>"
                                       class="drv-check__label drv-facet-seo-link"
                                       data-facet-link
                                       data-target-input="<?php echo esc_attr($input_id); ?>">
                                        <?php if ($icon !== '') : ?>
                                            <i class="ph <?php echo esc_attr($icon); ?> drv-icon--xs" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <span><?php echo esc_html($label); ?></span>
                                    </a>
                                <?php else : ?>
                                    <label for="<?php echo esc_attr($input_id); ?>" class="drv-check__label">
                                        <?php if ($icon !== '') : ?>
                                            <i class="ph <?php echo esc_attr($icon); ?> drv-icon--xs" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endif; ?>
                                <span class="drv-check__count u-tabular">(<?php echo esc_html((string) $count); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </details>
<?php endforeach; ?>

<div class="drv-filter-nojs-actions">
    <button type="submit" class="drv-btn drv-btn--primary drv-btn--block">Áp dụng bộ lọc</button>
</div>

