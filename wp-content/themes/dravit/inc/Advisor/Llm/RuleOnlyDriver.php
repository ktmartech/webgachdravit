<?php
declare(strict_types=1);

namespace Dravit\Advisor\Llm;

use Dravit\Advisor\Contracts\LlmClientInterface;
use Dravit\Advisor\KnowledgeBase;
use Dravit\Advisor\ScopeGate;
use Dravit\Advisor\SlotNormalizer;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Driver mặc định hoạt động 100% bằng quy tắc kỹ thuật & từ điển trên Server (`0đ` phí API, phản hồi `< 200ms` - ADR D-32).
 * Đồng thời đóng vai trò lõi diễn đạt an toàn tuyệt đối cho `Fallback`.
 */
final class RuleOnlyDriver implements LlmClientInterface {
    private SlotNormalizer $normalizer;
    private ScopeGate $scopeGate;
    private KnowledgeBase $kb;

    public function __construct(
        ?SlotNormalizer $normalizer = null,
        ?ScopeGate $scopeGate = null,
        ?KnowledgeBase $kb = null
    ) {
        $this->kb         = $kb ?? new KnowledgeBase();
        $this->normalizer = $normalizer ?? new SlotNormalizer($this->kb);
        $this->scopeGate  = $scopeGate ?? new ScopeGate();
    }

    public function getName(): string {
        return 'rule_only';
    }

    public function isExternal(): bool {
        return false;
    }

