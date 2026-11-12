<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Bộ lệnh WP-CLI quản trị Catalog Dravit:
 * - `wp dravit seed-catalog`
 * - `wp dravit seed-products`
 * - `wp dravit resync-catalog`
 * - `wp dravit import-master <file.xlsx|file.csv> [--images-dir=<dir>] [--dry-run]`
 */
final class CliCommand implements ServiceProviderInterface {
    public function register(): void {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('dravit seed-catalog', [$this, 'seed_catalog']);
        \WP_CLI::add_command('dravit seed-products', [$this, 'seed_products']);
        \WP_CLI::add_command('dravit resync-catalog', [$this, 'resync_catalog']);
        \WP_CLI::add_command('dravit import-master', [$this, 'import_master']);
    }

    public function seed_catalog(): void {
        $stats = (new Seeder())->run();
        if (class_exists('\WP_CLI')) {
            \WP_CLI::success(sprintf(
                'Đã khởi tạo %d thuộc tính pa_*, %d terms chuẩn và %d Smart Collections.',
                $stats['attributes'],
                $stats['terms'],
                $stats['collections']
            ));
        }
    }

    public function seed_products(): void {
        (new Seeder())->run();
        $seeded = (new ProductSeeder())->run();
        if (class_exists('\WP_CLI')) {
            \WP_CLI::success(sprintf('Đã khởi tạo & đồng bộ %d SKU gạch mẫu.', count($seeded)));
        }
    }

    public function resync_catalog(): void {
        if (!function_exists('wc_get_products')) {
            return;
        }

        $sync  = new CollectionSync();
        $page  = 1;
        $total = 0;

        do {
            /** @var array<int, \WC_Product> $batch */
            $batch = wc_get_products([
                'limit'  => 50,
                'page'   => $page,
                'status' => ['publish', 'draft', 'private'],
                'return' => 'objects',
            ]);

            foreach ($batch as $product) {
                $sync->before_product_save($product);
                $product->save();
                $total++;
            }
            $page++;
        } while ($batch !== []);

        if (class_exists('\WP_CLI')) {
            \WP_CLI::success(sprintf('Đã đồng bộ lại %d sản phẩm.', $total));
        }
    }

    /**
     * Nạp file Master Product Listing 48 cột (`.xlsx` hoặc `.csv`).
     *
     * ## OPTIONS
     *
     * <file>
     * : Đường dẫn tới file `.xlsx` hoặc `.csv` cần import.
     *
     * [--images-dir=<dir>]
     * : Thư mục chứa ảnh sản phẩm đặt tên theo `{SKU}-1..6.{jpg,webp,png}`.
     *
     * [--dry-run]
     * : Chỉ bóc tách và kiểm tra kết quả khớp danh mục mà không ghi vào DB.
     *
     * @param array<int, string>    $args
     * @param array<string, mixed>  $assoc_args
     */
    public function import_master(array $args, array $assoc_args): void {
        if (!class_exists('\WP_CLI')) {
            return;
        }

        $file_path  = isset($args[0]) ? trim((string) $args[0]) : '';
        $images_dir = isset($assoc_args['images-dir']) ? trim((string) $assoc_args['images-dir']) : '';
        $dry_run    = !empty($assoc_args['dry-run']);

        if ($file_path === '' || !is_readable($file_path)) {
            \WP_CLI::error('Vui lòng truyền đường dẫn file .xlsx hoặc .csv hợp lệ.');
            return;
        }

        try {
            $report = (new MasterListingImporter())->importFile($file_path, $images_dir, $dry_run);
        } catch (\Throwable $e) {
            \WP_CLI::error($e->getMessage());
            return;
        }

        foreach ($report['items'] as $item) {
            \WP_CLI::log(sprintf(
                '[%s] %s (#%d) -> Primary: %s | Collections (%d): %s',
                strtoupper($item['action']),
                $item['sku'],
                $item['product_id'],
                $item['primary_cat'],
                count($item['collections']),
                implode(', ', $item['collections'])
            ));
        }

        if ($dry_run) {
            \WP_CLI::success(sprintf(
                'DRY-RUN hoàn tất: đọc %d dòng hợp lệ (%d dòng phụ bị bỏ qua).',
                count($report['items']),
                $report['skipped']
            ));
            return;
        }

        \WP_CLI::success(sprintf(
            'Import Master Listing hoàn tất: tạo mới %d SKU, cập nhật %d SKU, bỏ qua %d dòng phụ.',
            $report['created'],
            $report['updated'],
            $report['skipped']
        ));
    }
}
