<?php
declare(strict_types=1);

namespace Dravit\Admin;

use Dravit\Catalog\CollectionSync;
use Dravit\Catalog\MasterListingImporter;
use Dravit\Catalog\ProductSeeder;
use Dravit\Catalog\Seeder;
use Dravit\Core\Config;
use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Trang quản trị `Công cụ -> Dravit Catalog`: Khởi tạo 14 thuộc tính + 28 Smart Collections,
 * nhập trực tiếp file Master Listing 48 cột (`.xlsx` / `.csv`), đồng bộ toàn kho & Báo cáo sức khỏe Catalog.
 */
final class ToolsResync implements ServiceProviderInterface {
    private const PAGE_SLUG  = 'drv-catalog-tools';
    private const ASYNC_HOOK = 'drv_resync_catalog_batch';

    public function register(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_drv_catalog_action', [$this, 'handle_post_action']);
        add_action(self::ASYNC_HOOK, [$this, 'process_async_batch'], 10, 1);
    }

    public function register_menu(): void {
        $cap = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
        add_management_page(
            'Dravit Catalog & Smart Collections',
            'Dravit Catalog',
            $cap,
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function handle_post_action(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die('Bạn không có quyền thực hiện thao tác này.');
        }

        check_admin_referer('drv_catalog_tools_nonce');

        $task       = isset($_POST['drv_task']) ? sanitize_key(wp_unslash((string) $_POST['drv_task'])) : '';
        $msg        = 'seed_done';
        $query_args = ['page' => self::PAGE_SLUG];

        if ($task === 'seed_catalog') {
            (new Seeder())->run();
            $msg = 'seed_catalog_done';
        } elseif ($task === 'seed_products') {
            (new Seeder())->run();
            (new ProductSeeder())->run();
            $msg = 'seed_products_done';
        } elseif ($task === 'resync_all') {
            $count = $this->count_all_products();
            if ($count > 50 && function_exists('as_enqueue_async_action')) {
                as_enqueue_async_action(self::ASYNC_HOOK, ['page' => 1], 'dravit-catalog');
                $msg = 'resync_queued';
            } else {
                $this->run_sync_batch(1, 50, true);
                $msg = 'resync_done';
            }
        } elseif ($task === 'import_master') {
            $tmp_name  = isset($_FILES['drv_master_file']['tmp_name']) ? (string) $_FILES['drv_master_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via is_uploaded_file below
            $orig_name = isset($_FILES['drv_master_file']['name']) ? sanitize_file_name(wp_unslash((string) $_FILES['drv_master_file']['name'])) : '';
            $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

            if ($tmp_name === '' || !is_uploaded_file($tmp_name) || !in_array($ext, ['xlsx', 'csv'], true)) {
                $msg = 'import_invalid_file';
            } else {
                try {
                    $report                    = (new MasterListingImporter())->importFile($tmp_name, '', false, $orig_name);
                    $msg                       = 'import_master_done';
                    $query_args['drv_created'] = $report['created'];
                    $query_args['drv_updated'] = $report['updated'];
                } catch (\Throwable $e) {
                    set_transient('drv_master_import_err', $e->getMessage(), 60);
                    $msg = 'import_master_err';
                }
            }
        }

        $query_args['drv_notice'] = $msg;
        wp_safe_redirect(add_query_arg($query_args, admin_url('tools.php')));
        exit;
    }

    public function process_async_batch(int $page = 1): void {
        $processed = $this->run_sync_batch(max(1, $page), 50, false);
        if ($processed === 50 && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::ASYNC_HOOK, ['page' => $page + 1], 'dravit-catalog');
        }
    }

    public function render_page(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only status query args after redirect
        $notice  = isset($_GET['drv_notice']) ? sanitize_key(wp_unslash((string) $_GET['drv_notice'])) : '';
        $created = isset($_GET['drv_created']) ? absint(wp_unslash((string) $_GET['drv_created'])) : 0;
        $updated = isset($_GET['drv_updated']) ? absint(wp_unslash((string) $_GET['drv_updated'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $health  = $this->collect_health_stats();
        $err_msg = get_transient('drv_master_import_err');
        if (is_string($err_msg) && $err_msg !== '') {
            delete_transient('drv_master_import_err');
        }
        ?>
        <div class="wrap">
            <h1>Quản trị Danh mục &amp; Kho gạch Dravit</h1>

            <?php if ($notice !== '') : ?>
                <?php $is_err = in_array($notice, ['import_invalid_file', 'import_master_err'], true); ?>
                <div class="notice <?php echo esc_attr($is_err ? 'notice-error' : 'notice-success'); ?> is-dismissible">
                    <p>
                        <?php
                        $messages = [
                            'seed_catalog_done'   => 'Đã khởi tạo đồng bộ 14 thuộc tính pa_*, bộ giá trị mở rộng, 2 taxonomy ẩn và 28 Smart Collections.',
                            'seed_products_done'  => 'Đã khởi tạo 10 SKU gạch mẫu (8 SKU niêm yết + 2 SKU Liên hệ báo giá) và gán danh mục chuẩn xác.',
                            'resync_queued'       => 'Đã đưa tác vụ đồng bộ lại toàn kho vào hàng đợi Action Scheduler (50 sản phẩm/lô).',
                            'resync_done'         => 'Đã tính lại giá viên, đơn vị mua và gán lại 28 Smart Collections cho toàn bộ sản phẩm.',
                            'import_master_done'  => sprintf('Nhập Master Listing thành công: tạo mới %d SKU, cập nhật %d SKU, tự động đồng bộ 28 Smart Collections & Hồ sơ AI.', $created, $updated),
                            'import_invalid_file' => 'Vui lòng chọn đúng file định dạng .xlsx hoặc .csv hợp lệ.',
                            'import_master_err'   => 'Lỗi khi đọc file Master Listing: ' . (is_string($err_msg) ? $err_msg : 'Không xác định'),
                        ];
                        echo esc_html($messages[$notice] ?? 'Thao tác hoàn tất.');
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2>1. Tác vụ Đồng bộ Hệ thống</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="drv_catalog_action">
                <?php wp_nonce_field('drv_catalog_tools_nonce'); ?>
                <p>
                    <button type="submit" name="drv_task" value="seed_catalog" class="button button-primary">
                        Khởi tạo / Cập nhật 28 Smart Collections &amp; 14 Thuộc tính
                    </button>
                    <button type="submit" name="drv_task" value="resync_all" class="button">
                        Đồng bộ lại Toàn bộ Sản phẩm (Giá &amp; Danh mục)
                    </button>
                    <button type="submit" name="drv_task" value="seed_products" class="button">
                        Nạp 10 SKU Gạch Mẫu Chuẩn Nghiệm Thu
                    </button>
                </p>
            </form>

            <hr>

            <h2>2. Nhập File Master Product Listing 48 Cột (.xlsx / .csv)</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="drv_catalog_action">
                <?php wp_nonce_field('drv_catalog_tools_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="drv_master_file">Chọn file bảng tính</label></th>
                            <td>
                                <input type="file" id="drv_master_file" name="drv_master_file" accept=".xlsx,.csv" required>
                                <p class="description">
                                    Hỗ trợ tải trực tiếp file gốc <code>Master_Product_Listing_GachDravit.xlsx</code> (không cần xóa các dòng tiêu đề nhóm) hoặc file <code>.csv</code>.
                                    Hệ thống tự tách các cột gộp kỹ thuật, chuẩn hóa từ đồng nghĩa 14 bộ lọc, tính giá viên và lưu 12 cột dữ liệu AI.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="submit" name="drv_task" value="import_master" class="button button-primary">
                        Nhập &amp; Đồng bộ Master Listing
                    </button>
                </p>
            </form>

            <hr>

            <h2>3. Báo cáo Sức khỏe Catalog (Health Check)</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>Smart Collections đã khởi tạo</th>
                        <td><strong><?php echo esc_html((string) $health['seeded_collections']); ?> / 28</strong> danh mục chuẩn</td>
                    </tr>
                    <tr>
                        <th>Tổng sản phẩm đang hiển thị</th>
                        <td><strong><?php echo esc_html((string) $health['total_products']); ?></strong> SKU</td>
                    </tr>
                    <tr>
                        <th>Sản phẩm đang ở mục "Chưa phân loại"</th>
                        <td><strong><?php echo esc_html((string) $health['uncategorized_count']); ?></strong> SKU</td>
                    </tr>
                    <tr>
                        <th>Danh mục mỏng (&lt; 6 sản phẩm, cần bổ sung mẫu)</th>
                        <td>
                            <?php
                            echo $health['thin_collections'] !== []
                                ? esc_html(implode(', ', $health['thin_collections']))
                                : 'Đạt chuẩn (Tất cả danh mục đều ≥ 6 SKU)';
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function run_sync_batch(int $page, int $limit, bool $loop_all): int {
        if (!function_exists('wc_get_products')) {
            return 0;
        }

        $sync      = new CollectionSync();
        $processed = 0;
        $cur_page  = $page;

        do {
            /** @var array<int, \WC_Product> $batch */
            $batch = wc_get_products([
                'limit'  => $limit,
                'page'   => $cur_page,
                'status' => ['publish', 'draft', 'private'],
                'return' => 'objects',
            ]);

            foreach ($batch as $product) {
                $sync->before_product_save($product);
                $product->save();
                $processed++;
            }
            $cur_page++;
        } while ($loop_all && $batch !== []);

        return $processed;
    }

    private function count_all_products(): int {
        $counts = wp_count_posts('product');
        return (int) ($counts->publish ?? 0) + (int) ($counts->draft ?? 0) + (int) ($counts->private ?? 0);
    }

    /**
     * @return array{seeded_collections: int, total_products: int, uncategorized_count: int, thin_collections: array<int, string>}
     */
    private function collect_health_stats(): array {
        $collections_cfg    = Config::get('collections');
        $seeded_collections = 0;
        $thin_collections   = [];

        if (taxonomy_exists('product_cat')) {
            foreach ($collections_cfg as $slug => $cfg) {
                $t = get_term_by('slug', (string) $slug, 'product_cat');
                if ($t instanceof \WP_Term) {
                    $seeded_collections++;
                    if ((int) $t->count < 6) {
                        $thin_collections[] = sprintf('%s (%d)', (string) ($cfg['name'] ?? $slug), (int) $t->count);
                    }
                }
            }
        }

        $uncat_count = 0;
        $default_id  = (int) get_option('default_product_cat', 0);
        if ($default_id > 0 && taxonomy_exists('product_cat')) {
            $def_term = get_term($default_id, 'product_cat');
            if ($def_term instanceof \WP_Term) {
                $uncat_count = (int) $def_term->count;
            }
        }

        $post_counts = wp_count_posts('product');

        return [
            'seeded_collections'  => $seeded_collections,
            'total_products'      => (int) ($post_counts->publish ?? 0),
            'uncategorized_count' => $uncat_count,
            'thin_collections'    => $thin_collections,
        ];
    }
}