    /**
     * @param array<string, mixed> $currentSlots
     * @param array<string, mixed> $allowedSchema
     * @return array<string, mixed>
     */
    public function extractIntentAndSlots(string $message, array $currentSlots, array $allowedSchema): array {
        unset($allowedSchema);

        $extracted = $this->normalizer->extractFromText($message);
        $merged    = $this->normalizer->mergeSlots($currentSlots, $extracted);
        $gate      = $this->scopeGate->evaluate($message, $merged);

        $missing = $this->resolveNextMissingSlot($merged);
        $clarify = $missing !== null ? $this->getClarificationForSlot($missing) : null;

        return [
            'in_scope'         => $gate['in_scope'],
            'intent'           => $gate['intent'],
            'reason'           => $gate['reason'],
            'slots'            => $merged,
            'kb_refs'          => $gate['kb_refs'],
            'missing_priority' => $missing,
            'clarification'    => $clarify !== null ? $clarify['question'] : null,
            'quick_chips'      => $clarify !== null ? $clarify['chips'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function explainRecommendations(array $context): array {
        $intent     = isset($context['intent']) && is_string($context['intent']) ? $context['intent'] : 'recommend';
        $slots      = isset($context['slots']) && is_array($context['slots']) ? $context['slots'] : [];
        $candidates = isset($context['candidates']) && is_array($context['candidates']) ? $context['candidates'] : [];
        $requiredR  = isset($context['required_r']) ? (int) $context['required_r'] : 0;
        $wastePct   = isset($context['waste_pct']) ? (int) $context['waste_pct'] : 5;
        $relaxed    = isset($context['relaxed']) && is_array($context['relaxed']) ? $context['relaxed'] : [];
        $tradeoff   = isset($context['tradeoff']) && is_string($context['tradeoff']) ? $context['tradeoff'] : null;
        $seedRefs   = isset($context['kb_refs']) && is_array($context['kb_refs']) ? array_map('strval', $context['kb_refs']) : [];

        $rules    = Config::get('ai-rules');
        $messages = isset($rules['messages']) && is_array($rules['messages']) ? $rules['messages'] : [];

        // 1. Trường hợp trả lời thuần Kiến thức (`knowledge`) hoặc Chính sách (`policy`)
        if (in_array($intent, ['knowledge', 'policy'], true)) {
            $validRefs = array_values(array_filter($seedRefs, fn (string $r): bool => $this->kb->isValidRef($r)));
            if ($validRefs === []) {
                return [
                    'summary'       => (string) ($messages['no_info'] ?? 'Mình chưa có thông tin chi tiết về nội dung này.'),
                    'items'         => [],
                    'tradeoff'      => null,
                    'kb_refs'       => [],
                    'clarification' => 'Bạn có muốn chuyển sang chọn mẫu theo không gian phòng không?',
                    'quick_chips'   => ['Phòng khách', 'Nhà tắm', 'Phòng bếp', 'Nhắn Zalo kỹ thuật'],
                ];
            }

            $sec     = $this->kb->getSectionByRef($validRefs[0]);
            $summary = $sec !== null && $sec['content'] !== ''
                ? $this->truncateClean($sec['content'], 380)
                : (string) ($messages['no_info'] ?? '');

            $itemRows = [];
            foreach (array_slice($candidates, 0, 3) as $cand) {
                if (is_array($cand)) {
                    $itemRows[] = [
                        'id'  => (int) ($cand['id'] ?? 0),
                        'why' => $this->buildCandidateFactWhy($cand, $slots),
                    ];
                }
            }

            return [
                'summary'       => $summary,
                'items'         => $itemRows,
                'tradeoff'      => $tradeoff,
                'kb_refs'       => $validRefs,
                'clarification' => 'Bạn đang tìm gạch cho khu vực nào để mình lọc mẫu áp dụng đúng tiêu chuẩn trên?',
                'quick_chips'   => ['Nhà tắm chống trơn', 'Phòng khách 28 m²', 'Sân vườn R12'],
            ];
        }

        // 2. Trường hợp Chuyển nhân viên (`handoff`)
        if ($intent === 'handoff') {
            $validRefs = array_values(array_filter($seedRefs, fn (string $r): bool => $this->kb->isValidRef($r)));
            return [
                'summary'       => 'Mình đã ghi nhận tóm tắt nhu cầu công trình của bạn. Với các đơn hàng cần giữ lô màu (Shade), gia công cắt CNC hoặc bóc tách bản vẽ, chuyên viên kỹ thuật DRAVIT sẽ hỗ trợ trực tiếp qua Zalo.',
                'items'         => [],
                'tradeoff'      => null,
                'kb_refs'       => $validRefs !== [] ? $validRefs : ['kb-chinh-sach#showroom'],
                'clarification' => null,
                'quick_chips'   => ['Xem bảng dự toán BOQ', 'Mở bộ lọc 14 tiêu chí'],
            ];
        }

        // 3. Trường hợp Gợi ý / So sánh / Tính thùng (`recommend` | `compare` | `calc`)
        if ($candidates === []) {
            return [
                'summary'       => (string) ($messages['empty_catalog'] ?? 'Hiện chưa có mẫu khớp hoàn toàn điều kiện này trong kho sẵn có.'),
                'items'         => [],
                'tradeoff'      => $tradeoff,
                'kb_refs'       => array_values(array_filter($seedRefs, fn (string $r): bool => $this->kb->isValidRef($r))),
                'clarification' => 'Bạn có thể nới bớt tiêu chí màu sắc hoặc nhắn Zalo để kiểm tra lô hàng dự án.',
                'quick_chips'   => ['Xem toàn bộ kho gạch', 'Nhắn Zalo kỹ thuật'],
            ];
        }

        $summary  = $this->buildStructuredSummary($candidates, $slots, $requiredR, $wastePct, $relaxed);
        $itemRows = [];
        foreach ($candidates as $cand) {
            if (!is_array($cand)) {
                continue;
            }
            $itemRows[] = [
                'id'  => (int) ($cand['id'] ?? 0),
                'why' => $this->buildCandidateFactWhy($cand, $slots),
            ];
        }

        $missing = $this->resolveNextMissingSlot($slots);
        $clarify = $missing !== null ? $this->getClarificationForSlot($missing) : null;
        $validRefs = array_values(array_filter($seedRefs, fn (string $r): bool => $this->kb->isValidRef($r)));

        return [
            'summary'       => $summary,
            'items'         => $itemRows,
            'tradeoff'      => $tradeoff,
            'kb_refs'       => $validRefs,
            'clarification' => $clarify !== null ? $clarify['question'] : null,
            'quick_chips'   => $clarify !== null ? $clarify['chips'] : [],
        ];
    }

    /**
     * Ghép câu tóm tắt 100% từ dữ kiện thực tế của danh sách ứng viên.
     *
     * @param array<int, mixed>    $candidates
     * @param array<string, mixed> $slots
     * @param array<int, mixed>    $relaxed
     */
    private function buildStructuredSummary(
        array $candidates,
        array $slots,
        int $requiredR,
        int $wastePct,
        array $relaxed
    ): string {
        $count      = count($candidates);
        $facets     = Config::get('facets');
        $spaceSlug  = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';
        $spaceLabel = $spaceSlug !== ''
            ? mb_strtolower((string) ($facets['attributes']['khong-gian']['terms'][$spaceSlug]['name'] ?? $spaceSlug), 'UTF-8')
            : 'nhu cầu của bạn';

        $parts = [];
        if ($requiredR > 0) {
            $parts[] = sprintf(
                'Đã lọc %d mẫu gạch phù hợp cho %s, khóa cứng tiêu chuẩn an toàn Chống trượt từ R%d trở lên.',
                $count,
                $spaceLabel,
                $requiredR
            );
        } else {
            $parts[] = sprintf('Đã chọn lọc %d mẫu gạch trong kho DRAVIT phù hợp nhất cho %s.', $count, $spaceLabel);
        }

        if (!empty($slots['area_m2']) && is_numeric($slots['area_m2'])) {
            $areaStr = str_replace('.', ',', rtrim(rtrim(number_format((float) $slots['area_m2'], 1, '.', ''), '0'), '.'));
            $parts[] = $wastePct > 0
                ? sprintf('Số thùng trên từng thẻ đã cộng %d%% hao hụt thi công cho diện tích %s m².', $wastePct, $areaStr)
                : sprintf('Số thùng trên từng thẻ đang tính sát theo diện tích %s m² (0%% hao hụt).', $areaStr);
        }

        if ($relaxed !== []) {
            $labelMap = [
                'budget'  => 'ngân sách',
                'color'   => 'tông màu',
                'surface' => 'bề mặt',
                'style'   => 'phong cách',
                'size'    => 'khổ gạch',
                'vein'    => 'họa tiết vân',
            ];
            $names = [];
            foreach ($relaxed as $rKey) {
                if (is_string($rKey) && isset($labelMap[$rKey])) {
                    $names[] = $labelMap[$rKey];
                }
            }
            if ($names !== []) {
                $parts[] = 'Đã mở rộng nhẹ tiêu chí ' . implode(', ', array_unique($names)) . ' để có phương án thay thế sát nhất mà vẫn giữ nguyên chuẩn an toàn.';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Tạo lý do `why` ngắn gọn 1 câu từ đúng thông số thật của SKU (không bao giờ nêu giá nếu `quote_only`).
     *
     * @param array<string, mixed> $cand
     * @param array<string, mixed> $slots
     */
    private function buildCandidateFactWhy(array $cand, array $slots): string {
        unset($slots);

        $facets = Config::get('facets');
        $attrs  = isset($cand['attrs']) && is_array($cand['attrs']) ? $cand['attrs'] : [];
        $bits   = [];

        $sizeSlug = $attrs['pa_kich-thuoc'][0] ?? '';
        if (is_string($sizeSlug) && $sizeSlug !== '') {
            $sizeLabel = (string) ($facets['attributes']['kich-thuoc']['terms'][$sizeSlug]['short_label'] ?? $sizeSlug);
            $bits[]    = 'Khổ ' . $sizeLabel . ' cm';
        }

        $matSlug = $attrs['pa_chat-lieu'][0] ?? '';
        if (is_string($matSlug) && $matSlug !== '') {
            $matLabel = (string) ($facets['attributes']['chat-lieu']['terms'][$matSlug]['short_label'] ?? $matSlug);
            $bits[]   = 'xương ' . $matLabel;
        }

        $surfSlug = $attrs['pa_be-mat'][0] ?? '';
        if (is_string($surfSlug) && $surfSlug !== '') {
            $surfLabel = (string) ($facets['attributes']['be-mat']['terms'][$surfSlug]['short_label'] ?? $surfSlug);
            $bits[]    = 'bề mặt ' . $surfLabel;
        }

        $dcofLevel = (int) ($cand['dcof_level'] ?? 0);
        if ($dcofLevel >= 9) {
            $bits[] = 'Chống trượt R' . $dcofLevel;
        }

        if (isset($cand['water_absorption']) && is_numeric($cand['water_absorption'])) {
            $wStr   = str_replace('.', ',', rtrim(rtrim(number_format((float) $cand['water_absorption'], 2, '.', ''), '0'), ','));
            $bits[] = 'hút nước ' . $wStr . '%';
        }

        $calc = isset($cand['calc']) && is_array($cand['calc']) ? $cand['calc'] : null;
        if ($calc !== null && !empty($calc['boxes'])) {
            $bits[] = sprintf('cần %d thùng', (int) $calc['boxes']);
        }

        if (($cand['price_state'] ?? '') === 'quote_only') {
            $bits[] = 'báo giá chiết khấu theo khối lượng';
        } elseif (!empty($cand['effective_price_m2']) && is_numeric($cand['effective_price_m2'])) {
            $bits[] = number_format((int) $cand['effective_price_m2'], 0, ',', '.') . 'đ/m²';
        }

        return ucfirst(implode(', ', $bits)) . '.';
    }

    /**
     * @param array<string, mixed> $slots
     */
    private function resolveNextMissingSlot(array $slots): ?string {
        $flow     = $this->kb->getFlowConfig();
        $priority = isset($flow['slot_priority']) && is_array($flow['slot_priority'])
            ? $flow['slot_priority']
            : ['space', 'area_m2', 'special', 'vein', 'color', 'budget_max_vnd'];

        foreach ($priority as $slotKey) {
            if (!is_string($slotKey)) {
                continue;
            }
            if ($slotKey === 'special') {
                // Nếu đã ở không gian có khóa an toàn R>=10 thì không cần hỏi lặp lại điều kiện ẩm ướt
                $space = (string) ($slots['space'] ?? '');
                if (in_array($space, ['nha-tam', 'phong-bep', 'san-vuon', 'ngoai-troi', 'ho-boi', 'gara-o-to'], true)) {
                    continue;
                }
                if (empty($slots['special'])) {
                    return 'special';
                }
                continue;
            }

            if (empty($slots[$slotKey])) {
                return $slotKey;
            }
        }

        return null;
    }

    /**
     * @return array{question: string, chips: array<int, string>}|null
     */
    private function getClarificationForSlot(string $slotKey): ?array {
        $flow = $this->kb->getFlowConfig();
        $map  = $flow['clarifications'] ?? [];
        if (!is_array($map) || !isset($map[$slotKey]) || !is_array($map[$slotKey])) {
            return null;
        }

        $entry    = $map[$slotKey];
        $question = isset($entry['question']) && is_string($entry['question']) ? $entry['question'] : '';
        $chips    = isset($entry['chips']) && is_array($entry['chips']) ? array_map('strval', $entry['chips']) : [];

        if ($question === '') {
            return null;
        }

        return [
            'question' => str_replace(['—', '–'], '-', $question),
            'chips'    => $chips,
        ];
    }

    private function truncateClean(string $text, int $maxLen): string {
        $clean = str_replace(['—', '–', '**'], ['-', '-', ''], trim($text));
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);
        if (mb_strlen($clean, 'UTF-8') <= $maxLen) {
            return $clean;
        }

        $cut = mb_substr($clean, 0, $maxLen, 'UTF-8');
        $pos = mb_strrpos($cut, ' ', 0, 'UTF-8');
        return ($pos !== false ? mb_substr($cut, 0, $pos, 'UTF-8') : $cut) . '...';
    }
}

