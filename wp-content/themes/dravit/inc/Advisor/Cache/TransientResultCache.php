<?php
declare(strict_types=1);

namespace Dravit\Advisor\Cache;

use Dravit\Advisor\Contracts\ResultCacheInterface;

defined('ABSPATH') || exit;

/**
 * Lưu đệm phản hồi Trợ lý AI bằng WP Transient (tự vô hiệu khi `catalog_version` thay đổi - ADR D-47).
 */
final class TransientResultCache implements ResultCacheInterface {
    private const PREFIX = 'drv_aif_c_';

    /** @var array<string, array<string, mixed>> */
    private array $memory = [];

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array {
        $safeKey = self::PREFIX . substr(hash('sha256', $key), 0, 24);
        if (isset($this->memory[$safeKey])) {
            return $this->memory[$safeKey];
        }

        if (function_exists('get_transient')) {
            $val = get_transient($safeKey);
            if (is_array($val)) {
                $this->memory[$safeKey] = $val;
                return $val;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value, int $ttl = 900): void {
        $safeKey                = self::PREFIX . substr(hash('sha256', $key), 0, 24);
        $this->memory[$safeKey] = $value;

        if (function_exists('set_transient')) {
            set_transient($safeKey, $value, max(60, $ttl));
        }
    }

    public function flush(): void {
        $this->memory = [];
    }
}

