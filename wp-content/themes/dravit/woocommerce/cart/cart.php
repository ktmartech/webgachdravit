<?php
/**
 * Giỏ hàng `/gio-hang` — Bảng kê khối lượng công trình (docs/layout/04-cart.html, docs/04 §4, D-15/D-24/D-26).
 *
 * - Ledger: giá/m² chính + giá/viên phụ, `<drv-qty-input>` số nguyên viên, quy đổi `thùng · m² · kg` luôn hiển thị.
 * - Khối tổng: Tổng m² · Tổng thùng · Tổng kg · Gợi ý xe · Phí vận chuyển "Báo sau khi xác nhận đơn" · Tổng tạm tính.
 * - Không coupon. Có khối "Vật tư thi công đề xuất" (phụ kiện `phu_kien`, mở lại theo yêu cầu, tự ẩn khi chưa có SKU vật tư)
 *   và khối cam kết; tắt JS vẫn cập nhật/xóa được qua form classic.
 *
 * @package Dravit
 * @version 11.3.0
 */
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;
use Dravit\Core\Template;
use Dravit\Woo\CartPresenter;
use Dravit\Woo\CartSuggestions;

defined('ABSPATH') || exit;

$lines      = CartPresenter::fromCart();
$summary    = CartPresenter::summarize(array_values($lines), CartPresenter::vehicleThresholds());
$cart       = WC()->cart;
$contents   = $cart->get_cart();
$total_vnd  = (int) round((float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax());
$threshold  = CartPresenter::quoteThresholdM2();
$is_project = $summary['total_m2'] >= $threshold;
$catalog    = MegaMenuRenderer::collection_url('gach');
$checkout   = untrailingslashit((string) wc_get_checkout_url());
$cart_url   = untrailingslashit((string) wc_get_cart_url());
$zalo       = drv_clean_phone((string) drv_setting('contact.zalo', ''));
$hotline    = (string) drv_setting('contact.hotline', '');

$cart_product_ids = [];
foreach ($contents as $c_item) {
    $cart_product_ids[] = (int) ($c_item['product_id'] ?? 0);
}
$accessories = CartSuggestions::accessories((float) $summary['total_m2'], $cart_product_ids);
$related_ids = CartSuggestions::relatedTileIds($cart_product_ids, 4);

do_action('woocommerce_before_cart');
?>
<section class="l-section l-section--white u-bd-b drv-cart-hero">
    <div class="l-container l-stack l-stack--md">
        <?php
        drv_ui('breadcrumb', [
            'items' => [
                ['label' => 'Trang chủ', 'url' => untrailingslashit(home_url('/'))],
                ['label' => 'Giỏ hàng'],
            ],
        ]);
        ?>
        <div class="l-cluster l-cluster--between">
            <div class="l-stack l-stack--sm">
                <span class="drv-sec-head__eyebrow">Giao dịch theo viên · Quy đổi thùng, m² &amp; kg</span>
                <h1 class="drv-cart-hero__title">Giỏ hàng &amp; bảng kê khối lượng</h1>
            </div>
            <div class="l-cluster l-cluster--xs">
                <a href="<?php echo esc_url($catalog); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                    <?php drv_ui('icon', ['name' => 'arrow-left', 'size' => 'xs']); ?>
                    <span>Chọn thêm mẫu gạch</span>
                </a>
                <button type="button" class="drv-btn drv-btn--soft drv-btn--sm" data-drv-action="cart-to-quote">
                    <?php drv_ui('icon', ['name' => 'clipboard-text', 'size' => 'xs']); ?>
                    <span>Chuyển sang DS báo giá</span>
                </button>
            </div>
        </div>
        <?php
        drv_ui('steps', [
            'steps' => [
                ['label' => 'Giỏ hàng & quy đổi khối lượng', 'sub' => 'Kiểm tra số viên, số thùng, m² và tổng kg', 'state' => 'current'],
                ['label' => 'Thông tin giao hàng', 'sub' => 'Địa chỉ 2 cấp · Chọn COD hoặc chuyển khoản', 'state' => 'upcoming'],
                ['label' => 'Xác nhận đơn & báo phí vận chuyển', 'sub' => 'Nhân viên gọi xác nhận trong giờ làm việc', 'state' => 'upcoming'],
            ],
        ]);
        ?>
    </div>
</section>

<section class="l-section" aria-label="Chi tiết giỏ hàng">
    <div class="l-container l-stack l-stack--md">
        <div class="woocommerce-notices-wrapper"><?php wc_print_notices(); ?></div>

        <div class="drv-cart-layout" data-drv-cart>
            <form class="woocommerce-cart-form l-stack l-stack--lg" action="<?php echo esc_url($cart_url); ?>" method="post">
                <?php do_action('woocommerce_before_cart_table'); ?>

                <div class="drv-cart-ledger">
                    <div class="drv-cart-ledger__head" aria-hidden="true">
                        <span>Sản phẩm &amp; quy cách</span>
                        <span>Đơn giá</span>
                        <span>Số lượng</span>
                        <span class="u-text-right">Thành tiền</span>
                    </div>

                    <?php do_action('woocommerce_before_cart_contents'); ?>

                    <?php
                    foreach ($lines as $key => $line) {
                        $cart_item = $contents[$key] ?? [];
                        if (!apply_filters('woocommerce_cart_item_visible', true, $cart_item, $key)) {
                            continue;
                        }
                        Template::render_part('template-parts/cart/line-row', ['line' => $line, 'cart_item' => $cart_item]);
                    }
                    ?>

                    <?php do_action('woocommerce_cart_contents'); ?>

                    <div class="drv-cart-ledger__foot">
                        <div class="l-cluster l-cluster--xs u-text-xs u-text-muted">
                            <?php drv_ui('icon', ['name' => 'check-square', 'weight' => 'fill', 'size' => 'sm']); ?>
                            <span>Số lượng giao dịch tính theo <strong>số nguyên viên</strong>. Hệ thống tự quy đổi số thùng, m² và trọng lượng.</span>
                        </div>
                        <div class="l-cluster l-cluster--xs">
                            <button type="button" class="drv-btn drv-btn--ghost drv-btn--sm" data-drv-action="cart-reset-qty" hidden>
                                <?php drv_ui('icon', ['name' => 'arrow-counter-clockwise', 'size' => 'xs']); ?>
                                <span>Đặt lại số lượng mặc định</span>
                            </button>
                            <button type="submit" class="drv-btn drv-btn--outline drv-btn--sm" name="update_cart" value="1" data-drv-cart-update>
                                <?php drv_ui('icon', ['name' => 'arrows-clockwise', 'size' => 'xs']); ?>
                                <span>Cập nhật giỏ hàng</span>
                            </button>
                            <?php do_action('woocommerce_cart_actions'); ?>
                        </div>
                    </div>

                    <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                    <?php do_action('woocommerce_after_cart_contents'); ?>
                </div>

                <?php Template::render_part('template-parts/cart/fbt-bundle', ['items' => $accessories, 'total_m2' => (float) $summary['total_m2']]); ?>
                <?php Template::render_part('template-parts/cart/guarantees'); ?>

                <?php do_action('woocommerce_after_cart_table'); ?>
            </form>

            <aside class="drv-summary-box drv-summary-box--sticky" aria-labelledby="cart-summary-heading" data-cart-summary>
                <div class="u-bd-b drv-summary-box__head">
                    <h2 id="cart-summary-heading" class="u-text-lg u-m-0">Tóm tắt khối lượng &amp; đơn hàng</h2>
                </div>

                <div class="drv-cart-metrics" aria-live="polite">
                    <div class="drv-cart-metric">
                        <span class="drv-cart-metric__label">Tổng diện tích gạch</span>
                        <strong class="drv-cart-metric__val u-text-moss u-tabular" data-cart-total-m2><?php echo esc_html(drv_format_decimal($summary['total_m2'], 2) . ' m²'); ?></strong>
                    </div>
                    <div class="drv-cart-metric">
                        <span class="drv-cart-metric__label">Tổng số thùng</span>
                        <strong class="drv-cart-metric__val u-tabular" data-cart-total-boxes><?php echo esc_html($summary['boxes_text']); ?></strong>
                    </div>
                    <div class="drv-cart-metric">
                        <span class="drv-cart-metric__label">Tổng trọng lượng</span>
                        <strong class="drv-cart-metric__val u-tabular" data-cart-total-kg><?php echo esc_html(drv_format_decimal($summary['total_kg'], 0) . ' kg'); ?></strong>
                    </div>
                    <div class="drv-cart-metric">
                        <span class="drv-cart-metric__label">Số mã gạch</span>
                        <strong class="drv-cart-metric__val u-tabular" data-cart-line-count><?php echo esc_html((string) $summary['line_count']); ?></strong>
                    </div>
                </div>

                <div class="drv-summary-row">
                    <span class="u-text-muted">Phương tiện giao dự kiến:</span>
                    <strong class="u-text-navy u-text-xs" data-cart-vehicle-hint><?php echo esc_html($summary['vehicle_hint']); ?></strong>
                </div>
                <div class="drv-summary-row">
                    <span class="u-text-muted">Phí vận chuyển:</span>
                    <span class="u-text-moss u-bold"><?php echo esc_html($summary['shipping_notice']); ?></span>
                </div>

                <details class="drv-ship-collapse">
                    <summary class="drv-ship-collapse__trigger">
                        <?php drv_ui('icon', ['name' => 'info', 'size' => 'xs']); ?>
                        <span>Vì sao phí vận chuyển báo sau?</span>
                        <i class="ph ph-caret-down drv-icon drv-icon--xs drv-ship-collapse__arrow" aria-hidden="true"></i>
                    </summary>
                    <div class="drv-ship-collapse__body">
                        Đơn nặng <strong class="u-tabular" data-cart-total-kg><?php echo esc_html(drv_format_decimal($summary['total_kg'], 0) . ' kg'); ?></strong>.
                        Nhân viên điều vận kiểm tra đường vào công trình để ghép loại xe có cước hợp lý nhất, rồi báo phí trước khi bạn thanh toán.
                    </div>
                </details>

                <div class="drv-summary-row drv-summary-row--total">
                    <div class="l-stack l-stack--2xs">
                        <span>Tổng tạm tính (chưa gồm phí vận chuyển)</span>
                        <span class="u-text-xs u-text-muted">Giá đã gồm VAT</span>
                    </div>
                    <span class="u-text-sale u-text-xl u-tabular" data-cart-total-price><?php echo esc_html(drv_format_vnd($total_vnd)); ?></span>
                </div>

                <div class="l-stack l-stack--sm">
                    <?php do_action('woocommerce_proceed_to_checkout'); ?>
                    <a href="<?php echo esc_url($checkout); ?>" class="drv-btn drv-btn--lg drv-btn--full checkout-button">
                        <span>Tiến hành đặt hàng</span>
                        <?php drv_ui('icon', ['name' => 'arrow-right']); ?>
                    </a>
                    <button type="button" class="drv-btn drv-btn--outline drv-btn--full" data-drv-action="cart-to-quote">
                        <?php drv_ui('icon', ['name' => 'clipboard-text']); ?>
                        <span>Chuyển sang DS báo giá</span>
                    </button>
                </div>

                <div class="drv-cart-project u-bg-ivory u-bd u-rounded-sm u-pad-3 l-stack l-stack--xs">
                    <div class="l-cluster l-cluster--between">
                        <span class="u-text-xs u-bold u-text-navy">Đặt cho nhà mới hoặc đơn từ 30m²?</span>
                        <button type="button" class="drv-link u-text-xs u-bold" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal" data-drv-product="Giỏ hàng hiện tại">
                            <span>Nhận chiết khấu công trình</span>
                            <?php drv_ui('icon', ['name' => 'caret-right', 'size' => 'xs']); ?>
                        </button>
                    </div>
                    <p class="u-text-xs u-text-muted u-m-0">
                        Gửi bản vẽ mặt bằng hoặc danh mục mã gạch qua Zalo
                        <a href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" class="u-bold u-text-moss" target="_blank" rel="noopener noreferrer"><?php echo esc_html(str_replace(' ', '.', $hotline)); ?></a>
                        để nhận bảng bóc tách khối lượng theo từng phòng.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php Template::render_part('template-parts/cart/related-tiles', ['ids' => $related_ids]); ?>

<?php do_action('woocommerce_before_cart_collaterals'); ?>
<div class="cart-collaterals">
    <?php do_action('woocommerce_cart_collaterals'); ?>
</div>

<?php drv_section('trust-strip', ['surface' => 'white']); ?>

<?php
do_action('woocommerce_after_cart');
