<?php
declare(strict_types=1);

namespace Dravit\Woo;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Đăng nhập / Đăng ký / Quên mật khẩu tại `/tai-khoan` (khách chưa đăng nhập):
 * - Đăng nhập bằng SĐT (tra `billing_phone` đã chuẩn hóa) hoặc email.
 * - Đăng ký: Họ tên + SĐT (duy nhất) + Email + Mật khẩu; lưu qua `WC_Customer` CRUD.
 * - Thông báo lỗi đăng nhập chung chung (không lộ SĐT/email nào đã có tài khoản).
 * - Bỏ thước đo độ mạnh mật khẩu zxcvbn (~800KB) + selectWoo ở màn khách; kiểm tra mật khẩu phía server.
 *
 * Nonce của các form do WooCommerce xác thực (`woocommerce-login`, `woocommerce-register`, `lost_password`,
 * `reset_password`) trước khi các hook dưới đây chạy.
 */
final class AccountAuth implements ServiceProviderInterface {
    public function register(): void {
        add_filter('woocommerce_login_credentials', [$this, 'map_phone_login']);
        add_filter('authenticate', [$this, 'generic_login_error'], 99);
        add_action('wp_loaded', [$this, 'map_phone_lost_password'], 5);
        add_filter('woocommerce_process_registration_errors', [$this, 'validate_registration'], 10, 4);
        add_action('woocommerce_created_customer', [$this, 'save_new_customer'], 10, 1);
        add_action('validate_password_reset', [$this, 'validate_password_reset'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_guest_assets'], 100);
    }

    /**
     * Tìm user theo SĐT đã chuẩn hóa (`billing_phone`). Trả 0 nếu không có hoặc trùng nhiều tài khoản.
     */
    public static function find_user_by_phone(string $phone): int {
        $phone = CheckoutValidator::normalizePhone($phone);
        if ($phone === '') {
            return 0;
        }
        $ids = get_users([
            'meta_key'    => 'billing_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'  => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'number'      => 2,
            'fields'      => 'ID',
            'count_total' => false,
        ]);
        return count($ids) === 1 ? (int) $ids[0] : 0;
    }

    /**
     * @param array<string, mixed> $creds
     * @return array<string, mixed>
     */
    public function map_phone_login(array $creds): array {
        $id = AccountIdentity::classify((string) ($creds['user_login'] ?? ''));
        if ($id['type'] !== 'phone') {
            return $creds;
        }
        $user_id = self::find_user_by_phone($id['value']);
        $user    = $user_id > 0 ? get_userdata($user_id) : false;
        if ($user instanceof \WP_User) {
            $creds['user_login'] = $user->user_login;
        }
        return $creds;
    }

    /**
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function generic_login_error($user) {
        if ($user instanceof \WP_Error && array_intersect($user->get_error_codes(), ['invalid_username', 'invalid_email', 'incorrect_password'])) {
            return new \WP_Error('drv_invalid_login', AccountIdentity::genericLoginError());
        }
        return $user;
    }

    /**
     * Quên mật khẩu: WooCommerce chỉ tra theo tên đăng nhập / email → đổi SĐT sang tên đăng nhập trước khi WC xử lý.
     */
    public function map_phone_lost_password(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WC xác thực nonce `lost_password` ngay sau (wp_loaded:20); ở đây chỉ chuẩn hóa định danh.
        if (!isset($_POST['wc_reset_password'], $_POST['user_login']) || !is_string($_POST['user_login'])) {
            return;
        }
        $id = AccountIdentity::classify(sanitize_text_field(wp_unslash($_POST['user_login'])));
        // phpcs:enable
        if ($id['type'] !== 'phone') {
            return;
        }
        $user_id = self::find_user_by_phone($id['value']);
        $user    = $user_id > 0 ? get_userdata($user_id) : false;
        if ($user instanceof \WP_User) {
            $_POST['user_login'] = $user->user_login;
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $email
     */
    public function validate_registration(\WP_Error $errors, $username, $password, $email): \WP_Error {
        unset($username);
        $data = self::posted_registration();
        $data['email']    = (string) $email;
        $data['password'] = (string) $password;

        foreach (AccountIdentity::validateRegistration($data) as $field => $message) {
            $errors->add('drv_register_' . $field, $message);
        }
        if (!$errors->has_errors() && self::find_user_by_phone($data['phone']) > 0) {
            $errors->add('drv_register_phone', 'Số điện thoại này đã có tài khoản. Vui lòng đăng nhập hoặc chọn "Quên mật khẩu".');
        }
        return $errors;
    }

    public function save_new_customer(int $customer_id): void {
        $data = self::posted_registration();
        if ($data['name'] === '' && $data['phone'] === '') {
            return; // Không phải form đăng ký `/tai-khoan` (vd: Admin tạo khách) → không động vào.
        }
        $customer = new \WC_Customer($customer_id);
        $name     = mb_substr(trim($data['name']), 0, AccountIdentity::NAME_MAX);
        $phone    = CheckoutValidator::normalizePhone($data['phone']);

        if ($name !== '') {
            $customer->set_first_name($name);
            $customer->set_display_name($name);
            $customer->set_billing_first_name($name);
        }
        if ($phone !== '') {
            $customer->set_billing_phone($phone);
        }
        $customer->set_billing_email($customer->get_email());
        $customer->set_billing_country('VN');
        $customer->save();
    }

    /**
     * Đặt lại mật khẩu: cùng quy tắc với đăng ký.
     *
     * @param \WP_User|\WP_Error|false $user
     */
    public function validate_password_reset(\WP_Error $errors, $user): void {
        unset($user);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC đã xác thực nonce `reset_password`.
        if (!isset($_POST['password_1']) || !is_string($_POST['password_1'])) {
            return;
        }
        $message = AccountIdentity::passwordError((string) wp_unslash($_POST['password_1'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Mật khẩu không sanitize.
        if ($message !== '') {
            $errors->add('drv_password', $message);
        }
    }

    public function dequeue_guest_assets(): void {
        if (!function_exists('is_account_page') || !is_account_page() || is_user_logged_in()) {
            return;
        }
        foreach (['wc-password-strength-meter', 'password-strength-meter', 'zxcvbn-async', 'selectWoo'] as $handle) {
            wp_dequeue_script($handle);
        }
        wp_dequeue_style('select2');
    }

    /**
     * @return array{name: string, phone: string, honeypot: string}
     */
    private static function posted_registration(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WC đã xác thực nonce `woocommerce-register`.
        $get = static fn (string $key): string => isset($_POST[$key]) && is_string($_POST[$key])
            ? sanitize_text_field(wp_unslash($_POST[$key]))
            : '';
        $out = [
            'name'     => $get('drv_reg_name'),
            'phone'    => $get('drv_reg_phone'),
            'honeypot' => $get(AccountIdentity::HONEYPOT),
        ];
        // phpcs:enable
        return $out;
    }
}
