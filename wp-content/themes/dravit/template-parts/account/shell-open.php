<?php
/**
 * Khung trang tài khoản khi CHƯA đăng nhập (đăng nhập · đăng ký · quên/đặt lại mật khẩu).
 * Mở hero + lưới 2 cột; đóng bằng `template-parts/account/shell-close.php`.
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$eyebrow = (string) ($args['eyebrow'] ?? 'Tài khoản DRAVIT');
$title   = (string) ($args['title'] ?? 'Đăng nhập');
$lead    = (string) ($args['lead'] ?? '');
$crumb   = (string) ($args['crumb'] ?? $title);
$account = untrailingslashit((string) wc_get_page_permalink('myaccount'));

$items = [['label' => 'Trang chủ', 'url' => untrailingslashit(home_url('/'))]];
if ($crumb !== 'Tài khoản') {
    $items[] = ['label' => 'Tài khoản', 'url' => $account];
}
$items[] = ['label' => $crumb];
?>
<section class="l-section l-section--white u-bd-b drv-auth-hero">
    <div class="l-container l-stack l-stack--sm">
        <?php drv_ui('breadcrumb', ['items' => $items]); ?>
        <span class="drv-sec-head__eyebrow"><?php echo esc_html($eyebrow); ?></span>
        <h1 class="drv-auth-hero__title"><?php echo esc_html($title); ?></h1>
        <?php if ($lead !== '') : ?>
            <p class="drv-auth-hero__lead"><?php echo esc_html($lead); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="l-section drv-auth-sec">
    <div class="l-container">
        <div class="drv-auth-layout">
            <div class="drv-auth-main">
