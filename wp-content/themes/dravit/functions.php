<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

define('DRV_VERSION', '1.0.0');
define('DRV_DIR', get_template_directory());
define('DRV_URI', get_template_directory_uri());
define('DRV_ENV', function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Dravit\\')) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, 7));
    $file     = DRV_DIR . '/inc/' . $relative . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

require_once DRV_DIR . '/inc/Core/Helpers.php';

\Dravit\Theme::boot();

