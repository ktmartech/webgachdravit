<?php
declare(strict_types=1);

namespace Dravit\Advisor\Contracts;

defined('ABSPATH') || exit;

/**
 * Hợp đồng ghi nhận nhật ký truy vấn AI đã ẩn PII và đánh giá phản hồi của khách (ADR D-43, D-47).
 */
interface AdvisorLogInterface {
    /**
     * Lưu một lượt hỏi đáp vào kho nhật ký nội bộ và trả về `request_id`.
     *
     * @param array<string, mixed> $record
     */
    public function logQuery(array $record): int;

    /**
     * Cập nhật đánh giá `up` / `down` kèm lý do vào bản ghi `request_id`.
     */
    public function recordFeedback(int $requestId, string $rating, string $reason = ''): bool;

    /**
     * Xóa các bản ghi quá hạn lưu trữ (mặc định 90 ngày) và trả về số lượng đã xóa.
     */
    public function cleanupOldLogs(int $days = 90): int;
}

