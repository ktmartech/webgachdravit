<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Đã escape toàn bộ trường bên trong MegaMenuRenderer.
echo MegaMenuRenderer::get_fragments()['desktop'];

