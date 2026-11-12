<?php
declare(strict_types=1);

namespace Dravit\Units;

use Dravit\Units\PricingModel\AreaPack;

defined('ABSPATH') || exit;

/**
 * Registry quản lý các mô hình giá `area_pack | piece | set | pack` (docs/04 §1.2).
 */
final class UnitRegistry {
    /** @var array<string, PricingModelInterface> */
    private static array $instances = [];

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array {
        if (self::$config === null) {
            $raw          = require DRV_DIR . '/config/units.php';
            $models       = is_array($raw['models'] ?? null) ? $raw['models'] : [];
            $models       = function_exists('apply_filters')
                ? (array) apply_filters('drv/units/models', $models)
                : $models;
            $raw['models'] = $models;
            self::$config  = $raw;
        }
        return self::$config;
    }

    public static function modelForProduct(\WC_Product $product): PricingModelInterface {
        $key = (string) ($product->get_meta('drv_pricing_model', true) ?: 'area_pack');
        return self::model($key);
    }

    public static function model(string $key): PricingModelInterface {
        $cfg    = self::config();
        $models = $cfg['models'] ?? [];
        if (!isset($models[$key]) || !is_array($models[$key])) {
            $key = 'area_pack';
        }

        if (!isset(self::$instances[$key])) {
            $class = (string) ($models[$key]['class'] ?? AreaPack::class);
            if (class_exists($class)) {
                $obj = new $class();
                if ($obj instanceof PricingModelInterface) {
                    self::$instances[$key] = $obj;
                    return $obj;
                }
            }
            self::$instances[$key] = new AreaPack();
        }

        return self::$instances[$key];
    }

    public static function unitSchemaCode(string $unit_key): string {
        $cfg = self::config();
        return (string) ($cfg['units'][$unit_key]['schema'] ?? 'C62');
    }
}

