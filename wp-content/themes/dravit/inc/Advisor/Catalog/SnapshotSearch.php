<?php
declare(strict_types=1);

namespace Dravit\Advisor\Catalog;

use Dravit\Advisor\Contracts\CatalogSearchInterface;

defined('ABSPATH') || exit;

/**
 * Đọc snapshot sản phẩm trong RAM / Transient / File JSON (`< 5ms`, không chạy `WP_Query` mỗi lượt hỏi - ADR D-32, D-49).
 */
final class SnapshotSearch implements CatalogSearchInterface {
    /** @var array<string, mixed>|null */
    private static ?array $memorySnapshot = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $injectedItems;

    private string $injectedVersion;

    /**
     * Cho phép truyền trực tiếp mảng ứng viên giả lập trong PHPUnit mà không cần chạm DB/Filesystem.
     *
     * @param array<int, array<string, mixed>>|null $injectedItems
     */
    public function __construct(?array $injectedItems = null, string $injectedVersion = 'test-v1') {
        $this->injectedItems   = $injectedItems;
        $this->injectedVersion = $injectedVersion;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public static function primeMemory(array $snapshot): void {
        self::$memorySnapshot = $snapshot;
    }

    public static function flushMemory(): void {
        self::$memorySnapshot = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array {
        if ($this->injectedItems !== null) {
            return [
                'version'  => $this->injectedVersion,
                'built_at' => '2026-11-13T00:00:00+00:00',
                'count'    => count($this->injectedItems),
                'items'    => $this->injectedItems,
            ];
        }

        if (self::$memorySnapshot !== null) {
            return self::$memorySnapshot;
        }

        if (function_exists('get_transient')) {
            $cached = get_transient(SnapshotBuilder::TRANSIENT_KEY);
            if (is_array($cached) && isset($cached['items']) && is_array($cached['items'])) {
                self::$memorySnapshot = $cached;
                return $cached;
            }
        }

        $filePath = SnapshotBuilder::snapshotFilePath();
        if ($filePath !== '' && is_readable($filePath)) {
            $raw = file_get_contents($filePath);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['items']) && is_array($decoded['items'])) {
                    self::$memorySnapshot = $decoded;
                    if (function_exists('set_transient')) {
                        set_transient(SnapshotBuilder::TRANSIENT_KEY, $decoded, DAY_IN_SECONDS);
                    }
                    return $decoded;
                }
            }
        }

        $rebuilt              = (new SnapshotBuilder())->build();
        self::$memorySnapshot = $rebuilt;

        return $rebuilt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCandidates(): array {
        $snapshot = $this->getSnapshot();
        $items    = $snapshot['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $typed */
        $typed = array_values(array_filter($items, 'is_array'));

        return $typed;
    }

    public function getVersion(): string {
        $snapshot = $this->getSnapshot();
        return isset($snapshot['version']) && is_string($snapshot['version']) && $snapshot['version'] !== ''
            ? $snapshot['version']
            : 'v0';
    }

    /**
     * @return array<string, mixed>
     */
    public function rebuild(): array {
        if ($this->injectedItems !== null) {
            return $this->getSnapshot();
        }

        return (new SnapshotBuilder())->build();
    }
}

