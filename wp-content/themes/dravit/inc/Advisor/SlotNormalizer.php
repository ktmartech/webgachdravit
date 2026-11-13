<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Catalog\SearchNormalizer;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Chuẩn hóa câu hỏi tự nhiên tiếng Việt (có dấu / không dấu / viết tắt kích thước / ngân sách)
 * sang đúng tập slug chuẩn trong `config/facets.php` (`docs/03` §2, `docs/13` §7.1).
 */
final class SlotNormalizer {
    private KnowledgeBase $kb;

    public function __construct(?KnowledgeBase $kb = null) {
        $this->kb = $kb ?? new KnowledgeBase();
    }

    /**
     * Khung slot rỗng mặc định.
     *
     * @return array<string, mixed>
     */
    public static function emptySlots(): array {
        return [
            'space'          => null,
            'function'       => null,
            'area_m2'        => null,
            'size'           => null,
            'vein'           => null,
            'material'       => null,
            'surface'        => null,
            'color'          => null,
            'style'          => null,
            'dcof'           => null,
            'budget_max_vnd' => null,
            'special'        => [],
            'showroom_only'  => false,
            'sku_query'      => null,
        ];
    }

    /**
     * Danh sách toàn bộ giá trị slug hợp lệ cho từng slot (đưa vào schema LLM & dùng để lọc trắng).
     *
     * @return array<string, array<int, string>>
     */
    public function getAllowedSchema(): array {
        $facets = Config::get('facets');
        $attrs  = isset($facets['attributes']) && is_array($facets['attributes']) ? $facets['attributes'] : [];

        $pick = static function (string $key) use ($attrs): array {
            $terms = $attrs[$key]['terms'] ?? [];
            return is_array($terms) ? array_map('strval', array_keys($terms)) : [];
        };

        return [
            'space'    => $pick('khong-gian'),
            'function' => $pick('cong-nang'),
            'size'     => $pick('kich-thuoc'),
            'vein'     => $pick('van-gach'),
            'material' => $pick('chat-lieu'),
            'surface'  => $pick('be-mat'),
            'color'    => $pick('mau-sac'),
            'style'    => $pick('phong-cach'),
            'dcof'     => $pick('dcof'),
            'special'  => ['nguoi-gia', 'tre-nho', 'am-uot', 'doc-nuoc'],
        ];
    }

    /**
     * Bóc tách slot trực tiếp từ câu tiếng Việt tự nhiên.
     *
     * @return array<string, mixed>
     */
    public function extractFromText(string $rawText): array {
        $slots = self::emptySlots();
        $text  = trim($rawText);
        if ($text === '') {
            return $slots;
        }

        if (class_exists('\Normalizer')) {
            $nfc = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($nfc)) {
                $text = $nfc;
            }
        }

        $lower = mb_strtolower($text, 'UTF-8');
        $ascii = SearchNormalizer::asciiFold($lower);

        // 1. Trích xuất mã SKU nếu khách gõ trực tiếp (VD: DRV-8801M, DRV-SLAB01)
        if (preg_match('/\b(drv-[a-z0-9]{3,10})\b/i', $text, $skuMatch) === 1) {
            $slots['sku_query'] = strtoupper($skuMatch[1]);
        }

        // 2. Diện tích m² (hỗ trợ 6m2, 6,5 m², 28 mét vuông, ngang 4m dài 5m, 3.5m x 4m)
        $slots['area_m2'] = $this->extractAreaM2($lower, $ascii);

        // 3. Khổ gạch chuẩn cm (hỗ trợ cả mm 800x800, đảo chiều 120x60, gạch thẻ 7,5x30 / 75x300)
        $slots['size'] = $this->extractTileSize($text);

        // 4. Ngân sách tối đa mỗi m² (hỗ trợ 350k, dưới 400 nghìn, tầm 1,2 triệu, 450.000đ)
        $slots['budget_max_vnd'] = $this->extractBudgetMaxVnd($lower, $ascii);

        // 5. Chỉ số chống trượt R tường minh (R9..R13)
        if (preg_match('/\br(9|10|11|12|13)\b/i', $ascii, $rMatch) === 1) {
            $slots['dcof'] = 'r' . $rMatch[1];
        }

        // 6. Cờ xem mẫu thực tế tại kệ Showroom
        if (preg_match('/\b(co mau tai showroom|mau tai showroom|mau tai ke|xem mau truc tiep|san mau showroom)\b/', $ascii) === 1) {
            $slots['showroom_only'] = true;
        }

