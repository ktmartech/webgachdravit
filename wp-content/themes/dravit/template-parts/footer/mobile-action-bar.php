<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

$hotline_raw = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel = drv_clean_phone($hotline_raw);
$zalo_clean  = drv_clean_phone((string) drv_setting('contact.zalo', $hotline_raw));

$cart_count = 0;
if (drv_is_woo_active() && function_exists('WC') && WC()->cart !== null) {
    $cart_count = count(WC()->cart->get_cart());
}
?>
<nav class="drv-mobile-bar" aria-label="Thanh công cụ nhanh trên di động">
    <button type="button" class="drv-mobile-bar__btn" data-drv-action="dialog-open" data-drv-target="#drv-mobile-menu-drawer">
        <i class="ph ph-list drv-icon drv-icon--sm" aria-hidden="true"></i>
        <span>Danh mục</span>
    </button>
    <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-mobile-bar__btn">
        <i class="ph ph-phone-call drv-icon drv-icon--sm" aria-hidden="true"></i>
        <span>Gọi điện</span>
    </a>
    <a href="<?php echo esc_url('https://zalo.me/' . $zalo_clean); ?>" class="drv-mobile-bar__btn" target="_blank" rel="noopener noreferrer">
        <i class="ph ph-chat-circle-dots drv-icon drv-icon--sm" aria-hidden="true"></i>
        <span>Chat Zalo</span>
    </a>
    <button type="button" class="drv-mobile-bar__btn drv-mobile-bar__btn--accent" data-drv-action="dialog-open" data-drv-target="#drv-quote-modal">
        <i class="ph ph-file-text drv-icon drv-icon--sm" aria-hidden="true"></i>
        <span>Báo giá</span>
    </button>
    <button type="button" class="drv-mobile-bar__btn" data-drv-action="dialog-open" data-drv-target="#drv-minicart-drawer">
        <i class="ph ph-shopping-bag drv-icon drv-icon--sm" aria-hidden="true"></i>
        <span>Giỏ (<span data-drv-cart-count class="u-tabular"><?php echo esc_html((string) $cart_count); ?></span>)</span>
    </button>
</nav>

