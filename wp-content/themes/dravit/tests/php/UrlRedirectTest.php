<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Seo\UrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UrlRedirectTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_r1_r2_r10_trailing_slash_and_legacy_woo_paths_redirect_301(): void {
        // Trang chủ gốc `/` giữ nguyên 200 OK
        $root = UrlNormalizer::evaluateRequest('/', []);
        self::assertSame('ok', $root['action']);

        // R-1: Có dấu `/` cuối -> 301 không dấu `/` cuối
        $r1 = UrlNormalizer::evaluateRequest('/gach-lat-nen/', []);
        self::assertSame('redirect', $r1['action']);
        self::assertSame(301, $r1['status']);
        self::assertSame('/gach-lat-nen', $r1['location']);

        // R-2: Đường dẫn mặc định WooCommerce `/product-category/...` -> 301 về `/{leaf-slug}`
        $r2 = UrlNormalizer::evaluateRequest('/product-category/gach/gach-phong-khach/', []);
        self::assertSame('redirect', $r2['action']);
        self::assertSame('/gach-phong-khach', $r2['location']);

        // R-10: Shop gốc `/san-pham` -> 301 về `/gach`, và `/showroom` -> 301 về `/lien-he`
        $r10 = UrlNormalizer::evaluateRequest('/san-pham', []);
        self::assertSame('redirect', $r10['action']);
        self::assertSame('/gach', $r10['location']);

        $sr = UrlNormalizer::evaluateRequest('/showroom/', []);
        self::assertSame('redirect', $sr['action']);
        self::assertSame('/lien-he', $sr['location']);
    }

    public function test_r3_single_value_on_root_gach_redirects_to_static_silo_only_when_mapped(): void {
        // Có Collection tĩnh -> 301
        $lat_nen = UrlNormalizer::evaluateRequest('/gach', ['cong-nang' => 'lat-nen']);
        self::assertSame('redirect', $lat_nen['action']);
        self::assertSame('/gach-lat-nen', $lat_nen['location']);

        $nung_ket = UrlNormalizer::evaluateRequest('/gach', ['chat-lieu' => 'da-nung-ket']);
        self::assertSame('redirect', $nung_ket['action']);
        self::assertSame('/da-nung-ket', $nung_ket['location']);

        $bst = UrlNormalizer::evaluateRequest('/gach', ['bo-suu-tap' => 'dravit-luxury']);
        self::assertSame('redirect', $bst['action']);
        self::assertSame('/bo-suu-tap/dravit-luxury', $bst['location']);

        // Không có Collection tĩnh riêng (mau-sac=trang) hoặc chọn >= 2 giá trị -> giữ 200 OK tại /gach
        $mau_trang = UrlNormalizer::evaluateRequest('/gach', ['mau-sac' => 'trang']);
        self::assertSame('ok', $mau_trang['action']);

        $multi = UrlNormalizer::evaluateRequest('/gach', ['cong-nang' => 'lat-nen,op-tuong']);
        self::assertSame('ok', $multi['action']);
    }

    public function test_r4_r5_r6_r7_r8_query_grammar_ordering_dedup_and_garbage_stripping(): void {
        // R-4: Sai thứ tự tham số (be-mat=70 đứng trước kich-thuoc=40) -> 301 đảo lại
        $r4 = UrlNormalizer::evaluateRequest('/gach-phong-khach', [
            'be-mat'     => 'mo',
            'kich-thuoc' => '80x80',
        ]);
        self::assertSame('redirect', $r4['action']);
        self::assertSame('/gach-phong-khach?kich-thuoc=80x80&be-mat=mo', $r4['location']);

        // R-5: Hoa thường + trùng lặp + ngược a..z -> 301 chuẩn hóa
        $r5 = UrlNormalizer::evaluateRequest('/gach-phong-khach', [
            'kich-thuoc' => '80X80,60x60,80x80',
        ]);
        self::assertSame('redirect', $r5['action']);
        self::assertSame('/gach-phong-khach?kich-thuoc=60x60,80x80', $r5['location']);

        // R-6 & R-8 & sort=moi-ve + chiều 1 dư thừa -> gọt sạch trong 1 lần 301 duy nhất
        $dirty = UrlNormalizer::evaluateRequest('/gach-phong-khach/', [
            'foo'        => 'bar',
            'khong-gian' => 'phong-khach',
            'mau-sac'    => 'mau-khong-ton-tai,trang',
            'sort'       => 'moi-ve',
        ]);
        self::assertSame('redirect', $dirty['action']);
        self::assertSame('/gach-phong-khach?mau-sac=trang', $dirty['location']);

        // R-7: Tracking params được bảo toàn trên URL trình duyệt (không redirect mất UTM)
        $r7 = UrlNormalizer::evaluateRequest('/gach-80x80', [
            'utm_source' => 'zalo',
            'fbclid'     => 'IwAR123',
        ]);
        self::assertSame('ok', $r7['action']);
    }

    public function test_r9_and_m9_pagination_bounds_and_private_attribute_archives_return_404(): void {
        // ?page=2 -> 301 sang /page/2; /page/1 -> 301 bỏ /page/1
        $q_page2 = UrlNormalizer::evaluateRequest('/gach-80x80', ['page' => '2'], ['max_pages' => 4]);
        self::assertSame('redirect', $q_page2['action']);
        self::assertSame('/gach-80x80/page/2', $q_page2['location']);

        $p1 = UrlNormalizer::evaluateRequest('/gach-80x80/page/1', []);
        self::assertSame('redirect', $p1['action']);
        self::assertSame('/gach-80x80', $p1['location']);

        // R-9: Phân trang vượt quá max_pages -> HTTP 404
        $r9 = UrlNormalizer::evaluateRequest('/gach-80x80/page/999', [], ['max_pages' => 3]);
        self::assertSame('404', $r9['action']);
        self::assertSame(404, $r9['status']);

        // M-9: Truy cập trực tiếp pa_* không public (/mau-sac/trang) -> HTTP 404
        $m9 = UrlNormalizer::evaluateRequest('/mau-sac/trang', []);
        self::assertSame('404', $m9['action']);
        self::assertSame(404, $m9['status']);
    }
}

