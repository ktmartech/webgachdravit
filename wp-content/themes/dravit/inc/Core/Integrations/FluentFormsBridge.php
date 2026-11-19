<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;
use Dravit\Leads\LeadController;
use Dravit\Leads\LeadValidator;

defined('ABSPATH') || exit;

/**
 * Cầu nối Dual-Mode giữa 5 slot form báo giá/tư vấn và Fluent Forms (ADR D-31, Step 8):
 * - Slot có Form ID -> render `[fluentform id="X"]`, bơm ngữ cảnh gạch vào các Hidden field `drv_*` / `utm_*`.
 * - Bắt `fluentform/submission_inserted` -> ghi ngược 1 bản ghi CPT `drv_lead` (`_drv_ff_entry_id`).
 * - Tùy chọn `forms.boq_mirror`: nhân bản phiếu BOQ sang Entries của 1 form để kích hoạt Webhook / Zalo / Sheets.
 */
final class FluentFormsBridge implements ServiceProviderInterface {
    /** @var array<string, string> Slot cấu hình -> `_drv_lead_type`. */
    public const SLOT_SOURCES = [
        'quote_modal'      => 'quote_modal',
        'pdp_consult'      => 'pdp',
        'contact_page'     => 'contact',
        'showroom_booking' => 'showroom_visit',
        'blog_inline'      => 'blog_inline',
    ];

    /** @var array<int, string> Tên Hidden field được bơm ngữ cảnh. */
    public const CONTEXT_KEYS = ['drv_sku', 'drv_product_id', 'drv_product_name', 'drv_area_m2', 'drv_boq_summary', 'drv_page_url', 'drv_post_id', 'utm_source', 'utm_medium', 'utm_campaign'];

    /** @var array<string, string> */
    private static array $render_context = [];

    private static bool $mirroring = false;

    /** Đang xử lý entry do chính Fluent Forms tạo -> không nhân bản ngược lại. */
    private static bool $ingesting = false;

    public function register(): void {
        add_filter('fluentform/disable_default_style', '__return_false');
        add_filter('fluentform/rendering_field_data_input_hidden', [$this, 'inject_hidden_value'], 10, 2);
        add_filter('fluentform/validation_errors', [$this, 'validate_phone'], 10, 3);
        add_action('fluentform/submission_inserted', [$this, 'on_submission_inserted'], 20, 3);
        add_action('drv/lead/created', [$this, 'mirror_boq_entry'], 20, 2);
        add_action('drv/lead/created', [$this, 'mirror_native_lead'], 25, 2);
    }

    /** Giữ giao diện native cho mọi slot (lead vẫn đẩy sang Fluent Forms qua mirror). */
    public static function native_ui(): bool {
        return (bool) Settings::get('forms.native_ui', false);
    }

    public static function slot_form_id(string $slot): int {
        $raw = Settings::get('forms.' . $slot, 0);
        return is_numeric($raw) ? max(0, (int) $raw) : 0;
    }

    public static function has_slot_form(string $slot): bool {
        return drv_is_fluent_forms_active() && self::slot_form_id($slot) > 0;
    }

    /**
     * Render shortcode Fluent Forms nếu slot đã gắn Form ID; trả `true` nếu đã render.
     *
     * @param array<string, scalar> $context Ngữ cảnh gạch (drv_sku, drv_product_name, drv_area_m2...).
     */
    public static function render_slot(string $slot, array $context = []): bool {
        if (self::native_ui() || !self::has_slot_form($slot) || !function_exists('do_shortcode')) {
            return false;
        }

        self::$render_context = self::sanitizeContext($context + self::pageContext() + ['drv_page_url' => self::currentUrl()]);
        $output               = do_shortcode(sprintf('[fluentform id="%d"]', self::slot_form_id($slot)));
        $ctx_json             = (string) wp_json_encode(self::$render_context, JSON_UNESCAPED_UNICODE);
        self::$render_context = [];

        if (trim($output) === '' || str_contains($output, '[fluentform')) {
            return false;
        }

        printf(
            '<div class="drv-ff-slot" data-drv-ff-slot="%s" data-drv-ff-context="%s">',
            esc_attr($slot),
            esc_attr($ctx_json)
        );
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by Fluent Forms shortcode.
        echo '</div>';
        return true;
    }

