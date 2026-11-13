<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Advisor\Contracts\CatalogSearchInterface;
use Dravit\Core\Config;
use Dravit\Units\Calc;

defined('ABSPATH') || exit;

/**
 * Động cơ lọc cứng an toàn, chấm điểm mềm, khử trùng lặp `drv_series`, trộn tối đa `2/6` `quote_only`
 * và tính toán khối lượng qua `Dravit\Units\Calc` trên Snapshot (ADR D-32, D-48, D-49; docs/13 §5).
 */
final class QueryEngine {
    private CatalogSearchInterface $catalog;

    /** @var array<string, mixed> */
    private array $rules;

    /**
     * @param array<string, mixed>|null $rulesOverride
     */
    public function __construct(CatalogSearchInterface $catalog, ?array $rulesOverride = null) {
        $this->catalog = $catalog;
        $this->rules   = $rulesOverride ?? Config::get('ai-rules');
    }

    /**
     * Xác định ngưỡng chỉ số chống trượt `R` tối thiểu BẮT BUỘC (0 nếu không có ràng buộc an toàn).
     * Luật cứng KHÔNG BAO GIỜ NỚI LỎNG:
     * - `nha-tam >= 10`
     * - `phong-bep >= 10`
     * - `san-vuon / ngoai-troi / gara-o-to >= 11`
     * - `ho-boi >= 12`
     * - `nguoi-gia / tre-nho / am-uot >= 10`
     *
     * @param array<string, mixed> $slots
     */
    public function resolveRequiredSafetyR(array $slots): int {
        $required = 0;

        $safetyMap  = isset($this->rules['safety_r_min']) && is_array($this->rules['safety_r_min'])
            ? $this->rules['safety_r_min']
            : [];
        $specialMap = isset($this->rules['special_r_min']) && is_array($this->rules['special_r_min'])
            ? $this->rules['special_r_min']
            : [];
        $dcofLevels = isset($this->rules['dcof_levels']) && is_array($this->rules['dcof_levels'])
            ? $this->rules['dcof_levels']
            : [];

        $space      = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';
        $function   = isset($slots['function']) && is_string($slots['function']) ? $slots['function'] : '';
        $isWallOnly = in_array($function, ['op-tuong', 'gach-the', 'bong-gio', 'op-mat-bep-lavabo'], true);

        if (!$isWallOnly && $space !== '' && isset($safetyMap[$space])) {
            $required = max($required, (int) $safetyMap[$space]);
        }

        $specials = isset($slots['special']) && is_array($slots['special']) ? $slots['special'] : [];
        foreach ($specials as $sp) {
            if (is_string($sp) && isset($specialMap[$sp])) {
                $required = max($required, (int) $specialMap[$sp]);
            }
        }

        $explicitDcof = isset($slots['dcof']) && is_string($slots['dcof']) ? strtolower($slots['dcof']) : '';
        if ($explicitDcof !== '' && isset($dcofLevels[$explicitDcof])) {
            $required = max($required, (int) $dcofLevels[$explicitDcof]);
        }

        return $required;
    }

