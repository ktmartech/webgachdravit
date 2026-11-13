<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Advisor\Contracts\CatalogSearchInterface;
use Dravit\Advisor\QueryEngine;
use Dravit\Advisor\SlotNormalizer;
use PHPUnit\Framework\TestCase;

final class SafetyRuleEngineTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'home_url'          => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' ? '/' . ltrim($path, '/') : ''),
            'untrailingslashit' => static fn (string $v): string => rtrim($v, '/\\'),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Tập 10 SKU chuẩn từ `ProductSeeder` + 3 biến thể cùng series / quote_only để kiểm định luật an toàn cứng & khử trùng lặp.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildFixtureCatalog(): array {
        return [
            [
                'id'                 => 101,
                'sku'                => 'DRV-8801M',
                'title'              => 'Gạch Vân Đá Marble Calacatta Gold 80x80 Bóng Kiếng',
                'url'                => 'https://gachdravit.vn/san-pham/drv-8801m',
                'series'             => 'CALACATTA-ROYAL',
                'showroom_shelf'     => 'A1-01',
                'has_showroom'       => true,
                'price_state'        => 'sale',
                'price_m2'           => 420000,
                'sale_price_m2'      => 365000,
                'effective_price_m2' => 365000,
                'w_mm'               => 800.0,
                'h_mm'               => 800.0,
                'thickness_mm'       => 9.8,
                'pieces_per_box'     => 3,
                'area_piece'         => 0.64,
                'area_box'           => 1.92,
                'kg_per_box'         => 44.0,
                'round_mode'         => 'box',
                'qty_min'            => 3,
                'water_absorption'   => 0.12,
                'dcof_slug'          => 'r9',
                'dcof_level'         => 9,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 180,
                'collections'        => ['gach', 'gach-lat-nen', 'gach-phong-khach', 'gach-van-da', 'gach-80x80'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['phong-khach', 'sanh-thuong-mai'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['80x80'],
                    'pa_mau-sac'    => ['trang', 'vang'],
                    'pa_be-mat'     => ['bong-kieng'],
                    'pa_phong-cach' => ['luxury', 'hien-dai'],
                    'pa_dcof'       => ['r9'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 102,
                'sku'                => 'DRV-3605S',
                'title'              => 'Gạch Ốp Lát Nhà Tắm Vân Đá Xám Nhạt 30x60 Nhám R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-3605s',
                'series'             => 'NORDIC-SLATE',
                'showroom_shelf'     => 'B2-04',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 295000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 295000,
                'w_mm'               => 300.0,
                'h_mm'               => 600.0,
                'thickness_mm'       => 9.0,
                'pieces_per_box'     => 8,
                'area_piece'         => 0.18,
                'area_box'           => 1.44,
                'kg_per_box'         => 28.0,
                'round_mode'         => 'box',
                'qty_min'            => 8,
                'water_absorption'   => 0.30,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 320,
                'collections'        => ['gach', 'gach-nha-tam', 'gach-30x60'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['nha-tam', 'phong-bep'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['30x60'],
                    'pa_mau-sac'    => ['ghi-sang', 'xam'],
                    'pa_be-mat'     => ['nham'],
                    'pa_phong-cach' => ['hien-dai', 'toi-gian'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 103,
                'sku'                => 'DRV-61209',
                'title'              => 'Gạch Vân Xi Măng Đá Mờ 60x120 Hiện Đại R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-61209',
                'series'             => 'URBAN-CONCRETE',
                'showroom_shelf'     => 'B1-08',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 460000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 460000,
                'w_mm'               => 600.0,
                'h_mm'               => 1200.0,
                'thickness_mm'       => 10.0,
                'pieces_per_box'     => 2,
                'area_piece'         => 0.72,
                'area_box'           => 1.44,
                'kg_per_box'         => 34.0,
                'round_mode'         => 'box',
                'qty_min'            => 2,
                'water_absorption'   => 0.15,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 96,
                'collections'        => ['gach', 'gach-60x120', 'gach-van-xi-mang'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['phong-khach', 'nha-tam', 'phong-bep', 'thuong-mai'],
                    'pa_van-gach'   => ['van-xi-mang'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['60x120'],
                    'pa_mau-sac'    => ['xam'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['industrial', 'hien-dai'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 104,
                'sku'                => 'DRV-SLAB01',
                'title'              => 'Đá Nung Kết Khổ Lớn Statuario 120x240 Bóng Kiếng',
                'url'                => 'https://gachdravit.vn/san-pham/drv-slab01',
                'series'             => 'STATUARIO-SLAB',
                'showroom_shelf'     => 'S1-01',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 1250000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 1250000,
                'w_mm'               => 1200.0,
                'h_mm'               => 2400.0,
                'thickness_mm'       => 9.0,
                'pieces_per_box'     => 1,
                'area_piece'         => 2.88,
                'area_box'           => 2.88,
                'kg_per_box'         => 62.0,
                'round_mode'         => 'piece',
                'qty_min'            => 1,
                'water_absorption'   => 0.05,
                'dcof_slug'          => 'r9',
                'dcof_level'         => 9,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 24,
                'collections'        => ['gach', 'da-nung-ket'],
                'attrs'              => [
                    'pa_cong-nang'  => ['op-tuong', 'lat-nen'],
                    'pa_khong-gian' => ['phong-khach', 'sanh-thuong-mai'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['da-nung-ket'],
                    'pa_kich-thuoc' => ['120x240'],
                    'pa_mau-sac'    => ['trang'],
                    'pa_be-mat'     => ['bong-kieng'],
                    'pa_phong-cach' => ['luxury'],
                    'pa_dcof'       => ['r9'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 105,
                'sku'                => 'DRV-W1590',
                'title'              => 'Gạch Giả Gỗ Sồi Tự Nhiên 15x90 Mờ R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-w1590',
                'series'             => 'OAK-WOOD',
                'showroom_shelf'     => 'C1-03',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 335000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 335000,
                'w_mm'               => 150.0,
                'h_mm'               => 900.0,
                'thickness_mm'       => 9.5,
                'pieces_per_box'     => 8,
                'area_piece'         => 0.135,
                'area_box'           => 1.08,
                'kg_per_box'         => 23.0,
                'round_mode'         => 'box',
                'qty_min'            => 8,
                'water_absorption'   => 0.20,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 240,
                'collections'        => ['gach', 'gach-gia-go', 'gach-phong-ngu'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['phong-ngu', 'phong-khach'],
                    'pa_van-gach'   => ['gia-go'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['15x90'],
                    'pa_mau-sac'    => ['nau', 'vang'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['scandinavian', 'wabi-sabi'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 106,
                'sku'                => 'DRV-TRZ60',
                'title'              => 'Gạch Terrazzo Hạt Đá Kem 60x60 Mờ R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-trz60',
                'series'             => 'TERRAZZO-CREAM',
                'showroom_shelf'     => 'C2-05',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 375000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 375000,
                'w_mm'               => 600.0,
                'h_mm'               => 600.0,
                'thickness_mm'       => 9.5,
                'pieces_per_box'     => 4,
                'area_piece'         => 0.36,
                'area_box'           => 1.44,
                'kg_per_box'         => 31.0,
                'round_mode'         => 'box',
                'qty_min'            => 4,
                'water_absorption'   => 0.18,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 160,
                'collections'        => ['gach', 'gach-terrazzo', 'gach-60x60'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen', 'gach-bong'],
                    'pa_khong-gian' => ['phong-khach', 'phong-bep', 'nha-tam', 'thuong-mai'],
                    'pa_van-gach'   => ['terrazzo'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['60x60'],
                    'pa_mau-sac'    => ['kem', 'be'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['indochine', 'wabi-sabi'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 107,
                'sku'                => 'DRV-SV40',
                'title'              => 'Gạch Sân Vườn Đá Chẻ Xám Đậm 40x40 Dày 15mm R12',
                'url'                => 'https://gachdravit.vn/san-pham/drv-sv40',
                'series'             => 'BASALT-OUTDOOR',
                'showroom_shelf'     => 'D1-02',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 320000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 320000,
                'w_mm'               => 400.0,
                'h_mm'               => 400.0,
                'thickness_mm'       => 15.0,
                'pieces_per_box'     => 6,
                'area_piece'         => 0.16,
                'area_box'           => 0.96,
                'kg_per_box'         => 33.0,
                'round_mode'         => 'box',
                'qty_min'            => 6,
                'water_absorption'   => 0.08,
                'dcof_slug'          => 'r12',
                'dcof_level'         => 12,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 300,
                'collections'        => ['gach', 'gach-san-vuon', 'gach-ngoai-troi', 'gach-ho-boi'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['san-vuon', 'ngoai-troi', 'gara-o-to', 'ho-boi'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['40x40'],
                    'pa_mau-sac'    => ['xam-dam'],
                    'pa_be-mat'     => ['nham', 'dinh-hinh'],
                    'pa_phong-cach' => ['hien-dai'],
                    'pa_dcof'       => ['r12'],
                    'pa_do-day'     => ['day'],
                ],
            ],
            [
                'id'                 => 108,
                'sku'                => 'DRV-TH7530',
                'title'              => 'Gạch Thẻ Men Bóng Xanh Ngọc 7.5x30 Ốp Tường',
                'url'                => 'https://gachdravit.vn/san-pham/drv-th7530',
                'series'             => 'ARTISAN-SUBWAY',
                'showroom_shelf'     => 'E1-04',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 490000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 490000,
                'w_mm'               => 75.0,
                'h_mm'               => 300.0,
                'thickness_mm'       => 8.5,
                'pieces_per_box'     => 44,
                'area_piece'         => 0.0225,
                'area_box'           => 0.99,
                'kg_per_box'         => 16.5,
                'round_mode'         => 'box',
                'qty_min'            => 44,
                'water_absorption'   => 6.5,
                'dcof_slug'          => '',
                'dcof_level'         => 0,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 440,
                'collections'        => ['gach', 'gach-the', 'gach-op-tuong'],
                'attrs'              => [
                    'pa_cong-nang'  => ['op-tuong', 'gach-the'],
                    'pa_khong-gian' => ['phong-bep', 'nha-tam'],
                    'pa_van-gach'   => ['don-sac'],
                    'pa_chat-lieu'  => ['ceramic'],
                    'pa_kich-thuoc' => ['7-5x30'],
                    'pa_mau-sac'    => ['xanh-la', 'trang'],
                    'pa_be-mat'     => ['bong-kieng'],
                    'pa_phong-cach' => ['indochine'],
                    'pa_dcof'       => [],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 109,
                'sku'                => 'DRV-3612T',
                'title'              => 'Gạch Travertine Kem Dự Án 60x120 Mờ R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-3612t',
                'series'             => 'ROMA-TRAVERTINE',
                'showroom_shelf'     => 'S2-02',
                'has_showroom'       => true,
                'price_state'        => 'quote_only',
                'price_m2'           => null,
                'sale_price_m2'      => null,
                'effective_price_m2' => null,
                'w_mm'               => 600.0,
                'h_mm'               => 1200.0,
                'thickness_mm'       => 10.5,
                'pieces_per_box'     => 2,
                'area_piece'         => 0.72,
                'area_box'           => 1.44,
                'kg_per_box'         => 35.0,
                'round_mode'         => 'box',
                'qty_min'            => 2,
                'water_absorption'   => 0.10,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => null,
                'collections'        => ['gach', 'gach-60x120', 'gach-nha-tam'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['nha-tam', 'phong-khach'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['60x120'],
                    'pa_mau-sac'    => ['kem'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['luxury'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 110,
                'sku'                => 'DRV-SLAB02',
                'title'              => 'Đá Nung Kết Armani Dark 120x240 Báo Giá Dự Án',
                'url'                => 'https://gachdravit.vn/san-pham/drv-slab02',
                'series'             => 'ARMANI-SLAB',
                'showroom_shelf'     => 'S1-05',
                'has_showroom'       => true,
                'price_state'        => 'quote_only',
                'price_m2'           => null,
                'sale_price_m2'      => null,
                'effective_price_m2' => null,
                'w_mm'               => 1200.0,
                'h_mm'               => 2400.0,
                'thickness_mm'       => 12.0,
                'pieces_per_box'     => 1,
                'area_piece'         => 2.88,
                'area_box'           => 2.88,
                'kg_per_box'         => 78.0,
                'round_mode'         => 'piece',
                'qty_min'            => 1,
                'water_absorption'   => 0.04,
                'dcof_slug'          => '',
                'dcof_level'         => 0,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => null,
                'collections'        => ['gach', 'da-nung-ket'],
                'attrs'              => [
                    'pa_cong-nang'  => ['op-tuong'],
                    'pa_khong-gian' => ['phong-khach', 'sanh-thuong-mai'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['da-nung-ket'],
                    'pa_kich-thuoc' => ['120x240'],
                    'pa_mau-sac'    => ['nau', 'den'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['luxury'],
                    'pa_dcof'       => [],
                    'pa_do-day'     => ['day'],
                ],
            ],
            // Ứng viên mỏng R11 (9mm) để kiểm định Gara ô tô BẮT BUỘC loại gạch mỏng < 12mm
            [
                'id'                 => 111,
                'sku'                => 'DRV-OUT60-THIN',
                'title'              => 'Gạch Ban Công Nhám 60x60 Mỏng 9mm R11',
                'url'                => 'https://gachdravit.vn/san-pham/drv-out60-thin',
                'series'             => 'BALCONY-LITE',
                'showroom_shelf'     => 'D2-01',
                'has_showroom'       => false,
                'price_state'        => 'priced',
                'price_m2'           => 310000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 310000,
                'w_mm'               => 600.0,
                'h_mm'               => 600.0,
                'thickness_mm'       => 9.0,
                'pieces_per_box'     => 4,
                'area_piece'         => 0.36,
                'area_box'           => 1.44,
                'kg_per_box'         => 29.0,
                'round_mode'         => 'box',
                'qty_min'            => 4,
                'water_absorption'   => 0.20,
                'dcof_slug'          => 'r11',
                'dcof_level'         => 11,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 120,
                'collections'        => ['gach', 'gach-ngoai-troi'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['ngoai-troi', 'san-vuon'],
                    'pa_van-gach'   => ['van-xi-mang'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['60x60'],
                    'pa_mau-sac'    => ['xam'],
                    'pa_be-mat'     => ['nham'],
                    'pa_phong-cach' => ['hien-dai'],
                    'pa_dcof'       => ['r11'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            // Biến thể trùng `series` với DRV-3605S để kiểm định deduplicateBySeries()
            [
                'id'                 => 112,
                'sku'                => 'DRV-3605S-DARK',
                'title'              => 'Gạch Nhà Tắm Vân Đá Xám Đậm 30x60 Nhám R10 (Cùng Series)',
                'url'                => 'https://gachdravit.vn/san-pham/drv-3605s-dark',
                'series'             => 'NORDIC-SLATE',
                'showroom_shelf'     => 'B2-05',
                'has_showroom'       => true,
                'price_state'        => 'priced',
                'price_m2'           => 295000,
                'sale_price_m2'      => null,
                'effective_price_m2' => 295000,
                'w_mm'               => 300.0,
                'h_mm'               => 600.0,
                'thickness_mm'       => 9.0,
                'pieces_per_box'     => 8,
                'area_piece'         => 0.18,
                'area_box'           => 1.44,
                'kg_per_box'         => 28.0,
                'round_mode'         => 'box',
                'qty_min'            => 8,
                'water_absorption'   => 0.30,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => 200,
                'collections'        => ['gach', 'gach-nha-tam'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['nha-tam'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['30x60'],
                    'pa_mau-sac'    => ['xam-dam'],
                    'pa_be-mat'     => ['nham'],
                    'pa_phong-cach' => ['hien-dai'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            // Thêm 2 mẫu quote_only R10 để kiểm định trần tối đa 2/6 mẫu quote_only
            [
                'id'                 => 113,
                'sku'                => 'DRV-QT-R10A',
                'title'              => 'Gạch Dự Án Limestone 60x120 Mờ R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-qt-r10a',
                'series'             => 'LIME-PROJ-A',
                'showroom_shelf'     => '',
                'has_showroom'       => false,
                'price_state'        => 'quote_only',
                'price_m2'           => null,
                'sale_price_m2'      => null,
                'effective_price_m2' => null,
                'w_mm'               => 600.0,
                'h_mm'               => 1200.0,
                'thickness_mm'       => 10.0,
                'pieces_per_box'     => 2,
                'area_piece'         => 0.72,
                'area_box'           => 1.44,
                'kg_per_box'         => 34.0,
                'round_mode'         => 'box',
                'qty_min'            => 2,
                'water_absorption'   => 0.12,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => null,
                'collections'        => ['gach', 'gach-nha-tam'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['nha-tam'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['60x120'],
                    'pa_mau-sac'    => ['kem'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['luxury'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
            [
                'id'                 => 114,
                'sku'                => 'DRV-QT-R10B',
                'title'              => 'Gạch Dự Án Onyx Matt 60x120 R10',
                'url'                => 'https://gachdravit.vn/san-pham/drv-qt-r10b',
                'series'             => 'ONYX-PROJ-B',
                'showroom_shelf'     => '',
                'has_showroom'       => false,
                'price_state'        => 'quote_only',
                'price_m2'           => null,
                'sale_price_m2'      => null,
                'effective_price_m2' => null,
                'w_mm'               => 600.0,
                'h_mm'               => 1200.0,
                'thickness_mm'       => 10.0,
                'pieces_per_box'     => 2,
                'area_piece'         => 0.72,
                'area_box'           => 1.44,
                'kg_per_box'         => 34.0,
                'round_mode'         => 'box',
                'qty_min'            => 2,
                'water_absorption'   => 0.12,
                'dcof_slug'          => 'r10',
                'dcof_level'         => 10,
                'stock_status'       => 'instock',
                'stock_qty_pieces'   => null,
                'collections'        => ['gach', 'gach-nha-tam'],
                'attrs'              => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['nha-tam'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_kich-thuoc' => ['60x120'],
                    'pa_mau-sac'    => ['kem'],
                    'pa_be-mat'     => ['mo'],
                    'pa_phong-cach' => ['luxury'],
                    'pa_dcof'       => ['r10'],
                    'pa_do-day'     => ['tieu-chuan'],
                ],
            ],
        ];
    }

    public static function createMockCatalog(): CatalogSearchInterface {
        return new class () implements CatalogSearchInterface {
            public function getSnapshot(): array {
                $items = SafetyRuleEngineTest::buildFixtureCatalog();
                return [
                    'version'  => 'test-fixture-v1',
                    'built_at' => '2026-11-13T00:00:00+07:00',
                    'count'    => count($items),
                    'items'    => $items,
                ];
            }

            public function getCandidates(): array {
                return SafetyRuleEngineTest::buildFixtureCatalog();
            }

            public function getVersion(): string {
                return 'test-fixture-v1';
            }

            public function rebuild(): array {
                return $this->getSnapshot();
            }
        };
    }

    public function test_bathroom_kitchen_elderly_and_children_strictly_exclude_r9_and_missing_r_even_under_relaxation(): void {
        $engine     = new QueryEngine(self::createMockCatalog());
        $normalizer = new SlotNormalizer();

        $safetyQueries = [
            'Gạch lát nhà tắm 6m2 màu hồng ngân sách 80k', // ép kích hoạt relaxation hết cỡ (màu hồng + 80k không có sẵn)
            'Gạch lát sàn bếp 12m2 chống trơn dầu mỡ',
            'Lát phòng ngủ 16m2 cho ông bà lớn tuổi',
            'Sàn nhà có trẻ nhỏ chạy nhảy 20m2',
        ];

        foreach ($safetyQueries as $q) {
            $slots  = $normalizer->extractFromText($q);
            $result = $engine->query($slots, 5);

            self::assertGreaterThanOrEqual(10, $result['required_r'], 'Ngưỡng R bắt buộc phải >= 10 cho: ' . $q);
            self::assertNotEmpty($result['items']);

            foreach ($result['items'] as $item) {
                self::assertGreaterThanOrEqual(
                    10,
                    (int) $item['dcof_level'],
                    sprintf('Mẫu %s (R=%d) vi phạm luật an toàn cứng khi hỏi: "%s"', $item['sku'], $item['dcof_level'], $q)
                );
                self::assertNotContains($item['sku'], ['DRV-8801M', 'DRV-SLAB01', 'DRV-TH7530', 'DRV-SLAB02']);
            }
        }
    }

    public function test_wall_tiling_in_kitchen_or_bathroom_allows_subway_tile_without_floor_slip_lock(): void {
        $engine     = new QueryEngine(self::createMockCatalog());
        $normalizer = new SlotNormalizer();

        $slots  = $normalizer->extractFromText('Ốp tường bếp 8m2 gạch thẻ màu xanh lá');
        $result = $engine->query($slots, 5);

        $skus = array_column($result['items'], 'sku');
        self::assertContains('DRV-TH7530', $skus, 'Khi khách hỏi rõ "ốp tường" gạch thẻ, DRV-TH7530 phải được phép xuất hiện.');
    }

    public function test_outdoor_requires_r11_garage_requires_r11_plus_12mm_and_pool_requires_r12(): void {
        $engine     = new QueryEngine(self::createMockCatalog());
        $normalizer = new SlotNormalizer();

        // 1. Sân vườn / Ban công -> >= R11 (cả DRV-SV40 R12 và DRV-OUT60-THIN R11 đều hợp lệ, toàn bộ R10 bị loại)
        $outdoorRes = $engine->query($normalizer->extractFromText('Gạch lát sân vườn ngoài trời 30m2'), 5);
        self::assertSame(11, $outdoorRes['required_r']);
        foreach ($outdoorRes['items'] as $item) {
            self::assertGreaterThanOrEqual(11, (int) $item['dcof_level']);
        }

        // 2. Gara ô tô -> >= R11 VÀ dày >= 12mm (DRV-OUT60-THIN 9mm bị loại cứng, chỉ còn DRV-SV40 15mm R12)
        $garageRes  = $engine->query($normalizer->extractFromText('Gạch lát gara ô tô 28m2'), 5);
        $garageSkus = array_column($garageRes['items'], 'sku');
        self::assertSame(['DRV-SV40'], $garageSkus);

        // 3. Hồ bơi -> >= R12 (DRV-OUT60-THIN R11 bị loại, chỉ còn DRV-SV40 R12)
        $poolRes  = $engine->query($normalizer->extractFromText('Gạch lát thành hồ bơi 20m2'), 5);
        $poolSkus = array_column($poolRes['items'], 'sku');
        self::assertSame(12, $poolRes['required_r']);
        self::assertSame(['DRV-SV40'], $poolSkus);
    }

    public function test_polished_tile_in_bathroom_triggers_safety_tradeoff_and_never_leaks_r9(): void {
        $engine     = new QueryEngine(self::createMockCatalog());
        $normalizer = new SlotNormalizer();

        $slots  = $normalizer->extractFromText('Mình thích gạch bóng kiếng lát sàn nhà tắm 6m2');
        $result = $engine->query($slots, 5);

        self::assertSame(10, $result['required_r']);
        self::assertNotNull($result['tradeoff']);
        self::assertStringContainsString('R10', (string) $result['tradeoff']);
        self::assertContains('kb-faq#faq-bong-nha-tam', $result['kb_refs']);

        foreach ($result['items'] as $item) {
            self::assertGreaterThanOrEqual(10, (int) $item['dcof_level']);
        }
    }

    public function test_deduplicates_same_drv_series_and_caps_quote_only_at_two_items_and_calculates_boxes(): void {
        $engine     = new QueryEngine(self::createMockCatalog());
        $normalizer = new SlotNormalizer();

        $slots  = $normalizer->extractFromText('Tìm gạch lát nhà tắm 6m2 màu kem sang trọng');
        $result = $engine->query($slots, 5);

        self::assertLessThanOrEqual(6, count($result['items']));

        // Khử trùng lặp NORDIC-SLATE (DRV-3605S và DRV-3605S-DARK không bao giờ cùng xuất hiện)
        $seriesSeen = [];
        $quoteCount = 0;
        foreach ($result['items'] as $item) {
            $series = (string) $item['series'];
            if ($series !== '') {
                self::assertArrayNotHasKey($series, $seriesSeen, 'Không được lặp lại 2 biến thể cùng drv_series.');
                $seriesSeen[$series] = true;
            }
            if ($item['price_state'] === 'quote_only') {
                $quoteCount++;
                self::assertNull($item['calc']['est_total_vnd'], 'Sản phẩm quote_only không được có est_total_vnd.');
            } else {
                self::assertIsInt($item['calc']['est_total_vnd']);
                self::assertGreaterThan(0, $item['calc']['est_total_vnd']);
            }
        }

        self::assertLessThanOrEqual(2, $quoteCount, 'Tối đa 2 mẫu quote_only trong 6 kết quả gợi ý.');

        // Kiểm tra tính toán chính xác cho DRV-3605S: 6m² + 5% = 6.3m²; 1 thùng = 8 viên × 0.18 = 1.44m² -> ceil(6.3 / 1.44) = 5 thùng (40 viên = 7.2m²)
        $drv3605 = null;
        foreach ($result['items'] as $it) {
            if ($it['sku'] === 'DRV-3605S') {
                $drv3605 = $it;
                break;
            }
        }
        self::assertNotNull($drv3605);
        self::assertSame(5, $drv3605['calc']['boxes']);
        self::assertSame(40, $drv3605['calc']['pieces']);
        self::assertSame(7.2, $drv3605['calc']['actual_m2']);
    }
}
