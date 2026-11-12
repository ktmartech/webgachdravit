<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Catalog\MasterListingImporter;
use Dravit\Catalog\MasterListingParser;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Tích hợp bộ ánh xạ cột CSV chuẩn (`docs/14 §9.2`) VÀ Master Product Listing 48 cột
 * cho WooCommerce Product CSV Importer (`Sản phẩm -> Nhập`).
 */
final class CsvProductMapping implements ServiceProviderInterface {
    /** @var array<string, string> */
    private const COLUMN_MAP = [
        'drv_line'                 => 'Dravit: Phân hệ (gach/phu_kien)',
        'drv_pricing_model'        => 'Dravit: Mô hình giá (area_pack/piece/set/pack)',
        'drv_price_m2'             => 'Dravit: Giá niêm yết (đ/m²)',
        'drv_sale_price_m2'        => 'Dravit: Giá ưu đãi (đ/m²)',
        'drv_piece_w_mm'           => 'Dravit: Chiều rộng viên (mm)',
        'drv_piece_h_mm'           => 'Dravit: Chiều dài viên (mm)',
        'drv_thickness_mm'         => 'Dravit: Độ dày (mm)',
        'drv_pieces_per_box'       => 'Dravit: Số viên/thùng',
        'drv_kg_per_box'           => 'Dravit: Khối lượng thùng (kg)',
        'drv_unit_label'           => 'Dravit: Nhãn đơn vị (viên/tấm)',
        'drv_buy_units'            => 'Dravit: Đơn vị cho phép mua (m2|thung|vien)',
        'drv_default_buy_unit'     => 'Dravit: Đơn vị mua mặc định (m2/thung/vien)',
        'drv_round_mode'           => 'Dravit: Chế độ làm tròn (box/piece)',
        'drv_qty_min'              => 'Dravit: Số viên tối thiểu',
        'drv_waste_rate'           => 'Dravit: Tỷ lệ hao hụt khuyến nghị (%)',
        'drv_water_absorption'     => 'Dravit: Độ hút nước E (%)',
        'drv_pei'                  => 'Dravit: Cấp mài mòn PEI',
        'drv_rectified'            => 'Dravit: Mài cạnh phẳng (1/0)',
        'drv_faces'                => 'Dravit: Số mặt vân (Faces)',
        'drv_shade'                => 'Dravit: Độ biến thiên vân (V1-V4)',
        'drv_grout_mm'             => 'Dravit: Độ rộng ron (mm)',
        'drv_grout_color'          => 'Dravit: Màu ron gợi ý',
        'drv_series'               => 'Dravit: Tên dòng Series',
        'drv_color_name'           => 'Dravit: Tên mẫu vân / tông màu chi tiết',
        'drv_factory_code'         => 'Dravit: Mã mẫu gốc nhà máy',
        'drv_showroom_shelf'       => 'Dravit: Mã kệ Showroom',
        'drv_google_photos_url'    => 'Dravit: Link Album Google Photos',
        'drv_card_label'           => 'Dravit: Nhãn nổi bật Card',
        'drv_ai_blurb'             => 'Dravit: Mô tả ngắn AI',
        'drv_pack_unit_label'      => 'Dravit: Nhãn quy cách phụ kiện',
        'drv_coverage_m2_per_unit' => 'Dravit: Định mức phủ phụ kiện (m²)',
        'drv_joint_note'           => 'Dravit: Ghi chú thi công phụ kiện',
    ];

