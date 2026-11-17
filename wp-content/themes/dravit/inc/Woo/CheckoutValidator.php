<?php
declare(strict_types=1);

namespace Dravit\Woo;

defined('ABSPATH') || exit;

/**
 * Validate dữ liệu Checkout VN (docs/04 §5.1 & §5.3, docs/09 C-2, C-3, C-12) — hàm thuần, không phụ thuộc WC.
 * Trả về mảng lỗi `[field_key => thông điệp tiếng Việt]`; mảng rỗng nghĩa là hợp lệ.
 */
final class CheckoutValidator {
    public const PHONE_PATTERN  = '/^0(3|5|7|8|9)\d{8}$/';
    public const TAX_ID_PATTERN = '/^\d{10}(-\d{3})?$/';
    public const VAT_COMPANY_MAX = 200;
    public const VAT_ADDRESS_MAX = 250;

    /**
     * Chuẩn hóa SĐT VN: bỏ khoảng trắng/dấu chấm/gạch, đổi `+84` / `84` đầu số thành `0`.
     */
    public static function normalizePhone(string $raw): string {
        $digits = (string) preg_replace('/[^\d+]/', '', trim($raw));
        if (str_starts_with($digits, '+84')) {
            $digits = '0' . substr($digits, 3);
        } elseif (str_starts_with($digits, '84') && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }
        return (string) preg_replace('/\D/', '', $digits);
    }

    public static function isValidPhone(string $raw): bool {
        return (bool) preg_match(self::PHONE_PATTERN, self::normalizePhone($raw));
    }

    public static function isValidTaxId(string $raw): bool {
        return (bool) preg_match(self::TAX_ID_PATTERN, trim($raw));
    }

    public static function isValidEmail(string $raw): bool {
        $raw = trim($raw);
        if ($raw === '') {
            return false;
        }
        if (function_exists('is_email')) {
            return (bool) is_email($raw);
        }
        return filter_var($raw, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string, mixed> $data Dữ liệu checkout đã được WooCommerce sanitize + các khóa riêng `vat_*`, `drv_ward_code`.
     * @return array<string, string>
     */
    public static function validate(array $data): array {
        $errors = [];

        $name = trim((string) ($data['billing_first_name'] ?? ''));
        if ($name === '') {
            $errors['billing_first_name'] = 'Vui lòng nhập họ và tên người nhận hàng.';
        }

        $phone = trim((string) ($data['billing_phone'] ?? ''));
        if ($phone === '') {
            $errors['billing_phone'] = 'Vui lòng nhập số điện thoại để nhân viên gọi xác nhận đơn.';
        } elseif (!self::isValidPhone($phone)) {
            $errors['billing_phone'] = 'Số điện thoại cần đủ 10 số, bắt đầu bằng 03, 05, 07, 08 hoặc 09.';
        }

        $email = trim((string) ($data['billing_email'] ?? ''));
        if ($email !== '' && !self::isValidEmail($email)) {
            $errors['billing_email'] = 'Email chưa đúng định dạng (ví dụ ten@congty.vn). Có thể để trống.';
        }

        $province = trim((string) ($data['billing_state'] ?? ''));
        if ($province === '' || !Wards::isProvince($province)) {
            $errors['billing_state'] = 'Vui lòng chọn Tỉnh / Thành phố giao hàng.';
        } else {
            $ward_name = trim((string) ($data['billing_city'] ?? ''));
            $ward_code = trim((string) ($data['drv_ward_code'] ?? ''));
            $ward      = $ward_code !== '' ? Wards::find($province, $ward_code) : Wards::findByName($province, $ward_name);
            if ($ward_name === '') {
                $errors['billing_city'] = 'Vui lòng chọn Phường / Xã nơi nhận hàng.';
            } elseif (Wards::forProvince($province) !== [] && ($ward === null || $ward['name'] !== $ward_name)) {
                $errors['billing_city'] = 'Phường / Xã không thuộc Tỉnh / Thành phố đã chọn. Vui lòng chọn lại.';
            }
        }

        if (trim((string) ($data['billing_address_1'] ?? '')) === '') {
            $errors['billing_address_1'] = 'Vui lòng nhập số nhà, tên đường hoặc vị trí công trình.';
        }

        if (self::vatRequested($data)) {
            $company = trim((string) ($data['vat_company'] ?? ''));
            if ($company === '') {
                $errors['vat_company'] = 'Vui lòng nhập tên công ty / đơn vị xuất hóa đơn.';
            } elseif (mb_strlen($company) > self::VAT_COMPANY_MAX) {
                $errors['vat_company'] = 'Tên công ty tối đa 200 ký tự.';
            }

            $tax_id = trim((string) ($data['vat_tax_id'] ?? ''));
            if ($tax_id === '') {
                $errors['vat_tax_id'] = 'Vui lòng nhập mã số thuế doanh nghiệp.';
            } elseif (!self::isValidTaxId($tax_id)) {
                $errors['vat_tax_id'] = 'Mã số thuế gồm 10 chữ số (hoặc 10 số + "-" + 3 số chi nhánh).';
            }

            $address = trim((string) ($data['vat_address'] ?? ''));
            if ($address === '') {
                $errors['vat_address'] = 'Vui lòng nhập địa chỉ đăng ký kinh doanh trên hóa đơn.';
            } elseif (mb_strlen($address) > self::VAT_ADDRESS_MAX) {
                $errors['vat_address'] = 'Địa chỉ đăng ký tối đa 250 ký tự.';
            }

            $vat_email = trim((string) ($data['vat_email'] ?? ''));
            if ($vat_email === '' && $email === '') {
                $errors['vat_email'] = 'Vui lòng nhập email nhận hóa đơn điện tử.';
            } elseif ($vat_email !== '' && !self::isValidEmail($vat_email)) {
                $errors['vat_email'] = 'Email nhận hóa đơn chưa đúng định dạng.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function vatRequested(array $data): bool {
        $flag = $data['require_vat_invoice'] ?? '';
        return in_array(is_scalar($flag) ? (string) $flag : '', ['1', 'yes', 'on', 'true'], true);
    }

    /**
     * Meta VAT chuẩn hóa để lưu qua HPOS CRUD (docs/14 §7.2).
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function vatMeta(array $data): array {
        if (!self::vatRequested($data)) {
            return ['_drv_vat_required' => '0'];
        }
        $vat_email = trim((string) ($data['vat_email'] ?? ''));
        if ($vat_email === '') {
            $vat_email = trim((string) ($data['billing_email'] ?? ''));
        }
        return [
            '_drv_vat_required' => '1',
            '_drv_vat_company'  => mb_substr(trim((string) ($data['vat_company'] ?? '')), 0, self::VAT_COMPANY_MAX),
            '_drv_vat_tax_id'   => trim((string) ($data['vat_tax_id'] ?? '')),
            '_drv_vat_address'  => mb_substr(trim((string) ($data['vat_address'] ?? '')), 0, self::VAT_ADDRESS_MAX),
            '_drv_vat_email'    => $vat_email,
        ];
    }
}
