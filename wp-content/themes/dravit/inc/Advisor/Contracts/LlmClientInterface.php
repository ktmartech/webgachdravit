<?php
declare(strict_types=1);

namespace Dravit\Advisor\Contracts;

defined('ABSPATH') || exit;

/**
 * Hợp đồng Driver ngôn ngữ cho Trợ lý AI Tìm Gạch (ADR D-32, D-47).
 * Cho phép chọn `RuleOnlyDriver` (0đ), `AnthropicDriver`, `GeminiDriver` hoặc mở rộng qua filter `drv/advisor/llm_client`.
 */
interface LlmClientInterface {
    /**
     * Mã định danh driver (`rule_only`, `anthropic`, `gemini`, ...).
     */
    public function getName(): string;

    /**
     * Driver có gọi API mạng ra ngoài hay chạy cục bộ trên server.
     */
    public function isExternal(): bool;

    /**
     * Gọi 1: Phân tích phạm vi (`in_scope`), ý định (`intent`), các `slots` chuẩn và 1 câu hỏi làm rõ.
     *
     * @param array<string, mixed> $currentSlots
     * @param array<string, mixed> $allowedSchema
     * @return array<string, mixed>
     */
    public function extractIntentAndSlots(string $message, array $currentSlots, array $allowedSchema): array;

    /**
     * Gọi 2: Diễn giải kết quả trong danh sách ID ứng viên và các đoạn tri thức (`kb_refs`) đã chọn.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function explainRecommendations(array $context): array;
}

