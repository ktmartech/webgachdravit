<?php
/**
 * Kiểm chứng thực tế Gate 2 trên cơ sở dữ liệu WordPress + WooCommerce (chạy qua WP-CLI `eval-file`).
 */
declare(strict_types=1);

use Dravit\Catalog\ProductSeeder;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;

if (!defined('ABSPATH')) {
    exit("Chạy file này qua WP-CLI eval-file.\n");
}

$errors = [];
$assert = static function (bool $cond, string $msg) use (&$errors): void {
    if (!$cond) {
        $errors[] = $msg;
    }
};

// 1. Kiểm tra 14 thuộc tính pa_* và cờ public_archive
$attr_taxonomies = wc_get_attribute_taxonomies();
$assert(count($attr_taxonomies) >= 14, 'Phải có tối thiểu 14 thuộc tính pa_* toàn cục.');

$bo_suu_tap_obj = get_taxonomy('pa_bo-suu-tap');
$xuat_xu_obj    = get_taxonomy('pa_xuat-xu');
$cong_nang_obj  = get_taxonomy('pa_cong-nang');
$kich_thuoc_obj = get_taxonomy('pa_kich-thuoc');

$assert($bo_suu_tap_obj instanceof \WP_Taxonomy && $bo_suu_tap_obj->public === true, 'pa_bo-suu-tap phải public=true.');
$assert($xuat_xu_obj instanceof \WP_Taxonomy && $xuat_xu_obj->public === true, 'pa_xuat-xu phải public=true.');
$assert($cong_nang_obj instanceof \WP_Taxonomy && $cong_nang_obj->public === false, 'pa_cong-nang phải public=false.');
$assert($kich_thuoc_obj instanceof \WP_Taxonomy && $kich_thuoc_obj->public === false, 'pa_kich-thuoc phải public=false.');

// 2. Kiểm tra 2 taxonomy nội bộ
$assert(taxonomy_exists('drv_price_band'), 'drv_price_band phải tồn tại.');
$assert(taxonomy_exists('drv_flag'), 'drv_flag phải tồn tại.');
$assert(get_term_by('slug', 'gia-du-an', 'drv_price_band') instanceof \WP_Term, 'Thiếu term gia-du-an trong drv_price_band.');
$assert(get_term_by('slug', 'showroom', 'drv_flag') instanceof \WP_Term, 'Thiếu term showroom trong drv_flag.');

// 3. Kiểm tra 28 Smart Collections & danh mục mặc định chua-phan-loai
$collections_cfg = \Dravit\Core\Config::get('collections');
$assert(count($collections_cfg) === 28, 'Config phải có đúng 28 Smart Collections.');
foreach ($collections_cfg as $c_slug => $c_cfg) {
    $t = get_term_by('slug', (string) $c_slug, 'product_cat');
    $assert($t instanceof \WP_Term, "Thiếu product_cat Smart Collection: {$c_slug}");
}

$def_cat_id = (int) get_option('default_product_cat', 0);
if ($def_cat_id > 0) {
    $def_cat = get_term($def_cat_id, 'product_cat');
    $assert($def_cat instanceof \WP_Term && $def_cat->slug === 'chua-phan-loai', 'Danh mục mặc định phải đổi slug thành chua-phan-loai.');
}

