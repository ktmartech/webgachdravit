<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Units\Calc;

defined('ABSPATH') || exit;

/**
 * Bộ phân tách & chuẩn hóa dữ liệu Master Product Listing 48 cột (Excel `.xlsx` / `.csv`)
 * về chuẩn Data Dictionary (`docs/14`, `config/facets.php`, `config/collections.php`) và hồ sơ AI (`docs/13`).
 *
 * Thuần PHP (không gọi DB), đảm bảo 100% kiểm thử độc lập qua PHPUnit.
 */
final class MasterListingParser {
    /**
     * Bảng ánh xạ mọi biến thể tên cột (Technical key Excel, key không tiền tố, nhãn Tiếng Việt hàng 5)
     * về khóa chuẩn nội bộ.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        // Nhóm 1: Định danh
        'row_id'                                                    => 'row_id',
        'stt'                                                       => 'row_id',
        'sku'                                                       => 'sku',
        'ma-sku'                                                    => 'sku',
        'meta:drv_factory_code'                                     => 'drv_factory_code',
        'drv_factory_code'                                          => 'drv_factory_code',
        'ma-mau-ten-goc-nha-may'                                    => 'drv_factory_code',
        'meta:drv_source_album'                                     => 'drv_source_album',
        'drv_source_album'                                          => 'drv_source_album',
        'dong-gach-nguon-album-va'                                  => 'drv_source_album',
        'post_title'                                                => 'post_title',
        'name'                                                      => 'post_title',
        'ten'                                                       => 'post_title',
        'ten-san-pham-chuan-seo-tu-dong-ghep-hoac-nhap-tay'         => 'post_title',
        'ten-san-pham-chuan-seo'                                    => 'post_title',

        // Nhóm 2: Đơn vị, Quy cách & Giá
        'meta:drv_unit_mode'                                        => 'drv_pricing_model',
        'drv_unit_mode'                                             => 'drv_pricing_model',
        'drv_pricing_model'                                         => 'drv_pricing_model',
        'che-do-don-vi-d-06'                                        => 'drv_pricing_model',
        'meta:drv_length_mm'                                        => 'drv_piece_h_mm',
        'drv_length_mm'                                             => 'drv_piece_h_mm',
        'drv_piece_h_mm'                                            => 'drv_piece_h_mm',
        'dai-thuc-te-mm'                                            => 'drv_piece_h_mm',
        'meta:drv_width_mm'                                         => 'drv_piece_w_mm',
        'drv_width_mm'                                              => 'drv_piece_w_mm',
        'drv_piece_w_mm'                                            => 'drv_piece_w_mm',
        'rong-thuc-te-mm'                                           => 'drv_piece_w_mm',
        'meta:drv_tile_area_m2'                                     => '_ignore_tile_area_m2',
        'drv_tile_area_m2'                                          => '_ignore_tile_area_m2',
        'dien-tich-1-vien-m2-tu-tinh'                               => '_ignore_tile_area_m2',
        'meta:drv_pcs_per_box'                                      => 'drv_pieces_per_box',
        'drv_pcs_per_box'                                           => 'drv_pieces_per_box',
        'drv_pieces_per_box'                                        => 'drv_pieces_per_box',
        'so-vien-thung'                                             => 'drv_pieces_per_box',
        'meta:drv_m2_per_box'                                       => '_ignore_m2_per_box',
        'dien-tich-thung-m2-tu-tinh'                                => '_ignore_m2_per_box',
        'meta:drv_kg_per_box'                                       => 'drv_kg_per_box',
        'drv_kg_per_box'                                            => 'drv_kg_per_box',
        'khoi-luong-thung-kg'                                       => 'drv_kg_per_box',
        'meta:drv_price_per_m2'                                     => 'drv_price_m2',
        'drv_price_per_m2'                                          => 'drv_price_m2',
        'drv_price_m2'                                              => 'drv_price_m2',
        'gia-niem-yet-m2-vnd-chinh'                                 => 'drv_price_m2',
        'meta:drv_sale_price_m2'                                    => 'drv_sale_price_m2',
        'drv_sale_price_m2'                                         => 'drv_sale_price_m2',
        'regular_price'                                             => '_ignore_regular_price',
        'gia-giao-dich-vien-vnd-tu-tinh-wc-price'                   => '_ignore_regular_price',

        // Nhóm 3: Thuộc tính cốt lõi & Danh mục chính
        'meta:rank_math_primary_product_cat'                        => 'primary_cat_raw',
        'rank_math_primary_product_cat'                             => 'primary_cat_raw',
        'danh-muc-chinh-breadcrumb-primary-term'                    => 'primary_cat_raw',
        'attribute:pa_cong-nang'                                    => 'attr_cong_nang',
        'pa_cong-nang'                                              => 'attr_cong_nang',
        'cong-nang-ap-dung-flt-01-da-chon'                          => 'attr_cong_nang',
        'attribute:pa_loai-gach'                                    => 'attr_loai_gach',
        'pa_loai-gach'                                              => 'attr_loai_gach',
        'loai-gach-chi-tiet-flt-01'                                 => 'attr_loai_gach',
        'attribute:pa_khong-gian'                                   => 'attr_khong_gian',
        'pa_khong-gian'                                             => 'attr_khong_gian',
        'khong-gian-phu-hop-flt-02-da-chon'                         => 'attr_khong_gian',
        'attribute:pa_van-gach'                                     => 'attr_van_gach',
        'pa_van-gach'                                               => 'attr_van_gach',
        'nhom-van-gach-chinh-flt-03-04'                             => 'attr_van_gach',
        'meta:drv_pattern_detail'                                   => 'drv_color_name',
        'drv_pattern_detail'                                        => 'drv_color_name',
        'drv_color_name'                                            => 'drv_color_name',
        'ten-mau-van-tong-mau-chi-tiet'                             => 'drv_color_name',
        'attribute:pa_kich-thuoc'                                   => 'attr_kich_thuoc_raw',
        'pa_kich-thuoc'                                             => 'attr_kich_thuoc_raw',
        'kich-thuoc-hien-thi-flt-05-06-cm'                          => 'attr_kich_thuoc_raw',
        'attribute:pa_chat-lieu'                                    => 'attr_chat_lieu',
        'pa_chat-lieu'                                              => 'attr_chat_lieu',
        'chat-lieu-xuong-gach-flt-07'                               => 'attr_chat_lieu',

        // Nhóm 4: Thông số kỹ thuật chuyên sâu
        'attribute:pa_be-mat'                                       => 'attr_be_mat',
        'pa_be-mat'                                                 => 'attr_be_mat',
        'be-mat-hieu-ung-flt-08'                                    => 'attr_be_mat',
        'attribute:pa_mau-sac'                                      => 'attr_mau_sac',
        'pa_mau-sac'                                                => 'attr_mau_sac',
        'mau-sac-chu-dao-flt-09'                                    => 'attr_mau_sac',
        'attribute:pa_phong-cach'                                   => 'attr_phong_cach',
        'pa_phong-cach'                                             => 'attr_phong_cach',
        'phong-cach-kien-truc-flt-10'                               => 'attr_phong_cach',
        'attribute:pa_hinh-dang'                                    => 'attr_hinh_dang',
        'pa_hinh-dang'                                              => 'attr_hinh_dang',
        'hinh-dang-vien-gach-flt-11'                                => 'attr_hinh_dang',
        'attribute:pa_xuat-xu'                                      => 'attr_xuat_xu',
        'pa_xuat-xu'                                                => 'attr_xuat_xu',
        'thuong-hieu-xuat-xu-flt-12'                                => 'attr_xuat_xu',
        'attribute:pa_bo-suu-tap'                                   => 'attr_bo_suu_tap',
        'pa_bo-suu-tap'                                             => 'attr_bo_suu_tap',
        'attribute:pa_chong-truot'                                  => 'attr_dcof',
        'pa_chong-truot'                                            => 'attr_dcof',
        'attribute:pa_dcof'                                         => 'attr_dcof',
        'pa_dcof'                                                   => 'attr_dcof',
        'chi-so-chong-truot-r-dcof-flt-13'                          => 'attr_dcof',
        'meta:drv_water_thickness'                                  => 'compound_water_thickness',
        'drv_water_thickness'                                       => 'compound_water_thickness',
        'do-hut-nuoc-do-day-flt-13'                                 => 'compound_water_thickness',
        'meta:drv_pei_rectified'                                    => 'compound_pei_rectified',
        'drv_pei_rectified'                                         => 'compound_pei_rectified',
        'do-chiu-luc-mai-mon-pei-mai-canh'                          => 'compound_pei_rectified',

        // Nhóm 5: Hình ảnh & Showroom
        'meta:drv_faces_shade'                                      => 'compound_faces_shade',
        'drv_faces_shade'                                           => 'compound_faces_shade',
        'so-mat-van-faces-bien-thien-v1-v4'                         => 'compound_faces_shade',
        'meta:drv_in_showroom'                                      => 'in_showroom_raw',
        'drv_in_showroom'                                           => 'in_showroom_raw',
        'co-mau-tai-showroom-flt-14'                                => 'in_showroom_raw',
        'meta:drv_google_photos_url'                                => 'drv_google_photos_url',
        'drv_google_photos_url'                                     => 'drv_google_photos_url',
        'link-album-google-photos-goc'                              => 'drv_google_photos_url',
        'images'                                                    => 'images_raw',

        // Nhóm 6: SEO, URL & Mô tả ngắn
        'tax:product_cat'                                           => '_ignore_product_cat',
        'danh-sach-smart-collections-tu-dong-gan-d-02'              => '_ignore_product_cat',
        'post_name'                                                 => 'post_name',
        'slug'                                                      => 'post_name',
        'url-san-pham-chuan-san-pham-slug-d-14'                     => 'post_name',
        'post_excerpt'                                              => 'post_excerpt',
        'short_description'                                         => 'post_excerpt',
        'mo-ta-ngan-chuan-brand-voice-3-cau-vat-lieu-do-ben-thi-cong' => 'post_excerpt',

        // Nhóm 7: Dữ liệu Training AI & RAG (Cột 37 -> 48)
        'meta:ai_min_room_m2'                                       => 'ai_min_room_m2',
        'ai_min_room_m2'                                            => 'ai_min_room_m2',
        'dien-tich-phong-toi-thieu-m2-ai-loc-kho-gach'              => 'ai_min_room_m2',
        'meta:ai_max_room_m2'                                       => 'ai_max_room_m2',
        'ai_max_room_m2'                                            => 'ai_max_room_m2',
        'dien-tich-phong-toi-da-ly-tuong-m2'                        => 'ai_max_room_m2',
        'meta:ai_wastage_pct'                                       => 'ai_wastage_pct',
        'ai_wastage_pct'                                            => 'ai_wastage_pct',
        'ty-le-hao-hut-khuyen-nghi-ai-cong-tu-dong'                 => 'ai_wastage_pct',
        'meta:ai_not_recommended_for'                               => 'ai_not_recommended_for',
        'ai_not_recommended_for'                                    => 'ai_not_recommended_for',
        'khong-gian-khong-khuyen-khich-ai-guardrail-cam-ky'         => 'ai_not_recommended_for',
        'meta:ai_install_requirement'                               => 'ai_install_requirement',
        'ai_install_requirement'                                    => 'ai_install_requirement',
        'vat-tu-luu-y-thi-cong-bat-buoc-keo-dan-ke-ron'             => 'ai_install_requirement',
        'meta:ai_color_undertone'                                   => 'ai_color_undertone',
        'ai_color_undertone'                                        => 'ai_color_undertone',
        'sac-do-nhiet-do-mau-ai-khop-so-thich-mau'                  => 'ai_color_undertone',
        'meta:ai_lighting_effect'                                   => 'ai_lighting_effect',
        'ai_lighting_effect'                                        => 'ai_lighting_effect',
        'hieu-ung-anh-sang-thi-giac-trong-phong'                    => 'ai_lighting_effect',
        'meta:ai_pairing_suggestion'                                => 'ai_pairing_suggestion',
        'ai_pairing_suggestion'                                     => 'ai_pairing_suggestion',
        'goi-y-phoi-cap-ma-gach-mau-keo-cha-ron'                    => 'ai_pairing_suggestion',
        'meta:ai_special_needs_tags'                                => 'ai_special_needs_tags',
        'ai_special_needs_tags'                                     => 'ai_special_needs_tags',
        'the-yeu-cau-dac-biet-ai-khop-nhu-cau-doi-song'             => 'ai_special_needs_tags',
        'meta:ai_budget_tier'                                       => 'ai_budget_tier',
        'ai_budget_tier'                                            => 'ai_budget_tier',
        'phan-khuc-ngan-sach-tu-dong-phan-loai-theo-gia-m2'         => 'ai_budget_tier',
        'meta:ai_why_buy_one_liner'                                 => 'ai_why_buy_one_liner',
        'ai_why_buy_one_liner'                                      => 'ai_why_buy_one_liner',
        'ly-do-ai-khuyen-chon-1-cau-tom-tat-dat-gia-chuan-dravit'   => 'ai_why_buy_one_liner',
        'meta:ai_rag_summary_chunk'                                 => 'ai_rag_summary_chunk',
        'ai_rag_summary_chunk'                                      => 'ai_rag_summary_chunk',
        'chuoi-ngu-nghia-nen-cho-ai-rag-summary-chunk-tu-dong-ghep-47-cot' => 'ai_rag_summary_chunk',

        // Cột đơn nguyên hiện đại trong docs/14 (tương thích ngược 100%)
        'drv_line'                                                  => 'drv_line',
        'drv_thickness_mm'                                          => 'drv_thickness_mm',
        'drv_water_absorption'                                      => 'drv_water_absorption',
        'drv_pei'                                                   => 'drv_pei',
        'drv_rectified'                                             => 'drv_rectified',
        'drv_faces'                                                 => 'drv_faces',
        'drv_shade'                                                 => 'drv_shade',
        'drv_grout_mm'                                              => 'drv_grout_mm',
        'drv_grout_color'                                           => 'drv_grout_color',
        'drv_series'                                                => 'drv_series',
        'drv_showroom_shelf'                                        => 'drv_showroom_shelf',
        'drv_card_label'                                            => 'drv_card_label',
        'drv_ai_blurb'                                              => 'drv_ai_blurb',
        'drv_unit_label'                                            => 'drv_unit_label',
        'drv_buy_units'                                             => 'drv_buy_units',
        'drv_default_buy_unit'                                      => 'drv_default_buy_unit',
        'drv_round_mode'                                            => 'drv_round_mode',
        'drv_qty_min'                                               => 'drv_qty_min',
        'drv_waste_rate'                                            => 'drv_waste_rate',
    ];

    /**
     * Từ điển đồng nghĩa quy chiếu nhãn Tiếng Việt / Tiếng Anh về đúng canonical slug trong `config/facets.php`.
     *
     * @var array<string, array<string, string>>
     */
    private const TAXONOMY_SYNONYMS = [
        'pa_cong-nang' => [
            'lat-nen'                   => 'lat-nen',
            'gach-lat-nen'              => 'lat-nen',
            'op-tuong'                  => 'op-tuong',
            'gach-op-tuong'             => 'op-tuong',
            'trang-tri'                 => 'trang-tri',
            'gach-trang-tri'            => 'trang-tri',
            'gach-bong'                 => 'gach-bong',
            'bong-gio'                  => 'bong-gio',
            'gach-bong-gio'             => 'bong-gio',
            'gach-the'                  => 'gach-the',
            'op-mat-bep-lavabo'         => 'op-mat-bep-lavabo',
            'op-mat-bep'                => 'op-mat-bep-lavabo',
            'mat-bep'                   => 'op-mat-bep-lavabo',
            'ban-dao'                   => 'op-mat-bep-lavabo',
            'lavabo'                    => 'op-mat-bep-lavabo',
            'countertop'                => 'op-mat-bep-lavabo',
            'gach-san-nang'             => 'gach-san-nang',
            'gach-20mm'                 => 'gach-san-nang',
            'gach-20mm-san-vuon'        => 'gach-san-nang',
            'outdoor-paver'             => 'gach-san-nang',
            'paver'                     => 'gach-san-nang',
            'bac-cau-thang'             => 'bac-cau-thang',
            'bac-cau-thang-len-tuong'   => 'bac-cau-thang',
            'len-tuong'                 => 'bac-cau-thang',
            'len-chan-tuong'            => 'bac-cau-thang',
        ],
        'pa_khong-gian' => [
            'phong-khach'         => 'phong-khach',
            'nha-tam'             => 'nha-tam',
            'phong-tam'           => 'nha-tam',
            'wc'                  => 'nha-tam',
            'nha-ve-sinh'         => 'nha-tam',
            'phong-bep'           => 'phong-bep',
            'bep'                 => 'phong-bep',
            'nha-bep'             => 'phong-bep',
            'san-vuon'            => 'san-vuon',
            'gara-o-to'           => 'gara-o-to',
            'gara-o-to-bai-do'    => 'gara-o-to',
            'gara'                => 'gara-o-to',
            'bai-do-xe'           => 'gara-o-to',
            'ngoai-troi'          => 'ngoai-troi',
            'ban-cong'            => 'ngoai-troi',
            'san-thuong'          => 'ngoai-troi',
            'hien-nha'            => 'ngoai-troi',
            'mat-tien'            => 'mat-tien',
            'phong-ngu'           => 'phong-ngu',
            'ho-boi'              => 'ho-boi',
            'be-boi'              => 'ho-boi',
            'sanh-thuong-mai'     => 'sanh-thuong-mai',
            'sanh'                => 'sanh-thuong-mai',
            'dai-sanh'            => 'sanh-thuong-mai',
            'biet-thu'            => 'biet-thu',
            'penthouse'           => 'biet-thu',
            'quan-cafe'           => 'quan-cafe',
            'cafe'                => 'quan-cafe',
            'nha-hang'            => 'quan-cafe',
            'hanh-lang-cau-thang' => 'hanh-lang-cau-thang',
            'hanh-lang'           => 'hanh-lang-cau-thang',
            'cau-thang'           => 'hanh-lang-cau-thang',
            'phong-tho'           => 'phong-tho',
            'khach-san-resort'    => 'khach-san-resort',
            'khach-san'           => 'khach-san-resort',
            'resort'              => 'khach-san-resort',
            'spa'                 => 'khach-san-resort',
        ],
        'pa_van-gach' => [
            'van-da-marble'        => 'van-da-marble',
            'marble'               => 'van-da-marble',
            'cam-thach'            => 'van-da-marble',
            'van-da-cam-thach'     => 'van-da-marble',
            'van-da-tu-nhien'      => 'van-da-tu-nhien',
            'da-tu-nhien'          => 'van-da-tu-nhien',
            'stone'                => 'van-da-tu-nhien',
            'van-da-travertine'    => 'van-da-travertine',
            'travertine'           => 'van-da-travertine',
            'da-travertine'        => 'van-da-travertine',
            'van-da-onyx'          => 'van-da-onyx',
            'onyx'                 => 'van-da-onyx',
            'da-onyx'              => 'van-da-onyx',
            'ngoc-xuyen-sang'      => 'van-da-onyx',
            'van-da-hoa-cuong'     => 'van-da-hoa-cuong',
            'van-da-granite'       => 'van-da-hoa-cuong',
            'hoa-cuong'            => 'van-da-hoa-cuong',
            'da-hoa-cuong'         => 'van-da-hoa-cuong',
            'van-da-bazan'         => 'van-da-bazan',
            'van-da-bazan-da-che'  => 'van-da-bazan',
            'bazan'                => 'van-da-bazan',
            'basalt'               => 'van-da-bazan',
            'slate'                => 'van-da-bazan',
            'da-che'               => 'van-da-bazan',
            'gia-go'               => 'gia-go',
            'van-go'               => 'gia-go',
            'gach-gia-go'          => 'gia-go',
            'terrazzo'             => 'terrazzo',
            'da-mai'               => 'terrazzo',
            'van-xi-mang'          => 'van-xi-mang',
            'xi-mang'              => 'van-xi-mang',
            'be-tong'              => 'van-xi-mang',
            'mosaic'               => 'mosaic',
            'don-sac'              => 'don-sac',
            'monochrome'           => 'don-sac',
            'van-vai'              => 'van-vai',
            'fabric'               => 'van-vai',
            'textile'              => 'van-vai',
            'van-cat'              => 'van-cat',
            'sandstone'            => 'van-cat',
            'van-kim-loai'         => 'van-kim-loai',
            'kim-loai'             => 'van-kim-loai',
            'metallic'             => 'van-kim-loai',
            'corten'               => 'van-kim-loai',
            'van-dat-nung'         => 'van-dat-nung',
            'dat-nung'             => 'van-dat-nung',
            'terracotta'           => 'van-dat-nung',
            'hinh-hoc'             => 'hinh-hoc',
            'van-3d'               => 'hinh-hoc',
            'hoa-van-co-dien'      => 'hoa-van-co-dien',
            'hoa-van-co-dien-gach-tham' => 'hoa-van-co-dien',
            'gach-tham'            => 'hoa-van-co-dien',
            'hoa-van'              => 'hoa-van-co-dien',
            'carpet'               => 'hoa-van-co-dien',
        ],
        'pa_chat-lieu' => [
            'porcelain'                   => 'porcelain',
            'xuong-da-porcelain'          => 'porcelain',
            'xuong-da-porcelain-kho-lon'  => 'porcelain',
            'xuong-da'                    => 'porcelain',
            'xuong-trang'                 => 'xuong-trang',
            'white-body'                  => 'xuong-trang',
            'dong-chat'                   => 'dong-chat',
            'full-body'                   => 'dong-chat',
            'da-nung-ket'                 => 'da-nung-ket',
            'sintered-stone'              => 'da-nung-ket',
            'granite'                     => 'granite',
            'gach-granite'                => 'granite',
            'ban-su'                      => 'ban-su',
            'xuong-ban-su'                => 'ban-su',
            'semi-porcelain'              => 'ban-su',
            'ceramic'                     => 'ceramic',
            'gach-ceramic'                => 'ceramic',
            'men-ceramic'                 => 'ceramic',
            'cotto-dat-nung'              => 'cotto-dat-nung',
            'dat-nung-cotto'              => 'cotto-dat-nung',
            'cotto'                       => 'cotto-dat-nung',
            'clinker'                     => 'cotto-dat-nung',
            'thuy-tinh'                   => 'thuy-tinh',
            'thuy-tinh-crystal'           => 'thuy-tinh',
            'crystal'                     => 'thuy-tinh',
            'glass-mosaic'                => 'thuy-tinh',
            'xi-mang'                     => 'xi-mang',
        ],
        'pa_be-mat' => [
            'bong-kieng'      => 'bong-kieng',
            'bong'            => 'bong-kieng',
            'polished'        => 'bong-kieng',
            'vi-tinh-the'     => 'vi-tinh-the',
            'microcrystal'    => 'vi-tinh-the',
            'diamond-glaze'   => 'vi-tinh-the',
            'mo'              => 'mo',
            'matt'            => 'mo',
            'matte'           => 'mo',
            'baby-skin'       => 'baby-skin',
            'babyskin'        => 'baby-skin',
            'velvet'          => 'baby-skin',
            'silk-matt'       => 'baby-skin',
            'satin'           => 'satin',
            'men-satin'       => 'satin',
            'satin-matt'      => 'satin',
            'honed'           => 'honed',
            'mai-mo'          => 'honed',
            'ban-bong'        => 'ban-bong',
            'lappato'         => 'ban-bong',
            'nham'            => 'nham',
            'nham-san'        => 'nham',
            'structured'      => 'nham',
            'granilia'        => 'granilia',
            'hat-mai'         => 'granilia',
            'dry-granula'     => 'granilia',
            'grit'            => 'granilia',
            'carving'         => 'carving',
            'khac-chim'       => 'carving',
            'sugar'           => 'sugar',
            'hieu-ung-sugar'  => 'sugar',
            'hieu-ung-hat'    => 'sugar',
            'line-punch'      => 'line-punch',
            'dinh-hinh-khuon' => 'line-punch',
            'khuon-noi-3d'    => 'khuon-noi-3d',
            'khuon-3d'        => 'khuon-noi-3d',
            'relief'          => 'khuon-noi-3d',
            '3d-relief'       => 'khuon-noi-3d',
        ],
        'pa_mau-sac' => [
            'trang'             => 'trang',
            'kem'               => 'kem',
            'nga'               => 'kem',
            'ivory'             => 'kem',
            'be'                => 'be',
            'beige'             => 'be',
            'ghi-sang'          => 'ghi-sang',
            'xam-nhat'          => 'ghi-sang',
            'xam-sang'          => 'ghi-sang',
            'light-grey'        => 'ghi-sang',
            'light-gray'        => 'ghi-sang',
            'xam'               => 'xam',
            'ghi'               => 'xam',
            'xam-dam'           => 'xam-dam',
            'xam-dam-than-chi'  => 'xam-dam',
            'than-chi'          => 'xam-dam',
            'charcoal'          => 'xam-dam',
            'dark-grey'         => 'xam-dam',
            'vang'              => 'vang',
            'vang-dong'         => 'vang-dong',
            'vang-dong-anh-kim' => 'vang-dong',
            'anh-kim'           => 'vang-dong',
            'gold'              => 'vang-dong',
            'nau'               => 'nau',
            'do-dat-nung'       => 'do-dat-nung',
            'cam-dat'           => 'do-dat-nung',
            'do-cotto'          => 'do-dat-nung',
            'terracotta'        => 'do-dat-nung',
            'den'               => 'den',
            'xanh-la'           => 'xanh-la',
            'xanh-reu'          => 'xanh-la',
            'xanh-ngoc'         => 'xanh-la',
            'xanh-duong'        => 'xanh-duong',
            'xanh-bien'         => 'xanh-duong',
            'xanh'              => 'xanh-duong',
            'hong'              => 'hong',
            'da-sac'            => 'da-sac',
        ],
        'pa_phong-cach' => [
            'hien-dai'      => 'hien-dai',
            'modern'        => 'hien-dai',
            'sang-trong'    => 'sang-trong',
            'luxury'        => 'sang-trong',
            'toi-gian'      => 'toi-gian',
            'minimalism'    => 'toi-gian',
            'minimalist'    => 'toi-gian',
            'wabi-sabi'     => 'wabi-sabi',
            'japandi'       => 'japandi',
            'bac-au'        => 'bac-au',
            'scandinavian'  => 'bac-au',
            'chau-au'       => 'chau-au',
            'european'      => 'chau-au',
            'dia-trung-hai' => 'dia-trung-hai',
            'mediterranean' => 'dia-trung-hai',
            'tropical'      => 'dia-trung-hai',
            'indochine'     => 'indochine',
            'dong-duong'    => 'indochine',
            'tan-co-dien'   => 'tan-co-dien',
            'neoclassic'    => 'tan-co-dien',
            'cong-nghiep'   => 'cong-nghiep',
            'industrial'    => 'cong-nghiep',
            'moc-mac'       => 'moc-mac',
            'rustic'        => 'moc-mac',
        ],
        'pa_hinh-dang' => [
            'vuong'               => 'vuong',
            'hinh-vuong'          => 'vuong',
            'chu-nhat'            => 'chu-nhat',
            'hinh-chu-nhat'       => 'chu-nhat',
            'thanh-dai'           => 'thanh-dai',
            'kitkat'              => 'kitkat',
            'vi-thanh-kitkat'     => 'kitkat',
            'finger'              => 'kitkat',
            'finger-mosaic'       => 'kitkat',
            'song-luon'           => 'song-luon',
            'song-luon-lam-dua'   => 'song-luon',
            'lam-dua'             => 'song-luon',
            'fluted'              => 'song-luon',
            'luc-giac'            => 'luc-giac',
            'hexagon'             => 'luc-giac',
            'bat-giac'            => 'bat-giac',
            'octagon'             => 'bat-giac',
            'tron-dong-xu'        => 'tron-dong-xu',
            'dong-xu'             => 'tron-dong-xu',
            'penny'               => 'tron-dong-xu',
            'penny-round'         => 'tron-dong-xu',
            'vay-ca'              => 'vay-ca',
            'long-vu'             => 'long-vu',
            'den-long'            => 'den-long',
            'arabesque'           => 'den-long',
            'ziczac'              => 'ziczac',
            'chevron'             => 'ziczac',
            'herringbone'         => 'ziczac',
        ],
        'pa_xuat-xu' => [
            'viet-nam'          => 'viet-nam',
            'tieu-chuan-eu'     => 'tieu-chuan-eu',
            'va-eu'             => 'tieu-chuan-eu',
            'tay-ban-nha'       => 'tay-ban-nha',
            'spain'             => 'tay-ban-nha',
            'y'                 => 'y',
            'italy'             => 'y',
            'an-do'             => 'an-do',
            'india'             => 'an-do',
            'malaysia'          => 'malaysia',
            'thai-lan'          => 'thai-lan',
            'thailand'          => 'thai-lan',
            'indonesia'         => 'indonesia',
            'trung-quoc'        => 'trung-quoc',
            'nhap-khau'         => 'nhap-khau',
            'nhap-khau-cao-cap' => 'nhap-khau',
        ],
    ];

