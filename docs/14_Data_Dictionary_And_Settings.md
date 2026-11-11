# 14. Từ điển Dữ liệu Tập trung & Cấu hình Hệ thống

> **Vai trò tài liệu:** Nguồn tham chiếu duy nhất (Single Source of Truth) cho toàn bộ tên khóa dữ liệu (Meta keys, Taxonomy terms, CPT `drv_lead`, HPOS Order/Cart meta, Option `drv_settings`, Cache keys và ánh xạ cột CSV) của dự án `gachdravit.vn`.
> **Ràng buộc kiến trúc:** PHP 8.1+ (`declare(strict_types=1);`), WooCommerce HPOS bắt buộc (`D-22`), không dùng ACF, không tạo bảng CSDL riêng (`D-07`, `D-23`), đơn ngôn ngữ Tiếng Việt (`D-01`), không dùng dấu gạch ngang dài (em-dash) trong chuỗi hiển thị UI.
> **Tham chiếu chéo:** [00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md) · [03_Catalog_Data_Model.md](03_Catalog_Data_Model.md) · [04_Pricing_Units_Commerce.md](04_Pricing_Units_Commerce.md) · [05_Faceted_Filter_AJAX.md](05_Faceted_Filter_AJAX.md) · [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md)

---

## 1. Quy ước Lưu trữ & Đặt tên (`D-07`, `D-22`, `D-23`)

### 1.1 Nguyên tắc Không Tạo Bảng DB Riêng (`D-07`, `D-23`)
Toàn bộ dữ liệu nghiệp vụ Phase 1 lưu trên các thực thể chuẩn của WordPress và WooCommerce để bảo toàn dữ liệu khi nâng cấp hoặc tách plugin ở giai đoạn sau:

| Miền dữ liệu | Cấu trúc lưu trữ chuẩn | Cơ chế Đọc / Ghi bắt buộc |
|---|---|---|
| Sản phẩm & quy cách gạch | `product` (`WC_Product_Simple`) + Product Meta | Bắt buộc qua WC CRUD: `$product->get_meta()`, `$product->update_meta_data()`, `$product->save()`. **Cấm** ghi trực tiếp vào `wp_postmeta` bằng `update_post_meta()` với dữ liệu giá/kho/thuộc tính. |
| 28 Smart Collections | Taxonomy `product_cat` + `wp_termmeta` | `get_term_meta()` / `update_term_meta()` + `config/collections.php`. |
| 14 Bộ lọc (Facets) | Global Attributes `pa_*` + Taxonomy ẩn `drv_price_band`, `drv_flag` | `wc_get_attribute_taxonomies()`, `wp_set_object_terms()`, `get_term_meta()`. |
| Đơn hàng & Dòng đơn | Bảng HPOS `wp_wc_orders`, `wp_wc_orders_meta`, `wp_woocommerce_order_itemmeta` | Bắt buộc qua WC Order CRUD: `wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`, `$item->get_meta()`, `$item->add_meta_data()`. **Nghiêm cấm** dùng `get_post_meta()` / `update_post_meta()` trên đơn hàng. |
| Báo giá BOQ & Lead | CPT private `drv_lead` (`wp_posts` + `wp_postmeta`) | `wp_insert_post()` + `get_post_meta()` / `update_post_meta()` gói trong `Dravit\Leads\LeadRepository`. |
| Cấu hình toàn hệ thống | `wp_options` (`drv_settings`, `drv_db_version`) | Settings API + helper `drv_setting('nhom.khoa')`. `autoload = 'yes'` cho `drv_settings`. |

### 1.2 Phân biệt Tiền tố `drv_*` và `_drv_*`

| Tiền tố | Phạm vi áp dụng | Lý do kỹ thuật | Ví dụ |
|---|---|---|---|
| `drv_*` (không gạch dưới đầu) | Product meta, Term meta (`product_cat`, `pa_*`), Post meta (Blog), Attachment meta, User meta, Cart item session data | Dữ liệu cấu hình và hiển thị công khai ra template/schema/REST nội bộ; dễ đối soát khi export/import CSV của WooCommerce. Trong Admin Product/Post, hộp *Custom Fields* mặc định của WP được ẩn qua hook để chỉ sửa qua Metabox chuẩn. | `drv_price_m2`, `drv_buy_units`, `drv_h1`, `drv_img_role`, `drv_job_title` |
| `_drv_*` (có gạch dưới đầu) | HPOS Order meta, Order Line Item snapshot meta, CPT `drv_lead` meta, Nav Menu Item meta, chỉ mục tìm kiếm ẩn | Protected meta của WP/WC: tự động ẩn khỏi danh sách meta thô của WooCommerce Order Items và WP Custom Fields UI; chỉ hiển thị qua formatter có kiểm soát (`woocommerce_order_item_get_formatted_meta_data` hoặc Admin Metabox riêng). | `_drv_ward_code`, `_drv_vat_tax_id`, `_drv_m2_qty`, `_drv_lot_code`, `_drv_boq` |

### 1.3 Ràng buộc Kiểu dữ liệu, Slug & Định dạng Chuỗi

1. **Giới hạn độ dài `post_status` / Order Status (`D-22`):** Cột `wp_posts.post_status` và `wp_wc_orders.status` có kiểu `varchar(20)`. Mọi custom status **bắt buộc dài `≤ 20` ký tự tính cả tiền tố `wc-`** (ví dụ: `wc-cho-xac-nhan` dài 15 ký tự).
2. **Slug Taxonomy & Series:** Chỉ dùng ký tự ASCII thường `a-z0-9-`, không dấu tiếng Việt, dấu thập phân `.` trong kích thước hoặc độ hút nước đổi thành `-` (`7.5x30` -> `7-5x30`, `0.5` -> `0-5`).
3. **Lưu mảng trong Meta:** Khi lưu mảng phẳng hoặc cấu trúc lồng nhau vào meta (`drv_buy_units`, `drv_face_image_ids`, `drv_faq`, `drv_geo_table`, `_drv_boq`), chuẩn hóa lưu chuỗi **JSON UTF-8** (`wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)`) để tránh lỗi `serialize` khi đổi domain và đọc trực tiếp được cả ở PHP lẫn JS.
4. **Chuẩn văn bản UI:** Không dùng ký tự em-dash trong mọi nhãn mặc định, placeholder, mô tả ngắn hay dữ liệu seed; chỉ dùng dấu `-`, `:` hoặc `·`. Đơn vị diện tích luôn viết `m²` (có số `²`), số thập phân dùng dấu `,`, tiền VND dùng dấu `.` phân cách nghìn kèm `đ` sát số (`385.000đ/m²`).

---

## 2. Product Meta (`WC_Product` CRUD - Tab Admin `"Đơn vị & Thông số"`)

Đăng ký tại class `Dravit\Admin\ProductDataTab` (tab riêng `"Đơn vị & Thông số"` trong hộp *Dữ liệu sản phẩm* của WooCommerce). Đọc/ghi qua `$product->get_meta($key, true)` và `$product->update_meta_data($key, $val)` trong hook `woocommerce_admin_process_product_object`.

### 2.1 Nhóm Định danh, Mô hình Giá & Quy cách Đóng gói

| Meta key | Kiểu PHP / Lưu DB | Mặc định | Bắt buộc? | Validate / Sanitize | Control UI Admin | Nơi sử dụng |
|---|---|---|---|---|---|---|
| `drv_line` | `string` (`'gach'\|'keo-cha-ron'\|'vat-tu'`) | `'gach'` | Có | Whitelist theo danh mục gốc trong `config/collections.php` (`sanitize_key`) | `<select>` Ngành hàng | `CollectionSync` (`C01`), Silo isolation `IL-06` |
| `drv_pricing_model` | `string` (`'area_pack'\|'piece'\|'set'\|'pack'`) | `'area_pack'` | Có | Whitelist key trong `config/units.php` | `<select>` Mô hình bán | `UnitRegistry`, `PriceHtml`, Cart, Schema |
| `drv_unit` | `string` | `''` | Không | Whitelist key `units` trong `config/units.php` (`'chiec'\|'bo'\|'tuyp'\|'bao'\|'kg'`) | `<select>` Đơn vị ghi đè (chỉ hiện khi model `≠ area_pack`) | Ghi đè đơn vị cho SP `pack`/`piece` Phase 2 |
| `drv_unit_label` | `string` (`'viên'\|'tấm'`) | `'viên'` | Có (gạch) | Whitelist `['viên', 'tấm']` | `<select>` Danh xưng đơn vị cơ sở | Card, PDP, `<drv-unit-calc>`, Cart (`'tấm'` cho Big Slab) |
| `drv_price_m2` | `int` (VND) | `0` | Không | `max(0, absint(preg_replace('/\D/', '', $raw)))`. Bằng `0` hoặc để trống -> chế độ `quote` (*Liên hệ báo giá*, `D-20`) | `<input type="text" inputmode="numeric">` Giá niêm yết / m² | Tính `_regular_price`, Card, PDP, Sort giá, `drv_price_band` |
| `drv_sale_price_m2` | `int` (VND) | `0` | Không | Chỉ lưu `> 0` khi `drv_price_m2 > 0` và `< drv_price_m2`; ngược lại ép về `0` | `<input type="text" inputmode="numeric">` Giá khuyến mãi / m² | Tính `_sale_price`, nhãn `% giảm`, `C08 gach-uu-dai` |
| `drv_piece_w_mm` | `int` (mm) | `0` | Có (gạch) | `absint`, khoảng `10..3600` | `<input type="number" min="10" max="3600">` Chiều rộng viên (mm) | Tính `area_piece`, sinh `pa_kich-thuoc`, Schema |
| `drv_piece_h_mm` | `int` (mm) | `0` | Có (gạch) | `absint`, khoảng `10..3600` | `<input type="number" min="10" max="3600">` Chiều dài viên (mm) | Tính `area_piece`, sinh `pa_kich-thuoc`, cờ `large_format` |
| `drv_thickness_mm` | `float` (mm) | `0.0` | Có (gạch) | `round((float) str_replace(',', '.', $raw), 1)`, khoảng `3.0..30.0` | `<input type="number" step="0.1">` Độ dày (mm) | Bảng thông số PDP, tự sinh `pa_do-day`, Schema |
| `drv_pieces_per_box` | `int` | `1` | Có (gạch) | `max(1, absint($raw))`, khoảng `1..200` | `<input type="number" min="1">` Số viên / thùng | Tính `drv_m2_per_box`, quy đổi thùng, bước nhảy `box` |
| `drv_m2_per_box` | `float` | `0.0` | Tự tính | `round(($w * $h / 1_000_000) * $pieces_per_box, 4)` | `<input readonly>` m² / thùng (tự động tính) | Card, PDP, `<drv-unit-calc>`, Schema |
| `drv_kg_per_box` | `float` (kg) | `0.0` | Khuyến nghị | `max(0.0, round((float) str_replace(',', '.', $raw), 2))` | `<input type="number" step="0.1">` Khối lượng / thùng (kg) | Quy đổi tải trọng đơn/BOQ, gợi ý xe tải, tính `_weight` WC |
| `drv_coverage_m2_per_unit` | `float` (m²) | `0.0` | Không | `max(0.0, round((float) $raw, 2))` | `<input type="number" step="0.01">` Định mức phủ m²/đơn vị (ẩn ở model `area_pack`) | Dự phòng cho keo chà ron / vữa model `pack` (Phase 2) |

### 2.2 Nhóm Cấu hình Đơn vị Mua Theo Từng Sản phẩm (`D-24`)

Cho phép Admin quyết định linh hoạt trên từng SKU gạch: khách được phép nhập theo đơn vị nào (`m²`, `thùng`, `viên`), tab nào mở mặc định, và sản phẩm bắt buộc xuất chẵn thùng hay cho phép mua lẻ từng viên/tấm. Giao dịch lõi gửi xuống WooCommerce vẫn luôn là **số nguyên viên** (`D-05`).

