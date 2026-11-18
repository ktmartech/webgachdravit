<?php
declare(strict_types=1);

use Dravit\Core\Template;

defined('ABSPATH') || exit;

/**
 * Buy-Box 2 chế độ (`priced` / `quote_only`) trên PDP (`03-product-single.html`, `D-20`, `D-24`, `D-26`, `D-30`, Test P-3, P-4, P-7, P-9):
 * - Thẻ `<h1>` sạch 100% văn bản; nút sao chép mã SKU nằm TÁCH BIỆT bên ngoài `<h1>` (`P-9`).
 * - Gọi `drv_price_html($product, 'single')` và `drv_ui('unit-calc')`.
 * - Cụm CTA chuyển đổi kép: Mua lẻ (Form POST số nguyên `viên` + `drv_buy_now=1` + AJAX Store API) + Báo giá công trình.
 *
 * @var array<string, mixed> $args
 */
$product = $args['product'] ?? null;
$view    = isset($args['view']) && is_array($args['view']) ? $args['view'] : [];
if (!$product instanceof \WC_Product || $view === []) {
    return;
}

$id          = (int) $view['id'];
$sku         = (string) $view['sku'];
$title       = (string) $view['title'];
$permalink   = (string) $view['permalink'];
$is_quote    = !empty($view['is_quote']);
$is_out      = !empty($view['is_out']);
$unit_label  = (string) $view['unit_label'];
$unit_cfg    = $view['unit_cfg'];
$def_unit    = (string) ($unit_cfg['default_buy_unit'] ?? 'm2');
$waste_def   = (int) $view['waste_def'];
$default_m2  = (float) $view['default_m2'];
$init_calc   = $view['init_calc'];
$init_pieces = max(1, (int) ($init_calc['pieces'] ?? 1));
$init_boxes  = (string) ($init_calc['boxText'] ?? '1 thùng');
$stock_boxes = $view['stock_boxes'];
$shelf       = (string) $view['shelf'];
$ai_enabled  = (bool) drv_setting('features.ai_enabled', false);

