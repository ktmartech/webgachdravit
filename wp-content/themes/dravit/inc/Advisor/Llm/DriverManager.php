<?php
declare(strict_types=1);

namespace Dravit\Advisor\Llm;

use Dravit\Advisor\Contracts\LlmClientInterface;
use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Quản lý lựa chọn Driver theo cấu hình Admin `drv_settings.ai.provider`, kiểm soát trần ngân sách gọi API theo ngày
 * và mở hook `drv/advisor/llm_client` để mở rộng không sửa lõi (ADR D-32).
 */
final class DriverManager {
    public static function resolve(): LlmClientInterface {
        $provider = strtolower(trim((string) drv_setting('ai.provider', 'rule_only')));
        $apiKey   = trim((string) drv_setting('ai.api_key', ''));
        $model    = trim((string) drv_setting('ai.model', ''));

        $driver = new RuleOnlyDriver();

        if ($provider === 'anthropic' && $apiKey !== '' && self::hasDailyBudgetRemaining()) {
            $driver = new AnthropicDriver($apiKey, $model);
        } elseif ($provider === 'gemini' && $apiKey !== '' && self::hasDailyBudgetRemaining()) {
            $driver = new GeminiDriver($apiKey, $model);
        }

        if (function_exists('apply_filters')) {
            /** @var mixed $filtered */
            $filtered = apply_filters('drv/advisor/llm_client', $driver, $provider, [
                'provider' => $provider,
                'model'    => $model,
            ]);
            if ($filtered instanceof LlmClientInterface) {
                return $filtered;
            }
        }

        return $driver;
    }

    public static function hasDailyBudgetRemaining(): bool {
        if (!function_exists('get_transient')) {
            return true;
        }

        $rules = Config::get('ai-rules');
        $cap   = max(1, (int) ($rules['daily_budget_calls'] ?? 500));
        $key   = self::dailyCounterKey();
        $used  = (int) get_transient($key);

        return $used < $cap;
    }

    public static function incrementDailyCallCount(): void {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return;
        }

        $key  = self::dailyCounterKey();
        $used = (int) get_transient($key);
        set_transient($key, $used + 1, DAY_IN_SECONDS);
    }

    private static function dailyCounterKey(): string {
        return 'drv_ai_calls_' . gmdate('Ymd');
    }
}

