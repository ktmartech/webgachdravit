<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Advisor\Cache\TransientResultCache;
use Dravit\Advisor\Catalog\SnapshotSearch;
use Dravit\Advisor\Contracts\AdvisorLogInterface;
use Dravit\Advisor\Contracts\CatalogSearchInterface;
use Dravit\Advisor\Contracts\LlmClientInterface;
use Dravit\Advisor\Contracts\ResultCacheInterface;
use Dravit\Advisor\Llm\DriverManager;
use Dravit\Advisor\Log\CptAdvisorLogger;
use Dravit\Advisor\Support\PiiRedactor;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Điều phối toàn bộ Pipeline 8 lớp Trợ lý AI Tìm Gạch đóng kín (`docs/13` §4, §10; ADR D-32):
 * `ScopeGate -> SlotNormalizer -> QueryEngine (Snapshot) -> LlmClient (hoặc RuleOnlyDriver) -> Validator -> Fallback -> Renderer -> Logger`.
 */
final class AdvisorService {
    private CatalogSearchInterface $catalog;
    private LlmClientInterface $llm;
    private ResultCacheInterface $cache;
    private AdvisorLogInterface $logger;
    private KnowledgeBase $kb;
    private SlotNormalizer $normalizer;
    private ScopeGate $scopeGate;
    private QueryEngine $queryEngine;
    private Validator $validator;
    private Fallback $fallback;
    private Renderer $renderer;

    public function __construct(
        ?CatalogSearchInterface $catalog = null,
        ?LlmClientInterface $llm = null,
        ?ResultCacheInterface $cache = null,
        ?AdvisorLogInterface $logger = null,
        ?KnowledgeBase $kb = null
    ) {
        $this->kb          = $kb ?? new KnowledgeBase();
        $this->catalog     = $catalog ?? new SnapshotSearch();
        $this->llm         = $llm ?? DriverManager::resolve();
        $this->cache       = $cache ?? new TransientResultCache();
        $this->logger      = $logger ?? new CptAdvisorLogger();
        $this->normalizer  = new SlotNormalizer($this->kb);
        $this->scopeGate   = new ScopeGate();
        $this->queryEngine = new QueryEngine($this->catalog);
        $this->validator   = new Validator($this->kb);
        $this->fallback    = new Fallback();
        $this->renderer    = new Renderer();
    }

