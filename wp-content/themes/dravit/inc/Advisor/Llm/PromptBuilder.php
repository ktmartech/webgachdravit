<?php
declare(strict_types=1);

namespace Dravit\Advisor\Llm;

use Dravit\Advisor\KnowledgeBase;

defined('ABSPATH') || exit;

/**
 * Đóng gói ngữ cảnh khép kín cho LLM (Lớp L2 + L6 - docs/13 §1, §7).
 * Mọi nội dung từ khách đều bị bọc trong `<tin_nhan_khach>` và khử thẻ đóng giả mạo để chống Prompt Injection.
 */
final class PromptBuilder {
    private KnowledgeBase $kb;

    public function __construct(?KnowledgeBase $kb = null) {
        $this->kb = $kb ?? new KnowledgeBase();
    }

    public function loadTemplate(string $name): string {
        $safe = (string) preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
        $path = DRV_DIR . '/config/prompts/' . $safe . '.md';
        if (!is_readable($path)) {
            return '';
        }
        $raw = file_get_contents($path);
        return is_string($raw) ? trim($raw) : '';
    }

    /**
     * Khử mọi ký tự thoát khối dữ liệu XML `<...>` từ chuỗi đầu vào của khách (L6).
     */
    public static function sanitizeUntrustedData(string $input, int $maxChars = 500): string {
        $clean = wp_strip_all_tags($input);
        $clean = str_replace(['<', '>'], ['(', ')'], $clean);
        $clean = (string) preg_replace('/\s+/u', ' ', trim($clean));

        if (mb_strlen($clean, 'UTF-8') > $maxChars) {
            $clean = mb_substr($clean, 0, $maxChars, 'UTF-8');
        }

        return $clean;
    }

    /**
     * Dựng cặp `[system, user]` cho Gọi 1: Phân tích phạm vi + ý định + slot chuẩn.
     *
     * @param array<string, mixed> $currentSlots
     * @param array<string, mixed> $allowedSchema
     * @return array{system: string, user: string}
     */
    public function buildSlotExtractPrompt(string $message, array $currentSlots, array $allowedSchema): array {
        $system = $this->loadTemplate('slot-extract');
        $schema = (string) wp_json_encode($allowedSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cur    = (string) wp_json_encode($currentSlots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $safeMsg = self::sanitizeUntrustedData($message);

        $user = "<schema_hop_le>\n{$schema}\n</schema_hop_le>\n"
            . "<slot_hien_tai>\n{$cur}\n</slot_hien_tai>\n"
            . "<tin_nhan_khach>\n{$safeMsg}\n</tin_nhan_khach>";

        return [
            'system' => $system,
            'user'   => $user,
        ];
    }

    /**
     * Dựng gói `[system_rules, knowledge_block, user]` cho Gọi 2: Diễn giải ứng viên (hỗ trợ Prompt Caching cho khối tĩnh).
     *
     * @param array<string, mixed> $context
     * @return array{system_rules: string, knowledge_block: string, user: string}
     */
    public function buildExplainPrompt(array $context): array {
        $systemRules = $this->loadTemplate('explain-rank');

        $topics = isset($context['kb_topics']) && is_array($context['kb_topics'])
            ? array_map('strval', $context['kb_topics'])
            : ['an-toan', 'kich-thuoc', 'hao-hut', 'chat-lieu', 'chinh-sach', 'faq'];

        $kbBlock = "<tri_thuc>\n" . $this->kb->buildPromptKnowledgeContext($topics) . "\n</tri_thuc>";

        $candidates = isset($context['candidates']) && is_array($context['candidates']) ? $context['candidates'] : [];
        $compact    = [];
        foreach ($candidates as $cand) {
            if (!is_array($cand)) {
                continue;
            }
            $isQuote   = ($cand['price_state'] ?? '') === 'quote_only';
            $compact[] = [
                'id'               => (int) ($cand['id'] ?? 0),
                'sku'              => (string) ($cand['sku'] ?? ''),
                'title'            => (string) ($cand['title'] ?? ''),
                'price_state'      => (string) ($cand['price_state'] ?? 'quote_only'),
                'price_m2'         => $isQuote ? null : (int) ($cand['effective_price_m2'] ?? 0),
                'dcof_r'           => (string) strtoupper((string) ($cand['dcof_slug'] ?? '')),
                'water_absorption' => $cand['water_absorption'] ?? null,
                'area_box'         => $cand['area_box'] ?? null,
                'pieces_per_box'   => $cand['pieces_per_box'] ?? null,
                'stock_label'      => (string) ($cand['stock_label'] ?? ''),
                'attrs'            => $cand['attrs'] ?? [],
                'calc'             => $cand['calc'] ?? null,
            ];
        }

        $candJson  = (string) wp_json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $slotsJson = (string) wp_json_encode($context['slots'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $safeMsg   = self::sanitizeUntrustedData((string) ($context['message'] ?? ''));

        $user = "<du_lieu>\n"
            . "intent: " . (string) ($context['intent'] ?? 'recommend') . "\n"
            . "required_r: " . (int) ($context['required_r'] ?? 0) . "\n"
            . "slots: {$slotsJson}\n"
            . "</du_lieu>\n"
            . "<ung_vien>\n{$candJson}\n</ung_vien>\n"
            . "<tin_nhan_khach>\n{$safeMsg}\n</tin_nhan_khach>";

        return [
            'system_rules'    => $systemRules,
            'knowledge_block' => $kbBlock,
            'user'            => $user,
        ];
    }

    /**
     * Bóc đối tượng JSON từ chuỗi phản hồi của LLM (kể cả khi LLM bọc trong ```json ... ```).
     *
     * @return array<string, mixed>|null
     */
    public static function decodeJsonResponse(string $rawText): ?array {
        $trimmed = trim($rawText);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $m) === 1) {
            $trimmed = $m[1];
        } elseif (!str_starts_with($trimmed, '{')) {
            $first = strpos($trimmed, '{');
            $last  = strrpos($trimmed, '}');
            if ($first !== false && $last !== false && $last > $first) {
                $trimmed = substr($trimmed, $first, $last - $first + 1);
            }
        }

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }
}

