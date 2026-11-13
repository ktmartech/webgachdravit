<?php
declare(strict_types=1);

namespace Dravit\Core;

use Dravit\Catalog\SearchController as CatalogSearchController;

defined('ABSPATH') || exit;

/**
 * Alias tương thích hợp đồng `Dravit\Core\SearchController` (docs/15 §2).
 */
final class SearchController extends CatalogSearchController {
}

