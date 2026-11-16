<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Core\Integrations\FluentFormsBridge;
use Dravit\Leads\LeadValidator;
use PHPUnit\Framework\TestCase;

/**
 * Step 8 · D-31: Dual-Mode Fluent Forms – bơm ngữ cảnh gạch vào Hidden field và ánh xạ entry về hợp đồng `drv_lead`.
 */
final class FluentFormsBridgeTest extends TestCase {
    public function test_maps_flexible_field_names_to_lead_contract(): void {
        $mapped = FluentFormsBridge::mapSubmission([
            'names'           => ['first_name' => 'Nguyễn', 'last_name' => 'Thanh Tùng'],
            'phone'           => '0909 385 880',
            'email'           => 'tung@studio.vn',
            'province'        => 'hcm',
            'message'         => 'Báo giá giúp tôi',
            'drv_sku'         => 'DRV-8801M',
            'drv_product_name'=> 'Marble Calacatta',
            'drv_product_id'  => '101',
            'drv_area_m2'     => '32.5',
            'drv_page_url'    => 'https://gachdravit.vn/san-pham/abc',
            'utm_source'      => 'zalo',
            'utm_medium'      => 'cpc',
        ], 'pdp_consult');

        self::assertSame('pdp', $mapped['source']);
        self::assertSame('Nguyễn Thanh Tùng', $mapped['input']['name']);
        self::assertSame('HCM', $mapped['input']['province']);
        self::assertSame(101, $mapped['input']['product_id']);
        self::assertSame('32.5', $mapped['input']['area_m2']);
        self::assertStringContainsString('DRV-8801M', $mapped['input']['message']);
        self::assertTrue($mapped['input']['consent']);
        self::assertSame('https://gachdravit.vn/san-pham/abc', $mapped['page_url']);
        self::assertSame(['utm_source' => 'zalo', 'utm_medium' => 'cpc'], $mapped['utm']);

        // Bản ghi đã ánh xạ phải qua được validator chuẩn của drv_lead.
        $checked = LeadValidator::validate($mapped['input'], $mapped['source'], ['HCM']);
        self::assertSame([], $checked['errors']);
        self::assertSame('0909385880', $checked['data']['phone']);
        self::assertSame(32.5, $checked['data']['area_m2']);
    }

    public function test_slot_to_source_mapping_and_boq_summary_fallback(): void {
        self::assertSame('contact', FluentFormsBridge::mapSubmission(['name' => 'A B'], 'contact_page')['source']);
        self::assertSame('showroom_visit', FluentFormsBridge::mapSubmission([], 'showroom_booking')['source']);
        self::assertSame('blog_inline', FluentFormsBridge::mapSubmission([], 'blog_inline')['source']);

        $withSummary = FluentFormsBridge::mapSubmission(['drv_boq_summary' => 'Phòng khách · DRV-8801M: 23 thùng'], 'quote_modal');
        self::assertStringContainsString('23 thùng', $withSummary['input']['message']);
        self::assertSame('Gửi từ Fluent Forms', FluentFormsBridge::mapSubmission([], 'quote_modal')['input']['message']);
    }

    public function test_context_whitelist_and_hidden_field_injection(): void {
        $ctx = FluentFormsBridge::sanitizeContext([
            'drv_sku'        => 'DRV-8801M',
            'drv_area_m2'    => 32.5,
            'drv_page_url'   => 'https://gachdravit.vn/x',
            'evil_key'       => 'drop me',
            'drv_product_name' => '<script>x</script>Calacatta',
        ]);
        self::assertSame(['drv_sku', 'drv_product_name', 'drv_area_m2', 'drv_page_url'], array_keys($ctx));
        self::assertStringNotContainsString('<script>', $ctx['drv_product_name']);

        $bridge = new FluentFormsBridge();
        $field  = ['element' => 'input_hidden', 'attributes' => ['name' => 'drv_sku', 'value' => '']];
        // Ngoài ngữ cảnh render (không có context) -> giữ nguyên giá trị.
        self::assertSame('', $bridge->inject_hidden_value($field, null)['attributes']['value']);
    }

    public function test_unconfigured_form_ids_are_ignored(): void {
        self::assertSame('', FluentFormsBridge::slotForFormId(0));
        self::assertSame('', FluentFormsBridge::slotForFormId(987654), 'Form không gắn vào slot nào thì không đồng bộ về drv_lead');
        self::assertSame(0, FluentFormsBridge::slot_form_id('quote_modal'));
    }

    public function test_boq_summary_text_for_mirror_entries(): void {
        $text = FluentFormsBridge::boqSummary(
            [['name' => 'Phòng khách', 'slots' => [['sku' => 'DRV-8801M', 'box_text' => '23 thùng (69 viên)', 'actual_m2' => 44.16]]]],
            ['total_m2' => 44.16, 'total_boxes' => 23, 'total_kg' => 966.0]
        );
        self::assertStringContainsString('Phòng khách · DRV-8801M: 23 thùng (69 viên) (44,16 m²)', $text);
        self::assertStringContainsString('Tổng: 44,16 m² · 23 thùng', $text);
        self::assertStringNotContainsString('—', $text, 'Không dùng em-dash (Q-12)');
    }
}
