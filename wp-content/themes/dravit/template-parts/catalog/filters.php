<?php
declare(strict_types=1);

use Dravit\Core\Template;
use Dravit\Filters\UrlState;

defined('ABSPATH') || exit;

/**
 * Custom Element `<drv-filter-form>` chứa DUY NHẤT 1 thẻ `<form id="drv-catalog-filter-form">` trên toàn trang
 * (Test F-13, STRIP-25: tự động reparent vào `<dialog id="drv-filter-sheet">` trên màn hình `< 1024px`).
 *
 * @var array<string, mixed> $args
 */
$state        = ($args['state'] ?? null) instanceof UrlState ? $args['state'] : UrlState::fromCurrentRequest();
$archive_name = trim((string) ($args['archive_name'] ?? 'Gạch ốp lát'));
$showroom     = isset($args['showroom']) && is_array($args['showroom']) ? $args['showroom'] : null;
$ui_groups    = isset($args['ui_groups']) && is_array($args['ui_groups']) ? $args['ui_groups'] : [];
$total_found  = max(0, (int) ($args['total_found'] ?? 0));
?>
<drv-filter-form class="drv-filter-host"
                 data-base="<?php echo esc_attr($state->base()); ?>"
                 data-base-url="<?php echo esc_attr($state->baseUrl()); ?>"
                 data-archive-name="<?php echo esc_attr($archive_name); ?>"
                 data-per-page="<?php echo esc_attr((string) $state->perPage()); ?>"
                 data-total="<?php echo esc_attr((string) $total_found); ?>">
    <form id="drv-catalog-filter-form"
          class="drv-filter-form"
          method="get"
          action="<?php echo esc_url($state->baseUrl()); ?>"
          data-filter-form-el>
        <?php
        Template::render_part('template-parts/catalog/filters-inner', [
            'state'       => $state,
            'showroom'    => $showroom,
            'ui_groups'   => $ui_groups,
            'total_found' => $total_found,
        ]);
        ?>
    </form>
</drv-filter-form>
