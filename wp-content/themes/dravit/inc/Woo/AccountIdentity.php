<?php
declare(strict_types=1);

namespace Dravit\Woo;

defined('ABSPATH') || exit;

/**
 * Logic thuần cho đăng nhập / đăng ký tài khoản khách (`/tai-khoan`):
 * - Định danh đăng nhập: SĐT Việt Nam (chuẩn hóa `0xxxxxxxxx`) hoặc email (hoặc tên đăng nhập của nhân viên).
 * - Kiểm tra form đăng ký: họ tên, SĐT, email, mật khẩu, honeypot.
 */
final class AccountIdentity {
    public const PASSWORD_MIN = 8;
    public const NAME_MAX     = 60;

    /** Tên trường bẫy bot (ẩn với người thật, bot tự điền). */
    public const HONEYPOT = 'drv_company_site';

    /**
     * Phân loại chuỗi khách nhập vào ô "SĐT hoặc email".
     *
     * @return array{type: 'phone'|'email'|'login'|'empty', value: string}
     */
    public static function classify(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            return ['type' => 'empty', 'value' => ''];
        }
        if (str_contains($raw, '@')) {
            return ['type' => 'email', 'value' => strtolower($raw)];
        }
        // Chỉ coi là SĐT khi chuỗi chỉ gồm số, khoảng trắng, dấu chấm/gạch, ngoặc và `+`.
        if (preg_match('/^[\d\s().+-]+$/', $raw)) {
            $phone = CheckoutValidator::normalizePhone($raw);
            if (preg_match(CheckoutValidator::PHONE_PATTERN, $phone)) {
                return ['type' => 'phone', 'value' => $phone];
            }
        }
        return ['type' => 'login', 'value' => $raw];
    }

    /**
     * Kiểm tra dữ liệu form đăng ký. Trả về [field => thông báo lỗi]; rỗng = hợp lệ.
     *
     * @param array<string, mixed> $data Khóa: name, phone, email, password, honeypot.
     * @return array<string, string>
     */
    public static function validateRegistration(array $data): array {
        $errors   = [];
        $name     = trim((string) ($data['name'] ?? ''));
        $phone    = trim((string) ($data['phone'] ?? ''));
        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if (trim((string) ($data['honeypot'] ?? '')) !== '') {
            // Không nói rõ lý do với bot.
            return ['form' => 'Không thể tạo tài khoản lúc này. Vui lòng gọi Hotline để được hỗ trợ.'];
        }
        if ($name === '') {
            $errors['name'] = 'Vui lòng nhập họ và tên.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors['name'] = sprintf('Họ và tên tối đa %d ký tự.', self::NAME_MAX);
        }
        if ($phone === '') {
            $errors['phone'] = 'Vui lòng nhập số điện thoại.';
        } elseif (!CheckoutValidator::isValidPhone($phone)) {
            $errors['phone'] = 'Số điện thoại chưa đúng (10 số, bắt đầu bằng 03, 05, 07, 08, 09).';
        }
        if ($email === '') {
            $errors['email'] = 'Vui lòng nhập email để nhận thông báo đơn hàng và khôi phục mật khẩu.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email chưa đúng định dạng.';
        }
        $pw_error = self::passwordError($password);
        if ($pw_error !== '') {
            $errors['password'] = $pw_error;
        }
        return $errors;
    }

    /**
     * Mật khẩu tối thiểu 8 ký tự, có cả chữ và số (thay cho thước đo độ mạnh zxcvbn ~800KB của WooCommerce).
     */
    public static function passwordError(string $password): string {
        if ($password === '') {
            return 'Vui lòng đặt mật khẩu.';
        }
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return sprintf('Mật khẩu cần ít nhất %d ký tự.', self::PASSWORD_MIN);
        }
        if (!preg_match('/\pL/u', $password) || !preg_match('/\d/', $password)) {
            return 'Mật khẩu cần có cả chữ và số.';
        }
        return '';
    }

    /**
     * Thông báo chung khi đăng nhập sai — không tiết lộ SĐT/email nào đã có tài khoản.
     */
    public static function genericLoginError(): string {
        return 'Số điện thoại/email hoặc mật khẩu chưa đúng. Vui lòng thử lại hoặc chọn "Quên mật khẩu".';
    }
}
