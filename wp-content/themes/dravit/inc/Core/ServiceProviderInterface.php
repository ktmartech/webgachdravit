<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

interface ServiceProviderInterface {
    /**
     * Đăng ký toàn bộ WordPress actions/filters của module.
     */
    public function register(): void;
}

