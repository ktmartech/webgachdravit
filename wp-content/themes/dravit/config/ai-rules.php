<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Cấu hình Luật An toàn cứng, Trọng số chấm điểm mềm, Ma trận diện tích & Danh sách trắng
 * cho Trợ lý AI Tìm Gạch DRAVIT (ADR D-32, D-48, D-49; docs/13_AI_Advisor_MVP_Spec.md §5).
 *
 * @return array<string, mixed>
 */
return [
    'version' => '1.0.0',

    // 1. Ràng buộc an toàn cứng KHÔNG BAO GIỜ NỚI LỎNG (docs/13 §5.1, kb-an-toan-chong-truot.md)
    'safety_r_min' => [
        'nha-tam'    => 10,
        'phong-bep'  => 10,
        'san-vuon'   => 11,
        'ngoai-troi' => 11,
        'gara-o-to'  => 11,
        'ho-boi'     => 12,
    ],

    // Người dùng / điều kiện đặc biệt kích hoạt ngưỡng R tối thiểu
    'special_r_min' => [
        'nguoi-gia' => 10,
        'tre-nho'   => 10,
        'am-uot'    => 10,
        'doc-nuoc'  => 11,
    ],

    // Bảng quy đổi slug pa_dcof sang cấp số nguyên để so sánh >=
    'dcof_levels' => [
        'r9'  => 9,
        'r10' => 10,
        'r11' => 11,
        'r12' => 12,
        'r13' => 13,
    ],

    // Ràng buộc chịu tải cho gara ô tô / bãi đỗ xe
    'garage_min_thickness_mm' => 12.0,
    'garage_thickness_slugs'  => ['day', 'sieu-day-20mm'],

    // Không gian ngoài trời yêu cầu xương đặc chắc (E <= 0.5%) hoặc gắn đúng không gian ngoài trời
    'outdoor_spaces' => ['san-vuon', 'ngoai-troi', 'ho-boi', 'gara-o-to'],

    // 2. Trọng số chấm điểm mềm (tổng 100 điểm - docs/13 §5.2)
    'weights' => [
        'space'               => 30,
        'size'                => 20,
        'style_color_pattern' => 20,
        'surface'             => 10,
        'budget'              => 15,
        'stock_showroom'      => 5,
    ],

    // 3. Ma trận gợi ý khổ gạch theo diện tích sàn (kb-kich-thuoc-dien-tich.md)
    'area_size_matrix' => [
        [
            'max_m2' => 9.99,
            'sizes'  => ['30x60', '60x60', '40x40', '30x30', '15x60', '15x80', '15x90'],
            'label'  => 'Dưới 10 m² (ưu tiên khổ 30x60, 60x60 dễ tạo dốc và ít cắt hao hụt)',
        ],
        [
            'max_m2' => 20.0,
            'sizes'  => ['60x60', '30x60', '60x120', '15x80', '15x90', '20x100', '20x120'],
            'label'  => '10 - 20 m² (hợp khổ 60x60, thanh giả gỗ 15x90 hoặc 60x120)',
        ],
        [
            'max_m2' => 35.0,
            'sizes'  => ['80x80', '60x120', '60x60', '20x120', '75x150'],
            'label'  => '20 - 35 m² (hợp khổ 80x80 hoặc khổ lớn 60x120 mở rộng không gian)',
        ],
        [
            'max_m2' => 99999.0,
            'sizes'  => ['80x80', '60x120', '100x100', '120x120', '120x240', '160x320', '80x160', '90x180'],
            'label'  => 'Trên 35 m² (ưu tiên khổ lớn 80x80, 60x120 hoặc tấm đá nung kết Big Slab)',
        ],
    ],

    // 4. Giới hạn hiển thị, trộn quote_only & hao hụt mặc định
    'max_results'       => 6,
    'max_quote_only'    => 2,
    'default_waste_pct' => 5,

    // Thứ tự nới lỏng tiêu chí mềm khi 0 kết quả (TUYỆT ĐỐI không có safety_r hay độ dày gara)
    'relaxation_order' => [
        'budget',
        'color',
        'surface',
        'style',
        'size',
    ],

    // 5. Giới hạn bảo mật, ngân sách API & bộ nhớ đệm
    'rate_limit' => [
        'limit'  => 10,
        'window' => 600,
    ],
    'max_message_chars'  => 500,
    'daily_budget_calls' => 500,
    'cache_ttl_seconds'  => 900,
    'log_retention_days' => 90,

    // 6. Danh sách trắng thương hiệu & chặn tên đối thủ (Validator L5)
    'allowed_brands' => [
        'dravit',
        'dravit signature',
        'dravit luxury',
        'dravit eco',
    ],
    'competitor_brands' => [
        'viglacera',
        'dong tam',
        'đồng tâm',
        'prime',
        'taicera',
        'eurotile',
        'vietceramics',
        'casa',
        'catalan',
        'tasa',
        'cmc',
        'mikado',
        'bach ma',
        'bạch mã',
        'hoan my',
        'hoàn mỹ',
        'vitto',
        'amygress',
        'a my',
        'á mỹ',
        'royal',
        'inax',
        'toto',
        'kohler',
    ],

    // Danh sách trắng domain cho phép xuất hiện trong văn bản tư vấn
    'allowed_domains' => [
        'gachdravit.vn',
        'zalo.me',
    ],

    // 7. Chuỗi văn bản chuẩn Brand Voice DRAVIT (0 em-dash, đơn vị chuẩn m², không sáo rỗng)
    'messages' => [
        'welcome'          => 'Cho mình biết không gian cần ốp lát (phòng khách, nhà tắm, bếp, sân vườn), diện tích m² và tông màu mong muốn. Hệ thống sẽ lọc mẫu đạt chuẩn an toàn chống trượt R và tính sẵn số thùng chẵn cho bạn.',
        'out_of_scope'     => 'Mình chỉ hỗ trợ chọn gạch ốp lát và dự toán khối lượng từ kho DRAVIT. Bạn cho mình biết không gian cần ốp lát (phòng khách, nhà tắm, bếp, sân vườn) hoặc diện tích m² để mình gợi ý mẫu phù hợp nhé.',
        'competitor_ref'   => 'Mình chỉ tra cứu thông số và báo giá các dòng gạch trong danh mục chính hãng DRAVIT, không so sánh với hãng ngoài. Bạn chia sẻ kích thước hoặc bề mặt mong muốn để mình lọc mẫu tương đương tại kho DRAVIT nhé.',
        'no_info'          => 'Mình chưa có thông tin chi tiết về nội dung này trong tài liệu kỹ thuật đã duyệt. Bạn có thể nhắn trực tiếp chuyên viên kỹ thuật DRAVIT qua Zalo để được kiểm tra chính xác theo công trình thực tế.',
        'quote_only_note'  => 'Mẫu này chưa có giá niêm yết lẻ, DRAVIT sẽ gửi báo giá chiết khấu theo khối lượng khi bạn gửi yêu cầu.',
        'degraded_notice'  => 'Đang dùng chế độ gợi ý nhanh từ bộ quy tắc kỹ thuật DRAVIT.',
        'disclaimer'       => 'Số thùng và đơn giá mang tính tham khảo theo quy cách đóng hộp tiêu chuẩn. Giá thực tế, lô màu (Shade) và phí vận chuyển sẽ được DRAVIT xác nhận khi lên báo giá.',
        'rate_limited'     => 'Bạn đã gửi khá nhiều yêu cầu liên tiếp. Vui lòng đợi ít phút hoặc chuyển sang Bộ lọc 14 tiêu chí để tiếp tục xem toàn bộ kho gạch.',
        'feature_disabled' => 'Trợ lý AI tìm gạch hiện đang tạm đóng để cập nhật dữ liệu. Vui lòng sử dụng Bộ lọc 14 tiêu chí tại kho gạch.',
        'empty_catalog'    => 'Hiện chưa tìm thấy mẫu khớp toàn bộ điều kiện kỹ thuật này trong kho sẵn có. Bạn có thể gửi yêu cầu qua Zalo để bộ phận dự án tra cứu lô hàng sắp cập bến.',
    ],
];