    /**
     * Ánh xạ tên Danh mục chính Tiếng Việt về slug Smart Collection trong `config/collections.php`.
     *
     * @var array<string, string>
     */
    private const PRIMARY_CAT_MAP = [
        'gach'                  => 'gach',
        'gach-op-lat'           => 'gach',
        'gach-lat-nen'          => 'gach-lat-nen',
        'gach-op-tuong'         => 'gach-op-tuong',
        'gach-trang-tri'        => 'gach-trang-tri',
        'gach-bong'             => 'gach-bong',
        'gach-bong-gio'         => 'gach-bong-gio',
        'gach-the'              => 'gach-the',
        'gach-uu-dai'           => 'gach-uu-dai',
        'gach-phong-khach'      => 'gach-phong-khach',
        'gach-nha-tam'          => 'gach-nha-tam',
        'gach-phong-bep'        => 'gach-phong-bep',
        'gach-san-vuon'         => 'gach-san-vuon',
        'gach-ngoai-troi'       => 'gach-ngoai-troi',
        'gach-mat-tien'         => 'gach-mat-tien',
        'gach-phong-ngu'        => 'gach-phong-ngu',
        'gach-ho-boi'           => 'gach-ho-boi',
        'gach-gia-go'           => 'gach-gia-go',
        'gach-van-da'           => 'gach-van-da',
        'gach-van-da-marble'    => 'gach-van-da',
        'gach-terrazzo'         => 'gach-terrazzo',
        'gach-van-xi-mang'      => 'gach-van-xi-mang',
        'gach-mosaic'           => 'gach-mosaic',
        'da-nung-ket'           => 'da-nung-ket',
        'gach-porcelain'        => 'gach-porcelain',
        'gach-xuong-da-porcelain' => 'gach-porcelain',
        'gach-60x60'            => 'gach-60x60',
        'gach-80x80'            => 'gach-80x80',
        'gach-30x60'            => 'gach-30x60',
        'gach-60x120'           => 'gach-60x120',
        'gach-15x80-20x100'     => 'gach-15x80-20x100',
    ];