| Meta key | Kiểu PHP / Lưu DB | Mặc định | Bắt buộc? | Validate / Sanitize & Luật phụ thuộc | Control UI Admin | Nơi sử dụng |
|---|---|---|---|---|---|---|
| `drv_buy_units` | `string[]` (JSON string, vd `'["m2","thung"]'`) | Lấy từ `drv_settings.order.default_buy_units` (`['m2','thung']`) | Có | Tập con không rỗng của `['m2', 'thung', 'vien']`, giữ đúng thứ tự `m2 -> thung -> vien`. Nếu rỗng sau khi lọc -> gán lại mặc định hệ thống. | Nhóm 3 checkbox: `Theo m²`, `Theo thùng`, `Theo viên/tấm` | Render thanh tab đơn vị trong `<drv-unit-calc>`, REST `/products`, Store API |
| `drv_default_buy_unit` | `string` (`'m2'\|'thung'\|'vien'`) | `'m2'` | Có | Phải thuộc mảng `drv_buy_units` của chính SP đó; nếu không thuộc -> tự gán bằng `drv_buy_units[0]`. | `<select>` Đơn vị mở sẵn mặc định | Tab active ban đầu trên PDP `<drv-unit-calc>` & hành vi nút Thêm giỏ nhanh trên Card |
| `drv_round_mode` | `string` (`'box'\|'piece'`) | `'box'` | Có | Whitelist `['box', 'piece']`. **Luật cưỡng bức:** Nếu `'vien'` **không** nằm trong `drv_buy_units`, hệ thống tự động ép `drv_round_mode = 'box'` khi lưu. | Radio: `Bắt buộc chẵn thùng (box)` / `Cho phép mua lẻ viên (piece)` | `<drv-unit-calc>`, `<drv-qty-input>`, `QuantityRules` validate giỏ hàng |
| `drv_qty_min` | `int` (viên) | `= drv_pieces_per_box` (nếu `box`) hoặc `1` (nếu `piece`) | Có | `max(1, absint($raw))`. Nếu `drv_round_mode === 'box'`, tự động làm tròn lên bội số gần nhất của `drv_pieces_per_box` (`ceil($min / $ppb) * $ppb`). | `<input type="number" min="1">` Số lượng mua tối thiểu (tính theo viên) | `woocommerce_quantity_input_args`, Store API `minimum`, Thêm giỏ nhanh trên Card |
| `drv_qty_step` | `int` (viên) | Tự tính | Tự tính | Không nhập tay: luôn gán tự động `= drv_pieces_per_box` khi `drv_round_mode === 'box'`, và `= 1` khi `drv_round_mode === 'piece'`. | `<input readonly>` Bước nhảy giao dịch (viên) | Validate bội số trong `woocommerce_add_to_cart_validation`, `woocommerce_update_cart_validation`, Store API `multiple_of` |
| `drv_waste_rate` | `float` (%) | Rỗng `''` (dùng mặc định hệ thống `5`) | Không | Để trống `''` hoặc số thực `0.0..20.0` (`round((float) $raw, 1)`). | `<input type="number" step="0.5" placeholder="Theo cài đặt chung (5%)">` Ghi đè hao hụt (%) | `<drv-unit-calc>` (ví dụ gạch khổ lớn hoặc lát xương cá đặt `10%`) |

### 2.3 Nhóm Thông số Kỹ thuật, Trưng bày & Nội dung Bán hàng

| Meta key | Kiểu PHP / Lưu DB | Mặc định | Bắt buộc? | Validate / Sanitize | Control UI Admin | Nơi sử dụng |
|---|---|---|---|---|---|---|
| `drv_water_absorption` | `float` (%) | `0.5` | Có (gạch) | `round((float) str_replace(',', '.', $raw), 2)`, khoảng `0.0..25.0` | `<input type="number" step="0.01">` Độ hút nước E (%) | Tự sinh `pa_do-hut-nuoc`, Bảng thông số PDP, Schema |
| `drv_pei` | `string` (`''\|'I'\|'II'\|'III'\|'IV'\|'V'`) | `''` | Không | Whitelist `['', 'I', 'II', 'III', 'IV', 'V']` | `<select>` Độ chịu mài mòn PEI | Bảng thông số PDP, Schema `additionalProperty` |
| `drv_mohs` | `int` | `0` | Không | `0` (trống) hoặc số nguyên `1..10` | `<input type="number" min="1" max="10">` Độ cứng bề mặt Mohs | Bảng thông số mở rộng PDP |
| `drv_breaking_strength` | `int` (N) | `0` | Không | `absint`, vd `1300`, `2000` (`0` = không công bố) | `<input type="number" min="0">` Lực phá vỡ (N) | Bảng thông số kỹ thuật PDP (ISO 13006) |
| `drv_bending_strength` | `float` (N/mm²) | `0.0` | Không | `max(0.0, round((float) $raw, 1))`, vd `35.0` | `<input type="number" step="0.5">` Cường độ chịu uốn (N/mm²) | Bảng thông số kỹ thuật PDP (tách biệt với lực phá vỡ N) |
| `drv_rectified` | `string` (`'yes'\|'no'`) | `'yes'` | Không | `'yes'` hoặc `'no'` | Checkbox `Mài cạnh chuẩn xác (Rectified)` | Bảng thông số PDP, tóm tắt kỹ thuật |
| `drv_grout_min_mm` | `float` (mm) | `1.5` | Không | `round((float) $raw, 1)`, khoảng `0.5..15.0` | `<input type="number" step="0.5">` Mạch ron khuyến nghị tối thiểu (mm) | Bảng thông số & Lưu ý thi công PDP (`{grout}`) |
| `drv_grout_max_mm` | `float` (mm) | `2.0` | Không | `max($grout_min, round((float) $raw, 1))`, khoảng `0.5..20.0` | `<input type="number" step="0.5">` Mạch ron khuyến nghị tối đa (mm) | Bảng thông số & Lưu ý thi công PDP (`1,5 - 2 mm`) |
| `drv_stain_class` | `int` | `0` | Không | `0` (trống) hoặc số nguyên `1..5` | `<select>` Cấp chống bám bẩn (Class 1 - 5) | Bảng thông số mở rộng PDP |
| `drv_shade` | `string` (`''\|'V1'\|'V2'\|'V3'\|'V4'`) | `''` | Không | Whitelist `['', 'V1', 'V2', 'V3', 'V4']` | `<select>` Biến thiên vân màu thiết kế (Shade) | PDP spec & lưu ý thi công (`D-30`: không phải mã lô kho) |
| `drv_faces` | `int` | `0` | Không | `absint`, khoảng `0..64` | `<input type="number" min="0" max="64">` Số mặt vân ngẫu nhiên (Faces) | Badge gallery, khối Lưới mặt vân PDP |
| `drv_color_name` | `string` | `''` | Khuyến nghị | `sanitize_text_field`, tối đa 60 ký tự | `<input type="text" maxlength="60">` Tên màu thương mại | Nhãn màu trên PDP, tooltip trục swatch `drv_series`, BOQ |
| `drv_series` | `string` | `''` | Không | `sanitize_title` (ASCII slug, vd `calacatta-gold`) | `<input type="text">` Mã dòng Series (nhóm biến thể) | Truy vấn SKU anh em render link `<a href>` SSR (`D-26`) |
| `drv_showroom_shelf` | `string` | `''` | Không | `sanitize_text_field`, in hoa mã kệ, tối đa 30 ký tự (vd `A-08`) | `<input type="text" maxlength="30">` Vị trí kệ trưng bày Showroom | Card, PDP; khác rỗng -> tự gán `drv_flag = showroom` |
| `drv_card_label` | `string` | `''` | Không | `sanitize_text_field`, tối đa 20 ký tự (vd `Mẫu mới`, `Ưa chuộng`) | `<input type="text" maxlength="20">` Nhãn chip trên thẻ SP | Chip góc trên `.drv-card`; trống -> fallback tự động |
| `drv_face_image_ids` | `int[]` (JSON string) | `'[]'` | Không | Mảng `absint` attachment ID hợp lệ (`wp_attachment_is_image`), tối đa 24 ảnh | Media Picker đa ảnh `Ảnh các mặt vân (Faces)` | Khối hiển thị các mặt vân thực tế trên PDP |
| `drv_map_file_id` | `int` | `0` | Không | `absint` attachment ID (chỉ nhận `.zip` hoặc `.pdf`) | Media Picker `File Map 3D / CAD cho KTS` | Nút tải Map 3D trên PDP (`data-drv-action="download-map"`) |
| `drv_lighting_note` | `string` | `''` | Không | `sanitize_text_field`, tối đa 250 ký tự | `<textarea rows="2" maxlength="250">` Ghi chú dưới ánh đèn 3000K/6500K | Bảng thông số mở rộng & lưu ý chọn mẫu PDP |
| `drv_warranty` | `string` | `''` | Không | `sanitize_text_field`, tối đa 120 ký tự | `<input type="text" maxlength="120">` Chính sách bảo hành | Bảng thông số PDP |
| `drv_video_url` | `string` | `''` | Không | `esc_url_raw` (chỉ nhận `https://` YouTube hoặc MP4 cùng domain) | `<input type="url">` URL Video thực tế | Nút xem video quay thật viên gạch trên PDP |
| `drv_ai_blurb` | `string` | `''` | Không | `sanitize_text_field`, tối đa 300 ký tự, không chứa giá cứng | `<textarea rows="2" maxlength="300">` Tóm tắt dữ kiện cho AI (Phase 3) | Snapshot catalog cho AI Advisor (`13_AI_Advisor_MVP_Spec.md`) |
| `rank_math_primary_product_cat` | `int` | `0` | Tự điền nếu trống | `absint` `term_id` thuộc tập `product_cat` của SP | Hộp chọn *Primary Term* của Rank Math | Cố định Breadcrumb `IL-04` & Schema `Product.category` |
| `drv_primary_auto` | `string` (`'1'\|'0'`) | `'0'` | Tự sinh | `'1'` khi hệ thống tự chọn theo `priority`, `'0'` khi Admin chọn tay | Admin Notice nhắc kiểm tra danh mục chính | `Catalog\PrimaryCategory` |
| `_drv_search_index` | `string` | `''` | Tự sinh | Chuỗi ASCII thường bỏ dấu ghép từ `{sku} {title} {color_name} {series} {term_names}` | Không hiển thị | Tăng tốc truy vấn không dấu trong `/dravit/v1/search` |

> **Trường WooCommerce Native giữ nguyên cách dùng:** `_sku` (Mã SKU duy nhất), `global_unique_id` (GTIN/EAN), `_regular_price` / `_sale_price` / `_price` (khóa `readonly` trên UI khi model `= area_pack`, tự tính theo **đồng/viên**), `_sale_price_dates_from` / `_sale_price_dates_to`, `_manage_stock`, `_stock` (**số nguyên viên**), `_low_stock_amount`, `_stock_status`, `_weight` (**kg/viên**, tự tính từ `drv_kg_per_box / drv_pieces_per_box`), `_thumbnail_id` (ảnh chính LCP), `_product_image_gallery` (`gallery[0]` làm ảnh hover trên Card).

### 2.4 Bảng Dữ liệu Tự dẫn xuất khi Lưu Sản phẩm & Thuật toán `drv_price_state`

Mỗi khi hook `woocommerce_admin_process_product_object` và `woocommerce_after_product_object_save` kích hoạt trên sản phẩm `drv_line = 'gach'`:

| Dữ liệu nguồn | Dữ liệu đích tự sinh | Công thức / Quy tắc chính xác |
|---|---|---|
| `drv_piece_w_mm`, `drv_piece_h_mm` | Term `pa_kich-thuoc` | `$min = min($w, $h) / 10; $max = max($w, $h) / 10;` định dạng bỏ số `0` thập phân dư, thay `.` bằng `-`, ghép `"{$min}x{$max}"` (vd `75×300` -> `7-5x30`, `800×800` -> `80x80`). |
| `drv_piece_w_mm`, `drv_piece_h_mm` | Cờ runtime `large_format` | `max($w_mm, $h_mm) > 1200` (vd `1200×2400` -> `true` -> tự vào `da-nung-ket`; `600×1200` -> `false`). |
| `w_mm`, `h_mm`, `drv_pieces_per_box` | Meta `drv_m2_per_box` | `round(($w_mm * $h_mm / 1_000_000) * $pieces_per_box, 4)`. |
| `drv_kg_per_box`, `drv_pieces_per_box` | WC `_weight` (kg/viên) | Nếu `drv_kg_per_box > 0`: `round($kg_per_box / $pieces_per_box, 3)`. |
| `drv_thickness_mm` (`$t`) | Term `pa_do-day` | `$t <= 6.0` -> `mong`; `$t > 6.0 && $t < 12.0` -> `tieu-chuan`; `$t >= 12.0` -> `day`. |
| `drv_water_absorption` (`$e`) | Term `pa_do-hut-nuoc` | `$e <= 0.5` -> `duoi-0-5`; `$e <= 3.0` -> `0-5-3`; `$e <= 6.0` -> `3-6`; `$e <= 10.0` -> `6-10`; `$e > 10.0` -> `tren-10`. |
| `drv_price_m2`, `drv_sale_price_m2` | WC `_regular_price`, `_sale_price`, `_price` & Term `drv_price_band` | - Nếu `drv_price_m2 > 0`: `_regular_price = (string) (int) round($price_m2 * $area_piece)`. Nếu `0 < $sale_m2 && $sale_m2 < $price_m2`: `_sale_price = (string) (int) round($sale_m2 * $area_piece)`, ngược lại `_sale_price = ''`. Giá hiệu lực/m² `$eff_m2 = $product->is_on_sale('edit') ? $sale_m2 : $price_m2` gán vào `drv_price_band`: `1..199999` -> `duoi-200k`; `200000..350000` -> `200k-350k`; `350001..600000` -> `350k-600k`; `>= 600001` -> `tren-600k`.<br>- Nếu `drv_price_m2 <= 0`: gán `_regular_price = ''`, `_sale_price = ''`, `_price = ''`; gán `drv_price_band = gia-du-an`. |
| `drv_showroom_shelf` | Term `drv_flag` | `trim($shelf) !== ''` -> gán `showroom`; trống -> gỡ `showroom`. |
| `drv_buy_units`, `drv_round_mode` | `drv_qty_step`, `drv_qty_min` | Nếu `!in_array('vien', $buy_units, true)` -> `$round_mode = 'box'`. `$qty_step = ($round_mode === 'box') ? $pieces_per_box : 1`. `$qty_min = ($round_mode === 'box') ? (int) ceil(max(1, $qty_min) / $pieces_per_box) * $pieces_per_box : max(1, $qty_min)`. |

