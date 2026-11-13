<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Catalog\SearchNormalizer;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Hậu kiểm hoàn toàn bằng code sau mỗi lần gọi LLM (Lớp L5 - docs/13 §8):
 * - Chặn ID sản phẩm ngoài tập ứng viên (`items[].id`).
 * - Chặn số liệu bịa đặt (giá, m², thùng, R, độ hút nước không khớp dữ liệu server).
 * - Chặn tuyệt đối bất kỳ con số tiền hoặc so sánh rẻ/đắt cạnh sản phẩm `quote_only`.
 * - Chặn URL, SĐT, Email ngoài danh sách trắng DRAVIT.
 * - Chặn thương hiệu đối thủ ngoài catalog.
 * - Kiểm tra `kb_refs[]` tồn tại thật trong `KnowledgeBase`.
 */
final class Validator {
    private KnowledgeBase $kb;

    /** @var array<string, mixed> */
    private array $rules;

    /**
     * @param array<string, mixed>|null $rulesOverride
     */
    public function __construct(?KnowledgeBase $kb = null, ?array $rulesOverride = null) {
        $this->kb    = $kb ?? new KnowledgeBase();
        $this->rules = $rulesOverride ?? Config::get('ai-rules');
    }

    /**
     * Kiểm định phản hồi có cấu trúc từ LLM so với danh sách ứng viên & dữ kiện server.
     *
     * @param array<string, mixed>             $llmOutput
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed>             $queryContext
     * @return array{
     *   valid: bool,
     *   errors: array<int, string>,
     *   sanitized: array<string, mixed>
     * }
     */
    public function validate(array $llmOutput, array $candidates, array $queryContext = []): array {
        $errors = [];

        $candidateById = [];
        foreach ($candidates as $cand) {
            $cid = (int) ($cand['id'] ?? 0);
            if ($cid > 0) {
                $candidateById[$cid] = $cand;
            }
        }

        $intent     = isset($queryContext['intent']) && is_string($queryContext['intent']) ? $queryContext['intent'] : 'recommend';
        $maxResults = max(1, (int) ($this->rules['max_results'] ?? 6));

        $summary       = $this->normalizeText((string) ($llmOutput['summary'] ?? ''));
        $tradeoff      = isset($llmOutput['tradeoff']) && is_string($llmOutput['tradeoff']) && trim($llmOutput['tradeoff']) !== ''
            ? $this->normalizeText($llmOutput['tradeoff'])
            : null;
        $clarification = isset($llmOutput['clarification']) && is_string($llmOutput['clarification']) && trim($llmOutput['clarification']) !== ''
            ? $this->normalizeText($llmOutput['clarification'])
            : null;

        if ($summary === '' || mb_strlen($summary, 'UTF-8') > 500) {
            $errors[] = 'invalid_summary_length';
        }

        // 1. Kiểm tra danh sách `items[].id` bắt buộc thuộc tập ứng viên
        $rawItems       = isset($llmOutput['items']) && is_array($llmOutput['items']) ? $llmOutput['items'] : [];
        $validatedItems = [];

        if (count($rawItems) > $maxResults) {
            $errors[] = 'items_exceed_limit';
        }

        foreach ($rawItems as $rawItem) {
            if (!is_array($rawItem)) {
                $errors[] = 'malformed_item';
                continue;
            }
            $id  = isset($rawItem['id']) ? (int) $rawItem['id'] : 0;
            $why = $this->normalizeText((string) ($rawItem['why'] ?? ''));

            if ($id <= 0 || !isset($candidateById[$id])) {
                $errors[] = 'hallucinated_product_id:' . $id;
                continue;
            }

            if (mb_strlen($why, 'UTF-8') > 260) {
                $errors[] = 'item_why_too_long:' . $id;
            }

            $cand = $candidateById[$id];

            // 2. Chặn tuyệt đối số tiền hoặc so sánh rẻ/đắt trên sản phẩm `quote_only`
            if (($cand['price_state'] ?? '') === 'quote_only' && $this->mentionsPriceOrCostComparison($why)) {
                $errors[] = 'quote_only_price_leak:' . $id;
            }

            // 3. Đối chiếu mọi con số trong `why` với tập dữ kiện hợp lệ của riêng sản phẩm đó + ngữ cảnh diện tích
            $allowedItemNums = $this->collectAllowedNumbers([$cand], $queryContext);
            if (!$this->verifyNumbersInText($why, $allowedItemNums)) {
                $errors[] = 'unverified_number_in_item_why:' . $id;
            }

            // 4. Chặn thương hiệu đối thủ, URL/SĐT/Email lạ trong `why`
            $this->checkContactsUrlsAndBrands($why, $errors);

            $validatedItems[] = [
                'id'  => $id,
                'why' => $why,
            ];
        }

        // Nếu toàn bộ ứng viên là quote_only hoặc câu summary nhắc tới tên/mã SKU quote_only kèm giá tiền -> chặn
        foreach ($candidateById as $cand) {
            if (($cand['price_state'] ?? '') !== 'quote_only') {
                continue;
            }
            $sku = (string) ($cand['sku'] ?? '');
            if ($sku !== '' && stripos($summary, $sku) !== false && $this->mentionsPriceOrCostComparison($summary)) {
                $errors[] = 'quote_only_price_leak_summary:' . $sku;
            }
        }

        // 5. Kiểm tra `kb_refs[]` tồn tại thật trong `KnowledgeBase` trước khi đối chiếu số liệu trong `summary`
        $rawRefs   = isset($llmOutput['kb_refs']) && is_array($llmOutput['kb_refs']) ? $llmOutput['kb_refs'] : [];
        $validRefs = [];
        foreach ($rawRefs as $ref) {
            if (!is_string($ref) || trim($ref) === '') {
                continue;
            }
            $cleanRef = trim($ref);
            if (!$this->kb->isValidRef($cleanRef)) {
                $errors[] = 'invalid_kb_ref:' . $cleanRef;
                continue;
            }
            $validRefs[] = $cleanRef;
        }

        if (in_array($intent, ['knowledge', 'policy'], true) && $validRefs === []) {
            $errors[] = 'missing_mandatory_kb_ref';
        }

        // 6. Đối chiếu toàn bộ con số trong `summary` và `tradeoff` với tập dữ kiện hợp lệ toàn phiên + các đoạn KB đã dẫn nguồn
        $globalAllowedNums = $this->collectAllowedNumbers($candidates, $queryContext, $validRefs);
        if (!$this->verifyNumbersInText($summary, $globalAllowedNums)) {
            $errors[] = 'unverified_number_in_summary';
        }
        if ($tradeoff !== null && !$this->verifyNumbersInText($tradeoff, $globalAllowedNums)) {
            $errors[] = 'unverified_number_in_tradeoff';
        }

        // 7. Kiểm tra URL, SĐT, Email & thương hiệu trong `summary`, `tradeoff`, `clarification`
        $this->checkContactsUrlsAndBrands($summary, $errors);
        if ($tradeoff !== null) {
            $this->checkContactsUrlsAndBrands($tradeoff, $errors);
        }
        if ($clarification !== null) {
            $this->checkContactsUrlsAndBrands($clarification, $errors);
        }

        $errors = array_values(array_unique($errors));

        return [
            'valid'     => $errors === [],
            'errors'    => $errors,
            'sanitized' => [
                'summary'       => $summary,
                'items'         => $validatedItems,
                'tradeoff'      => $tradeoff,
                'kb_refs'       => array_values(array_unique($validRefs)),
                'clarification' => $clarification,
            ],
        ];
    }

