<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Từ điển đơn vị đo lường (UN/CEFACT) & Mô hình định giá mở rộng (docs/04_Pricing_Units_Commerce.md §1.2).
 *
 * @return array{units: array<string, array{label: string, plural?: string, schema: string}>, models: array<string, array<string, mixed>>}
 */
return [
    'units' => [
        'm2'    => ['label' => 'm²',    'schema' => 'MTK'],
        'vien'  => ['label' => 'viên',  'schema' => 'C62'],
        'tam'   => ['label' => 'tấm',   'schema' => 'C62'],
        'thung' => ['label' => 'thùng', 'schema' => 'BX'],
        'chiec' => ['label' => 'chiếc', 'schema' => 'C62'],
        'bo'    => ['label' => 'bộ',    'schema' => 'SET'],
        'tuyp'  => ['label' => 'tuýp',  'schema' => 'C62'],
        'bao'   => ['label' => 'bao',   'schema' => 'C62'],
        'kg'    => ['label' => 'kg',    'schema' => 'KGM'],
    ],
    'models' => [
        'area_pack' => [
            'class'      => \Dravit\Units\PricingModel\AreaPack::class,
            'label'      => 'Gạch ốp lát - Tính giá theo m², giao dịch theo viên/thùng (area_pack)',
            'price_unit' => 'm2',
            'sale_unit'  => 'vien',
            'pack_unit'  => 'thung',
        ],
        'piece' => [
            'class'      => \Dravit\Units\PricingModel\Piece::class,
            'label'      => 'Thiết bị / Phụ kiện - Tính theo chiếc (piece)',
            'price_unit' => 'chiec',
            'sale_unit'  => 'chiec',
        ],
        'set' => [
            'class'      => \Dravit\Units\PricingModel\Set::class,
            'label'      => 'Combo / Bộ sản phẩm - Tính theo bộ (set)',
            'price_unit' => 'bo',
            'sale_unit'  => 'bo',
        ],
        'pack' => [
            'class'          => \Dravit\Units\PricingModel\Pack::class,
            'label'          => 'Keo chà ron / Vữa - Tính theo tuýp/bao (pack)',
            'price_unit'     => 'tuyp',
            'sale_unit'      => 'tuyp',
            'secondary_unit' => 'kg',
        ],
    ],
];