    /**
     * Chạy toàn bộ luồng truy hồi ứng viên trên snapshot.
     *
     * @param array<string, mixed> $slots
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   required_r: int,
     *   relaxed: array<int, string>,
     *   tradeoff: string|null,
     *   kb_refs: array<int, string>,
     *   context_facets: array{collection_url: string, filter_url: string, active_tags: array<int, array{key: string, label: string}>}
     * }
     */
    public function query(array $slots, int $wastePct = 5): array {
        $candidates = $this->catalog->getCandidates();
        $requiredR  = $this->resolveRequiredSafetyR($slots);
        $wastePct   = max(0, min(20, $wastePct));

        $kbRefs   = $this->buildSafetyAndSizeKbRefs($slots, $requiredR);
        $tradeoff = $this->detectContradictionTradeoff($slots, $requiredR, $kbRefs);

        // Bước 1: Lọc cứng bất biến (An toàn R, chịu tải gara, không gian ngoài trời, trạng thái kho)
        $safePool = $this->applyHardImmutableFilters($candidates, $slots, $requiredR, true);
        if ($safePool === []) {
            // Cho phép lấy hàng đặt trước / hết kho tạm nếu kho sẵn rỗng, nhưng VẪN GIỮ NGUYÊN ràng buộc an toàn R & độ dày
            $safePool = $this->applyHardImmutableFilters($candidates, $slots, $requiredR, false);
        }

        // Bước 2: Lọc tiêu chí mềm có kiểm soát & tự động nới lỏng tuần tự nếu 0 kết quả
        $relaxed      = [];
        $activeSlots  = $slots;
        $filteredPool = $this->applySoftConstraints($safePool, $activeSlots);

        $relaxOrder = isset($this->rules['relaxation_order']) && is_array($this->rules['relaxation_order'])
            ? $this->rules['relaxation_order']
            : ['budget', 'color', 'surface', 'style', 'size'];

        if ($filteredPool === [] && $safePool !== []) {
            foreach ($relaxOrder as $criterion) {
                if (!is_string($criterion)) {
                    continue;
                }
                if (!$this->slotCriterionIsActive($activeSlots, $criterion)) {
                    continue;
                }

                $this->clearSlotCriterion($activeSlots, $criterion);
                $relaxed[]    = $criterion;
                $filteredPool = $this->applySoftConstraints($safePool, $activeSlots);

                if ($filteredPool !== []) {
                    break;
                }
            }

            // Nếu vẫn rỗng (VD khách chọn vân gạch chưa có trong không gian an toàn đó), quay về toàn bộ safePool đạt chuẩn an toàn
            if ($filteredPool === []) {
                $filteredPool = $safePool;
                $relaxed[]    = 'vein';
            }
        }

        // Bước 3: Chấm điểm mềm (0..100), gom theo family `drv_series` và giới hạn tối đa 2/6 mẫu `quote_only`
        $scored = [];
        foreach ($filteredPool as $item) {
            $item['match_score'] = $this->computeSoftScore($item, $slots);
            $scored[]            = $item;
        }

        usort($scored, function (array $a, array $b): int {
            $scoreCmp = ((int) $b['match_score']) <=> ((int) $a['match_score']);
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            // Ưu tiên mẫu có giá niêm yết (`priced`/`sale`) khi điểm ngang nhau (docs/13 §5.4)
            $aPriced = ($a['price_state'] ?? '') !== 'quote_only' ? 1 : 0;
            $bPriced = ($b['price_state'] ?? '') !== 'quote_only' ? 1 : 0;
            if ($aPriced !== $bPriced) {
                return $bPriced <=> $aPriced;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        $deduped = $this->deduplicateBySeries($scored);
        $topList = $this->interleaveWithQuoteCap($deduped);

        // Bước 4: Gắn nhãn tồn kho theo thùng & tính toán khối lượng server-side qua `Dravit\Units\Calc`
        $areaM2   = isset($slots['area_m2']) && is_numeric($slots['area_m2']) ? (float) $slots['area_m2'] : null;
        $enriched = [];
        foreach ($topList as $cand) {
            $cand['stock_label'] = self::formatStockLabel($cand);
            $cand['calc']        = ($areaM2 !== null && $areaM2 > 0.0)
                ? self::calculateRequirement($cand, $areaM2, $wastePct)
                : null;
            $enriched[]          = $cand;
        }

        if ($areaM2 !== null && $areaM2 > 0.0) {
            $kbRefs[] = 'kb-hao-hut-thung#hao-hut-5';
        }

        return [
            'items'          => $enriched,
            'required_r'     => $requiredR,
            'relaxed'        => array_values(array_unique($relaxed)),
            'tradeoff'       => $tradeoff,
            'kb_refs'        => array_values(array_unique($kbRefs)),
            'context_facets' => $this->buildContextFacets($slots, $requiredR),
        ];
    }

    /**
     * Tính toán số thùng / số viên / diện tích thực / trọng lượng / tổng tiền dự kiến qua lõi `Dravit\Units\Calc`.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    public static function calculateRequirement(array $item, float $rawAreaM2, int $wastePct = 5): ?array {
        $areaPiece = isset($item['area_piece']) ? (float) $item['area_piece'] : 0.0;
        $pcsPerBox = max(1, (int) ($item['pieces_per_box'] ?? 1));
        if ($areaPiece <= 0.0) {
            return null;
        }

        $targetArea   = round($rawAreaM2 * (1.0 + ($wastePct / 100.0)), 4);
        $roundMode    = isset($item['round_mode']) && is_string($item['round_mode']) ? $item['round_mode'] : 'box';
        $qtyMin       = max(1, (int) ($item['qty_min'] ?? 1));
        $kgPerBox     = isset($item['kg_per_box']) ? (float) $item['kg_per_box'] : 0.0;
        $priceState   = (string) ($item['price_state'] ?? 'quote_only');
        $effectiveM2  = ($priceState !== 'quote_only' && isset($item['effective_price_m2']) && is_numeric($item['effective_price_m2']))
            ? (int) $item['effective_price_m2']
            : 0;

        $calc = Calc::calcFromM2([
            'areaM2'        => $rawAreaM2,
            'wastePct'      => $wastePct,
            'forceRoundBox' => true,
            'areaPiece'     => $areaPiece,
            'piecesPerBox'  => $pcsPerBox,
            'kgPerBox'      => $kgPerBox,
            'priceM2'       => $effectiveM2,
            'roundMode'     => $roundMode,
            'qtyMin'        => $qtyMin,
        ]);

        $boxes       = $calc['loosePieces'] > 0 ? ($calc['fullBoxes'] + 1) : $calc['fullBoxes'];
        $estTotalVnd = ($priceState !== 'quote_only' && !$calc['isQuote'] && $calc['totalVnd'] > 0)
            ? $calc['totalVnd']
            : null;

        return [
            'input_area_m2' => round($rawAreaM2, 2),
            'waste_pct'     => $wastePct,
            'target_m2'     => round($targetArea, 2),
            'pieces'        => $calc['pieces'],
            'boxes'         => $boxes,
            'box_text'      => $calc['boxText'],
            'actual_m2'     => $calc['actualM2'],
            'weight_kg'     => $calc['totalKg'],
            'est_total_vnd' => $estTotalVnd,
        ];
    }

    /**
     * Quy đổi số lượng viên tồn kho sang nhãn thùng thân thiện (`"Sẵn kho 60 thùng"`, `"Đặt theo dự án"`).
     *
     * @param array<string, mixed> $item
     */
    public static function formatStockLabel(array $item): string {
        if (($item['price_state'] ?? '') === 'quote_only') {
            return 'Đặt theo lô dự án';
        }

        $status = (string) ($item['stock_status'] ?? 'instock');
        if ($status === 'outofstock') {
            return 'Đặt trước 5 - 7 ngày';
        }

        $qtyPieces = $item['stock_qty_pieces'] ?? null;
        $pcsPerBox = max(1, (int) ($item['pieces_per_box'] ?? 1));

        if (is_int($qtyPieces) && $qtyPieces > 0) {
            $boxes = (int) floor($qtyPieces / $pcsPerBox);
            if ($boxes <= 0) {
                return sprintf('Còn %d viên lẻ', $qtyPieces);
            }
            if ($boxes <= 10) {
                return sprintf('Sắp hết (còn %d thùng)', $boxes);
            }
            return sprintf('Sẵn kho %d thùng', $boxes);
        }

        return 'Sẵn kho giao ngay';
    }

    /**
     * Lọc cứng bất biến (KHÔNG BAO GIỜ NỚI LỎNG).
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed>             $slots
     * @return array<int, array<string, mixed>>
     */
    private function applyHardImmutableFilters(
        array $candidates,
        array $slots,
        int $requiredR,
        bool $requireInStock
    ): array {
        $space        = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';
        $skuQuery     = isset($slots['sku_query']) && is_string($slots['sku_query']) ? strtoupper($slots['sku_query']) : '';
        $showroomOnly = !empty($slots['showroom_only']);
        $garageMinMm  = isset($this->rules['garage_min_thickness_mm']) ? (float) $this->rules['garage_min_thickness_mm'] : 12.0;
        $garageSlugs  = isset($this->rules['garage_thickness_slugs']) && is_array($this->rules['garage_thickness_slugs'])
            ? $this->rules['garage_thickness_slugs']
            : ['day', 'sieu-day-20mm'];
        $outdoorList  = isset($this->rules['outdoor_spaces']) && is_array($this->rules['outdoor_spaces'])
            ? $this->rules['outdoor_spaces']
            : ['san-vuon', 'ngoai-troi', 'ho-boi', 'gara-o-to'];

        $passed = [];

        foreach ($candidates as $item) {
            if ($requireInStock && ($item['stock_status'] ?? 'instock') === 'outofstock') {
                continue;
            }

            if ($skuQuery !== '' && strtoupper((string) ($item['sku'] ?? '')) !== $skuQuery) {
                continue;
            }

            if ($showroomOnly && empty($item['has_showroom'])) {
                continue;
            }

            // 1. LUẬT AN TOÀN CHỐNG TRƯỢT R BẤT BIẾN:
            // Nếu có ràng buộc R (nha-tam >= 10, phong-bep >= 10, ngoai-troi/san-vuon >= 11, ho-boi >= 12, nguoi-gia/tre-nho >= 10),
            // bất kỳ SKU nào thiếu R (`dcof_level === 0`) hoặc thấp hơn ngưỡng (`R9 < 10`) đều BỊ LOẠI TUYỆT ĐỐI.
            $itemR = (int) ($item['dcof_level'] ?? 0);
            if ($requiredR > 0 && $itemR < $requiredR) {
                continue;
            }

            $attrs      = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];
            $itemSpaces = isset($attrs['pa_khong-gian']) && is_array($attrs['pa_khong-gian']) ? $attrs['pa_khong-gian'] : [];
            $itemThick  = isset($attrs['pa_do-day']) && is_array($attrs['pa_do-day']) ? $attrs['pa_do-day'] : [];
            $thickMm    = isset($item['thickness_mm']) ? (float) $item['thickness_mm'] : 0.0;
            $waterAbs   = isset($item['water_absorption']) && is_numeric($item['water_absorption'])
                ? (float) $item['water_absorption']
                : null;

            // 2. Ràng buộc chịu tải Gara ô tô (dày >= 12mm / `day` hoặc `sieu-day-20mm`)
            if ($space === 'gara-o-to') {
                $thickOk = $thickMm >= $garageMinMm || array_intersect($itemThick, $garageSlugs) !== [];
                if (!$thickOk) {
                    continue;
                }
            }

            // 3. Ràng buộc khu vực ngoài trời / sân vườn / hồ bơi: không lọt gạch xương Ceramic hút nước cao > 0,5%
            if ($space !== '' && in_array($space, $outdoorList, true)) {
                $taggedOutdoor = array_intersect($itemSpaces, ['san-vuon', 'ngoai-troi', 'ho-boi', 'gara-o-to']) !== [];
                $lowWater      = $waterAbs !== null && $waterAbs <= 0.5;
                if (!$taggedOutdoor && !$lowWater) {
                    continue;
                }
            }

            $passed[] = $item;
        }

        return $passed;
    }

    /**
     * Lọc các điều kiện lựa chọn mềm của khách (không gian khớp, ngân sách trần cho mẫu có giá, màu, bề mặt, khổ...).
     *
     * @param array<int, array<string, mixed>> $pool
     * @param array<string, mixed>             $slots
     * @return array<int, array<string, mixed>>
     */
    private function applySoftConstraints(array $pool, array $slots): array {
        $result = [];

        $space      = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';
        $function   = isset($slots['function']) && is_string($slots['function']) ? $slots['function'] : '';
        $size       = isset($slots['size']) && is_string($slots['size']) ? $slots['size'] : '';
        $vein       = isset($slots['vein']) && is_string($slots['vein']) ? $slots['vein'] : '';
        $material   = isset($slots['material']) && is_string($slots['material']) ? $slots['material'] : '';
        $surface    = isset($slots['surface']) && is_string($slots['surface']) ? $slots['surface'] : '';
        $color      = isset($slots['color']) && is_string($slots['color']) ? $slots['color'] : '';
        $style      = isset($slots['style']) && is_string($slots['style']) ? $slots['style'] : '';
        $budgetMax  = isset($slots['budget_max_vnd']) && is_int($slots['budget_max_vnd']) ? $slots['budget_max_vnd'] : null;

        foreach ($pool as $item) {
            $attrs = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];

            if ($space !== '') {
                $itemSpaces = $attrs['pa_khong-gian'] ?? [];
                // Gara ô tô có thể dùng chung gạch sân vườn chịu lực R11-R12 dày >= 12mm
                $acceptedSpaces = $space === 'gara-o-to' ? ['gara-o-to', 'san-vuon', 'ngoai-troi'] : [$space];
                if (is_array($itemSpaces) && $itemSpaces !== [] && array_intersect($acceptedSpaces, $itemSpaces) === []) {
                    continue;
                }
            }

            if ($function !== '' && !$this->itemHasAttr($attrs, 'pa_cong-nang', [$function])) {
                continue;
            }

            if ($size !== '' && !$this->itemHasAttr($attrs, 'pa_kich-thuoc', [$size])) {
                continue;
            }

            if ($vein !== '') {
                $veinGroup = $vein === 'van-da-marble'
                    ? ['van-da-marble', 'van-da-tu-nhien', 'van-da-travertine', 'van-da-onyx']
                    : [$vein];
                if (!$this->itemHasAttr($attrs, 'pa_van-gach', $veinGroup)) {
                    continue;
                }
            }

            if ($material !== '' && !$this->itemHasAttr($attrs, 'pa_chat-lieu', [$material])) {
                continue;
            }

            if ($surface !== '') {
                $surfaceGroup = match ($surface) {
                    'mo'    => ['mo', 'baby-skin', 'satin', 'honed'],
                    'nham'  => ['nham', 'granilia', 'carving', 'khuon-noi-3d'],
                    default => [$surface],
                };
                if (!$this->itemHasAttr($attrs, 'pa_be-mat', $surfaceGroup)) {
                    continue;
                }
            }

            if ($color !== '') {
                $colorGroup = match ($color) {
                    'trang' => ['trang', 'kem', 'ghi-sang'],
                    'kem'   => ['kem', 'be', 'vang'],
                    'xam'   => ['xam', 'ghi-sang', 'xam-dam'],
                    default => [$color],
                };
                if (!$this->itemHasAttr($attrs, 'pa_mau-sac', $colorGroup)) {
                    continue;
                }
            }

            if ($style !== '' && !$this->itemHasAttr($attrs, 'pa_phong-cach', [$style])) {
                continue;
            }

            // Trần ngân sách: chỉ áp lên mẫu có giá (`priced`/`sale`), mẫu `quote_only` vẫn giữ lại và giới hạn ở bước trộn 2/6
            if ($budgetMax !== null && ($item['price_state'] ?? '') !== 'quote_only') {
                $effPrice = (int) ($item['effective_price_m2'] ?? 0);
                if ($effPrice > $budgetMax) {
                    continue;
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Tính điểm khớp chuẩn hóa `0..100` theo trọng số `config/ai-rules.php` (`docs/13` §5.2).
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $slots
     */
    private function computeSoftScore(array $item, array $slots): int {
        $weights = isset($this->rules['weights']) && is_array($this->rules['weights'])
            ? $this->rules['weights']
            : ['space' => 30, 'size' => 20, 'style_color_pattern' => 20, 'surface' => 10, 'budget' => 15, 'stock_showroom' => 5];

        $attrs = isset($item['attrs']) && is_array($item['attrs']) ? $item['attrs'] : [];
        $score = 0.0;

        // 1. Hợp không gian (30đ)
        $wSpace = (float) ($weights['space'] ?? 30);
        $space  = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';
        if ($space !== '') {
            if ($this->itemHasAttr($attrs, 'pa_khong-gian', [$space])) {
                $score += $wSpace;
            } elseif ($space === 'gara-o-to' && $this->itemHasAttr($attrs, 'pa_khong-gian', ['san-vuon', 'ngoai-troi'])) {
                $score += $wSpace * 0.85;
            } else {
                $score += $wSpace * 0.35;
            }
        } else {
            $score += $wSpace * 0.8;
        }

        // 2. Khổ gạch phù hợp diện tích hoặc khổ yêu cầu (20đ)
        $wSize = (float) ($weights['size'] ?? 20);
        $size  = isset($slots['size']) && is_string($slots['size']) ? $slots['size'] : '';
        $area  = isset($slots['area_m2']) && is_numeric($slots['area_m2']) ? (float) $slots['area_m2'] : null;

        if ($size !== '') {
            $score += $this->itemHasAttr($attrs, 'pa_kich-thuoc', [$size]) ? $wSize : ($wSize * 0.25);
        } elseif ($area !== null && $area > 0.0) {
            $recommendedSizes = $this->recommendedSizesForArea($area);
            $score           += $this->itemHasAttr($attrs, 'pa_kich-thuoc', $recommendedSizes) ? $wSize : ($wSize * 0.45);
        } else {
            $score += $wSize * 0.75;
        }

        // 3. Phong cách / màu / vân gạch (20đ)
        $wStyle   = (float) ($weights['style_color_pattern'] ?? 20);
        $subParts = 0;
        $subHits  = 0.0;

        foreach ([
            'vein'     => 'pa_van-gach',
            'color'    => 'pa_mau-sac',
            'style'    => 'pa_phong-cach',
            'material' => 'pa_chat-lieu',
        ] as $sKey => $tax) {
            $sVal = isset($slots[$sKey]) && is_string($slots[$sKey]) ? $slots[$sKey] : '';
            if ($sVal !== '') {
                $subParts++;
                if ($this->itemHasAttr($attrs, $tax, [$sVal])) {
                    $subHits += 1.0;
                } elseif ($sKey === 'vein' && $sVal === 'van-da-marble' && $this->itemHasAttr($attrs, $tax, ['van-da-tu-nhien', 'van-da-travertine'])) {
                    $subHits += 0.7;
                }
            }
        }

        $score += $subParts > 0 ? ($wStyle * ($subHits / $subParts)) : ($wStyle * 0.75);

        // 4. Bề mặt (10đ)
        $wSurface = (float) ($weights['surface'] ?? 10);
        $surface  = isset($slots['surface']) && is_string($slots['surface']) ? $slots['surface'] : '';
        if ($surface !== '') {
            $score += $this->itemHasAttr($attrs, 'pa_be-mat', [$surface]) ? $wSurface : ($wSurface * 0.3);
        } else {
            $score += $wSurface * 0.8;
        }

        // 5. Gần ngân sách (15đ)
        $wBudget   = (float) ($weights['budget'] ?? 15);
        $budgetMax = isset($slots['budget_max_vnd']) && is_int($slots['budget_max_vnd']) ? $slots['budget_max_vnd'] : null;
        if ($budgetMax !== null && $budgetMax > 0) {
            if (($item['price_state'] ?? '') === 'quote_only') {
                $score += $wBudget * 0.55;
            } else {
                $eff = (int) ($item['effective_price_m2'] ?? 0);
                if ($eff > 0 && $eff <= $budgetMax) {
                    $ratio  = $eff / $budgetMax;
                    $score += $wBudget * ($ratio >= 0.65 ? 1.0 : 0.85);
                } else {
                    $score += $wBudget * 0.2;
                }
            }
        } else {
            $score += $wBudget * 0.8;
        }

        // 6. Sẵn kho & có mẫu tại kệ Showroom (5đ)
        $wStock = (float) ($weights['stock_showroom'] ?? 5);
        $inSt   = ($item['stock_status'] ?? 'instock') === 'instock';
        $hasSh  = !empty($item['has_showroom']);
        if ($inSt && $hasSh) {
            $score += $wStock;
        } elseif ($inSt) {
            $score += $wStock * 0.7;
        }

        return max(50, min(99, (int) round($score)));
    }

    /**
     * Khử trùng lặp theo dòng thiết kế `drv_series` (giữ lại biến thể điểm cao nhất của mỗi series trước).
     *
     * @param array<int, array<string, mixed>> $sorted
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateBySeries(array $sorted): array {
        $seenSeries = [];
        $unique     = [];

        foreach ($sorted as $item) {
            $series = trim((string) ($item['series'] ?? ''));
            if ($series !== '') {
                $key = mb_strtolower($series, 'UTF-8');
                if (isset($seenSeries[$key])) {
                    continue;
                }
                $seenSeries[$key] = true;
            }
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * Lấy tối đa `max_results` (6) mẫu, trong đó tối đa `max_quote_only` (2) mẫu `quote_only` (trừ khi không còn mẫu có giá nào).
     *
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function interleaveWithQuoteCap(array $candidates): array {
        $maxResults   = max(1, (int) ($this->rules['max_results'] ?? 6));
        $maxQuoteOnly = max(0, (int) ($this->rules['max_quote_only'] ?? 2));

        $selected   = [];
        $overflowQ  = [];
        $quoteCount = 0;

        foreach ($candidates as $item) {
            if (count($selected) >= $maxResults) {
                break;
            }

            $isQuote = ($item['price_state'] ?? '') === 'quote_only';
            if ($isQuote) {
                if ($quoteCount >= $maxQuoteOnly) {
                    $overflowQ[] = $item;
                    continue;
                }
                $quoteCount++;
            }

            $selected[] = $item;
        }

        // Chỉ bù thêm `quote_only` khi danh sách rỗng hoàn toàn không có mẫu có giá nào
        if ($selected === [] && $overflowQ !== []) {
            return array_slice($overflowQ, 0, min($maxResults, count($overflowQ)));
        }

        return $selected;
    }

    /**
     * Phát hiện mâu thuẫn kỹ thuật phổ biến (VD: khách muốn "bóng kiếng" lát "nhà tắm" / "sân vườn") để giải thích thẳng thắn.
     *
     * @param array<string, mixed> $slots
     * @param array<int, string>   $kbRefs
     */
    private function detectContradictionTradeoff(array &$slots, int $requiredR, array &$kbRefs): ?string {
        $surface = isset($slots['surface']) && is_string($slots['surface']) ? $slots['surface'] : '';
        $space   = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';

        if ($surface === 'bong-kieng' && $requiredR >= 10) {
            // Chuyển tiêu chí mềm sang bề mặt mờ/bán bóng an toàn nhưng vẫn giữ thông báo rõ ràng
            $slots['surface'] = null;
            $kbRefs[]         = 'kb-faq#faq-bong-nha-tam';

            if ($space === 'nha-tam') {
                return 'Lưu ý an toàn: Gạch bóng kiếng (R9) rất trơn khi đọng nước xà phòng nên hệ thống đã khóa sang các mẫu bề mặt Mờ Matt / Nhám đạt chuẩn Chống trượt R10 - R11 cho sàn nhà tắm (bóng kiếng chỉ nên ốp tường).';
            }
            return sprintf(
                'Lưu ý an toàn: Khu vực này yêu cầu Chống trượt tối thiểu R%d nên hệ thống tự động loại gạch bóng gương R9 và ưu tiên bề mặt Mờ Matt / Bán bóng an toàn.',
                $requiredR
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $slots
     * @return array<int, string>
     */
    private function buildSafetyAndSizeKbRefs(array $slots, int $requiredR): array {
        $refs  = [];
        $space = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';

        if ($requiredR > 0) {
            $refs[] = match ($space) {
                'nha-tam'               => 'kb-an-toan-chong-truot#r-nha-tam',
                'phong-bep'             => 'kb-an-toan-chong-truot#r-phong-bep',
                'san-vuon', 'ngoai-troi', 'gara-o-to' => 'kb-an-toan-chong-truot#r-ngoai-troi',
                'ho-boi'                => 'kb-an-toan-chong-truot#r-ho-boi',
                default                 => 'kb-an-toan-chong-truot#r-nguoi-gia',
            };
        }

        if (!empty($slots['area_m2']) && is_numeric($slots['area_m2'])) {
            $area   = (float) $slots['area_m2'];
            $refs[] = $area < 10.0
                ? 'kb-kich-thuoc-dien-tich#kho-nho'
                : ($area <= 20.0 ? 'kb-kich-thuoc-dien-tich#kho-vua' : 'kb-kich-thuoc-dien-tich#kho-lon');
        }

        return array_values(array_unique($refs));
    }

    /**
     * Xây dựng đường dẫn liên thông sang Bộ lọc 14 tiêu chí truyền thống (`collection_url` & `filter_url` không dấu `/` cuối).
     *
     * @param array<string, mixed> $slots
     * @return array{collection_url: string, filter_url: string, active_tags: array<int, array{key: string, label: string}>}
     */
    public function buildContextFacets(array $slots, int $requiredR = 0): array {
        $facets      = Config::get('facets');
        $attrsCfg    = isset($facets['attributes']) && is_array($facets['attributes']) ? $facets['attributes'] : [];
        $baseSlug    = 'gach';
        $queryParams = [];
        $activeTags  = [];

        // Ưu tiên chọn collection tĩnh gốc theo không gian -> công năng -> vân gạch -> chất liệu -> kích thước
        $space = isset($slots['space']) && is_string($slots['space']) ? $slots['space'] : '';
        $func  = isset($slots['function']) && is_string($slots['function']) ? $slots['function'] : '';
        $vein  = isset($slots['vein']) && is_string($slots['vein']) ? $slots['vein'] : '';
        $mat   = isset($slots['material']) && is_string($slots['material']) ? $slots['material'] : '';
        $size  = isset($slots['size']) && is_string($slots['size']) ? $slots['size'] : '';

        $usedForBase = '';
        if ($space !== '' && isset($attrsCfg['khong-gian']['static_map'][$space])) {
            $baseSlug    = (string) $attrsCfg['khong-gian']['static_map'][$space];
            $usedForBase = 'space';
        } elseif ($func !== '' && isset($attrsCfg['cong-nang']['static_map'][$func])) {
            $baseSlug    = (string) $attrsCfg['cong-nang']['static_map'][$func];
            $usedForBase = 'function';
        } elseif ($vein !== '' && isset($attrsCfg['van-gach']['static_map'][$vein])) {
            $baseSlug    = (string) $attrsCfg['van-gach']['static_map'][$vein];
            $usedForBase = 'vein';
        } elseif ($mat !== '' && isset($attrsCfg['chat-lieu']['static_map'][$mat])) {
            $baseSlug    = (string) $attrsCfg['chat-lieu']['static_map'][$mat];
            $usedForBase = 'material';
        } elseif ($size !== '' && isset($attrsCfg['kich-thuoc']['redirect_map'][$size])) {
            $baseSlug    = (string) $attrsCfg['kich-thuoc']['redirect_map'][$size];
            $usedForBase = 'size';
        }

        $slotToFacet = [
            'space'    => 'khong-gian',
            'function' => 'cong-nang',
            'size'     => 'kich-thuoc',
            'vein'     => 'van-gach',
            'material' => 'chat-lieu',
            'surface'  => 'be-mat',
            'color'    => 'mau-sac',
            'style'    => 'phong-cach',
        ];

        foreach ($slotToFacet as $slotKey => $facetKey) {
            $val = isset($slots[$slotKey]) && is_string($slots[$slotKey]) ? $slots[$slotKey] : '';
            if ($val === '') {
                continue;
            }
            $termLabel = (string) ($attrsCfg[$facetKey]['terms'][$val]['name'] ?? $val);
            $activeTags[] = [
                'key'   => $slotKey,
                'label' => $termLabel,
            ];

            if ($slotKey !== $usedForBase) {
                $param               = (string) ($attrsCfg[$facetKey]['param'] ?? $facetKey);
                $queryParams[$param] = $val;
            }
        }

        if (!empty($slots['area_m2']) && is_numeric($slots['area_m2'])) {
            $areaStr      = str_replace('.', ',', rtrim(rtrim(number_format((float) $slots['area_m2'], 1, '.', ''), '0'), '.'));
            $activeTags[] = [
                'key'   => 'area_m2',
                'label' => $areaStr . ' m²',
            ];
        }

        if ($requiredR > 0) {
            $rList = [];
            for ($r = $requiredR; $r <= 13; $r++) {
                $rList[] = 'r' . $r;
            }
            $queryParams['dcof'] = implode(',', $rList);
            $activeTags[]        = [
                'key'   => 'dcof',
                'label' => 'Chống trượt ≥ R' . $requiredR,
            ];
        }

        if (!empty($slots['budget_max_vnd']) && is_int($slots['budget_max_vnd'])) {
            $bMax         = $slots['budget_max_vnd'];
            $activeTags[] = [
                'key'   => 'budget_max_vnd',
                'label' => '≤ ' . number_format($bMax, 0, ',', '.') . 'đ/m²',
            ];
            if ($bMax <= 200000) {
                $queryParams['gia'] = 'duoi-200k';
            } elseif ($bMax <= 350000) {
                $queryParams['gia'] = 'duoi-200k,200k-350k';
            } elseif ($bMax <= 600000) {
                $queryParams['gia'] = 'duoi-200k,200k-350k,350k-600k';
            }
        }

        if (!empty($slots['showroom_only'])) {
            $queryParams['showroom'] = 'showroom';
            $activeTags[]            = [
                'key'   => 'showroom_only',
                'label' => 'Có mẫu tại Showroom',
            ];
        }

        $collectionPath = '/' . ltrim($baseSlug, '/');
        $collectionUrl  = function_exists('home_url')
            ? untrailingslashit(home_url($collectionPath))
            : $collectionPath;

        $filterUrl = $collectionUrl;
        if ($queryParams !== []) {
            $qs        = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
            $qs        = str_replace('%2C', ',', $qs);
            $filterUrl = $collectionUrl . '?' . $qs;
        }

        return [
            'collection_url' => $collectionUrl,
            'filter_url'     => $filterUrl,
            'active_tags'    => $activeTags,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array<int, string>   $needles
     */
    private function itemHasAttr(array $attrs, string $tax, array $needles): bool {
        $vals = $attrs[$tax] ?? [];
        if (!is_array($vals) || $vals === []) {
            return false;
        }
        return array_intersect($needles, $vals) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function recommendedSizesForArea(float $areaM2): array {
        $matrix = isset($this->rules['area_size_matrix']) && is_array($this->rules['area_size_matrix'])
            ? $this->rules['area_size_matrix']
            : [];

        foreach ($matrix as $band) {
            if (!is_array($band)) {
                continue;
            }
            $max = isset($band['max_m2']) ? (float) $band['max_m2'] : 0.0;
            if ($areaM2 <= $max && isset($band['sizes']) && is_array($band['sizes'])) {
                return array_map('strval', $band['sizes']);
            }
        }

        return ['80x80', '60x120'];
    }

    /**
     * @param array<string, mixed> $slots
     */
    private function slotCriterionIsActive(array $slots, string $criterion): bool {
        return match ($criterion) {
            'budget'  => !empty($slots['budget_max_vnd']),
            'color'   => !empty($slots['color']),
            'surface' => !empty($slots['surface']),
            'style'   => !empty($slots['style']),
            'size'    => !empty($slots['size']),
            default   => false,
        };
    }

    /**
     * @param array<string, mixed> $slots
     */
    private function clearSlotCriterion(array &$slots, string $criterion): void {
        match ($criterion) {
            'budget'  => $slots['budget_max_vnd'] = null,
            'color'   => $slots['color']          = null,
            'surface' => $slots['surface']        = null,
            'style'   => $slots['style']          = null,
            'size'    => $slots['size']           = null,
            default   => null,
        };
    }
}