    /**
     * Ngữ cảnh tự động theo trang hiện tại (SSR, an toàn với cache theo URL): SP -> `drv_product_*`, bài Cẩm nang -> `drv_post_id`.
     *
     * @return array<string, string>
     */
    public static function pageContext(): array {
        if (!function_exists('is_singular') || !function_exists('get_queried_object_id')) {
            return [];
        }
        if (is_singular('product') && function_exists('wc_get_product')) {
            $product = wc_get_product((int) get_queried_object_id());
            if ($product instanceof \WC_Product) {
                return array_filter([
                    'drv_product_id'   => (string) $product->get_id(),
                    'drv_sku'          => (string) $product->get_sku(),
                    'drv_product_name' => (string) $product->get_name(),
                ], static fn (string $v): bool => $v !== '' && $v !== '0');
            }
        }
        if (is_singular('post')) {
            return ['drv_post_id' => (string) get_queried_object_id()];
        }
        return [];
    }

    /**
     * Danh sách form Fluent Forms để chọn trong Admin (`id => nhãn`); rỗng khi plugin chưa bật.
     *
     * @return array<int, string>
     */
    public static function available_forms(): array {
        global $wpdb;
        if (!drv_is_fluent_forms_active() || !isset($wpdb) || !is_object($wpdb)) {
            return [];
        }
        $table = $wpdb->prefix . 'fluentform_forms';
        $rows  = $wpdb->get_results("SELECT id, title, status FROM {$table} ORDER BY id ASC", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tên bảng từ $wpdb->prefix, không có input người dùng.
        return self::formOptions(is_array($rows) ? $rows : []);
    }

    /**
     * @param array<int, array<string, mixed>> $rows Hàng `fluentform_forms` (id, title, status).
     * @return array<int, string>
     */
    public static function formOptions(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $label = '#' . $id . ' · ' . ($title !== '' ? $title : 'Chưa đặt tên');
            if (isset($row['status']) && (string) $row['status'] !== 'published') {
                $label .= ' (' . (string) $row['status'] . ')';
            }
            $out[$id] = $label;
        }
        return $out;
    }

    /**
     * Bơm giá trị SSR cho Hidden field `drv_*` (JS sẽ cập nhật tiếp khi khách đổi m² / mở modal từ PDP).
     *
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function inject_hidden_value(array $field, mixed $form): array {
        unset($form);
        $name = (string) ($field['attributes']['name'] ?? '');
        if ($name !== '' && isset(self::$render_context[$name]) && (string) ($field['attributes']['value'] ?? '') === '') {
            $field['attributes']['value'] = self::$render_context[$name];
        }
        return $field;
    }

    /**
     * Kiểm SĐT di động VN ở phía server cho mọi form thuộc slot DRAVIT. Không dùng rule `regex` của Fluent Forms: nó tách
     * chuỗi quy tắc theo `|` nên biểu thức `^(\+?84|0)...` bị cắt đôi và từ chối cả SĐT hợp lệ như `0987654321`.
     *
     * @param mixed $errors
     * @param mixed $form_data
     * @param mixed $form
     * @return mixed
     */
    public function validate_phone($errors, $form_data = [], $form = null) {
        if (!is_array($errors) || !is_array($form_data) || !is_object($form) || self::slotForFormId((int) ($form->id ?? 0)) === '') {
            return $errors;
        }
        $phone = isset($form_data['phone']) && is_scalar($form_data['phone']) ? trim((string) $form_data['phone']) : '';
        if ($phone !== '' && LeadValidator::normalizePhone($phone) === '') {
            $errors['phone'] = ['phone' => 'Số điện thoại cần gồm 10 chữ số di động Việt Nam (bắt đầu 03, 05, 07, 08 hoặc 09).'];
        }
        return $errors;
    }

