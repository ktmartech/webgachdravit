<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

defined('ABSPATH') || exit;

/**
 * Facade đồng bộ hợp đồng tên gọi giữa docs/19 (`MegaMenuRenderer`) và docs/21 (`CollectionMenuBuilder`).
 */
final class CollectionMenuBuilder {
    /**
     * @return array{desktop: string, mobile: string}
     */
    public static function build(bool $bypass_cache = false): array {
        return MegaMenuRenderer::get_fragments($bypass_cache);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array {
        return MegaMenuRenderer::resolve_columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns_from_config(): array {
        return MegaMenuRenderer::build_columns_from_config();
    }

    public static function purge_cache(): void {
        MegaMenuRenderer::purge_cache();
    }
}

