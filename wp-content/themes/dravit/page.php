<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

if (function_exists('is_page') && is_page('gioi-thieu')) {
    require DRV_DIR . '/page-templates/template-about.php';
    return;
}

get_header();

// Giỏ hàng & Thanh toán (Classic Shortcode D-15): template WooCommerce trong theme tự dựng H1, steps và lưới 2 cột.
// Tài khoản khi CHƯA đăng nhập (đăng nhập · đăng ký · quên/đặt lại mật khẩu): template `woocommerce/myaccount/*` tự dựng hero + lưới.
$is_auth_flow = drv_is_woo_active() && function_exists('is_account_page') && is_account_page() && !is_user_logged_in();
$is_woo_flow  = $is_auth_flow || (drv_is_woo_active()
    && ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout())));

if ($is_woo_flow) :
    $flow_class = $is_auth_flow
        ? 'drv-page--account'
        : ((function_exists('is_cart') && is_cart()) ? 'drv-page--cart' : 'drv-page--checkout');
    ?>
<main id="main-content" class="drv-page <?php echo esc_attr($flow_class); ?>">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</main>
    <?php
else :
    ?>
<main id="main-content" class="l-container l-section">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('drv-page'); ?>>
            <h1 class="drv-page__title"><?php the_title(); ?></h1>
            <div class="drv-page__content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>
    <?php
endif;

get_footer();
