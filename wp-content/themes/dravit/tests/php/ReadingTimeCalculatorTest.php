<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Content\ReadingTime;
use PHPUnit\Framework\TestCase;

final class ReadingTimeCalculatorTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'wp_strip_all_tags' => static fn (string $s): string => strip_tags($s),
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
    public function test_strips_shortcodes_and_html_before_counting_vietnamese_words(): void {
        $content = '<p>Gạch Porcelain xương đá mài cạnh phẳng 80x80 cm có độ hút nước dưới 0,5%.</p>'
            . '[drv_products collection="gach-80x80" limit="4"]'
            . '[drv_callout type="tip"]Kiểm tra đồng bộ mã màu Shade trước khi lát.[/drv_callout]';

        $words = ReadingTime::countWords($content);
        // "Gạch Porcelain xương đá mài cạnh phẳng 80x80 cm có độ hút nước dưới 0,5%." (15)
        // + "Kiểm tra đồng bộ mã màu Shade trước khi lát." (10) = 25 từ.
        self::assertSame(25, $words);
        self::assertSame(1, ReadingTime::calculateMinutes($content));
        self::assertSame('1 phút đọc', ReadingTime::formatLabel(0));
    }

    public function test_calculates_minutes_at_200_words_per_minute_threshold(): void {
        $fourHundredWords = implode(' ', array_fill(0, 400, 'gạch'));
        $fiveHundredWords = implode(' ', array_fill(0, 510, 'vữa'));

        self::assertSame(2, ReadingTime::calculateMinutes($fourHundredWords, 200));
        self::assertSame(3, ReadingTime::calculateMinutes($fiveHundredWords, 200));
        self::assertSame('3 phút đọc', ReadingTime::formatLabel(3));
    }
}