    /**
     * Xử lý 1 lượt truy vấn từ khách hàng và trả về gói JSON chuẩn hợp đồng `docs/13` §10 + `docs/15` §2.5.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function handleQuery(array $input): array {
        $startTime = microtime(true);
        $rules     = Config::get('ai-rules');
        $messages  = isset($rules['messages']) && is_array($rules['messages']) ? $rules['messages'] : [];

        $message       = isset($input['message']) && is_string($input['message']) ? trim($input['message']) : '';
        $incomingSlots = isset($input['slots']) && is_array($input['slots']) ? $input['slots'] : [];
        $wastePct      = isset($input['waste_pct']) && is_numeric($input['waste_pct'])
            ? max(0, min(20, (int) $input['waste_pct']))
            : (int) ($rules['default_waste_pct'] ?? 5);
        $isChipClick   = !empty($input['from_chip']);

        $catalogVer = $this->catalog->getVersion();
        $provider   = $this->llm->getName();

        // 0. Kiểm tra Cache nhanh nếu cùng catalog_version + message + slots + waste_pct
        $cacheKey = $this->buildCacheKey($catalogVer, $provider, $message, $incomingSlots, $wastePct);
        $cached   = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $cached['latency_ms'] = (int) round((microtime(true) - $startTime) * 1000);
            $cached['cached']     = true;
            return $cached;
        }

        // 1. Luôn chạy cổng kiểm tra tất định (chặn Prompt Injection / Đối thủ / Ngoài phạm vi trước khi gọi mạng)
        $ruleSlots = $this->normalizer->mergeSlots($incomingSlots, $this->normalizer->extractFromText($message));
        $hardGate  = $this->scopeGate->evaluate($message, $ruleSlots);

        if (!$hardGate['in_scope']) {
            return $this->buildOutOfScopeResponse($message, $hardGate['reason'], $catalogVer, $startTime);
        }

        // 2. Gọi 1: Phân tích Slot & Intent (Nếu bấm chip nhanh hoặc dùng RuleOnlyDriver -> không gọi LLM ngoài)
        $degraded       = false;
        $degradedNotice = null;
        $slots          = $ruleSlots;
        $intent         = $hardGate['intent'];
        $seedRefs       = $hardGate['kb_refs'];
        $quickChips     = [];
        $clarification  = null;

        if (!$isChipClick && $message !== '' && $this->llm->isExternal()) {
            try {
                DriverManager::incrementDailyCallCount();
                $llmSlotRes = $this->llm->extractIntentAndSlots($message, $incomingSlots, $this->normalizer->getAllowedSchema());
                if (isset($llmSlotRes['in_scope']) && $llmSlotRes['in_scope'] === false) {
                    return $this->buildOutOfScopeResponse($message, 'llm_out_of_scope', $catalogVer, $startTime);
                }
                if (isset($llmSlotRes['intent']) && is_string($llmSlotRes['intent']) && in_array($llmSlotRes['intent'], ScopeGate::VALID_INTENTS, true)) {
                    $intent = $llmSlotRes['intent'];
                }
                if (isset($llmSlotRes['slots']) && is_array($llmSlotRes['slots'])) {
                    // Vẫn gộp ưu tiên slot tất định (diện tích, giá, R) để không mất độ chính xác số học
                    $sanitizedLlmSlots = $this->normalizer->sanitizeSlots($llmSlotRes['slots']);
                    $slots             = $this->normalizer->mergeSlots($sanitizedLlmSlots, $this->normalizer->extractFromText($message));
                    $slots             = $this->normalizer->mergeSlots($incomingSlots, $slots);
                }
                if (isset($llmSlotRes['clarification']) && is_string($llmSlotRes['clarification'])) {
                    $clarification = $llmSlotRes['clarification'];
                }
            } catch (\Throwable) {
                $degraded       = true;
                $degradedNotice = (string) ($messages['degraded_notice'] ?? '');
            }
        } else {
            $localExtract  = (new Llm\RuleOnlyDriver($this->normalizer, $this->scopeGate, $this->kb))
                ->extractIntentAndSlots($message, $incomingSlots, []);
            $clarification = isset($localExtract['clarification']) && is_string($localExtract['clarification'])
                ? $localExtract['clarification']
                : null;
            $quickChips    = isset($localExtract['quick_chips']) && is_array($localExtract['quick_chips'])
                ? $localExtract['quick_chips']
                : [];
        }

        // 3. Truy vấn ứng viên trên Snapshot (Lọc cứng R10/R11/R12 không bao giờ nới lỏng + chấm điểm + tính số thùng)
        $queryResult = $this->queryEngine->query($slots, $wastePct);
        $candidates  = $queryResult['items'];
        $requiredR   = $queryResult['required_r'];
        $allKbRefs   = array_values(array_unique(array_merge($seedRefs, $queryResult['kb_refs'])));

        $explainCtx = [
            'message'    => $message,
            'intent'     => $intent,
            'slots'      => $slots,
            'candidates' => $candidates,
            'required_r' => $requiredR,
            'waste_pct'  => $wastePct,
            'relaxed'    => $queryResult['relaxed'],
            'tradeoff'   => $queryResult['tradeoff'],
            'kb_refs'    => $allKbRefs,
        ];

        // 4. Gọi 2: Diễn giải + Hậu kiểm Validator L5 (Nếu LLM lỗi hoặc Validator bác bỏ -> Fallback tất định)
        $explanation = null;
        if ($this->llm->isExternal() && DriverManager::hasDailyBudgetRemaining()) {
            try {
                DriverManager::incrementDailyCallCount();
                $rawExplain = $this->llm->explainRecommendations($explainCtx);
                $check      = $this->validator->validate($rawExplain, $candidates, $explainCtx);

                if ($check['valid']) {
                    $explanation = $check['sanitized'];
                    if ($explanation['tradeoff'] === null && $queryResult['tradeoff'] !== null) {
                        $explanation['tradeoff'] = $queryResult['tradeoff'];
                    }
                } else {
                    $explanation    = $this->fallback->buildDegradedExplanation($explainCtx, implode(',', $check['errors']));
                    $degraded       = true;
                    $degradedNotice = (string) ($explanation['degraded_notice'] ?? '');
                }
            } catch (\Throwable) {
                $explanation    = $this->fallback->buildDegradedExplanation($explainCtx, 'llm_exception');
                $degraded       = true;
                $degradedNotice = (string) ($explanation['degraded_notice'] ?? '');
            }
        } else {
            $explanation = (new Llm\RuleOnlyDriver($this->normalizer, $this->scopeGate, $this->kb))
                ->explainRecommendations($explainCtx);
        }

        if ($quickChips === [] && isset($explanation['quick_chips']) && is_array($explanation['quick_chips'])) {
            $quickChips = $explanation['quick_chips'];
        }
        if ($clarification === null && isset($explanation['clarification']) && is_string($explanation['clarification'])) {
            $clarification = $explanation['clarification'];
        }

        // 5. Render HTML thẻ sản phẩm qua `drv_ui('product-card')` & chuẩn hóa danh sách trả về
        $whyItems = isset($explanation['items']) && is_array($explanation['items']) ? $explanation['items'] : [];
        $whyById  = [];
        foreach ($whyItems as $wi) {
            if (is_array($wi) && isset($wi['id'], $wi['why']) && is_string($wi['why'])) {
                $whyById[(int) $wi['id']] = $wi['why'];
            }
        }

        // Với intent thuần knowledge/policy/handoff không cần render 6 thẻ sản phẩm nếu LLM/Rule trả items rỗng
        $displayCandidates = in_array($intent, ['knowledge', 'policy', 'handoff'], true) && $whyItems === []
            ? []
            : $candidates;

        $html = $displayCandidates !== [] ? $this->renderer->renderRecommendations($displayCandidates, $whyItems) : '';

        $responseItems = [];
        $itemIds       = [];
        foreach ($displayCandidates as $cand) {
            $cid       = (int) ($cand['id'] ?? 0);
            $isQuote   = ($cand['price_state'] ?? '') === 'quote_only';
            $wMm       = (float) ($cand['w_mm'] ?? 0.0);
            $hMm       = (float) ($cand['h_mm'] ?? 0.0);
            $sizeLbl   = ($wMm > 0.0 && $hMm > 0.0)
                ? ((int) round($wMm / 10.0) . '×' . (int) round($hMm / 10.0) . ' cm')
                : '';
            $itemIds[] = $cid;

            $responseItems[] = [
                'id'             => $cid,
                'sku'            => (string) ($cand['sku'] ?? ''),
                'title'          => (string) ($cand['title'] ?? ''),
                'url'            => (string) ($cand['url'] ?? ''),
                'img_primary'    => (string) ($cand['img_primary'] ?? ''),
                'size_label'     => $sizeLbl,
                'pieces_per_box' => max(1, (int) ($cand['pieces_per_box'] ?? 4)),
                'area_box'       => (float) ($cand['area_box'] ?? 1.44),
                'price_state'    => (string) ($cand['price_state'] ?? 'quote_only'),
                'price_m2'       => $isQuote ? null : (int) ($cand['effective_price_m2'] ?? 0),
                'dcof_r'         => $cand['dcof_level'] ? 'R' . (int) $cand['dcof_level'] : '',
                'match_score'    => (int) ($cand['match_score'] ?? 90),
                'stock_label'    => (string) ($cand['stock_label'] ?? ''),
                'why'            => $whyById[$cid] ?? '',
                'calc'           => $cand['calc'] ?? null,
            ];
        }

        $kbRefsOut = isset($explanation['kb_refs']) && is_array($explanation['kb_refs'])
            ? array_values(array_map('strval', $explanation['kb_refs']))
            : [];
        $kbSources = [];
        foreach ($kbRefsOut as $ref) {
            $sec = $this->kb->getSectionByRef($ref);
            if ($sec !== null) {
                $kbSources[] = [
                    'ref'   => $sec['ref'],
                    'title' => $sec['title'],
                ];
            }
        }

        $handoff = ($intent === 'handoff' || ($displayCandidates === [] && $intent === 'recommend'))
            ? $this->buildHandoffPayload($message, $slots)
            : null;

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        $requestId = $this->logger->logQuery([
            'message'         => $message,
            'intent'          => $intent,
            'provider'        => $provider,
            'degraded'        => $degraded,
            'latency_ms'      => $latencyMs,
            'catalog_version' => $catalogVer,
            'slots'           => $slots,
            'item_ids'        => $itemIds,
            'kb_refs'         => $kbRefsOut,
        ]);

        $payload = [
            'request_id'      => $requestId,
            'in_scope'        => true,
            'intent'          => $intent,
            'provider'        => $provider,
            'degraded'        => $degraded,
            'degraded_notice' => $degradedNotice,
            'summary'         => (string) ($explanation['summary'] ?? ''),
            'tradeoff'        => $explanation['tradeoff'] ?? $queryResult['tradeoff'],
            'clarification'   => $clarification,
            'quick_chips'     => $quickChips,
            'disclaimer'      => (string) ($messages['disclaimer'] ?? ''),
            'slots'           => $slots,
            'required_r'      => $requiredR,
            'waste_pct'       => $wastePct,
            'relaxed'         => $queryResult['relaxed'],
            'context_facets'  => $queryResult['context_facets'],
            'items'           => $responseItems,
            'html'            => $html,
            'kb_refs'         => $kbRefsOut,
            'kb_sources'      => $kbSources,
            'handoff'         => $handoff,
            'catalog_version' => $catalogVer,
            'latency_ms'      => $latencyMs,
        ];

        $ttl = max(60, (int) ($rules['cache_ttl_seconds'] ?? 900));
        $this->cache->set($cacheKey, $payload, $ttl);

        return $payload;
    }

    /**
     * Dựng danh sách gợi ý mặc định ngay khi tải trang SSR `/tro-ly-ai-tim-gach` (0ms đợi JS).
     *
     * @return array<string, mixed>
     */
    public function getInitialShowcase(): array {
        $slots  = SlotNormalizer::emptySlots();
        $result = $this->queryEngine->query($slots, 5);
        $items  = $result['items'];

        $ruleExplain = (new Llm\RuleOnlyDriver($this->normalizer, $this->scopeGate, $this->kb))->explainRecommendations([
            'intent'     => 'recommend',
            'slots'      => $slots,
            'candidates' => $items,
            'required_r' => 0,
            'waste_pct'  => 5,
            'relaxed'    => [],
            'tradeoff'   => null,
            'kb_refs'    => ['kb-an-toan-chong-truot#r-tong-quan'],
        ]);

        $whyItems = isset($ruleExplain['items']) && is_array($ruleExplain['items']) ? $ruleExplain['items'] : [];

        return [
            'count'          => count($items),
            'summary'        => 'Chọn nhanh một kịch bản gợi ý bên trái hoặc nhập không gian kèm diện tích m² để hệ thống khóa chuẩn Chống trượt R và tính sẵn số thùng chẵn.',
            'html'           => $this->renderer->renderRecommendations($items, $whyItems),
            'context_facets' => $result['context_facets'],
        ];
    }