        // 7. Ánh xạ từ điển đồng nghĩa (Ưu tiên khớp có dấu trước để phân biệt "gạch bông" vs "bóng", sau đó khớp không dấu)
        $synonyms = $this->kb->getSynonyms();
        $slotKeys = ['space', 'function', 'vein', 'material', 'surface', 'color', 'style'];

        foreach ($slotKeys as $slotKey) {
            $group = $synonyms[$slotKey] ?? [];
            if (!is_array($group)) {
                continue;
            }
            $matched = $this->matchSingleSlotFromGroup($lower, $ascii, $group, $slotKey);
            if ($matched !== null) {
                $slots[$slotKey] = $matched;
            }
        }

        // Bổ sung nhận diện nhanh từ khóa hay gõ tắt / lỗi dấu nhẹ
        if ($slots['surface'] === null && preg_match('/(?:^|\s)gạch bóng(?:\s|$)/u', $lower) === 1) {
            $slots['surface'] = 'bong-kieng';
        }
        if ($slots['surface'] === null && preg_match('/\b(bong guong|bong kieng|sieu bong)\b/', $ascii) === 1) {
            $slots['surface'] = 'bong-kieng';
        }
        if ($slots['size'] === null && preg_match('/\b(kho lon|gach tam lon|big slab)\b/', $ascii) === 1) {
            $slots['size'] = '60x120';
        }

        // 8. Mảng điều kiện an toàn đặc biệt (`nguoi-gia`, `tre-nho`, `am-uot`)
        $specialGroup = isset($synonyms['special']) && is_array($synonyms['special']) ? $synonyms['special'] : [];
        $specialFound = [];
        foreach ($specialGroup as $slug => $phrases) {
            if (!is_string($slug) || !is_array($phrases)) {
                continue;
            }
            foreach ($phrases as $phrase) {
                if (!is_string($phrase) || $phrase === '') {
                    continue;
                }
                $pLower = mb_strtolower($phrase, 'UTF-8');
                $pAscii = SearchNormalizer::asciiFold($pLower);
                if ($this->containsPhrase($lower, $pLower) || $this->containsPhrase($ascii, $pAscii)) {
                    $specialFound[] = $slug;
                    break;
                }
            }
        }
        $slots['special'] = array_values(array_unique($specialFound));

