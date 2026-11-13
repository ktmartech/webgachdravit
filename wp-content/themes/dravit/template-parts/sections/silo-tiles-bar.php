<?php
declare(strict_types=1);

use Dravit\Seo\InternalLinks;

defined('ABSPATH') || exit;

/**
 * Thanh điều hướng Sub-Silo Tiles tĩnh (`drv_section('silo-tiles-bar')`, IL-01 & IL-02, ADR D-29):
 * 100% URL tĩnh không dấu `/` cuối, 0 link chứa `?param`.
 *
 * @var array<string, mixed> $args
 */
$current_slug   = trim((string) ($args['current_slug'] ?? ''));
$explicit_slugs = isset($args['slugs']) && is_array($args['slugs']) ? $args['slugs'] : [];
$limit          = max(1, (int) ($args['limit'] ?? 6));
$title          = trim((string) ($args['title'] ?? ''));

$items = isset($args['items']) && is_array($args['items']) && $args['items'] !== []
    ? $args['items']
    : InternalLinks::getSiloTiles($current_slug, $explicit_slugs, $limit);

if ($items === []) {
    return;
}
?>
<nav class="drv-silo-bar" aria-label="<?php echo esc_attr($title !== '' ? $title : 'Danh mục gạch liên quan'); ?>">
    <?php if ($title !== '') : ?>
        <p class="u-text-xs u-uppercase u-text-muted"><?php echo esc_html($title); ?></p>
    <?php endif; ?>
    <div class="l-grid l-grid--auto-sm">
        <?php foreach ($items as $tile) :
            if (!is_array($tile)) {
                continue;
            }
            drv_ui('sub-silo-tile', [
                'name'      => (string) ($tile['name'] ?? ''),
                'url'       => (string) ($tile['url'] ?? ''),
                'count'     => (int) ($tile['count'] ?? 0),
                'thumb_id'  => (int) ($tile['thumb_id'] ?? 0),
                'thumb_url' => (string) ($tile['thumb_url'] ?? ''),
                'active'    => ($current_slug !== '' && ($tile['slug'] ?? '') === $current_slug),
            ]);
        endforeach; ?>
    </div>
</nav>