$hotline_raw = (string) drv_setting('contact.hotline', '');
$hotline_tel = drv_clean_phone($hotline_raw);
$hours_raw   = (string) drv_setting('contact.working_hours', '');
// "08:00 - 21:00 (Thứ 2 - Chủ nhật)" → "08:00 - 21:00" để tránh ngoặc lồng "(… (…))" trên thẻ liên hệ.
$hours_text  = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $hours_raw)) ?: $hours_raw;
$shelf_label = drv_shelf_label($shelf);
$showrooms   = drv_setting('showrooms', []);
$sr_first    = (is_array($showrooms) && isset($showrooms[0]) && is_array($showrooms[0])) ? $showrooms[0] : [];
$sr_name     = trim((string) ($sr_first['name'] ?? drv_setting('company.brand_name', '')));
$sr_addr     = trim((string) ($sr_first['address'] ?? drv_setting('company.address', '')));
$contact_url = untrailingslashit(home_url('/lien-he'));
?>
<div class="drv-pdp-buybox">
    <div class="drv-pdp-header">
        <h1 id="pdp-product-title" class="drv-pdp-title"><?php echo esc_html($title); ?></h1>

        <div class="drv-pdp-meta-row">
            <?php if ($sku !== '') : ?>
                <span class="drv-pdp-meta-item">
                    <span>SKU: <strong class="u-text-navy u-tabular"><?php echo esc_html($sku); ?></strong></span>
                    <?php
                    drv_ui('copy-inline', [
                        'value'      => $sku,
                        'aria_label' => 'Sao chép mã SKU ' . $sku,
                    ]);
                    ?>
                </span>
            <?php endif; ?>

            <span class="drv-pdp-meta-item">
                <span class="drv-status-dot<?php echo $is_out ? ' drv-status-dot--out' : ''; ?>" aria-hidden="true"></span>
                <strong class="u-text-navy">
                    <?php
                    if ($is_out) {
                        echo esc_html('Đặt trước theo lô');
                    } elseif ($stock_boxes !== null && $stock_boxes > 0) {
                        echo esc_html('Còn ' . $stock_boxes . ' thùng');
                    } else {
                        echo esc_html('Còn hàng');
                    }
                    ?>
                </strong>
            </span>

            <?php if ($shelf_label !== '') : ?>
                <a href="<?php echo esc_url($contact_url); ?>" class="drv-pdp-meta-item drv-pdp-meta-item--shelf u-text-muted">
                    <i class="ph ph-map-pin drv-icon--xs" aria-hidden="true"></i>
                    <span><?php echo esc_html($shelf_label === 'Showroom' ? 'Có mẫu tại Showroom' : $shelf_label . ' Showroom'); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="drv-pdp-price-bar">
        <?php echo drv_price_html($product, 'single'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside price.php ?>

        <div class="drv-pdp-quick-actions">
            <?php Template::render_part('template-parts/ui/share-menu', ['url' => $permalink, 'label' => 'Chia sẻ sản phẩm', 'menu_id' => 'drv-share-menu-pdp']); ?>
        </div>
    </div>

    <?php
    Template::render_part('template-parts/product/series-swatches', [
        'swatches' => $view['swatches'],
    ]);

    drv_ui('unit-calc', [
        'product'         => $product,
        'default_area_m2' => $default_m2,
    ]);
    ?>

    <div id="drv-buy-actions" class="drv-pdp-cta-block" data-pdp-cta-anchor>
        <?php if (!$is_quote && !$is_out) : ?>
            <form id="drv-pdp-buy-form"
                  class="cart drv-pdp-buy-form"
                  method="post"
                  action="<?php echo esc_url($permalink); ?>"
                  data-drv-pdp-buy-form>
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr((string) $id); ?>">
                <input type="hidden" name="quantity" value="<?php echo esc_attr((string) $init_pieces); ?>" data-drv-qty-pieces>
                <input type="hidden" name="drv_m2_input" value="<?php echo esc_attr((string) $default_m2); ?>" data-drv-m2-input>
                <input type="hidden" name="drv_waste_pct" value="<?php echo esc_attr((string) $waste_def); ?>" data-drv-waste-input>
                <input type="hidden" name="drv_input_unit" value="<?php echo esc_attr($def_unit); ?>" data-drv-unit-input>

                <div class="drv-pdp-cta-row">
                    <button type="submit"
                            name="drv_buy_now"
                            value="1"
                            class="drv-btn-pill drv-btn-pill--navy">
                        <span>Mua ngay</span>
                    </button>

                    <button type="submit"
                            class="drv-btn-pill drv-btn-pill--soft"
                            data-drv-action="add-to-cart"
                            data-product-id="<?php echo esc_attr((string) $id); ?>"
                            data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                            data-drv-sku="<?php echo esc_attr($sku); ?>"
                            data-drv-title="<?php echo esc_attr($title); ?>"
                            data-drv-sync-calc="true"
                            data-drv-open-drawer="true">
                        <i class="ph-bold ph-shopping-cart-simple drv-icon" aria-hidden="true"></i>
                        <span>Thêm vào giỏ</span>
                    </button>
                </div>
            </form>

            <div class="drv-pdp-b2b" role="group" aria-labelledby="pdp-b2b-title">
                <div class="drv-pdp-b2b__head">
                    <span class="drv-pdp-b2b__icon" aria-hidden="true">
                        <i class="ph-fill ph-buildings"></i>
                    </span>
                    <span class="drv-pdp-b2b__text">
                        <strong id="pdp-b2b-title" class="drv-pdp-b2b__title">Mua cho công trình ≥ 30m²?</strong>
                        <span class="drv-pdp-b2b__desc">Giá chiết khấu theo khối lượng, báo kèm phí vận chuyển.</span>
                    </span>
                    <button type="button"
                            class="drv-pdp-b2b__cta"
                            data-drv-action="pdp-quote-modal"
                            data-pdp-quote-modal-open
                            data-product-id="<?php echo esc_attr((string) $id); ?>"
                            data-drv-sku="<?php echo esc_attr($sku); ?>"
                            data-drv-title="<?php echo esc_attr($title); ?>"
                            data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                            data-drv-boxes-text="<?php echo esc_attr($init_boxes); ?>">
                        <span>Nhận báo giá</span>
                        <i class="ph-bold ph-arrow-right" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="drv-pdp-b2b__actions">
                    <button type="button"
                            class="drv-pdp-b2b__chip"
                            data-drv-action="quote-add"
                            data-pdp-quote-add
                            data-product-id="<?php echo esc_attr((string) $id); ?>"
                            data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                            data-drv-sku="<?php echo esc_attr($sku); ?>"
                            data-drv-title="<?php echo esc_attr($title); ?>"
                            data-drv-pieces="<?php echo esc_attr((string) $init_pieces); ?>"
                            data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>">
                        <i class="ph-bold ph-plus" aria-hidden="true"></i>
                        <span>Thêm vào DS báo giá</span>
                        <span class="drv-pdp-b2b__chip-meta u-tabular" data-sync-calc-boxes><?php echo esc_html($init_boxes); ?></span>
                    </button>
                    <?php if ($ai_enabled) : ?>
                        <button type="button"
                                class="drv-pdp-b2b__chip"
                                data-drv-action="ai-finder-open"
                                data-drv-prompt="<?php echo esc_attr('Tư vấn giúp tôi mẫu gạch ' . ($sku !== '' ? $sku . ' ' : '') . $title); ?>">
                            <i class="ph-fill ph-sparkle" aria-hidden="true"></i>
                            <span>Hỏi Trợ lý AI</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="drv-pdp-cta-row">
                <button type="button"
                        class="drv-btn-pill drv-btn-pill--navy"
                        data-drv-action="pdp-quote-modal"
                        data-pdp-quote-modal-open
                        data-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-drv-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-title="<?php echo esc_attr($title); ?>"
                        data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>"
                        data-drv-boxes-text="<?php echo esc_attr($init_boxes); ?>">
                    <i class="ph-bold ph-paper-plane-tilt drv-icon" aria-hidden="true"></i>
                    <span><?php echo esc_html($is_out ? 'Báo giá lô mới' : 'Nhận báo giá'); ?></span>
                </button>

                <button type="button"
                        class="drv-btn-pill drv-btn-pill--soft"
                        data-drv-action="quote-add"
                        data-pdp-quote-add
                        data-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-drv-product-id="<?php echo esc_attr((string) $id); ?>"
                        data-drv-sku="<?php echo esc_attr($sku); ?>"
                        data-drv-title="<?php echo esc_attr($title); ?>"
                        data-drv-pieces="<?php echo esc_attr((string) $init_pieces); ?>"
                        data-drv-m2="<?php echo esc_attr((string) $default_m2); ?>">
                    <i class="ph-bold ph-plus drv-icon" aria-hidden="true"></i>
                    <span>Báo giá công trình</span>
                </button>
            </div>

            <div class="drv-pdp-b2b__actions">
                <a class="drv-pdp-b2b__chip" href="<?php echo esc_url('tel:' . $hotline_tel); ?>">
                    <i class="ph-fill ph-phone-call" aria-hidden="true"></i>
                    <span>Cần gấp? Gọi <strong class="u-tabular"><?php echo esc_html($hotline_raw); ?></strong></span>
                </a>
                <?php if ($ai_enabled) : ?>
                    <button type="button"
                            class="drv-pdp-b2b__chip"
                            data-drv-action="ai-finder-open"
                            data-drv-prompt="<?php echo esc_attr('Tư vấn giúp tôi mẫu gạch ' . ($sku !== '' ? $sku . ' ' : '') . $title); ?>">
                        <i class="ph-fill ph-sparkle" aria-hidden="true"></i>
                        <span>Hỏi Trợ lý AI</span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="drv-pdp-contact-grid">
        <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-pdp-contact-card">
            <span class="drv-pdp-contact-card__icon" aria-hidden="true">
                <i class="ph-fill ph-phone-call"></i>
            </span>
            <span class="drv-pdp-contact-card__body">
                <span class="u-text-muted">Hotline đặt hàng</span>
                <strong class="u-text-navy u-tabular"><?php echo esc_html($hotline_raw); ?></strong>
                <span class="u-text-muted u-tabular">(<?php echo esc_html($hours_text); ?>)</span>
            </span>
        </a>

        <a href="<?php echo esc_url($contact_url); ?>" class="drv-pdp-contact-card">
            <span class="drv-pdp-contact-card__icon" aria-hidden="true">
                <i class="ph-fill ph-map-pin"></i>
            </span>
            <span class="drv-pdp-contact-card__body">
                <strong class="u-text-navy"><?php echo esc_html($sr_name . ': ' . $sr_addr); ?></strong>
                <span class="u-text-muted">(<?php echo esc_html($hours_text); ?>) <strong class="u-text-moss">Xem bản đồ <i class="ph-bold ph-caret-right drv-icon--xs" aria-hidden="true"></i></strong></span>
            </span>
        </a>
    </div>
</div>
