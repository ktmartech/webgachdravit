<?php
declare(strict_types=1);

namespace Dravit\Woo;

defined('ABSPATH') || exit;

/**
 * Tra cứu danh mục Phường/Xã 2 cấp (docs/15 §2.7, O-06) từ `assets/data/wards/{KEY}.json`
 * do `tools/build-wards.mjs` sinh. Dùng cho SSR select `billing_city` và validate server-side.
 */
final class Wards {
    /** @var array<string, array<int, array{code: string, name: string, type: string}>> */
    private static array $cache = [];

    /** @var array<string, string>|null */
    private static ?array $provinces = null;

    /**
     * @return array<string, string> [KEY => Tên tỉnh/thành]
     */
    public static function provinces(): array {
        if (self::$provinces === null) {
            $file            = (defined('DRV_DIR') ? DRV_DIR : dirname(__DIR__, 2)) . '/config/provinces-vn.php';
            $data            = is_readable($file) ? require $file : [];
            self::$provinces = is_array($data) ? array_map('strval', $data) : [];
        }
        return self::$provinces;
    }

    public static function isProvince(string $key): bool {
        return $key !== '' && isset(self::provinces()[$key]);
    }

    public static function dataDir(): string {
        return (defined('DRV_DIR') ? DRV_DIR : dirname(__DIR__, 2)) . '/assets/data/wards';
    }

    public static function dataUrlBase(): string {
        return (defined('DRV_URI') ? DRV_URI : '') . '/assets/data/wards';
    }

    /**
     * @return array<int, array{code: string, name: string, type: string}>
     */
    public static function forProvince(string $key): array {
        if (!self::isProvince($key) || !preg_match('/^[A-Z]{2,4}$/', $key)) {
            return [];
        }
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $file = self::dataDir() . '/' . $key . '.json';
        $raw  = is_readable($file) ? (string) file_get_contents($file) : '';
        $json = $raw !== '' ? json_decode($raw, true) : null;
        $list = [];
        if (is_array($json) && isset($json['wards']) && is_array($json['wards'])) {
            foreach ($json['wards'] as $w) {
                if (is_array($w) && isset($w['code'], $w['name'])) {
                    $list[] = [
                        'code' => (string) $w['code'],
                        'name' => (string) $w['name'],
                        'type' => (string) ($w['type'] ?? 'xa'),
                    ];
                }
            }
        }
        return self::$cache[$key] = $list;
    }

    /**
     * @return array{code: string, name: string, type: string}|null
     */
    public static function find(string $province_key, string $ward_code): ?array {
        foreach (self::forProvince($province_key) as $w) {
            if ($w['code'] === $ward_code) {
                return $w;
            }
        }
        return null;
    }

    /**
     * @return array{code: string, name: string, type: string}|null
     */
    public static function findByName(string $province_key, string $ward_name): ?array {
        $needle = trim($ward_name);
        if ($needle === '') {
            return null;
        }
        foreach (self::forProvince($province_key) as $w) {
            if ($w['name'] === $needle) {
                return $w;
            }
        }
        return null;
    }

    /**
     * Chỉ phục vụ PHPUnit (nạp danh sách giả lập).
     *
     * @param array<string, string>                                                $provinces
     * @param array<string, array<int, array{code: string, name: string, type: string}>> $wards
     */
    public static function seedForTests(array $provinces, array $wards): void {
        self::$provinces = $provinces;
        self::$cache     = $wards;
    }

    public static function resetForTests(): void {
        self::$provinces = null;
        self::$cache     = [];
    }
}