    private function normalizeText(string $text): string {
        // Loại bỏ em-dash `—` hoặc en-dash `–` sang dấu `-` chuẩn UI DRAVIT (docs/07, docs/13 §6)
        $clean = str_replace(['—', '–'], '-', trim($text));
        return (string) preg_replace('/\s+/u', ' ', $clean);
    }

    private function mentionsPriceOrCostComparison(string $text): bool {
        $lower = mb_strtolower($text, 'UTF-8');
        $ascii = SearchNormalizer::asciiFold($lower);

        if (preg_match('/\d+\s*(?:đ|d\/m|vnd|vnđ|k\/m|nghin|ngan|trieu)/iu', $lower) === 1) {
            return true;
        }
        if (preg_match('/\b(re hon|dat hon|gia khoang|gia tam|gia chi tu|tiet kiem hon)\b/', $ascii) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Thu thập toàn bộ các con số hợp lệ xuất phát từ dữ liệu sản phẩm thật, thông số KB chuẩn và câu hỏi của khách.
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed>             $queryContext
     * @param array<int, string>               $kbRefs
     * @return array<int, float>
     */
    private function collectAllowedNumbers(array $candidates, array $queryContext, array $kbRefs = []): array {
        // Các con số hằng số kỹ thuật đã duyệt trong KB (R9..R13, hao hụt 5% / 10%, E <= 0.5%, ron 1.5 - 3mm, dày 6 - 20mm...)
        $allowed = [
            0.0, 0.04, 0.05, 0.1, 0.12, 0.15, 0.2, 0.25, 0.3, 0.35, 0.5,
            1.0, 1.5, 2.0, 2.5, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0,
            11.0, 12.0, 13.0, 14.0, 15.0, 18.0, 20.0, 24.0, 30.0, 40.0, 45.0, 50.0, 60.0, 80.0, 90.0, 100.0, 120.0, 160.0, 240.0, 320.0,
        ];

        $slots = isset($queryContext['slots']) && is_array($queryContext['slots']) ? $queryContext['slots'] : [];
        if (isset($slots['area_m2']) && is_numeric($slots['area_m2'])) {
            $area      = (float) $slots['area_m2'];
            $allowed[] = $area;
            $allowed[] = round($area * 1.05, 2);
            $allowed[] = round($area * 1.10, 2);
        }
        if (isset($slots['budget_max_vnd']) && is_numeric($slots['budget_max_vnd'])) {
            $b         = (float) $slots['budget_max_vnd'];
            $allowed[] = $b;
            $allowed[] = round($b / 1000.0, 2);
        }
        if (isset($queryContext['required_r']) && is_numeric($queryContext['required_r'])) {
            $allowed[] = (float) $queryContext['required_r'];
        }
        if (isset($queryContext['waste_pct']) && is_numeric($queryContext['waste_pct'])) {
            $allowed[] = (float) $queryContext['waste_pct'];
        }

        $allowed[] = (float) count($candidates);

        foreach ($candidates as $cand) {
            foreach ([
                'w_mm',
                'h_mm',
                'thickness_mm',
                'pieces_per_box',
                'area_piece',
                'area_box',
                'kg_per_box',
                'water_absorption',
                'dcof_level',
                'match_score',
                'stock_qty_pieces',
            ] as $field) {
                if (isset($cand[$field]) && is_numeric($cand[$field])) {
                    $val       = (float) $cand[$field];
                    $allowed[] = $val;
                    if ($field === 'w_mm' || $field === 'h_mm') {
                        $allowed[] = round($val / 10.0, 2);
                    }
                }
            }

            if (isset($cand['stock_qty_pieces'], $cand['pieces_per_box']) && is_int($cand['stock_qty_pieces'])) {
                $allowed[] = (float) floor($cand['stock_qty_pieces'] / max(1, (int) $cand['pieces_per_box']));
            }

            if (($cand['price_state'] ?? '') !== 'quote_only') {
                foreach (['price_m2', 'sale_price_m2', 'effective_price_m2'] as $pf) {
                    if (!empty($cand[$pf]) && is_numeric($cand[$pf])) {
                        $p         = (float) $cand[$pf];
                        $allowed[] = $p;
                        $allowed[] = round($p / 1000.0, 2);
                    }
                }
            }

            if (isset($cand['calc']) && is_array($cand['calc'])) {
                foreach (['input_area_m2', 'waste_pct', 'target_m2', 'pieces', 'boxes', 'actual_m2', 'weight_kg', 'est_total_vnd'] as $cf) {
                    if (isset($cand['calc'][$cf]) && is_numeric($cand['calc'][$cf])) {
                        $allowed[] = (float) $cand['calc'][$cf];
                    }
                }
            }
        }

        // Bổ sung các con số nằm trong chính văn bản tri thức chuẩn `config/knowledge/*.md` đã được dẫn nguồn hợp lệ
        foreach ($kbRefs as $ref) {
            $sec = $this->kb->getSectionByRef($ref);
            if ($sec === null || $sec['content'] === '') {
                continue;
            }
            if (preg_match_all('/(?<![A-Za-z])(\d{1,3}(?:\.\d{3})+|\d+(?:[.,]\d+)?)(?![A-Za-z])/u', $sec['content'], $kbNumMatches) > 0) {
                foreach ($kbNumMatches[1] as $rawKbNum) {
                    $allowed[] = $this->parseVietnameseNumber($rawKbNum);
                }
            }
        }

        return $allowed;
    }

    /**
     * Tách các con số trong văn bản (loại trừ số nằm trong mã SKU/kệ/ref như DRV-8801M, R10, PEI IV, A-08) và đối chiếu.
     *
     * @param array<int, float> $allowedNumbers
     */
    private function verifyNumbersInText(string $text, array $allowedNumbers): bool {
        if ($text === '') {
            return true;
        }

        // Ẩn các mã định danh hợp lệ chứa chữ + số (DRV-8801M, DRV-61209, A-08, kb-..., m², R10..R13)
        $masked = (string) preg_replace('/\bDRV-[A-Z0-9]+\b/i', ' ', $text);
        $masked = (string) preg_replace('/\b[A-Z]-\d{1,3}\b/', ' ', $masked);
        $masked = (string) preg_replace('/m²|m2/iu', ' ', $masked);
        $masked = (string) preg_replace('/\bR(9|10|11|12|13)\b/i', ' ', $masked);

        if (preg_match_all('/(?<![A-Za-z])(\d{1,3}(?:\.\d{3})+|\d+(?:[.,]\d+)?)(?![A-Za-z])/u', $masked, $matches) < 1) {
            return true;
        }

        foreach ($matches[1] as $rawNum) {
            $parsed = $this->parseVietnameseNumber($rawNum);
            if (!$this->numberMatchesAllowed($parsed, $allowedNumbers)) {
                return false;
            }
        }

        return true;
    }

    private function parseVietnameseNumber(string $raw): float {
        // Dạng phân tách hàng nghìn bằng dấu chấm: 385.000 hoặc 1.526.400
        if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $raw) === 1) {
            return (float) str_replace('.', '', $raw);
        }
        // Dạng số thập phân tiếng Việt dùng dấu phẩy: 1,44 hoặc 0,15
        return (float) str_replace(',', '.', $raw);
    }

