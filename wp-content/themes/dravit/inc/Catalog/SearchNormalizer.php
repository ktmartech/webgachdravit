<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Bộ chuẩn hóa chuỗi tìm kiếm tiếng Việt không dấu, quy đổi kích thước mm -> cm và ánh xạ từ đồng nghĩa
 * (docs/15 §2.2, docs/19 §3.4, docs/21 Step 3.3).
 */
final class SearchNormalizer {
    /**
     * Bảng ánh xạ nguyên âm có dấu tiếng Việt & đ/Đ -> ASCII thường.
     *
     * @var array<string, string>
     */
    private const VIETNAMESE_MAP = [
        'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'À' => 'a', 'Á' => 'a', 'Ả' => 'a', 'Ã' => 'a', 'Ạ' => 'a',
        'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a', 'Ặ' => 'a',
        'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a', 'Ậ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'È' => 'e', 'É' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e', 'Ẹ' => 'e',
        'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ể' => 'e', 'Ễ' => 'e', 'Ệ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'Ì' => 'i', 'Í' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i', 'Ị' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'Ò' => 'o', 'Ó' => 'o', 'Ỏ' => 'o', 'Õ' => 'o', 'Ọ' => 'o',
        'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o', 'Ộ' => 'o',
        'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o', 'Ợ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'Ù' => 'u', 'Ú' => 'u', 'Ủ' => 'u', 'Ũ' => 'u', 'Ụ' => 'u',
        'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ử' => 'u', 'Ữ' => 'u', 'Ự' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        'Ỳ' => 'y', 'Ý' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y', 'Ỵ' => 'y',
        'đ' => 'd', 'Đ' => 'd',
    ];

    /**
     * Tập kích thước chuẩn theo đơn vị cm (slug) để phân biệt tức thì giữa nhập cm (80x80, 100x100, 75x150)
     * và nhập mm (800x800, 75x300, 100x200, 600x1200).
     *
     * @var array<string, bool>
     */
    private const KNOWN_CM_SLUGS = [
        '5x20'    => true,
        '6x24'    => true,
        '7-5x30'  => true,
        '10x20'   => true,
        '10x30'   => true,
        '15x60'   => true,
        '15x80'   => true,
        '15x90'   => true,
        '20x20'   => true,
        '20x100'  => true,
        '20x120'  => true,
        '30x30'   => true,
        '30x60'   => true,
        '30x90'   => true,
        '40x40'   => true,
        '40x80'   => true,
        '50x50'   => true,
        '60x60'   => true,
        '60x90'   => true,
        '60x120'  => true,
        '75x150'  => true,
        '80x80'   => true,
        '80x160'  => true,
        '90x90'   => true,
        '90x180'  => true,
        '100x100' => true,
        '120x120' => true,
        '120x180' => true,
        '120x240' => true,
        '120x270' => true,
        '120x280' => true,
        '160x320' => true,
    ];

    /**
     * Từ điển đồng nghĩa thực tế của khách hàng dẫn tới các Smart Collections (docs/19 §3.4).
     *
     * @var array<string, array<int, string>>
     */
    private const SILO_SYNONYMS = [
        'wc'          => ['gach-nha-tam'],
        'toilet'      => ['gach-nha-tam'],
        've sinh'     => ['gach-nha-tam'],
        'phong tam'   => ['gach-nha-tam'],
        'nha ve sinh' => ['gach-nha-tam'],
        'san go'      => ['gach-gia-go'],
        'van go'      => ['gach-gia-go'],
        'go'          => ['gach-gia-go', 'gach-15x80-20x100'],
        'marble'      => ['gach-van-da'],
        'cam thach'   => ['gach-van-da'],
        'calacatta'   => ['gach-van-da'],
        'travertine'  => ['gach-van-da'],
        'be tong'     => ['gach-van-xi-mang'],
        'cement'      => ['gach-van-xi-mang'],
        'da mai'      => ['gach-terrazzo'],
        'big slab'    => ['da-nung-ket', 'gach-60x120'],
        'kho lon'     => ['da-nung-ket', 'gach-60x120'],
        'sintered'    => ['da-nung-ket'],
        'subway'      => ['gach-the'],
        'indochine'   => ['gach-bong'],
        'dong duong'  => ['gach-bong'],
        'chan nang'   => ['gach-bong-gio'],
        'thong gio'   => ['gach-bong-gio'],
        'ban cong'    => ['gach-ngoai-troi'],
        'san thuong'  => ['gach-ngoai-troi'],
        'gara'        => ['gach-san-vuon'],
        'be boi'      => ['gach-ho-boi'],
        'khuyen mai'  => ['gach-uu-dai'],
        'giam gia'    => ['gach-uu-dai'],
        'xa kho'      => ['gach-uu-dai'],
        'sale'        => ['gach-uu-dai'],
    ];

