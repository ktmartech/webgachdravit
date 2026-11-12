<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

final class RankMathCompat implements ServiceProviderInterface {
    public function register(): void {
        // Loại bỏ trang `/san-pham` khỏi breadcrumb sản phẩm (06 §2).
        add_filter('rank_math/frontend/breadcrumb/is_using_shop_base', '__return_false');

        // Khử triệt để dấu `/` cuối trên canonical URL xuất từ Rank Math (D-21).
        add_filter('rank_math/frontend/canonical', [$this, 'strip_trailing_slash'], 20);
        add_filter('user_trailingslashit', [$this, 'untrailingslash_non_root'], 20, 2);
    }

    public function strip_trailing_slash(string $canonical): string {
        if ($canonical === '') {
            return $canonical;
        }

        $home = untrailingslashit(home_url());
        if (untrailingslashit($canonical) === $home) {
            return $home;
        }

        return untrailingslashit($canonical);
    }

    public function untrailingslash_non_root(string $url, string $type): string {
        unset($type);
        if ($url === '/') {
            return '/';
        }

        return untrailingslashit($url);
    }
}

