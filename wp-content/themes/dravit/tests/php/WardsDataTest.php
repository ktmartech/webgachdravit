<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Woo\Wards;
use PHPUnit\Framework\TestCase;

/**
 * Step 9B · docs/04 §5.2, docs/15 §2.7, O-06: dữ liệu `assets/data/wards/*.json` khớp 34 khóa `config/provinces-vn.php`.
 */
final class WardsDataTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Wards::resetForTests();
    }

    public function test_every_province_has_ward_file_with_unique_codes(): void {
        $provinces = Wards::provinces();
        self::assertCount(34, $provinces);

        $total = 0;
        foreach (array_keys($provinces) as $key) {
            $wards = Wards::forProvince($key);
            self::assertNotEmpty($wards, "Thiếu dữ liệu phường/xã cho {$key}");
            $codes = array_column($wards, 'code');
            self::assertSame(count($codes), count(array_unique($codes)), "Trùng mã phường/xã ở {$key}");
            foreach ($wards as $w) {
                self::assertMatchesRegularExpression('/^\d{5}$/', $w['code']);
                self::assertContains($w['type'], ['phuong', 'xa', 'dac-khu']);
            }
            $total += count($wards);
        }
        self::assertSame(3321, $total, 'Tổng số đơn vị cấp xã sau sắp xếp 01/07/2025.');
    }

    public function test_lookup_by_code_and_name(): void {
        $first = Wards::forProvince('HCM')[0];
        self::assertSame($first, Wards::find('HCM', $first['code']));
        self::assertSame($first, Wards::findByName('HCM', $first['name']));
        self::assertNull(Wards::find('HN', $first['code']));
        self::assertSame([], Wards::forProvince('../etc'));
    }
}
