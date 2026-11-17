<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Dravit\Woo\CheckoutValidator;
use Dravit\Woo\Wards;
use PHPUnit\Framework\TestCase;

/**
 * Step 9B · docs/04 §5.1 & §5.3, docs/09 C-2, C-3, C-12: SĐT VN, email tùy chọn, Tỉnh → Phường/Xã, khối VAT.
 */
final class CheckoutValidatorTest extends TestCase {
    private const VALID = [
        'billing_first_name' => 'Nguyễn Văn An',
        'billing_phone'      => '0909 385 880',
        'billing_email'      => '',
        'billing_state'      => 'HCM',
        'billing_city'       => 'Phường Sài Gòn',
        'drv_ward_code'      => '26740',
        'billing_address_1'  => 'Số 12 đường B2, KĐT Sala',
    ];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        // Không stub `is_email`: CheckoutValidator tự fallback FILTER_VALIDATE_EMAIL khi chạy ngoài WordPress
        // (stub Brain Monkey sẽ định nghĩa hàm toàn cục và làm hỏng các test chạy sau).
        Wards::seedForTests(
            ['HCM' => 'TP. Hồ Chí Minh', 'HN' => 'TP. Hà Nội'],
            [
                'HCM' => [
                    ['code' => '26740', 'name' => 'Phường Sài Gòn', 'type' => 'phuong'],
                    ['code' => '26734', 'name' => 'Phường Tân Định', 'type' => 'phuong'],
                ],
                'HN'  => [
                    ['code' => '00004', 'name' => 'Phường Ba Đình', 'type' => 'phuong'],
                ],
            ]
        );
    }

    protected function tearDown(): void {
        Wards::resetForTests();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_valid_order_without_email_passes(): void {
        self::assertSame([], CheckoutValidator::validate(self::VALID), 'C-3: không nhập email vẫn đặt hàng được.');
    }

    /**
     * @dataProvider phoneProvider
     */
    public function test_phone_rules(string $raw, bool $expected): void {
        self::assertSame($expected, CheckoutValidator::isValidPhone($raw));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function phoneProvider(): array {
        return [
            'mobile 09'         => ['0909385880', true],
            'spaces and dots'   => ['0909.385.880', true],
            'intl +84'          => ['+84 909 385 880', true],
            'intl 84'           => ['84909385880', true],
            'prefix 03'         => ['0362123456', true],
            'too short'         => ['090938588', false],
            'too long'          => ['09093858801', false],
            'landline 02'       => ['0281234567', false],
            'letters'           => ['09abc85880', false],
        ];
    }

    public function test_normalize_phone_returns_local_format(): void {
        self::assertSame('0909385880', CheckoutValidator::normalizePhone('+84 909-385-880'));
    }

    public function test_invalid_phone_reports_inline_message(): void {
        $errors = CheckoutValidator::validate(array_merge(self::VALID, ['billing_phone' => '12345']));
        self::assertArrayHasKey('billing_phone', $errors);
        self::assertStringContainsString('10 số', $errors['billing_phone']);
    }

    public function test_ward_must_belong_to_selected_province(): void {
        $errors = CheckoutValidator::validate(array_merge(self::VALID, [
            'billing_state' => 'HN',
            'billing_city'  => 'Phường Sài Gòn',
            'drv_ward_code' => '26740',
        ]));
        self::assertArrayHasKey('billing_city', $errors);

        $missing = CheckoutValidator::validate(array_merge(self::VALID, ['billing_city' => '', 'drv_ward_code' => '']));
        self::assertArrayHasKey('billing_city', $missing);

        $bad_state = CheckoutValidator::validate(array_merge(self::VALID, ['billing_state' => 'XX']));
        self::assertArrayHasKey('billing_state', $bad_state);
    }

    public function test_ward_code_name_mismatch_is_rejected(): void {
        $errors = CheckoutValidator::validate(array_merge(self::VALID, ['drv_ward_code' => '26734']));
        self::assertArrayHasKey('billing_city', $errors);
    }

    public function test_vat_block_requires_company_tax_id_address_and_email(): void {
        $errors = CheckoutValidator::validate(array_merge(self::VALID, ['require_vat_invoice' => '1']));
        self::assertArrayHasKey('vat_company', $errors);
        self::assertArrayHasKey('vat_tax_id', $errors);
        self::assertArrayHasKey('vat_address', $errors);
        self::assertArrayHasKey('vat_email', $errors, 'Không có cả billing_email lẫn vat_email.');

        $ok = CheckoutValidator::validate(array_merge(self::VALID, [
            'require_vat_invoice' => '1',
            'vat_company'         => 'Công ty TNHH An Phát',
            'vat_tax_id'          => '0316889988-001',
            'vat_address'         => '120 Điện Biên Phủ, Phường Sài Gòn, TP. Hồ Chí Minh',
            'billing_email'       => 'ketoan@anphat.vn',
        ]));
        self::assertSame([], $ok);
    }

    /**
     * @dataProvider taxProvider
     */
    public function test_tax_id_regex(string $raw, bool $expected): void {
        self::assertSame($expected, CheckoutValidator::isValidTaxId($raw));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function taxProvider(): array {
        return [
            '10 digits'          => ['0318688999', true],
            'branch suffix'      => ['0318688999-001', true],
            '9 digits'           => ['031868899', false],
            '13 digits no dash'  => ['0318688999001', false],
            'branch 2 digits'    => ['0318688999-01', false],
            'letters'            => ['03186889AB', false],
        ];
    }

    public function test_vat_meta_uses_hpos_keys_and_falls_back_to_billing_email(): void {
        self::assertSame(['_drv_vat_required' => '0'], CheckoutValidator::vatMeta(self::VALID));

        $meta = CheckoutValidator::vatMeta(array_merge(self::VALID, [
            'require_vat_invoice' => '1',
            'vat_company'         => 'Công ty TNHH An Phát',
            'vat_tax_id'          => '0316889988',
            'vat_address'         => '120 Điện Biên Phủ',
            'billing_email'       => 'ketoan@anphat.vn',
        ]));
        self::assertSame('1', $meta['_drv_vat_required']);
        self::assertSame('0316889988', $meta['_drv_vat_tax_id']);
        self::assertSame('ketoan@anphat.vn', $meta['_drv_vat_email']);
    }
}
