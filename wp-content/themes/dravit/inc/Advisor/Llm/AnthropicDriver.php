<?php
declare(strict_types=1);

namespace Dravit\Advisor\Llm;

use Dravit\Advisor\Contracts\LlmClientInterface;

defined('ABSPATH') || exit;

/**
 * Driver kết nối Anthropic Claude (Haiku / Sonnet) qua `wp_remote_post` với Prompt Caching (`ephemeral`)
 * và KHÔNG bật bất kỳ công cụ tìm kiếm/thực thi ngoài nào (L1 - docs/13 §1, §7, ADR D-32).
 */
final class AnthropicDriver implements LlmClientInterface {
    private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    private string $apiKey;
    private string $fastModel;
    private string $explainModel;
    private PromptBuilder $promptBuilder;

    public function __construct(
        string $apiKey,
        string $model = '',
        ?PromptBuilder $promptBuilder = null
    ) {
        $this->apiKey        = trim($apiKey);
        $defaultModel        = trim($model);
        $this->fastModel     = $defaultModel !== '' ? $defaultModel : 'claude-3-5-haiku-latest';
        $this->explainModel  = $defaultModel !== '' ? $defaultModel : 'claude-3-7-sonnet-latest';
        $this->promptBuilder = $promptBuilder ?? new PromptBuilder();
    }

    public function getName(): string {
        return 'anthropic';
    }

    public function isExternal(): bool {
        return true;
    }

    /**
     * @param array<string, mixed> $currentSlots
     * @param array<string, mixed> $allowedSchema
     * @return array<string, mixed>
     */
    public function extractIntentAndSlots(string $message, array $currentSlots, array $allowedSchema): array {
        $prompts = $this->promptBuilder->buildSlotExtractPrompt($message, $currentSlots, $allowedSchema);

        $systemBlocks = [
            [
                'type'          => 'text',
                'text'          => $prompts['system'],
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        $decoded = $this->sendMessagesRequest($this->fastModel, $systemBlocks, $prompts['user'], 450);
        if ($decoded === null) {
            throw new \RuntimeException('Anthropic slot extraction failed or timed out.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function explainRecommendations(array $context): array {
        $prompts = $this->promptBuilder->buildExplainPrompt($context);

        // Bật Prompt Caching cho cả phần luật hệ thống và khối tri thức Markdown đã duyệt (docs/13 §7.2)
        $systemBlocks = [
            [
                'type'          => 'text',
                'text'          => $prompts['system_rules'],
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type'          => 'text',
                'text'          => $prompts['knowledge_block'],
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        $decoded = $this->sendMessagesRequest($this->explainModel, $systemBlocks, $prompts['user'], 900);
        if ($decoded === null) {
            throw new \RuntimeException('Anthropic explanation failed or returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param array<int, array<string, mixed>> $systemBlocks
     * @return array<string, mixed>|null
     */
    private function sendMessagesRequest(
        string $model,
        array $systemBlocks,
        string $userMessage,
        int $maxTokens
    ): ?array {
        if ($this->apiKey === '' || !function_exists('wp_remote_post')) {
            return null;
        }

        $body = wp_json_encode([
            'model'       => $model,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.1,
            'system'      => $systemBlocks,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $userMessage,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($body)) {
            return null;
        }

        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 8,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $rawBody = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || !isset($payload['content']) || !is_array($payload['content'])) {
            return null;
        }

        $textChunk = '';
        foreach ($payload['content'] as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text' && isset($part['text']) && is_string($part['text'])) {
                $textChunk .= $part['text'];
            }
        }

        return PromptBuilder::decodeJsonResponse($textChunk);
    }
}