    /**
     * @param array<int, float> $allowedNumbers
     */
    private function numberMatchesAllowed(float $target, array $allowedNumbers): bool {
        foreach ($allowedNumbers as $candidate) {
            $tolerance = $target >= 1000.0 ? 1.0 : 0.05;
            if (abs($target - $candidate) <= $tolerance) {
                return true;
            }
        }
        return false;
    }

    /**
     * Kiểm tra danh sách trắng SĐT, Email, URL và chặn thương hiệu đối thủ.
     *
     * @param array<int, string> $errors
     */
    private function checkContactsUrlsAndBrands(string $text, array &$errors): void {
        if ($text === '') {
            return;
        }

        // 1. Kiểm tra Email ngoài danh sách trắng
        $allowedEmails = array_filter([
            strtolower(trim((string) drv_setting('company.email', ''))),
            strtolower(trim((string) drv_setting('contact.email', ''))),
            strtolower(trim((string) drv_setting('contact.project_email', ''))),
        ]);
        if (preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u', $text, $emMatches) > 0) {
            foreach ($emMatches[0] as $foundEmail) {
                if (!in_array(strtolower($foundEmail), $allowedEmails, true)) {
                    $errors[] = 'unauthorized_email:' . $foundEmail;
                }
            }
        }

        // 2. Kiểm tra SĐT ngoài hotline / Zalo đã cấu hình
        $hotlineDigits = (string) preg_replace('/\D/', '', (string) drv_setting('contact.hotline', '0909888777'));
        $zaloDigits    = (string) preg_replace('/\D/', '', (string) drv_setting('contact.zalo', '0909888777'));
        if (preg_match_all('/(?<!\d)(?:\+?84|0)(?:[\s.\-]?\d){8,10}(?!\d)/u', $text, $phMatches) > 0) {
            foreach ($phMatches[0] as $foundPhone) {
                $digits = (string) preg_replace('/\D/', '', $foundPhone);
                if (strlen($digits) < 9) {
                    continue;
                }
                if ($digits !== $hotlineDigits && $digits !== $zaloDigits) {
                    $errors[] = 'unauthorized_phone:' . $digits;
                }
            }
        }

        // 3. Kiểm tra URL ngoài domain cho phép
        $allowedDomains = isset($this->rules['allowed_domains']) && is_array($this->rules['allowed_domains'])
            ? array_map('strtolower', array_map('strval', $this->rules['allowed_domains']))
            : ['gachdravit.vn', 'zalo.me'];

        if (function_exists('home_url')) {
            $homeHost = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
            if ($homeHost !== '') {
                $allowedDomains[] = $homeHost;
            }
        }

        if (preg_match_all('#https?://[^\s)"\'<>]+#iu', $text, $urlMatches) > 0) {
            foreach ($urlMatches[0] as $url) {
                $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
                $ok   = false;
                foreach ($allowedDomains as $dom) {
                    if ($host === $dom || str_ends_with($host, '.' . $dom)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    $errors[] = 'unauthorized_url:' . $host;
                }
            }
        }

        // 4. Kiểm tra thương hiệu đối thủ
        $lower       = mb_strtolower($text, 'UTF-8');
        $ascii       = SearchNormalizer::asciiFold($lower);
        $competitors = isset($this->rules['competitor_brands']) && is_array($this->rules['competitor_brands'])
            ? $this->rules['competitor_brands']
            : [];

        foreach ($competitors as $comp) {
            if (!is_string($comp) || $comp === '') {
                continue;
            }
            $cLower = mb_strtolower($comp, 'UTF-8');
            $cAscii = SearchNormalizer::asciiFold($cLower);
            $patL   = '/(?<![\p{L}\p{N}])' . preg_quote($cLower, '/') . '(?![\p{L}\p{N}])/u';
            $patA   = '/(?<![a-z0-9])' . preg_quote($cAscii, '/') . '(?![a-z0-9])/';

            if (preg_match($patL, $lower) === 1 || preg_match($patA, $ascii) === 1) {
                $errors[] = 'forbidden_competitor_brand:' . $comp;
            }
        }
    }
}
