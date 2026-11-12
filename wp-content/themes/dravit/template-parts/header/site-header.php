<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;
use Dravit\Core\Template;

defined('ABSPATH') || exit;

$home_url    = MegaMenuRenderer::internal_url('/');
$boq_url     = MegaMenuRenderer::internal_url('/danh-sach-bao-gia');
$ai_url      = MegaMenuRenderer::internal_url('/tro-ly-ai-tim-gach');
$ai_enabled  = (bool) drv_setting('features.ai_enabled', true);
$hotline_raw = (string) drv_setting('contact.hotline', '0909 888 777');
$hotline_tel = drv_clean_phone($hotline_raw);
$brand_name  = (string) drv_setting('company.brand_name', 'DRAVIT');

$cart_count = 0;
if (drv_is_woo_active() && function_exists('WC') && WC()->cart !== null) {
    $cart_count = count(WC()->cart->get_cart());
}
?>
<header class="drv-header">
    <div class="l-container drv-header__top">
        <button type="button"
                class="drv-btn drv-btn--ghost drv-btn--sm drv-btn--icon drv-header__menu-btn u-only-mobile"
                data-drv-action="dialog-open"
                data-drv-target="#drv-mobile-menu-drawer"
                aria-expanded="false"
                aria-controls="drv-mobile-menu-drawer"
                aria-label="Mở danh mục điều hướng">
            <i class="ph ph-list drv-icon" aria-hidden="true"></i>
        </button>

        <a href="<?php echo esc_url($home_url); ?>" class="drv-logo" aria-label="<?php echo esc_attr($brand_name . ' - Trang chủ'); ?>">
            <?php drv_render_brand_logo('header', false); ?>
        </a>

        <?php Template::render_part('template-parts/header/search-bar'); ?>

        <div class="drv-header__actions">
            <a href="<?php echo esc_url('tel:' . $hotline_tel); ?>" class="drv-header__info-item u-tabular">
                <i class="ph ph-phone-call drv-icon u-text-moss" aria-hidden="true"></i>
                <span><?php echo esc_html($hotline_raw); ?></span>
            </a>

            <?php if ($ai_enabled) : ?>
                <a href="<?php echo esc_url($ai_url); ?>" class="drv-btn drv-btn--moss drv-btn--sm u-hide-mobile" aria-label="Trợ lý AI tư vấn chọn gạch">
                    <i class="ph-fill ph-robot drv-icon drv-icon--sm" aria-hidden="true"></i>
                    <span>Trợ lý AI</span>
                </a>
            <?php endif; ?>

            <button type="button"
                    class="drv-btn drv-btn--ghost drv-btn--sm drv-btn--icon drv-header__search-toggle"
                    data-drv-action="search-toggle"
                    aria-label="Mở tìm kiếm"
                    aria-expanded="false"
                    aria-controls="global-search-input">
                <i class="ph ph-magnifying-glass drv-icon drv-icon--sm" aria-hidden="true"></i>
            </button>

            <a href="<?php echo esc_url($boq_url); ?>"
               class="drv-btn drv-btn--outline drv-btn--sm drv-header__cart-btn"
               aria-label="Mở danh sách báo giá công trình">
                <span class="drv-header__cart-icon-wrap">
                    <i class="ph ph-clipboard-text drv-icon drv-icon--sm" aria-hidden="true"></i>
                    <span class="drv-header__cart-badge u-tabular" data-drv-quote-count>0</span>
                </span>
                <span class="drv-header__cart-text">DS Báo giá (<span data-quote-badge-count class="u-tabular">0</span>)</span>
            </a>

            <button type="button"
                    class="drv-btn drv-btn--sm drv-header__cart-btn"
                    data-drv-action="dialog-open"
                    data-drv-target="#drv-minicart-drawer"
                    aria-expanded="false"
                    aria-controls="drv-minicart-drawer"
                    aria-label="Mở giỏ hàng">
                <span class="drv-header__cart-icon-wrap">
                    <i class="ph ph-shopping-bag drv-icon drv-icon--sm" aria-hidden="true"></i>
                    <span class="drv-header__cart-badge u-tabular" data-drv-cart-count><?php echo esc_html((string) $cart_count); ?></span>
                </span>
                <span class="drv-header__cart-text">Giỏ hàng</span>
            </button>
        </div>
    </div>

    <?php Template::render_part('template-parts/header/nav-bar'); ?>
</header>