    /**
     * Khử toàn bộ dấu tiếng Việt (cả NFC & NFD combining marks), đổi đ -> d, thường hóa ASCII.
     */
    public static function asciiFold(string $text): string {
        if ($text === '') {
            return '';
        }

        $folded = strtr($text, self::VIETNAMESE_MAP);
        if (function_exists('remove_accents')) {
            $folded = remove_accents($folded);
        }

        // Khử dấu tổ hợp Unicode NFD (copy từ iOS/macOS).
        $folded = (string) preg_replace('/\p{Mn}+/u', '', $folded);
        $folded = strtolower($folded);

        return trim((string) preg_replace('/\s+/', ' ', $folded));
    }

    /**
     * Chuẩn hóa toàn diện chuỗi truy vấn:
     * `"Gạch lát nền Đá mờ 800x800"` -> `"gach lat nen da mo 80x80"`.
     */
    public static function normalize(string $query): string {
        $ascii = self::asciiFold($query);
        if ($ascii === '') {
            return '';
        }

        // Quy đổi các cụm kích thước (VD: 800x800, 600 x 1200, 75x300, 7,5x30cm) sang slug cm chuẩn.
        $ascii = (string) preg_replace_callback(
            '/(?<![a-z0-9])(\d+(?:[.,\-]\d+)?)\s*[x*×]\s*(\d+(?:[.,\-]\d+)?)(?:\s*(mm|cm))?(?![a-z0-9])/i',
            static function (array $matches): string {
                return self::normalizeDimensionMatch($matches[1], $matches[2], $matches[3] ?? '');
            },
            $ascii
        );

        // Giữ chữ cái, chữ số và dấu gạch ngang nối bên trong từ (như drv-8801, 7-5x30).
        $ascii = (string) preg_replace('/[^a-z0-9\-]+/', ' ', $ascii);
        $ascii = (string) preg_replace('/(?<![a-z0-9])-+|-+(?![a-z0-9])/', ' ', $ascii);

        return trim((string) preg_replace('/\s+/', ' ', $ascii));
    }

