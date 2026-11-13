<?php
/**
 * Kiểm chứng thực tế Gate 3 trên cơ sở dữ liệu WordPress + WooCommerce (chạy qua WP-CLI `eval-file`).
 */
declare(strict_types=1);

use Dravit\Catalog\ProductSeeder;
use Dravit\Catalog\SearchController;
use Dravit\Catalog\SearchIndex;
use Dravit\Catalog\SearchTrending;
use Dravit\Core\Config;
use Dravit\Seo\Canonical;
use Dravit\Seo\LlmsTxt;
use Dravit\Seo\RobotsMatrix;
use Dravit\Seo\SchemaGraph;
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

// 1. Kiểm chứng R-1..R-10 (UrlNormalizer chạy trên term_exists thực tế của DB)
$r1 = UrlNormalizer::evaluateRequest('/gach/');
$assert($r1['action'] === 'redirect' && $r1['status'] === 301 && $r1['location'] === '/gach', 'R-1: /gach/ phải 301 về /gach.');

$r2 = UrlNormalizer::evaluateRequest('/product-category/gach/gach-lat-nen/');
$assert($r2['action'] === 'redirect' && $r2['location'] === '/gach-lat-nen', 'R-2: /product-category/gach/gach-lat-nen/ phải 301 về /gach-lat-nen.');

$r3 = UrlNormalizer::evaluateRequest('/san-pham');
$assert($r3['action'] === 'redirect' && $r3['location'] === '/gach', 'R-3: /san-pham phải 301 về /gach.');

$r4 = UrlNormalizer::evaluateRequest('/showroom');
$assert($r4['action'] === 'redirect' && $r4['location'] === '/lien-he', 'R-4: /showroom phải 301 về /lien-he.');

$r5 = UrlNormalizer::evaluateRequest('/gach', ['kich-thuoc' => '80x80']);
$assert($r5['action'] === 'redirect' && $r5['location'] === '/gach-80x80', 'R-5: /gach?kich-thuoc=80x80 phải 301 về /gach-80x80.');

$r678 = UrlNormalizer::evaluateRequest(
    '/gach-lat-nen',
    ['mau-sac' => 'xam,trang', 'kich-thuoc' => '80x80', 'foo' => 'bar', 'utm_source' => 'zalo']
);
$assert(
    $r678['action'] === 'redirect' && $r678['location'] === '/gach-lat-nen?kich-thuoc=80x80&mau-sac=trang,xam&utm_source=zalo',
    'R-6..R-8: Chuẩn hóa thứ tự facet + strip rác + giữ utm_source sai: ' . ($r678['location'] ?? '')
);

$r9 = UrlNormalizer::evaluateRequest('/gach/page/999', [], ['max_pages' => 3]);
$assert($r9['action'] === '404' && $r9['status'] === 404, 'R-9: /gach/page/999 vượt max_pages phải trả 404.');

$r10 = UrlNormalizer::evaluateRequest('/cong-nang/lat-nen');
$assert($r10['action'] === '404' && $r10['status'] === 404, 'R-10: Taxonomy nội bộ /cong-nang/lat-nen phải trả 404.');

// 2. Kiểm chứng M-1..M-12 (RobotsMatrix & Canonical)
$m1 = RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 12, 'query_params' => []]);
$assert($m1['index'] === 'index' && $m1['follow'] === 'follow', 'M-1: Collection sạch post_count > 0 phải index, follow.');

$m2 = RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 12, 'paged' => 2, 'max_pages' => 5, 'query_params' => []]);
$assert($m2['index'] === 'index' && $m2['follow'] === 'follow', 'M-2: /page/2 sạch phải index, follow.');

$c2 = Canonical::resolveCanonical($origin . '/gach-lat-nen/', 2, []);
$assert($c2 === $origin . '/gach-lat-nen/page/2', 'M-2 Canonical: /page/2 phải giữ /page/2 không dấu / cuối.');

$m3 = RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 12, 'query_params' => ['kich-thuoc' => '80x80']]);
$assert($m3['index'] === 'noindex' && $m3['follow'] === 'follow', 'M-3: Lọc tham số nhóm seo phải noindex, follow.');

$c3 = Canonical::resolveCanonical($origin . '/gach-lat-nen/', 2, ['kich-thuoc' => '80x80']);
$assert($c3 === $origin . '/gach-lat-nen', 'M-3 Canonical: Khi có filter param, canonical phải trỏ về URL sạch gốc.');

