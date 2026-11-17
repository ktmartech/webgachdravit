<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Content\Toc;
use PHPUnit\Framework\TestCase;

final class TocGeneratorTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'wp_strip_all_tags' => static fn (string $s): string => strip_tags($s),
            'esc_attr'          => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
    public function test_slugify_folds_vietnamese_diacritics_and_preserves_dimensions(): void {
        self::assertSame(
            'tieu-chuan-chong-truot-r10-r11-cho-san-800x800-mm',
            Toc::slugify('Tiêu chuẩn chống trượt R10 - R11 cho sàn 800×800 mm')
        );
        self::assertSame(
            'cong-thuc-quy-doi-1-92-m2-thung',
            Toc::slugify('Công thức quy đổi 1,92 m²/thùng')
        );
    }

    public function test_suppresses_toc_when_fewer_than_three_h2_headings_on_blog_post(): void {
        $html = '<h2>Mục một</h2><p>Đoạn 1</p><h2>Mục hai</h2><p>Đoạn 2</p>';
        $res  = Toc::processHtml($html, 3);

        self::assertSame([], $res['items']);
        self::assertSame($html, $res['html']);

        // Trên trang Chính sách (`min_h2 = 2`), 2 thẻ H2 đủ ngưỡng kích hoạt TOC.
        $policyRes = Toc::processHtml($html, 2);
        self::assertCount(2, $policyRes['items']);
        self::assertSame('muc-mot', $policyRes['items'][0]['id']);
        self::assertSame('muc-hai', $policyRes['items'][1]['id']);
    }

    public function test_generates_unique_suffixes_for_duplicate_headings_and_excludes_embedded_boxes(): void {
        $html = <<<'HTML'
<aside class="drv-key-takeaways">
    <h2>Tóm tắt ẩn khỏi TOC</h2>
</aside>
<h2>1. Chọn cấp nhám R10</h2>
<h3>Khu khô lavabo</h3>
<div class="drv-product-embed-box">
    <h3>Gạch trong thẻ nhúng không được lọt vào TOC</h3>
</div>
<h2>2. Quy trình thi công</h2>
<h3>Khu khô lavabo</h3>
<div class="drv-faq-group">
    <h2>Câu hỏi FAQ không lọt vào TOC</h2>
</div>
<h2>3. Nghiệm thu mã Shade</h2>
<h3>Khu khô lavabo</h3>
HTML;

        $res = Toc::processHtml($html, 3);
        $ids = array_map(static fn (array $item): string => $item['id'], $res['items']);

        self::assertSame([
            '1-chon-cap-nham-r10',
            'khu-kho-lavabo',
            '2-quy-trinh-thi-cong',
            'khu-kho-lavabo-2',
            '3-nghiem-thu-ma-shade',
            'khu-kho-lavabo-3',
        ], $ids);

        self::assertStringContainsString('<h2 id="1-chon-cap-nham-r10">1. Chọn cấp nhám R10</h2>', $res['html']);
        self::assertStringContainsString('<h3 id="khu-kho-lavabo-2">Khu khô lavabo</h3>', $res['html']);
        self::assertStringContainsString('<h3 id="khu-kho-lavabo-3">Khu khô lavabo</h3>', $res['html']);
    }
}
