<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Catalog\SearchNormalizer;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Cổng kiểm soát phạm vi đóng kín & chống Prompt Injection (Lớp L3 + L6 - docs/13 §1, §4).
 * Phân loại 7 intent chuẩn: `recommend | compare | calc | knowledge | policy | handoff | out_of_scope`.
 */
final class ScopeGate {
    /** @var array<int, string> */
    public const VALID_INTENTS = [
        'recommend',
        'compare',
        'calc',
        'knowledge',
        'policy',
        'handoff',
        'out_of_scope',
    ];

    /**
     * Đánh giá phạm vi, phát hiện chèn lệnh / hỏi đối thủ / chủ đề ngoài lề và gán `intent` + `kb_refs` gợi ý.
     *
     * @param array<string, mixed> $slots
     * @return array{
     *   in_scope: bool,
     *   intent: string,
     *   reason: string,
     *   kb_refs: array<int, string>
     * }
     */
    public function evaluate(string $message, array $slots): array {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return [
                'in_scope' => true,
                'intent'   => 'recommend',
                'reason'   => 'empty_init',
                'kb_refs'  => [],
            ];
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');
        $ascii = SearchNormalizer::asciiFold($lower);

        // 1. Chặn đứng Prompt Injection / Jailbreak / yêu cầu bịa giá (L6)
        if ($this->isPromptInjection($lower, $ascii)) {
            return [
                'in_scope' => false,
                'intent'   => 'out_of_scope',
                'reason'   => 'prompt_injection',
                'kb_refs'  => [],
            ];
        }

        // 2. Chặn hỏi / so sánh thương hiệu đối thủ ngoài danh mục DRAVIT (L1/L3/L5)
        if ($this->mentionsCompetitor($lower, $ascii)) {
            return [
                'in_scope' => false,
                'intent'   => 'out_of_scope',
                'reason'   => 'competitor',
                'kb_refs'  => [],
            ];
        }

        // 3. Chặn các chủ đề hoàn toàn ngoài phạm vi gạch ốp lát (thời tiết, code, y tế, chứng khoán, kết cấu móng...)
        if ($this->isExplicitlyUnrelated($ascii) && !SlotNormalizer::hasAnyConstraint($slots)) {
            return [
                'in_scope' => false,
                'intent'   => 'out_of_scope',
                'reason'   => 'unrelated_topic',
                'kb_refs'  => [],
            ];
        }

        // 4. Ý định Handoff chuyển chuyên viên trực tiếp / Zalo
        if (preg_match('/\b(gap nhan vien|tu van vien|nhan zalo|goi dien|chuyen nhan vien|bao gia du an lon|cat cnc theo ban ve|gui mau thuc te)\b/', $ascii) === 1) {
            return [
                'in_scope' => true,
                'intent'   => 'handoff',
                'reason'   => 'explicit_handoff',
                'kb_refs'  => ['kb-chinh-sach#showroom'],
            ];
        }

        // 5. Ý định Chính sách bán hàng (`policy`)
        $policyRefs = $this->detectPolicyRefs($ascii);
        if ($policyRefs !== [] && !SlotNormalizer::hasAnyConstraint($slots)) {
            return [
                'in_scope' => true,
                'intent'   => 'policy',
                'reason'   => 'policy_topic',
                'kb_refs'  => $policyRefs,
            ];
        }

        // 6. Ý định Hỏi kiến thức kỹ thuật (`knowledge`) khi không phải câu tìm mẫu cụ thể
        $knowledgeRefs = $this->detectKnowledgeRefs($ascii);
        $asksExplain   = preg_match('/\b(la gi|khac gi|khac nhau|co nen|tai sao|vi sao|cach ve sinh|cach tay|dung keo gi|mach ron bao nhieu|hao hut bao nhieu)\b/', $ascii) === 1;
        if ($knowledgeRefs !== [] && ($asksExplain || !SlotNormalizer::hasAnyConstraint($slots))) {
            return [
                'in_scope' => true,
                'intent'   => 'knowledge',
                'reason'   => 'knowledge_topic',
                'kb_refs'  => $knowledgeRefs,
            ];
        }

        // 7. Ý định Tính toán số thùng / viên (`calc`)
        if (
            !empty($slots['area_m2'])
            && preg_match('/\b(may thung|bao nhieu thung|bao nhieu vien|quy doi|tinh so thung|can mua may thung)\b/', $ascii) === 1
        ) {
            return [
                'in_scope' => true,
                'intent'   => 'calc',
                'reason'   => 'unit_calculation',
                'kb_refs'  => ['kb-hao-hut-thung#hao-hut-5', 'kb-hao-hut-thung#lam-tron-thung'],
            ];
        }

        // 8. Ý định So sánh mẫu (`compare`)
        if (preg_match('/\b(so sanh|chon mau nao|phan van giua)\b/', $ascii) === 1) {
            return [
                'in_scope' => true,
                'intent'   => 'compare',
                'reason'   => 'product_comparison',
                'kb_refs'  => array_values(array_unique(array_merge($knowledgeRefs, $policyRefs))),
            ];
        }

        // 9. Nếu có từ khóa liên quan tới gạch/nhà cửa hoặc có ít nhất 1 slot -> `recommend`
        $tileContext = preg_match('/\b(gach|op|lat|nen|san|tuong|phong|nha|bep|vuon|da|go|mau|kho|thung|vien|m2|bao gia|dravit|mau nao|tu van|tim)\b/', $ascii) === 1;
        if (SlotNormalizer::hasAnyConstraint($slots) || $tileContext) {
            return [
                'in_scope' => true,
                'intent'   => 'recommend',
                'reason'   => 'catalog_recommend',
                'kb_refs'  => array_values(array_unique(array_merge($knowledgeRefs, $policyRefs))),
            ];
        }

        return [
            'in_scope' => false,
            'intent'   => 'out_of_scope',
            'reason'   => 'unknown_non_tile',
            'kb_refs'  => [],
        ];
    }