    public function recordFeedback(int $requestId, string $rating, string $reason = ''): bool {
        return $this->logger->recordFeedback($requestId, $rating, $reason);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOutOfScopeResponse(
        string $message,
        string $reason,
        string $catalogVer,
        float $startTime
    ): array {
        $rules    = Config::get('ai-rules');
        $messages = isset($rules['messages']) && is_array($rules['messages']) ? $rules['messages'] : [];

        $text = $reason === 'competitor'
            ? (string) ($messages['competitor_ref'] ?? 'Mình chỉ hỗ trợ tra cứu sản phẩm trong kho chính hãng DRAVIT.')
            : (string) ($messages['out_of_scope'] ?? 'Mình chỉ hỗ trợ chọn gạch ốp lát DRAVIT.');

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
        $requestId = $this->logger->logQuery([
            'message'         => $message,
            'intent'          => 'out_of_scope',
            'provider'        => $this->llm->getName(),
            'degraded'        => false,
            'latency_ms'      => $latencyMs,
            'catalog_version' => $catalogVer,
            'slots'           => [],
            'item_ids'        => [],
            'kb_refs'         => [],
        ]);

        $gachUrl = function_exists('home_url') ? untrailingslashit(home_url('/gach')) : '/gach';

        return [
            'request_id'      => $requestId,
            'in_scope'        => false,
            'intent'          => 'out_of_scope',
            'provider'        => $this->llm->getName(),
            'degraded'        => false,
            'degraded_notice' => null,
            'summary'         => $text,
            'tradeoff'        => null,
            'clarification'   => null,
            'quick_chips'     => [
                'Nhà tắm 6 m² chống trơn',
                'Phòng khách 28 m² vân đá',
                'Phòng ngủ giả gỗ ấm chân',
                'Sân vườn chịu lực R12',
            ],
            'disclaimer'      => '',
            'slots'           => SlotNormalizer::emptySlots(),
            'required_r'      => 0,
            'waste_pct'       => 5,
            'relaxed'         => [],
            'context_facets'  => [
                'collection_url' => $gachUrl,
                'filter_url'     => $gachUrl,
                'active_tags'    => [],
            ],
            'items'           => [],
            'html'            => '',
            'kb_refs'         => [],
            'kb_sources'      => [],
            'handoff'         => null,
            'catalog_version' => $catalogVer,
            'latency_ms'      => $latencyMs,
        ];
    }

    /**
     * @param array<string, mixed> $slots
     * @return array{zalo_url: string, hotline: string, summary: string}
     */
    private function buildHandoffPayload(string $message, array $slots): array {
        $zaloPhone = drv_clean_phone((string) drv_setting('contact.zalo', '0909888777'));
        $zaloUrl   = $zaloPhone !== '' ? 'https://zalo.me/' . $zaloPhone : 'https://zalo.me';
        $hotline   = trim((string) drv_setting('contact.hotline', '0909 888 777'));

        $summaryParts = [];
        if (!empty($slots['space'])) {
            $summaryParts[] = 'Khu vực: ' . (string) $slots['space'];
        }
        if (!empty($slots['area_m2'])) {
            $summaryParts[] = 'Diện tích: ' . (string) $slots['area_m2'] . ' m²';
        }
        if ($message !== '') {
            $summaryParts[] = 'Ghi chú: ' . PiiRedactor::redact($message);
        }

        return [
            'zalo_url' => $zaloUrl,
            'hotline'  => $hotline,
            'summary'  => $summaryParts !== [] ? implode(' | ', $summaryParts) : 'Yêu cầu tư vấn kỹ thuật gạch DRAVIT',
        ];
    }

    /**
     * @param array<string, mixed> $slots
     */
    private function buildCacheKey(
        string $catalogVer,
        string $provider,
        string $message,
        array $slots,
        int $wastePct
    ): string {
        $normMsg = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($message)), 'UTF-8');
        ksort($slots);
        $encodedSlots = function_exists('wp_json_encode') ? wp_json_encode($slots) : json_encode($slots);
        return implode('|', [
            $catalogVer,
            $provider,
            $wastePct,
            $normMsg,
            is_string($encodedSlots) ? $encodedSlots : '[]',
        ]);
    }
}
