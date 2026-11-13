<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Advisor\Support\SimpleYaml;

defined('ABSPATH') || exit;

/**
 * Nạp và tra cứu bộ tri thức kỹ thuật đã duyệt (`config/knowledge/*.md|yaml` - docs/13 §2.2).
 * Hỗ trợ kiểm tra trích dẫn hợp lệ `kb_refs[]` (`kb-id` và `kb-id#anchor`) cho Validator L4/L5.
 */
final class KnowledgeBase {
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $markdownIndex = null;

    /** @var array<string, mixed>|null */
    private static ?array $synonyms = null;

    /** @var array<string, mixed>|null */
    private static ?array $flowConfig = null;

    private string $baseDir;

    public function __construct(?string $baseDir = null) {
        $this->baseDir = $baseDir !== null ? rtrim($baseDir, '/\\') : DRV_DIR . '/config/knowledge';
    }

    public static function resetCache(): void {
        self::$markdownIndex = null;
        self::$synonyms      = null;
        self::$flowConfig    = null;
    }

    /**
     * Từ điển đồng nghĩa `kb-tu-dien-dong-nghia.yaml`.
     *
     * @return array<string, mixed>
     */
    public function getSynonyms(): array {
        if (self::$synonyms !== null && $this->baseDir === DRV_DIR . '/config/knowledge') {
            return self::$synonyms;
        }

        $parsed = SimpleYaml::parseFile($this->baseDir . '/kb-tu-dien-dong-nghia.yaml');
        if ($this->baseDir === DRV_DIR . '/config/knowledge') {
            self::$synonyms = $parsed;
        }

        return $parsed;
    }

    /**
     * Quy trình tư vấn & câu hỏi làm rõ `kb-quy-trinh-tu-van.yaml`.
     *
     * @return array<string, mixed>
     */
    public function getFlowConfig(): array {
        if (self::$flowConfig !== null && $this->baseDir === DRV_DIR . '/config/knowledge') {
            return self::$flowConfig;
        }

        $parsed = SimpleYaml::parseFile($this->baseDir . '/kb-quy-trinh-tu-van.yaml');
        if ($this->baseDir === DRV_DIR . '/config/knowledge') {
            self::$flowConfig = $parsed;
        }

        return $parsed;
    }

    /**
     * Toàn bộ tài liệu Markdown đã đánh chỉ mục theo `id` và `sections[anchor]`.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllDocuments(): array {
        if (self::$markdownIndex !== null && $this->baseDir === DRV_DIR . '/config/knowledge') {
            return self::$markdownIndex;
        }

        $index = [];
        $files = glob($this->baseDir . '/*.md');
        if (is_array($files)) {
            sort($files);
            foreach ($files as $file) {
                $doc = $this->parseMarkdownFile($file);
                if ($doc !== null) {
                    $index[(string) $doc['id']] = $doc;
                }
            }
        }

        if ($this->baseDir === DRV_DIR . '/config/knowledge') {
            self::$markdownIndex = $index;
        }

        return $index;
    }

    /**
     * Kiểm tra mã trích dẫn `kb_ref` (ví dụ `kb-an-toan-chong-truot` hoặc `kb-an-toan-chong-truot#r-nha-tam`) có tồn tại thật không.
     */
    public function isValidRef(string $ref): bool {
        $clean = trim($ref);
        if ($clean === '') {
            return false;
        }

        $parts  = explode('#', $clean, 2);
        $docId  = $parts[0];
        $anchor = $parts[1] ?? '';

        $docs = $this->getAllDocuments();
        if (!isset($docs[$docId])) {
            return false;
        }

        if ($anchor === '') {
            return true;
        }

        $sections = $docs[$docId]['sections'] ?? [];
        return is_array($sections) && isset($sections[$anchor]);
    }