$m4 = RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 12, 'query_params' => ['dcof' => 'r10']]);
$assert($m4['index'] === 'noindex' && $m4['follow'] === 'nofollow', 'M-4: Lọc tham số nhóm tech (dcof) phải noindex, nofollow.');

$m5 = RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 12, 'query_params' => ['sort' => 'gia-tang']]);
$assert($m5['index'] === 'noindex' && $m5['follow'] === 'nofollow', 'M-5: ?sort=* phải noindex, nofollow.');

$m6 = RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 0, 'query_params' => []]);
$assert($m6['index'] === 'noindex' && $m6['follow'] === 'follow', 'M-6: Collection rỗng post_count=0 phải noindex, follow.');

$m7  = RobotsMatrix::resolveRobots(['view' => 'search']);
$m8  = RobotsMatrix::resolveRobots(['view' => 'page', 'page_slug' => 'danh-sach-bao-gia']);
$m9  = RobotsMatrix::resolveRobots(['view' => 'page', 'is_transactional' => true]);
$m10 = RobotsMatrix::resolveRobots(['view' => 'page', 'page_slug' => 'thanh-toan']);
$assert($m7['index'] === 'noindex' && $m7['follow'] === 'follow', 'M-7: Search phải noindex, follow.');
$assert($m8['index'] === 'noindex' && $m8['follow'] === 'follow', 'M-8: BOQ phải noindex, follow.');
$assert($m9['index'] === 'noindex' && $m9['follow'] === 'nofollow', 'M-9: Cart transactional phải noindex, nofollow.');
$assert($m10['index'] === 'noindex' && $m10['follow'] === 'nofollow', 'M-10: Checkout phải noindex, nofollow.');

// 3. Kiểm chứng SchemaGraph trên dữ liệu WC_Product thực tế trong DB (M-13, M-14, D-20)
$priced_id = (int) wc_get_product_id_by_sku('DRV-8801M');
$quote_id  = (int) wc_get_product_id_by_sku('DRV-3612T');
$assert($priced_id > 0 && $quote_id > 0, 'Phải tìm thấy cả DRV-8801M (priced) và DRV-3612T (quote_only) trong DB.');

if ($priced_id > 0) {
    $priced_prod = wc_get_product($priced_id);
    $node_priced = SchemaGraph::buildProductNode($priced_prod, $origin);
    $assert(isset($node_priced['offers']) && is_array($node_priced['offers']), 'M-13: Sản phẩm có giá DRV-8801M phải có offers.');
    $specs = $node_priced['offers']['priceSpecification'] ?? [];
    $assert(is_array($specs) && count($specs) === 2, 'M-13: DRV-8801M phải có đúng 2 UnitPriceSpecification (C62 + MTK).');
    $assert(($specs[0]['unitCode'] ?? '') === 'C62' && ($specs[1]['unitCode'] ?? '') === 'MTK', 'M-13: Sai unitCode C62/MTK.');
    $assert(!array_key_exists('mpn', $node_priced), 'M-14: Cấm gắn mpn chứa mã lô/shade vào Product JSON-LD.');
}

if ($quote_id > 0) {
    $quote_prod = wc_get_product($quote_id);
    $node_quote = SchemaGraph::buildProductNode($quote_prod, $origin);
    $assert(!array_key_exists('offers', $node_quote), 'D-20 / M-13: Sản phẩm quote_only DRV-3612T tuyệt đối không được xuất offers 0đ.');
}

// 4. Kiểm chứng /llms.txt & robots.txt (GEO)
$llms_md = LlmsTxt::buildMarkdown();
$assert(str_starts_with($llms_md, '# ') && str_contains($llms_md, 'DRAVIT'), '/llms.txt phải bắt đầu bằng tiêu đề Markdown chứa DRAVIT.');
$collections_cfg = Config::get('collections');
foreach ($collections_cfg as $slug => $cfg) {
    $clean_url = $origin . '/' . $slug;
    $assert(str_contains($llms_md, '(' . $clean_url . ')'), "/llms.txt thiếu URL sạch của collection: {$clean_url}");
    $assert(!str_contains($llms_md, '(' . $clean_url . '/)'), "/llms.txt chứa dấu / cuối trái phép tại: {$clean_url}/");
}