    /**
     * Danh sách nhãn hiển thị cho WooCommerce CSV Importer dropdown.
     *
     * @return array<string, string>
     */
    public static function wcCsvImporterColumns(): array {
        return [
            'meta:drv_factory_code'              => 'Master: Mã mẫu nhà máy (drv_factory_code)',
            'meta:drv_source_album'              => 'Master: Nguồn Album VA (drv_source_album)',
            'meta:drv_unit_mode'                 => 'Master: Chế độ đơn vị (drv_unit_mode)',
            'meta:drv_length_mm'                 => 'Master: Dài thực tế mm (drv_length_mm)',
            'meta:drv_width_mm'                  => 'Master: Rộng thực tế mm (drv_width_mm)',
            'meta:drv_pcs_per_box'               => 'Master: Số viên/thùng (drv_pcs_per_box)',
            'meta:drv_kg_per_box'                => 'Master: Khối lượng thùng kg (drv_kg_per_box)',
            'meta:drv_price_per_m2'              => 'Master: Giá niêm yết đ/m² (drv_price_per_m2)',
            'meta:rank_math_primary_product_cat' => 'Master: Danh mục chính Breadcrumb',
            'attribute:pa_cong-nang'             => 'Master: Công năng áp dụng (pa_cong-nang)',
            'attribute:pa_loai-gach'             => 'Master: Loại gạch chi tiết (pa_loai-gach)',
            'attribute:pa_khong-gian'            => 'Master: Không gian phù hợp (pa_khong-gian)',
            'attribute:pa_van-gach'              => 'Master: Nhóm vân gạch (pa_van-gach)',
            'meta:drv_pattern_detail'            => 'Master: Tên mẫu vân chi tiết (drv_color_name)',
            'attribute:pa_chat-lieu'             => 'Master: Chất liệu xương gạch (pa_chat-lieu)',
            'attribute:pa_be-mat'                => 'Master: Bề mặt / Hiệu ứng (pa_be-mat)',
            'attribute:pa_mau-sac'               => 'Master: Màu sắc chủ đạo (pa_mau-sac)',
            'attribute:pa_phong-cach'            => 'Master: Phong cách kiến trúc (pa_phong-cach)',
            'attribute:pa_hinh-dang'             => 'Master: Hình dáng viên gạch (pa_hinh-dang)',
            'attribute:pa_xuat-xu'               => 'Master: Thương hiệu / Xuất xứ (pa_xuat-xu)',
            'attribute:pa_chong-truot'           => 'Master: Chỉ số chống trượt R (pa_dcof)',
            'meta:drv_water_thickness'           => 'Master: Gộp Độ hút nước & Độ dày',
            'meta:drv_pei_rectified'             => 'Master: Gộp Độ mài mòn PEI & Mài cạnh',
            'meta:drv_faces_shade'               => 'Master: Gộp Số mặt vân Faces & Biến thiên V',
            'meta:drv_in_showroom'               => 'Master: Trạng thái mẫu tại Showroom',
            'meta:drv_google_photos_url'         => 'Master: Link Album Google Photos',
            'meta:ai_min_room_m2'                => 'Master AI: Diện tích phòng tối thiểu (m²)',
            'meta:ai_max_room_m2'                => 'Master AI: Diện tích phòng tối đa (m²)',
            'meta:ai_wastage_pct'                => 'Master AI: Tỷ lệ hao hụt khuyến nghị (%)',
            'meta:ai_not_recommended_for'        => 'Master AI: Không gian không khuyến khích',
            'meta:ai_install_requirement'        => 'Master AI: Vật tư & lưu ý thi công',
            'meta:ai_color_undertone'            => 'Master AI: Sắc độ & nhiệt độ màu',
            'meta:ai_lighting_effect'            => 'Master AI: Hiệu ứng ánh sáng trong phòng',
            'meta:ai_pairing_suggestion'         => 'Master AI: Gợi ý phối cặp & màu ron',
            'meta:ai_special_needs_tags'         => 'Master AI: Thẻ yêu cầu đặc biệt',
            'meta:ai_budget_tier'                => 'Master AI: Phân khúc ngân sách',
            'meta:ai_why_buy_one_liner'          => 'Master AI: Lý do AI khuyên chọn (1 câu)',
            'meta:ai_rag_summary_chunk'          => 'Master AI: Chuỗi RAG Summary Chunk',
        ];
    }

