<?php
declare(strict_types=1);

namespace Dravit\Leads;

defined('ABSPATH') || exit;

/**
 * Ghi / đọc bản ghi CPT private `drv_lead` (docs/14 §6). Không tạo bảng DB riêng (D-07, D-23).
 * Lưu ý: `drv_lead` là CPT (không phải đơn hàng) nên dùng post meta là đúng chuẩn; đơn hàng luôn qua WC CRUD (D-22).
 */
final class LeadRepository {
    public const POST_TYPE = 'drv_lead';

    /** @var array<int, string> */
    public const STATUSES = ['new', 'contacted', 'quoted', 'won', 'lost'];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'new'       => 'Mới tiếp nhận',
        'contacted' => 'Đã liên hệ',
        'quoted'    => 'Đã gửi báo giá',
        'won'       => 'Đã chốt đơn',
        'lost'      => 'Không chốt',
    ];

    public const NEW_COUNT_TRANSIENT = 'drv_new_leads_count';

    /**
     * Sinh mã phiếu `BG-{Ymd}-{4 số}` (BOQ) hoặc `LD-{Ymd}-{4 số}` (lead khác) theo giờ VN.
     *
     * @param callable(string): bool|null $exists Hàm kiểm tra trùng mã.
     */
    public static function generateCode(string $type, ?int $now = null, ?callable $exists = null): string {
        $prefix = $type === 'boq' ? 'BG' : 'LD';
        $ts     = $now ?? time();
        $date   = function_exists('wp_date') ? (string) wp_date('Ymd', $ts) : gmdate('Ymd', $ts + 7 * 3600);

        for ($i = 0; $i < 8; $i++) {
            $rand = function_exists('wp_rand') ? wp_rand(1000, 9999) : random_int(1000, 9999);
            $code = sprintf('%s-%s-%04d', $prefix, $date, $rand);
            if ($exists === null || !$exists($code)) {
                return $code;
            }
        }

        return sprintf('%s-%s-%s', $prefix, $date, substr((string) $ts, -6));
    }

    public static function codeExists(string $code): bool {
        if (!function_exists('get_posts')) {
            return false;
        }
        $found = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Chỉ chạy khi tạo lead mới (tần suất thấp).
            'meta_query'     => [['key' => '_drv_quote_code', 'value' => $code]],
        ]);
        return $found !== [];
    }

    /**
     * Tạo bản ghi lead. `$data` là output đã validate của `LeadValidator::validate()`.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $extra_meta Meta bổ sung (BOQ snapshot, UTM, ip_hash...).
     * @return array{id: int, code: string}|\WP_Error
     */
    public static function create(string $type, array $data, array $extra_meta = []): array|\WP_Error {
        if (!function_exists('wp_insert_post')) {
            return new \WP_Error('drv_unavailable', 'Hệ thống lưu trữ chưa sẵn sàng.', ['status' => 500]);
        }

        $code  = self::generateCode($type, null, [self::class, 'codeExists']);
        $title = sprintf('[%s] %s - %s', $code, (string) ($data['name'] ?? ''), (string) ($data['phone'] ?? ''));

        $post_id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'private',
            'post_title'  => $title,
        ], true);

        if ($post_id instanceof \WP_Error) {
            return $post_id;
        }
        $post_id = (int) $post_id;

        $meta = [
            '_drv_lead_type'  => $type,
            '_drv_status'     => 'new',
            '_drv_quote_code' => $code,
            '_drv_name'       => (string) ($data['name'] ?? ''),
            '_drv_phone'      => (string) ($data['phone'] ?? ''),
            '_drv_email'      => (string) ($data['email'] ?? ''),
            '_drv_province'   => (string) ($data['province'] ?? ''),
            '_drv_ward'       => (string) ($data['ward'] ?? ''),
            '_drv_role'       => (string) ($data['role'] ?? 'chu-nha'),
            '_drv_project'    => (string) ($data['project'] ?? ''),
            '_drv_area_m2'    => (float) ($data['area_m2'] ?? 0),
            '_drv_message'    => (string) ($data['message'] ?? ''),
            '_drv_product_id' => (int) ($data['product_id'] ?? 0),
            '_drv_post_id'    => (int) ($data['post_id'] ?? 0),
            '_drv_sample_box' => !empty($data['sample_box']) ? '1' : '0',
            '_drv_consent_at' => function_exists('current_time') ? (string) current_time('c') : gmdate('c'),
        ];

        if (!empty($data['showroom_id'])) {
            $meta['_drv_showroom_id'] = (string) $data['showroom_id'];
        }
        if (!empty($data['preferred_time'])) {
            $meta['_drv_preferred_time'] = (string) $data['preferred_time'];
        }

        foreach ($extra_meta as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_drv_')) {
                $meta[$key] = is_array($value) ? (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }
        }

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        self::flushNewCount();

        return ['id' => $post_id, 'code' => $code];
    }

    public static function meta(int $lead_id, string $key, mixed $default = ''): mixed {
        if (!function_exists('get_post_meta')) {
            return $default;
        }
        $value = get_post_meta($lead_id, $key, true);
        return ($value === '' || $value === false || $value === null) ? $default : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function boq(int $lead_id): array {
        $raw     = (string) self::meta($lead_id, '_drv_boq', '');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public static function countNew(): int {
        if (!function_exists('get_transient')) {
            return 0;
        }
        $cached = get_transient(self::NEW_COUNT_TRANSIENT);
        if ($cached !== false) {
            return (int) $cached;
        }
        $ids = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'private',
            'posts_per_page' => 99,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cache 5 phút.
            'meta_query'     => [['key' => '_drv_status', 'value' => 'new']],
        ]);
        $count = count($ids);
        set_transient(self::NEW_COUNT_TRANSIENT, $count, 300);
        return $count;
    }

    public static function flushNewCount(): void {
        if (function_exists('delete_transient')) {
            delete_transient(self::NEW_COUNT_TRANSIENT);
        }
    }
}
