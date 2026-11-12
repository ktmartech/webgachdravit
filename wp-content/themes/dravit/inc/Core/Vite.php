<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

final class Vite {
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $manifest = null;

    private static ?string $manifest_path = null;

    public static function is_dev_server_running(): bool {
        return is_file(self::hot_file_path());
    }

    public static function dev_server_url(): string {
        if (!self::is_dev_server_running()) {
            return '';
        }

        $url = trim((string) file_get_contents(self::hot_file_path()));
        return rtrim($url, '/');
    }

    /**
     * Đọc và cache manifest của Vite (`.vite/manifest.json`).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function manifest(?string $custom_path = null): array {
        $target = $custom_path ?? (DRV_DIR . '/assets/dist/.vite/manifest.json');

        if (self::$manifest !== null && self::$manifest_path === $target) {
            return self::$manifest;
        }

        self::$manifest_path = $target;

        if (!is_readable($target)) {
            self::$manifest = [];
            return self::$manifest;
        }

        $raw     = (string) file_get_contents($target);
        $decoded = json_decode($raw, true);

        self::$manifest = is_array($decoded) ? $decoded : [];
        return self::$manifest;
    }

    /**
     * Phân giải URL đã băm (hashed URL) của 1 entry từ manifest hoặc Vite HMR server.
     */
    public static function asset_url(string $entry, ?string $manifest_path = null): ?string {
        $entry = ltrim($entry, '/');

        if ($manifest_path === null && self::is_dev_server_running()) {
            return self::dev_server_url() . '/' . $entry;
        }

        $manifest = self::manifest($manifest_path);
        $record   = $manifest[$entry] ?? null;

        if (!is_array($record) || empty($record['file']) || !is_string($record['file'])) {
            return null;
        }

        return DRV_URI . '/assets/dist/' . ltrim($record['file'], '/');
    }

    /**
     * Lấy danh sách các chunk phụ thuộc (`imports`) để in `<link rel="modulepreload">`.
     *
     * @return array<int, string>
     */
    public static function preload_urls(string $entry, ?string $manifest_path = null): array {
        $entry = ltrim($entry, '/');
        if ($manifest_path === null && self::is_dev_server_running()) {
            return [];
        }

        $manifest = self::manifest($manifest_path);
        $record   = $manifest[$entry] ?? null;
        if (!is_array($record) || empty($record['imports']) || !is_array($record['imports'])) {
            return [];
        }

        $urls = [];
        foreach ($record['imports'] as $import_key) {
            if (!is_string($import_key) || !isset($manifest[$import_key]['file'])) {
                continue;
            }
            $file = $manifest[$import_key]['file'];
            if (is_string($file) && $file !== '') {
                $urls[] = DRV_URI . '/assets/dist/' . ltrim($file, '/');
            }
        }

        return array_values(array_unique($urls));
    }

    public static function reset_cache(): void {
        self::$manifest      = null;
        self::$manifest_path = null;
    }

    private static function hot_file_path(): string {
        return DRV_DIR . '/assets/dist/hot';
    }
}

