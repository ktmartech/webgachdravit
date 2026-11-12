<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$cart_url     = (drv_is_woo_active() && function_exists('wc_get_cart_url'))
    ? untrailingslashit((string) wc_get_cart_url())
    : MegaMenuRenderer::internal_url('/gio-hang');
$checkout_url = (drv_is_woo_active() && function_exists('wc_get_checkout_url'))
    ? untrailingslashit((string) wc_get_checkout_url())
    : MegaMenuRenderer::internal_url('/thanh-toan');
$catalog_url  = MegaMenuRenderer::collection_url('gach');

$cart_count = 0;
if (drv_is_woo_active() && function_exists('WC') && WC()->cart !== null) {
    $cart_count = count(WC()->cart->get_cart());
}
?>
<dialog id="drv-minicart-drawer" class="drv-dialog drv-dialog--drawer-right" aria-labelledby="minicart-title">
    <div class="drv-dialog__head">
        <div class="l-cluster l-cluster--sm">
            <i class="ph ph-shopping-bag drv-icon u-text-moss" aria-hidden="true"></i>
            <h2 id="minicart-title" class="u-text-lg">Giỏ hàng (<span data-drv-cart-count class="u-tabular"><?php echo esc_html((string) $cart_count); ?></span> mã)</h2>
        </div>
        <button type="button" class="drv-btn drv-btn--sm drv-btn--ghost drv-btn--icon" data-drv-action="dialog-close" aria-label="Đóng giỏ hàng">
            <i class="ph ph-x drv-icon" aria-hidden="true"></i>
        </button>
    </div>

    <div class="drv-dialog__body l-stack l-stack--md" data-minicart-body>
        <div class="drv-search-empty l-stack l-stack--sm" data-minicart-empty>
            <p>Chưa có mẫu gạch nào trong giỏ hàng của bạn.</p>
            <div>
                <a href="<?php echo esc_url($catalog_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                    <span>Khám phá 28 danh mục gạch</span>
                    <i class="ph ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="drv-dialog__foot l-stack l-stack--sm" aria-live="polite">
        <div class="drv-summary-row">
            <span class="u-text-muted">Tổng diện tích gạch:</span>
            <strong class="u-tabular" data-cart-total-m2>0 m²</strong>
        </div>
        <div class="drv-summary-row">
            <span class="u-text-muted">Tổng trọng lượng ước tính:</span>
            <strong class="u-tabular" data-cart-total-kg>0 kg</strong>
        </div>
        <div class="drv-summary-row">
            <span class="u-text-muted">Phí vận chuyển:</span>
            <span class="u-text-moss u-bold">Báo sau khi xác nhận đơn</span>
        </div>
        <div class="drv-summary-row drv-summary-row--total">
            <span>Tổng tạm tính:</span>
            <span class="u-text-sale u-tabular" data-cart-total-price>0đ</span>
        </div>
        <div class="l-grid l-grid--2 l-grid--gap-sm">
            <a href="<?php echo esc_url($cart_url); ?>" class="drv-btn drv-btn--outline drv-btn--full">Xem giỏ hàng</a>
            <a href="<?php echo esc_url($checkout_url); ?>" class="drv-btn drv-btn--full">Thanh toán ngay</a>
        </div>
    </div>
</dialog>