**Hàm suy dẫn trạng thái giá `Dravit\Units\PriceState::resolve(WC_Product $product): string` (`D-20`):**
Không lưu cứng xuống DB để luôn phản ánh tức thời lịch khuyến mãi và biến động tồn kho:

```php
public static function resolve(\WC_Product $product): string {
    $price_m2 = (int) $product->get_meta('drv_price_m2', true);
    if ($price_m2 <= 0 || $product->get_regular_price() === '') {
        return 'quote_only'; // Khóa is_purchasable = false, CTA = Báo giá / Thêm vào DS báo giá, ẩn Offer JSON-LD
    }
    if (!$product->is_in_stock()) {
        return 'out_of_stock'; // Hiển thị giá tham khảo + badge "Tạm hết", khóa Thêm giỏ, mở CTA Nhận báo giá lô mới
    }
    if ($product->is_on_sale()) {
        return 'sale'; // Hiển thị giá KM/m² + giá gốc gạch ngang + chip -X%
    }
    return 'priced'; // Hiển thị giá niêm yết/m² + giá/viên + nút Thêm vào giỏ
}
```

---

## 3. Attachment Meta (Media Library)

Lưu trên `post_type = 'attachment'` thông qua hook `attachment_fields_to_edit` và `attachment_fields_to_save` (`Dravit\Admin\AttachmentFields`).

| Meta / Field | Kiểu | Giá trị hợp lệ / Mặc định | Mục đích & Nơi sử dụng |
|---|---|---|---|
| `drv_img_role` | `string` (post meta) | `'room'` \| `'face'` \| `'light'` \| `'grout'` \| `'showroom'` \| `'edge'` (mặc định `'room'`) | Phân loại vai trò ảnh trong gallery PDP: `room` (phối cảnh không gian), `face` (chụp thẳng bề mặt viên), `light` (chụp nghiêng bắt sáng/hiệu ứng men), `grout` (cận cảnh mạch ron), `showroom` (ảnh chụp thực tế tại kệ), `edge` (chụp cạnh xương gạch/độ dày). |
| `drv_img_tag` | `string` (post meta) | Tối đa 30 ký tự, mặc định `''` | Nhãn badge ngắn đè lên góc ảnh trên PDP Gallery (ví dụ: `"Phối cảnh phòng khách"`, `"Cận cảnh mạch ron 1,5 mm"`, `"Ánh đèn 3000K"`). Nếu trống, fallback theo nhãn mặc định của `drv_img_role`. |
| `_wp_attachment_image_alt` | `string` (WP native) | Văn bản mô tả thật ≤ 125 ký tự | Thuộc tính `alt` bắt buộc cho SEO Ảnh & WCAG 2.2 AA. |
| `post_excerpt` | `string` (WP `wp_posts`) | Caption ảnh ≤ 200 ký tự | Hiển thị làm dòng chú thích bên dưới khung zoom của **PhotoSwipe 5** trên PDP và bài viết Cẩm nang. |

> **Quy ước thứ tự Gallery sản phẩm (không tạo thêm meta):** Ảnh đại diện (`_thumbnail_id`) là ảnh chính LCP trên PDP và ảnh mặc định trên `.drv-card`. Phần tử đầu tiên của thư viện ảnh (`gallery_image_ids[0]`) tự động được dùng làm **ảnh thứ 2 khi hover trên `.drv-card`** (ưu tiên đặt ảnh phối cảnh nếu ảnh chính là mặt gạch, hoặc ngược lại).

---

## 4. Term Meta & Từ điển Chuẩn 14 Facets

### 4.1 Term Meta trên `product_cat` (28 Smart Collections)

Quản lý qua `Dravit\Admin\TermFields` (màn hình sửa Danh mục sản phẩm) và khởi tạo ban đầu qua Seeder từ `config/collections.php`. Trường `description` mặc định của WP không sử dụng ngoài frontend để tránh lặp với `drv_intro`.

| Meta key | Kiểu PHP / Lưu DB | Mặc định | Validate / Sanitize | Nơi sử dụng |
|---|---|---|---|---|
| `drv_collection_id` | `string` | Theo config (`DRV-C01`..`DRV-Z05`) | `sanitize_text_field`, readonly | Định danh cố định đối chiếu tài liệu Silo & Seeder |
| `drv_group` | `string` | `'root'\|'function'\|'space'\|'material'\|'size'` | Whitelist 5 nhóm | Chia cột Mega Menu 4+1, nhóm Quick Pills `IL-01` |
| `drv_h1` | `string` | Theo cột H1 tài liệu Silo | `sanitize_text_field`, ≤ 120 ký tự | Thẻ `<h1>` duy nhất đầu trang danh mục |
| `drv_intro` | `string` | `''` | `wp_kses($val, ['strong' => []])`, ≤ 60 chữ | Đoạn tóm tắt kỹ thuật 1-2 dòng ngay dưới `<h1>` |
| `drv_seo_footer` | `string` (HTML) | `''` | `wp_kses_post` (600 - 1.200 chữ) | Bài viết hướng dẫn chuyên sâu cuối trang danh mục (`IL-01`) |
| `drv_faq` | `array<{q:string,a:string}>` (JSON) | `'[]'` | Tối đa 8 cặp, `q` `sanitize_text_field`, `a` `wp_kses_post` | Accordion `<details>` cuối trang + nguồn duy nhất sinh `FAQPage` JSON-LD |
| `drv_hero_image` | `int` (attachment ID) | `0` | `absint` | Ảnh đại diện OG Social, ảnh cột 5 Mega Menu Preview, thẻ không gian Home |
| `drv_thumb_id` | `int` (attachment ID) | `0` | `absint` (tỷ lệ 1:1, 96×96px) | Ảnh nhỏ trên dải Sub-silo Tiles và gợi ý Silo trong Live Search |
| `drv_related` | `int[]` (JSON `term_id[]`) | Từ `related` trong config | Mảng `absint` `product_cat` | Pills *"Gợi ý theo nhu cầu"* (`IL-02`) và gợi ý khi lọc 0 kết quả |
| `drv_geo_table` | `array` (JSON) | `'{}'` | `{title:string, caption:string, headers:string[], rows:string[][]}` | Bảng dữ kiện chuẩn GEO (`<table>` + `<caption>`) trên trang danh mục |
| `drv_tile_items` | `array` (JSON) | `'[]'` | Mảng tối đa 10 mục `{type:'collection'\|'facet', target:string, label:string, thumb_id:int}` | Dải Sub-silo Tiles ngang đầu trang danh mục (ưu tiên trỏ URL tĩnh theo `D-29`) |
| `drv_menu_badge` | `string` | `''` | `sanitize_text_field`, ≤ 16 ký tự | Nhãn phụ cạnh tên danh mục trên Mega Menu (vd `"Phổ biến"`, `"Khổ lớn"`) |
| `drv_menu_desc` | `string` | `''` | `sanitize_text_field`, ≤ 110 ký tự | Mô tả ngắn hiển thị ở Cột 5 Mega Menu khi rê chuột vào link |
| `drv_home_spec` | `string` | `''` | `sanitize_text_field`, ≤ 50 ký tự | Dòng thông số ngắn trên ô Không gian ở Trang chủ (vd `"R10 · Hút nước ≤ 0,5%"`) |
| `drv_lookbook` | `array` (JSON) | `'{}'` | `{image_id:int, title:string, desc:string, skus:string[]}` | Phối cảnh mẫu trong khối Explorer 4 tab ở Trang chủ |
| `drv_quick_chips` | `array` (JSON) | `'[]'` | Mảng `{label:string, url:string}` (chỉ URL tĩnh hoặc 1-param SEO hợp lệ) | Chip điều hướng nhanh dưới phần giới thiệu |
| `rank_math_title` | `string` | Theo Silo | ≤ 60 ký tự (Seeder chỉ điền khi đang trống) | Thẻ `<title>` trang danh mục |
| `rank_math_description` | `string` | Theo Silo | ≤ 160 ký tự (Seeder chỉ điền khi đang trống) | Meta description trang danh mục |

### 4.2 Bảng Trọng số `priority` Chuẩn của 28 Smart Collections (`D-27`)

Dùng trong `config/collections.php` để `PrimaryCategory` tự chọn danh mục chính khi Admin chưa gán tay (đã hiệu chỉnh theo `D-27` để khớp 100% cả 8/8 SKU nghiệm thu tại `09 §3.1`, bỏ qua `C01 gach` khi xét primary):

| Mã ID | Slug | Nhóm (`drv_group`) | Priority (`D-27`) | Phase | Điều kiện tự gán (`rule`) |
|---|---|---|---:|---:|---|
| `DRV-C01` | `gach` | `root` | `0` | 1 | `line = gach` |
| `DRV-C02` | `gach-lat-nen` | `function` | `40` | 1 | `pa_cong-nang ∋ lat-nen` |
| `DRV-C03` | `gach-op-tuong` | `function` | **`42`** | 1 | `pa_cong-nang ∋ op-tuong` |
| `DRV-C04` | `gach-trang-tri` | `function` | `30` | 1 | `pa_cong-nang ∋ trang-tri` |
| `DRV-C05` | `gach-bong` | `function` | `70` | 2 | `pa_cong-nang ∋ gach-bong` |
| `DRV-C06` | `gach-bong-gio` | `function` | `70` | 2 | `pa_cong-nang ∋ bong-gio` |
| `DRV-C07` | `gach-the` | `function` | `70` | 2 | `pa_cong-nang ∋ gach-the` |
| `DRV-C08` | `gach-uu-dai` | `function` | `5` | 2 | `flag: on_sale` |
| `DRV-S01` | `gach-phong-khach` | `space` | `20` | 1 | `pa_khong-gian ∋ phong-khach` |
| `DRV-S02` | `gach-nha-tam` | `space` | `20` | 1 | `pa_khong-gian ∋ nha-tam` |
| `DRV-S03` | `gach-phong-bep` | `space` | `20` | 1 | `pa_khong-gian ∋ phong-bep` |
| `DRV-S04` | `gach-san-vuon` | `space` | **`55`** | 1 | `pa_khong-gian ∋ san-vuon` |
| `DRV-S05` | `gach-ngoai-troi` | `space` | `15` | 2 | `pa_khong-gian ∋ ngoai-troi` |
| `DRV-S06` | `gach-mat-tien` | `space` | `15` | 2 | `pa_khong-gian ∋ mat-tien` |
| `DRV-S07` | `gach-phong-ngu` | `space` | `15` | 2 | `pa_khong-gian ∋ phong-ngu` |
| `DRV-S08` | `gach-ho-boi` | `space` | `25` | 2 | `pa_khong-gian ∋ ho-boi` |
| `DRV-M01` | `gach-gia-go` | `material` | `60` | 1 | `pa_van-gach ∋ gia-go` |
| `DRV-M02` | `gach-van-da` | `material` | **`30`** | 1 | `pa_van-gach ∋ van-da-marble` OR `van-da-tu-nhien` |
| `DRV-M03` | `gach-terrazzo` | `material` | `60` | 1 | `pa_van-gach ∋ terrazzo` |
| `DRV-M04` | `gach-van-xi-mang` | `material` | **`42`** | 1 | `pa_van-gach ∋ van-xi-mang` |
| `DRV-M05` | `gach-mosaic` | `material` | `60` | 2 | `pa_van-gach ∋ mosaic` |
| `DRV-M06` | `da-nung-ket` | `material` | `80` | 1 | `pa_chat-lieu ∋ da-nung-ket` OR `flag: large_format` |
| `DRV-M07` | `gach-porcelain` | `material` | `10` | 2 | `pa_chat-lieu ∋ porcelain` OR `granite` |
| `DRV-Z01` | `gach-60x60` | `size` | `35` | 1 | `pa_kich-thuoc ∋ 60x60` |
| `DRV-Z02` | `gach-80x80` | `size` | `35` | 1 | `pa_kich-thuoc ∋ 80x80` |
| `DRV-Z03` | `gach-30x60` | `size` | `35` | 1 | `pa_kich-thuoc ∋ 30x60` |
| `DRV-Z04` | `gach-60x120` | `size` | `45` | 1 | `pa_kich-thuoc ∋ 60x120` |
| `DRV-Z05` | `gach-15x80-20x100` | `size` | `35` | 2 | `pa_kich-thuoc ∋ 15x80, 15x90, 20x100, 20x120` |

### 4.3 Term Meta trên `pa_*` và Taxonomy Ẩn

| Meta key | Áp dụng cho Taxonomy | Kiểu | Mô tả |
|---|---|---|---|
| `drv_filter_label` | Mọi `pa_*`, `drv_price_band`, `drv_flag` | `string` | Nhãn giải thích đầy đủ hiển thị trong cột lọc Sidebar/Drawer (ví dụ `R10: Bếp, nhà tắm, an toàn trẻ nhỏ`). Nếu trống, dùng `term->name`. |
| `drv_short_label` | `pa_be-mat`, `pa_dcof`, `pa_chat-lieu` | `string` | Nhãn rút gọn ≤ 12 ký tự hiển thị trên tag thông số của `.drv-card` (ví dụ `Matt`, `Bóng`, `R10`). |
| `drv_swatch_hex` | `pa_mau-sac` | `string` | Mã màu HEX `#RRGGBB` hoặc token gradient CSS (`multi`) để vẽ chấm màu `.drv-swatch-dot` trong bộ lọc và BOQ. |
| `drv_icon` | `pa_hinh-dang` | `string` | Tên class icon Phosphor thuộc bộ subset tự host (ví dụ `ph-square`, `ph-rectangle`, `ph-hexagon`). |

