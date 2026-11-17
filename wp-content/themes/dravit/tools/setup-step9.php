<?php
/**
 * Step 9 setup (idempotent) — chạy: `./tools/wp-cli.ps1 eval-file tools/setup-step9.php`
 *
 * - Bán duy nhất Việt Nam, khách vãng lai đặt được (docs/04 §5.1).
 * - Shipping zone "Việt Nam" + phương thức `drv_quote_later` (docs/04 §6).
 * - Trang điều khoản WooCommerce = `/dieu-khoan` để bật ô đồng ý bắt buộc (C-9).
 * - Tên cổng BACS/COD tiếng Việt; tắt hướng dẫn chuyển khoản mặc định của BACS ở bước đặt hàng.
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce chưa kích hoạt.');
}

update_option('woocommerce_allowed_countries', 'specific');
update_option('woocommerce_specific_allowed_countries', ['VN']);
update_option('woocommerce_ship_to_countries', 'specific');
update_option('woocommerce_specific_ship_to_countries', ['VN']);
update_option('woocommerce_default_country', 'VN:HCM');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_coupons', 'no');
update_option('woocommerce_ship_to_destination', 'billing_only');
update_option('woocommerce_enable_shipping_calc', 'no');
update_option('woocommerce_shipping_cost_requires_address', 'no');

// `/tai-khoan`: khách tự đăng ký (Họ tên + SĐT + Email + Mật khẩu tự đặt), tên đăng nhập tự sinh, vẫn giữ guest checkout.
update_option('woocommerce_enable_myaccount_registration', 'yes');
update_option('woocommerce_registration_generate_username', 'yes');
update_option('woocommerce_registration_generate_password', 'no');
update_option('woocommerce_enable_checkout_login_reminder', 'no');
update_option('woocommerce_enable_signup_and_login_from_checkout', 'no');
update_option('woocommerce_registration_privacy_policy_text', 'Thông tin của Quý khách chỉ dùng để xử lý đơn hàng, quản lý tài khoản và các mục đích nêu trong [privacy_policy].');

$terms = get_page_by_path('dieu-khoan');
if ($terms instanceof WP_Post) {
    update_option('woocommerce_terms_page_id', (int) $terms->ID);
    update_option('woocommerce_checkout_terms_and_conditions_checkbox_text', 'Tôi đã đọc và đồng ý với [terms]');
    WP_CLI::log('Terms page → #' . $terms->ID);
} else {
    WP_CLI::warning('Chưa có trang /dieu-khoan — ô đồng ý điều khoản sẽ không hiện.');
}

// Shipping zone "Việt Nam" + drv_quote_later.
$zone_id = 0;
foreach (WC_Shipping_Zones::get_zones() as $z) {
    if (($z['zone_name'] ?? '') === 'Việt Nam') {
        $zone_id = (int) $z['id'];
        break;
    }
}
$zone = new WC_Shipping_Zone($zone_id ?: null);
if ($zone_id === 0) {
    $zone->set_zone_name('Việt Nam');
    $zone->set_zone_order(0);
    $zone->add_location('VN', 'country');
    $zone->save();
    WP_CLI::log('Created zone Việt Nam #' . $zone->get_id());
}
$has_method = false;
foreach ($zone->get_shipping_methods() as $m) {
    if ($m->id === 'drv_quote_later') {
        $has_method = true;
    }
}
if (!$has_method) {
    $instance = $zone->add_shipping_method('drv_quote_later');
    WP_CLI::log('Added drv_quote_later instance #' . $instance);
}

// Cổng thanh toán.
$bacs = (array) get_option('woocommerce_bacs_settings', []);
$bacs = array_merge($bacs, [
    'enabled'      => 'yes',
    'title'        => 'Chuyển khoản ngân hàng (VietQR)',
    'description'  => '',
    'instructions' => '',
]);
update_option('woocommerce_bacs_settings', $bacs);

$cod = (array) get_option('woocommerce_cod_settings', []);
$cod = array_merge($cod, [
    'enabled'      => 'yes',
    'title'        => 'Thanh toán khi nhận hàng (COD)',
    'description'  => '',
    'instructions' => '',
]);
update_option('woocommerce_cod_settings', $cod);

$cheque = (array) get_option('woocommerce_cheque_settings', []);
$cheque['enabled'] = 'no';
update_option('woocommerce_cheque_settings', $cheque);

// Ngôn ngữ site = tiếng Việt (D-01): chuỗi gốc WP/WooCommerce (màn đăng nhập xem đơn, tài khoản, email) không lẫn tiếng Anh.
// Gói dịch cần tải từ wordpress.org — chạy 1 lần trên mỗi môi trường:
//   ./tools/wp-cli.ps1 language core install vi --activate
//   ./tools/wp-cli.ps1 language plugin install --all vi
if (get_option('WPLANG') !== 'vi') {
    WP_CLI::warning('WPLANG chưa là "vi" — chạy `language core install vi --activate` và `language plugin install --all vi`.');
}

WC_Cache_Helper::get_transient_version('shipping', true);
WP_CLI::success('Step 9 setup xong.');
