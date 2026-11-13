<?php
declare(strict_types=1);

namespace Dravit;

use Dravit\Admin\AttachmentFields;
use Dravit\Admin\CsvProductMapping;
use Dravit\Admin\ProductFields;
use Dravit\Admin\SettingsPage;
use Dravit\Admin\TermFields;
use Dravit\Admin\ToolsResync;
use Dravit\Catalog\CliCommand;
use Dravit\Catalog\CollectionSync;
use Dravit\Catalog\SaleSync;
use Dravit\Catalog\SearchController;
use Dravit\Catalog\SlugGuard;
use Dravit\Catalog\Taxonomies;
use Dravit\Core\Assets;
use Dravit\Core\Cleanup;
use Dravit\Core\Integrations\FluentFormsBridge;
use Dravit\Core\Integrations\LiteSpeedCompat;
use Dravit\Core\Integrations\RankMathCompat;
use Dravit\Core\Integrations\WooCompat;
use Dravit\Core\Menu\MegaMenuRenderer;
use Dravit\Core\Menu\MenuSetup;
use Dravit\Core\RestSecurity;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Core\Settings;
use Dravit\Core\Setup;
use Dravit\Seo\Breadcrumbs;
use Dravit\Seo\Canonical;
use Dravit\Seo\LlmsTxt;
use Dravit\Seo\MetaTags;
use Dravit\Seo\RobotsMatrix;
use Dravit\Seo\SchemaGraph;
use Dravit\Seo\TrailingSlashRedirect;
use Dravit\Units\WooProductHooks;

defined('ABSPATH') || exit;

final class Theme {
    private static bool $booted = false;

    /** @var array<int, ServiceProviderInterface> */
    private static array $registered = [];

    /**
     * Khởi động tuần tự các module theo kiến trúc Dravit (Core -> Seo -> Integrations -> Domain -> Woo -> Admin).
     * Kiểm tra an toàn sự tồn tại của từng plugin bên thứ 3 để không bao giờ gây Fatal Error.
     */
    public static function boot(): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        foreach (self::provider_classes() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $instance = new $class();
            if ($instance instanceof ServiceProviderInterface) {
                $instance->register();
                self::$registered[] = $instance;
            }
        }
    }

    /**
     * Danh sách provider được phép nạp theo trạng thái môi trường & plugin hiện hành.
     *
     * @return array<int, class-string<ServiceProviderInterface>>
     */
    public static function provider_classes(): array {
        $providers = [
            Settings::class,
            Setup::class,
            RestSecurity::class,
            SlugGuard::class,
            TrailingSlashRedirect::class,
            Canonical::class,
            RobotsMatrix::class,
            Breadcrumbs::class,
            MetaTags::class,
            SchemaGraph::class,
            LlmsTxt::class,
            SearchController::class,
            MenuSetup::class,
            MegaMenuRenderer::class,
            Cleanup::class,
            Assets::class,
        ];

        $is_admin = function_exists('is_admin') && is_admin();

        if ($is_admin) {
            $providers[] = SettingsPage::class;
            $providers[] = AttachmentFields::class;
        }

        if (drv_is_woo_active()) {
            $providers[] = WooCompat::class;
            $providers[] = Taxonomies::class;
            $providers[] = WooProductHooks::class;
            $providers[] = CollectionSync::class;
            $providers[] = SaleSync::class;

            if ($is_admin) {
                $providers[] = ProductFields::class;
                $providers[] = TermFields::class;
                $providers[] = CsvProductMapping::class;
                $providers[] = ToolsResync::class;
            }

            if (defined('WP_CLI') && WP_CLI) {
                $providers[] = CliCommand::class;
            }
        }

        if (drv_is_rank_math_active()) {
            $providers[] = RankMathCompat::class;
        }

        if (drv_is_fluent_forms_active()) {
            $providers[] = FluentFormsBridge::class;
        }

        if (drv_is_litespeed_active()) {
            $providers[] = LiteSpeedCompat::class;
        }

        return $providers;
    }

    /**
     * @return array<int, ServiceProviderInterface>
     */
    public static function registered_providers(): array {
        return self::$registered;
    }

    /**
     * Chỉ phục vụ môi trường kiểm thử PHPUnit.
     */
    public static function reset_for_tests(): void {
        self::$booted     = false;
        self::$registered = [];
    }
}