    /**
     * Đồng bộ entry Fluent Forms -> `drv_lead` (chỉ với form đang gắn vào 1 trong 5 slot).
     *
     * @param mixed $entry_id
     * @param mixed $form_data
     * @param mixed $form
     */
    public function on_submission_inserted($entry_id, $form_data, $form): void {
        if (self::$mirroring || !is_array($form_data) || !is_object($form)) {
            return;
        }
        $slot = self::slotForFormId((int) ($form->id ?? 0));
        if ($slot === '') {
            return;
        }

        $mapped = self::mapSubmission($form_data, $slot);
        self::$ingesting = true;
        try {
            $result = LeadController::submit($mapped['input'], $mapped['source'], [
                'page_url'   => $mapped['page_url'],
                'utm'        => $mapped['utm'],
                'channel'    => 'fluentform',
                'extra_meta' => [
                    '_drv_ff_entry_id' => (int) $entry_id,
                    '_drv_ff_form_id'  => (int) ($form->id ?? 0),
                ],
            ]);
        } finally {
            self::$ingesting = false;
        }

        if ($result instanceof \WP_Error) {
            do_action('drv/integrations/fluentform_unmapped', (int) $entry_id, $result, $form_data);
            return;
        }
        do_action('drv/integrations/fluentform_submitted', (int) $entry_id, $result['id'], $form_data);
    }

    public static function slotForFormId(int $form_id): string {
        if ($form_id <= 0) {
            return '';
        }
        foreach (array_keys(self::SLOT_SOURCES) as $slot) {
            if (self::slot_form_id($slot) === $form_id) {
                return $slot;
            }
        }
        return '';
    }

    /**
     * Ánh xạ dữ liệu form Fluent Forms (tên trường linh hoạt) sang hợp đồng trường Lead chuẩn (docs/17 §4.1).
     * `consent = true`: Fluent Forms đã tự bắt buộc ô đồng ý (GDPR / Terms) trong form builder.
     *
     * @param array<string, mixed> $data
     * @return array{source: string, input: array<string, mixed>, page_url: string, utm: array<string, string>}
     */
    public static function mapSubmission(array $data, string $slot): array {
        $pick = static function (array $keys) use ($data): string {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $val = $data[$key];
                if (is_array($val)) {
                    $val = trim(implode(' ', array_filter(array_map(static fn ($v): string => is_scalar($v) ? trim((string) $v) : '', [
                        $val['first_name'] ?? '', $val['middle_name'] ?? '', $val['last_name'] ?? '',
                    ]))));
                }
                if (is_scalar($val) && trim((string) $val) !== '') {
                    return trim((string) $val);
                }
            }
            return '';
        };

        $message = $pick(['drv_message', 'message', 'description', 'noi_dung', 'ghi_chu']);
        $summary = $pick(['drv_boq_summary']);
        if ($summary !== '') {
            $message = trim($message . "\n" . $summary);
        }
        $sku = $pick(['drv_sku']);
        if ($sku !== '' && !str_contains($message, $sku)) {
            $message = trim('Mã quan tâm: ' . $sku . ($pick(['drv_product_name']) !== '' ? ' - ' . $pick(['drv_product_name']) : '') . "\n" . $message);
        }