    public function register(): void {
        add_filter('woocommerce_csv_product_import_mapping_options', [$this, 'register_mapping_options']);
        add_filter('woocommerce_csv_product_import_mapping_default_columns', [$this, 'register_default_columns']);
        add_filter('woocommerce_product_import_pre_insert_product_object', [$this, 'hydrate_imported_meta'], 10, 2);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function register_mapping_options(array $options): array {
        foreach (self::COLUMN_MAP as $key => $label) {
            $options[$key] = $label;
        }
        foreach (MasterListingParser::wcCsvImporterColumns() as $m_key => $m_label) {
            $options[$m_key] = $m_label;
        }
        return $options;
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function register_default_columns(array $columns): array {
        foreach (self::COLUMN_MAP as $key => $label) {
            $columns[$key]   = $key;
            $columns[$label] = $key;
        }
        foreach (MasterListingParser::wcCsvImporterColumns() as $m_key => $m_label) {
            $columns[$m_key]   = $m_key;
            $columns[$m_label] = $m_key;
            $short_key         = (string) preg_replace('/^(meta|attribute):/', '', $m_key);
            if (!isset($columns[$short_key])) {
                $columns[$short_key] = $m_key;
            }
        }
        return $columns;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function hydrate_imported_meta(\WC_Product $product, array $data): \WC_Product {
        // Luồng 1: Tự động chuẩn hóa đầy đủ khi file CSV xuất từ bảng Master Listing 48 cột.
        $merged_data = $data;
        if (!isset($merged_data['sku']) || trim((string) $merged_data['sku']) === '') {
            $merged_data['sku'] = $product->get_sku();
        }

        if (MasterListingParser::hasMasterColumns($merged_data)) {
            $parsed = MasterListingParser::parseRow($merged_data);
            if ($parsed['is_valid_sku']) {
                if ($product->get_name() === '' && $parsed['post_title'] !== '') {
                    $product->set_name($parsed['post_title']);
                }
                if ($parsed['post_name'] !== '') {
                    $product->set_slug($parsed['post_name']);
                }
                if ($product->get_short_description() === '' && $parsed['post_excerpt'] !== '') {
                    $product->set_short_description($parsed['post_excerpt']);
                }

                foreach ($parsed['meta'] as $m_key => $m_val) {
                    if (is_array($m_val)) {
                        $product->update_meta_data($m_key, $m_val);
                    } elseif ((string) $m_val !== '') {
                        $product->update_meta_data($m_key, (string) $m_val);
                    }
                }

                if ($parsed['primary_cat_slug'] !== '' && taxonomy_exists('product_cat')) {
                    $prim_term = get_term_by('slug', $parsed['primary_cat_slug'], 'product_cat');
                    if ($prim_term instanceof \WP_Term) {
                        $product->update_meta_data('rank_math_primary_product_cat', (string) $prim_term->term_id);
                        $product->update_meta_data('drv_primary_auto', '0');
                    }
                }

                (new MasterListingImporter())->attachTaxonomyAttributes($product, $parsed['taxonomies']);
                return $product;
            }
        }

        // Luồng 2: Tương thích ngược 100% với mẫu CSV đơn cột (`docs/templates/import-gach-mau.csv`).
        foreach (array_keys(self::COLUMN_MAP) as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $raw = trim((string) $data[$key]);

            if ($key === 'drv_buy_units') {
                $parts = array_values(array_filter(array_map('trim', explode('|', $raw))));
                $product->update_meta_data($key, $parts !== [] ? $parts : ['m2', 'thung']);
                continue;
            }

            if (in_array($key, ['drv_price_m2', 'drv_sale_price_m2', 'drv_piece_w_mm', 'drv_piece_h_mm', 'drv_pieces_per_box', 'drv_qty_min', 'drv_faces'], true)) {
                $clean_num = (int) preg_replace('/[^0-9]/', '', $raw);
                $product->update_meta_data($key, (string) max(0, $clean_num));
                continue;
            }

            if (in_array($key, ['drv_thickness_mm', 'drv_kg_per_box', 'drv_grout_mm', 'drv_waste_rate', 'drv_coverage_m2_per_unit'], true)) {
                $product->update_meta_data($key, (string) max(0.0, (float) str_replace(',', '.', $raw)));
                continue;
            }

            if ($key === 'drv_google_photos_url') {
                $product->update_meta_data($key, esc_url_raw(MasterListingParser::extractUrl($raw)));
                continue;
            }

            $product->update_meta_data($key, sanitize_text_field($raw));
        }

        return $product;
    }
}
