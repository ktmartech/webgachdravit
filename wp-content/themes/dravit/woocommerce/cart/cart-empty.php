<?php
/**
 * Giỏ hàng trống (04-cart.html §2B): CTA về `/gach` + 4 lối vào Collection tĩnh (IL-01, không query string).
 *
 * @package Dravit
 * @version 7.0.1
 */
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$silos = [
    ['slug' => 'gach-lat-nen', 'label' => 'Gạch lát nền', 'desc' => 'Khổ 60x60, 80x80, 60x120 chịu lực cao'],
    ['slug' => 'gach-phong-khach', 'label' => 'Gạch phòng khách', 'desc' => 'Vân đá, terrazzo và khổ lớn ít ron'],
    ['slug' => 'gach-nha-tam', 'label' => 'Gạch nhà tắm', 'desc' => 'Bề mặt mờ nhám chống trơn R10 - R11'],
    ['slug' => 'gach-san-vuon', 'label' => 'Gạch sân vườn', 'desc' => 'Chịu nắng mưa, chống trượt ngoài trời'],
];
$catalog = MegaMenuRenderer::collection_url('gach');
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
        <h1 class="drv-cart-hero__title">Giỏ hàng</h1>
    </div>
</section>

<section class="l-section" aria-label="Giỏ hàng trống">
    <div class="l-container l-stack l-stack--md">
        <div class="woocommerce-notices-wrapper"><?php wc_print_notices(); ?></div>
        <?php do_action('woocommerce_cart_is_empty'); ?>

        <div class="drv-cart-empty-box">
            <div class="drv-cart-empty-box__icon" aria-hidden="true">
                <i class="ph ph-shopping-bag-open"></i>
            </div>
            <div class="l-stack l-stack--xs drv-cart-empty-box__text">
                <span class="drv-sec-head__eyebrow">Giỏ hàng đang trống</span>
                <h2>Chưa có mẫu gạch nào trong giỏ</h2>
                <p class="u-text-sm u-text-muted">
                    Chọn mẫu theo không gian hoặc kích thước, rồi dùng máy tính trên trang sản phẩm để quy đổi <strong>m² sang số viên và số thùng nguyên</strong>.
                </p>
            </div>
            <div class="l-cluster l-cluster--sm drv-cart-empty-box__actions">
                <a href="<?php echo esc_url($catalog); ?>" class="drv-btn drv-btn--lg">
                    <?php drv_ui('icon', ['name' => 'grid-four']); ?>
                    <span>Xem tất cả mẫu gạch</span>
                </a>
                <a href="<?php echo esc_url(untrailingslashit(home_url('/danh-sach-bao-gia'))); ?>" class="drv-btn drv-btn--outline drv-btn--lg">
                    <?php drv_ui('icon', ['name' => 'clipboard-text']); ?>
                    <span>Mở danh sách báo giá</span>
                </a>
            </div>
            <div class="l-grout-grid l-grout-grid--4 drv-cart-empty-box__silos">
                <?php foreach ($silos as $silo) :
                    $term  = get_term_by('slug', $silo['slug'], 'product_cat');
                    $count = $term instanceof \WP_Term ? (int) $term->count : 0;
                    ?>
                    <a href="<?php echo esc_url(MegaMenuRenderer::collection_url($silo['slug'])); ?>" class="u-bg-white u-pad-4 l-stack l-stack--xs">
                        <?php if ($count > 0) : ?>
                            <span class="u-text-xs u-bold u-text-moss"><?php echo esc_html($count . ' mẫu'); ?></span>
                        <?php endif; ?>
                        <strong class="u-text-base u-text-navy"><?php echo esc_html($silo['label']); ?></strong>
                        <span class="u-text-xs u-text-muted"><?php echo esc_html($silo['desc']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