        $source = self::SLOT_SOURCES[$slot] ?? 'contact';
        $input  = [
            'name'           => $pick(['drv_name', 'names', 'name', 'full_name', 'ho_ten']),
            'phone'          => $pick(['drv_phone', 'phone', 'phone_number', 'sdt', 'so_dien_thoai']),
            'email'          => $pick(['drv_email', 'email', 'email_address']),
            'province'       => strtoupper($pick(['drv_province', 'province', 'tinh'])),
            'ward'           => $pick(['drv_ward', 'ward', 'phuong_xa']),
            'role'           => $pick(['drv_role', 'role']),
            'project'        => $pick(['drv_project', 'project', 'cong_trinh']),
            'area_m2'        => $pick(['drv_area_m2', 'area_m2', 'dien_tich']),
            'product_id'     => (int) $pick(['drv_product_id', 'product_id']),
            'post_id'        => (int) $pick(['drv_post_id', 'post_id']),
            'preferred_time' => $pick(['drv_preferred_time', 'preferred_time', 'datetime', 'ngay_hen']),
            'message'        => $message !== '' ? $message : 'Gửi từ Fluent Forms',
            'consent'        => true,
        ];

        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid', 'fbclid'] as $key) {
            $val = $pick([$key]);
            if ($val !== '') {
                $utm[$key] = $val;
            }
        }

        return [
            'source'   => $source,
            'input'    => $input,
            'page_url' => $pick(['drv_page_url', '_wp_http_referer']),
            'utm'      => LeadValidator::sanitizeUtm($utm),
        ];
    }

    /**
     * Nhân bản phiếu BOQ sang Entries Fluent Forms (khi `forms.boq_mirror > 0`) để chạy integration của FF.
     *
     * @param array<string, mixed> $payload
     */
    public function mirror_boq_entry(int $lead_id, array $payload): void {
        $form_id = self::slot_form_id('boq_mirror');
        if ($form_id <= 0 || ($payload['lead_type'] ?? '') !== 'boq' || !function_exists('wpFluent')) {
            return;
        }

        try {
            $form = wpFluent()->table('fluentform_forms')->find($form_id);
            if (!is_object($form)) {
                return;
            }
            $totals   = is_array($payload['boq']['totals'] ?? null) ? $payload['boq']['totals'] : [];
            $response = [
                'drv_name'         => (string) ($payload['name'] ?? ''),
                'drv_phone'        => (string) ($payload['phone'] ?? ''),
                'drv_email'        => (string) ($payload['email'] ?? ''),
                'drv_province'     => (string) ($payload['province'] ?? ''),
                'drv_project'      => (string) ($payload['project'] ?? ''),
                'drv_area_m2'      => (string) ($totals['total_m2'] ?? ''),
                'drv_boq_summary'  => self::boqSummary($payload['boq']['rooms'] ?? [], $totals),
                'drv_quote_code'   => (string) ($payload['code'] ?? ''),
                'drv_lead_id'      => (string) $lead_id,
                'message'          => (string) ($payload['message'] ?? ''),
            ];
            $now    = current_time('mysql');
            $serial = (int) wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->max('serial_number');
            $entry  = (int) wpFluent()->table('fluentform_submissions')->insertGetId([
                'form_id'       => $form_id,
                'serial_number' => $serial + 1,
                'response'      => (string) wp_json_encode($response, JSON_UNESCAPED_UNICODE),
                'source_url'    => (string) get_post_meta($lead_id, '_drv_page_url', true),
                'user_id'       => get_current_user_id(),
                'status'        => 'unread',
                'is_favourite'  => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            if ($entry > 0) {
                update_post_meta($lead_id, '_drv_ff_entry_id', $entry);
                self::$mirroring = true;
                do_action('fluentform/submission_inserted', $entry, $response, $form);
            }
        } catch (\Throwable $e) {
            do_action('drv/integrations/fluentform_mirror_failed', $lead_id, $e->getMessage());
        } finally {
            self::$mirroring = false;
        }
    }

    /**
     * Chế độ UI native: nhân bản lead (contact, pdp, showroom...) vào Entries của Form ID gắn với slot,
     * để Webhook / Zalo / Sheets / email của Fluent Forms chạy như khi khách gửi form Fluent.
     *
     * @param array<string, mixed> $payload
     */
    public function mirror_native_lead(int $lead_id, array $payload): void {
        $type = (string) ($payload['lead_type'] ?? '');
        if (self::$ingesting || self::$mirroring || $type === 'boq' || !self::native_ui() || !function_exists('wpFluent') || !drv_is_fluent_forms_active()) {
            return;
        }
        $slot    = array_search($type, self::SLOT_SOURCES, true);
        $form_id = is_string($slot) ? self::slot_form_id($slot) : 0;
        if ($form_id <= 0) {
            return;
        }

        try {
            $form = wpFluent()->table('fluentform_forms')->find($form_id);
            if (!is_object($form)) {
                return;
            }
            $page_url = (string) get_post_meta($lead_id, '_drv_page_url', true);
            $utm_meta = get_post_meta($lead_id, '_drv_utm', true);
            $utm      = is_array($utm_meta) ? array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $utm_meta) : [];
            $response = self::nativeResponse($payload, $lead_id, $page_url, $utm);

            $now    = current_time('mysql');
            $serial = (int) wpFluent()->table('fluentform_submissions')->where('form_id', $form_id)->max('serial_number');
            $entry  = (int) wpFluent()->table('fluentform_submissions')->insertGetId([
                'form_id'       => $form_id,
                'serial_number' => $serial + 1,
                'response'      => (string) wp_json_encode($response, JSON_UNESCAPED_UNICODE),
                'source_url'    => $page_url,
                'user_id'       => get_current_user_id(),
                'status'        => 'unread',
                'is_favourite'  => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            if ($entry > 0) {
                update_post_meta($lead_id, '_drv_ff_entry_id', $entry);
                update_post_meta($lead_id, '_drv_ff_form_id', $form_id);
                self::$mirroring = true;
                do_action('fluentform/submission_inserted', $entry, $response, $form);
            }
        } catch (\Throwable $e) {
            do_action('drv/integrations/fluentform_mirror_failed', $lead_id, $e->getMessage());
        } finally {
            self::$mirroring = false;
        }
    }

    /**
     * Lead native -> khóa field của form mẫu Fluent Forms (`config/fluent-forms/*.json`) + khóa `drv_*`.
     *
     * @param array<string, mixed>  $payload
     * @param array<string, string> $utm
     * @return array<string, mixed>
     */
    public static function nativeResponse(array $payload, int $lead_id, string $page_url, array $utm): array {
        $product_id = (int) ($payload['product_id'] ?? 0);
        $product    = $product_id > 0 && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        $area       = (float) ($payload['area_m2'] ?? 0);
        $post_id    = (int) ($payload['post_id'] ?? 0);
        $response   = [
            'name'             => (string) ($payload['name'] ?? ''),
            'phone'            => (string) ($payload['phone'] ?? ''),
            'email'            => (string) ($payload['email'] ?? ''),
            'province'         => (string) ($payload['province'] ?? ''),
            'ward'             => (string) ($payload['ward'] ?? ''),
            'role'             => (string) ($payload['role'] ?? ''),
            'project'          => (string) ($payload['project'] ?? ''),
            'area_m2'          => $area > 0 ? (string) $area : '',
            'preferred_time'   => (string) ($payload['preferred_time'] ?? ''),
            'message'          => (string) ($payload['message'] ?? ''),
            'drv_product_id'   => $product_id > 0 ? (string) $product_id : '',
            'drv_sku'          => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
            'drv_product_name' => $product instanceof \WC_Product ? (string) $product->get_name() : '',
            'drv_post_id'      => $post_id > 0 ? (string) $post_id : '',
            'drv_page_url'     => $page_url,
            'drv_quote_code'   => (string) ($payload['code'] ?? ''),
            'drv_lead_id'      => (string) $lead_id,
        ];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid', 'fbclid'] as $key) {
            if (($utm[$key] ?? '') !== '') {
                $response[$key] = $utm[$key];
            }
        }
        return array_filter($response, static fn ($v): bool => $v !== '');
    }

    /**
     * @param mixed                $rooms
     * @param array<string, mixed> $totals
     */
    public static function boqSummary(mixed $rooms, array $totals): string {
        $lines = [];
        foreach (is_array($rooms) ? $rooms : [] as $room) {
            foreach ((array) ($room['slots'] ?? []) as $slot) {
                $lines[] = sprintf(
                    '%s · %s: %s (%s m²)',
                    (string) ($room['name'] ?? ''),
                    (string) ($slot['sku'] ?? ''),
                    (string) ($slot['box_text'] ?? ''),
                    drv_format_decimal((float) ($slot['actual_m2'] ?? 0), 2)
                );
            }
        }
        if ($totals !== []) {
            $lines[] = sprintf('Tổng: %s m² · %d thùng · %s kg', drv_format_decimal((float) ($totals['total_m2'] ?? 0), 2), (int) ($totals['total_boxes'] ?? 0), drv_format_decimal((float) ($totals['total_kg'] ?? 0), 0));
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, scalar> $context
     * @return array<string, string>
     */
    public static function sanitizeContext(array $context): array {
        $out = [];
        foreach (self::CONTEXT_KEYS as $key) {
            if (isset($context[$key]) && is_scalar($context[$key])) {
                $out[$key] = LeadValidator::text($context[$key], $key === 'drv_boq_summary' ? 1000 : 250);
            }
        }
        return $out;
    }

    private static function currentUrl(): string {
        if (!function_exists('home_url')) {
            return '';
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '/';
        return home_url($uri);
    }
}
