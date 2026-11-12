<?php
declare(strict_types=1);

namespace Dravit\Catalog;

defined('ABSPATH') || exit;

/**
 * Bộ khớp luật phân loại tự động 28 Smart Collections từ `config/collections.php` (docs/03 §3.1, §3.3).
 */
final class RuleMatcher {
    /**
     * Kiểm tra một biểu thức luật (`all`, `any`, `line`, `attr`, `flag`) trên ngữ cảnh sản phẩm.
     *
     * @param array<string, mixed> $rule
     * @param array{
     *     line?: string,
     *     attrs?: array<string, array<int, string>>,
     *     flags?: array<string, bool>
     * } $context
     */
    public static function match(array $rule, array $context): bool {
        if (isset($rule['all']) && is_array($rule['all'])) {
            foreach ($rule['all'] as $sub) {
                if (!is_array($sub) || !self::match($sub, $context)) {
                    return false;
                }
            }
            return $rule['all'] !== [];
        }

        if (isset($rule['any']) && is_array($rule['any'])) {
            foreach ($rule['any'] as $sub) {
                if (is_array($sub) && self::match($sub, $context)) {
                    return true;
                }
            }
            return false;
        }

        if (isset($rule['line'])) {
            return ($context['line'] ?? 'gach') === (string) $rule['line'];
        }

        if (isset($rule['flag'])) {
            $flag = (string) $rule['flag'];
            return !empty($context['flags'][$flag]);
        }

        if (isset($rule['attr'])) {
            $tax      = (string) $rule['attr'];
            $expected = isset($rule['in']) && is_array($rule['in']) ? array_map('strval', $rule['in']) : [];
            $actual   = isset($context['attrs'][$tax]) && is_array($context['attrs'][$tax])
                ? array_map('strval', $context['attrs'][$tax])
                : [];

            if ($expected === [] || $actual === []) {
                return false;
            }

            return array_intersect($expected, $actual) !== [];
        }

        return false;
    }

    /**
     * Khớp toàn bộ 28 Smart Collections cho 1 SKU dòng `gach`.
     * Nếu `line !== 'gach'`, trả về mảng rỗng `[]` (không gán vào bất kỳ collection gạch nào).
     *
     * @param array<string, array<string, mixed>> $collections_config
     * @param array{
     *     line?: string,
     *     attrs?: array<string, array<int, string>>,
     *     flags?: array<string, bool>
     * } $context
     * @return array<int, string> Danh sách slug `product_cat` khớp luật
     */
    public static function matchCollections(array $collections_config, array $context): array {
        $line = (string) ($context['line'] ?? 'gach');
        if ($line !== 'gach') {
            return [];
        }

        $matched = [];
        foreach ($collections_config as $slug => $item) {
            $rule = isset($item['rule']) && is_array($item['rule']) ? $item['rule'] : [];
            if ($rule !== [] && self::match($rule, $context)) {
                $matched[] = (string) $slug;
            }
        }

        return $matched;
    }
}

