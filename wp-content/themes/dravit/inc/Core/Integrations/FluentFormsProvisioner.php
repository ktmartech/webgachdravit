<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;

defined('ABSPATH') || exit;

/**
 * Tự tạo các form Fluent Forms từ `config/fluent-forms/{slot}.json` và gắn Form ID vào `drv_settings.forms` (ADR D-31).
 * - Chạy tự động ở `admin_init` (quản trị viên) khi Fluent Forms bật và còn slot chưa có form hợp lệ.
 * - Mỗi slot chỉ tự tạo 1 lần cho mỗi `drv_version` của file JSON; nút "Tạo lại form còn thiếu" ở Admin bỏ qua giới hạn đó.
 * - Không ghi đè form đã gắn: slot đang trỏ tới form còn tồn tại sẽ được giữ nguyên.
 * - Tệp JSON theo định dạng Export của Fluent Forms nên vẫn có thể nhập tay qua giao diện Fluent Forms.
 */
final class FluentFormsProvisioner implements ServiceProviderInterface {
    public const OPTION_KEY = 'drv_ff_provisioned';
    public const ACTION     = 'drv_provision_forms';
    public const META_SLOT  = '_drv_slot';

    public function register(): void {
        add_action('admin_init', [$this, 'maybe_provision'], 30);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_manual']);
    }

    public static function definitions_dir(): string {
        return DRV_DIR . '/config/fluent-forms';
    }

    /**
     * Đọc & kiểm tra 1 định nghĩa form; trả `null` nếu JSON hỏng hoặc thiếu trường bắt buộc.
     *
     * @return array<string, mixed>|null
     */
    public static function parseDefinition(string $json): ?array {
        $data = json_decode($json, true);
        $item = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data[0] : null;
        if ($item === null) {
            return null;
        }
        $slot = (string) ($item['drv_slot'] ?? '');
        if (!array_key_exists($slot, self::slot_labels()) || !is_array($item['form']['fields'] ?? null) || trim((string) ($item['title'] ?? '')) === '') {
            return null;
        }
        $item['drv_version'] = max(1, (int) ($item['drv_version'] ?? 1));
        return $item;
    }

    /**
     * @return array<string, string> slot => nhãn (cùng danh sách slot của `Settings`).
     */
    public static function slot_labels(): array {
        return [
            'boq_mirror'       => 'BOQ Entries',
            'quote_modal'      => 'Báo giá nhanh',
            'pdp_consult'      => 'Tư vấn sản phẩm',
            'contact_page'     => 'Liên hệ',
            'showroom_booking' => 'Đặt lịch xem mẫu',
            'blog_inline'      => 'Cẩm nang',
        ];
    }

