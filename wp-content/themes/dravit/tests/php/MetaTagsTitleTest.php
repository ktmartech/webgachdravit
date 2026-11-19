<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Seo\MetaTags;
use PHPUnit\Framework\TestCase;

final class MetaTagsTitleTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_strip_all_tags')->alias(static fn (string $s): string => strip_tags($s));
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_page_suffix_goes_before_brand(): void {
        self::assertSame('Gạch Ốp Lát - Trang 2 | DRAVIT', MetaTags::appendPageSuffix('Gạch Ốp Lát | DRAVIT', 2));
        self::assertSame('Cẩm nang - Trang 3', MetaTags::appendPageSuffix('Cẩm nang', 3));
    }

    public function test_page_one_and_existing_suffix_are_untouched(): void {
        self::assertSame('Gạch Ốp Lát | DRAVIT', MetaTags::appendPageSuffix('Gạch Ốp Lát | DRAVIT', 1));
        self::assertSame('Gạch - Trang 2 | DRAVIT', MetaTags::appendPageSuffix('Gạch - Trang 2 | DRAVIT', 2));
    }

    public function test_truncated_title_never_ends_with_dangling_dash(): void {
        $title = MetaTags::sanitizeTitle('Tiêu chuẩn chọn gạch lát nhà tắm chống trượt R10 – R11 và quy trình thi công sàn ướt | DRAVIT');
        self::assertLessThanOrEqual(60, mb_strlen($title));
        self::assertStringEndsWith('| DRAVIT', $title);
        self::assertDoesNotMatchRegularExpression('/[-|]\s*\|\s*DRAVIT$/u', $title);
    }
}