### 4.4 Từ điển Term Chuẩn của 14 Facets (Single Source cho Seeder & `config/facets.php`)

> **Quy tắc bất biến:** Nghiêm cấm tạo slug biến thể như `mo-matt`, `nham-san`, `nau-go`, `den-than`, `duoi-05`, `8.5mm` như bản HTML prototype. Mọi template, seeder, CSV import và bộ lọc đều phải dùng chính xác các slug dưới đây.

#### A. `pa_cong-nang` (Param: `cong-nang` · `FLT-01` · Nhóm `seo`)
| Thứ tự | Slug | `name` | `drv_filter_label` | Collection tĩnh ánh xạ |
|---:|---|---|---|---|
| 1 | `lat-nen` | Lát nền | Gạch lát nền chịu lực | `/gach-lat-nen` |
| 2 | `op-tuong` | Ốp tường | Gạch ốp tường & vách trang trí | `/gach-op-tuong` |
| 3 | `trang-tri` | Trang trí | Gạch điểm nhấn trang trí | `/gach-trang-tri` |
| 4 | `gach-bong` | Gạch bông | Gạch bông mỹ thuật | `/gach-bong` |
| 5 | `bong-gio` | Bông gió | Gạch bông gió chắn nắng | `/gach-bong-gio` |
| 6 | `gach-the` | Gạch thẻ | Gạch thẻ ốp tường | `/gach-the` |

#### B. `pa_khong-gian` (Param: `khong-gian` · `FLT-02` · Nhóm `seo`)
| Thứ tự | Slug | `name` | `drv_filter_label` | Collection tĩnh ánh xạ | Ngưỡng R khuyến nghị |
|---:|---|---|---|---|---|
| 1 | `phong-khach` | Phòng khách | Phòng khách & sảnh chính | `/gach-phong-khach` | `≥ R9` |
| 2 | `nha-tam` | Nhà tắm | Nhà tắm & khu vệ sinh | `/gach-nha-tam` | `≥ R10` |
| 3 | `phong-bep` | Phòng bếp | Phòng bếp & bàn ăn | `/gach-phong-bep` | `≥ R10` |
| 4 | `san-vuon` | Sân vườn | Sân vườn & lối dạo | `/gach-san-vuon` | `≥ R11` |
| 5 | `ngoai-troi` | Ngoài trời | Ban công & sân thượng | `/gach-ngoai-troi` | `≥ R11` |
| 6 | `mat-tien` | Mặt tiền | Mặt tiền & trụ cổng | `/gach-mat-tien` | Không bắt buộc (ốp đứng) |
| 7 | `phong-ngu` | Phòng ngủ | Phòng ngủ | `/gach-phong-ngu` | `≥ R9` |
| 8 | `ho-boi` | Hồ bơi | Hồ bơi & thành bể | `/gach-ho-boi` | `≥ R11` (`R12` cho dốc/bậc) |

#### C. `pa_van-gach` & `pa_chat-lieu` (Params: `van-gach`, `chat-lieu` · `FLT-03` · Nhóm `seo`)
| Taxonomy | Thứ tự | Slug | `name` | `drv_filter_label` | `drv_short_label` | Collection tĩnh ánh xạ |
|---|---:|---|---|---|---|---|
| `pa_van-gach` | 1 | `van-da-marble` | Vân đá Marble | Vân đá cẩm thạch (Marble) | Marble | `/gach-van-da` |
| `pa_van-gach` | 2 | `van-da-tu-nhien` | Vân đá tự nhiên | Vân đá tự nhiên (Stone) | Vân đá | `/gach-van-da` |
| `pa_van-gach` | 3 | `gia-go` | Giả gỗ | Vân gỗ tự nhiên | Vân gỗ | `/gach-gia-go` |
| `pa_van-gach` | 4 | `terrazzo` | Terrazzo | Hạt đá mài Terrazzo | Terrazzo | `/gach-terrazzo` |
| `pa_van-gach` | 5 | `van-xi-mang` | Vân xi măng | Vân xi măng & bê tông | Xi măng | `/gach-van-xi-mang` |
| `pa_van-gach` | 6 | `mosaic` | Mosaic | Gạch vỉ Mosaic | Mosaic | `/gach-mosaic` |
| `pa_chat-lieu` | 1 | `porcelain` | Porcelain | Xương đá Porcelain | Porcelain | `/gach-porcelain` |
| `pa_chat-lieu` | 2 | `da-nung-ket` | Đá nung kết | Đá nung kết (Sintered Stone) | Đá nung kết | `/da-nung-ket` |
| `pa_chat-lieu` | 3 | `granite` | Granite | Xương đá đồng chất Granite | Granite | `/gach-porcelain` |
| `pa_chat-lieu` | 4 | `ceramic` | Ceramic | Xương men Ceramic ốp nhẹ | Ceramic | *(lọc `?chat-lieu=ceramic`)* |
| `pa_chat-lieu` | 5 | `xi-mang` | Xi măng | Cốt liệu xi măng ép tĩnh | Xi măng | *(lọc `?chat-lieu=xi-mang`)* |

#### D. `pa_kich-thuoc` (Param: `kich-thuoc` · `FLT-04` · Nhóm `seo` · Tự sinh từ `mm`)
| Khổ chuẩn (Slug) | `name` | Collection tĩnh ánh xạ (`static_map`) | Ghi chú |
|---|---|---|---|
| `60x60` | 60x60 cm | `/gach-60x60` | Lát nền phổ thông |
| `80x80` | 80x80 cm | `/gach-80x80` | Phòng khách > 20 m² |
| `30x60` | 30x60 cm | `/gach-30x60` | Ốp tường / lát nhà tắm |
| `60x120` | 60x120 cm | `/gach-60x120` | Khổ lớn thông dụng |
| `15x80`, `15x90`, `20x100`, `20x120` | {slug} cm | `/gach-15x80-20x100` (chỉ 301 khi chọn đủ nhóm Z05; chọn lẻ 1 khổ giữ `?kich-thuoc=15x90`) | Gạch thanh giả gỗ |
| `120x240`, `160x320` | {slug} cm | *(tự gán vào `/da-nung-ket`, lọc `?kich-thuoc=120x240`)* | Big Slab (`large_format`) |
| `20x20`, `30x30`, `40x40`, `7-5x30`, `10x30`... | Ví dụ `7,5x30 cm` | *(không có URL tĩnh -> lọc `?kich-thuoc=7-5x30`)* | Khổ trang trí / sân vườn |

#### E. `pa_bo-suu-tap` & `pa_xuat-xu` (`FLT-05` · Nhóm `seo` · Có Public Archive)
| Taxonomy | Slug seed chuẩn | `name` | URL Archive tĩnh (`index, follow`) |
|---|---|---|---|
| `pa_bo-suu-tap` | `dravit-signature` | Dravit Signature | `/bo-suu-tap/dravit-signature` |
| `pa_bo-suu-tap` | `dravit-luxury` | Dravit Luxury | `/bo-suu-tap/dravit-luxury` |
| `pa_bo-suu-tap` | `dravit-eco` | Dravit Eco | `/bo-suu-tap/dravit-eco` |
| `pa_xuat-xu` | `viet-nam` | Việt Nam | `/xuat-xu/viet-nam` |
| `pa_xuat-xu` | `an-do` | Ấn Độ | `/xuat-xu/an-do` |
| `pa_xuat-xu` | `tay-ban-nha` | Tây Ban Nha | `/xuat-xu/tay-ban-nha` |
| `pa_xuat-xu` | `y` | Ý (Italy) | `/xuat-xu/y` |

#### F. `pa_mau-sac` (Param: `mau-sac` · `FLT-06` · Nhóm `seo` · UI Swatch)
| Thứ tự | Slug | `name` | `drv_filter_label` | `drv_swatch_hex` | Ghi chú hiển thị |
|---:|---|---|---|---|---|
| 1 | `trang` | Trắng | Trắng / Trắng khói | `#FBF9F5` | Viền mảnh `1px solid #CBD5E1` để nổi trên nền sáng |
| 2 | `kem` | Kem | Kem đá / Ngà (Ivory) | `#EDE6D8` | Tông ấm chủ đạo |
| 3 | `xam` | Xám | Xám / Ghi bê tông | `#9CA3AF` | Trung tính hiện đại |
| 4 | `vang` | Vàng | Vàng kem / Cát | `#E6C78B` | Vân đá Hoàng gia / cổ điển |
| 5 | `nau` | Nâu | Nâu gỗ / Đất nung | `#8B5E3C` | Dùng chung cho giả gỗ & nâu đất |
| 6 | `den` | Đen | Đen than / Xám đậm | `#1F2937` | Đá tối màu |
| 7 | `xanh-la` | Xanh lá | Xanh rêu / Xanh ngọc | `#2F6F5E` | Gạch thẻ, gạch bông |
| 8 | `xanh-duong` | Xanh dương | Xanh biển / Cobalt | `#2563EB` | Hồ bơi, trang trí |
| 9 | `hong` | Hồng | Hồng phấn / Terracotta nhạt | `#E5A9A9` | Trang trí điểm nhấn |
| 10 | `da-sac` | Đa sắc | Đa sắc (Terrazzo / Hoa văn) | `multi` | CSS render `conic-gradient(#EDE6D8, #9CA3AF, #8B5E3C, #2F6F5E)` |

#### G. `pa_be-mat` (Param: `be-mat` · `FLT-07` · Nhóm `seo`)
| Thứ tự | Slug | `name` | `drv_filter_label` | `drv_short_label` |
|---:|---|---|---|---|
| 1 | `bong-kieng` | Bóng kiếng | Bóng kiếng toàn phần (Polished) | Bóng kiếng |
| 2 | `mo` | Mờ (Matt) | Mờ mịn chống chói (Matt) | Matt |
| 3 | `ban-bong` | Bán bóng | Bán bóng ánh lụa (Lappato) | Bán bóng |
| 4 | `nham` | Nhám sần | Nhám sần chống trượt (Structured) | Nhám sần |
| 5 | `carving` | Carving | Rãnh khắc chìm theo vân (Carving) | Carving |
| 6 | `sugar` | Hiệu ứng Sugar | Hạt tinh thể lấp lánh (Sugar) | Sugar |

#### H. `pa_phong-cach` (Param: `phong-cach` · `FLT-08` · Nhóm `seo`)
| Thứ tự | Slug | `name` | `drv_filter_label` |
|---:|---|---|---|
| 1 | `hien-dai` | Hiện đại | Hiện đại (Modern) |
| 2 | `toi-gian` | Tối giản | Tối giản (Minimalism) |
| 3 | `wabi-sabi` | Wabi-Sabi | Mộc tự nhiên (Wabi-Sabi) |
| 4 | `japandi` | Japandi | Bắc Âu pha Á Đông (Japandi) |
| 5 | `indochine` | Indochine | Đông Dương (Indochine) |
| 6 | `tan-co-dien` | Tân cổ điển | Tân cổ điển sang trọng |

#### I. `pa_hinh-dang` (Param: `hinh-dang` · `FLT-09` · Nhóm `seo` · UI Icon + Nhãn)
| Thứ tự | Slug | `name` | `drv_icon` (Phosphor Subset) |
|---:|---|---|---|
| 1 | `vuong` | Hình vuông | `ph-square` |
| 2 | `chu-nhat` | Hình chữ nhật | `ph-rectangle` |
| 3 | `luc-giac` | Lục giác | `ph-hexagon` |
| 4 | `vay-ca` | Vảy cá | `ph-drop` |
| 5 | `long-vu` | Lông vũ | `ph-feather` |
| 6 | `den-long` | Đèn lồng | `ph-diamond` |
| 7 | `ziczac` | Ziczac / Chevron | `ph-arrows-left-right` |

#### J. Nhóm Facet Kỹ thuật & Tiện ích (`FLT-10..14` · Nhóm `tech` · Không bao giờ render `<a href>`)

