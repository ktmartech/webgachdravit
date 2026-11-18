<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * "Vật tư thi công đề xuất" (mockup `04-cart.html`): checkbox chọn vật tư mua kèm + tổng cộng + nút "Thêm vào đơn".
 * Dữ liệu thật từ SKU phụ kiện (`drv_line = phu_kien`), số lượng theo định mức phủ trên tổng m² gạch trong giỏ.
 * Thêm vào đơn qua Store API (`assets/src/js/cart.js` → action `cart-fbt-submit`).
 *
 * @var array<string, mixed> $args
 */
$items    = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
$total_m2 = (float) ($args['total_m2'] ?? 0);
if ($items === []) {
    return;
}

$sum   = 0;
$count = count($items);
foreach ($items as $it) {
    $sum += (int) $it['total'];
}
?>
<div class="drv-fbt-bundle" role="region" aria-label="Gói vật tư thi công thường mua cùng nhau" data-drv-fbt>
    <div class="drv-fbt-bundle__head">
        <h2 class="drv-fbt-bundle__title">Vật tư thi công đề xuất</h2>
        <span class="drv-fbt-bundle__subtitle"><?php echo esc_html('Định mức theo ' . drv_format_decimal($total_m2, 2) . ' m² trong giỏ · Giao cùng xe gạch'); ?></span>
    </div>

    <div class="drv-fbt-bundle__body">
        <div class="drv-fbt-list" role="group" aria-label="Chọn vật tư mua kèm">
            <?php foreach ($items as $it) :
                $check_id = 'fbt-check-' . (int) $it['id'];
                ?>
                <label class="drv-fbt-item" for="<?php echo esc_attr($check_id); ?>">
                    <input type="checkbox"
                           id="<?php echo esc_attr($check_id); ?>"
                           class="drv-fbt-item__check"
                           checked
                           data-fbt-id="<?php echo esc_attr((string) $it['id']); ?>"
                           data-fbt-qty="<?php echo esc_attr((string) $it['qty']); ?>"
                           data-fbt-price="<?php echo esc_attr((string) $it['total']); ?>"
                           aria-label="<?php echo esc_attr('Mua kèm ' . $it['qty'] . ' ' . $it['unit'] . ' ' . $it['name']); ?>">
                    <?php if ($it['img'] !== '') : ?>
                        <img src="<?php echo esc_url($it['img']); ?>" width="56" height="56" loading="lazy" decoding="async" alt="<?php echo esc_attr($it['name']); ?>" class="drv-fbt-item__thumb">
                    <?php endif; ?>
                    <div class="drv-fbt-item__info">
                        <div class="drv-fbt-item__title"><?php echo esc_html($it['name']); ?></div>
                        <div class="drv-fbt-item__meta-row">
                            <span class="drv-badge drv-badge--champagne u-text-xs"><?php echo esc_html($it['badge']); ?></span>
                            <span class="drv-fbt-item__price">
                                <strong class="u-tabular"><?php echo esc_html(drv_format_vnd((int) $it['total'])); ?></strong>
                                <?php if ($it['per_unit_label'] !== '') : ?>
                                    <span class="u-text-muted u-tabular"><?php echo esc_html('(' . $it['per_unit_label'] . ')'); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="drv-fbt-summary" aria-live="polite">
            <div class="drv-fbt-summary__info">
                <span class="drv-fbt-summary__label">Tổng cộng thêm (<span data-fbt-count><?php echo esc_html((string) $count); ?></span> món):</span>
                <div class="drv-fbt-summary__total u-tabular" data-fbt-total><?php echo esc_html(drv_format_vnd($sum)); ?></div>
                <span class="drv-fbt-summary__note">Giao cùng xe gạch</span>
            </div>

            <button type="button" class="drv-btn drv-btn--moss drv-fbt-btn" data-drv-action="cart-fbt-submit">
                <i class="ph-bold ph-plus drv-icon--xs" aria-hidden="true"></i>
                <span>Thêm vào đơn</span>
            </button>
        </div>
    </div>
</div>
