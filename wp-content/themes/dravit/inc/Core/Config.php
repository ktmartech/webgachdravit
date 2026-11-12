<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

/**
 * Bộ nạp từ điển cấu hình tĩnh trong `config/{name}.php` có bộ nhớ đệm tĩnh trong request.
 */
final class Config {
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public static function get(string $name): array {
        $safe = (string) preg_replace('/[^a-z0-9_-]/i', '', $name);
        if ($safe === '') {
            return [];
        }

        if (isset(self::$cache[$safe])) {
            return self::$cache[$safe];
        }

        $path = DRV_DIR . '/config/' . $safe . '.php';
        if (!is_readable($path)) {
            return [];
        }

        $loaded = require $path;
        self::$cache[$safe] = is_array($loaded) ? $loaded : [];

        return self::$cache[$safe];
    }

    public static function flush_cache(): void {
        self::$cache = [];
    }
}

