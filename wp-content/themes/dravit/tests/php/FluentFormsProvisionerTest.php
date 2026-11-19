<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Core\Integrations\FluentFormsBridge;
use Dravit\Core\Integrations\FluentFormsProvisioner;
use Dravit\Leads\LeadValidator;
use PHPUnit\Framework\TestCase;

/**
 * D-31: các mẫu JSON `config/fluent-forms/*.json` phải import được và khớp hợp đồng trường của FluentFormsBridge / LeadValidator.
 */
final class FluentFormsProvisionerTest extends TestCase {
    /** @return array<string, array<string, mixed>> */
    private function definitions(): array {
        $defs = FluentFormsProvisioner::load_definitions();
        self::assertNotSame([], $defs, 'Không đọc được mẫu form nào');
        return $defs;
    }

    /** @param array<string, mixed> $def @return array<string, array<string, mixed>> */
    private function fieldsByName(array $def): array {
        $out = [];
        foreach ($def['form']['fields'] as $field) {
            $out[(string) $field['attributes']['name']] = $field;
        }
        return $out;
    }

    public function test_every_slot_has_a_valid_definition_file(): void {
        $defs = $this->definitions();
        self::assertEqualsCanonicalizing(array_keys(FluentFormsProvisioner::slot_labels()), array_keys($defs));
        self::assertEqualsCanonicalizing(array_keys(FluentFormsProvisioner::slot_labels()), array_keys(array_diff_key(\Dravit\Core\Settings::defaults()['forms'], ['native_ui' => true])));

        foreach ($defs as $slot => $def) {
            self::assertSame('published', $def['status'], $slot);
            self::assertArrayHasKey('submitButton', $def['form'], $slot);
            $names = array_keys($this->fieldsByName($def));
            self::assertSame(count($names), count(array_unique($names)), "Trùng tên trường ở {$slot}");
            foreach ($def['form']['fields'] as $field) {
                self::assertArrayHasKey('element', $field);
                self::assertArrayHasKey('uniqElKey', $field);
            }
            $meta_keys = array_column(FluentFormsProvisioner::buildMetaRows($def), 'meta_key');
            self::assertContains('formSettings', $meta_keys, $slot);
            self::assertContains(FluentFormsProvisioner::META_SLOT, $meta_keys, $slot);
        }
    }

    public function test_submissions_from_each_form_map_to_a_valid_lead(): void {
        $sample = [
            'name' => 'Nguyễn Văn A', 'phone' => '0909 385 880', 'email' => 'a@b.vn', 'province' => 'HCM', 'role' => 'nha-thau',
            'area_m2' => '85', 'message' => 'Cần báo giá gạch 80x80', 'preferred_time' => 'Sáng thứ 7',
            'drv_sku' => 'DRV-8801M', 'drv_product_id' => '101', 'drv_product_name' => 'Marble', 'drv_post_id' => '55',
            'drv_page_url' => 'https://gachdravit.vn/x',
        ];
        foreach ($this->definitions() as $slot => $def) {
            if ($slot === 'boq_mirror') {
                continue;
            }
            $data = array_intersect_key($sample, $this->fieldsByName($def));
            $map  = FluentFormsBridge::mapSubmission($data, $slot);
            $res  = LeadValidator::validate($map['input'], $map['source'], array_keys((array) require DRV_DIR . '/config/provinces-vn.php'));
            self::assertSame([], $res['errors'], "Form {$slot} gửi lên không qua được LeadValidator");
        }
    }

    public function test_context_fields_exist_where_templates_inject_them(): void {
        $defs = $this->definitions();
        foreach (['quote_modal', 'pdp_consult'] as $slot) {
            $names = array_keys($this->fieldsByName($defs[$slot]));
            foreach (['drv_sku', 'drv_product_id', 'drv_product_name', 'drv_page_url'] as $key) {
                self::assertContains($key, $names, "{$slot} thiếu Hidden field {$key}");
            }
        }
        self::assertContains('drv_post_id', array_keys($this->fieldsByName($defs['blog_inline'])));
        self::assertContains('preferred_time', array_keys($this->fieldsByName($defs['showroom_booking'])));
        // BOQ mirror: tên trường phải trùng khóa `mirror_boq_entry` ghi vào Entries.
        foreach (['drv_name', 'drv_phone', 'drv_boq_summary', 'drv_quote_code', 'drv_lead_id'] as $key) {
            self::assertContains($key, array_keys($this->fieldsByName($defs['boq_mirror'])));
        }
    }

    public function test_parse_definition_rejects_broken_files(): void {
        self::assertNull(FluentFormsProvisioner::parseDefinition('not json'));
        self::assertNull(FluentFormsProvisioner::parseDefinition('[]'));
        self::assertNull(FluentFormsProvisioner::parseDefinition('[{"drv_slot":"khong_co","title":"X","form":{"fields":[]}}]'));
        self::assertNull(FluentFormsProvisioner::parseDefinition('[{"drv_slot":"quote_modal","title":"","form":{"fields":[]}}]'));
        self::assertNotNull(FluentFormsProvisioner::parseDefinition('[{"drv_slot":"quote_modal","title":"X","form":{"fields":[]}}]'));
    }

    public function test_form_row_matches_fluent_forms_import_shape(): void {
        $def = $this->definitions()['contact_page'];
        $row = FluentFormsProvisioner::buildFormRow($def, '2026-01-01 00:00:00', 1);
        self::assertSame(['title', 'status', 'form_fields', 'has_payment', 'type', 'created_by', 'created_at', 'updated_at'], array_keys($row));
        $fields = json_decode($row['form_fields'], true);
        self::assertIsArray($fields['fields']);
        self::assertArrayHasKey('submitButton', $fields);
        self::assertStringContainsString('Họ và tên', $row['form_fields'], 'Giữ nguyên tiếng Việt có dấu, không escape unicode');
    }

    public function test_provisioning_is_inert_without_fluent_forms(): void {
        self::assertFalse(FluentFormsProvisioner::tables_ready());
    }
}