    /**
     * Chuẩn hóa tên cột bất kỳ về khóa nội bộ chuẩn.
     */
    public static function normalizeHeaderKey(string $raw_header): string {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $raw_header) ?? $raw_header;
        $clean = trim($clean);
        if ($clean === '') {
            return '';
        }

        $lower = strtolower($clean);
        if (isset(self::HEADER_ALIASES[$lower])) {
            return self::HEADER_ALIASES[$lower];
        }

        $slug = self::toAsciiSlug($clean);
        if (isset(self::HEADER_ALIASES[$slug])) {
            return self::HEADER_ALIASES[$slug];
        }

        return $clean;
    }

    /**
     * Kiểm tra xem một dòng dữ liệu có chứa các cột đặc trưng của Master Listing hay không.
     *
     * @param array<string, mixed> $row
     */
    public static function hasMasterColumns(array $row): bool {
        $master_signals = [
            'drv_factory_code',
            'drv_source_album',
            'compound_water_thickness',
            'compound_pei_rectified',
            'compound_faces_shade',
            'in_showroom_raw',
            'drv_google_photos_url',
            'ai_min_room_m2',
            'ai_why_buy_one_liner',
            'ai_rag_summary_chunk',
            'attr_loai_gach',
        ];

        foreach (array_keys($row) as $raw_key) {
            $norm = self::normalizeHeaderKey((string) $raw_key);
            if (in_array($norm, $master_signals, true) || str_starts_with((string) $raw_key, 'meta:') || str_starts_with((string) $raw_key, 'attribute:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bóc tách toàn diện 1 dòng dữ liệu Master Listing (48 cột hoặc CSV kết hợp).
     *
     * @param array<string, mixed> $raw_row
     * @return array{
     *     is_valid_sku: bool,
     *     sku: string,
     *     post_title: string,
     *     post_name: string,
     *     post_excerpt: string,
     *     primary_cat_slug: string,
     *     images: array<int, string>,
     *     meta: array<string, mixed>,
     *     taxonomies: array<string, array<int, string>>
     * }
     */
    public static function parseRow(array $raw_row): array {
        $norm = [];
        foreach ($raw_row as $k => $v) {
            $nk = self::normalizeHeaderKey((string) $k);
            if ($nk === '' || str_starts_with($nk, '_ignore_')) {
                continue;
            }
            $val_str = is_scalar($v) ? trim((string) $v) : '';
            if (!isset($norm[$nk]) || $norm[$nk] === '') {
                $norm[$nk] = $val_str;
            }
        }

        $sku = strtoupper(trim($norm['sku'] ?? ''));
        $sku_slug = self::toAsciiSlug($sku);
        $is_valid = $sku !== ''
            && !in_array($sku_slug, ['sku', 'ma-sku'], true)
            && !str_starts_with($sku_slug, 'tong-')
            && !str_starts_with($sku_slug, 'nhom-')
            && !str_starts_with($sku_slug, 'bang-master');

        if (!$is_valid) {
            return [
                'is_valid_sku'     => false,
                'sku'              => '',
                'post_title'       => '',
                'post_name'        => '',
                'post_excerpt'     => '',
                'primary_cat_slug' => '',
                'images'           => [],
                'meta'             => [],
                'taxonomies'       => [],
            ];
        }

        // 1. Kích thước, Quy cách & Giá
        $w_mm       = self::parseInteger($norm['drv_piece_w_mm'] ?? '');
        $h_mm       = self::parseInteger($norm['drv_piece_h_mm'] ?? '');
        $pcs_box    = max(1, self::parseInteger($norm['drv_pieces_per_box'] ?? '1'));
        $kg_box     = self::parseFloat($norm['drv_kg_per_box'] ?? '');
        $price_m2   = self::parseInteger($norm['drv_price_m2'] ?? '');
        $sale_m2    = self::parseInteger($norm['drv_sale_price_m2'] ?? '');
        $line       = in_array($norm['drv_line'] ?? '', ['gach', 'phu_kien'], true) ? (string) $norm['drv_line'] : 'gach';
        $unit_mode  = trim($norm['drv_pricing_model'] ?? '');
        if (!in_array($unit_mode, ['area_pack', 'piece', 'set', 'pack'], true)) {
            $unit_mode = ($line === 'gach') ? 'area_pack' : 'piece';
        }

        // 2. Bóc tách 3 cột kỹ thuật gộp chuỗi (hoặc đọc từ cột đơn nguyên nếu có)
        $water_thick = self::parseWaterAndThickness($norm['compound_water_thickness'] ?? '');
        $water_abs   = ($norm['drv_water_absorption'] ?? '') !== ''
            ? str_replace(',', '.', trim((string) $norm['drv_water_absorption']))
            : $water_thick['water_absorption'];
        $thick_mm    = ($norm['drv_thickness_mm'] ?? '') !== ''
            ? self::parseFloat((string) $norm['drv_thickness_mm'])
            : $water_thick['thickness_mm'];

        $pei_rect    = self::parsePeiAndRectified($norm['compound_pei_rectified'] ?? '');
        $pei         = ($norm['drv_pei'] ?? '') !== '' ? trim((string) $norm['drv_pei']) : $pei_rect['pei'];
        $rectified   = ($norm['drv_rectified'] ?? '') !== ''
            ? (in_array(strtolower((string) $norm['drv_rectified']), ['1', 'yes', 'true'], true) ? '1' : '0')
            : $pei_rect['rectified'];
        $breaking_n  = $pei_rect['breaking_strength_n'];

        $faces_shade = self::parseFacesAndShade($norm['compound_faces_shade'] ?? '');
        $faces       = ($norm['drv_faces'] ?? '') !== '' ? self::parseInteger((string) $norm['drv_faces']) : $faces_shade['faces'];
        $shade       = ($norm['drv_shade'] ?? '') !== '' ? trim((string) $norm['drv_shade']) : $faces_shade['shade'];

        // Showroom shelf / trạng thái có mẫu tại kệ
        $shelf = trim($norm['drv_showroom_shelf'] ?? '');
        if ($shelf === '' && ($norm['in_showroom_raw'] ?? '') !== '') {
            $showroom_slug = self::toAsciiSlug((string) $norm['in_showroom_raw']);
            if ($showroom_slug !== '' && !str_contains($showroom_slug, 'khong') && $showroom_slug !== '0' && $showroom_slug !== 'no') {
                $shelf = 'Showroom';
            }
        }

        // Google Photos URL (hỗ trợ cả HYPERLINK formula & Markdown link)
        $gphotos_url = self::extractUrl($norm['drv_google_photos_url'] ?? '');

        // 3. Phân giải Danh mục chính & 14 Taxonomy `pa_*`
        $primary_raw      = trim($norm['primary_cat_raw'] ?? '');
        $primary_cat_slug = self::resolvePrimaryCatSlug($primary_raw);

        $taxonomies = [
            'pa_cong-nang'  => self::resolveTaxonomySlugs('pa_cong-nang', $norm['attr_cong_nang'] ?? ''),
            'pa_khong-gian' => self::resolveTaxonomySlugs('pa_khong-gian', $norm['attr_khong_gian'] ?? ''),
            'pa_van-gach'   => self::resolveTaxonomySlugs('pa_van-gach', $norm['attr_van_gach'] ?? ''),
            'pa_chat-lieu'  => self::resolveTaxonomySlugs('pa_chat-lieu', $norm['attr_chat_lieu'] ?? ''),
            'pa_be-mat'     => self::resolveTaxonomySlugs('pa_be-mat', $norm['attr_be_mat'] ?? ''),
            'pa_mau-sac'    => self::resolveTaxonomySlugs('pa_mau-sac', $norm['attr_mau_sac'] ?? ''),
            'pa_phong-cach' => self::resolveTaxonomySlugs('pa_phong-cach', $norm['attr_phong_cach'] ?? ''),
            'pa_hinh-dang'  => self::resolveTaxonomySlugs('pa_hinh-dang', $norm['attr_hinh_dang'] ?? ''),
            'pa_xuat-xu'    => self::resolveTaxonomySlugs('pa_xuat-xu', $norm['attr_xuat_xu'] ?? ''),
            'pa_bo-suu-tap' => self::resolveTaxonomySlugs('pa_bo-suu-tap', $norm['attr_bo_suu_tap'] ?? ''),
            'pa_dcof'       => self::resolveDcofSlugs($norm['attr_dcof'] ?? ''),
        ];

        // Cầu nối `pa_loai-gach` / `Danh mục chính = Đá nung kết`: đảm bảo khổ vuông lớn 120x120 khớp `/da-nung-ket`.
        $loai_gach_slug = self::toAsciiSlug($norm['attr_loai_gach'] ?? '');
        if (str_contains($loai_gach_slug, 'da-nung-ket') || $primary_cat_slug === 'da-nung-ket') {
            if (!in_array('da-nung-ket', $taxonomies['pa_chat-lieu'], true)) {
                $taxonomies['pa_chat-lieu'][] = 'da-nung-ket';
            }
        }

        $size_slug = AttributeDeriver::deriveSizeSlug($w_mm, $h_mm);
        if ($size_slug !== '') {
            $taxonomies['pa_kich-thuoc'] = [$size_slug];
        }

        // Lọc bỏ các taxonomy rỗng
        $taxonomies = array_filter($taxonomies, static fn(array $slugs): bool => $slugs !== []);

        // 4. Suy dẫn Tên sản phẩm SEO & Slug URL chuẩn nếu trong `.xlsx` là công thức chưa cache giá trị
        $factory_code   = trim($norm['drv_factory_code'] ?? '');
        $source_album   = trim($norm['drv_source_album'] ?? '');
        $pattern_detail = trim($norm['drv_color_name'] ?? '');
        $size_display   = self::stripParentheses($norm['attr_kich_thuoc_raw'] ?? '');
        if ($size_display === '' && $w_mm > 0 && $h_mm > 0) {
            $size_display = AttributeDeriver::deriveSizeShortLabel($w_mm, $h_mm);
        }
        $surface_short  = self::stripParentheses($norm['attr_be_mat'] ?? '');

        $post_title = trim($norm['post_title'] ?? '');
        if ($post_title === '') {
            $title_parts = array_filter([
                $primary_raw !== '' ? $primary_raw : 'Gạch ốp lát',
                'Dravit',
                trim($norm['attr_van_gach'] ?? ''),
                $pattern_detail,
                $size_display,
                $surface_short,
                $factory_code,
            ], static fn(string $p): bool => $p !== '');
            $post_title = implode(' ', $title_parts);
        }

        $post_name = self::normalizeProductSlug($norm['post_name'] ?? '');
        if ($post_name === '') {
            $slug_size = $size_slug !== '' ? $size_slug : self::toAsciiSlug($size_display);
            $post_name = 'gach-dravit-' . ($slug_size !== '' ? $slug_size . '-' : '') . strtolower($sku);
            $post_name = self::toAsciiSlug($post_name);
        }

        $post_excerpt = trim($norm['post_excerpt'] ?? '');

        // 5. Bóc tách Nhóm 7: Hồ sơ AI Tư vấn & Chuỗi RAG (Cột 37 -> 48)
        $min_room_m2 = self::parseFloat($norm['ai_min_room_m2'] ?? '');
        $max_room_m2 = self::parseFloat($norm['ai_max_room_m2'] ?? '');
        $wastage_pct = self::parseWastagePercent($norm['ai_wastage_pct'] ?? ($norm['drv_waste_rate'] ?? ''));

        $install_req = trim($norm['ai_install_requirement'] ?? '');
        $pairing_sug = trim($norm['ai_pairing_suggestion'] ?? '');
        $why_buy     = trim($norm['ai_why_buy_one_liner'] ?? '');
        $lighting    = trim($norm['ai_lighting_effect'] ?? '');
        $not_rec     = trim($norm['ai_not_recommended_for'] ?? '');
        $undertone   = trim($norm['ai_color_undertone'] ?? '');

        // Tự động bóc tách độ rộng ron & màu ron từ ghi chú thi công / phối cặp nếu cột lẻ để trống
        $grout_mm = ($norm['drv_grout_mm'] ?? '') !== ''
            ? self::parseFloat((string) $norm['drv_grout_mm'])
            : self::extractGroutMm($install_req);
        $grout_color = trim($norm['drv_grout_color'] ?? '');
        if ($grout_color === '' && $pairing_sug !== '') {
            $grout_color = self::extractGroutColor($pairing_sug);
        }

        $special_tags = [];
        if (($norm['ai_special_needs_tags'] ?? '') !== '') {
            foreach (explode(',', (string) $norm['ai_special_needs_tags']) as $tag) {
                $t_slug = self::toAsciiSlug($tag);
                if ($t_slug !== '') {
                    $special_tags[] = $t_slug;
                }
            }
            $special_tags = array_values(array_unique($special_tags));
        }

        $budget_tier = trim($norm['ai_budget_tier'] ?? '');
        if ($budget_tier === '' && $price_m2 > 0) {
            $budget_tier = self::deriveBudgetTierLabel($price_m2);
        }

        $rag_chunk = trim($norm['ai_rag_summary_chunk'] ?? '');
        if ($rag_chunk === '') {
            $rag_chunk = self::buildRagSummaryChunk([
                'sku'            => $sku,
                'post_title'     => $post_title,
                'post_name'      => $post_name,
                'price_m2'       => $price_m2,
                'w_mm'           => $w_mm,
                'h_mm'           => $h_mm,
                'pcs_box'        => $pcs_box,
                'kg_box'         => $kg_box,
                'wastage_pct'    => $wastage_pct,
                'size_raw'       => $norm['attr_kich_thuoc_raw'] ?? $size_display,
                'spaces_raw'     => $norm['attr_khong_gian'] ?? '',
                'min_room_m2'    => $min_room_m2,
                'max_room_m2'    => $max_room_m2,
                'not_rec'        => $not_rec,
                'color_raw'      => $norm['attr_mau_sac'] ?? '',
                'undertone'      => $undertone,
                'van_raw'        => $norm['attr_van_gach'] ?? '',
                'pattern_detail' => $pattern_detail,
                'surface_raw'    => $norm['attr_be_mat'] ?? '',
                'lighting'       => $lighting,
                'style_raw'      => $norm['attr_phong_cach'] ?? '',
                'material_raw'   => $norm['attr_chat_lieu'] ?? '',
                'dcof_raw'       => $norm['attr_dcof'] ?? '',
                'water_thick'    => $norm['compound_water_thickness'] ?? '',
                'pei_rect'       => $norm['compound_pei_rectified'] ?? '',
                'faces_shade'    => $norm['compound_faces_shade'] ?? '',
                'special_tags'   => $special_tags,
                'why_buy'        => $why_buy,
                'pairing_sug'    => $pairing_sug,
                'install_req'    => $install_req,
                'showroom_raw'   => $norm['in_showroom_raw'] ?? ($shelf !== '' ? 'Có mẫu tại Showroom' : ''),
                'budget_tier'    => $budget_tier,
            ]);
        }

        $ai_blurb = trim($norm['drv_ai_blurb'] ?? '');
        if ($ai_blurb === '' && $why_buy !== '') {
            $ai_blurb = function_exists('mb_substr') ? mb_substr($why_buy, 0, 200) : substr($why_buy, 0, 200);
        }

        $series = trim($norm['drv_series'] ?? '');
        if ($series === '' && $factory_code !== '') {
            // Ví dụ: `VA6120B-01` -> nhóm series cơ sở `va6120` hoặc mã gốc chuẩn hóa.
            $series = self::deriveSeriesSlug($factory_code);
        }

        // Đóng gói hồ sơ AI có cấu trúc cho Step 4 `SnapshotBuilder` & `RuleOnlyDriver`
        $ai_profile = [
            'min_room_m2'         => $min_room_m2,
            'max_room_m2'         => $max_room_m2,
            'wastage_pct'         => $wastage_pct,
            'not_recommended_for' => $not_rec,
            'install_requirement' => $install_req,
            'color_undertone'     => $undertone,
            'lighting_effect'     => $lighting,
            'pairing_suggestion'  => $pairing_sug,
            'special_needs_tags'  => $special_tags,
            'budget_tier'         => $budget_tier,
            'why_buy_one_liner'   => $why_buy,
            'rag_summary_chunk'   => $rag_chunk,
        ];

        $buy_units = ['m2', 'thung'];
        if (($norm['drv_buy_units'] ?? '') !== '') {
            $parsed_units = array_values(array_filter(array_map('trim', explode('|', (string) $norm['drv_buy_units']))));
            if ($parsed_units !== []) {
                $buy_units = $parsed_units;
            }
        }

        $meta = [
            'drv_line'               => $line,
            'drv_pricing_model'      => $unit_mode,
            'drv_price_m2'           => (string) max(0, $price_m2),
            'drv_sale_price_m2'      => (string) max(0, $sale_m2),
            'drv_piece_w_mm'         => (string) max(0, $w_mm),
            'drv_piece_h_mm'         => (string) max(0, $h_mm),
            'drv_thickness_mm'       => $thick_mm > 0.0 ? (string) $thick_mm : '',
            'drv_pieces_per_box'     => (string) $pcs_box,
            'drv_kg_per_box'         => $kg_box > 0.0 ? (string) $kg_box : '',
            'drv_unit_label'         => in_array($norm['drv_unit_label'] ?? '', ['viên', 'tấm'], true) ? (string) $norm['drv_unit_label'] : 'viên',
            'drv_buy_units'          => $buy_units,
            'drv_default_buy_unit'   => in_array($norm['drv_default_buy_unit'] ?? '', ['m2', 'thung', 'vien'], true) ? (string) $norm['drv_default_buy_unit'] : 'm2',
            'drv_round_mode'         => in_array($norm['drv_round_mode'] ?? '', ['box', 'piece'], true) ? (string) $norm['drv_round_mode'] : 'box',
            'drv_qty_min'            => (string) max($pcs_box, self::parseInteger($norm['drv_qty_min'] ?? '')),
            'drv_waste_rate'         => $wastage_pct > 0.0 ? (string) $wastage_pct : '',
            'drv_water_absorption'   => $water_abs,
            'drv_pei'                => $pei,
            'drv_breaking_strength'  => $breaking_n > 0 ? (string) $breaking_n : '',
            'drv_rectified'          => $rectified,
            'drv_faces'              => $faces > 0 ? (string) $faces : '',
            'drv_shade'              => $shade,
            'drv_bookmatch'          => $faces_shade['bookmatch'] ? '1' : '0',
            'drv_grout_mm'           => $grout_mm > 0.0 ? (string) $grout_mm : '',
            'drv_grout_color'        => $grout_color,
            'drv_factory_code'       => $factory_code,
            'drv_source_album'       => $source_album,
            'drv_color_name'         => $pattern_detail,
            'drv_series'             => $series,
            'drv_showroom_shelf'     => $shelf,
            'drv_google_photos_url'  => $gphotos_url,
            'drv_lighting_note'      => $lighting,
            'drv_card_label'         => trim($norm['drv_card_label'] ?? ''),
            'drv_ai_blurb'           => $ai_blurb,
            'drv_ai_profile'         => $ai_profile,
        ];

        $images = [];
        if (($norm['images_raw'] ?? '') !== '') {
            foreach (explode(',', (string) $norm['images_raw']) as $img_item) {
                $u = trim($img_item);
                if ($u !== '') {
                    $images[] = $u;
                }
            }
        }

        return [
            'is_valid_sku'     => true,
            'sku'              => $sku,
            'post_title'       => $post_title,
            'post_name'        => $post_name,
            'post_excerpt'     => $post_excerpt,
            'primary_cat_slug' => $primary_cat_slug,
            'images'           => $images,
            'meta'             => $meta,
            'taxonomies'       => $taxonomies,
        ];
    }

    /**
     * Tách cột gộp Độ hút nước & Độ dày (VD: `"≤ 0,5% · Dày 9,5mm"` -> `['water_absorption' => '0.5', 'thickness_mm' => 9.5]`).
     *
     * @return array{water_absorption: string, thickness_mm: float}
     */
    public static function parseWaterAndThickness(string $raw): array {
        $water = '';
        $thick = 0.0;
        $clean = trim($raw);
        if ($clean === '') {
            return ['water_absorption' => '', 'thickness_mm' => 0.0];
        }

        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*%/u', $clean, $m) === 1) {
            $water = str_replace(',', '.', $m[1]);
        }

        if (preg_match('/(?:dày|day)?\s*([0-9]+(?:[.,][0-9]+)?)\s*mm/iu', $clean, $m2) === 1) {
            $thick = (float) str_replace(',', '.', $m2[1]);
        }

        return [
            'water_absorption' => $water,
            'thickness_mm'     => max(0.0, $thick),
        ];
    }

    /**
     * Tách cột gộp Độ mài mòn PEI, Lực bẻ gãy N & Mài cạnh (VD: `"PEI IV ( ≥ 1300N ) · Mài cạnh (Rectified)"`).
     *
     * @return array{pei: string, breaking_strength_n: int, rectified: string}
     */
    public static function parsePeiAndRectified(string $raw): array {
        $clean = trim($raw);
        if ($clean === '') {
            return ['pei' => '', 'breaking_strength_n' => 0, 'rectified' => '0'];
        }

        $pei = '';
        if (preg_match('/\bPEI\s*(V|IV|III|II|I|[1-5])\b/iu', $clean, $m) === 1) {
            $token = strtoupper($m[1]);
            $roman_map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V'];
            $roman = $roman_map[$token] ?? $token;
            $pei   = 'PEI ' . $roman;
        }

        $breaking_n = 0;
        if (preg_match('/([0-9]{3,5})\s*N\b/iu', $clean, $m_n) === 1) {
            $breaking_n = (int) $m_n[1];
        }

        $slug = self::toAsciiSlug($clean);
        $rectified = '0';
        if ((str_contains($slug, 'mai-canh') || str_contains($slug, 'rectified'))
            && !str_contains($slug, 'khong-mai')
            && !str_contains($slug, 'non-rectified')
        ) {
            $rectified = '1';
        }

        return [
            'pei'                 => $pei,
            'breaking_strength_n' => $breaking_n,
            'rectified'           => $rectified,
        ];
    }

    /**
     * Tách cột gộp Số mặt vân & Độ biến thiên sắc độ (VD: `"6 Faces · V2 (Biến thiên nhẹ)"`, `"4 Faces Liên Vân (Bookmatch)"`).
     *
     * @return array{faces: int, shade: string, bookmatch: bool}
     */
    public static function parseFacesAndShade(string $raw): array {
        $clean = trim($raw);
        if ($clean === '') {
            return ['faces' => 0, 'shade' => '', 'bookmatch' => false];
        }

        $faces = 0;
        if (preg_match('/([0-9]+)\s*faces?/iu', $clean, $m) === 1) {
            $faces = (int) $m[1];
        }

        $shade = '';
        if (preg_match('/\b(V[1-4])\b/iu', $clean, $m_v) === 1) {
            $shade = strtoupper($m_v[1]);
        }

        $slug = self::toAsciiSlug($clean);
        $bookmatch = str_contains($slug, 'bookmatch') || str_contains($slug, 'lien-van');

        return [
            'faces'     => max(0, $faces),
            'shade'     => $shade,
            'bookmatch' => $bookmatch,
        ];
    }

    /**
     * Quy chiếu chuỗi đa giá trị sang mảng canonical term slugs của taxonomy `pa_*`.
     *
     * @return array<int, string>
     */
    public static function resolveTaxonomySlugs(string $taxonomy, string $raw): array {
        $clean = trim($raw);
        if ($clean === '') {
            return [];
        }

        $map    = self::TAXONOMY_SYNONYMS[$taxonomy] ?? [];
        $tokens = self::splitMultiValue($clean);
        $result = [];

        foreach ($tokens as $token) {
            $full_slug  = self::toAsciiSlug($token);
            $short_text = self::stripParentheses($token);
            $short_slug = self::toAsciiSlug($short_text);

            $matched = '';
            if ($short_slug !== '' && isset($map[$short_slug])) {
                $matched = $map[$short_slug];
            } elseif ($full_slug !== '' && isset($map[$full_slug])) {
                $matched = $map[$full_slug];
            } elseif ($taxonomy === 'pa_bo-suu-tap' && $short_slug !== '') {
                $matched = $short_slug;
            } else {
                // Thử tách dấu `/` (VD: `"Xương đá Porcelain / Khổ lớn"` hoặc `"Nhập khẩu / Cao cấp"`)
                foreach (explode('/', $short_text) as $sub) {
                    $sub_slug = self::toAsciiSlug($sub);
                    if ($sub_slug !== '' && isset($map[$sub_slug])) {
                        $matched = $map[$sub_slug];
                        break;
                    }
                }
            }

            // Thử đối chiếu nội dung bên trong ngoặc đơn (VD: `"Bắc Âu (Japandi)"` -> `japandi`)
            if ($matched === '' && preg_match('/\(([^)]+)\)/u', $token, $m_paren) === 1) {
                $in_slug = self::toAsciiSlug($m_paren[1]);
                if ($in_slug !== '' && isset($map[$in_slug])) {
                    $matched = $map[$in_slug];
                }
            }

            // Dự phòng khớp cụm từ khóa theo ranh giới từ dài nhất trước (VD: `"Đá chẻ Slate"` -> `van-da-bazan`)
            if ($matched === '' && $short_slug !== '' && $map !== []) {
                $syn_keys = array_keys($map);
                usort($syn_keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
                foreach ($syn_keys as $syn_key) {
                    if (strlen($syn_key) < 3) {
                        continue;
                    }
                    if (preg_match('/(?:^|-)' . preg_quote($syn_key, '/') . '(?:-|$)/', $short_slug) === 1) {
                        $matched = $map[$syn_key];
                        break;
                    }
                }
            }

            if ($matched !== '' && !in_array($matched, $result, true)) {
                $result[] = $matched;
            }
        }

        return $result;
    }

    /**
     * Bóc tách chỉ số chống trượt `R9..R13` cho taxonomy `pa_dcof`.
     *
     * @return array<int, string>
     */
    public static function resolveDcofSlugs(string $raw): array {
        if (trim($raw) === '') {
            return [];
        }

        if (preg_match_all('/\b(r9|r10|r11|r12|r13)\b/i', $raw, $matches) >= 1) {
            return array_values(array_unique(array_map('strtolower', $matches[1])));
        }

        return [];
    }

    /**
     * Quy chiếu tên Danh mục chính về slug trong 28 Smart Collections.
     */
    public static function resolvePrimaryCatSlug(string $raw): string {
        $slug = self::toAsciiSlug(self::stripParentheses($raw));
        return self::PRIMARY_CAT_MAP[$slug] ?? '';
    }

    /**
     * Làm sạch URL/slug sản phẩm (cắt domain và tiền tố `/san-pham/`).
     */
    public static function normalizeProductSlug(string $raw): string {
        $clean = trim($raw);
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('#^https?://[^/]+#i', '', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B/");
        if (str_starts_with($clean, 'san-pham/')) {
            $clean = substr($clean, strlen('san-pham/'));
        }

        return self::toAsciiSlug($clean);
    }

    /**
     * Trích xuất URL thực tế từ chuỗi thường, công thức Excel `HYPERLINK("url", ...)` hoặc Markdown `[...](url)`.
     */
    public static function extractUrl(string $raw): string {
        $clean = trim($raw);
        if ($clean === '') {
            return '';
        }

        if (preg_match('/https?:\/\/[^\s"\'\)\]]+/i', $clean, $m) === 1) {
            return $m[0];
        }

        return '';
    }

    /**
     * Chuyển đổi chuỗi Tiếng Việt có dấu sang slug ASCII không dấu (chạy độc lập không cần WP).
     */
    public static function toAsciiSlug(string $text): string {
        $str = trim($text);
        if ($str === '') {
            return '';
        }

        $vn_map = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
            'À' => 'a', 'Á' => 'a', 'Ạ' => 'a', 'Ả' => 'a', 'Ã' => 'a',
            'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ậ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a',
            'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ặ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a',
            'È' => 'e', 'É' => 'e', 'Ẹ' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e',
            'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ệ' => 'e', 'Ể' => 'e', 'Ễ' => 'e',
            'Ì' => 'i', 'Í' => 'i', 'Ị' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i',
            'Ò' => 'o', 'Ó' => 'o', 'Ọ' => 'o', 'Ỏ' => 'o', 'Õ' => 'o',
            'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ộ' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o',
            'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ợ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o',
            'Ù' => 'u', 'Ú' => 'u', 'Ụ' => 'u', 'Ủ' => 'u', 'Ũ' => 'u',
            'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ự' => 'u', 'Ử' => 'u', 'Ữ' => 'u',
            'Ỳ' => 'y', 'Ý' => 'y', 'Ỵ' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y',
            'Đ' => 'd',
            '²' => '2',
        ];

        $ascii = strtr($str, $vn_map);
        $ascii = strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? '';

        return trim($ascii, '-');
    }

    private static function parseInteger(string $raw): int {
        $clean = trim($raw);
        if ($clean === '') {
            return 0;
        }
        // Xử lý dấu chấm phân cách hàng nghìn chuẩn Việt Nam (`1.200` -> `1200`, `420.000` -> `420000`)
        if (preg_match('/^[0-9]{1,3}(\.[0-9]{3})+$/', $clean) === 1) {
            return (int) str_replace('.', '', $clean);
        }
        if (preg_match('/^[0-9]{1,3}(,[0-9]{3})+$/', $clean) === 1) {
            return (int) str_replace(',', '', $clean);
        }
        // Trường hợp số thực dạng chuỗi từ Excel XML (`1200.0`)
        if (is_numeric($clean)) {
            return (int) round((float) $clean);
        }

        $digits = preg_replace('/[^0-9]/', '', $clean) ?? '';
        return $digits !== '' ? (int) $digits : 0;
    }

    private static function parseFloat(string $raw): float {
        $clean = trim($raw);
        if ($clean === '') {
            return 0.0;
        }
        $normalized = str_replace(',', '.', $clean);
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $normalized, $m) === 1) {
            return (float) $m[1];
        }
        return 0.0;
    }

    private static function parseWastagePercent(string $raw): float {
        $clean = trim($raw);
        if ($clean === '') {
            return 0.0;
        }
        $has_pct = str_contains($clean, '%');
        $val     = self::parseFloat($clean);
        if (!$has_pct && $val > 0.0 && $val <= 1.0) {
            return round($val * 100, 2);
        }
        return round($val, 2);
    }

    private static function extractGroutMm(string $install_req): float {
        if ($install_req === '') {
            return 0.0;
        }
        if (preg_match('/(?:ke\s*cân\s*bằng|đường\s*ron|mạch\s*ron)[^0-9]*([0-9]+(?:[.,][0-9]+)?)\s*(?:-[0-9.,]+\s*)?mm/iu', $install_req, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return 0.0;
    }

    private static function extractGroutColor(string $pairing_sug): string {
        if (preg_match('/ron\s*:\s*([^\r\n|]+)/iu', $pairing_sug, $m) === 1) {
            $color = trim($m[1]);
            return function_exists('mb_substr') ? mb_substr($color, 0, 60) : substr($color, 0, 60);
        }
        return '';
    }

    private static function deriveSeriesSlug(string $factory_code): string {
        $clean = preg_replace('/-[0-9]+$/', '', trim($factory_code)) ?? $factory_code;
        return self::toAsciiSlug($clean);
    }

    private static function deriveBudgetTierLabel(int $price_m2): string {
        if ($price_m2 < 320000) {
            return '1. Tiết kiệm (<320k/m²)';
        }
        if ($price_m2 <= 550000) {
            return '2. Phổ thông - Tối ưu (320k-550k/m²)';
        }
        if ($price_m2 <= 850000) {
            return '3. Cao cấp (550k-850k/m²)';
        }
        return '4. Thượng hạng - Big Slab (>850k/m²)';
    }

    /**
     * Tự động ghép chuỗi RAG Summary Chunk chuẩn 100% công thức cột 48 khi đọc từ `.xlsx` chưa cache formula.
     *
     * @param array<string, mixed> $ctx
     */
    private static function buildRagSummaryChunk(array $ctx): string {
        $w_mm       = (int) ($ctx['w_mm'] ?? 0);
        $h_mm       = (int) ($ctx['h_mm'] ?? 0);
        $pcs_box    = max(1, (int) ($ctx['pcs_box'] ?? 1));
        $price_m2   = (int) ($ctx['price_m2'] ?? 0);
        $area_piece = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_box     = round($area_piece * $pcs_box, 4);
        $price_pc   = $price_m2 > 0 ? Calc::pricePerPiece($price_m2, $area_piece) : 0;

        $price_part = $price_m2 > 0
            ? sprintf('%sđ/m² (%sđ/viên) - Phân khúc: %s', number_format($price_m2), number_format($price_pc), (string) $ctx['budget_tier'])
            : 'Liên hệ báo giá';

        $tags_str = is_array($ctx['special_tags']) ? implode(', ', $ctx['special_tags']) : '';

        return sprintf(
            '[SKU: %s] Tên: %s | URL: https://gachdravit.vn/san-pham/%s | Giá: %s | Quy cách: Kích thước %s (%dx%dmm, %s m²/viên), 1 thùng = %d viên = %s m² (nặng %s kg), hao hụt khuyến nghị: %s%% | Không gian phù hợp: %s (Diện tích phòng lý tưởng: từ %sm² đến %sm²) | CẢNH BÁO KHÔNG DÙNG CHO: %s | Màu sắc & Ánh sáng: Màu %s (%s), %s (%s), bề mặt %s (%s), phong cách %s | Thông số kỹ thuật: %s, chống trượt %s, hút nước & độ dày: %s, chịu lực: %s, số mặt vân: %s | Đáp ứng yêu cầu đặc biệt: %s | Lý do khuyên chọn: %s | Gợi ý phối cặp & màu ron: %s | Yêu cầu thi công: %s | Trạng thái mẫu: %s',
            (string) $ctx['sku'],
            (string) $ctx['post_title'],
            (string) $ctx['post_name'],
            $price_part,
            (string) $ctx['size_raw'],
            $h_mm,
            $w_mm,
            number_format($area_piece, 2, '.', ''),
            $pcs_box,
            number_format($m2_box, 2, '.', ''),
            rtrim(rtrim(number_format((float) $ctx['kg_box'], 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float) $ctx['wastage_pct'], 2, '.', ''), '0'), '.'),
            (string) $ctx['spaces_raw'],
            rtrim(rtrim(number_format((float) $ctx['min_room_m2'], 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format((float) $ctx['max_room_m2'], 2, '.', ''), '0'), '.'),
            (string) $ctx['not_rec'],
            (string) $ctx['color_raw'],
            (string) $ctx['undertone'],
            (string) $ctx['van_raw'],
            (string) $ctx['pattern_detail'],
            (string) $ctx['surface_raw'],
            (string) $ctx['lighting'],
            (string) $ctx['style_raw'],
            (string) $ctx['material_raw'],
            (string) $ctx['dcof_raw'],
            (string) $ctx['water_thick'],
            (string) $ctx['pei_rect'],
            (string) $ctx['faces_shade'],
            $tags_str,
            (string) $ctx['why_buy'],
            (string) $ctx['pairing_sug'],
            (string) $ctx['install_req'],
            (string) $ctx['showroom_raw']
        );
    }

    private static function stripParentheses(string $text): string {
        $stripped = preg_replace('/\s*\([^)]*\)/u', '', $text) ?? $text;
        return trim($stripped);
    }

    /**
     * Tách chuỗi theo dấu `,` hoặc `|` nằm bên ngoài cặp ngoặc đơn `(...)`.
     *
     * @return array<int, string>
     */
    private static function splitMultiValue(string $raw): array {
        $parts = [];
        $buf   = '';
        $depth = 0;
        $len   = strlen($raw);

        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch === '(') {
                $depth++;
                $buf .= $ch;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
                $buf  .= $ch;
            } elseif (($ch === ',' || $ch === '|') && $depth === 0) {
                $trimmed = trim($buf);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
                $buf = '';
            } else {
                $buf .= $ch;
            }
        }

        $trimmed = trim($buf);
        if ($trimmed !== '') {
            $parts[] = $trimmed;
        }

        return $parts;
    }
}
