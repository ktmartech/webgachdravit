<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Thanh đặt mua / báo giá dính đáy màn hình khi cuộn qua khu vực CTA chính (`#drv-sticky-purchase`, `03-product-single.html`, Test P-8):
 * - Chế độ `priced`: Ảnh + Tên + Đang tính `{m²} · {viên} ({thùng})` + Tạm tính + Nút `Mua ngay` (`form="drv-pdp-buy-form"`) & `Thêm vào giỏ`.
 * - Chế độ `quote_only`: Ảnh + Tên + Đang tính `{thùng} ({m²} · {kg})` + Nhãn `Liên hệ báo giá` + Nút `Thêm DS báo giá` & `Nhận báo giá`.
 *
 * @var array<string, mixed> $args
 */
$view = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
if ($view === []) {
    return;
}

$id          = (int) $view['id'];
$sku         = (string) $view['sku'];
$title       = (string) $view['title'];
$is_quote    = !empty($view['is_quote']);
$is_out      = !empty($view['is_out']);
$is_sale     = !empty($view['is_sale']);
$price_m2    = (int) $view['price_m2'];
$regular_m2  = (int) $view['regular_m2'];
$unit_label  = (string) $view['unit_label'];
$default_m2  = (float) $view['default_m2'];
$init_calc   = $view['init_calc'];
$init_pieces = max(1, (int) ($init_calc['pieces'] ?? 1));
$init_boxes  = (string) ($init_calc['boxText'] ?? '1 thùng');
$actual_m2   = (float) ($init_calc['actualM2'] ?? 0.0);
$total_kg    = (float) ($init_calc['totalKg'] ?? 0.0);
$total_vnd   = (int) ($init_calc['totalVnd'] ?? 0);

$thumb_src = isset($view['gallery'][1]['src_large'])
    ? (string) $view['gallery'][1]['src_large']
    : (isset($view['gallery'][0]['src_large'])
        ? (string) $view['gallery'][0]['src_large']
        : (DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg'));

$sticky_meta = sprintf(
    '%s m² · %d %s (%s%s)',
    drv_format_decimal($actual_m2, 2),
    $init_pieces,
    $unit_label,
    $init_boxes,
    $total_kg > 0.0 ? ' · ≈ ' . drv_format_decimal($total_kg, 0) . ' kg' : ''
);
?>
<aside id="drv-sticky-purchase"
       class="drv-sticky-purchase"
       data-drv-sticky-purchase
       data-pdp-mode="<?php echo esc_attr(($is_quote || $is_out) ? 'quote' : 'priced'); ?>"
       aria-label="Thanh đặt mua và báo giá nhanh">
    <div class="l-container drv-sticky-purchase__inner">
        <div class="drv-sticky-purchase__product">
            <img class="drv-sticky-purchase__thumb"
                 src="<?php echo esc_url($thumb_src); ?>"
                 width="48"
                 height="48"
                 loading="lazy"
                 decoding="async"
                 alt="<?php echo esc_attr($title); ?>">
            <div class="l-stack l-stack--2xs">
                <strong class="drv-sticky-purchase__name"><?php echo esc_html($title); ?></strong>
                <span class="u-text-xs u-text-muted u-tabular" data-sync-calc-sticky-meta><?php echo esc_html($sticky_meta); ?></span>
            </div>
        </div>

        <div class="drv-sticky-purchase__right">
            <div class="drv-sticky-purchase__price-col">
                <span class="drv-sticky-purchase__mobile-meta u-tabular" data-sync-calc-sticky-meta><?php echo esc_html($sticky_meta); ?></span>

                <?php if ($is_quote || $is_out) : ?>
                    <strong class="drv-sticky-purchase__total u-text-navy">Liên hệ báo giá</strong>
                    <span class="drv-sticky-purchase__unit-price u-text-xs u-text-muted">Báo giá theo khối lượng công trình</span>
                <?php else : ?>
                    <strong class="drv-sticky-purchase__total u-text-sale u-tabular" data-sync-calc-total><?php echo esc_html(drv_format_vnd($total_vnd)); ?></strong>
                    <span class="drv-sticky-purchase__unit-price u-text-xs u-text-muted u-tabular">
                        <?php echo esc_html(drv_format_vnd($price_m2) . '/m²'); ?>
                        <?php if ($is_sale && $regular_m2 > $price_m2) : ?>
                            · <del><?php echo esc_html(drv_format_vnd($regular_m2)); ?></del>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="drv-sticky-purchase__actions-col">
                <div class="drv-sticky-purchase__btns">
                    <?php if (!$is_quote && !$is_out) : ?>
                        <button type="submit"
                                form="drv-pdp-buy-form"
                                name="drv_buy_now"
                                value="1"
                                class="drv-btn-pill drv-btn-pill--navy drv-sticky-purchase__btn">
                            <span>Mua ngay</span>
                        </button>

                        <button type="button"
                                class="drv-btn-pill drv-btn-pill--soft drv-sticky-purchase__btn"
                                data-drv-action="add-to-cart"
                                data-product-id="<?php echo esc_attr((string) $id); ?>"
                                data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                                data-drv-sku="<?php echo esc_attr($sku); ?>"
                                data-drv-title="<?php echo esc_attr($title); ?>"
                                data-drv-sync-calc="true"
                                data-drv-open-drawer="true"
                                aria-label="Thêm vào giỏ">
                            <i class="ph-bold ph-shopping-cart-simple drv-icon--xs" aria-hidden="true"></i>
                            <span class="drv-sticky-purchase__btn-text">Thêm vào giỏ</span>
                        </button>
                    <?php else : ?>
                        <button type="button"
                                class="drv-btn-pill drv-btn-pill--soft drv-sticky-purchase__btn"
                                data-drv-action="quote-add"
                                data-pdp-quote-add
                                data-product-id="<?php echo esc_attr((string) $id); ?>"
                                data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                                data-drv-sku="<?php echo esc_attr($sku); ?>"
                                data-drv-title="<?php echo esc_attr($title); ?>"
                                data-drv-pieces="<?php echo esc_attr((string) $init_pieces); ?>"
                                data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                                aria-label="Thêm vào danh sách báo giá">
                            <i class="ph-bold ph-plus drv-icon--xs" aria-hidden="true"></i>
                            <span class="drv-sticky-purchase__btn-text">DS báo giá</span>
                        </button>

                        <button type="button"
                                class="drv-btn-pill drv-btn-pill--navy drv-sticky-purchase__btn"
                                data-drv-action="pdp-quote-modal"
                                data-pdp-quote-modal-open
                                data-product-id="<?php echo esc_attr((string) $id); ?>"
                                data-drv-sku="<?php echo esc_attr($sku); ?>"
                                data-drv-title="<?php echo esc_attr($title); ?>"
                                data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                                data-drv-boxes-text="<?php echo esc_attr($init_boxes); ?>">
                            <span>Nhận báo giá</span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!$is_quote && !$is_out) : ?>
                    <span class="drv-sticky-purchase__sub u-hide-mobile">
                        Giá tốt hơn nếu bạn đang xây nhà hoặc mua nhiều
                        <button type="button"
                                class="drv-pdp-b2b-link"
                                data-drv-action="pdp-quote-modal"
                                data-pdp-quote-modal-open
                                data-product-id="<?php echo esc_attr((string) $id); ?>"
                                data-drv-sku="<?php echo esc_attr($sku); ?>"
                                data-drv-title="<?php echo esc_attr($title); ?>"
                                data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                                data-drv-boxes-text="<?php echo esc_attr($init_boxes); ?>">Nhận báo giá riêng</button>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</aside>
