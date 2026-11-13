<?php
declare(strict_types=1);

namespace Dravit\Advisor\Llm;

use Dravit\Advisor\Contracts\LlmClientInterface;

defined('ABSPATH') || exit;

/**
 * Driver kết nối Google Gemini (`gemini-2.5-flash` / `gemini-2.5-pro`) qua `wp_remote_post`
 * với `responseMimeType = application/json`, header `x-goog-api-key` và 0 công cụ ngoài (ADR D-32).
 */
final class GeminiDriver implements LlmClientInterface {
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private string $apiKey;
    private string $model;
    private PromptBuilder $promptBuilder;

    public function __construct(
        string $apiKey,
        string $model = '',
        ?PromptBuilder $promptBuilder = null
    ) {
        $this->apiKey        = trim($apiKey);
        $cleanModel          = (string) preg_replace('/[^a-zA-Z0-9.\-]/', '', trim($model));
        $this->model         = $cleanModel !== '' ? $cleanModel : 'gemini-2.5-flash';
        $this->promptBuilder = $promptBuilder ?? new PromptBuilder();
    }

    public function getName(): string {
        return 'gemini';
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
        $decoded = $this->sendGenerateContent($prompts['system'], $prompts['user'], 450);

        if ($decoded === null) {
            throw new \RuntimeException('Gemini slot extraction failed or timed out.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function explainRecommendations(array $context): array {
        $prompts           = $this->promptBuilder->buildExplainPrompt($context);
        $systemInstruction = $prompts['system_rules'] . "\n\n" . $prompts['knowledge_block'];
        $decoded           = $this->sendGenerateContent($systemInstruction, $prompts['user'], 900);

        if ($decoded === null) {
            throw new \RuntimeException('Gemini explanation failed or returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sendGenerateContent(string $systemInstruction, string $userPrompt, int $maxOutputTokens): ?array {
        if ($this->apiKey === '' || !function_exists('wp_remote_post')) {
            return null;
        }

        $url  = self::API_BASE . rawurlencode($this->model) . ':generateContent';
        $body = wp_json_encode([
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents'          => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig'  => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => $maxOutputTokens,
                'responseMimeType' => 'application/json',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($body)) {
            return null;
        }

        $response = wp_remote_post($url, [
            'timeout' => 8,
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $this->apiKey,
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
        if (!is_array($payload)) {
            return null;
        }

        $textChunk = (string) ($payload['candidates'][0]['content']['parts'][0]['text'] ?? '');
        return PromptBuilder::decodeJsonResponse($textChunk);
    }
}