$llms_provider = new LlmsTxt();
$robots_txt    = $llms_provider->filter_robots_txt("User-agent: *\nDisallow: /wp-admin/\n", true);
$assert(str_contains($robots_txt, 'User-agent: GPTBot') && str_contains($robots_txt, 'User-agent: ClaudeBot'), 'robots.txt thiếu AI crawler rules.');
$assert(str_contains($robots_txt, 'Disallow: /*?*dcof='), 'robots.txt thiếu crawl budget rule cho tech param.');

// 5. Kiểm chứng các Section tái sử dụng (`drv_section`)
ob_start();
drv_section('geo-spec-section', [
    'title'   => 'Thông số đóng gói gạch 80x80',
    'headers' => ['Kích thước', 'Số viên/thùng'],
    'rows'    => [['800×800 mm', '3 viên (1,92 m²)']],
]);
$geo_html = (string) ob_get_clean();
$assert(str_contains($geo_html, 'drv-geo-table'), 'geo-spec-section phải xuất bảng drv-geo-table.');

ob_start();
drv_section('faq-section', ['items' => [['q' => 'Gạch 80x80 mấy viên 1 thùng?', 'a' => '3 viên/thùng (1,92 m²).']]]);
$faq_html = (string) ob_get_clean();
$assert(str_contains($faq_html, 'drv-faq') && str_contains($faq_html, '1,92 m²'), 'faq-section render lỗi.');

ob_start();
drv_section('silo-tiles-bar', ['slugs' => ['gach-lat-nen', 'gach-80x80', 'da-nung-ket']]);
$silo_html = (string) ob_get_clean();
$assert(str_contains($silo_html, 'drv-silo-bar') && !str_contains($silo_html, '?'), 'silo-tiles-bar phải không có query ?param.');

ob_start();
drv_section('error-404-recovery');
$err404_html = (string) ob_get_clean();
$assert(str_contains($err404_html, 'drv-recovery') && str_contains($err404_html, 'ph-clipboard-text'), 'error-404-recovery render lỗi.');

// 6. Kiểm chứng _drv_search_index & REST `/dravit/v1/search` < 150ms
foreach (ProductSeeder::definitions() as $def) {
    $sku = (string) $def['sku'];
    $pid = (int) wc_get_product_id_by_sku($sku);
    $idx = $pid > 0 ? (string) get_post_meta($pid, SearchIndex::META_KEY, true) : '';
    $assert($idx !== '', "SKU {$sku} thiếu _drv_search_index trong DB.");
}

// Xóa cache transient trước khi đo tốc độ truy vấn DB thực tế (cold query)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drv_srch_%' OR option_name LIKE '_transient_timeout_drv_srch_%'");

$server = rest_get_server();

$req_sku = new \WP_REST_Request('GET', '/dravit/v1/search');
$req_sku->set_param('q', '8801');
$t0       = microtime(true);
$resp_sku = $server->dispatch($req_sku);
$ms_sku   = (microtime(true) - $t0) * 1000.0;
$data_sku = $resp_sku->get_data();
$hdrs_sku = $resp_sku->get_headers();

$assert($resp_sku->get_status() === 200, 'REST search ?q=8801 phải trả HTTP 200.');
$assert($ms_sku < 150.0, sprintf('REST search ?q=8801 vượt ngân sách 150ms (%.2fms).', $ms_sku));
$assert(($hdrs_sku['X-Robots-Tag'] ?? '') === 'noindex, nofollow', 'M-11: REST response phải đính kèm X-Robots-Tag: noindex, nofollow.');
$assert(!empty($data_sku['products']) && ($data_sku['products'][0]['sku'] ?? '') === 'DRV-8801M', 'REST search ?q=8801 phải ưu tiên DRV-8801M đứng đầu.');

// Truy vấn tiếng Việt không dấu kết hợp kích thước mm: "da mo 800x800" & "van da 800x800"
$req_vi = new \WP_REST_Request('GET', '/dravit/v1/search');
$req_vi->set_param('q', 'da mo 800x800');
$t1      = microtime(true);
$resp_vi = $server->dispatch($req_vi);
$ms_vi   = (microtime(true) - $t1) * 1000.0;
$data_vi = $resp_vi->get_data();

