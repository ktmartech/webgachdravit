<?php
/**
 * Tạo 2 SKU vật tư thi công mẫu cho khối "Vật tư thi công đề xuất" trong giỏ hàng (`drv_line = phu_kien`, mô hình `pack`).
 *
 * Chạy:  tools/wp-cli.ps1 eval-file tools/setup-accessories.php
 * Idempotent theo SKU; chỉ tạo khi chưa tồn tại. Sửa giá, định mức phủ và ảnh trong màn hình sửa sản phẩm.
 * (Dữ liệu mẫu: thay bằng thông số và giá thật của nhà cung cấp trước khi go-live.)
 */
declare(strict_types=1);

if (!defined('ABSPATH') || !function_exists('wc_get_product_id_by_sku')) {
    exit;
}

$defs = [
    [
        'sku'      => 'DRV-KEO-C2',
        'name'     => 'Keo dán gạch Dravit C2-Pro (bao 25kg)',
        'price'    => 195000,
        'weight'   => 25,
        'unit'     => 'bao',
        'pack'     => 'bao 25kg',
        'coverage' => 3.84,
        'note'     => 'Keo gốc xi măng cải tiến cấp C2 (ISO 13007) cho gạch Porcelain và đá nung kết khổ lớn.',
    ],
    [
        'sku'      => 'DRV-KE-15',
        'name'     => 'Bộ ke cân bằng phẳng mặt gạch 1.5mm (túi 100 bộ)',
        'price'    => 65000,
        'weight'   => 0.4,
        'unit'     => 'túi',
        'pack'     => 'túi 100 bộ',
        'coverage' => 30.0,
        'note'     => 'Ke cân bằng đường ron 1,5 mm giúp mặt sàn phẳng, không chênh mép (lippage).',
    ],
];

foreach ($defs as $d) {
    if (wc_get_product_id_by_sku($d['sku']) > 0) {
        echo "SKIPPED  {$d['sku']}\n";
        continue;
    }

    $p = new WC_Product_Simple();
    $p->set_name($d['name']);
    $p->set_sku($d['sku']);
    $p->set_status('publish');
    $p->set_catalog_visibility('hidden');
    $p->set_regular_price((string) $d['price']);
    $p->set_price((string) $d['price']);
    $p->set_weight((string) $d['weight']);
    $p->set_manage_stock(false);
    $p->set_stock_status('instock');
    $p->set_short_description($d['note']);
    $p->update_meta_data('drv_line', 'phu_kien');
    $p->update_meta_data('drv_pricing_model', 'pack');
    $p->update_meta_data('drv_unit_label', $d['unit']);
    $p->update_meta_data('drv_pack_unit_label', $d['pack']);
    $p->update_meta_data('drv_coverage_m2_per_unit', (string) $d['coverage']);
    $p->update_meta_data('drv_qty_min', '1');
    $id = $p->save();
    echo "CREATED  {$d['sku']} (#{$id})\n";
}
