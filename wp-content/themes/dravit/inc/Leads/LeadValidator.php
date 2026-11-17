<?php
declare(strict_types=1);

namespace Dravit\Leads;

defined('ABSPATH') || exit;

/**
 * Validate & làm sạch trường liên hệ hợp nhất cho mọi nguồn Lead / BOQ (docs/17 §4.1, docs/15 §2.6).
 * Hàm thuần (không gọi DB) để dùng chung REST, `admin-post.php` fallback và Fluent Forms bridge.
 */
final class LeadValidator {
    /** @var array<int, string> */
    public const SOURCES = ['boq', 'quote_modal', 'pdp', 'contact', 'sample', 'showroom_visit', 'blog_inline', 'ai_handoff'];

    /** @var array<string, string> */
    public const ROLES = [
        'chu-nha'  => 'Chủ nhà',
        'nha-thau' => 'Nhà thầu',
        'kts'      => 'Kiến trúc sư',
        'dai-ly'   => 'Đại lý',
    ];

    /** @var array<string, string> */
    public const SOURCE_LABELS = [
        'boq'            => 'BOQ Công trình',
        'quote_modal'    => 'Báo giá nhanh',
        'pdp'            => 'Từ SP (PDP)',
        'contact'        => 'Liên hệ',
        'sample'         => 'Nhận mẫu thật',
        'showroom_visit' => 'Hẹn Showroom',
        'blog_inline'    => 'Cẩm nang',
        'ai_handoff'     => 'Trợ lý AI',
    ];

    /**
     * Chuẩn hóa SĐT di động VN về dạng `0xxxxxxxxx`; trả `''` nếu không hợp lệ.
     */
    public static function normalizePhone(string $raw): string {
        $digits = (string) preg_replace('/[\s.\-()]/', '', trim($raw));
        if (str_starts_with($digits, '+84')) {
            $digits = '0' . substr($digits, 3);
        } elseif (str_starts_with($digits, '84') && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }

        return preg_match('/^0[35789]\d{8}$/', $digits) === 1 ? $digits : '';
    }

    public static function normalizeSource(string $raw): string {
        $key = strtolower(trim($raw));
        $map = [
            'contact_page'     => 'contact',
            'pdp_consult'      => 'pdp',
            'showroom_booking' => 'showroom_visit',
            'blog_sample'      => 'sample',
        ];
        $key = $map[$key] ?? $key;

        return in_array($key, self::SOURCES, true) ? $key : '';
    }

    /**
     * @param array<string, mixed> $input         Dữ liệu thô khách gửi.
     * @param array<int, string>   $province_codes Mã 34 tỉnh hợp lệ.
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $input, string $source, array $province_codes = []): array {
        $errors = [];
        $source = self::normalizeSource($source);
        if ($source === '') {
            return ['data' => [], 'errors' => ['source' => 'Nguồn biểu mẫu không hợp lệ.']];
        }

        $name = self::text($input['name'] ?? '', 80);
        if (mb_strlen($name) < 2 || preg_match('/\p{L}/u', $name) !== 1) {
            $errors['name'] = 'Vui lòng nhập họ và tên (tối thiểu 2 ký tự).';
        }

        $phone = self::normalizePhone((string) ($input['phone'] ?? ''));
        if ($phone === '') {
            $errors['phone'] = 'Số điện thoại cần gồm 10 chữ số di động Việt Nam (ví dụ: 0909123456).';
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '') {
            $valid_email = function_exists('is_email') ? is_email($email) : filter_var($email, FILTER_VALIDATE_EMAIL);
            if ($valid_email === false || $valid_email === '') {
                $errors['email'] = 'Email chưa đúng định dạng (ví dụ: ten@congty.vn).';
                $email = '';
            } else {
                $email = strtolower($email);
            }
        }

        $province = strtoupper(trim((string) ($input['province'] ?? '')));
        if ($province !== '' && $province_codes !== [] && !in_array($province, $province_codes, true)) {
            $province = '';
        }
        if ($province === '' && in_array($source, ['boq', 'sample'], true)) {
            $errors['province'] = 'Vui lòng chọn Tỉnh / Thành phố giao hàng.';
        }

        $ward = self::text($input['ward'] ?? '', 80);
        if ($ward === '' && $source === 'sample') {
            $errors['ward'] = 'Vui lòng nhập Phường / Xã nhận mẫu.';
        }

        $role = (string) ($input['role'] ?? 'chu-nha');
        if (!array_key_exists($role, self::ROLES)) {
            $role = 'chu-nha';
        }

        $area = (float) str_replace(',', '.', (string) ($input['area_m2'] ?? 0));
        $area = max(0.0, min(50000.0, round($area, 2)));

        $product_id = max(0, (int) ($input['product_id'] ?? 0));
        if ($product_id <= 0 && in_array($source, ['pdp', 'sample'], true)) {
            $errors['product_id'] = 'Thiếu mã sản phẩm cần tư vấn.';
        }

        $post_id = max(0, (int) ($input['post_id'] ?? 0));
        if ($post_id <= 0 && $source === 'blog_inline') {
            $errors['post_id'] = 'Thiếu bài viết nguồn.';
        }

        $preferred_time = self::text($input['preferred_time'] ?? '', 60);
        if ($preferred_time === '' && $source === 'showroom_visit') {
            $errors['preferred_time'] = 'Vui lòng chọn ngày / khung giờ muốn đến xem mẫu.';
        }

        $message = self::textarea($input['message'] ?? '', 1000);
        if ($source === 'contact' && mb_strlen($message) < 5) {
            $errors['message'] = 'Vui lòng mô tả ngắn nhu cầu cần hỗ trợ (tối thiểu 5 ký tự).';
        }
        if ($source === 'sample' && $message === '') {
            $errors['message'] = 'Vui lòng nhập địa chỉ nhận mẫu chi tiết.';
        }

        if (!self::truthy($input['consent'] ?? false)) {
            $errors['consent'] = 'Vui lòng xác nhận đồng ý để DRAVIT liên hệ tư vấn.';
        }

        $data = [
            'source'         => $source,
            'name'           => $name,
            'phone'          => $phone,
            'email'          => $email,
            'province'       => $province,
            'ward'           => $ward,
            'role'           => $role,
            'project'        => self::text($input['project'] ?? '', 120),
            'area_m2'        => $area,
            'product_id'     => $product_id,
            'post_id'        => $post_id,
            'sample_box'     => $source === 'sample' ? true : self::truthy($input['sample_box'] ?? false),
            'showroom_id'    => self::text($input['showroom_id'] ?? '', 40),
            'preferred_time' => $preferred_time,
            'message'        => $message,
            'consent'        => self::truthy($input['consent'] ?? false),
        ];

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * Chuẩn hóa UTM whitelist (docs/17 §4.1).
     *
     * @return array<string, string>
     */
    public static function sanitizeUtm(mixed $raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid', 'fbclid'] as $key) {
            $val = self::text($raw[$key] ?? '', 120);
            if ($val !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    public static function truthy(mixed $v): bool {
        return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on' || $v === 'yes';
    }

    public static function text(mixed $raw, int $max): string {
        $str = is_scalar($raw) ? (string) $raw : '';
        $str = function_exists('sanitize_text_field') ? sanitize_text_field($str) : trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($str)));
        return mb_substr(trim($str), 0, $max);
    }

    public static function textarea(mixed $raw, int $max): string {
        $str = is_scalar($raw) ? (string) $raw : '';
        $str = function_exists('sanitize_textarea_field') ? sanitize_textarea_field($str) : trim(strip_tags($str));
        return mb_substr(trim($str), 0, $max);
    }
}
