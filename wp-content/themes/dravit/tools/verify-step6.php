<?php
/**
 * Kiểm chứng thực tế Gate 6 trên cơ sở dữ liệu WordPress + WooCommerce (chạy qua WP-CLI `eval-file`).
 */
declare(strict_types=1);

use Dravit\Core\Template;
use Dravit\Filters\FacetCounter;
use Dravit\Filters\Renderer;
use Dravit\Filters\UrlState;
use Dravit\Seo\UrlNormalizer;

if (!defined('ABSPATH')) {
    exit("Chạy file này qua WP-CLI eval-file.\n");
}

$errors = [];
$assert = static function (bool $cond, string $msg) use (&$errors): void {
    if (!$cond) {
        $errors[] = $msg;
    }
};

$origin = UrlNormalizer::untrailingslashUrl(home_url('/'));
$server = rest_get_server();

// 1. Kiểm chứng GET /dravit/v1/catalog trên trang sạch /gach-phong-khach (M-1, M-11, M-13, F-14, F-15)
$req_clean = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_clean->set_param('base', 'product_cat:gach-phong-khach');
$t0         = microtime(true);
$resp_clean = $server->dispatch($req_clean);
$ms_clean   = (microtime(true) - $t0) * 1000.0;
$data_clean = $resp_clean->get_data();
$hdrs_clean = $resp_clean->get_headers();

$assert($resp_clean->get_status() === 200, 'REST catalog sạch phải trả HTTP 200.');
$assert(($hdrs_clean['X-Robots-Tag'] ?? '') === 'noindex, nofollow', 'M-11: REST catalog phải có header X-Robots-Tag: noindex, nofollow.');
$assert(str_contains($hdrs_clean['Cache-Control'] ?? '', 'max-age=300'), 'REST catalog phải có Cache-Control max-age=300.');
$assert(($data_clean['robots'] ?? '') === 'index, follow', 'M-1: Trang sạch có sản phẩm phải trả robots = index, follow.');
$assert(($data_clean['canonical'] ?? '') === $origin . '/gach-phong-khach', 'Canonical trang sạch /gach-phong-khach sai: ' . ($data_clean['canonical'] ?? ''));
$assert(($data_clean['total'] ?? 0) > 0, 'Danh mục /gach-phong-khach phải có sản phẩm từ 8 SKU seed.');

// F-14: Kiểm tra không còn 'phong-khach' trong nhóm khong-gian trên /gach-phong-khach
$kg_slugs = [];
foreach ($data_clean['facets'] ?? [] as $f_item) {
    if (($f_item['param'] ?? '') === 'khong-gian') {
        foreach ($f_item['options'] ?? [] as $opt) {
            $kg_slugs[] = $opt['slug'] ?? '';
        }
    }
}
$assert(!in_array('phong-khach', $kg_slugs, true), 'F-14: Trên /gach-phong-khach phải tự động ẩn lựa chọn trùng chiều 1 (phong-khach).');

// M-13: Trên trang sạch, nhóm seo có <a data-facet-link>, nhóm tech tuyệt đối không có href
$assert(str_contains($data_clean['facets_html'] ?? '', 'data-facet-link'), 'M-13: Trang sạch 0 tham số phải xuất <a data-facet-link> cho nhóm SEO.');
foreach ($data_clean['facets'] ?? [] as $f_item) {
    if (($f_item['group'] ?? '') === 'tech') {
        foreach ($f_item['options'] ?? [] as $opt) {
            $assert(($opt['href'] ?? null) === null, 'M-13: Nhóm tech (' . ($f_item['param'] ?? '') . ') không bao giờ được có href.');
        }
    }
}

// 2. Kiểm chứng lọc 1 tham số trên /gach-phong-khach?kich-thuoc=80x80 (F-1, F-9, M-3, M-13)
$req_f1 = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_f1->set_param('base', 'product_cat:gach-phong-khach');
$req_f1->set_param('kich-thuoc', '80x80');
$resp_f1 = $server->dispatch($req_f1);
$data_f1 = $resp_f1->get_data();

