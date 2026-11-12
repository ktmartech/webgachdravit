<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

final class LiteSpeedCompat implements ServiceProviderInterface {
    public function register(): void {
        add_action('wp', [$this, 'tag_or_exclude_current_request']);
    }

    public function tag_or_exclude_current_request(): void {
        if (is_admin()) {
            return;
        }

        if (drv_is_woo_active() && (is_cart() || is_checkout() || is_account_page())) {
            self::mark_no_cache('transactional-woo-page');
            return;
        }

        self::add_cache_tag('drv_global_shell');
    }

    public static function add_cache_tag(string $tag): void {
        if (has_action('litespeed_tag_add')) {
            do_action('litespeed_tag_add', $tag);
        }
    }

    public static function purge_tag(string $tag): void {
        if (has_action('litespeed_purge')) {
            do_action('litespeed_purge', $tag);
        }
    }

    public static function mark_no_cache(string $reason = 'dynamic'): void {
        if (has_action('litespeed_control_set_nocache')) {
            do_action('litespeed_control_set_nocache', $reason);
        }
    }
}