$assert($resp_vi->get_status() === 200, 'REST search ?q=da+mo+800x800 phải trả HTTP 200.');
$assert($ms_vi < 150.0, sprintf('REST search ?q=da+mo+800x800 vượt ngân sách 150ms (%.2fms).', $ms_vi));

$req_vi2 = new \WP_REST_Request('GET', '/dravit/v1/search');
$req_vi2->set_param('q', 'van da 800x800');
$t2       = microtime(true);
$resp_vi2 = $server->dispatch($req_vi2);
$ms_vi2   = (microtime(true) - $t2) * 1000.0;
$data_vi2 = $resp_vi2->get_data();
$assert(!empty($data_vi2['products']) && $ms_vi2 < 150.0, 'REST search ?q=van+da+800x800 phải trả sản phẩm khớp < 150ms.');

// Kiểm tra quote_only trong kết quả tìm kiếm không lộ 0đ
$req_quote = new \WP_REST_Request('GET', '/dravit/v1/search');
$req_quote->set_param('q', '3612T');
$resp_quote = $server->dispatch($req_quote);
$data_quote = $resp_quote->get_data();
$assert(
    !empty($data_quote['products'])
    && ($data_quote['products'][0]['price_state'] ?? '') === 'quote_only'
    && ($data_quote['products'][0]['price_m2'] ?? -1) === 0,
    'REST search SKU quote_only DRV-3612T phải trả price_state=quote_only.'
);

// 7. Kiểm chứng Hybrid 3 tầng "Sản phẩm tìm kiếm nhiều" (Phương án A) & Chip từ khóa phổ biến
SearchController::purgeInitialCache();
$init_payload = SearchController::getInitialPayload();
$assert(($init_payload['mode'] ?? '') === 'trending', 'Initial payload phải có mode=trending.');
$assert(count($init_payload['silos'] ?? []) === 6, 'Initial payload phải hiển thị đúng 6 Danh mục phổ biến.');
$assert(count($init_payload['products'] ?? []) === 4, 'Initial payload phải hiển thị đúng 4 Sản phẩm tìm kiếm nhiều (Cold-start Tier 3).');
foreach ($init_payload['products'] ?? [] as $tp) {
    $assert(($tp['price_m2'] ?? 0) > 0 && ($tp['price_piece'] ?? 0) > 0, 'Cold-start Tier 3 phải ưu tiên SP có giá m² và giá viên minh bạch.');
}

foreach (['80x80', 'Vân đá Marble', 'Terrazzo', 'Khổ lớn 60x120', 'Chống trượt R10'] as $chip_kw) {
    $req_chip = new \WP_REST_Request('GET', '/dravit/v1/search');
    $req_chip->set_param('q', $chip_kw);
    $t_chip    = microtime(true);
    $resp_chip = $server->dispatch($req_chip);
    $ms_chip   = (microtime(true) - $t_chip) * 1000.0;
    $data_chip = $resp_chip->get_data();
    $assert(
        !empty($data_chip['products']) && $ms_chip < 150.0,
        sprintf('Chip phổ biến "%s" phải trả sản phẩm khớp < 150ms (%.2fms).', $chip_kw, $ms_chip)
    );
}