| Taxonomy / Nguồn | Param | Thứ tự | Slug | `name` | `drv_filter_label` | `drv_short_label` | Quy tắc gán |
|---|---|---:|---|---|---|---|---|
| `pa_dcof` (`FLT-10`) | `dcof` | 1 | `r9` | R9 | R9: Khu vực khô ráo (phòng khách, phòng ngủ) | R9 | Nhập tay |
| `pa_dcof` (`FLT-10`) | `dcof` | 2 | `r10` | R10 | R10: Bếp, nhà tắm, an toàn gia đình | R10 | Nhập tay |
| `pa_dcof` (`FLT-10`) | `dcof` | 3 | `r11` | R11 | R11: Sân ướt, ban công, hiên ngoài trời | R11 | Nhập tay |
| `pa_dcof` (`FLT-10`) | `dcof` | 4 | `r12` | R12 | R12: Lối dốc gara, khu chế biến ướt | R12 | Nhập tay |
| `pa_dcof` (`FLT-10`) | `dcof` | 5 | `r13` | R13 | R13: Bậc hồ bơi, dốc nghiêng độ bám cao | R13 | Nhập tay |
| `pa_do-hut-nuoc` (`FLT-11`) | `do-hut-nuoc` | 1 | `duoi-0-5` | ≤ 0,5% | E ≤ 0,5% (Xương đá Porcelain đặc chắc) | E ≤ 0,5% | Tự sinh `≤ 0.5` |
| `pa_do-hut-nuoc` (`FLT-11`) | `do-hut-nuoc` | 2 | `0-5-3` | 0,5% - 3% | 0,5% < E ≤ 3% (Bán sứ ít thấm) | 0,5 - 3% | Tự sinh `0.5 < E ≤ 3` |
| `pa_do-hut-nuoc` (`FLT-11`) | `do-hut-nuoc` | 3 | `3-6` | 3% - 6% | 3% < E ≤ 6% (Hút nước trung bình) | 3 - 6% | Tự sinh `3 < E ≤ 6` |
| `pa_do-hut-nuoc` (`FLT-11`) | `do-hut-nuoc` | 4 | `6-10` | 6% - 10% | 6% < E ≤ 10% (Ốp tường trong nhà) | 6 - 10% | Tự sinh `6 < E ≤ 10` |
| `pa_do-hut-nuoc` (`FLT-11`) | `do-hut-nuoc` | 5 | `tren-10` | > 10% | E > 10% (Men Ceramic ốp tường nhẹ) | E > 10% | Tự sinh `> 10` |
| `pa_do-day` (`FLT-12`) | `do-day` | 1 | `mong` | Mỏng (≤ 6 mm) | Mỏng ≤ 6 mm (Tấm ốp nhẹ tải) | ≤ 6 mm | Tự sinh `≤ 6.0` |
| `pa_do-day` (`FLT-12`) | `do-day` | 2 | `tieu-chuan` | Tiêu chuẩn (6,1 - 11,9 mm) | Tiêu chuẩn 8,5 - 11 mm (Lát nền & ốp tường) | 8,5 - 11 mm | Tự sinh `6.0 < T < 12.0` |
| `pa_do-day` (`FLT-12`) | `do-day` | 3 | `day` | Dày chịu lực (≥ 12 mm) | Dày ≥ 12 mm (Chịu tải ô tô, sân vườn) | ≥ 12 mm | Tự sinh `≥ 12.0` |
| `drv_price_band` (`FLT-13`) | `gia` | 1 | `duoi-200k` | Dưới 200.000đ/m² | Dưới 200.000đ/m² | < 200k | Tự sinh `1..199999` |
| `drv_price_band` (`FLT-13`) | `gia` | 2 | `200k-350k` | 200.000đ - 350.000đ/m² | 200.000đ - 350.000đ/m² | 200 - 350k | Tự sinh `200000..350000` |
| `drv_price_band` (`FLT-13`) | `gia` | 3 | `350k-600k` | 350.000đ - 600.000đ/m² | 350.000đ - 600.000đ/m² | 350 - 600k | Tự sinh `350001..600000` |
| `drv_price_band` (`FLT-13`) | `gia` | 4 | `tren-600k` | Trên 600.000đ/m² | Trên 600.000đ/m² | > 600k | Tự sinh `≥ 600001` |
| `drv_price_band` (`FLT-13`) | `gia` | 5 | `gia-du-an` | Liên hệ báo giá | Hàng dự án / Liên hệ báo giá | Báo giá | Tự sinh `drv_price_m2 <= 0` |
| `drv_flag` (`FLT-14`) | `showroom` | 1 | `showroom` | Có mẫu tại Showroom | Có mẫu thực tế tại kệ Showroom | Mẫu tại kệ | Tự sinh `drv_showroom_shelf !== ''` |
| Orderby (`FLT-14`) | `sort` | - | `moi-ve` \| `ban-chay` \| `gia-tang` \| `gia-giam` | Mới về / Bán chạy / Giá thấp đến cao / Giá cao đến thấp | Select sắp xếp (mặc định không ghi `sort` lên URL) | - | Query param |

---

## 5. Post Meta (Blog Cẩm nang), Nav Menu Item Meta & User Meta (E-E-A-T)

### 5.1 Post Meta (`post_type = 'post'` - Cẩm nang `/cam-nang/{slug}`)

Đăng ký qua `Dravit\Content\PostMeta` (Metabox trong Gutenberg / Classic).

| Meta key | Kiểu PHP / Lưu DB | Mặc định | Validate / Sanitize | Nơi sử dụng |
|---|---|---|---|---|
| `drv_is_pillar` | `string` (`'1'\|'0'`) | `'0'` | `'1'` hoặc `'0'` | Ghim vào lưới Bento Pillar 5/4/3 đầu trang `/cam-nang` |
| `drv_reading_time` | `int` (phút) | Tự tính | `max(1, (int) ceil($word_count / 220))` tính tự động trên `save_post_post` | Header bài viết, thẻ bài viết Cẩm nang |
| `drv_toc_enabled` | `string` (`'1'\|'0'`) | `'1'` | `'1'` hoặc `'0'` | Bật/tắt khung Mục lục tự động `<drv-toc>` (ẩn nếu bài `< 3` thẻ `h2`) |
| `drv_key_takeaways` | `string[]` (JSON) | `'[]'` | Mảng 3 - 5 chuỗi, mỗi dòng ≤ 180 ký tự (`sanitize_text_field`) | Hộp *Tóm tắt nhanh cho gia chủ* đầu bài viết (chuẩn GEO) |
| `drv_related_collections` | `int[]` (JSON `term_id[]`) | `'[]'` | Tối đa 4 `term_id` thuộc `product_cat` | Khối điều hướng Silo thương mại `IL-05` cuối bài |
| `drv_related_posts` | `int[]` (JSON `post_id[]`) | `'[]'` | Tối đa 4 `post_id` bài viết khác; trống -> tự lấy cùng `category` | Khối bài viết liên quan cuối bài |
| `drv_reviewer_id` | `int` (`user_id`) | `0` | `absint` ID user tồn tại (khác `post_author` nếu có kỹ sư duyệt riêng) | Hộp *Thẩm định kỹ thuật* + Schema `BlogPosting.reviewedBy` (`E-E-A-T`) |
| `drv_reviewed_at` | `string` (`YYYY-MM-DD`) | `''` | Regex `^\d{4}-\d{2}-\d{2}$` | Hiển thị ngày kiểm duyệt kỹ thuật gần nhất |
| `drv_ai_prompts` | `string[]` (JSON) | `'[]'` | Tối đa 3 câu gợi ý (`sanitize_text_field`) | Dự phòng cho thẻ hỏi tiếp Trợ lý AI ở Phase 3 (ẩn khi `ai_enabled = false`) |

### 5.2 Nav Menu Item Meta (`post_type = 'nav_menu_item'`)

Dùng cho menu phụ Header Tier 2 bên phải và Footer (`wp_nav_menu_item_custom_fields`):

| Meta key | Kiểu | Validate / Sanitize | Nơi sử dụng |
|---|---|---|---|
| `_drv_icon` | `string` | Regex `^ph(-fill|-bold)?\s+ph-[a-z0-9-]+$` hoặc tên ngắn `ph-phone` | Render `<i class="ph {...}" aria-hidden="true"></i>` trước nhãn menu |
| `_drv_badge` | `string` | `sanitize_text_field`, tối đa 15 ký tự | Nhãn nhỏ cạnh mục menu (ví dụ `"Mới"`, `"BOQ"`) |

### 5.3 User Meta (`wp_usermeta` - Hồ sơ Tác giả & Kỹ sư Thẩm định E-E-A-T)

Chỉ dùng nhân sự thật từ chủ dự án cung cấp (`O-13`), tuyệt đối không tạo hồ sơ giả:

| Meta key | Kiểu PHP / Lưu DB | Validate / Sanitize | Nơi sử dụng |
|---|---|---|---|
| `drv_job_title` | `string` | `sanitize_text_field`, ≤ 80 ký tự (vd `"Kỹ sư Vật liệu Ốp lát"`) | Hộp tác giả/người duyệt cuối bài + Schema `Person.jobTitle` |
| `drv_credentials` | `string` | `sanitize_text_field`, ≤ 200 ký tự (kinh nghiệm, chứng chỉ thực tế) | Dòng mô tả năng lực chuyên môn dưới tên nhân sự |
| `drv_avatar_id` | `int` (attachment ID) | `absint` ảnh thật trên host (thay thế Gravatar ngoài để giữ tốc độ & quyền riêng tư) | Ảnh đại diện tác giả/kỹ sư thẩm định |
| `drv_same_as` | `string[]` (JSON) | Mảng URL hợp lệ (`esc_url_raw`, tối đa 5 link LinkedIn/hồ sơ nghề nghiệp) | Thuộc tính `sameAs` của thực thể `Person` trong JSON-LD |

---

## 6. CPT `drv_lead` Schema - Báo giá BOQ & Yêu cầu Tư vấn (`D-23`)

### 6.1 Tham số Đăng ký `register_post_type('drv_lead', ...)`

Đăng ký trong `Dravit\Leads\LeadPostType`:
- `public => false`, `publicly_queryable => false`, `exclude_from_search => true`, `rewrite => false`, `query_var => false`, `show_in_rest => false`, `has_archive => false`.
- `show_ui => true`, `show_in_menu => 'woocommerce'` (menu con **"Báo giá & Lead"** ngay dưới WooCommerce, kèm bubble đếm số lead `_drv_status = 'new'`).
- `supports => ['title']` (tiêu đề dạng `[BG-20261111-4821] Nguyễn Thanh Tùng - 0909385880`).
- `capabilities`: ánh xạ toàn bộ (`edit_post`, `read_post`, `delete_post`, `edit_posts`, `edit_others_posts`, `publish_posts`, `read_private_posts`) về quyền **`manage_woocommerce`**.
- `post_status`: cố định `'private'`; trạng thái nghiệp vụ quản lý qua `_drv_status` để lọc nhanh và không làm phình bảng trạng thái toàn cục của WP.

### 6.2 Enum Nguồn Lead (`_drv_lead_type`) & Quy trình Trạng thái (`_drv_status`)

| Giá trị `_drv_lead_type` | Nguồn gửi trên Website | Endpoint xử lý |
|---|---|---|
| `'boq'` | Bảng Dự toán & Danh sách Báo giá công trình theo phòng (`/danh-sach-bao-gia`) | `POST /wp-json/dravit/v1/quote/submit` |
| `'quote_modal'` | Dialog *"Nhận báo giá nhanh"* trên PDP (SP `quote_only` hoặc mua số lượng lớn) & trang Danh mục | `POST /wp-json/dravit/v1/lead` |
| `'contact'` | Form liên hệ & tư vấn kỹ thuật tại `/lien-he` | `POST /wp-json/dravit/v1/lead` |
| `'sample'` | Yêu cầu gửi mẫu gạch thực tế đối chiếu tại công trình | `POST /wp-json/dravit/v1/lead` |
| `'showroom_visit'` | Đặt lịch hẹn xem mẫu trực tiếp tại Showroom (chuẩn bị sẵn mã kệ) | `POST /wp-json/dravit/v1/lead` |
| `'blog_inline'` | Form nhận tư vấn nhúng giữa/cuối bài viết Cẩm nang (`[drv_lead_form]`) | `POST /wp-json/dravit/v1/lead` |
| `'ai_handoff'` | Chuyển tiếp hội thoại sang chuyên viên từ Trợ lý AI (Phase 3) | `POST /wp-json/dravit/v1/lead` |

**Luồng `_drv_status`:**
`new` (*Mới tiếp nhận*) -> `contacted` (*Đã liên hệ*) -> `quoted` (*Đã gửi báo giá*) -> `won` (*Đã chốt đơn* - tự chuyển khi bấm Admin Action *"Tạo đơn hàng từ báo giá"*) hoặc `lost` (*Không chốt*).

### 6.3 Bảng Meta của CPT `drv_lead`

