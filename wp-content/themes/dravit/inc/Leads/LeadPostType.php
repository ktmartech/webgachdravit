<?php
declare(strict_types=1);

namespace Dravit\Leads;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * CPT private `drv_lead` (docs/14 §6.1): menu "Báo giá & Lead" dưới WooCommerce, quyền `manage_woocommerce`,
 * bubble đếm lead mới và cron dọn dữ liệu quá hạn theo NĐ 13/2023 (docs/17 §5.1).
 */
final class LeadPostType implements ServiceProviderInterface {
    public const CRON_HOOK = 'drv_daily_lead_cleanup';

    public function register(): void {
        add_action('init', [$this, 'register_post_type'], 20);
        add_action('init', [$this, 'ensure_cron'], 40);
        add_action(self::CRON_HOOK, [$this, 'run_cleanup']);
        add_action('admin_menu', [$this, 'menu_bubble'], 99);
    }

    public function register_post_type(): void {
        $cap = 'manage_woocommerce';
        register_post_type(LeadRepository::POST_TYPE, [
            'labels'              => [
                'name'               => 'Báo giá & Lead',
                'singular_name'      => 'Phiếu báo giá / Lead',
                'menu_name'          => 'Báo giá & Lead',
                'all_items'          => 'Báo giá & Lead',
                'edit_item'          => 'Xử lý phiếu báo giá',
                'search_items'       => 'Tìm mã phiếu, tên, SĐT, SKU',
                'not_found'          => 'Chưa có yêu cầu báo giá nào.',
                'not_found_in_trash' => 'Thùng rác trống.',
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => drv_is_woo_active() ? 'woocommerce' : true,
            'show_in_rest'        => false,
            'rewrite'             => false,
            'query_var'           => false,
            'has_archive'         => false,
            'supports'            => ['title'],
            'map_meta_cap'        => false,
            'capabilities'        => [
                'edit_post'              => $cap,
                'read_post'              => $cap,
                'delete_post'            => $cap,
                'edit_posts'             => $cap,
                'edit_others_posts'      => $cap,
                'publish_posts'          => $cap,
                'read_private_posts'     => $cap,
                'delete_posts'           => $cap,
                'delete_private_posts'   => $cap,
                'delete_others_posts'    => $cap,
                'edit_private_posts'     => $cap,
                'create_posts'           => 'do_not_allow',
            ],
        ]);
    }

    public function menu_bubble(): void {
        global $submenu;
        if (!isset($submenu['woocommerce']) || !is_array($submenu['woocommerce'])) {
            return;
        }
        $count = LeadRepository::countNew();
        if ($count <= 0) {
            return;
        }
        foreach ($submenu['woocommerce'] as $idx => $item) {
            if (isset($item[2]) && $item[2] === 'edit.php?post_type=' . LeadRepository::POST_TYPE) {
                // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Thêm bubble đếm vào label submenu.
                $submenu['woocommerce'][$idx][0] .= sprintf(' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $count);
                break;
            }
        }
    }

    public function ensure_cron(): void {
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Xóa vĩnh viễn lead quá `quote.retention_days` chưa chuyển thành đơn (giữ lại lead `won` để đối chiếu).
     */
    public function run_cleanup(): void {
        $days   = max(30, (int) drv_setting('quote.retention_days', 365));
        $before = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $ids    = get_posts([
            'post_type'      => LeadRepository::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'date_query'     => [['column' => 'post_date_gmt', 'before' => $before]],
        ]);
        foreach ($ids as $id) {
            if ((int) get_post_meta((int) $id, '_drv_converted_order_id', true) > 0) {
                continue;
            }
            wp_delete_post((int) $id, true);
        }
        LeadRepository::flushNewCount();
    }
}