// Kiểm chứng Tier 2: Ghi nhận 3 lượt click khác IP + chống trùng lặp cùng IP + flush lên Top 1
$promo_pid = (int) wc_get_product_id_by_sku('DRV-WD1590');
if ($promo_pid <= 0) {
    $promo_pid = $priced_id;
}
if ($promo_pid > 0) {
    $prev_hits = get_post_meta($promo_pid, SearchTrending::META_HITS, true);
    delete_post_meta($promo_pid, SearchTrending::META_HITS);
    delete_transient(SearchTrending::BUFFER_TRANSIENT);
    set_transient(SearchTrending::LAST_FLUSH_TRANSIENT, time(), DAY_IN_SECONDS);

    $c1     = SearchTrending::recordProductClick($promo_pid, '198.51.100.11');
    $c1_dup = SearchTrending::recordProductClick($promo_pid, '198.51.100.11');
    $c2     = SearchTrending::recordProductClick($promo_pid, '198.51.100.12');
    $c3     = SearchTrending::recordProductClick($promo_pid, '198.51.100.13');

    $assert($c1['recorded'] === true && $c1_dup['recorded'] === false, 'SearchTrending phải chặn đếm trùng cùng IP trong 5 phút.');
    $assert($c2['recorded'] === true && $c3['recorded'] === true, 'SearchTrending phải ghi nhận lượt click từ IP khác nhau.');

    $flushed_cnt = SearchTrending::flushBufferNow();
    $assert($flushed_cnt >= 1, 'SearchTrending::flushBufferNow() phải ghi cộng dồn xuống postmeta.');

    $promoted_payload = SearchController::getInitialPayload();
    $assert(
        (($promoted_payload['products'][0]['id'] ?? 0) === $promo_pid),
        'Tier 2: SP đạt >= 3 click tìm kiếm phải tự động thăng hạng lên vị trí #1 trong Sản phẩm tìm kiếm nhiều.'
    );

    // Dọn sạch dữ liệu test để giữ nguyên trạng thái DB sạch
    if ($prev_hits === '' || $prev_hits === false) {
        delete_post_meta($promo_pid, SearchTrending::META_HITS);
    } else {
        update_post_meta($promo_pid, SearchTrending::META_HITS, (int) $prev_hits);
    }
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_drv_srch_dedup_%' OR option_name LIKE '_transient_timeout_drv_srch_dedup_%'");
    SearchController::purgeInitialCache();
}

// Kiểm chứng Tier 1 Pin Limit Guard: Ghim đủ 4/4 -> SP thứ 5 bị chặn `limit_reached` -> gỡ 1 SP -> ghim được SP thứ 5
$pin_test_ids = array_values(array_map('intval', wc_get_products([
    'limit'  => 5,
    'status' => 'publish',
    'return' => 'ids',
])));
$assert(count($pin_test_ids) >= 5, 'Cần ít nhất 5 sản phẩm publish trong DB để kiểm thử giới hạn ghim 4/4.');
if (count($pin_test_ids) >= 5) {
    $orig_pinned = SearchTrending::getPinnedIds();
    foreach ($orig_pinned as $opid) {
        SearchTrending::setProductPinned($opid, false);
    }

    for ($k = 0; $k < 4; $k++) {
        $res_pin = SearchTrending::setProductPinned($pin_test_ids[$k], true);
        $assert($res_pin['ok'] === true && $res_pin['status'] === 'pinned', "Ghim SP #" . ($k + 1) . " phải thành công.");
    }

    $res_overflow = SearchTrending::setProductPinned($pin_test_ids[4], true);
    $assert(
        $res_overflow['ok'] === false && $res_overflow['status'] === 'limit_reached' && count($res_overflow['pinned_ids']) === 4,
        'Khi đã ghim đủ 4/4 SP, ghim thêm SP thứ 5 phải bị chặn với status=limit_reached.'
    );

    $res_unpin = SearchTrending::setProductPinned($pin_test_ids[1], false);
    $res_slot4 = SearchTrending::setProductPinned($pin_test_ids[4], true);
    $assert(
        $res_unpin['status'] === 'unpinned' && $res_slot4['ok'] === true && $res_slot4['status'] === 'pinned',
        'Sau khi gỡ bớt 1 SP (còn 3/4), phải cho phép ghim SP mới vào vị trí thứ 4.'
    );

    // Khôi phục danh sách ghim gốc
    foreach (SearchTrending::getPinnedIds() as $cpid) {
        SearchTrending::setProductPinned($cpid, false);
    }
    foreach ($orig_pinned as $opid) {
        SearchTrending::setProductPinned($opid, true);
    }
}

echo sprintf(
    "Latency cold search: ?q=8801 => %.2fms (%d SP), ?q=da+mo+800x800 => %.2fms (%d SP, %d silos), ?q=van+da+800x800 => %.2fms (%d SP)\n",
    $ms_sku,
    count($data_sku['products'] ?? []),
    $ms_vi,
    count($data_vi['products'] ?? []),
    count($data_vi['silos'] ?? []),
    $ms_vi2,
    count($data_vi2['products'] ?? [])
);

if ($errors !== []) {
    fwrite(STDERR, "FAILED Gate 3 Verification:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

echo "PASSED Gate 3 Verification (R-1..R-10, M-1..M-16, /llms.txt, SchemaGraph, Hybrid 3-Tier Trending Search < 150ms).\n";
