<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 5) . '/');
}

if (!defined('DRV_VERSION')) {
    define('DRV_VERSION', '1.0.0-test');
}

if (!defined('DRV_DIR')) {
    define('DRV_DIR', dirname(__DIR__, 2));
}

if (!defined('DRV_URI')) {
    define('DRV_URI', 'https://gachdravit.vn/wp-content/themes/dravit');
}

if (!defined('DRV_ENV')) {
    define('DRV_ENV', 'local');
}

require_once DRV_DIR . '/vendor/autoload.php';

// Đăng ký thêm đúng PSR-4 autoloader runtime của theme để kiểm chứng độc lập Composer autoload.
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


// Stub tối thiểu `WP_Error` cho unit test thuần (không nạp WordPress core).
if (!class_exists('WP_Error')) {
    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class WP_Error {
        /** @param mixed $data */
        public function __construct(private string $code = '', private string $message = '', private mixed $data = '') {}

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data(): mixed {
            return $this->data;
        }
    }
}