| Meta key | Kiểu PHP / Lưu DB | Bắt buộc? | Validate / Cấu trúc dữ liệu |
|---|---|---|---|
| `_drv_lead_type` | `string` | Có | Enum 7 giá trị ở §6.2 |
| `_drv_status` | `string` | Có | `'new' \| 'contacted' \| 'quoted' \| 'won' \| 'lost'` (mặc định `'new'`) |
| `_drv_quote_code` | `string` | Có | Mã tra cứu hiển thị cho khách & sale: `BG-{Ymd}-{4 chữ số}` cho `boq`, `LD-{Ymd}-{4 chữ số}` cho các loại khác |
| `_drv_name` | `string` | Có | `sanitize_text_field`, 2 - 80 ký tự |
| `_drv_phone` | `string` | Có | Chuẩn hóa về `0xxxxxxxxx` (10 số), khớp regex `^(0|\+84)(3|5|7|8|9)\d{8}$` |
| `_drv_email` | `string` | Không | `sanitize_email` |
| `_drv_province` | `string` | Tùy form | Mã tỉnh trong `config/provinces-vn.php` (vd `'HCM'`, `'DNA'`) |
| `_drv_ward` | `string` | Không | `sanitize_text_field` tên Phường/Xã |
| `_drv_role` | `string` | Không | `'chu-nha' \| 'nha-thau' \| 'kts' \| 'dai-ly'` (mặc định `'chu-nha'`) |
| `_drv_project` | `string` | Không | Tên/loại công trình, ≤ 120 ký tự |
| `_drv_area_m2` | `float` | Không | Diện tích dự kiến (dùng cho form đơn không qua BOQ) |
| `_drv_message` | `string` | Không | `sanitize_textarea_field`, ≤ 1.000 ký tự |
| `_drv_product_id` | `int` | Không | `absint` ID sản phẩm chính khi gửi từ PDP (`quote_modal` / `sample`) |
| `_drv_post_id` | `int` | Không | `absint` ID bài viết Cẩm nang khi gửi từ `blog_inline` |
| `_drv_boq` | `string` (JSON) | Khi `type=boq` | Snapshot đầy đủ do **Server tính lại 100%** qua `Dravit\Units\Calc`: `{waste_pct:int, rooms:[{name:string, slots:[{role, product_id, sku, name, price_state, target_m2, pieces, boxes, actual_m2, kg, price_m2, price_piece, line_total}]}]}` |
| `_drv_total_m2` | `float` | Khi `type=boq` | Tổng diện tích thực nhận toàn phiếu BOQ (m²) |
| `_drv_total_boxes` | `int` | Khi `type=boq` | Tổng số thùng toàn phiếu BOQ |
| `_drv_total_kg` | `float` | Khi `type=boq` | Tổng khối lượng dự kiến (kg) |
| `_drv_subtotal_vnd` | `int` | Khi `type=boq` | Tạm tính của các dòng có giá (`priced`/`sale`) trước chiết khấu |
| `_drv_discount_rate` | `float` (%) | Khi `type=boq` | Mức % chiết khấu dự kiến theo bậc `drv_settings.quote.volume_tiers` (`0`, `5`, `8`) |
| `_drv_discount_est_vnd` | `int` | Khi `type=boq` | Số tiền chiết khấu dự kiến (`round(subtotal * rate / 100)`) |
| `_drv_grand_est_vnd` | `int` | Khi `type=boq` | Tổng tạm tính sau chiết khấu dự kiến (chưa gồm SP `quote_only` và phí vận chuyển) |
| `_drv_has_quote_items` | `string` (`'1'\|'0'`) | Khi `type=boq` | `'1'` nếu phiếu BOQ có ít nhất 1 dòng thuộc trạng thái `quote_only` |
| `_drv_page_url` | `string` | Có | `esc_url_raw` trang gốc lúc khách gửi form |
| `_drv_utm` | `string` (JSON) | Không | `{utm_source, utm_medium, utm_campaign, utm_term, gclid, fbclid}` |
| `_drv_ip_hash` | `string` | Có | `hash_hmac('sha256', $client_ip, wp_salt('auth'))` (băm một chiều phục vụ chống spam, không lưu IP thô lâu dài) |
| `_drv_consent_at` | `string` (ISO 8601) | Có | Thời điểm khách xác nhận đồng ý xử lý dữ liệu liên hệ (NĐ 13/2023/NĐ-CP) |
| `_drv_admin_note` | `string` | Không | Ghi chú nội bộ của nhân viên kinh doanh trong Admin |
| `_drv_converted_order_id` | `int` | Không | ID đơn hàng HPOS tạo ra từ nút *"Tạo đơn hàng từ báo giá"*, chống tạo trùng |
| `_drv_converted_at` | `string` (ISO 8601) | Không | Thời điểm chuyển đổi thành đơn hàng |

---

## 7. WooCommerce Cart Item, HPOS Order & Order Item Meta (`D-22`, `D-30`)

### 7.1 Cart Item Data (Trong Session Giỏ hàng WooCommerce)

Truyền qua filter `woocommerce_add_cart_item_data` khi thêm sản phẩm vào giỏ:

| Key | Kiểu | Mặc định | Ý nghĩa |
|---|---|---|---|
| `drv_m2_input` | `float` | `0.0` | Diện tích m² thuần khách đã nhập trong `<drv-unit-calc>` trước khi quy đổi ra viên (`0` nếu khách nhập trực tiếp số thùng/viên). |
| `drv_waste_pct` | `int` | `5` | Tỷ lệ hao hụt `%` khách đã chọn (`0`, `5`, `10`). |
| `drv_input_unit` | `string` | `'m2'` | Đơn vị tab khách đang đứng lúc bấm thêm (`'m2' \| 'thung' \| 'vien'`). |

> Lưu ý: Cùng một `product_id` trong giỏ hàng được gộp thành **1 dòng duy nhất** (không tách dòng theo `drv_m2_input` để tránh lộn xộn tồn kho); khi cộng dồn số lượng viên, cập nhật lại `drv_m2_input` tương ứng hoặc xóa về `0` nếu khách chỉnh trực tiếp bằng stepper trong giỏ.

### 7.2 Trạng thái Đơn hàng HPOS & Order Meta (`wp_wc_orders_meta`)

**Bảng Slug Trạng thái Đơn chuẩn (`≤ 20` ký tự tính cả `wc-`):**

| Slug DB (`wp_wc_orders.status`) | Độ dài | Nhãn hiển thị tiếng Việt | Nguồn | Hành vi Tồn kho & VietQR |
|---|---:|---|---|---|
| `wc-cho-xac-nhan` | 15 | Chờ xác nhận | Custom (`OrderFlow`) | Trạng thái khởi tạo bắt buộc cho cả COD & BACS. **Trừ tồn kho ngay** (`wc_maybe_reduce_stock_levels`). **Không** render VietQR. |
| `wc-on-hold` | 10 | Chờ chuyển khoản | Native WC (đổi nhãn) | Chuyển sang khi Admin nhập phí ship + bấm *"Xác nhận đơn & gửi email"* trên đơn BACS. **Được phép render VietQR**. |
| `wc-processing` | 13 | Đang xử lý | Native WC | Chuyển sang sau khi xác nhận đơn COD, hoặc sau khi kế toán xác nhận đã nhận tiền đơn BACS. |
| `wc-completed` | 12 | Hoàn thành | Native WC | Đã giao hàng và nghiệm thu tại công trình. |
| `wc-cancelled` | 12 | Đã hủy | Native WC | Tự động hoàn lại tồn kho viên (`wc_maybe_increase_stock_levels`). |
| `wc-refunded` | 11 | Đã hoàn tiền | Native WC | Xử lý đổi trả / hoàn tiền. |

**Bảng Meta cấp Đơn hàng (chỉ thao tác qua `$order->get_meta()` / `$order->update_meta_data()`):**

| Meta key | Kiểu PHP | Khi ghi | Validate / Ý nghĩa |
|---|---|---|---|
| `_drv_ward_code` | `string` | Lúc đặt hàng (`CheckoutVN`) | Mã Phường/Xã chuẩn từ `wards/{province}.json` (tên Phường/Xã lưu đồng thời vào `billing_city` để địa chỉ WC hiển thị tự nhiên). |
| `_drv_vat_required` | `string` (`'1'\|'0'`) | Lúc đặt hàng | `'1'` nếu khách bật checkbox *"Yêu cầu xuất hóa đơn GTGT (VAT)"*. |
| `_drv_vat_company` | `string` | Lúc đặt hàng (khi VAT = `'1'`) | Tên đơn vị / công ty trên hóa đơn (bắt buộc khi `_drv_vat_required === '1'`, ≤ 200 ký tự). |
| `_drv_vat_tax_id` | `string` | Lúc đặt hàng (khi VAT = `'1'`) | Mã số thuế doanh nghiệp, bắt buộc khớp regex `^\d{10}(-\d{3})?$`. |
| `_drv_vat_address` | `string` | Lúc đặt hàng (khi VAT = `'1'`) | Địa chỉ đăng ký kinh doanh ghi trên hóa đơn (bắt buộc khi VAT = `'1'`, ≤ 250 ký tự). |
| `_drv_vat_email` | `string` | Lúc đặt hàng (tùy chọn) | `sanitize_email` nhận hóa đơn điện tử (nếu trống dùng `billing_email`). |
| `_drv_total_m2` | `float` | Lúc tạo đơn & khi sửa dòng đơn | Tổng diện tích m² của toàn bộ các dòng gạch trong đơn (`round(sum, 2)`). |
| `_drv_total_boxes` | `int` | Lúc tạo đơn & khi sửa dòng đơn | Tổng số thùng nguyên trong đơn. |
| `_drv_total_kg` | `float` | Lúc tạo đơn & khi sửa dòng đơn | Tổng khối lượng hàng hóa ước tính (kg) để điều phối xe tải. |
| `_drv_transfer_ref` | `string` | Lúc tạo đơn | Nội dung chuyển khoản VietQR chuẩn ngân hàng: `'DRV' . $order->get_id()` (chỉ chữ cái in hoa và chữ số, không dấu gạch ngang; mã đơn hiển thị UI là `DRV-{id}`). |
| `_drv_ship_vehicle` | `string` | Admin Metabox Vận hành đơn | Loại phương tiện giao hàng nhân viên chốt (vd `'xe_1t'`, `'xe_2t5'`, `'xe_5t'`, `'chanh_xe'`, `'khach_tu_lay'`). |
| `_drv_ship_note` | `string` | Admin Metabox Vận hành đơn | Ghi chú giao nhận/hạ hàng hiển thị trong email xác nhận gửi khách (vd `"Xe tải 1,5 tấn giao chân công trình sáng Thứ 5"`). |
| `_drv_confirmed_at` | `string` (ISO 8601) | Khi chạy Action *"Xác nhận đơn"* | Thời điểm nhân viên chốt phí vận chuyển và gửi email xác nhận. |
| `_drv_confirmed_by` | `int` (`user_id`) | Khi chạy Action *"Xác nhận đơn"* | ID tài khoản nhân viên xác nhận đơn. |
| `_drv_paid_at` | `string` (ISO 8601) | Khi bấm *"Đã nhận chuyển khoản"* | Thời điểm kế toán xác nhận tiền về tài khoản BACS. |
| `_drv_source_lead_id` | `int` | Khi tạo đơn từ `drv_lead` | Liên kết ngược về ID bản ghi `drv_lead` gốc. |

### 7.3 Order Line Item Snapshot Meta (`woocommerce_checkout_create_order_line_item`)

Đóng băng toàn bộ thông số quy đổi tại thời điểm đặt hàng để đơn cũ không bao giờ bị sai lệch khi sản phẩm đổi giá hoặc đổi quy cách thùng sau này:

| Meta key | Kiểu | Nhãn hiển thị (`formatted_meta_data`) | Công thức / Giá trị Snapshot |
|---|---|---|---|
| `_drv_model` | `string` | *(ẩn)* | `drv_pricing_model` (ví dụ `'area_pack'`) |
| `_drv_unit` | `string` | *(ẩn)* | `drv_unit_label` (`'viên'` hoặc `'tấm'`) |
| `_drv_price_m2` | `int` | `Đơn giá / m²` -> `385.000đ/m²` | Giá hiệu lực / m² tại thời điểm mua (`sale_price_m2` nếu đang KM, ngược lại `price_m2`) |
| `_drv_area_piece` | `float` | *(ẩn)* | Diện tích 1 viên (m²), vd `0.64` |
| `_drv_pieces_per_box` | `int` | *(ẩn)* | Số viên / thùng tại thời điểm đặt, vd `3` |
| `_drv_kg_per_box` | `float` | *(ẩn)* | Khối lượng 1 thùng (kg), vd `42.0` |
| `_drv_m2_qty` | `float` | `Diện tích tương đương` -> `30,72 m²` | `round($qty_vien * $area_piece, 4)` |
| `_drv_boxes` | `int` | `Quy cách đóng gói` -> `16 thùng` (hoặc `15 thùng + 1 viên lẻ`) | `intdiv($qty_vien, $pieces_per_box)` (kết hợp `_drv_loose_pieces` khi format) |
| `_drv_loose_pieces` | `int` | *(gộp vào dòng Quy cách đóng gói)* | `$qty_vien % $pieces_per_box` |
| `_drv_kg` | `float` | `Khối lượng ước tính` -> `672 kg` | `round($qty_vien * ($kg_per_box / $pieces_per_box), 1)` |
| `_drv_m2_input` | `float` | *(chỉ hiện trong Admin nếu `> 0`)* | Diện tích khách nhập ban đầu từ giỏ (`drv_m2_input`) |
| `_drv_waste_pct` | `int` | *(chỉ hiện trong Admin)* | `%` hao hụt khách đã chọn (`0`, `5`, `10`) |
| `_drv_lot_code` | `string` | `Mã lô xuất kho` -> `L2604-B2` (chỉ hiện khi khác rỗng) | Nhân viên kho nhập trong Admin đơn hàng trước khi giao để tra cứu bảo hành đồng màu (`D-30`) |

---

## 8. Option `drv_settings` Schema & Settings API Tabs

Lưu trong **1 option duy nhất** `drv_settings` (`autoload = 'yes'`), quản lý tại trang Admin **WooCommerce -> Cài đặt Dravit** (`Dravit\Core\Settings`, yêu cầu quyền `manage_woocommerce`).

### 8.1 Phân bổ 9 Tab Admin Settings API

| Tab slug | Nhãn Tab trên UI Admin | Các khóa con trong mảng `drv_settings` |
|---|---|---|
| `contact` | `1. Liên hệ & Pháp nhân` | `contact`, `company`, `social` |
| `showrooms` | `2. Showroom & Kho` | `showrooms` |
| `bank` | `3. Ngân hàng VietQR` | `bank` |
| `order` | `4. Đơn hàng & Vận chuyển` | `order` |
| `quote` | `5. Báo giá BOQ` | `quote` |
| `home` | `6. Trang chủ` | `home` |
| `catalog` | `7. Danh mục & Sản phẩm` | `catalog`, `pdp`, `collections_404` |
| `content` | `8. Cẩm nang & Tìm kiếm` | `blog`, `search` |
| `features` | `9. Tính năng & SEO/GEO` | `features`, `seo` |

