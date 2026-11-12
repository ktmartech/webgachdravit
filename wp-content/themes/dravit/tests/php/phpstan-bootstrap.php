<?php
declare(strict_types=1);

define('DRV_VERSION', '1.0.0');
define('DRV_DIR', dirname(__DIR__, 2));
define('DRV_URI', 'https://gachdravit.vn/wp-content/themes/dravit');
define('DRV_ENV', 'local');

if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static function add_command(string $name, callable|string|array $callable, array $args = []): bool {
            return true;
        }

        public static function success(string $message): void {
        }

        public static function error(string $message, bool|int $exit = true): void {
        }

        public static function log(string $message): void {
        }
    }
}

