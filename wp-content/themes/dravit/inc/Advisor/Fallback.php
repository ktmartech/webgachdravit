<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Advisor\Llm\RuleOnlyDriver;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Chế độ dự phòng an toàn tuyệt đối (timeout > 8s, lỗi API LLM, hết hạn mức ngày hoặc Validator bác bỏ - docs/13 §8).
 * Ghép câu trực tiếp từ dữ kiện có cấu trúc trên server thông qua `RuleOnlyDriver` và bật cờ `degraded = true`.
 */
final class Fallback {
    private RuleOnlyDriver $ruleDriver;

    public function __construct(?RuleOnlyDriver $ruleDriver = null) {
        $this->ruleDriver = $ruleDriver ?? new RuleOnlyDriver();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildDegradedExplanation(array $context, string $failureReason = 'llm_fallback'): array {
        $rules    = Config::get('ai-rules');
        $messages = isset($rules['messages']) && is_array($rules['messages']) ? $rules['messages'] : [];
        $result   = $this->ruleDriver->explainRecommendations($context);

        $result['degraded']        = true;
        $result['degraded_reason'] = $failureReason;
        $result['degraded_notice'] = (string) ($messages['degraded_notice'] ?? 'Đang dùng chế độ gợi ý nhanh từ bộ quy tắc kỹ thuật DRAVIT.');

        return $result;
    }
}