    /**
     * Lấy nội dung tóm tắt của 1 `kb_ref` để phục vụ trả lời `knowledge` / `policy` hoặc hiển thị nguồn.
     *
     * @return array{ref: string, title: string, content: string, topic: string}|null
     */
    public function getSectionByRef(string $ref): ?array {
        if (!$this->isValidRef($ref)) {
            return null;
        }

        $parts  = explode('#', trim($ref), 2);
        $docId  = $parts[0];
        $anchor = $parts[1] ?? '';
        $doc    = $this->getAllDocuments()[$docId];
        $topic  = (string) ($doc['topic'] ?? '');

        if ($anchor !== '' && isset($doc['sections'][$anchor]) && is_array($doc['sections'][$anchor])) {
            $sec = $doc['sections'][$anchor];
            return [
                'ref'     => $docId . '#' . $anchor,
                'title'   => (string) ($sec['title'] ?? $docId),
                'content' => (string) ($sec['content'] ?? ''),
                'topic'   => $topic,
            ];
        }

        $firstSec = is_array($doc['sections']) && $doc['sections'] !== [] ? reset($doc['sections']) : null;
        if (is_array($firstSec)) {
            return [
                'ref'     => $docId,
                'title'   => (string) ($firstSec['title'] ?? $docId),
                'content' => (string) ($firstSec['content'] ?? ''),
                'topic'   => $topic,
            ];
        }

        return [
            'ref'     => $docId,
            'title'   => $docId,
            'content' => (string) ($doc['body'] ?? ''),
            'topic'   => $topic,
        ];
    }

    /**
     * Chọn các tài liệu Markdown theo danh sách `topics` để đưa vào ngữ cảnh LLM (Prompt Caching).
     *
     * @param array<int, string> $topics
     */
    public function buildPromptKnowledgeContext(array $topics): string {
        $docs   = $this->getAllDocuments();
        $blocks = [];

        foreach ($docs as $id => $doc) {
            $docTopic = (string) ($doc['topic'] ?? '');
            if ($topics !== [] && !in_array($docTopic, $topics, true) && !in_array($id, $topics, true)) {
                continue;
            }
            $blocks[] = sprintf("[DOC id=\"%s\" topic=\"%s\"]\n%s\n[/DOC]", $id, $docTopic, trim((string) ($doc['body'] ?? '')));
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseMarkdownFile(string $filePath): ?array {
        if (!is_readable($filePath)) {
            return null;
        }
        $raw = file_get_contents($filePath);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $meta       = [];
        $body       = $normalized;

        if (preg_match('/^---\n(.*?)\n---\n(.*)$/s', $normalized, $m) === 1) {
            $meta = SimpleYaml::parse($m[1]);
            $body = trim($m[2]);
        }

        $id = isset($meta['id']) && is_string($meta['id']) && $meta['id'] !== ''
            ? $meta['id']
            : pathinfo($filePath, PATHINFO_FILENAME);

        $sections      = [];
        $currentAnchor = '';
        $currentTitle  = '';
        $currentLines  = [];

        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^#{1,3}\s+(.+?)(?:\s*\{#([a-z0-9\-]+)\})?\s*$/u', $line, $hm) === 1) {
                if ($currentAnchor !== '') {
                    $sections[$currentAnchor] = [
                        'title'   => $currentTitle,
                        'content' => trim(implode("\n", $currentLines)),
                    ];
                }
                $currentTitle  = trim($hm[1]);
                $currentAnchor = $hm[2] ?? $this->slugifyHeading($currentTitle);
                $currentLines  = [];
                continue;
            }

            if ($currentAnchor !== '') {
                $currentLines[] = $line;
            }
        }

        if ($currentAnchor !== '') {
            $sections[$currentAnchor] = [
                'title'   => $currentTitle,
                'content' => trim(implode("\n", $currentLines)),
            ];
        }

        return [
            'id'          => $id,
            'topic'       => (string) ($meta['topic'] ?? ''),
            'version'     => (string) ($meta['version'] ?? '1.0.0'),
            'reviewed_by' => (string) ($meta['reviewed_by'] ?? ''),
            'body'        => $body,
            'sections'    => $sections,
        ];
    }

    private function slugifyHeading(string $heading): string {
        $ascii = \Dravit\Catalog\SearchNormalizer::asciiFold(mb_strtolower($heading, 'UTF-8'));
        $slug  = (string) preg_replace('/[^a-z0-9]+/', '-', $ascii);
        return trim($slug, '-');
    }
}
