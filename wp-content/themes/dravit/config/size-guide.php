<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Cấu hình chuẩn cho công cụ So sánh tỷ lệ gạch thật & Bề mặt chống trượt R9-R13 (`<drv-size-guide>`).
 * Toàn bộ liên kết trỏ 100% về Smart Collection URL tĩnh (`docs/03`), không chứa `?param` hoặc `.html`.
 *
 * @return array<string, mixed>
 */
return [
    'default_size'  => '80x80',
    'baseline_size' => '60x60',
    'sizes'         => [
        '15x90'  => [
            'label'           => '15×90',
            'meta'            => '7,4 viên/m²',
            'w'               => 15,
            'h'               => 90,
            'rate'            => '7,41 viên/m²',
            'box'             => '8 viên · 1,08 m²',
            'grout'           => '2,0 mm (lát lệch 1/3)',
            'collection_slug' => 'gach-15x80-20x100',
        ],
        '30x60'  => [
            'label'           => '30×60',
            'meta'            => '5,6 viên/m²',
            'w'               => 30,
            'h'               => 60,
            'rate'            => '5,56 viên/m²',
            'box'             => '8 viên · 1,44 m²',
            'grout'           => '1,5 - 2,0 mm',
            'collection_slug' => 'gach-30x60',
        ],
        '60x60'  => [
            'label'           => '60×60',
            'meta'            => '2,8 viên/m²',
            'w'               => 60,
            'h'               => 60,
            'rate'            => '2,78 viên/m²',
            'box'             => '4 viên · 1,44 m²',
            'grout'           => '1,5 - 2,0 mm',
            'collection_slug' => 'gach-60x60',
        ],
        '80x80'  => [
            'label'           => '80×80',
            'meta'            => '1,6 viên/m²',
            'w'               => 80,
            'h'               => 80,
            'rate'            => '1,56 viên/m²',
            'box'             => '3 viên · 1,92 m²',
            'grout'           => '1,5 - 2,0 mm',
            'collection_slug' => 'gach-80x80',
        ],
        '60x120' => [
            'label'           => '60×120',
            'meta'            => '1,4 viên/m²',
            'w'               => 60,
            'h'               => 120,
            'rate'            => '1,39 viên/m²',
            'box'             => '2 viên · 1,44 m²',
            'grout'           => '1,5 - 2,0 mm (ke cân bằng)',
            'collection_slug' => 'gach-60x120',
        ],
    ],
    'rooms'         => [
        [
            'label'     => 'Phòng dưới 12 m²',
            'recommend' => '30×60 · 60×60',
            'size'      => '60x60',
            'active'    => false,
        ],
        [
            'label'     => 'Phòng 12 - 25 m²',
            'recommend' => '60×60 · 80×80',
            'size'      => '80x80',
            'active'    => true,
        ],
        [
            'label'     => 'Không gian trên 25 m²',
            'recommend' => '80×80 · 60×120',
            'size'      => '60x120',
            'active'    => false,
        ],
        [
            'label'     => 'Phòng ngủ · Hành lang',
            'recommend' => 'Thanh gỗ 15×90',
            'size'      => '15x90',
            'active'    => false,
        ],
    ],
    'zones'         => [
        ['key' => 'all', 'label' => 'Tất cả', 'icon' => ''],
        ['key' => 'dry', 'label' => 'Phòng khô', 'icon' => 'sun'],
        ['key' => 'damp', 'label' => 'Bếp · Nhà tắm', 'icon' => 'drop'],
        ['key' => 'wet', 'label' => 'Ngoài trời · Hồ bơi', 'icon' => 'cloud-rain'],
    ],
    'surfaces'      => [
        [
            'key'             => 'polished',
            'zones'           => ['dry'],
            'name'            => 'Bóng kiếng (Polished)',
            'badge'           => 'R9 · Chỉ dùng nơi khô',
            'badge_variant'   => 'outline',
            'desc'            => 'Phòng khách, đại sảnh, ốp vách khô. Không lát sàn nhà tắm ướt.',
            'swatch'          => 'tile-marble-calacatta.svg',
            'collection_slug' => 'gach-phong-khach',
        ],
        [
            'key'             => 'matt',
            'zones'           => ['dry', 'damp'],
            'name'            => 'Mờ mịn (Matt / Satin)',
            'badge'           => 'R10 · Đa dụng an toàn',
            'badge_variant'   => 'moss',
            'desc'            => 'Sàn bếp, hành lang, phòng ngủ và sàn nhà tắm có vách kính khô.',
            'swatch'          => 'tile-cement-grey.svg',
            'collection_slug' => 'gach-nha-tam',
        ],
        [
            'key'             => 'structured',
            'zones'           => ['damp', 'wet'],
            'name'            => 'Nhám sần định hình (Structured)',
            'badge'           => 'R11 - R12 · Khu ướt',
            'badge_variant'   => 'amber',
            'desc'            => 'Sàn tắm đứng vòi sen, sân vườn mưa nắng, ram dốc gara, bậc tam cấp.',
            'swatch'          => 'tile-structured-outdoor.svg',
            'collection_slug' => 'gach-san-vuon',
        ],
        [
            'key'             => 'carving',
            'zones'           => ['dry', 'damp'],
            'name'            => 'Hiệu ứng hạt đường / Khắc rãnh (Carving)',
            'badge'           => 'R10 · Thẩm mỹ kiến trúc',
            'badge_variant'   => 'teal',
            'desc'            => 'Rãnh vân chìm bắt sáng đèn hắt, thích hợp vách điểm nhấn & sảnh.',
            'swatch'          => 'tile-sintered-emperador.svg',
            'collection_slug' => 'da-nung-ket',
        ],
    ],
];

