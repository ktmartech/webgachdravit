<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Home\ShowroomStatus;
use PHPUnit\Framework\TestCase;

final class ShowroomStatusTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();
        Functions\stubs([
            'get_option' => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_returns_open_status_within_operating_hours_in_hcmc_timezone(): void {
        $tz      = new \DateTimeZone('Asia/Ho_Chi_Minh');
        $morning = new \DateTimeImmutable('2026-11-13 08:00:00', $tz);
        $evening = new \DateTimeImmutable('2026-11-13 20:59:59', $tz);

        $statusOpenStart = ShowroomStatus::evaluate(8, 21, $morning);
        $statusOpenLate  = ShowroomStatus::evaluate(8, 21, $evening);

        self::assertTrue($statusOpenStart['is_open']);
        self::assertSame('open', $statusOpenStart['state']);
        self::assertSame('Đang mở cửa', $statusOpenStart['badge_label']);
        self::assertSame('08:00 - 21:00', $statusOpenStart['hours_range']);
        self::assertStringContainsString('21:00', $statusOpenStart['detail_label']);

        self::assertTrue($statusOpenLate['is_open']);
        self::assertSame('Đang mở cửa', $statusOpenLate['badge_label']);
    }

    public function test_returns_closed_status_before_open_and_at_exact_close_minute(): void {
        $tz        = new \DateTimeZone('Asia/Ho_Chi_Minh');
        $earlyMorn = new \DateTimeImmutable('2026-11-13 07:59:00', $tz);
        $atClose   = new \DateTimeImmutable('2026-11-13 21:00:00', $tz);

        $beforeOpen = ShowroomStatus::evaluate(8, 21, $earlyMorn);
        $afterClose = ShowroomStatus::evaluate(8, 21, $atClose);

        self::assertFalse($beforeOpen['is_open']);
        self::assertSame('closed', $beforeOpen['state']);
        self::assertSame('Đã đóng cửa', $beforeOpen['badge_label']);
        self::assertStringContainsString('08:00', $beforeOpen['detail_label']);

        self::assertFalse($afterClose['is_open']);
        self::assertSame('Đã đóng cửa', $afterClose['badge_label']);
    }

    public function test_from_settings_parses_custom_minute_ranges_and_converts_utc_to_hcmc(): void {
        // 02:30 UTC == 09:30 Asia/Ho_Chi_Minh (open)
        $utcMorning = new \DateTimeImmutable('2026-11-13 02:30:00', new \DateTimeZone('UTC'));
        $resOpen    = ShowroomStatus::fromSettings('08:30 – 17:30', $utcMorning);

        // 11:00 UTC == 18:00 Asia/Ho_Chi_Minh (closed for 08:30 - 17:30)
        $utcEvening = new \DateTimeImmutable('2026-11-13 11:00:00', new \DateTimeZone('UTC'));
        $resClosed  = ShowroomStatus::fromSettings('08:30 – 17:30', $utcEvening);

        self::assertTrue($resOpen['is_open']);
        self::assertSame('08:30 - 17:30', $resOpen['hours_range']);
        self::assertFalse($resClosed['is_open']);
    }

    public function test_falls_back_safely_to_contact_hours_when_override_is_invalid(): void {
        $noon   = new \DateTimeImmutable('2026-11-13 12:00:00', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        $status = ShowroomStatus::fromSettings('Liên hệ trước', $noon);

        self::assertTrue($status['is_open']);
        self::assertSame('08:00 - 21:00', $status['hours_range']);
    }
}
