<?php
declare(strict_types=1);

namespace Dravit\Home;

defined('ABSPATH') || exit;

/**
 * Tính trạng thái mở cửa / đóng cửa của Showroom theo múi giờ chuẩn `Asia/Ho_Chi_Minh` (`docs/07 §3`, `docs/14 §6`, Step 5).
 */
final class ShowroomStatus {
    public const TIMEZONE = 'Asia/Ho_Chi_Minh';

    /**
     * Đánh giá trạng thái mở cửa tại thời điểm `$now` (tự động quy đổi về `Asia/Ho_Chi_Minh`).
     *
     * @return array{
     *   is_open: bool,
     *   state: 'open'|'closed',
     *   badge_label: string,
     *   hours_range: string,
     *   detail_label: string,
     *   timezone: string
     * }
     */
    public static function evaluate(
        int $open_hour = 8,
        int $close_hour = 21,
        ?\DateTimeInterface $now = null,
        int $open_minute = 0,
        int $close_minute = 0
    ): array {
        $tz = new \DateTimeZone(self::TIMEZONE);

        if ($now === null) {
            $local_now = new \DateTimeImmutable('now', $tz);
        } else {
            $local_now = \DateTimeImmutable::createFromInterface($now)->setTimezone($tz);
        }

        $safe_open_h  = max(0, min(23, $open_hour));
        $safe_close_h = max(0, min(24, $close_hour));
        $safe_open_m  = max(0, min(59, $open_minute));
        $safe_close_m = $safe_close_h === 24 ? 0 : max(0, min(59, $close_minute));

        $open_total  = ($safe_open_h * 60) + $safe_open_m;
        $close_total = ($safe_close_h * 60) + $safe_close_m;
        $now_total   = ((int) $local_now->format('G') * 60) + (int) $local_now->format('i');

        if ($open_total === $close_total) {
            $is_open = false;
        } elseif ($open_total < $close_total) {
            $is_open = ($now_total >= $open_total && $now_total < $close_total);
        } else {
            // Khung giờ qua nửa đêm
            $is_open = ($now_total >= $open_total || $now_total < $close_total);
        }

        $open_fmt    = sprintf('%02d:%02d', $safe_open_h, $safe_open_m);
        $close_fmt   = sprintf('%02d:%02d', $safe_close_h, $safe_close_m);
        $hours_range = $open_fmt . ' - ' . $close_fmt;
        $badge_label = $is_open ? 'Đang mở cửa' : 'Đã đóng cửa';

        return [
            'is_open'      => $is_open,
            'state'        => $is_open ? 'open' : 'closed',
            'badge_label'  => $badge_label,
            'hours_range'  => $hours_range,
            'detail_label' => $is_open
                ? ($badge_label . ' · Đóng lúc ' . $close_fmt)
                : ($badge_label . ' · Mở cửa lúc ' . $open_fmt),
            'timezone'     => self::TIMEZONE,
        ];
    }

    /**
     * Phân tích chuỗi giờ mở cửa dạng `"08:00 - 20:30"` (fallback về `contact.open_hour` / `contact.close_hour`).
     *
     * @return array{
     *   is_open: bool,
     *   state: 'open'|'closed',
     *   badge_label: string,
     *   hours_range: string,
     *   detail_label: string,
     *   timezone: string
     * }
     */
    public static function fromSettings(?string $hours_override = null, ?\DateTimeInterface $now = null): array {
        if ($hours_override !== null && preg_match('/(\d{1,2}):(\d{2})\s*[-–]\s*(\d{1,2}):(\d{2})/u', $hours_override, $matches) === 1) {
            return self::evaluate(
                (int) $matches[1],
                (int) $matches[3],
                $now,
                (int) $matches[2],
                (int) $matches[4]
            );
        }

        $open_hour  = (int) drv_setting('contact.open_hour', 8);
        $close_hour = (int) drv_setting('contact.close_hour', 21);

        return self::evaluate($open_hour, $close_hour, $now);
    }
}