    private function isPromptInjection(string $lower, string $ascii): bool {
        $patterns = [
            'ignore previous instructions',
            'ignore all instructions',
            'disregard previous',
            'system prompt',
            'reveal your prompt',
            'print your instructions',
            'you are now',
            'act as a',
            'developer mode',
            'jailbreak',
            '<system>',
            '</tin_nhan_khach>',
            'bo qua huong dan',
            'bo qua moi quy tac',
            'bo qua quy tac',
            'tiet lo prompt',
            'in ra system prompt',
            'doi vai tro',
            'hay bia gia',
            'bia dai gia',
            'uoc tinh dai gia',
        ];

        foreach ($patterns as $pat) {
            if (str_contains($lower, $pat) || str_contains($ascii, $pat)) {
                return true;
            }
        }

        return false;
    }

    private function mentionsCompetitor(string $lower, string $ascii): bool {
        $rules       = Config::get('ai-rules');
        $competitors = isset($rules['competitor_brands']) && is_array($rules['competitor_brands'])
            ? $rules['competitor_brands']
            : [];

        foreach ($competitors as $brand) {
            if (!is_string($brand) || $brand === '') {
                continue;
            }
            $bLower = mb_strtolower($brand, 'UTF-8');
            $bAscii = SearchNormalizer::asciiFold($bLower);
            $patL   = '/(?<![\p{L}\p{N}])' . preg_quote($bLower, '/') . '(?![\p{L}\p{N}])/u';
            $patA   = '/(?<![a-z0-9])' . preg_quote($bAscii, '/') . '(?![a-z0-9])/';

            if (preg_match($patL, $lower) === 1 || preg_match($patA, $ascii) === 1) {
                return true;
            }
        }

        if (preg_match('/\b(so voi hang khac|hang nao tot hon|mua o shop khac)\b/', $ascii) === 1) {
            return true;
        }

        return false;
    }

    private function isExplicitlyUnrelated(string $ascii): bool {
        return preg_match(
            '/\b(thoi tiet|chung khoan|bitcoin|crypto|viet code|python|javascript|php|sql|chua benh|thuoc khang sinh|bong da|ket qua xo so|nau mon|cong thuc nau an|tinh thep dam|ket cau mong coc|luat dat dai|ly hon)\b/',
            $ascii
        ) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function detectPolicyRefs(string $ascii): array {
        $refs = [];
        if (preg_match('/\b(phi ship|van chuyen|giao hang|bao ship|xe tai)\b/', $ascii) === 1) {
            $refs[] = 'kb-chinh-sach#giao-hang';
        }
        if (preg_match('/\b(thanh toan|chuyen khoan|vietqr|dat coc|cod)\b/', $ascii) === 1) {
            $refs[] = 'kb-chinh-sach#thanh-toan';
        }
        if (preg_match('/\b(hoa don|vat|gtgt|ma so thue)\b/', $ascii) === 1) {
            $refs[] = 'kb-chinh-sach#vat';
        }
        if (preg_match('/\b(doi tra|bao hanh|tra hang|dia chi showroom|xem mau o dau)\b/', $ascii) === 1) {
            $refs[] = 'kb-chinh-sach#showroom';
        }

        return $refs;
    }

    /**
     * @return array<int, string>
     */
    private function detectKnowledgeRefs(string $ascii): array {
        $refs = [];
        if (preg_match('/\b(chong truot|chong tron|chi so r|r9|r10|r11|r12|dcof)\b/', $ascii) === 1) {
            $refs[] = 'kb-an-toan-chong-truot#r-tong-quan';
        }
        if (preg_match('/\b(bong kieng nha tam|gach bong lat nha tam)\b/', $ascii) === 1) {
            $refs[] = 'kb-faq#faq-bong-nha-tam';
            $refs[] = 'kb-an-toan-chong-truot#r-nha-tam';
        }
        if (preg_match('/\b(porcelain|ceramic|ban su|da nung ket|hut nuoc|do nom|mo hoi)\b/', $ascii) === 1) {
            $refs[] = 'kb-be-mat-chat-lieu#porcelain-chi-tiet';
        }
        if (preg_match('/\b(hao hut|lam tron thung|lech mau|shade|caliber)\b/', $ascii) === 1) {
            $refs[] = 'kb-hao-hut-thung#hao-hut-5';
        }
        if (preg_match('/\b(keo dan gach|ho dau|xi mang|khe ron|mach ron|ke can bang)\b/', $ascii) === 1) {
            $refs[] = 'kb-thi-cong-co-ban#keo-porcelain';
            $refs[] = 'kb-thi-cong-co-ban#khe-ron';
        }
        if (preg_match('/\b(ve sinh|tay xi mang|bao duong|cha ron|epoxy|axit)\b/', $ascii) === 1) {
            $refs[] = 'kb-bao-duong#mang-xi-mang';
        }
        if (preg_match('/\b(lien he bao gia|chua co gia|mua le vien)\b/', $ascii) === 1) {
            $refs[] = 'kb-faq#bao-gia-du-an';
        }

        return array_values(array_unique($refs));
    }
}
