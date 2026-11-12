<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

final class Template {
    /**
     * Render một template part với phạm vi biến cô lập (isolated scope), truyền mảng `$props` qua `$args`.
     *
     * @param array<string, mixed> $props
     */
    public static function render_part(string $slug, array $props = []): void {
        $clean_slug = trim($slug, '/');
        $clean_slug = (string) preg_replace('/[^a-zA-Z0-9_\/-]/', '', $clean_slug);
        if ($clean_slug === '' || str_contains($clean_slug, '..')) {
            return;
        }

        $file = DRV_DIR . '/' . $clean_slug . '.php';
        if (!is_readable($file)) {
            return;
        }

        self::include_isolated($file, $props);
    }

    /**
     * Trả về chuỗi HTML của một template part.
     *
     * @param array<string, mixed> $props
     */
    public static function get_part_html(string $slug, array $props = []): string {
        ob_start();
        self::render_part($slug, $props);
        return (string) ob_get_clean();
    }

    /**
     * Render một UI primitive chuẩn hóa tại `template-parts/ui/{name}.php` (Tầng 2, ADR D-17).
     *
     * @param array<string, mixed> $props
     */
    public static function render_ui(string $name, array $props = []): void {
        $safe_name = (string) preg_replace('/[^a-z0-9-]/', '', strtolower($name));
        if ($safe_name === '') {
            return;
        }

        self::render_part('template-parts/ui/' . $safe_name, $props);
    }

    /**
     * Trả về chuỗi HTML của một UI primitive (dùng cho REST /catalog, shortcodes, PHPUnit).
     *
     * @param array<string, mixed> $props
     */
    public static function get_ui_html(string $name, array $props = []): string {
        ob_start();
        self::render_ui($name, $props);
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function include_isolated(string $____file, array $args): void {
        include $____file;
    }
}
