<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

/**
 * Quản lý & lắp ghép 16 module Section tái sử dụng thuần code PHP (Phương án S-3, ADR D-34).
 */
final class SectionRegistry {
    /** @var array<string, array{defaults?: array<string, mixed>, style?: string, script?: string}>|null */
    private static ?array $definitions = null;

    /**
     * Render 1 section chuẩn hóa: merge default props -> enqueue CSS/JS chunk riêng (nếu có) -> render view.
     *
     * @param array<string, mixed> $props
     */
    public static function render(string $name, array $props = []): void {
        if (array_key_exists('enabled', $props) && empty($props['enabled'])) {
            return;
        }

        $safe_name = (string) preg_replace('/[^a-z0-9-]/', '', strtolower($name));
        if ($safe_name === '') {
            return;
        }

        $definitions = self::definitions();
        $meta        = $definitions[$safe_name] ?? [];

        if (!empty($meta['style']) && is_string($meta['style'])) {
            Assets::enqueue_section_style($meta['style']);
        }

        if (!empty($meta['script']) && is_string($meta['script'])) {
            Assets::enqueue_section_script($meta['script']);
        }

        $defaults = isset($meta['defaults']) && is_array($meta['defaults']) ? $meta['defaults'] : [];
        $resolved = array_replace_recursive($defaults, $props);

        Template::render_part('template-parts/sections/' . $safe_name, $resolved);
    }

    /**
     * Lắp ghép nguyên 1 trang từ danh sách cấu hình sections (Phương án S-3).
     *
     * @param array<int, array{section: string, props?: array<string, mixed>}> $layout
     */
    public static function render_stack(array $layout): void {
        foreach ($layout as $block) {
            if (empty($block['section']) || !is_string($block['section'])) {
                continue;
            }
            $props = isset($block['props']) && is_array($block['props']) ? $block['props'] : [];
            self::render($block['section'], $props);
        }
    }

    /**
     * @return array<string, array{defaults?: array<string, mixed>, style?: string, script?: string}>
     */
    public static function definitions(): array {
        if (self::$definitions === null) {
            $config_file = DRV_DIR . '/config/sections.php';
            $loaded      = is_readable($config_file) ? require $config_file : [];
            self::$definitions = is_array($loaded) ? $loaded : [];
        }

        return self::$definitions;
    }

    public static function reset_cache(): void {
        self::$definitions = null;
    }
}

