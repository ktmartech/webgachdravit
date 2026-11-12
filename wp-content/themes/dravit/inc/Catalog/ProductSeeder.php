<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Khởi tạo idempotent 10 sản phẩm gạch mẫu: 8 SKU chuẩn nghiệm thu (`docs/09 §3.1`) + 2 SKU `quote_only` (`docs/04 §2.1`).
 */
final class ProductSeeder {
    /**
     * Danh sách 10 SKU chuẩn (có thể tái sử dụng trực tiếp trong Unit Test `CollectionSyncTest`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array {
        return [
            [
                'sku'                  => 'DRV-8801M',
                'name'                 => 'Gạch Vân Đá Calacatta Gold 80x80 Bóng Kiếng',
                'slug'                 => 'gach-van-da-calacatta-gold-80x80-drv-8801m',
                'short_description'    => 'Gạch xương đá Porcelain mài cạnh phẳng 800×800 mm, bề mặt bóng kiếng tái hiện 8 mặt vân Calacatta Gold sang trọng.',
                'stock_qty'            => 180,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 385000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 800,
                    'drv_piece_h_mm'       => 800,
                    'drv_thickness_mm'     => 9.5,
                    'drv_pieces_per_box'   => 3,
                    'drv_kg_per_box'       => 42.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 3,
                    'drv_water_absorption' => 0.12,
                    'drv_pei'              => 'PEI IV',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 8,
                    'drv_grout_mm'         => 1.5,
                    'drv_grout_color'      => 'Trắng sứ',
                    'drv_series'           => 'Calacatta Royal',
                    'drv_showroom_shelf'   => 'A-01',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['phong-khach', 'mat-tien'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['bong-kieng'],
                    'pa_mau-sac'    => ['trang'],
                    'pa_dcof'       => ['r9'],
                    'pa_phong-cach' => ['hien-dai', 'tan-co-dien'],
                    'pa_hinh-dang'  => ['vuong'],
                    'pa_bo-suu-tap' => ['dravit-luxury'],
                    'pa_xuat-xu'    => ['an-do'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-phong-khach', 'gach-mat-tien', 'gach-van-da', 'gach-porcelain', 'gach-80x80'],
                'expected_primary'     => 'gach-lat-nen',
            ],
            [
                'sku'                  => 'DRV-3605S',
                'name'                 => 'Gạch Vân Đá Travertine Kem 30x60 Mờ Mịn R10',
                'slug'                 => 'gach-van-da-travertine-kem-30x60-drv-3605s',
                'short_description'    => 'Gạch ốp lát 300×600 mm men Matt mịn chống trơn R10, đồng mạch tường và sàn cho phòng tắm, khu bếp.',
                'stock_qty'            => 320,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 265000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 300,
                    'drv_piece_h_mm'       => 600,
                    'drv_thickness_mm'     => 8.8,
                    'drv_pieces_per_box'   => 8,
                    'drv_kg_per_box'       => 26.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 8,
                    'drv_water_absorption' => 0.35,
                    'drv_pei'              => 'PEI III',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 6,
                    'drv_grout_mm'         => 1.5,
                    'drv_grout_color'      => 'Kem ngà',
                    'drv_series'           => 'Travertine Mist',
                    'drv_showroom_shelf'   => 'B-04',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['nha-tam', 'phong-bep'],
                    'pa_van-gach'   => ['van-da-tu-nhien'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['mo'],
                    'pa_mau-sac'    => ['kem'],
                    'pa_dcof'       => ['r10'],
                    'pa_phong-cach' => ['hien-dai'],
                    'pa_hinh-dang'  => ['chu-nhat'],
                    'pa_bo-suu-tap' => ['dravit-signature'],
                    'pa_xuat-xu'    => ['viet-nam'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-nha-tam', 'gach-phong-bep', 'gach-van-da', 'gach-porcelain', 'gach-30x60'],
                'expected_primary'     => 'gach-op-tuong',
            ],
            [
                'sku'                  => 'DRV-61209',
                'name'                 => 'Gạch Vân Xi Măng Minimal Grey 60x120 Matt R10',
                'slug'                 => 'gach-van-xi-mang-minimal-grey-60x120-drv-61209',
                'short_description'    => 'Gạch khổ lớn 600×1200 mm tông xám bê tông trung tính, ốp kịch trần nhà tắm master và lát sàn không gian mở.',
                'stock_qty'            => 120,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 520000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 600,
                    'drv_piece_h_mm'       => 1200,
                    'drv_thickness_mm'     => 9.8,
                    'drv_pieces_per_box'   => 2,
                    'drv_kg_per_box'       => 34.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 2,
                    'drv_water_absorption' => 0.15,
                    'drv_pei'              => 'PEI IV',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 6,
                    'drv_grout_mm'         => 1.5,
                    'drv_grout_color'      => 'Xám xi măng',
                    'drv_series'           => 'Urban Concrete',
                    'drv_showroom_shelf'   => 'A-08',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['phong-khach', 'nha-tam', 'phong-ngu'],
                    'pa_van-gach'   => ['van-xi-mang'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['mo'],
                    'pa_mau-sac'    => ['xam'],
                    'pa_dcof'       => ['r10'],
                    'pa_phong-cach' => ['toi-gian', 'japandi'],
                    'pa_hinh-dang'  => ['chu-nhat'],
                    'pa_bo-suu-tap' => ['dravit-signature'],
                    'pa_xuat-xu'    => ['tay-ban-nha'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-phong-khach', 'gach-nha-tam', 'gach-phong-ngu', 'gach-van-xi-mang', 'gach-porcelain', 'gach-60x120'],
                'expected_primary'     => 'gach-60x120',
            ],
            [
                'sku'                  => 'DRV-SLAB01',
                'name'                 => 'Đá Nung Kết Emperador Big Slab 120x240 Bán Bóng',
                'slug'                 => 'da-nung-ket-emperador-big-slab-120x240-drv-slab01',
                'short_description'    => 'Tấm đá nung kết khổ lớn 1200×2400 mm chịu nhiệt, chống trầy Mohs ≥ 6 chuyên dụng ốp vách TV, mặt tiền và đảo bếp.',
                'stock_qty'            => 28,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 1250000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 1200,
                    'drv_piece_h_mm'       => 2400,
                    'drv_thickness_mm'     => 9.0,
                    'drv_pieces_per_box'   => 1,
                    'drv_kg_per_box'       => 68.0,
                    'drv_unit_label'       => 'tấm',
                    'drv_buy_units'        => ['m2', 'thung', 'vien'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'piece',
                    'drv_qty_min'          => 1,
                    'drv_water_absorption' => 0.05,
                    'drv_pei'              => 'PEI V',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 4,
                    'drv_grout_mm'         => 1.0,
                    'drv_grout_color'      => 'Nâu khói',
                    'drv_series'           => 'Emperador Maximus',
                    'drv_showroom_shelf'   => 'S-01',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong', 'trang-tri'],
                    'pa_khong-gian' => ['phong-khach', 'mat-tien', 'phong-bep'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['da-nung-ket'],
                    'pa_be-mat'     => ['ban-bong'],
                    'pa_mau-sac'    => ['nau'],
                    'pa_dcof'       => ['r9'],
                    'pa_phong-cach' => ['hien-dai'],
                    'pa_hinh-dang'  => ['chu-nhat'],
                    'pa_bo-suu-tap' => ['dravit-luxury'],
                    'pa_xuat-xu'    => ['y'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-trang-tri', 'gach-phong-khach', 'gach-mat-tien', 'gach-phong-bep', 'gach-van-da', 'da-nung-ket'],
                'expected_primary'     => 'da-nung-ket',
            ],
            [
                'sku'                  => 'DRV-W1590',
                'name'                 => 'Gạch Giả Gỗ Sồi Bắc Âu 15x90 Khắc Vân Carving',
                'slug'                 => 'gach-gia-go-soi-bac-au-15x90-drv-w1590',
                'short_description'    => 'Thanh gạch giả gỗ xương đá Porcelain 150×900 mm khắc rãnh theo thớ gỗ tự nhiên, không cong vênh và mối mọt.',
                'stock_qty'            => 240,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 340000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 150,
                    'drv_piece_h_mm'       => 900,
                    'drv_thickness_mm'     => 9.2,
                    'drv_pieces_per_box'   => 8,
                    'drv_kg_per_box'       => 24.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 8,
                    'drv_water_absorption' => 0.25,
                    'drv_pei'              => 'PEI IV',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 12,
                    'drv_grout_mm'         => 2.0,
                    'drv_grout_color'      => 'Nâu sồi',
                    'drv_series'           => 'Nordic Oak',
                    'drv_showroom_shelf'   => 'W-02',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['phong-ngu', 'phong-khach', 'ngoai-troi'],
                    'pa_van-gach'   => ['gia-go'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['carving'],
                    'pa_mau-sac'    => ['nau'],
                    'pa_dcof'       => ['r10'],
                    'pa_phong-cach' => ['japandi', 'wabi-sabi'],
                    'pa_hinh-dang'  => ['chu-nhat'],
                    'pa_bo-suu-tap' => ['dravit-eco'],
                    'pa_xuat-xu'    => ['viet-nam'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-phong-ngu', 'gach-phong-khach', 'gach-ngoai-troi', 'gach-gia-go', 'gach-porcelain', 'gach-15x80-20x100'],
                'expected_primary'     => 'gach-gia-go',
            ],
            [
                'sku'                  => 'DRV-TRZ60',
                'name'                 => 'Gạch Terrazzo Ivory Đa Sắc 60x60 Matt Chống Trơn',
                'slug'                 => 'gach-terrazzo-ivory-da-sac-60x60-drv-trz60',
                'short_description'    => 'Gạch hạt đá mài Terrazzo 600×600 mm nền kem đa sắc tinh tế cho nhà tắm, bếp, quán cafe và hiên ngoài trời.',
                'stock_qty'            => 200,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 320000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 600,
                    'drv_piece_h_mm'       => 600,
                    'drv_thickness_mm'     => 9.2,
                    'drv_pieces_per_box'   => 4,
                    'drv_kg_per_box'       => 30.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 4,
                    'drv_water_absorption' => 0.30,
                    'drv_pei'              => 'PEI IV',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 6,
                    'drv_grout_mm'         => 1.5,
                    'drv_grout_color'      => 'Trắng kem',
                    'drv_series'           => 'Venezia Terrazzo',
                    'drv_showroom_shelf'   => 'T-03',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong', 'trang-tri'],
                    'pa_khong-gian' => ['phong-khach', 'nha-tam', 'phong-bep', 'ngoai-troi'],
                    'pa_van-gach'   => ['terrazzo'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['mo'],
                    'pa_mau-sac'    => ['da-sac'],
                    'pa_dcof'       => ['r10'],
                    'pa_phong-cach' => ['hien-dai', 'indochine'],
                    'pa_hinh-dang'  => ['vuong'],
                    'pa_bo-suu-tap' => ['dravit-signature'],
                    'pa_xuat-xu'    => ['viet-nam'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-trang-tri', 'gach-phong-khach', 'gach-nha-tam', 'gach-phong-bep', 'gach-ngoai-troi', 'gach-terrazzo', 'gach-porcelain', 'gach-60x60'],
                'expected_primary'     => 'gach-terrazzo',
            ],
            [
                'sku'                  => 'DRV-SV40',
                'name'                 => 'Gạch Sân Vườn Đá Chẻ Chịu Lực 40x40 Dày 15mm R12',
                'slug'                 => 'gach-san-vuon-da-che-chiu-luc-40x40-drv-sv40',
                'short_description'    => 'Gạch sân vườn và gara ô tô dày 15 mm chịu tải trọng cao, bề mặt định hình chống trượt R12 kháng rêu mốc.',
                'stock_qty'            => 160,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 235000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 400,
                    'drv_piece_h_mm'       => 400,
                    'drv_thickness_mm'     => 15.0,
                    'drv_pieces_per_box'   => 4,
                    'drv_kg_per_box'       => 22.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 4,
                    'drv_water_absorption' => 0.20,
                    'drv_pei'              => 'PEI V',
                    'drv_rectified'        => '0',
                    'drv_faces'            => 4,
                    'drv_grout_mm'         => 3.0,
                    'drv_grout_color'      => 'Xám đen',
                    'drv_series'           => 'Patio Forte',
                    'drv_showroom_shelf'   => 'O-01',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen'],
                    'pa_khong-gian' => ['san-vuon', 'ngoai-troi'],
                    'pa_van-gach'   => ['van-da-tu-nhien'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['nham'],
                    'pa_mau-sac'    => ['xam'],
                    'pa_dcof'       => ['r12'],
                    'pa_phong-cach' => ['hien-dai'],
                    'pa_hinh-dang'  => ['vuong'],
                    'pa_bo-suu-tap' => ['dravit-eco'],
                    'pa_xuat-xu'    => ['viet-nam'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-san-vuon', 'gach-ngoai-troi', 'gach-van-da', 'gach-porcelain'],
                'expected_primary'     => 'gach-san-vuon',
            ],
            [
                'sku'                  => 'DRV-TH7530',
                'name'                 => 'Gạch Thẻ Subway Xanh Rêu Thủ Công 7,5x30',
                'slug'                 => 'gach-the-subway-xanh-reu-thu-cong-7-5x30-drv-th7530',
                'short_description'    => 'Gạch thẻ ốp tường bếp và quầy bar 75×300 mm men bóng gợn thủ công, dễ phối kiểu xương cá hoặc so le.',
                'stock_qty'            => 880,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 450000,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 75,
                    'drv_piece_h_mm'       => 300,
                    'drv_thickness_mm'     => 8.0,
                    'drv_pieces_per_box'   => 44,
                    'drv_kg_per_box'       => 14.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung', 'vien'],
                    'drv_default_buy_unit' => 'thung',
                    'drv_round_mode'       => 'piece',
                    'drv_qty_min'          => 10,
                    'drv_water_absorption' => 11.5,
                    'drv_pei'              => 'PEI II',
                    'drv_rectified'        => '0',
                    'drv_faces'            => 8,
                    'drv_grout_mm'         => 2.0,
                    'drv_grout_color'      => 'Trắng ngà',
                    'drv_series'           => 'Artisan Subway',
                    'drv_showroom_shelf'   => 'D-05',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['op-tuong', 'trang-tri', 'gach-the'],
                    'pa_khong-gian' => ['phong-bep', 'nha-tam'],
                    'pa_chat-lieu'  => ['ceramic'],
                    'pa_be-mat'     => ['bong-kieng'],
                    'pa_mau-sac'    => ['xanh-la'],
                    'pa_phong-cach' => ['indochine'],
                    'pa_hinh-dang'  => ['chu-nhat'],
                    'pa_bo-suu-tap' => ['dravit-signature'],
                    'pa_xuat-xu'    => ['tay-ban-nha'],
                ],
                'expected_collections' => ['gach', 'gach-op-tuong', 'gach-trang-tri', 'gach-the', 'gach-phong-bep', 'gach-nha-tam'],
                'expected_primary'     => 'gach-the',
            ],
            // 2 SKU chế độ Liên hệ báo giá (`quote_only`, D-20)
            [
                'sku'                  => 'DRV-3612T',
                'name'                 => 'Gạch Dự Án Travertine Sand 30x60 (Hàng Đặt Lô Công Trình)',
                'slug'                 => 'gach-du-an-travertine-sand-30x60-drv-3612t',
                'short_description'    => 'Dòng gạch dự án kiểm soát đồng lô Shade/Caliber theo khối lượng công trình, nhận báo giá chiết khấu trực tiếp.',
                'stock_qty'            => 400,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 0,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 300,
                    'drv_piece_h_mm'       => 600,
                    'drv_thickness_mm'     => 9.0,
                    'drv_pieces_per_box'   => 8,
                    'drv_kg_per_box'       => 26.0,
                    'drv_unit_label'       => 'viên',
                    'drv_buy_units'        => ['m2', 'thung'],
                    'drv_default_buy_unit' => 'm2',
                    'drv_round_mode'       => 'box',
                    'drv_qty_min'          => 8,
                    'drv_water_absorption' => 0.30,
                    'drv_pei'              => 'PEI IV',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 6,
                    'drv_grout_mm'         => 1.5,
                    'drv_grout_color'      => 'Kem cát',
                    'drv_series'           => 'Project Series',
                    'drv_showroom_shelf'   => 'P-01',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['lat-nen', 'op-tuong'],
                    'pa_khong-gian' => ['nha-tam', 'phong-khach'],
                    'pa_van-gach'   => ['van-da-tu-nhien'],
                    'pa_chat-lieu'  => ['porcelain'],
                    'pa_be-mat'     => ['mo'],
                    'pa_mau-sac'    => ['vang'],
                    'pa_dcof'       => ['r10'],
                ],
                'expected_collections' => ['gach', 'gach-lat-nen', 'gach-op-tuong', 'gach-nha-tam', 'gach-phong-khach', 'gach-van-da', 'gach-porcelain', 'gach-30x60'],
                'expected_primary'     => 'gach-op-tuong',
            ],
            [
                'sku'                  => 'DRV-SLAB02',
                'name'                 => 'Đá Nung Kết Statuario Venato 160x320 Dày 6mm (Báo Giá Gia Công)',
                'slug'                 => 'da-nung-ket-statuario-venato-160x320-drv-slab02',
                'short_description'    => 'Tấm đá nung kết khổ đại 1600×3200 mm mỏng 6 mm chuyên ốp vách thông tầng, mặt bàn ăn và cánh tủ cao cấp.',
                'stock_qty'            => 16,
                'meta'                 => [
                    'drv_line'             => 'gach',
                    'drv_pricing_model'    => 'area_pack',
                    'drv_price_m2'         => 0,
                    'drv_sale_price_m2'    => 0,
                    'drv_piece_w_mm'       => 1600,
                    'drv_piece_h_mm'       => 3200,
                    'drv_thickness_mm'     => 6.0,
                    'drv_pieces_per_box'   => 1,
                    'drv_kg_per_box'       => 74.0,
                    'drv_unit_label'       => 'tấm',
                    'drv_buy_units'        => ['m2', 'vien'],
                    'drv_default_buy_unit' => 'vien',
                    'drv_round_mode'       => 'piece',
                    'drv_qty_min'          => 1,
                    'drv_water_absorption' => 0.04,
                    'drv_pei'              => 'PEI IV',
                    'drv_rectified'        => '1',
                    'drv_faces'            => 2,
                    'drv_grout_mm'         => 1.0,
                    'drv_grout_color'      => 'Trắng vân mây',
                    'drv_series'           => 'Statuario Atelier',
                    'drv_showroom_shelf'   => 'S-02',
                ],
                'attributes'           => [
                    'pa_cong-nang'  => ['op-tuong', 'trang-tri'],
                    'pa_khong-gian' => ['phong-khach', 'mat-tien'],
                    'pa_van-gach'   => ['van-da-marble'],
                    'pa_chat-lieu'  => ['da-nung-ket'],
                    'pa_be-mat'     => ['bong-kieng'],
                    'pa_mau-sac'    => ['trang'],
                    'pa_bo-suu-tap' => ['dravit-luxury'],
                    'pa_xuat-xu'    => ['y'],
                ],
                'expected_collections' => ['gach', 'gach-op-tuong', 'gach-trang-tri', 'gach-phong-khach', 'gach-mat-tien', 'gach-van-da', 'da-nung-ket'],
                'expected_primary'     => 'da-nung-ket',
            ],
        ];
    }

    /**
     * Tạo mới hoặc cập nhật idempotent 10 SKU chuẩn và chạy đồng bộ `CollectionSync`.
     *
     * @return array<string, int> Map `SKU => product_id`
     */
    public function run(): array {
        if (!function_exists('wc_get_product_id_by_sku') || !class_exists('\WC_Product_Simple')) {
            return [];
        }

        $seeded = [];
        foreach (self::definitions() as $def) {
            $sku = (string) $def['sku'];
            $id  = (int) wc_get_product_id_by_sku($sku);

            $existing = $id > 0 ? wc_get_product($id) : false;
            $product  = ($existing instanceof \WC_Product_Simple) ? $existing : new \WC_Product_Simple();

            $product->set_sku($sku);
            $product->set_name((string) $def['name']);
            $product->set_slug((string) $def['slug']);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_short_description((string) $def['short_description']);
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $def['stock_qty']);
            $product->set_stock_status('instock');

            $meta = isset($def['meta']) && is_array($def['meta']) ? $def['meta'] : [];
            foreach ($meta as $k => $v) {
                $product->update_meta_data((string) $k, is_array($v) ? $v : (string) $v);
            }

            // Gắn WC_Product_Attribute cho các thuộc tính phân loại.
            $attr_defs  = isset($def['attributes']) && is_array($def['attributes']) ? $def['attributes'] : [];
            $wc_attrs   = [];
            $pos        = 0;
            foreach ($attr_defs as $tax => $slugs) {
                if (!taxonomy_exists((string) $tax) || !is_array($slugs)) {
                    continue;
                }
                $tids = [];
                foreach ($slugs as $term_slug) {
                    $t = get_term_by('slug', (string) $term_slug, (string) $tax);
                    if ($t instanceof \WP_Term) {
                        $tids[] = (int) $t->term_id;
                    }
                }
                if ($tids === []) {
                    continue;
                }

                $attr_id = function_exists('wc_attribute_taxonomy_id_by_name')
                    ? wc_attribute_taxonomy_id_by_name((string) $tax)
                    : 0;
                $wc_attr = new \WC_Product_Attribute();
                $wc_attr->set_id($attr_id);
                $wc_attr->set_name((string) $tax);
                $wc_attr->set_options($tids);
                $wc_attr->set_position($pos++);
                $wc_attr->set_visible(true);
                $wc_attr->set_variation(false);
                $wc_attrs[(string) $tax] = $wc_attr;
            }
            $product->set_attributes($wc_attrs);

            $saved_id = $product->save();
            if ($saved_id > 0) {
                $seeded[$sku] = $saved_id;
            }
        }

        return $seeded;
    }
}