    /**
     * Hàng `fluentform_forms` (cùng cấu trúc `TransferService::importForms` của Fluent Forms).
     *
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    public static function buildFormRow(array $def, string $now, int $user_id): array {
        return [
            'title'       => mb_substr(trim((string) $def['title']), 0, 255),
            'status'      => 'published',
            'form_fields' => (string) json_encode($def['form'], JSON_UNESCAPED_UNICODE),
            'has_payment' => 0,
            'type'        => 'form',
            'created_by'  => $user_id,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
    }

    /**
     * @param array<string, mixed> $def
     * @return array<int, array{meta_key: string, value: string}>
     */
    public static function buildMetaRows(array $def): array {
        $rows = [];
        foreach ((array) ($def['metas'] ?? []) as $meta) {
            $key = is_array($meta) ? (string) ($meta['meta_key'] ?? '') : '';
            if ($key === '') {
                continue;
            }
            $value  = $meta['value'] ?? '';
            $rows[] = ['meta_key' => $key, 'value' => is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE)];
        }
        $rows[] = ['meta_key' => self::META_SLOT, 'value' => (string) $def['drv_slot']];
        return $rows;
    }

    /**
     * @return array<string, array<string, mixed>> slot => định nghĩa hợp lệ.
     */
    public static function load_definitions(): array {
        $out = [];
        foreach (glob(self::definitions_dir() . '/*.json') ?: [] as $file) {
            $raw = file_get_contents($file);
            $def = $raw === false ? null : self::parseDefinition($raw);
            if ($def !== null) {
                $out[(string) $def['drv_slot']] = $def;
            }
        }
        return $out;
    }

    /**
     * Slot cần tạo form: chưa có Form ID hợp lệ và (khi không ép) chưa được tự tạo ở phiên bản JSON này.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function pending_slots(bool $force = false): array {
        $recorded = get_option(self::OPTION_KEY, []);
        $recorded = is_array($recorded) ? $recorded : [];
        $pending  = [];
        foreach (self::load_definitions() as $slot => $def) {
            if (FluentFormsBridge::slot_form_id($slot) > 0 && self::form_exists(FluentFormsBridge::slot_form_id($slot))) {
                continue;
            }
            if (!$force && (int) ($recorded[$slot] ?? 0) >= (int) $def['drv_version']) {
                continue;
            }
            $pending[$slot] = $def;
        }
        return $pending;
    }

    public function maybe_provision(): void {
        if (!current_user_can('manage_options') || (function_exists('wp_doing_ajax') && wp_doing_ajax()) || !self::tables_ready()) {
            return;
        }
        if (self::pending_slots() !== []) {
            self::provision();
        }
    }

    public function handle_manual(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'dravit'), 403);
        }
        check_admin_referer(self::ACTION);
        $created = self::tables_ready() ? self::provision(true) : [];
        wp_safe_redirect(add_query_arg([
            'page'        => \Dravit\Admin\SettingsPage::MENU_SLUG,
            'tab'         => 'forms',
            'drv_ff_made' => count($created),
        ], admin_url('themes.php')));
        exit;
    }

    /**
     * Tạo (hoặc tái sử dụng) form cho các slot còn thiếu rồi lưu Form ID vào cài đặt.
     *
     * @return array<string, int> slot => form_id đã gắn trong lần chạy này.
     */
    public static function provision(bool $force = false): array {
        $attached = [];
        $recorded = get_option(self::OPTION_KEY, []);
        $recorded = is_array($recorded) ? $recorded : [];

        foreach (self::pending_slots($force) as $slot => $def) {
            try {
                $form_id = self::find_form_by_slot($slot) ?: self::insert_form($def);
            } catch (\Throwable $e) {
                do_action('drv/integrations/fluentform_provision_failed', $slot, $e->getMessage());
                continue;
            }
            if ($form_id > 0) {
                $attached[$slot]  = $form_id;
                $recorded[$slot]  = (int) $def['drv_version'];
            }
        }

        if ($attached !== []) {
            update_option(Settings::OPTION_KEY, Settings::sanitize(['forms' => $attached]));
            Settings::flush_cache();
        }
        update_option(self::OPTION_KEY, $recorded, false);

        return $attached;
    }

    /** @param array<string, mixed> $def */
    private static function insert_form(array $def): int {
        $now     = (string) current_time('mysql');
        $form_id = (int) wpFluent()->table('fluentform_forms')->insertGetId(self::buildFormRow($def, $now, (int) get_current_user_id()));
        if ($form_id <= 0) {
            return 0;
        }
        foreach (self::buildMetaRows($def) as $meta) {
            wpFluent()->table('fluentform_form_meta')->insert(['form_id' => $form_id] + $meta);
        }
        do_action('fluentform/form_imported', $form_id);
        return $form_id;
    }

    private static function find_form_by_slot(string $slot): int {
        $row = wpFluent()->table('fluentform_form_meta')->where('meta_key', self::META_SLOT)->where('value', $slot)->orderBy('id', 'DESC')->first();
        $id  = is_object($row) ? (int) ($row->form_id ?? 0) : 0;
        return $id > 0 && self::form_exists($id) ? $id : 0;
    }

    private static function form_exists(int $form_id): bool {
        if ($form_id <= 0 || !function_exists('wpFluent')) {
            return false;
        }
        try {
            return is_object(wpFluent()->table('fluentform_forms')->find($form_id));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function tables_ready(): bool {
        global $wpdb;
        if (!drv_is_fluent_forms_active() || !function_exists('wpFluent') || !isset($wpdb) || !is_object($wpdb)) {
            return false;
        }
        $table = $wpdb->prefix . 'fluentform_forms';
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
