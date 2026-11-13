<?php
declare(strict_types=1);

namespace Dravit\Core\Integrations;

use Dravit\Core\ServiceProviderInterface;

defined('ABSPATH') || exit;

/**
 * Cầu nối tinh chỉnh các hành vi riêng của Rank Math Free (docs/06 §2–5, ADR D-21, D-31):
 * Các bộ lọc Canonical, RobotsMatrix, Breadcrumbs, MetaTags, SchemaGraph và LlmsTxt đã được đăng ký
 * độc lập trong `Dravit\Seo\*` để hoạt động xuyên suốt cả khi bật và tắt Rank Math.
 */
final class RankMathCompat implements ServiceProviderInterface {
    public function register(): void {
        // Loại bỏ quảng cáo comment HTML của Rank Math trên frontend và tắt OpenGraph price 0đ cho SP báo giá.
        add_filter('rank_math/frontend/remove_credit_notice', '__return_true');
        add_filter('rank_math/opengraph/facebook/product_price_amount', [$this, 'guard_og_product_price'], 20, 1);
    }

    public function guard_og_product_price(mixed $price): mixed {
        if (is_numeric($price) && (float) $price <= 0.0) {
            return '';
        }
        return $price;
    }
}
