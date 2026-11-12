<?php
declare(strict_types=1);

use Dravit\Core\Menu\DropdownWalker;
use Dravit\Core\Template;

defined('ABSPATH') || exit;
?>
<div class="drv-header__nav-bar">
    <nav class="l-container drv-nav" aria-label="Điều hướng danh mục gạch chính">
        <ul class="drv-nav__list">
            <li class="drv-nav__item">
                <button type="button"
                        class="drv-nav__link"
                        data-drv-action="mega-toggle"
                        aria-expanded="false"
                        aria-controls="drv-mega-menu">
                    <i class="ph ph-squares-four drv-icon drv-icon--sm" aria-hidden="true"></i>
                    <span>Danh mục Gạch</span>
                    <i class="ph ph-caret-down drv-icon drv-icon--xs" aria-hidden="true"></i>
                </button>
            </li>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Đã escape toàn bộ bên trong DropdownWalker.
            echo DropdownWalker::render_silo_items();
            ?>
        </ul>

        <ul class="drv-nav__list">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Đã escape toàn bộ bên trong DropdownWalker.
            echo DropdownWalker::render_utility_items();
            ?>
        </ul>
    </nav>

    <?php Template::render_part('template-parts/header/mega-menu'); ?>
</div>

