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

// Stub wp_unslash cho unit test (WordPress function not available in test env).
if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed {
        return is_string($value) ? stripslashes($value) : $value;
    }
}


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

// Stub tối thiểu WP REST classes cho unit test LiteSpeedCompat & các REST controller.
if (!class_exists('WP_REST_Request')) {
    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class WP_REST_Request {
        private string $route = '';
        public function get_route(): string { return $this->route; }
        public function set_route(string $route): void { $this->route = $route; }
    }
}
if (!class_exists('WP_HTTP_Response')) {
    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class WP_HTTP_Response {
        /** @var array<string, string> */
        protected array $headers = [];
    }
}
if (!class_exists('WP_REST_Response')) {
    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class WP_REST_Response extends WP_HTTP_Response {
        public function header(string $key, string $value, bool $replace = true): void {
            $this->headers[$key] = $value;
        }
        /** @return array<string, string> */
        public function get_headers(): array { return $this->headers; }
    }
}
if (!class_exists('WP_REST_Server')) {
    // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
    class WP_REST_Server {}
}
