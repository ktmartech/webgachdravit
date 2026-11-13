<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\Settings;
use Dravit\Seo\Canonical;
use Dravit\Seo\RobotsMatrix;
use PHPUnit\Framework\TestCase;

final class RobotsMatrixTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();

        Functions\stubs([
            'wp_parse_url' => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'get_option'   => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_clean_static_collections_and_paginated_pages_are_index_follow(): void {
        // M-1: Danh mục tĩnh sạch trang 1 có sản phẩm -> index, follow
        $m1 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 10,
            'paged'        => 1,
            'max_pages'    => 2,
            'query_params' => [],
        ]);
        self::assertSame(['index' => 'index', 'follow' => 'follow'], $m1);
        self::assertSame(
            'https://gachdravit.vn/gach-phong-khach',
            Canonical::resolveCanonical('https://gachdravit.vn/gach-phong-khach/', 1, [])
        );

        // M-2: Phân trang sạch /gach-80x80/page/2 -> index, follow + self-canonical /page/2
        $m2 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 36,
            'paged'        => 2,
            'max_pages'    => 3,
            'query_params' => [],
        ]);
        self::assertSame(['index' => 'index', 'follow' => 'follow'], $m2);
        self::assertSame(
            'https://gachdravit.vn/gach-80x80/page/2',
            Canonical::resolveCanonical('https://gachdravit.vn/gach-80x80/page/2/', 2, [])
        );

        // M-8: Trang thuộc tính public (/bo-suu-tap/*, /xuat-xu/*) có sản phẩm -> index, follow
        $m8 = RobotsMatrix::resolveRobots([
            'view'         => 'attribute_public',
            'post_count'   => 5,
            'paged'        => 1,
            'query_params' => [],
        ]);
        self::assertSame(['index' => 'index', 'follow' => 'follow'], $m8);
    }

    public function test_seo_filter_params_are_noindex_follow_and_canonical_points_to_clean_root(): void {
        // M-3 & M-4: Lọc nhóm `seo` (1 hoặc nhiều param) -> noindex, follow
        $m3 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 8,
            'paged'        => 1,
            'query_params' => ['kich-thuoc' => '80x80'],
        ]);
        self::assertSame(['index' => 'noindex', 'follow' => 'follow'], $m3);

        $m4 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 4,
            'paged'        => 1,
            'query_params' => [
                'kich-thuoc' => '80x80',
                'mau-sac'    => 'trang',
                'be-mat'     => 'mo',
            ],
        ]);
        self::assertSame(['index' => 'noindex', 'follow' => 'follow'], $m4);

        // M-7: Lọc + phân trang (/gach-80x80/page/2?be-mat=mo) -> noindex, follow & canonical về trang 1 sạch
        $m7 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 12,
            'paged'        => 2,
            'max_pages'    => 2,
            'query_params' => ['be-mat' => 'mo'],
        ]);
        self::assertSame(['index' => 'noindex', 'follow' => 'follow'], $m7);
        self::assertSame(
            'https://gachdravit.vn/gach-80x80',
            Canonical::resolveCanonical('https://gachdravit.vn/gach-80x80/page/2', 2, ['be-mat' => 'mo'])
        );
    }

    public function test_tech_filter_sort_and_utility_params_are_noindex_nofollow(): void {
        // M-5: Có >= 1 param nhóm `tech` (gia, dcof, do-hut-nuoc, do-day, showroom) -> noindex, nofollow
        $m5 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 6,
            'query_params' => [
                'kich-thuoc' => '80x80',
                'gia'        => '200k-350k',
            ],
        ]);
        self::assertSame(['index' => 'noindex', 'follow' => 'nofollow'], $m5);

        // M-6: Tham số sắp xếp ?sort=gia-tang -> noindex, nofollow
        $m6 = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 10,
            'query_params' => ['sort' => 'gia-tang'],
        ]);
        self::assertSame(['index' => 'noindex', 'follow' => 'nofollow'], $m6);

        // Utility params (?quote=..., ?ai_query=...) -> noindex, nofollow
        $util = RobotsMatrix::resolveRobots([
            'view'         => 'page',
            'page_slug'    => 'tro-ly-ai-tim-gach',
            'ai_enabled'   => true,
            'query_params' => ['ai_query' => 'phong khach 30m2'],
        ]);
        self::assertSame(['index' => 'noindex', 'follow' => 'nofollow'], $util);
    }

    public function test_empty_collections_system_pages_and_tracking_params_follow_matrix(): void {
        // M-9: Thuộc tính pa_* không public -> noindex, nofollow
        self::assertSame(
            ['index' => 'noindex', 'follow' => 'nofollow'],
            RobotsMatrix::resolveRobots(['view' => 'attribute_private'])
        );

        // M-10: Danh mục trống (0 SP) -> noindex, follow
        self::assertSame(
            ['index' => 'noindex', 'follow' => 'follow'],
            RobotsMatrix::resolveRobots(['view' => 'collection', 'post_count' => 0])
        );

        // Trang hệ thống BOQ, Search, Tag, AI (khi tắt flag) -> noindex, follow
        self::assertSame(
            ['index' => 'noindex', 'follow' => 'follow'],
            RobotsMatrix::resolveRobots(['view' => 'page', 'page_slug' => 'danh-sach-bao-gia'])
        );
        self::assertSame(
            ['index' => 'noindex', 'follow' => 'follow'],
            RobotsMatrix::resolveRobots(['view' => 'page', 'page_slug' => 'tro-ly-ai-tim-gach', 'ai_enabled' => false])
        );
        self::assertSame(
            ['index' => 'noindex', 'follow' => 'follow'],
            RobotsMatrix::resolveRobots(['view' => 'search'])
        );
        self::assertSame(
            ['index' => 'noindex', 'follow' => 'follow'],
            RobotsMatrix::resolveRobots(['view' => 'tag'])
        );

        // Giỏ hàng, Thanh toán, Tài khoản, UI Kit -> noindex, nofollow
        foreach (['gio-hang', 'thanh-toan', 'dat-hang-thanh-cong', 'tai-khoan', 'ui-kit'] as $slug) {
            self::assertSame(
                ['index' => 'noindex', 'follow' => 'nofollow'],
                RobotsMatrix::resolveRobots(['view' => 'page', 'page_slug' => $slug]),
                "Sai robots cho trang hệ thống {$slug}"
            );
        }

        // R-7: Tracking params (utm_*, fbclid, gclid, zarsrc) không biến trang tĩnh thành noindex nhưng bị lọc khỏi canonical
        $utm_state = RobotsMatrix::resolveRobots([
            'view'         => 'collection',
            'post_count'   => 10,
            'query_params' => ['utm_source' => 'zalo', 'fbclid' => 'xyz123'],
        ]);
        self::assertSame(['index' => 'index', 'follow' => 'follow'], $utm_state);
        self::assertSame(
            'https://gachdravit.vn/gach-80x80',
            Canonical::resolveCanonical('https://gachdravit.vn/gach-80x80', 1, ['utm_source' => 'zalo', 'fbclid' => 'xyz123'])
        );
    }
}

