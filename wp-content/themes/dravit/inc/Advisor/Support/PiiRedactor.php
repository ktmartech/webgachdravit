<?php
declare(strict_types=1);

namespace Dravit\Advisor\Support;

defined('ABSPATH') || exit;

/**
 * Ẩn thông tin định danh cá nhân (SĐT Việt Nam, Email) trước khi lưu log truy vấn AI (docs/13 §11).
 */
final class PiiRedactor {
    public static function redact(string $text): string {
        if ($text === '') {
            return '';
        }

        // 1. Ẩn địa chỉ email
        $clean = (string) preg_replace(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            '[EMAIL_DA_AN]',
            $text
        );

        // 2. Ẩn số điện thoại Việt Nam (03x/05x/07x/08x/09x, 02x bàn, +84/84 có dấu chấm, gạch ngang hoặc khoảng trắng)
        $clean = (string) preg_replace(
            '/(?<!\d)(?:\+?84|0)(?:[\s.\-]?\d){8,10}(?!\d)/u',
            '[SDT_DA_AN]',
            $clean
        );

        return trim($clean);
    }
}

