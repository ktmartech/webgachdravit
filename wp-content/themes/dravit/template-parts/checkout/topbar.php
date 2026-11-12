<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$home_url    = MegaMenuRenderer::internal_url('/');
$cart_url    = (drv_is_woo_active() && function_exists('wc_get_cart_url'))
    ? untrailingslashit((string) wc_get_cart_url())
    : MegaMenuRenderer::internal_url('/gio-hang');
$hotline_raw = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel = drv_clean_phone($hotline_raw);
$zalo_clean  = drv_clean_phone((string) drv_setting('contact.zalo', $hotline_raw));
$brand_name  = (string) drv_setting('company.brand_name', 'DRAVIT');
?>
<header class="drv-checkout-topbar">
    <div class="l-container drv-checkout-topbar__inner">
        <div class="drv-checkout-topbar__brand">
            <a href="<?php echo esc_url($home_url); ?>" class="drv-logo" aria-label="<?php echo esc_attr($brand_name . ' - Về trang chủ'); ?>">
                <?php drv_render_brand_logo('header', false); ?>
            </a>
            <span class="drv-checkout-topbar__divider" aria-hidden="true"></span>
            <div class="drv-checkout-topbar__title">
                <span class="u-text-xs u-bold u-uppercase u-text-moss">Đặt hàng an toàn · Không thu tiền trước</span>
                <strong class="u-text-sm u-text-navy">Xác nhận thông tin giao gạch công trình</strong>
            </div>
        </div>

        <div class="drv-checkout-topbar__support">
            <a href="<?php echo esc_url($cart_url); ?>" class="drv-btn drv-btn--soft drv-btn--sm">
                <i class="ph ph-arrow-left drv-icon--xs" aria-hidden="true"></i>
                <span class="u-hide-mobile">Quay lại giỏ hàng</span>
                <span class="drv-checkout-topbar__mobile-text">Giỏ hàng</span>
            </a>
            <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-btn drv-btn--outline drv-btn--sm u-tabular">
                <i class="ph ph-phone-call drv-icon--xs u-text-moss" aria-hidden="true"></i>
                <span class="u-hide-mobile">Hỗ trợ: <?php echo esc_html($hotline_raw); ?></span>
                <span class="drv-checkout-topbar__mobile-text"><?php echo esc_html($hotline_raw); ?></span>
            </a>
            <a href="<?php echo esc_url('https://zalo.me/' . $zalo_clean); ?>" class="drv-btn drv-btn--moss drv-btn--sm u-hide-mobile" target="_blank" rel="noopener noreferrer">
                <i class="ph ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                <span>Zalo Điều vận</span>
            </a>
        </div>
    </div>
</header>