### 8.2 Cấu trúc Mảng PHP Mặc định & Quy tắc Sanitize (`Dravit\Core\Settings::defaults()`)

```php
<?php
return [
    // TAB 1: LIÊN HỆ & PHÁP NHÂN
    'contact' => [
        'hotline_display' => '0909 000 000',          // sanitize_text_field (hiển thị UI, cần O-03)
        'hotline_tel'     => '0909000000',            // preg_replace('/[^\d+]/', '') (dùng cho href="tel:")
        'zalo_phone'      => '0909000000',            // số Zalo nhận tin nhắn
        'zalo_url'        => 'https://zalo.me/0909000000', // esc_url_raw
        'email_sales'     => 'kinhdoanh@gachdravit.vn',    // sanitize_email (nhận thông báo đơn & lead)
        'email_support'   => 'cskh@gachdravit.vn',         // sanitize_email (hiển thị footer/liên hệ)
        'working_hours'   => '08:00 - 21:00 (Thứ 2 - Chủ nhật)', // sanitize_text_field
        'open_hour'       => 8,                       // int 0..23 (tính badge Đang mở cửa theo Asia/Ho_Chi_Minh)
        'close_hour'      => 21,                      // int 1..24
        'sla_response'    => 'Phản hồi trong 30 phút (giờ làm việc 08:00 - 21:00)', // O-11, dùng thống nhất toàn site
    ],
    'company' => [
        'legal_name'      => 'CÔNG TY TNHH DRAVIT',   // sanitize_text_field (O-09)
        'brand_name'      => 'DRAVIT',                // sanitize_text_field
        'tax_id'          => '',                      // regex ^\d{10}(-\d{3})?$
        'reg_address'     => '',                      // địa chỉ pháp lý theo đơn vị hành chính 2 cấp mới
        'bct_badge_url'   => '',                      // esc_url_raw link Bộ Công Thương (chỉ render icon khi khác rỗng, O-14)
    ],
    'social' => [
        'facebook'        => '',                      // esc_url_raw
        'youtube'         => '',                      // esc_url_raw
        'tiktok'          => '',                      // esc_url_raw
        'pinterest'       => '',                      // esc_url_raw
        'google_maps_cid' => '',                      // esc_url_raw Google Business Profile
    ],

    // TAB 2: SHOWROOM & KHO (Repeater tối đa 6 địa điểm)
    'showrooms' => [
        [
            'id'           => 'sr-hcm-main',          // sanitize_key
            'type'         => 'showroom',             // 'showroom' | 'warehouse'
            'name'         => 'Showroom DRAVIT TP. Hồ Chí Minh',
            'address_line' => '',                     // Số nhà, tên đường (O-03/O-09)
            'ward'         => '',                     // Phường/Xã mới
            'province'     => 'HCM',                  // Mã tỉnh trong config/provinces-vn.php
            'phone'        => '',                     // SĐT riêng tại điểm (trống -> dùng hotline)
            'hours_text'   => '08:00 - 21:00 hàng ngày',
            'lat'          => 0.0,                    // float -90..90 (chỉ xuất geo Schema khi != 0)
            'lng'          => 0.0,                    // float -180..180
            'maps_url'     => '',                     // esc_url_raw link chỉ đường Google Maps
            'parking_note' => 'Có chỗ đậu ô tô miễn phí trước sảnh',
            'cover_id'     => 0,                      // absint attachment ID
            'features'     => ['Kệ mẫu thực tế khổ lớn', 'Hệ đèn đối chiếu 3000K / 6500K', 'Khu thử nước chống trượt R'],
            'zones'        => [                       // Dữ liệu cho <drv-showroom>
                // { key: 'slabs', label: 'Khu Big Slab', items: [{ cool_id: int, warm_id: int, caption: string }] }
            ],
        ],
    ],

    // TAB 3: NGÂN HÀNG VIETQR (O-02)
    'bank' => [
        'enabled'       => true,                      // bool
        'bank_name'     => '',                        // Tên ngắn ngân hàng, vd 'MB Bank'
        'bank_bin'      => '',                        // 6 chữ số NAPAS BIN, vd '970422' (regex ^\d{6}$)
        'account_no'    => '',                        // preg_replace('/[^A-Za-z0-9]/', '')
        'account_name'  => '',                        // IN HOA KHÔNG DẤU, vd 'CONG TY TNHH DRAVIT'
        'branch'        => '',                        // Chi nhánh (tùy chọn)
        'qr_template'   => 'compact2',                // 'compact2' | 'compact' | 'qr_only'
    ],

    // TAB 4: ĐƠN HÀNG, ĐƠN VỊ MUA MẶC ĐỊNH & VẬN CHUYỂN
    'order' => [
        'default_buy_units'    => ['m2', 'thung'],    // Mặc định khi tạo SP mới (D-24)
        'default_buy_unit'     => 'm2',               // 'm2' | 'thung' | 'vien'
        'default_round_mode'   => 'box',              // 'box' | 'piece'
        'waste_default'        => 5,                  // int: 0 | 5 | 10 (%)
        'waste_options'        => [0, 5, 10],         // int[] các mức hao hụt trên <drv-unit-calc> & BOQ
        'transfer_due_days'    => 3,                  // int 1..14: số ngày giữ đơn chờ chuyển khoản (wc-on-hold)
        'auto_cancel_enabled'  => true,               // bool: tự hủy đơn on-hold quá hạn qua Action Scheduler
        'shipping_label'       => 'Báo sau khi xác nhận đơn', // Nhãn thay thế 0đ cho drv_quote_later
        'checkout_ship_notice' => 'Nhân viên Dravit sẽ gọi xác nhận tồn kho cùng mã lô và báo phí vận chuyển tối ưu trước khi giao.',
        'vehicle_thresholds'   => [                   // Ngưỡng gợi ý xe tải thống nhất toàn site (kg)
            ['max_kg' => 1000, 'label' => 'Xe tải nhẹ 1 tấn'],
            ['max_kg' => 2500, 'label' => 'Xe tải 2,5 tấn'],
            ['max_kg' => 5000, 'label' => 'Xe tải 5 tấn'],
            ['max_kg' => 999999, 'label' => 'Xe tải 8 tấn / chia chuyến'],
        ],
        'shipping_zone_hints'  => [                   // Gợi ý thời gian giao theo mã tỉnh trên Checkout
            'inner'   => ['provinces' => ['HCM'], 'text' => 'Nội thành TP.HCM: Xe tải nhẹ giao tận chân công trình (24h)'],
            'nearby'  => ['provinces' => ['DNA', 'BDG', 'VLO', 'DTH', 'LDG'], 'text' => 'Tuyến lân cận: Xe tải nguyên chuyến hoặc ghép chuyến (24 - 48h)'],
            'default' => ['text' => 'Tuyến liên tỉnh: Điều phối xe tải / chành xe uy tín (2 - 4 ngày)'],
        ],
        'note_chips'           => [                   // Chip chèn nhanh vào ô Ghi chú đơn hàng
            'Hẻm nhỏ (< 1,5 tấn)',
            'Đường lớn xe 5 tấn vào tận nơi',
            'Giao giờ hành chính (08:00 - 17:00)',
            'Gọi trước khi giao 60 phút',
        ],
    ],

    // TAB 5: BÁO GIÁ CÔNG TRÌNH BOQ & CHỐNG SPAM LEAD
    'quote' => [
        'notify_emails'      => ['kinhdoanh@gachdravit.vn'], // Mảng email nhận thông báo Lead/BOQ mới
        'max_items'          => 30,                   // Giới hạn tối đa số dòng slot trong 1 phiếu BOQ
        'rate_limit_15m'     => 5,                    // Số lần gửi Lead/BOQ tối đa / IP / 15 phút
        'volume_tiers'       => [                     // Bậc chiết khấu dự kiến trên BOQ (D-23, xếp giảm dần theo min_m2)
            ['min_m2' => 100, 'rate' => 8.0],
            ['min_m2' => 30,  'rate' => 5.0],
            ['min_m2' => 0,   'rate' => 0.0],
        ],
        'roles'              => [                     // Danh sách hạng mục chuẩn trong từng phòng BOQ
            'Lát nền chính',
            'Ốp tường',
            'Ốp vách Tivi',
            'Trang trí / Điểm nhấn',
            'Ốp tường bếp',
            'Khu vực tắm đứng',
            'Lát nền chống trượt',
            'Lát ban công / sân phơi',
            'Len chân tường',
        ],
        'default_rooms'      => ['Phòng khách', 'Phòng bếp', 'Nhà tắm / WC', 'Phòng ngủ Master'],
    ],

    // TAB 6: TRANG CHỦ
    'home' => [
        'hero' => [
            'eyebrow'        => 'GẠCH ỐP LÁT CHÍNH HÃNG DRAVIT',
            'headline'       => 'Thẩm mỹ từ những đường nét nhỏ nhất',
            'subhead'        => 'Tra cứu thông số chuẩn xác theo m², xem mẫu thực tế dưới hệ đèn 3000K - 6500K và nhận dự toán chẵn thùng cho từng phòng.',
            'image_desktop'  => 0,                    // absint attachment ID ảnh LCP desktop (D-25, bắt buộc)
            'image_mobile'   => 0,                    // absint attachment ID ảnh LCP mobile
            'cta_primary'    => ['label' => 'Khám phá 28 danh mục gạch', 'url' => '/gach'],
            'cta_secondary'  => ['label' => 'Lập bảng dự toán BOQ', 'url' => '/danh-sach-bao-gia'],
            'enable_canvas'  => false,                // Chỉ chạy trên desktop >= 1024px sau LCP nếu bật (D-25)
        ],
        'trust_items' => [
            ['icon' => 'ph-seal-check', 'title' => 'Thông số minh bạch', 'desc' => 'Công bố rõ độ hút nước E%, chỉ số chống trượt R và quy cách thùng'],
            ['icon' => 'ph-stack',      'title' => 'Xuất kho đồng lô',   'desc' => 'Kiểm soát mã lô xuất kho đồng nhất cho toàn bộ diện tích phòng'],
            ['icon' => 'ph-calculator', 'title' => 'Tính chuẩn m² & thùng', 'desc' => 'Tự động quy đổi diện tích thực tế sang số thùng và khối lượng vận chuyển'],
            ['icon' => 'ph-storefront', 'title' => 'Đối chiếu mẫu thật', 'desc' => 'Trải nghiệm bề mặt dưới ánh đèn vàng 3000K và trắng 6500K tại Showroom'],
        ],
        'space_slugs'    => ['gach-phong-khach', 'gach-nha-tam', 'gach-phong-bep', 'gach-san-vuon', 'gach-ngoai-troi', 'gach-mat-tien', 'gach-phong-ngu', 'gach-ho-boi'],
        'explorer_slugs' => ['gach-van-da', 'gach-gia-go', 'gach-terrazzo', 'da-nung-ket'],
        'featured_skus'  => [],                       // int[] product_id ghim thủ công (trống -> lấy bán chạy)
        'guide_post_ids' => [],                       // int[] post_id ghim khối Cẩm nang 8/4
    ],

    // TAB 7: DANH MỤC, SẢN PHẨM & TRANG 404
    'catalog' => [
        'per_page'             => 24,                 // int 12..48
        'new_badge_days'       => 45,                 // int: số ngày gắn nhãn "Mới" tự động nếu drv_card_label trống
        'bestseller_threshold' => 20,                 // int total_sales tối thiểu gắn nhãn "Bán chạy"
    ],
    'pdp' => [
        'perks' => [                                  // 4 cam kết ngắn dưới khung mua hàng PDP (O-12)
            ['icon' => 'ph-stack',        'text' => 'Cam kết giao gạch cùng mã lô sản xuất'],
            ['icon' => 'ph-shield-check', 'text' => 'Đồng kiểm quy cách và bề mặt khi hạ hàng'],
            ['icon' => 'ph-truck',        'text' => 'Điều phối xe tải giao tận chân công trình'],
            ['icon' => 'ph-headset',      'text' => 'Hỗ trợ tính khối lượng chẵn thùng theo bản vẽ'],
        ],
        'construction_note_tpl' => 'Độ biến thiên vân thiết kế: {shade}. Khuyến nghị dùng ke cân bằng đi mạch ron {grout} và trộn đều gạch từ 3 - 4 thùng khi lát.',
    ],
    'collections_404' => ['gach-lat-nen', 'gach-op-tuong', 'gach-phong-khach', 'gach-80x80'],

    // TAB 8: CẨM NANG & TÌM KIẾM
    'blog' => [
        'popular_post_ids' => [],                     // int[] tối đa 5 bài đọc nhiều trên /cam-nang
    ],
    'search' => [
        'popular_keywords' => ['Gạch 80x80 bóng kiếng', 'Gạch giả gỗ 15x90', 'Gạch nhà tắm R10', 'Gạch Terrazzo 60x60', 'Đá nung kết 60x120'],
        'featured_silos'   => ['gach-phong-khach', 'gach-nha-tam', 'gach-80x80', 'gach-van-da', 'gach-gia-go', 'da-nung-ket'],
    ],

    // TAB 9: TÍNH NĂNG & SEO / GEO
    'features' => [
        'ai_enabled'           => false,              // BẮT BUỘC false ở Phase 1 (D-25); bật ở Phase 3
        'sample_order_enabled' => true,               // Cho phép mở form đăng ký nhận mẫu đối chiếu
        'map_download_enabled' => true,               // Hiển thị nút tải Map 3D/CAD khi SP có drv_map_file_id
    ],
    'seo' => [
        'llms_intro' => 'DRAVIT (gachdravit.vn) cung cấp gạch ốp lát chính hãng (Porcelain, Đá nung kết, Terrazzo, Giả gỗ, Gạch trang trí) với thông số kỹ thuật chuẩn ISO/TCVN và báo giá minh bạch theo m².',
        'default_og_image_id' => 0,                   // absint attachment ID 1200x630
    ],
];
```