    /**
     * Tách chuỗi truy vấn đã chuẩn hóa thành danh sách token duy nhất (tối đa 8 token để tối ưu SQL).
     *
     * @return array<int, string>
     */
    public static function tokenize(string $query, int $max_tokens = 8): array {
        $normalized = self::normalize($query);
        if ($normalized === '') {
            return [];
        }

        $parts  = explode(' ', $normalized);
        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || isset($tokens[$part])) {
                continue;
            }
            $tokens[$part] = true;
            if (count($tokens) >= $max_tokens) {
                break;
            }
        }

        return array_map('strval', array_keys($tokens));
    }

    /**
     * Tìm các slug danh mục gợi ý khớp từ khóa hoặc từ đồng nghĩa (tối đa `$limit` mục).
     *
     * @param array<string, array<string, mixed>> $collections_config
     * @return array<int, string>
     */
    public static function matchCollectionSlugs(string $query, array $collections_config, int $limit = 4): array {
        $norm = self::normalize($query);
        if ($norm === '' || mb_strlen($norm) < 2) {
            return [];
        }

        $scores = [];

        foreach (self::SILO_SYNONYMS as $phrase => $target_slugs) {
            if ($norm === $phrase || str_contains(' ' . $norm . ' ', ' ' . $phrase . ' ')) {
                foreach ($target_slugs as $idx => $slug) {
                    if (isset($collections_config[$slug])) {
                        $scores[$slug] = max($scores[$slug] ?? 0, 95 - ($idx * 5));
                    }
                }
            }
        }

        $tokens = self::tokenize($norm);

        foreach ($collections_config as $slug => $cfg) {
            if ($slug === 'gach') {
                continue;
            }

            $name_norm = self::normalize((string) ($cfg['name'] ?? ''));
            $h1_norm   = self::normalize((string) ($cfg['h1'] ?? ''));
            $slug_norm = str_replace('-', ' ', $slug);
            $haystack  = $slug . ' ' . $slug_norm . ' ' . $name_norm . ' ' . $h1_norm;

            if ($slug === $norm || $slug_norm === $norm || $name_norm === $norm) {
                $scores[$slug] = max($scores[$slug] ?? 0, 100);
                continue;
            }

            if (str_contains($name_norm, $norm) || str_contains($slug_norm, $norm)) {
                $scores[$slug] = max($scores[$slug] ?? 0, 85);
                continue;
            }

            // Kiểm tra khớp các token có nghĩa (bỏ qua từ chung "gach").
            $meaningful = array_values(array_filter($tokens, static fn (string $t): bool => $t !== 'gach'));
            if ($meaningful === []) {
                continue;
            }

            $matched_count = 0;
            foreach ($meaningful as $tok) {
                if (str_contains($haystack, $tok)) {
                    $matched_count++;
                }
            }

            if ($matched_count === count($meaningful)) {
                $scores[$slug] = max($scores[$slug] ?? 0, 75);
            } elseif ($matched_count > 0 && count($meaningful) > 1) {
                $scores[$slug] = max($scores[$slug] ?? 0, 40 + ($matched_count * 10));
            }
        }

        if ($scores === []) {
            return [];
        }

        // Sắp xếp điểm giảm dần, nếu bằng điểm ưu tiên `priority` trong collections.php.
        uksort($scores, static function (int|string $a, int|string $b) use ($scores, $collections_config): int {
            $ka = (string) $a;
            $kb = (string) $b;
            if ($scores[$a] !== $scores[$b]) {
                return $scores[$b] <=> $scores[$a];
            }
            $pa = (int) ($collections_config[$ka]['priority'] ?? 0);
            $pb = (int) ($collections_config[$kb]['priority'] ?? 0);
            return $pb <=> $pa;
        });

        return array_slice(array_map('strval', array_keys($scores)), 0, max(1, $limit));
    }

    private static function normalizeDimensionMatch(string $raw_a, string $raw_b, string $unit): string {
        $num_a = (float) str_replace(['-', ','], '.', $raw_a);
        $num_b = (float) str_replace(['-', ','], '.', $raw_b);

        if ($num_a <= 0.0 || $num_b <= 0.0) {
            return $raw_a . 'x' . $raw_b;
        }

        $min = min($num_a, $num_b);
        $max = max($num_a, $num_b);
        $u   = strtolower(trim($unit));

        if ($u === 'mm') {
            return self::formatCmSlug($min / 10.0) . 'x' . self::formatCmSlug($max / 10.0);
        }

        $cm_candidate = self::formatCmSlug($min) . 'x' . self::formatCmSlug($max);
        if ($u === 'cm' || isset(self::KNOWN_CM_SLUGS[$cm_candidate])) {
            return $cm_candidate;
        }

        $mm_converted = self::formatCmSlug($min / 10.0) . 'x' . self::formatCmSlug($max / 10.0);
        if (isset(self::KNOWN_CM_SLUGS[$mm_converted]) || $max >= 200.0) {
            return $mm_converted;
        }

        return $cm_candidate;
    }

    private static function formatCmSlug(float $val): string {
        $rounded = round($val, 1);
        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        $formatted = rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
        return str_replace('.', '-', $formatted);
    }
}