// 4. Kiểm tra 10 SKU thực tế trong DB (8 SKU chuẩn nghiệm thu + 2 SKU quote_only)
foreach (ProductSeeder::definitions() as $def) {
    $sku = (string) $def['sku'];
    $pid = (int) wc_get_product_id_by_sku($sku);
    $assert($pid > 0, "Không tìm thấy SKU {$sku} trong DB.");
    if ($pid <= 0) {
        continue;
    }

    $product = wc_get_product($pid);
    $assert($product instanceof \WC_Product, "wc_get_product thất bại cho {$sku}.");
    if (!$product instanceof \WC_Product) {
        continue;
    }

    // Kiểm tra tập product_cat thực tế trong DB
    $assigned_slugs = wp_get_object_terms($pid, 'product_cat', ['fields' => 'slugs']);
    $assigned_slugs = is_array($assigned_slugs) ? array_values(array_map('strval', $assigned_slugs)) : [];
    $expected_slugs = $def['expected_collections'];
    sort($assigned_slugs);
    sort($expected_slugs);
    $assert(
        $assigned_slugs === $expected_slugs,
        sprintf('SKU %s sai product_cat trong DB. Expected=%s, Actual=%s', $sku, implode(',', $expected_slugs), implode(',', $assigned_slugs))
    );

    // Kiểm tra PrimaryCategory thực tế
    $primary_tid  = (int) get_post_meta($pid, 'rank_math_primary_product_cat', true);
    $primary_term = $primary_tid > 0 ? get_term($primary_tid, 'product_cat') : null;
    $actual_prim  = ($primary_term instanceof \WP_Term) ? $primary_term->slug : '';
    $assert(
        $actual_prim === $def['expected_primary'],
        sprintf('SKU %s sai PrimaryCategory trong DB. Expected=%s, Actual=%s', $sku, $def['expected_primary'], $actual_prim)
    );

    // Kiểm tra giá viên _regular_price & is_purchasable (D-20)
    $price_m2   = (int) $def['meta']['drv_price_m2'];
    $area_piece = Calc::pieceAreaM2((int) $def['meta']['drv_piece_w_mm'], (int) $def['meta']['drv_piece_h_mm']);
    if ($price_m2 > 0) {
        $expected_piece_price = Calc::pricePerPiece($price_m2, $area_piece);
        $assert(
            (int) $product->get_regular_price() === $expected_piece_price,
            sprintf('SKU %s sai _regular_price: Expected=%d, Actual=%s', $sku, $expected_piece_price, $product->get_regular_price())
        );
        $assert($product->is_purchasable() === true, "SKU {$sku} có giá phải is_purchasable = true.");
        $assert(PriceState::resolve($product) === PriceState::PRICED, "SKU {$sku} phải ở trạng thái priced.");
    } else {
        $assert($product->get_regular_price('edit') === '', "SKU quote_only {$sku} phải có _regular_price rỗng.");
        $assert($product->is_purchasable() === false, "SKU quote_only {$sku} bắt buộc is_purchasable = false.");
        $assert(PriceState::resolve($product) === PriceState::QUOTE_ONLY, "SKU {$sku} phải ở trạng thái quote_only.");
    }

    // Kiểm tra render thẻ sản phẩm thật không lỗi PHP và đúng class
    $card_html = drv_ui_html('product-card', ['product' => $product]);
    if ($price_m2 > 0) {
        $assert(str_contains($card_html, 'drv-card--priced'), "Card {$sku} thiếu class drv-card--priced.");
        $assert(!str_contains($card_html, 'drv-card--quote'), "Card {$sku} không được mang class drv-card--quote.");
        $assert(str_contains($card_html, 'data-drv-action="add-to-cart"'), "Card {$sku} phải có action add-to-cart.");
    } else {
        $assert(str_contains($card_html, 'drv-card--quote'), "Card quote {$sku} phải mang class drv-card--quote.");
        $assert(str_contains($card_html, 'Liên hệ báo giá'), "Card quote {$sku} phải hiển thị Liên hệ báo giá.");
        $assert(str_contains($card_html, 'data-drv-action="quote-add"'), "Card quote {$sku} phải có action quote-add.");
    }
}

// 5. Kiểm tra SlugGuard thực tế với wp_insert_post rồi dọn sạch ngay
$test_page_id = wp_insert_post([
    'post_title'  => 'Test Trùng Slug Gạch Lát Nền',
    'post_name'   => 'gach-lat-nen',
    'post_status' => 'draft',
    'post_type'   => 'page',
]);
if (is_int($test_page_id) && $test_page_id > 0) {
    $saved_post = get_post($test_page_id);
    $assert(
        $saved_post instanceof \WP_Post && $saved_post->post_name === 'gach-lat-nen-trang',
        'SlugGuard thực tế phải đổi page slug gach-lat-nen thành gach-lat-nen-trang.'
    );
    wp_delete_post($test_page_id, true);
}

if ($errors !== []) {
    fwrite(STDERR, "GATE 2 VERIFICATION FAILED:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "GATE 2 LIVE DB VERIFICATION PASSED 100% (14 pa_*, 28 Smart Collections, 10 SKUs, PriceState, SlugGuard).\n";

