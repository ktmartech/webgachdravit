<?php
declare(strict_types=1);

namespace Dravit\Advisor\Support;

defined('ABSPATH') || exit;

/**
 * Bộ đọc YAML gọn nhẹ thuần PHP (0 thư viện ngoài) phục vụ `config/knowledge/*.yaml` và `tests/advisor/cases.yaml`.
 * Hỗ trợ: comment `#`, map lồng nhau, list scalar, list of maps (`- key: val`), chuỗi ngoặc kép/đơn, số, bool, null, mảng inline `[...]`.
 */
final class SimpleYaml {
    /**
     * @return array<string, mixed>
     */
    public static function parseFile(string $filePath): array {
        if (!is_readable($filePath)) {
            return [];
        }
        $raw = file_get_contents($filePath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        return self::parse($raw);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parse(string $yaml): array {
        $normalized = str_replace(["\r\n", "\r"], "\n", $yaml);
        $rawLines   = explode("\n", $normalized);
        $lines      = [];

        foreach ($rawLines as $rawLine) {
            $cleaned = self::stripComment($rawLine);
            if (trim($cleaned) === '' || trim($cleaned) === '---') {
                continue;
            }
            $indent  = strlen($cleaned) - strlen(ltrim($cleaned, ' '));
            $lines[] = [
                'indent' => $indent,
                'text'   => trim($cleaned),
            ];
        }

        if ($lines === []) {
            return [];
        }

        $index  = 0;
        $parsed = self::parseBlock($lines, $index, $lines[0]['indent']);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<int, array{indent: int, text: string}> $lines
     */
    private static function parseBlock(array $lines, int &$index, int $baseIndent): mixed {
        $count = count($lines);
        if ($index >= $count) {
            return [];
        }

        if (str_starts_with($lines[$index]['text'], '- ') || $lines[$index]['text'] === '-') {
            return self::parseSequence($lines, $index, $baseIndent);
        }

        return self::parseMapping($lines, $index, $baseIndent);
    }

    /**
     * @param array<int, array{indent: int, text: string}> $lines
     * @return array<int, mixed>
     */
    private static function parseSequence(array $lines, int &$index, int $baseIndent): array {
        $result = [];
        $count  = count($lines);

        while ($index < $count) {
            $line = $lines[$index];
            if ($line['indent'] < $baseIndent) {
                break;
            }
            if ($line['indent'] > $baseIndent) {
                break;
            }
            if (!str_starts_with($line['text'], '- ') && $line['text'] !== '-') {
                break;
            }

            $rest = $line['text'] === '-' ? '' : trim(substr($line['text'], 2));
            $index++;

            if ($rest === '') {
                if ($index < $count && $lines[$index]['indent'] > $baseIndent) {
                    $result[] = self::parseBlock($lines, $index, $lines[$index]['indent']);
                } else {
                    $result[] = null;
                }
                continue;
            }

            $kv = self::splitKeyValue($rest);
            if ($kv !== null) {
                [$k, $vRaw] = $kv;
                $item       = [];
                if ($vRaw === '') {
                    if ($index < $count && $lines[$index]['indent'] > $baseIndent + 2) {
                        $item[$k] = self::parseBlock($lines, $index, $lines[$index]['indent']);
                    } elseif ($index < $count && $lines[$index]['indent'] > $baseIndent && str_starts_with($lines[$index]['text'], '-')) {
                        $item[$k] = self::parseBlock($lines, $index, $lines[$index]['indent']);
                    } else {
                        $item[$k] = null;
                    }
                } else {
                    $item[$k] = self::parseScalarOrInline($vRaw);
                }

                // Đọc tiếp các thuộc tính cùng một phần tử map trong sequence (`indent > $baseIndent`)
                if ($index < $count && $lines[$index]['indent'] > $baseIndent) {
                    $itemIndent = $lines[$index]['indent'];
                    $extra      = self::parseMapping($lines, $index, $itemIndent);
                    foreach ($extra as $ek => $ev) {
                        $item[$ek] = $ev;
                    }
                }

                $result[] = $item;
                continue;
            }

            $result[] = self::parseScalarOrInline($rest);
        }

        return $result;
    }

    /**
     * @param array<int, array{indent: int, text: string}> $lines
     * @return array<string, mixed>
     */
    private static function parseMapping(array $lines, int &$index, int $baseIndent): array {
        $result = [];
        $count  = count($lines);

        while ($index < $count) {
            $line = $lines[$index];
            if ($line['indent'] < $baseIndent) {
                break;
            }
            if ($line['indent'] > $baseIndent) {
                break;
            }
            if (str_starts_with($line['text'], '- ') || $line['text'] === '-') {
                break;
            }

            $kv = self::splitKeyValue($line['text']);
            if ($kv === null) {
                $index++;
                continue;
            }

            [$key, $valRaw] = $kv;
            $index++;

            if ($valRaw === '') {
                if ($index < $count && $lines[$index]['indent'] > $baseIndent) {
                    $result[$key] = self::parseBlock($lines, $index, $lines[$index]['indent']);
                } else {
                    $result[$key] = [];
                }
            } else {
                $result[$key] = self::parseScalarOrInline($valRaw);
            }
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function splitKeyValue(string $text): ?array {
        $inSingle = false;
        $inDouble = false;
        $len      = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && ($i === 0 || $text[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            } elseif ($ch === ':' && !$inSingle && !$inDouble) {
                $next = $i + 1 < $len ? $text[$i + 1] : '';
                if ($next === '' || $next === ' ' || $next === "\t") {
                    $rawKey = trim(substr($text, 0, $i));
                    $key    = trim($rawKey, "\"'");
                    $val    = trim(substr($text, $i + 1));
                    if ($key !== '') {
                        return [$key, $val];
                    }
                }
            }
        }

        return null;
    }

    private static function parseScalarOrInline(string $raw): mixed {
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'null' || $trimmed === '~') {
            return null;
        }
        if ($trimmed === 'true') {
            return true;
        }
        if ($trimmed === 'false') {
            return false;
        }
        if ($trimmed === '[]') {
            return [];
        }
        if ($trimmed === '{}') {
            return [];
        }

        if (
            (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
            || (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
        ) {
            $inner = substr($trimmed, 1, -1);
            return str_starts_with($trimmed, '"') ? stripcslashes($inner) : str_replace("''", "'", $inner);
        }

        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $inner = trim(substr($trimmed, 1, -1));
            if ($inner === '') {
                return [];
            }
            $parts = self::splitInlineItems($inner);
            $list  = [];
            foreach ($parts as $p) {
                $list[] = self::parseScalarOrInline($p);
            }
            return $list;
        }

        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $inner = trim(substr($trimmed, 1, -1));
            if ($inner === '') {
                return [];
            }
            $parts = self::splitInlineItems($inner);
            $map   = [];
            foreach ($parts as $p) {
                $kv = self::splitKeyValue($p);
                if ($kv !== null) {
                    $map[$kv[0]] = self::parseScalarOrInline($kv[1]);
                }
            }
            return $map;
        }

        if (preg_match('/^-?\d+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }
        if (preg_match('/^-?\d+\.\d+$/', $trimmed) === 1) {
            return (float) $trimmed;
        }

        return $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private static function splitInlineItems(string $inner): array {
        $items    = [];
        $buf      = '';
        $inSingle = false;
        $inDouble = false;
        $depth    = 0;
        $len      = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && ($i === 0 || $inner[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            } elseif (!$inSingle && !$inDouble) {
                if ($ch === '[' || $ch === '{') {
                    $depth++;
                } elseif ($ch === ']' || $ch === '}') {
                    $depth = max(0, $depth - 1);
                } elseif ($ch === ',' && $depth === 0) {
                    $items[] = trim($buf);
                    $buf     = '';
                    continue;
                }
            }
            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $items[] = trim($buf);
        }

        return $items;
    }

    private static function stripComment(string $line): string {
        $inSingle = false;
        $inDouble = false;
        $len      = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && ($i === 0 || $line[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            } elseif ($ch === '#' && !$inSingle && !$inDouble) {
                if ($i === 0 || $line[$i - 1] === ' ' || $line[$i - 1] === "\t") {
                    return rtrim(substr($line, 0, $i));
                }
            }
        }

        return rtrim($line);
    }
}

