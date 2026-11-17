<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Cấu hình Stack S-3 cho trang Giới thiệu `/gioi-thieu` (`page-templates/template-about.php`, Step 10.6).
 *
 * @return array<int, array{section: string, props: array<string, mixed>}>
 */
return [
    [
        'section' => 'trust-strip',
        'props'   => [
            'surface' => 'white',
        ],
    ],
    [
        'section' => 'showroom-showcase',
        'props'   => [
            'eyebrow'          => 'Không gian trưng bày & thử nghiệm vật liệu',
            'title'            => 'Kiểm Chứng Bề Mặt Thật Trước Khi Chốt Mã Lô',
            'description'      => 'Đối chiếu độ phản xạ ánh sáng dưới 3 nhiệt độ đèn 3000K - 4000K - 6500K và thử trực tiếp độ bám chân R9 - R12.',
            'show_booking_cta' => true,
            'surface'          => 'ivory',
        ],
    ],
    [
        'section' => 'lead-capture-section',
        'props'   => [
            'form_slot'    => 'contact_page',
            'lead_type'    => 'contact',
            'title'        => 'Kết nối Phòng Dự án & Kỹ thuật DRAVIT',
            'sub'          => 'Gửi yêu cầu bóc tách khối lượng công trình, mượn viên mẫu thật hoặc hợp tác KTS / Nhà thầu.',
            'fields'       => ['role', 'email', 'province', 'project', 'area_m2', 'message'],
            'submit_label' => 'Gửi yêu cầu hợp tác & tư vấn',
            'surface'      => 'white',
            'anchor'       => 'lien-he-du-an',
        ],
    ],
];
