<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Advisor\SlotNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlotNormalizerTest extends TestCase {
    private SlotNormalizer $normalizer;

    protected function setUp(): void {
        parent::setUp();
        $this->normalizer = new SlotNormalizer();
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function naturalVietnameseQueriesProvider(): array {
        return [
            '01_nha_tam_nguoi_gia_duoi_400k' => [
                'Tìm gạch lát nhà tắm 6m2 cho người lớn tuổi dưới 400k',
                [
                    'space'          => 'nha-tam',
                    'area_m2'        => 6.0,
                    'budget_max_vnd' => 400000,
                    'special'        => ['nguoi-gia'],
                ],
            ],
            '02_wc_khong_dau_tre_nho' => [
                'gach lat toilet 4,5 m2 nha co em be',
                [
                    'space'   => 'nha-tam',
                    'area_m2' => 4.5,
                    'special' => ['tre-nho'],
                ],
            ],
            '03_phong_khach_800x800_mm_bong_kieng' => [
                'Lát phòng khách 32m2 gạch vân đá 800x800 bóng kiếng',
                [
                    'space'   => 'phong-khach',
                    'area_m2' => 32.0,
                    'size'    => '80x80',
                    'vein'    => 'van-da-marble',
                    'surface' => 'bong-kieng',
                ],
            ],
            '04_dao_chieu_1200x600_da_mo' => [
                'Gạch 1200x600 đá mờ màu ghi sáng lát sảnh 48m2',
                [
                    'space'   => 'sanh-thuong-mai',
                    'size'    => '60x120',
                    'surface' => 'mo',
                    'color'   => 'ghi-sang',
                    'area_m2' => 48.0,
                ],
            ],
            '05_gach_the_75x300_mm_bep' => [
                'Ốp bếp 9m2 gạch thẻ 75x300 màu xanh lá',
                [
                    'space'    => 'phong-bep',
                    'function' => 'gach-the',
                    'size'     => '7-5x30',
                    'color'    => 'xanh-la',
                    'area_m2'  => 9.0,
                ],
            ],
            '06_gach_the_7_5x30_cm' => [
                'Gạch thẻ 7,5x30 cm màu trắng ốp quầy pha chế',
                [
                    'function' => 'gach-the',
                    'size'     => '7-5x30',
                    'color'    => 'trang',
                ],
            ],
            '07_phan_biet_gach_bong_indochine_khong_nham_bong_kieng' => [
                'Gạch bông phong cách Đông Dương lát quán cafe 25m2',
                [
                    'function' => 'gach-bong',
                    'style'    => 'indochine',
                    'space'    => 'quan-cafe',
                    'area_m2'  => 25.0,
                    'surface'  => null,
                ],
            ],
            '08_phan_biet_gach_bong_dau_sac_la_bong_kieng' => [
                'Tìm gạch bóng lát phòng khách 30m2 màu kem',
                [
                    'space'    => 'phong-khach',
                    'surface'  => 'bong-kieng',
                    'function' => 'lat-nen',
                    'color'    => 'kem',
                    'area_m2'  => 30.0,
                ],
            ],
            '09_dien_tich_ngang_dai' => [
                'Phòng ngủ ngang 4m dài 5,5m lát gạch giả gỗ 15x90',
                [
                    'space'   => 'phong-ngu',
                    'area_m2' => 22.0,
                    'vein'    => 'gia-go',
                    'size'    => '15x90',
                ],
            ],
            '10_dien_tich_phep_nhan_3_5m_x_4m' => [
                ' Sân vườn 3,5m x 4m cần gạch nhám chống trượt R12',
                [
                    'space'   => 'san-vuon',
                    'area_m2' => 14.0,
                    'surface' => 'nham',
                    'dcof'    => 'r12',
                ],
            ],
            '11_ngan_sach_1_2_trieu_da_nung_ket' => [
                'Đá nung kết 120x240 ốp mặt tiền tầm 1,2 triệu/m2',
                [
                    'material'       => 'da-nung-ket',
                    'size'           => '120x240',
                    'space'          => 'mat-tien',
                    'budget_max_vnd' => 1200000,
                ],
            ],
            '12_ngan_sach_1tr5_viet_tat' => [
                'Tìm gạch khổ lớn dưới 1tr5 cho phòng khách',
                [
                    'space'          => 'phong-khach',
                    'size'           => '60x120',
                    'budget_max_vnd' => 1500000,
                ],
            ],
            '13_ngan_sach_385_000d' => [
                'Gạch lát nền 60x60 giá khoảng 385.000đ',
                [
                    'function'       => 'lat-nen',
                    'size'           => '60x60',
                    'budget_max_vnd' => 385000,
                ],
            ],
            '14_gara_o_to_7_cho' => [
                'Lát nền gara ô tô 28m2 gạch dày chịu tải',
                [
                    'space'   => 'gara-o-to',
                    'area_m2' => 28.0,
                ],
            ],
            '15_ho_boi_ngoai_troi' => [
                'Gạch mosaic ốp bể bơi 36m2 màu xanh dương',
                [
                    'space'   => 'ho-boi',
                    'vein'    => 'mosaic',
                    'color'   => 'xanh-duong',
                    'area_m2' => 36.0,
                ],
            ],
            '16_ban_cong_san_thuong' => [
                'Gạch lát sân thượng phơi đồ 20m2 chống trơn trượt',
                [
                    'space'   => 'ngoai-troi',
                    'area_m2' => 20.0,
                    'special' => ['am-uot'],
                ],
            ],
            '17_terrazzo_wabi_sabi' => [
                'Gạch đá mài terrazzo phong cách Wabi Sabi màu be 18m2',
                [
                    'vein'    => 'terrazzo',
                    'style'   => 'wabi-sabi',
                    'color'   => 'be',
                    'area_m2' => 18.0,
                ],
            ],
            '18_xi_mang_industrial_xam_dam' => [
                'Gạch vân bê tông phong cách công nghiệp màu xám đậm 60x60',
                [
                    'vein'  => 'van-xi-mang',
                    'style' => 'cong-nghiep',
                    'color' => 'xam-dam',
                    'size'  => '60x60',
                ],
            ],
            '19_porcelain_xuong_da' => [
                'Gạch xương đá porcelain 80x80 bán bóng lappato',
                [
                    'material' => 'porcelain',
                    'size'     => '80x80',
                    'surface'  => 'ban-bong',
                ],
            ],
            '20_ceramic_men_bong' => [
                'Gạch men ceramic 30x60 ốp tường phòng tắm 15m2',
                [
                    'material' => 'ceramic',
                    'size'     => '30x60',
                    'function' => 'op-tuong',
                    'space'    => 'nha-tam',
                    'area_m2'  => 15.0,
                ],
            ],
            '21_gach_bong_gio_chan_nang' => [
                'Gạch bông gió chắn nắng mặt tiền hướng Tây 12m2',
                [
                    'function' => 'bong-gio',
                    'area_m2'  => 12.0,
                ],
            ],
            '22_gach_trang_tri_3d_sugar' => [
                'Gạch trang trí bề mặt hiệu ứng sugar màu vàng',
                [
                    'function' => 'trang-tri',
                    'surface'  => 'sugar',
                    'color'    => 'vang',
                ],
            ],
            '23_sku_truc_tiep_drv_8801m' => [
                'Cho mình xem mẫu drv-8801m cho diện tích 42m2',
                [
                    'sku_query' => 'DRV-8801M',
                    'area_m2'   => 42.0,
                ],
            ],
            '24_co_mau_tai_showroom' => [
                'Tìm mẫu vân gỗ nâu có mẫu tại showroom cho 26m2',
                [
                    'vein'          => 'gia-go',
                    'color'         => 'nau',
                    'area_m2'       => 26.0,
                    'showroom_only' => true,
                ],
            ],
            '25_phong_cach_scandinavian_bac_au' => [
                'Gạch vân vải Bắc Âu màu trắng cho phòng ngủ 14m2',
                [
                    'vein'    => 'van-vai',
                    'style'   => 'bac-au',
                    'color'   => 'trang',
                    'space'   => 'phong-ngu',
                    'area_m2' => 14.0,
                ],
            ],
            '26_phong_cach_tan_co_dien_luxury' => [
                'Gạch vân đá cẩm thạch tân cổ điển khổ 100x100',
                [
                    'vein'  => 'van-da-marble',
                    'style' => 'tan-co-dien',
                    'size'  => '100x100',
                ],
            ],
            '27_phong_cach_toi_gian_minimalist' => [
                'Gạch đơn sắc tối giản màu đen 600x600mm',
                [
                    'vein'  => 'don-sac',
                    'style' => 'toi-gian',
                    'color' => 'den',
                    'size'  => '60x60',
                ],
            ],
            '28_khu_tam_dung_doc_thoat_nuoc' => [
                'Sàn tắm đứng dốc thoát nước 3,2 mét vuông cần R11',
                [
                    'area_m2' => 3.2,
                    'dcof'    => 'r11',
                    'special' => ['am-uot', 'doc-nuoc'],
                ],
            ],
            '29_mau_hong_pastel_mosaic' => [
                'Gạch mosaic màu hồng ốp vách lavabo 4m2',
                [
                    'vein'     => 'mosaic',
                    'function' => 'op-tuong',
                    'color'    => 'hong',
                    'area_m2'  => 4.0,
                ],
            ],
            '30_gach_da_sac_nghe_thuat' => [
                'Gạch trang trí đa sắc hiện đại 60x120',
                [
                    'function' => 'trang-tri',
                    'color'    => 'da-sac',
                    'style'    => 'hien-dai',
                    'size'     => '60x120',
                ],
            ],
            '31_khong_dau_san_vuon_400x400' => [
                'gach san vuon 400x400 nham chong truot 55m2 duoi 350k',
                [
                    'space'          => 'san-vuon',
                    'size'           => '40x40',
                    'surface'        => 'nham',
                    'area_m2'        => 55.0,
                    'budget_max_vnd' => 350000,
                ],
            ],
            '32_tam_lon_160x320_da_nung_ket' => [
                'Đá nung kết 1600x3200 ốp vách thông tầng 24m2',
                [
                    'material' => 'da-nung-ket',
                    'size'     => '160x320',
                    'area_m2'  => 24.0,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expectedSubset
     */
    #[DataProvider('naturalVietnameseQueriesProvider')]
    public function test_extracts_canonical_slots_from_32_natural_vietnamese_queries(string $query, array $expectedSubset): void {
        $actual = $this->normalizer->extractFromText($query);

        foreach ($expectedSubset as $key => $expectedValue) {
            if ($key === 'special' && is_array($expectedValue)) {
                foreach ($expectedValue as $expectedFlag) {
                    self::assertContains(
                        $expectedFlag,
                        $actual['special'],
                        sprintf('Query "%s" phải nhận diện được cờ an toàn "%s".', $query, $expectedFlag)
                    );
                }
                continue;
            }

            self::assertSame(
                $expectedValue,
                $actual[$key] ?? null,
                sprintf('Slot "%s" không khớp cho câu hỏi: "%s"', $key, $query)
            );
        }
    }

    public function test_sanitize_slots_blocks_unknown_or_hallucinated_taxonomy_slugs(): void {
        $dirty = [
            'space'          => 'phong-karaoke-vip',
            'size'           => '999x999',
            'surface'        => 'sieu-nham-xyz',
            'dcof'           => 'r99',
            'area_m2'        => -15,
            'budget_max_vnd' => 100,
            'special'        => ['nguoi-gia', 'bay-tren-khong'],
            'sku_query'      => 'drv-8801m<script>',
        ];

        $clean = $this->normalizer->sanitizeSlots($dirty);

        self::assertNull($clean['space']);
        self::assertNull($clean['size']);
        self::assertNull($clean['surface']);
        self::assertNull($clean['dcof']);
        self::assertNull($clean['area_m2']);
        self::assertNull($clean['budget_max_vnd']);
        self::assertSame(['nguoi-gia'], $clean['special']);
        self::assertSame('DRV-8801MSCRIPT', $clean['sku_query']);
    }

    public function test_merge_slots_preserves_multi_turn_context_and_resets_area_when_switching_space(): void {
        $turn1 = $this->normalizer->extractFromText('Gạch lát nhà tắm 6m2 R11 màu xám');
        $turn2 = $this->normalizer->extractFromText('Đổi sang màu kem dưới 380k');

        $mergedSameSpace = $this->normalizer->mergeSlots($turn1, $turn2);
        self::assertSame('nha-tam', $mergedSameSpace['space']);
        self::assertSame(6.0, $mergedSameSpace['area_m2']);
        self::assertSame('r11', $mergedSameSpace['dcof']);
        self::assertSame('kem', $mergedSameSpace['color']);
        self::assertSame(380000, $mergedSameSpace['budget_max_vnd']);

        // Khi khách chuyển hẳn sang phòng khách mà chưa nói lại diện tích -> xóa diện tích 6m² và R11 cũ của nhà tắm
        $turn3               = $this->normalizer->extractFromText('Giờ tìm gạch vân đá cho phòng khách');
        $mergedSwitchedSpace = $this->normalizer->mergeSlots($mergedSameSpace, $turn3);

        self::assertSame('phong-khach', $mergedSwitchedSpace['space']);
        self::assertNull($mergedSwitchedSpace['area_m2']);
        self::assertNull($mergedSwitchedSpace['dcof']);
        self::assertSame('van-da-marble', $mergedSwitchedSpace['vein']);
    }
}
