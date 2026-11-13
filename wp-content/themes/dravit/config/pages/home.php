<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Cấu hình lắp ghép 7 Section chuẩn cho Trang chủ (`front-page.php`, đối chiếu `01-home.html`, Phương án S-3, ADR D-34).
 *
 * @return array<int, array{section: string, props: array<string, mixed>}>
 */
return [
    [
        'section' => 'hero-advisor',
        'props'   => [
            'variant'     => 'home',
            'show_search' => true,
        ],
    ],
    [
        'section' => 'space-bento',
        'props'   => [
            'eyebrow'     => 'Chọn theo không gian',
            'title'       => 'Bạn Đang Tìm Gạch Cho Khu Vực Nào?',
            'description' => 'Mỗi không gian cần chuẩn độ nhám R và xương gạch khác nhau — chọn đúng ngay từ đầu.',
            'cta_label'   => 'Xem tất cả 28 danh mục',
            'cta_path'    => '/gach',
            'surface'     => 'ivory',
        ],
    ],
    [
        'section' => 'product-explorer-tabs',
        'props'   => [
            'eyebrow'     => '02 · Khám phá kho gạch thực tế',
            'title'       => 'Sản phẩm nổi bật theo công năng',
            'description' => 'Niêm yết song song giá/m² và giá/viên, quy đổi số thùng rõ ràng ngay trên từng mã.',
            'per_tab'     => 3,
            'surface'     => 'white',
        ],
    ],
    [
        'section' => 'size-r-guide',
        'props'   => [
            'eyebrow'     => '03 · Cẩm nang chọn khổ gạch & bề mặt',
            'title'       => 'Tỷ lệ kích thước thật & tiêu chuẩn chống trượt',
            'description' => 'So sánh trực quan 5 khổ gạch phổ biến trên cùng tỷ lệ và chọn bề mặt R9 - R12 đúng khu vực ướt/khô.',
            'surface'     => 'ivory',
        ],
    ],
    [
        'section' => 'product-grid-block',
        'props'   => [
            'mode'        => 'deals',
            'eyebrow'     => 'Khuyến mãi đặc biệt',
            'title'       => 'Chương Trình Khuyến Mãi & Giảm Giá',
            'description' => 'Ưu đãi theo m² và xả kho lẻ lô nguyên thùng loại 1 — số lượng thùng tồn kho hiển thị minh bạch.',
            'cta_label'   => 'Xem tất cả khuyến mãi',
            'cta_path'    => '/gach-uu-dai',
            'limit'       => 4,
            'columns'     => 4,
            'surface'     => 'white',
            'show_notice' => true,
        ],
    ],
    [
        'section' => 'showroom-showcase',
        'props'   => [
            'eyebrow'          => 'Trải nghiệm thực tế tại TP.HCM',
            'title'            => 'Xem Mẫu Gạch Thật Tại Showroom DRAVIT',
            'description'      => 'Đặt các tấm gạch lớn cạnh nhau, sờ tay kiểm tra độ nhám R9 - R12 và kéo thanh trượt bên dưới để so sánh không gian trước và sau khi lát gạch.',
            'show_booking_cta' => true,
            'surface'          => 'champagne',
        ],
    ],
    [
        'section' => 'editorial-guides',
        'props'   => [
            'eyebrow'     => '06 · Cẩm nang chọn gạch & thi công',
            'title'       => 'Kiến thức thực tế từ kỹ sư vật liệu',
            'description' => 'Hướng dẫn đọc thông số kỹ thuật, tính hao hụt và nghiệm thu ốp lát chuẩn công trường.',
            'cta_label'   => 'Xem tất cả bài viết',
            'cta_path'    => '/cam-nang',
            'limit'       => 3,
            'surface'     => 'ivory',
        ],
    ],
];