### 8.3 Cơ chế Đọc Cấu hình & Migration `drv_db_version`

1. **Helper `drv_setting(string $path, mixed $fallback = null): mixed`:**
   - Nạp `get_option('drv_settings', [])` một lần duy nhất vào biến static trong request, merge đệ quy theo khóa với `Settings::defaults()`.
   - Truy xuất bằng ký pháp dấu chấm: `drv_setting('order.waste_default', 5)`, `drv_setting('features.ai_enabled', false)`.
2. **Migration theo `drv_db_version` (`Dravit\Core\DatabaseMigrator`):**
   - Hằng số `DRV_DB_VERSION` khai báo trong `functions.php` (khởi đầu `'1.0.0'`).
   - Trên hook `admin_init` (chỉ chạy trong Admin khi `current_user_can('manage_woocommerce')`) và khi chạy WP-CLI: so sánh `version_compare((string) get_option('drv_db_version', '0.0.0'), DRV_DB_VERSION, '<')`.
   - Nếu nhỏ hơn, chạy tuần tự các hàm nâng cấp idempotent (`migrate_1_0_0()`: đăng ký role/caps nếu cần, khởi tạo `drv_settings` mặc định nếu chưa có, flush rewrite rules 1 lần), sau đó `update_option('drv_db_version', DRV_DB_VERSION, true)`.

---

## 9. Cache Keys, LiteSpeed Purge Tags & Ánh xạ Cột CSV Import

### 9.1 Bảng Transient / Object Cache & LiteSpeed Tags

Khi Redis/Memcached của LiteSpeed Object Cache bật, ưu tiên `wp_cache_get/set($key, $group, $ttl)` với `$group = 'dravit'` (kèm biến `drv_cache_ver` tăng số nguyên mỗi lần resync để xóa nhóm tức thời); fallback sang `get_transient/set_transient` khi không có persistent object cache.

| Key mẫu | Cơ chế lưu | TTL | Dữ liệu lưu | Sự kiện Xóa / Làm mới (Invalidation) |
|---|---|---:|---|---|
| `drv_facets_{ver}_{md5}` | Object cache / Transient | `12 giờ` (`43200s`) | Mảng số lượng sản phẩm của từng giá trị trong 14 facets ứng với trạng thái lọc hiện tại (`FacetCounter`) | Tăng `drv_cache_ver` khi `drv/catalog/synced`, `woocommerce_product_set_stock_status`, đổi giá hoặc xóa SP |
| `drv_term_stats_{term_id}` | Object cache / Transient | `12 giờ` | `{count:int, price_min_m2:int, price_max_m2:int, has_showroom:bool, top_sizes:string[]}` cho Hero danh mục & Mega Menu | Xóa khi `drv/catalog/synced` chạm tới `term_id` hoặc khi đổi giá/tồn kho |
| `drv_mega_html` | Transient | `24 giờ` (`86400s`) | HTML đã render sẵn của Mega Menu 4+1 & Mobile Accordion (`D-29`) | Xóa khi lưu `product_cat`, chạy Seeder, cập nhật `drv_settings`, hoặc `drv/catalog/synced` |
| `drv_search_{ver}_{md5}` | Transient | `10 phút` (`600s`) | Kết quả JSON của `/dravit/v1/search` theo từ khóa chuẩn hóa | Tự hết hạn 10 phút hoặc tăng `drv_cache_ver` khi lưu sản phẩm |
| `drv_series_{series_slug}` | Object cache / Transient | `12 giờ` | Danh sách SKU anh em cùng `drv_series` (ID, URL, màu, khổ, bề mặt, `price_state`) cho PDP | Xóa theo `series_slug` khi lưu sản phẩm mang mã `drv_series` đó |
| `drv_rl_{scope}_{ip_hash}` | Transient | `60s` hoặc `900s` | Bộ đếm số request theo IP cho `/search` (`60/60s`), `/lead` & `/quote/submit` (`5/900s`) | Tự hết hạn theo cửa sổ thời gian |

**LiteSpeed Cache Tags (`do_action('litespeed_tag_add', $tag)` / `do_action('litespeed_purge', $tag)`):**

| Tag LiteSpeed | Gắn vào đâu | Khi nào gọi `litespeed_purge` |
|---|---|---|
| `drv_catalog` | Trang `/gach`, 27 collection, `/bo-suu-tap/*`, `/xuat-xu/*`, REST `/dravit/v1/catalog` | Lưu/xóa sản phẩm, đổi tồn kho, bắt đầu/kết thúc lịch KM (`SaleSync`), chạy Resync Catalog |
| `drv_prod_{id}` | Trang chi tiết sản phẩm `/san-pham/{slug}` của ID đó | Lưu sản phẩm `{id}`, hoặc lưu bất kỳ sản phẩm anh em cùng `drv_series` |
| `drv_global_shell` | Mọi trang public (do chứa Mega Menu & Footer liên hệ) | Lưu `drv_settings`, sửa `wp_nav_menu`, sửa thông tin hiển thị của `product_cat` trên Mega Menu |

### 9.2 Bảng Ánh xạ Cột CSV Import Chuẩn (`Dravit\Admin\CsvProductMapping`)

Mở rộng trình nhập CSV gốc của WooCommerce (**Sản phẩm -> Nhập**). Giá trị đa chọn trong cùng một ô phân cách bằng dấu sổ dọc `|` (ví dụ `lat-nen|op-tuong`). Mọi sản phẩm sau khi chèn qua CSV tự động kích hoạt `CollectionSync` để sinh `pa_kich-thuoc`, `pa_do-day`, `pa_do-hut-nuoc`, `drv_m2_per_box`, `_regular_price`, `drv_price_band`, `drv_flag` và gán đủ 28 Smart Collections.

| Tên cột trong file CSV (Header chuẩn) | Khóa ánh xạ Importer | Đích lưu (WC Field / Meta / Taxonomy) | Định dạng dữ liệu mẫu hợp lệ |
|---|---|---|---|
| `SKU` | `sku` | WC `_sku` | `DRV-8801M` |
| `Tên sản phẩm` | `name` | `post_title` | `Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80 Bóng Kiếng` |
| `Mô tả ngắn` | `short_description` | `post_excerpt` | Đoạn mô tả ngắn 2 - 3 câu |
| `Mô tả chi tiết` | `description` | `post_content` | HTML bài mô tả sản phẩm |
| `Ảnh sản phẩm` | `images` | `_thumbnail_id` + `_product_image_gallery` | URL hoặc tên file trong `uploads`, phân cách `,` (ảnh 1 = chính, ảnh 2 = hover card) |
| `Tồn kho (viên)` | `stock_quantity` | WC `_stock` (`_manage_stock = yes`) | `144` (số nguyên viên) |
| `Mã GTIN` | `global_unique_id` | WC `global_unique_id` | `8938500000012` |
| `Ngành hàng` | `drv_line` | Meta `drv_line` | `gach` (trống mặc định `gach`) |
| `Mô hình giá` | `drv_pricing_model` | Meta `drv_pricing_model` | `area_pack` |
| `Danh xưng đơn vị` | `drv_unit_label` | Meta `drv_unit_label` | `viên` hoặc `tấm` |
| `Giá/m²` | `drv_price_m2` | Meta `drv_price_m2` | `385000` (để trống hoặc `0` = Liên hệ báo giá) |
| `Giá KM/m²` | `drv_sale_price_m2` | Meta `drv_sale_price_m2` | `350000` |
| `Đơn vị cho phép mua` | `drv_buy_units` | Meta `drv_buy_units` (`D-24`) | `m2|thung` hoặc `m2|thung|vien` |
| `Đơn vị mua mặc định` | `drv_default_buy_unit` | Meta `drv_default_buy_unit` (`D-24`) | `m2`, `thung`, hoặc `vien` |
| `Chế độ làm tròn` | `drv_round_mode` | Meta `drv_round_mode` (`D-24`) | `box` (chẵn thùng) hoặc `piece` (cho lẻ viên) |
| `Mua tối thiểu (viên)` | `drv_qty_min` | Meta `drv_qty_min` (`D-24`) | `3` |
| `Rộng mm` | `drv_piece_w_mm` | Meta `drv_piece_w_mm` | `800` |
| `Dài mm` | `drv_piece_h_mm` | Meta `drv_piece_h_mm` | `800` |
| `Dày mm` | `drv_thickness_mm` | Meta `drv_thickness_mm` | `10` hoặc `9.5` |
| `Viên/thùng` | `drv_pieces_per_box` | Meta `drv_pieces_per_box` | `3` |
| `Kg/thùng` | `drv_kg_per_box` | Meta `drv_kg_per_box` | `42` |
| `Hao hụt riêng %` | `drv_waste_rate` | Meta `drv_waste_rate` | `5` hoặc để trống |
| `Độ hút nước %` | `drv_water_absorption` | Meta `drv_water_absorption` | `0.3` |
| `PEI` | `drv_pei` | Meta `drv_pei` | `IV` |
| `Mohs` | `drv_mohs` | Meta `drv_mohs` | `6` |
| `Lực phá vỡ N` | `drv_breaking_strength` | Meta `drv_breaking_strength` | `1800` |
| `Cường độ uốn N/mm2` | `drv_bending_strength` | Meta `drv_bending_strength` | `38` |
| `Mài cạnh` | `drv_rectified` | Meta `drv_rectified` | `1` / `yes` (Có) hoặc `0` / `no` (Không) |
| `Ron tối thiểu mm` | `drv_grout_min_mm` | Meta `drv_grout_min_mm` | `1.5` |
| `Ron tối đa mm` | `drv_grout_max_mm` | Meta `drv_grout_max_mm` | `2` |
| `Cấp chống bám bẩn` | `drv_stain_class` | Meta `drv_stain_class` | `5` |
| `Shade` | `drv_shade` | Meta `drv_shade` | `V2` |
| `Số mặt vân` | `drv_faces` | Meta `drv_faces` | `6` |
| `Tên màu` | `drv_color_name` | Meta `drv_color_name` | `Trắng khói Calacatta` |
| `Mã dòng Series` | `drv_series` | Meta `drv_series` | `calacatta-marble` |
| `Kệ showroom` | `drv_showroom_shelf` | Meta `drv_showroom_shelf` -> `drv_flag` | `A-08` (trống = không có mẫu tại kệ) |
| `Nhãn thẻ SP` | `drv_card_label` | Meta `drv_card_label` | `Bán chạy` |
| `Ghi chú ánh sáng` | `drv_lighting_note` | Meta `drv_lighting_note` | `Lên tông kem ấm dưới đèn 3000K` |
| `Bảo hành` | `drv_warranty` | Meta `drv_warranty` | `Chính hãng Dravit` |
| `Video URL` | `drv_video_url` | Meta `drv_video_url` | `https://...` |
| `Tóm tắt AI` | `drv_ai_blurb` | Meta `drv_ai_blurb` | Câu tóm tắt kỹ thuật cho Phase 3 |
| `Công năng` | `tax:pa_cong-nang` | Taxonomy `pa_cong-nang` (nhận slug §4.4) | `lat-nen|op-tuong` |
| `Không gian` | `tax:pa_khong-gian` | Taxonomy `pa_khong-gian` | `phong-khach|mat-tien` |
| `Vân gạch` | `tax:pa_van-gach` | Taxonomy `pa_van-gach` | `van-da-marble` |
| `Chất liệu` | `tax:pa_chat-lieu` | Taxonomy `pa_chat-lieu` | `porcelain` |
| `Màu sắc` | `tax:pa_mau-sac` | Taxonomy `pa_mau-sac` | `trang|xam` |
| `Bề mặt` | `tax:pa_be-mat` | Taxonomy `pa_be-mat` | `bong-kieng` |
| `Phong cách` | `tax:pa_phong-cach` | Taxonomy `pa_phong-cach` | `hien-dai|toi-gian` |
| `Hình dạng` | `tax:pa_hinh-dang` | Taxonomy `pa_hinh-dang` | `vuong` |
| `DCOF` | `tax:pa_dcof` | Taxonomy `pa_dcof` | `r9` |
| `Bộ sưu tập` | `tax:pa_bo-suu-tap` | Taxonomy `pa_bo-suu-tap` | `dravit-signature` |
| `Xuất xứ` | `tax:pa_xuat-xu` | Taxonomy `pa_xuat-xu` | `an-do` |
| `Danh mục chính` | `drv_primary_cat_slug` | Tra `term_id` theo slug -> `rank_math_primary_product_cat` (`drv_primary_auto = 0`) | `gach-lat-nen` (trống -> tự tính theo `priority` §4.2) |