$assert(($data_f1['url'] ?? '') === $origin . '/gach-phong-khach?kich-thuoc=80x80', 'F-1: URL chuẩn hóa sai: ' . ($data_f1['url'] ?? ''));
$assert(($data_f1['canonical'] ?? '') === $origin . '/gach-phong-khach', 'M-3: Canonical khi lọc phải trỏ về gốc sạch.');
$assert(($data_f1['robots'] ?? '') === 'noindex, follow', 'M-3: Lọc tham số SEO phải trả noindex, follow.');
$assert(!str_contains($data_f1['facets_html'] ?? '', 'data-facet-link'), 'M-13: Khi đã có >= 1 tham số lọc, tuyệt đối không sinh <a data-facet-link> đa tham số.');

// 3. Kiểm chứng R-3: Chọn 1 giá trị ánh xạ Collection tĩnh trên /gach -> trả redirect_to
$req_r3 = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_r3->set_param('base', 'product_cat:gach');
$req_r3->set_param('cong-nang', 'lat-nen');
$data_r3 = $server->dispatch($req_r3)->get_data();
$assert(($data_r3['redirect_to'] ?? '') === $origin . '/gach-lat-nen', 'R-3: Chọn cong-nang=lat-nen tại /gach phải trả redirect_to=/gach-lat-nen.');

// 4. Kiểm chứng sắp xếp hỗn hợp Gạch & Showroom trên /gach: SP quote_only luôn đứng sau SP priced (D-20)
foreach (['showroom-truoc', 'kho-lon', 'kho-nho'] as $non_price_sort) {
    $req_np = new \WP_REST_Request('GET', '/dravit/v1/catalog');
    $req_np->set_param('base', 'product_cat:gach');
    $req_np->set_param('sort', $non_price_sort);
    $resp_np = $server->dispatch($req_np);
    $data_np = $resp_np->get_data();
    $assert($resp_np->get_status() === 200 && ($data_np['robots'] ?? '') === 'noindex, nofollow', "M-6: sort={$non_price_sort} phải trả HTTP 200 và noindex, nofollow.");
}

foreach (['gia-tang', 'gia-giam'] as $sort_dir) {
    $req_sort = new \WP_REST_Request('GET', '/dravit/v1/catalog');
    $req_sort->set_param('base', 'product_cat:gach');
    $req_sort->set_param('per_page', 48);
    $req_sort->set_param('sort', $sort_dir);
    $req_sort->set_param('include_items_meta', true);
    $data_sort = $server->dispatch($req_sort)->get_data();

    $assert(($data_sort['robots'] ?? '') === 'noindex, nofollow', "M-6: sort={$sort_dir} phải trả noindex, nofollow.");

    $seen_quote = false;
    foreach ($data_sort['items_meta'] ?? [] as $item) {
        if (($item['price_state'] ?? '') === 'quote_only' || (int) ($item['price_m2'] ?? 0) <= 0) {
            $seen_quote = true;
        } elseif ($seen_quote && !empty($item['in_stock'])) {
            $assert(false, "D-20: Khi sort={$sort_dir}, SP còn hàng có giá ({$item['sku']}) không được xếp sau SP báo giá.");
        }
    }
}

// 5. Kiểm chứng fragment=count (F-8) & fragment=items phân trang không trùng (F-6)
$req_cnt = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_cnt->set_param('base', 'product_cat:gach');
$req_cnt->set_param('fragment', 'count');
$t_cnt    = microtime(true);
$data_cnt = $server->dispatch($req_cnt)->get_data();
$ms_cnt   = (microtime(true) - $t_cnt) * 1000.0;
$assert(($data_cnt['items_html'] ?? 'x') === '' && ($data_cnt['total'] ?? 0) >= 8, 'F-8: fragment=count phải trả total >= 8 và items_html rỗng.');

$req_p1 = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_p1->set_param('base', 'product_cat:gach');
$req_p1->set_param('per_page', 3);
$req_p1->set_param('page', 1);
$req_p1->set_param('fragment', 'items');
$req_p1->set_param('include_items_meta', true);
$data_p1 = $server->dispatch($req_p1)->get_data();

$req_p2 = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_p2->set_param('base', 'product_cat:gach');
$req_p2->set_param('per_page', 3);
$req_p2->set_param('page', 2);
$req_p2->set_param('fragment', 'items');
$req_p2->set_param('include_items_meta', true);
$data_p2 = $server->dispatch($req_p2)->get_data();