        return $this->sanitizeSlots($slots);
    }

    /**
     * Lọc trắng dữ liệu slot (từ LLM hoặc client gửi lên) để ép khớp 100% schema `config/facets.php`.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function sanitizeSlots(array $raw): array {
        $clean  = self::emptySlots();
        $schema = $this->getAllowedSchema();

        foreach (['space', 'function', 'size', 'vein', 'material', 'surface', 'color', 'style', 'dcof'] as $key) {
            $val = isset($raw[$key]) && is_string($raw[$key]) ? strtolower(trim($raw[$key])) : '';
            if ($val !== '' && in_array($val, $schema[$key] ?? [], true)) {
                $clean[$key] = $val;
            }
        }

        if (isset($raw['area_m2']) && is_numeric($raw['area_m2'])) {
            $area = round((float) $raw['area_m2'], 2);
            if ($area > 0.0 && $area <= 10000.0) {
                $clean['area_m2'] = $area;
            }
        }

        if (isset($raw['budget_max_vnd']) && is_numeric($raw['budget_max_vnd'])) {
            $budget = (int) round((float) $raw['budget_max_vnd']);
            if ($budget >= 50000 && $budget <= 20000000) {
                $clean['budget_max_vnd'] = $budget;
            }
        }

        if (isset($raw['special']) && is_array($raw['special'])) {
            $validSpecial = [];
            foreach ($raw['special'] as $sp) {
                if (is_string($sp) && in_array($sp, $schema['special'], true)) {
                    $validSpecial[] = $sp;
                }
            }
            $clean['special'] = array_values(array_unique($validSpecial));
        }

        $clean['showroom_only'] = !empty($raw['showroom_only']);

        if (isset($raw['sku_query']) && is_string($raw['sku_query']) && $raw['sku_query'] !== '') {
            $sku = strtoupper((string) preg_replace('/[^A-Za-z0-9\-]/', '', $raw['sku_query']));
            $clean['sku_query'] = $sku !== '' ? $sku : null;
        }

        return $clean;
    }

    /**
     * Hợp nhất ngữ cảnh nhiều lượt chat (`previous` + `incoming`).
     *
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function mergeSlots(array $previous, array $incoming): array {
        $prev   = $this->sanitizeSlots($previous);
        $inc    = $this->sanitizeSlots($incoming);
        $merged = $prev;

        // Nếu khách đổi hẳn sang không gian khác (VD từ nhà tắm sang phòng khách), reset ràng buộc R/diện tích cũ nếu khách chưa nhập lại
        if ($inc['space'] !== null && $prev['space'] !== null && $inc['space'] !== $prev['space']) {
            if ($inc['area_m2'] === null) {
                $merged['area_m2'] = null;
            }
            if ($inc['dcof'] === null) {
                $merged['dcof'] = null;
            }
        }

        foreach (['space', 'function', 'area_m2', 'size', 'vein', 'material', 'surface', 'color', 'style', 'dcof', 'budget_max_vnd', 'sku_query'] as $key) {
            if ($inc[$key] !== null) {
                $merged[$key] = $inc[$key];
            }
        }

        $prevSpecial       = is_array($prev['special']) ? $prev['special'] : [];
        $incSpecial        = is_array($inc['special']) ? $inc['special'] : [];
        $merged['special'] = array_values(array_unique(array_merge($prevSpecial, $incSpecial)));

        if ($inc['showroom_only']) {
            $merged['showroom_only'] = true;
        }

        return $merged;
    }

    /**
     * Kiểm tra xem bộ slot đã có ít nhất 1 tiêu chí chọn gạch thực thụ hay chưa.
     *
     * @param array<string, mixed> $slots
     */
    public static function hasAnyConstraint(array $slots): bool {
        foreach (['space', 'function', 'area_m2', 'size', 'vein', 'material', 'surface', 'color', 'style', 'dcof', 'budget_max_vnd', 'sku_query'] as $k) {
            if (!empty($slots[$k])) {
                return true;
            }
        }
        return !empty($slots['special']) || !empty($slots['showroom_only']);
    }

    /**
     * @param array<string, mixed> $group
     */
    private function matchSingleSlotFromGroup(string $lower, string $ascii, array $group, string $slotKey): ?string {
        $candidates = [];
        foreach ($group as $slug => $phrases) {
            if (!is_string($slug) || !is_array($phrases)) {
                continue;
            }
            foreach ($phrases as $phrase) {
                if (!is_string($phrase) || trim($phrase) === '') {
                    continue;
                }
                $pLower       = mb_strtolower(trim($phrase), 'UTF-8');
                $pAscii       = SearchNormalizer::asciiFold($pLower);
                $candidates[] = [
                    'slug'   => $slug,
                    'lower'  => $pLower,
                    'ascii'  => $pAscii,
                    'length' => mb_strlen($pLower, 'UTF-8'),
                ];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => $b['length'] <=> $a['length']);

        // Vòng 1: Khớp chính xác chuỗi có dấu tiếng Việt
        foreach ($candidates as $cand) {
            if ($this->containsPhrase($lower, $cand['lower'])) {
                return $cand['slug'];
            }
        }

        // Vòng 2: Khớp không dấu (bỏ qua cụm nhạy cảm dễ nhầm dấu như "gach bong" <-> "gạch bóng" nếu khách gõ có dấu khác)
        $ambiguousAscii = ['gach bong', 'bong gio'];
        foreach ($candidates as $cand) {
            if ($slotKey === 'function' && in_array($cand['ascii'], $ambiguousAscii, true) && $lower !== $ascii) {
                continue;
            }
            if ($this->containsPhrase($ascii, $cand['ascii'])) {
                return $cand['slug'];
            }
        }

        return null;
    }

    private function containsPhrase(string $haystack, string $needle): bool {
        if ($needle === '') {
            return false;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/u';
        return preg_match($pattern, $haystack) === 1;
    }

    private function extractAreaM2(string $lower, string $ascii): ?float {
        // 1. Dạng tường minh: "6m2", "6,5 m²", "28 mét vuông", "28 met vuong", "15.5m2"
        if (
            preg_match('/(?<!\d)(\d{1,4}(?:[.,]\d{1,2})?)\s*(?:m2|m²|mét\s*vuông|m\s*vuông)(?![a-z0-9])/iu', $lower, $m) === 1
            || preg_match('/(?<!\d)(\d{1,4}(?:[.,]\d{1,2})?)\s*(?:m2|met\s*vuong|m\s*vuong)(?![a-z0-9])/iu', $ascii, $m) === 1
        ) {
            $val = (float) str_replace(',', '.', $m[1]);
            if ($val > 0.0 && $val <= 10000.0) {
                return round($val, 2);
            }
        }

        // 2. Dạng kích thước phòng theo mét: "3,5m x 4m", "4x5m", "rộng 3m dài 5m", "ngang 4m sâu 6m"
        if (preg_match('/(?<!\d)(\d{1,2}(?:[.,]\d{1,2})?)\s*m?\s*[x×*]\s*(\d{1,2}(?:[.,]\d{1,2})?)\s*m(?![a-z0-9²])/iu', $lower, $m) === 1) {
            $w = (float) str_replace(',', '.', $m[1]);
            $h = (float) str_replace(',', '.', $m[2]);
            if ($w > 0.4 && $w <= 30.0 && $h > 0.4 && $h <= 50.0) {
                return round($w * $h, 2);
            }
        }

        if (preg_match('/(?:ngang|rong)\s*(\d{1,2}(?:[.,]\d{1,2})?)\s*m?\s*(?:x|va|,|\s+)?\s*(?:dai|sau)\s*(\d{1,2}(?:[.,]\d{1,2})?)\s*m?(?![a-z0-9])/iu', $ascii, $m) === 1) {
            $w = (float) str_replace(',', '.', $m[1]);
            $h = (float) str_replace(',', '.', $m[2]);
            if ($w > 0.4 && $w <= 30.0 && $h > 0.4 && $h <= 50.0) {
                return round($w * $h, 2);
            }
        }

        return null;
    }

    private function extractTileSize(string $rawText): ?string {
        $allowedSizes = $this->getAllowedSchema()['size'];
        $sizeLookup   = array_fill_keys($allowedSizes, true);

        // Tận dụng SearchNormalizer::normalize() đã quy đổi 800x800 -> 80x80, 1200x600 -> 60x120, 75x300 / 7,5x30 -> 7-5x30
        $normalized = SearchNormalizer::normalize($rawText);
        foreach (explode(' ', $normalized) as $token) {
            if (isset($sizeLookup[$token])) {
                return $token;
            }
            // Kiểm tra chiều đảo cm (VD: 120x60 -> 60x120, 60x30 -> 30x60)
            if (preg_match('/^(\d+(?:-\d+)?)x(\d+(?:-\d+)?)$/', $token, $dm) === 1) {
                $reversed = $dm[2] . 'x' . $dm[1];
                if (isset($sizeLookup[$reversed])) {
                    return $reversed;
                }
            }
        }

        return null;
    }

    private function extractBudgetMaxVnd(string $lower, string $ascii): ?int {
        // 1. Khoảng giá "từ 300k đến 450k" hoặc "300 - 450k" -> lấy cận trên
        if (preg_match('/(\d{2,4})\s*k?\s*(?:-|den|toi|~)\s*(\d{2,4})\s*(k|nghin|ngan)\b/', $ascii, $m) === 1) {
            $val = (int) $m[2] * 1000;
            if ($val >= 50000 && $val <= 20000000) {
                return $val;
            }
        }

        // 2. Đơn vị triệu: "1,2 triệu", "1.5tr", "1tr2", "dưới 1 triệu"
        if (preg_match('/(\d{1,2})\s*(?:tr|trieu)\s*(\d{1,2})\b/', $ascii, $m) === 1) {
            $major = (int) $m[1];
            $minor = $m[2];
            $scale = strlen($minor) === 1 ? 100000 : 10000;
            $val   = ($major * 1000000) + ((int) $minor * $scale);
            if ($val >= 100000 && $val <= 20000000) {
                return $val;
            }
        }

        if (preg_match('/(\d{1,2}(?:[.,]\d{1,2})?)\s*(?:trieu|tr)(?:\s*\/\s*m2|\s*\/\s*m²|\b)/u', $ascii, $m) === 1) {
            $num = (float) str_replace(',', '.', $m[1]);
            $val = (int) round($num * 1000000);
            if ($val >= 100000 && $val <= 20000000) {
                return $val;
            }
        }

        // 3. Đơn vị nghìn / k: "350k", "400 nghìn", "450 ngàn/m2"
        if (preg_match('/(?<!\d)(\d{2,4}(?:[.,]\d)?)\s*(?:k|nghin|ngan)(?:\s*\/\s*m2|\s*\/\s*m²|\s*1\s*m2|\b)/u', $ascii, $m) === 1) {
            $num = (float) str_replace(',', '.', $m[1]);
            $val = (int) round($num * 1000);
            if ($val >= 50000 && $val <= 20000000) {
                return $val;
            }
        }

        // 4. Số tiền đầy đủ: "350.000đ", "400,000 vnd", "450000đ/m2"
        if (preg_match('/(?<!\d)(\d{3}[.,]\d{3}|\d{1}[.,]\d{3}[.,]\d{3}|\d{6,8})\s*(?:đ|d|vnd|vnđ|dong)/iu', $lower, $m) === 1) {
            $digits = (int) preg_replace('/\D/', '', $m[1]);
            if ($digits >= 50000 && $digits <= 20000000) {
                return $digits;
            }
        }

        return null;
    }
}
