<?php
/**
 * Idempotent Step 0 Environment & Plugin Configurator (chạy qua WP-CLI `eval-file`).
 * Tham chiếu: docs/21_Theme_Build_Execution_Roadmap.md (Step 0) & docs/06_SEO_GEO_Spec.md (§1-§2).
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit("Script này phải được chạy qua WP-CLI eval-file.\n");
}

// 1. Cấu hình cơ bản WordPress & Permalink (06 §1, 20 §1.1, D-21).
update_option('blogname', 'DRAVIT');
update_option('blogdescription', 'Thẩm mỹ từ những đường nét nhỏ nhất.');
update_option('timezone_string', 'Asia/Ho_Chi_Minh');
update_option('date_format', 'd/m/Y');
update_option('time_format', 'H:i');
update_option('permalink_structure', '/cam-nang/%postname%');
update_option('category_base', 'chu-de');
update_option('tag_base', 'the');

// Chuẩn hóa chuyên mục mặc định Uncategorized -> Kỹ thuật ốp lát (20 §1.2).
$default_cat_id = (int) get_option('default_category', 1);
if ($default_cat_id > 0) {
    wp_update_term($default_cat_id, 'category', [
        'name' => 'Kỹ thuật ốp lát',
        'slug' => 'ky-thuat-op-lat',
    ]);
}

/**
 * Helper tạo hoặc cập nhật trang hệ thống một cách idempotent.
 */
$ensure_page = static function (
    string $slug,
    string $title,
    string $content = '',
    string $template = 'default',
    int $existing_id = 0
): int {
    $page = $existing_id > 0 ? get_post($existing_id) : get_page_by_path($slug, OBJECT, 'page');

    $post_data = [
        'post_type'      => 'page',
        'post_title'     => $title,
        'post_name'      => $slug,
        'post_content'   => $content,
        'post_status'    => 'publish',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
    ];

    if ($page instanceof WP_Post) {
        $post_data['ID'] = $page->ID;
        $page_id         = (int) wp_update_post($post_data);
    } else {
        $page_id = (int) wp_insert_post($post_data);
    }

    if ($page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', $template);
    }

    return $page_id;
};

// 2. Tạo Trang chủ, Trang Cẩm nang & các Trang hệ thống/chính sách (06 §1, D-15).
$front_id = $ensure_page('trang-chu', 'Trang chủ');
$blog_id  = $ensure_page('cam-nang', 'Cẩm nang chọn gạch & ốp lát');

update_option('show_on_front', 'page');
update_option('page_on_front', $front_id);
update_option('page_for_posts', $blog_id);

$shop_id = $ensure_page(
    'san-pham',
    'Sản phẩm',
    '',
    'default',
    (int) get_option('woocommerce_shop_page_id', 0)
);
$cart_id = $ensure_page(
    'gio-hang',
    'Giỏ hàng',
    '[woocommerce_cart]',
    'default',
    (int) get_option('woocommerce_cart_page_id', 0)
);
$checkout_id = $ensure_page(
    'thanh-toan',
    'Thanh toán',
    '[woocommerce_checkout]',
    'default',
    (int) get_option('woocommerce_checkout_page_id', 0)
);
$account_id = $ensure_page(
    'tai-khoan',
    'Tài khoản',
    '[woocommerce_my_account]',
    'default',
    (int) get_option('woocommerce_myaccount_page_id', 0)
);

update_option('woocommerce_shop_page_id', $shop_id);
update_option('woocommerce_cart_page_id', $cart_id);
update_option('woocommerce_checkout_page_id', $checkout_id);
update_option('woocommerce_myaccount_page_id', $account_id);

$ensure_page('danh-sach-bao-gia', 'Danh sách Báo giá Công trình', '', 'page-templates/template-quote.php');
$ensure_page('tro-ly-ai-tim-gach', 'Trợ lý AI Tìm Gạch', '', 'page-templates/template-ai-finder.php');
$ensure_page('lien-he', 'Liên hệ & Hệ thống Showroom', '', 'page-templates/template-contact.php');
$ensure_page('gioi-thieu', 'Giới thiệu DRAVIT');

$policy_pages = [
    'dieu-khoan'             => 'Điều khoản sử dụng',
    'chinh-sach-van-chuyen'  => 'Chính sách vận chuyển & giao nhận',
    'chinh-sach-doi-tra'     => 'Chính sách đổi trả & bảo hành',
    'chinh-sach-thanh-toan'  => 'Chính sách thanh toán',
    'chinh-sach-bao-mat'     => 'Chính sách bảo mật thông tin',
];
foreach ($policy_pages as $slug => $title) {
    $ensure_page($slug, $title, '', 'page-templates/template-policy.php');
}

