<?php
declare(strict_types=1);

namespace Dravit\Advisor\Contracts;

defined('ABSPATH') || exit;

/**
 * Hợp đồng bộ nhớ đệm kết quả tư vấn AI (ADR D-47).
 */
interface ResultCacheInterface {
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value, int $ttl = 900): void;

    public function flush(): void;
}

