<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Advisor\AdvisorService;
use Dravit\Advisor\Contracts\AdvisorLogInterface;
use Dravit\Advisor\Contracts\LlmClientInterface;
use Dravit\Advisor\Contracts\ResultCacheInterface;
use Dravit\Advisor\KnowledgeBase;
use Dravit\Advisor\Llm\RuleOnlyDriver;
use Dravit\Advisor\ScopeGate;
use Dravit\Advisor\SlotNormalizer;
use Dravit\Advisor\Support\PiiRedactor;
use Dravit\Advisor\Support\SimpleYaml;
use Dravit\Advisor\Validator;
use PHPUnit\Framework\TestCase;

final class AdvisorValidatorTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'esc_html'            => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_attr'            => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_url'             => static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8'),
            'sanitize_html_class' => static fn (mixed $v): string => (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $v),
            'sanitize_key'        => static fn (mixed $v): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            'untrailingslashit'   => static fn (string $v): string => rtrim($v, '/\\'),
            'home_url'            => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' ? '/' . ltrim($path, '/') : ''),
            'wp_parse_url'        => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'wp_json_encode'      => static fn (mixed $data): string|false => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'wp_strip_all_tags'   => static fn (string $str): string => strip_tags($str),
            'get_option'          => static fn (string $opt, mixed $default = false): mixed => $opt === 'drv_settings' ? [
                'features' => ['ai_enabled' => true],
                'contact'  => [
                    'hotline'       => '0909 123 456',
                    'zalo'          => 'https://zalo.me/0909123456',
                    'email'         => 'cskh@gachdravit.vn',
                    'project_email' => 'duan@gachdravit.vn',
                ],
                'ai'       => [
                    'provider'        => 'rule_only',
                    'daily_max_calls' => 500,
                ],
            ] : $default,
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function createInMemoryCache(): ResultCacheInterface {
        return new class () implements ResultCacheInterface {
            /** @var array<string, array<string, mixed>> */
            public array $store = [];

            public function get(string $cacheKey): ?array {
                return $this->store[$cacheKey] ?? null;
            }

            public function set(string $cacheKey, array $payload, int $ttlSeconds = 900): void {
                unset($ttlSeconds);
                $this->store[$cacheKey] = $payload;
            }

            public function flush(): void {
                $this->store = [];
            }
        };
    }

    /**
     * @param array<int, array<string, mixed>> $capturedLogs
     */
    private function createSpyLogger(array &$capturedLogs): AdvisorLogInterface {
        return new class ($capturedLogs) implements AdvisorLogInterface {
            /** @var array<int, array<string, mixed>> */
            private array $logs;

            /**
             * @param array<int, array<string, mixed>> $logs
             */
            public function __construct(array &$logs) {
                $this->logs = &$logs;
            }

            public function logQuery(array $entry): int {
                $entry['message_redacted'] = PiiRedactor::redact((string) ($entry['message'] ?? ''));
                $this->logs[]              = $entry;
                return count($this->logs);
            }

            public function recordFeedback(int $requestId, string $rating, string $reason = ''): bool {
                unset($requestId, $rating, $reason);
                return true;
            }

            public function cleanupOldLogs(int $days = 90): int {
                unset($days);
                return 0;
            }
        };
    }

    public function test_validator_rejects_hallucinated_product_id_and_fabricated_numbers(): void {
        $validator  = new Validator();
        $candidates = [SafetyRuleEngineTest::buildFixtureCatalog()[1]]; // id = 102 (DRV-3605S, 295.000đ/m², R10)

        $badOutput = [
            'summary'       => 'Gợi ý 2 mẫu gạch R10 giá chỉ 199.000đ/m² siêu rẻ.',
            'items'         => [
                ['id' => 102, 'why' => 'Đạt R10, giá niêm yết 295.000đ/m².'],
                ['id' => 999999, 'why' => 'Mẫu bịa không tồn tại trong kho.'],
            ],
            'tradeoff'      => null,
            'kb_refs'       => ['kb-an-toan-chong-truot#r10-khu-am-uot'],
            'clarification' => null,
        ];

        $res = $validator->validate($badOutput, $candidates, ['intent' => 'recommend']);

        self::assertFalse($res['valid']);
        self::assertContains('hallucinated_product_id:999999', $res['errors']);
        self::assertContains('unverified_number_in_summary', $res['errors']);
    }

    public function test_validator_blocks_any_price_or_cost_comparison_on_quote_only_products(): void {
        $validator = new Validator();
        $quoteItem = SafetyRuleEngineTest::buildFixtureCatalog()[8]; // id = 109 (DRV-3612T, quote_only)

        $leakedOutput = [
            'summary'       => 'Mẫu DRV-3612T có giá khoảng 550.000đ/m² tiết kiệm hơn đá tự nhiên.',
            'items'         => [
                ['id' => 109, 'why' => 'Đạt R10, rẻ hơn các dòng nhập khẩu khác.'],
            ],
            'tradeoff'      => null,
            'kb_refs'       => ['kb-an-toan-chong-truot#r10-khu-am-uot'],
            'clarification' => null,
        ];

        $res = $validator->validate($leakedOutput, [$quoteItem], ['intent' => 'recommend']);

        self::assertFalse($res['valid']);
        self::assertContains('quote_only_price_leak:109', $res['errors']);
        self::assertContains('quote_only_price_leak_summary:DRV-3612T', $res['errors']);
    }

    public function test_validator_blocks_unauthorized_phone_email_url_competitor_brand_and_fake_kb_ref(): void {
        $validator  = new Validator();
        $candidates = [SafetyRuleEngineTest::buildFixtureCatalog()[1]];

        $badOutput = [
            'summary'       => ' Gọi ngay 0988777666 hoặc xem https://evil-tiles.example.com/promo và gửi mail hacker@gmail.com, bền hơn Viglacera.',
            'items'         => [
                ['id' => 102, 'why' => 'Đạt chuẩn R10 cho nhà tắm.'],
            ],
            'tradeoff'      => null,
            'kb_refs'       => ['kb-khong-ton-tai#muc-ao'],
            'clarification' => null,
        ];

        $res = $validator->validate($badOutput, $candidates, ['intent' => 'knowledge']);

        self::assertFalse($res['valid']);
        self::assertContains('unauthorized_phone:0988777666', $res['errors']);
        self::assertContains('unauthorized_email:hacker@gmail.com', $res['errors']);
        self::assertContains('unauthorized_url:evil-tiles.example.com', $res['errors']);
        self::assertContains('forbidden_competitor_brand:viglacera', $res['errors']);
        self::assertContains('invalid_kb_ref:kb-khong-ton-tai#muc-ao', $res['errors']);
        self::assertContains('missing_mandatory_kb_ref', $res['errors']);
    }

    public function test_advisor_service_automatically_falls_back_to_deterministic_engine_when_external_llm_hallucinates(): void {
        $rogueLlm = new class () implements LlmClientInterface {
            public function getName(): string {
                return 'mock_rogue_llm';
            }

            public function isExternal(): bool {
                return true;
            }

            public function extractIntentAndSlots(string $userMessage, array $currentSlots, array $allowedSchema): array {
                unset($userMessage, $currentSlots, $allowedSchema);
                return [
                    'in_scope' => true,
                    'intent'   => 'recommend',
                    'slots'    => ['space' => 'nha-tam', 'area_m2' => 6],
                ];
            }

            public function explainRecommendations(array $context): array {
                unset($context);
                // Bịa ID 777777 + bịa giá + chèn đối thủ -> Validator bắt buộc chặn và kích hoạt Fallback
                return [
                    'summary' => 'Mua gạch Đồng Tâm mã 777777 giá 123.456đ tại https://spam.io',
                    'items'   => [
                        ['id' => 777777, 'why' => 'Giá 123.456đ'],
                    ],
                    'kb_refs' => ['kb-ao#khong-co'],
                ];
            }
        };

        $logs    = [];
        $service = new AdvisorService(
            SafetyRuleEngineTest::createMockCatalog(),
            $rogueLlm,
            $this->createInMemoryCache(),
            $this->createSpyLogger($logs)
        );

        $response = $service->handleQuery([
            'message' => 'Tư vấn gạch nhà tắm 6m2 cho người già, sđt mình 0912345678 email an.nguyen@gmail.com',
        ]);

        self::assertTrue($response['in_scope']);
        self::assertTrue($response['degraded'], 'Khi LLM trả dữ liệu sai kiểm định, hệ thống phải bật cờ degraded = true.');
        self::assertStringNotContainsString('Đồng Tâm', (string) $response['summary']);
        self::assertStringNotContainsString('spam.io', (string) $response['summary']);
        self::assertNotEmpty($response['items']);

        foreach ($response['items'] as $item) {
            self::assertNotSame(777777, $item['id']);
            self::assertStringStartsWith('R1', $item['dcof_r']);
        }

        // Kiểm tra khử PII trước khi ghi log CPT `drv_ai_query`
        self::assertCount(1, $logs);
        self::assertStringNotContainsString('0912345678', (string) $logs[0]['message_redacted']);
        self::assertStringNotContainsString('an.nguyen@gmail.com', (string) $logs[0]['message_redacted']);
        self::assertStringContainsString('[SDT_DA_AN]', (string) $logs[0]['message_redacted']);
        self::assertStringContainsString('[EMAIL_DA_AN]', (string) $logs[0]['message_redacted']);
    }

    public function test_executes_all_64_regression_cases_in_cases_yaml_with_zero_safety_or_validator_violations(): void {
        $yamlPath = DRV_DIR . '/tests/advisor/cases.yaml';
        self::assertFileExists($yamlPath);

        $parsed = SimpleYaml::parseFile($yamlPath);
        $cases  = $parsed['cases'] ?? [];
        self::assertIsArray($cases);
        self::assertGreaterThanOrEqual(60, count($cases));

        $kb         = new KnowledgeBase();
        $normalizer = new SlotNormalizer($kb);
        $scopeGate  = new ScopeGate();
        $validator  = new Validator($kb);
        $ruleDriver = new RuleOnlyDriver($normalizer, $scopeGate, $kb);
        $logs       = [];

        $service = new AdvisorService(
            SafetyRuleEngineTest::createMockCatalog(),
            $ruleDriver,
            $this->createInMemoryCache(),
            $this->createSpyLogger($logs),
            $kb
        );

        foreach ($cases as $case) {
            self::assertIsArray($case);
            $caseId  = (string) ($case['id'] ?? 'unknown');
            $message = (string) ($case['message'] ?? '');

            $res = $service->handleQuery(['message' => $message]);

            self::assertSame(
                (bool) $case['expect_in_scope'],
                (bool) $res['in_scope'],
                sprintf('[%s] Sai lệch in_scope cho câu: "%s"', $caseId, $message)
            );
            self::assertSame(
                (string) $case['expect_intent'],
                (string) $res['intent'],
                sprintf('[%s] Sai lệch intent cho câu: "%s"', $caseId, $message)
            );

            if (!$res['in_scope']) {
                self::assertSame([], $res['items'], sprintf('[%s] Câu ngoài phạm vi không được trả về sản phẩm.', $caseId));
                continue;
            }

            // Kiểm tra các slot kỳ vọng
            if (isset($case['expect_slots']) && is_array($case['expect_slots'])) {
                foreach ($case['expect_slots'] as $slotKey => $expectedVal) {
                    $actualVal = $res['slots'][$slotKey] ?? null;
                    if (is_int($expectedVal) && is_float($actualVal)) {
                        self::assertEqualsWithDelta((float) $expectedVal, $actualVal, 0.01, sprintf('[%s] Slot %s sai.', $caseId, $slotKey));
                    } else {
                        self::assertSame($expectedVal, $actualVal, sprintf('[%s] Slot %s sai cho "%s".', $caseId, $slotKey, $message));
                    }
                }
            }

            if (!empty($case['expect_tradeoff'])) {
                self::assertNotNull($res['tradeoff'], sprintf('[%s] Phải có cảnh báo đánh đổi kỹ thuật tradeoff.', $caseId));
            }

            // Kiểm tra ngưỡng R tối thiểu trên mọi thẻ sản phẩm trả về (trừ trường hợp khách hỏi rõ gạch thẻ ốp tường)
            $minR     = (int) ($case['min_r'] ?? 0);
            $function = $res['slots']['function'] ?? null;
            if ($minR > 0 && !in_array($function, ['op-tuong', 'gach-the'], true)) {
                foreach ($res['items'] as $item) {
                    $rNum = (int) ltrim((string) ($item['dcof_r'] ?? ''), 'Rr');
                    self::assertGreaterThanOrEqual(
                        $minR,
                        $rNum,
                        sprintf('[%s] Sản phẩm %s (R=%s) vi phạm ngưỡng R >= %d.', $caseId, $item['sku'], $item['dcof_r'], $minR)
                    );
                }
            }

            // Đảm bảo đầu ra của RuleOnlyDriver luôn vượt qua chính Validator L5 (0 bịa giá quote_only, 0 em-dash, kb_refs hợp lệ)
            self::assertStringNotContainsString('—', (string) $res['summary'], sprintf('[%s] Cấm dùng em-dash trong summary.', $caseId));
            if (in_array($res['intent'], ['knowledge', 'policy'], true)) {
                self::assertNotEmpty($res['kb_refs'], sprintf('[%s] Intent %s bắt buộc có trích dẫn kb_refs.', $caseId, $res['intent']));
                foreach ($res['kb_refs'] as $ref) {
                    self::assertTrue($kb->isValidRef((string) $ref), sprintf('[%s] kb_ref "%s" không hợp lệ.', $caseId, $ref));
                }
            }

            foreach ($res['items'] as $item) {
                if ($item['price_state'] === 'quote_only') {
                    self::assertNull($item['price_m2'], sprintf('[%s] Mẫu quote_only %s phải có price_m2 = null.', $caseId, $item['sku']));
                    if (is_array($item['calc'])) {
                        self::assertNull($item['calc']['est_total_vnd'], sprintf('[%s] Mẫu quote_only không được có est_total_vnd.', $caseId));
                    }
                }
            }

            // Kiểm định trực tiếp qua Validator với các sản phẩm trả về
            if ($res['items'] !== []) {
                $fixtureMap = [];
                foreach (SafetyRuleEngineTest::buildFixtureCatalog() as $fc) {
                    $fixtureMap[(int) $fc['id']] = $fc;
                    if (isset($res['slots']['area_m2']) && is_numeric($res['slots']['area_m2'])) {
                        $fc['calc']                  = \Dravit\Advisor\QueryEngine::calculateRequirement($fc, (float) $res['slots']['area_m2'], 5);
                        $fixtureMap[(int) $fc['id']] = $fc;
                    }
                }
                $candsForCheck = [];
                $whyForCheck   = [];
                foreach ($res['items'] as $ri) {
                    $cid = (int) $ri['id'];
                    if (isset($fixtureMap[$cid])) {
                        $candsForCheck[] = $fixtureMap[$cid];
                        $whyForCheck[]   = ['id' => $cid, 'why' => (string) $ri['why']];
                    }
                }

                $valCheck = $validator->validate(
                    [
                        'summary'       => $res['summary'],
                        'items'         => $whyForCheck,
                        'tradeoff'      => $res['tradeoff'],
                        'kb_refs'       => $res['kb_refs'],
                        'clarification' => $res['clarification'],
                    ],
                    $candsForCheck,
                    [
                        'intent'     => $res['intent'],
                        'slots'      => $res['slots'],
                        'required_r' => $minR,
                        'waste_pct'  => 5,
                    ]
                );

                self::assertTrue(
                    $valCheck['valid'],
                    sprintf('[%s] Đầu ra vi phạm Validator L5: %s', $caseId, implode(', ', $valCheck['errors']))
                );
            }
        }
    }
}