// Dọn trang mẫu mặc định của WP nếu còn tồn tại.
$sample = get_page_by_path('sample-page', OBJECT, 'page');
if ($sample instanceof WP_Post && $sample->post_status !== 'trash') {
    wp_trash_post($sample->ID);
}

// 3. Cấu hình WooCommerce: HPOS 100%, VND, VN-only, Permalink `/san-pham` & cổng COD/BACS (D-09, D-14, D-15, D-22).
update_option('woocommerce_currency', 'VND');
update_option('woocommerce_currency_pos', 'right');
update_option('woocommerce_price_thousand_sep', '.');
update_option('woocommerce_price_decimal_sep', ',');
update_option('woocommerce_price_num_decimals', 0);
update_option('woocommerce_default_country', 'VN');
update_option('woocommerce_allowed_countries', 'specific');
update_option('woocommerce_specific_allowed_countries', ['VN']);
update_option('woocommerce_ship_to_countries', '');
update_option('woocommerce_checkout_order_received_endpoint', 'dat-hang-thanh-cong');
update_option('woocommerce_custom_orders_table_enabled', 'yes');
update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
update_option('woocommerce_feature_order_attribution_enabled', 'no');

update_option('woocommerce_permalinks', [
    'product_base'           => '/san-pham',
    'category_base'          => 'danh-muc-gach',
    'tag_base'               => 'tu-khoa-gach',
    'attribute_base'         => '',
    'use_verbose_page_rules' => false,
]);

$bacs_settings            = (array) get_option('woocommerce_bacs_settings', []);
$bacs_settings['enabled'] = 'yes';
$bacs_settings['title']   = 'Chuyển khoản ngân hàng (VietQR)';
update_option('woocommerce_bacs_settings', $bacs_settings);

$cod_settings                       = (array) get_option('woocommerce_cod_settings', []);
$cod_settings['enabled']            = 'yes';
$cod_settings['title']              = 'Thanh toán khi nhận hàng (COD)';
$cod_settings['enable_for_virtual'] = 'no';
update_option('woocommerce_cod_settings', $cod_settings);

// 4. Cấu hình Rank Math Free theo đúng checklist docs/06_SEO_GEO_Spec.md §2.
$rm_modules = [
    'seo-analysis',
    'sitemap',
    'rich-snippet',
    'woocommerce',
    'redirections',
    '404-monitor',
    'instant-indexing',
    'image-seo',
    'llms-txt',
    'robots-txt',
];
update_option('rank_math_modules', $rm_modules);
if (class_exists(\RankMath\Installer::class)) {
    \RankMath\Installer::create_tables($rm_modules);
}

$rm_general = (array) get_option('rank-math-options-general', []);
$rm_general = array_merge($rm_general, [
    'strip_category_base'            => 'off',
    'attachment_redirect_urls'       => 'on',
    'breadcrumbs'                    => 'on',
    'breadcrumbs_separator'          => '/',
    'breadcrumbs_home'               => 'on',
    'breadcrumbs_home_label'         => 'Trang chủ',
    'breadcrumbs_hide_taxonomy_name' => 'on',
    'wc_remove_product_base'         => 'off',
    'wc_remove_category_base'        => 'on',
    'wc_remove_category_parent_slugs'=> 'on',
    'wc_remove_generator'            => 'on',
    'remove_shop_snippet_data'       => 'on',
    'product_brand'                  => 'custom',
    'custom_product_brand'           => 'DRAVIT',
]);
update_option('rank-math-options-general', $rm_general);

$rm_titles = (array) get_option('rank-math-options-titles', []);
$rm_titles = array_merge($rm_titles, [
    'title_separator'           => '|',
    'noindex_empty_taxonomies'  => 'on',
    'noindex_paginated_pages'   => 'off',
    'noindex_archive_subpages'  => 'off',
    'noindex_password_protected'=> 'on',
    'disable_author_archives'   => 'on',
    'disable_date_archives'     => 'on',
    'knowledgegraph_type'       => 'company',
    'knowledgegraph_name'       => 'DRAVIT',
    'tax_product_cat_robots'    => ['index'],
    'tax_post_tag_robots'       => ['noindex'],
]);
update_option('rank-math-options-titles', $rm_titles);

// 5. Kích hoạt theme `dravit` và làm mới toàn bộ bảng Rewrite Rules.
switch_theme('dravit');
flush_rewrite_rules(false);

echo "Step 0 WordPress/WooCommerce/RankMath setup completed successfully.\n";