$ids_p1 = array_column($data_p1['items_meta'] ?? [], 'id');
$ids_p2 = array_column($data_p2['items_meta'] ?? [], 'id');
$assert(count($ids_p1) === 3 && count($ids_p2) === 3 && array_intersect($ids_p1, $ids_p2) === [], 'F-6: Load More trang 1 & 2 (per_page=3) không được trùng sản phẩm.');

// 6. Kiểm chứng bảo mật tham số & trần per_page <= 48 (X-1, X-2) & hủy cache phiên bản (F-11)
$req_sec = new \WP_REST_Request('GET', '/dravit/v1/catalog');
$req_sec->set_param('base', 'product_cat:gach');
$req_sec->set_param('per_page', 1000);
$req_sec->set_param('kich-thuoc', "80x80,' OR 1=1--");
$data_sec = $server->dispatch($req_sec)->get_data();
$assert(($data_sec['per_page'] ?? 0) === 48, 'X-2: per_page=1000 phải bị giới hạn về 48.');
$assert(($data_sec['url'] ?? '') === $origin . '/gach-80x80' || ($data_sec['redirect_to'] ?? '') === $origin . '/gach-80x80', 'X-1: Giá trị SQL payload phải bị lọc sạch.');

$ver_before = FacetCounter::getCacheVersion();
do_action('drv/catalog/synced');
$ver_after  = FacetCounter::getCacheVersion();
$assert($ver_after === $ver_before + 1, 'F-11: Sự kiện drv/catalog/synced phải tăng drv_cache_ver để làm mới cache bộ lọc.');

// 7. Kiểm chứng SSR DOM: đúng 1 <form id="drv-catalog-filter-form">, có aria-live, 0 debug badge (F-12, F-13, F-15)
$state_ssr = UrlState::fromArray('product_cat:gach-phong-khach', []);
$counts_s  = FacetCounter::getCounts($state_ssr);
$view_s    = Renderer::buildFilterViewData($state_ssr, $counts_s, $counts_s);

ob_start();
Template::render_part('template-parts/catalog/filters', [
    'state'        => $state_ssr,
    'archive_name' => 'Gạch Phòng Khách',
    'showroom'     => $view_s['showroom'],
    'ui_groups'    => $view_s['ui_groups'],
    'total_found'  => (int) ($data_clean['total'] ?? 0),
]);
Template::render_part('template-parts/catalog/toolbar', [
    'state'        => $state_ssr,
    'shown'        => (int) ($data_clean['shown'] ?? 0),
    'total'        => (int) ($data_clean['total'] ?? 0),
    'archive_name' => 'Gạch Phòng Khách',
]);
Template::render_part('template-parts/catalog/mobile-filter-sheet', [
    'state'       => $state_ssr,
    'total_found' => (int) ($data_clean['total'] ?? 0),
]);
$ssr_html = (string) ob_get_clean();

$assert(substr_count($ssr_html, '<form ') === 1, 'F-13 / STRIP-25: Toàn trang (Sidebar + Mobile Sheet) chỉ được có đúng 1 thẻ <form>.');
$assert(str_contains($ssr_html, 'id="drv-catalog-live-status"') && str_contains($ssr_html, 'aria-live="polite"'), 'F-12: Thiếu vùng aria-live="polite" cho Screen Reader.');
$assert(!str_contains($ssr_html, 'robots:') && !str_contains($ssr_html, 'data-demo-state'), 'F-15 / STRIP-05: Không được xuất badge debug robots hay thanh demo prototype.');

echo sprintf(
    "Catalog REST latency: fragment=grid => %.2fms (%d SP), fragment=count => %.2fms (%d SP)\n",
    $ms_clean,
    (int) ($data_clean['total'] ?? 0),
    $ms_cnt,
    (int) ($data_cnt['total'] ?? 0)
);

if ($errors !== []) {
    fwrite(STDERR, "FAILED Gate 6 Verification:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "PASSED Gate 6 Verification (F-1..F-15, R-3..R-8, M-1..M-13, X-1..X-2, Single Form & Anti-Spider-Trap).\n";
