# 20. Đặc tả Hệ thống Cẩm nang, Gutenberg Patterns, Shortcodes & E-E-A-T

> **Quyết định tham chiếu:** `D-01`, `D-04`, `D-08`, `D-17`, `D-20`, `D-21`, `D-23`, `D-24`, `D-25`, `D-26`, `D-28`, `D-29`, `D-30` ([00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md)).
> **Liên kết tài liệu:** [06_SEO_GEO_Spec.md](06_SEO_GEO_Spec.md) · [07_UI_UX_Page_Specs.md](07_UI_UX_Page_Specs.md) · [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md) · [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md) · [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md).
> **Module phụ trách:** `Dravit\Content\BlogSetup`, `Dravit\Content\PostMeta`, `Dravit\Content\AuthorProfile`, `Dravit\Content\BlockStyles`, `Dravit\Content\Patterns`, `Dravit\Content\Shortcodes`, `Dravit\Content\Toc`, `Dravit\SEO\BlogSchema`, `assets/src/js/blog.js`.

---

## 1. Kiến trúc URL & Taxonomy Silo Cẩm nang (`D-21`, `D-29`)

### 1.1 Cấu trúc Đường dẫn Tĩnh & Quy tắc Rewrite Không Dấu `/` Cuối

Toàn bộ phân hệ nội dung chuyên môn gom dưới tiền tố `/cam-nang` (trang tĩnh *Trang bài viết* `page_for_posts`), tách biệt rạch ròi với 28 Smart Collections thương mại gốc `/gach-*` và sản phẩm `/san-pham/{slug}`:

| Thực thể WordPress | Cấu hình Permalink / Base | Mẫu URL Chuẩn (`D-21`, `D-29`) | Chỉ thị Meta `robots` | Template PHP xử lý |
|---|---|---|---|---|
| Trang chủ Cẩm nang (`page_for_posts`) | Slug trang: `cam-nang` | `/cam-nang` (phân trang `/cam-nang/page/2`) | `index, follow` | `home.php` |
| Bài viết chi tiết (`post`) | `permalink_structure`: `/cam-nang/%postname%` | `/cam-nang/{slug}` | `index, follow` | `single-post.php` (hoặc `single.php`) |
| Chuyên mục Cẩm nang (`category`) | `category_base`: `chu-de` | `/chu-de/{slug}` (phân trang `/chu-de/{slug}/page/2`) | `index, follow` | `category.php` |
| Thẻ bài viết (`post_tag`) | `tag_base`: `the` | `/the/{slug}` | **`noindex, follow`** | `tag.php` (hoặc `archive.php`) |
| Tìm kiếm trong Cẩm nang | `?s={kw}&post_type=post` | `/?s=chong+truot&post_type=post` | **`noindex, follow`** | `search.php` (nhánh `post`) |
| Trang Tác giả / Lưu trữ Ngày | Vô hiệu hóa trong Rank Math (`06 §2`) | Redirect `301` về `/gioi-thieu#doi-ngu` hoặc `/cam-nang` | Không tồn tại | - |

> **Ràng buộc kỹ thuật bắt buộc (`with_front => false`):** Vì `permalink_structure` có tiền tố tĩnh `/cam-nang/`, mọi `register_post_type()` và `register_taxonomy()` khác trên hệ thống (bao gồm WooCommerce `/san-pham`, `/bo-suu-tap`, `/xuat-xu`) **bắt buộc đặt `'rewrite' => ['slug' => '...', 'with_front' => false]`** để không bao giờ bị nối nhầm thành `/cam-nang/san-pham/{slug}`. Đồng thời, `Catalog\SlugGuard` chặn tạo Page/Category trùng 28 slug gạch và redirect `301` mọi URL dư dấu `/` cuối (`D-21`).

### 1.2 4 Chuyên mục Chuẩn (`category` Seed) & Vai trò Hỗ trợ Silo Thương mại (`IL-05`)

Seeder khởi tạo sẵn 4 chuyên mục chuẩn (xóa hoặc đổi `Uncategorized` mặc định thành `ky-thuat-op-lat`):

| STT | Slug (`/chu-de/{slug}`) | Tên chuyên mục | Nhóm chủ đề & Từ khóa mục tiêu | Nhóm Collection thương mại nhận link `IL-05` |
|---:|---|---|---|---|
| 1 | `chon-gach-theo-phong` | **Chọn gạch theo phòng** | Kinh nghiệm chọn khổ gạch, bề mặt, độ chống trượt cho phòng khách, nhà tắm, bếp, sân vườn, ban công | `/gach-phong-khach`, `/gach-nha-tam`, `/gach-phong-bep`, `/gach-san-vuon` |
| 2 | `ky-thuat-op-lat` | **Kỹ thuật ốp lát** | Chỉ số `R9 - R13`, độ hút nước `E%`, độ mài mòn `PEI`, đi ron cân bằng, trộn gạch theo `Shade V1 - V4`, chống thấm | `/gach-lat-nen`, `/gach-op-tuong`, `/gach-porcelain`, `/da-nung-ket` |
| 3 | `phoi-mau-xu-huong` | **Phối màu & Xu hướng** | Phối gạch vân đá, Terrazzo, giả gỗ theo phong cách Japandi, Wabi-Sabi, Indochine, ánh đèn `3000K / 6500K` | `/gach-van-da`, `/gach-gia-go`, `/gach-terrazzo`, `/gach-trang-tri` |
| 4 | `dinh-muc-bao-gia` | **Định mức & Báo giá** | Công thức tính số thùng, hệ số hao hụt cắt gạch `5 - 10%`, so sánh chi phí theo `m²`, kinh nghiệm nghiệm thu lô gạch | `/gach`, `/gach-80x80`, `/gach-60x120`, `/danh-sach-bao-gia` |

### 1.3 Đặc tả Template Lưu trữ: `home.php` (`/cam-nang`) & `category.php` (`/chu-de/{slug}`)

#### A. Khắc phục Triệt để các Lỗi Kiến trúc của Prototype `07-blog-archive.html`
1. **Bỏ `role="tablist"` / `role="tab"` sai chuẩn trên link chuyên mục:** Thay bằng `<nav class="drv-blog-cats" aria-label="Chuyên mục Cẩm nang">` chứa các thẻ `<a class="drv-blog-cats__link" href="/chu-de/{slug}">` thật (trang hiện tại gắn `aria-current="page"`).
2. **Tìm kiếm bài viết chạy Server-side GET:** Form `<form role="search" method="get" action="/">` gửi `name="s"` kèm `<input type="hidden" name="post_type" value="post">` (xóa bỏ bộ lọc ẩn/hiện DOM cục bộ chỉ tìm trong vài thẻ đang hiện trên trang).
3. **Ẩn hoàn toàn CTA Trợ lý AI ở Phase 1 (`D-25`):** Khi `drv_setting('features.ai_enabled', false) === false`, thay khối gợi ý AI bằng hộp công cụ **Lập bảng dự toán BOQ theo phòng** (`/danh-sach-bao-gia`).

#### B. Bố cục Trang Chủ Cẩm nang `home.php` (`/cam-nang`)
- **Tại trang 1 (`!is_paged()`):**
  1. **Header Editorial & Thanh Chuyên mục + Ô Tìm kiếm Bài viết:** H1 *"Cẩm nang Chọn gạch & Kỹ thuật Ốp lát"*, mô tả ngắn chuẩn chuyên gia, thanh link `Tất cả (/cam-nang)` + 4 chuyên mục `/chu-de/{slug}` + ô tìm kiếm bài viết.
  2. **Lưới Editorial Bento `5 / 4 / 3` (12 cột Desktop, đường ron `1px`):**
     - **Cột trái (5 cột - Bài Pillar Trọng tâm):** Truy vấn 1 bài mới nhất có meta `drv_is_pillar = '1'` (fallback: sticky post hoặc bài mới nhất), ảnh tỷ lệ `16:10` (`fetchpriority="high"`), nhãn chuyên mục, thời lượng đọc `{n} phút đọc`, tóm tắt 3 dòng.
     - **Cột giữa (4 cột - 3 Bài Mới Xuất bản):** 3 bài mới nhất tiếp theo (`post__not_in => [$pillar_id]`), ảnh vuông `96×96` cạnh tiêu đề.
     - **Cột phải (3 cột - 5 Bài Tra cứu Nhiều Nhất):** Đọc danh sách ID từ `drv_setting('blog.popular_ids') ?: drv_setting('blog.popular_post_ids', [])` (`orderby => 'post__in'`, tối đa 5 bài; nếu Admin chưa chọn thì fallback 5 bài mới nhất thuộc chuyên mục `ky-thuat-op-lat` và `dinh-muc-bao-gia`), đánh số thứ tự `01..05` kiểu `tabular-nums`.
  3. **Khối Topic Clusters (4 Cụm Chủ đề Chuyên sâu):** 4 cột tương ứng 4 chuyên mục chuẩn, mỗi cột liệt kê 3 bài tiêu biểu + link *"Xem tất cả {count} bài chuyên mục {Tên}"* trỏ về `/chu-de/{slug}`.
  4. **Bảng Tra nhanh Chỉ số Chống trượt `R9 - R13` theo Không gian (GEO Reference Block):** Render bằng bảng HTML ngữ nghĩa `<table>` từ dữ liệu cấu hình kỹ thuật chuẩn (`O-15`), mỗi dòng có link thẳng sang Collection tĩnh tương ứng (`/gach-phong-khach`, `/gach-nha-tam`, `/gach-phong-bep`, `/gach-san-vuon`, `/gach-ho-boi`).
  5. **Lưới Bài viết Còn lại + Phân trang chuẩn `/cam-nang/page/2`:** Hiển thị 9 bài/trang.
  6. **Dải Chuyển đổi Cuối trang (CRO Strip):** 2 cột đối xứng: *Cần tính số thùng theo diện tích phòng?* (`/danh-sach-bao-gia`) và *Xem mẫu gạch thật dưới đèn 3000K / 6500K* (`/lien-he`).
- **Từ trang 2 trở đi (`is_paged()`):** Thu gọn khối Bento, Topic Clusters và bảng `R9 - R13`; hiển thị thẳng Header chuyên mục + Lưới 12 bài viết + Phân trang số có `<a href="/cam-nang/page/{n}">` để Googlebot thu thập sâu không trùng lặp nội dung đầu trang.

#### C. Bố cục Trang Chuyên mục `category.php` (`/chu-de/{slug}`)
- Breadcrumb Rank Math: `Trang chủ > Cẩm nang (/cam-nang) > {Tên chuyên mục}`.
- H1 `{Tên chuyên mục}`, đoạn mô tả chuyên mục (`term_description()`), thanh chuyển nhanh giữa 4 chuyên mục (`aria-current="page"` trên mục đang xem).
- Lưới 12 bài viết/trang (`3 cột` Desktop · `2 cột` Tablet · `1 cột` Mobile) + phân trang chuẩn `/chu-de/{slug}/page/{n}` (`index, follow`).
- Khối chân trang **Danh mục Gạch Liên quan (`IL-05`)**: Hiển thị 4 thẻ Collection tĩnh gắn với chuyên mục đó (qua term meta `drv_related_collections` của category hoặc bảng ánh xạ mặc định ở §1.2).

---

## 2. Mô hình Dữ liệu Bài viết & Hồ sơ Tác giả / Thẩm định E-E-A-T (`O-13`)

### 2.1 Bảng Post Meta của Bài viết Cẩm nang (`Dravit\Content\PostMeta`)

Đăng ký qua `register_post_meta('post', ...)` (`show_in_rest => true` cho Gutenberg Sidebar / Metabox PHP thuần, không cài ACF):

| Meta key | Kiểu dữ liệu | Mặc định | Quy tắc Tự tính / Sanitize trên `save_post_post` | Tác dụng ngoài Frontend & Schema |
|---|---|---|---|---|
| `drv_is_pillar` | `'1' \| '0'` | `'0'` | `'1'` hoặc `'0'` | Đưa bài viết lên vị trí bài đinh 5 cột của lưới Bento `/cam-nang` |
| `drv_reading_time` | `int` (phút) | Tự tính | Đếm từ tiếng Việt từ `wp_strip_all_tags(strip_shortcodes($post->post_content))` qua `preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)`, tính `max(1, (int) ceil($words / 200))` (~200 từ/phút) | Hiển thị `"{n} phút đọc"` ở meta đầu bài và thẻ bài viết |
| `drv_toc_enabled` | `'1' \| '0'` | `'1'` | `'1'` hoặc `'0'` | Cho phép sinh Mục lục `<drv-toc>` (tự ẩn nếu bài có `< 3` thẻ `<h2>`) |
| `drv_key_takeaways` | `string[]` (JSON) | `'[]'` | Mảng 3 - 5 gạch đầu dòng, mỗi mục `≤ 180` ký tự (`sanitize_text_field`) | Tự động render hộp **Tóm tắt nhanh cho gia chủ** ngay đầu bài nếu nội dung chưa chèn block pattern tương ứng |
| `drv_related_collections` | `int[]` (JSON) | `'[]'` | Mảng tối đa `4` `term_id` thuộc `product_cat` (chỉ nhận 28 Smart Collections tĩnh) | Render dải điều hướng Silo thương mại `IL-05` cuối bài (bắt buộc tối thiểu 1 - 2 collection cho mọi bài xuất bản) |
| `drv_related_posts` | `int[]` (JSON) | `'[]'` | Mảng tối đa `4` `post_id` đã publish; nếu để trống tự lấy bài cùng `category` | Khối *Bài viết kỹ thuật liên quan* dưới chân bài |
| `drv_reviewer_id` | `int` (`user_id`) | `0` | `absint` user tồn tại trong WP (`0` = chưa gán người thẩm định riêng) | Hiển thị huy hiệu **Thẩm định kỹ thuật bởi** đầu/cuối bài + xuất `reviewedBy` trong JSON-LD `BlogPosting` |
| `drv_reviewed_at` | `string` | `''` | Chuỗi ngày `YYYY-MM-DD` hợp lệ (`preg_match('/^\d{4}-\d{2}-\d{2}$/')`) | Hiển thị ngày rà soát thông số kỹ thuật gần nhất |
| `drv_ai_prompts` | `string[]` (JSON) | `'[]'` | Tối đa 3 câu hỏi gợi mở (`sanitize_text_field`) | Dùng cho `[drv_ai_card]` ở Phase 3 (ẩn ở Phase 1 theo `D-25`) |

### 2.2 Hồ sơ Nhân sự E-E-A-T (`Dravit\Content\AuthorProfile`) & Cấm Dữ liệu Giả

#### A. Bảng User Meta (`show_user_profile` & `edit_user_profile`)
| Meta key | Kiểu | Mô tả & Validate |
|---|---|---|
| `drv_job_title` | `string` | Chức danh chuyên môn thực tế (`≤ 80` ký tự, ví dụ: `"Kỹ sư Vật liệu Ốp lát"`, `"Chuyên viên Tư vấn Kỹ thuật DRAVIT"`). |
| `drv_credentials` | `string` | Tóm tắt kinh nghiệm thực chiến / chứng chỉ (`≤ 200` ký tự, đồng bộ với `description` tiểu sử). |
| `drv_avatar_id` | `int` | Attachment ID ảnh chân dung thật tự host trên `gachdravit.vn` (tắt hẳn `get_avatar` gọi ra `gravatar.com` để bảo vệ LCP/quyền riêng tư). |
| `drv_same_as` | `string[]` (JSON) | Mảng URL hồ sơ nghề nghiệp chính chủ (`LinkedIn`, bài báo chuyên ngành, tối đa 5 URL `esc_url_raw`). |
| `drv_public_expert` | `'1' \| '0'` | Đánh dấu hiển thị nhân sự này trong khu vực Đội ngũ Chuyên môn công khai. |

#### B. Định danh `Person.url` khi Tắt Author Archive (`06 §2`)
Vì lưu trữ tác giả `/author/*` bị tắt trong Rank Math để tránh mỏng nội dung (thin content), mọi liên kết tên tác giả / người thẩm định trên bài viết và thuộc tính `url` / `@id` của thực thể `Person` trong JSON-LD **trỏ về khu vực neo cố định trên trang Giới thiệu** (hoặc trang con `/doi-ngu` nếu tách riêng khi quy mô nhân sự lớn):
- URL hiển thị & `Person.url`: `https://gachdravit.vn/gioi-thieu#person-{user_id}` (hoặc `/doi-ngu#person-{user_id}` khi tạo trang `/doi-ngu`).
- Trên template `page-about.php` (`/gioi-thieu`), khối `#doi-ngu` tự động lặp qua các user có `drv_public_expert = '1'` và gắn `id="person-{user_id}"` cho từng thẻ hồ sơ để cả người đọc lẫn trình thu thập của Google đối chiếu minh bạch.

#### C. Quy tắc Trung thực Tuyệt đối (Xóa bỏ Vi phạm của Prototype `08-blog-single.html`)
1. **Nghiêm cấm bịa đặt danh tính & ảnh mạng:** Tuyệt đối không chèn tên nhân vật giả định (`KS. Trịnh Hoài Nam`, `KTS. Lê Minh Khôi`), ảnh chân dung stock/Unsplash hoặc số lượt bình chọn giả (`148 gia chủ thấy hữu ích`).
2. **Cơ chế Fallback An toàn trước khi có `O-13`:** Khi bài viết chưa được gán nhân sự có hồ sơ thật (`empty($job_title)` hoặc tài khoản `admin` tổng hợp), template tự động hiển thị tác giả là tổ chức **`Ban Kỹ thuật DRAVIT`** (kèm biểu tượng thương hiệu DRAVIT) và xuất `author` trong Schema là `@id: "https://gachdravit.vn/#organization"`.
3. **Loại bỏ Widget Poll Ảo:** Xóa bỏ khối khảo sát cảm xúc đếm số ảo cuối bài trong prototype; thay bằng khối **Hỗ trợ kỹ thuật trực tiếp** (Gửi ảnh hiện trạng qua Zalo / Mở form nhận mẫu gạch).

---

## 3. Hệ thống Gutenberg Block Styles & Block Patterns (`inc/Content/{BlockStyles,Patterns}.php`)

Tuân thủ `D-01` và `D-04`: **Không viết custom Gutenberg block bằng React/JSX** để tránh gánh nặng bảo trì deprecation khi nâng cấp WordPress. Toàn bộ thành phần trình bày chuyên sâu của Cẩm nang xây dựng bằng **Core Blocks + `register_block_style()` + PHP Block Patterns (`patterns/*.php`)**, chia sẻ chung hệ CSS tokens (`assets/src/css/editor.css` nạp qua `add_editor_style()`).

### 3.1 Đăng ký Block Styles (`Dravit\Content\BlockStyles`)

| Block gốc | Tên style (`name`) | Nhãn trong Editor | Quy cách hiển thị CSS (`@layer components`) |
|---|---|---|---|
| `core/group` | `drv-callout-note` | `DRAVIT: Ghi chú kỹ thuật` | Nền `--c-ivory` (`#F8FAFC`), viền trái `3px solid var(--c-navy-900)`, padding `16px 20px` |
| `core/group` | `drv-callout-tip` | `DRAVIT: Mẹo thi công chuẩn` | Nền `#F0FDFA`, viền trái `3px solid var(--c-teal-500)` (`#0D9488`), nhấn mạnh kinh nghiệm thực tế tại công trình |
| `core/group` | `drv-callout-warning` | `DRAVIT: Cảnh báo sai lầm` | Nền `--c-amber-100` (`#FEF3C7`), viền trái `3px solid var(--c-amber-500)` (`#F59E0B`), chữ `--c-navy-900`, cảnh báo trượt ngã / lệch lô / thấm ngược |
| `core/list` | `drv-checklist` | `DRAVIT: Danh sách kiểm tra` | Dấu tick màu `--c-teal-700`, khoảng cách dòng rộng dễ quét mắt trên di động |
| `core/image` | `drv-scene-frame` | `DRAVIT: Ảnh phối cảnh có viền ron` | Viền mảnh `1px solid var(--c-navy-200)`, liền mạch với thanh `[drv_shop_the_look]` bên dưới |

### 3.2 Bộ 6 Block Patterns Chuẩn Biên tập (`patterns/*.php`, Category `dravit-editorial`)

#### 1. Hộp Tóm tắt Nhanh Chuẩn GEO (`patterns/blog-key-takeaways.php`)
Đặt ngay sau đoạn mở bài để các công cụ AI (ChatGPT, Gemini, Perplexity, Google AI Overviews) trích xuất câu trả lời trực diện:
```html
<!-- wp:group {"className":"drv-takeaways","layout":{"type":"constrained"}} -->
<aside class="wp-block-group drv-takeaways" aria-label="Tóm tắt nội dung cốt lõi">
  <!-- wp:heading {"level":2,"className":"drv-takeaways__title"} -->
  <h2 class="wp-block-heading drv-takeaways__title">Tóm tắt nhanh cho gia chủ</h2>
  <!-- /wp:heading -->
  <!-- wp:list {"className":"drv-takeaways__list"} -->
  <ul class="wp-block-list drv-takeaways__list">
    <li>Sàn nhà tắm có dốc thoát nước bắt buộc chọn gạch xương Porcelain (E ≤ 0,5%) và bề mặt chống trượt từ R10 trở lên.</li>
    <li>Khổ gạch 30x60 cm dễ tạo độ dốc về phễu thu sàn hơn khổ 60x120 cm trong phòng tắm dưới 5 m².</li>
    <li>Luôn cộng thêm 5 - 10% hao hụt cắt gạch và đặt dư 1 thùng cùng mã lô xuất kho để dự phòng bảo trì.</li>
  </ul>
  <!-- /wp:list -->
</aside>
<!-- /wp:group -->
```
> *Lưu ý:* Thẻ tiêu đề trong `.drv-takeaways` có thể dùng `<p class="drv-takeaways__title"><strong>Tóm tắt nhanh cho gia chủ</strong></p>` (mặc định trong pattern) để không chiếm mất mục số 1 của Mục lục `<drv-toc>`. Nếu biên tập viên điền mảng `drv_key_takeaways` trong Metabox mà chưa chèn block `.drv-takeaways` vào nội dung, `single-post.php` tự động render hộp này ngay trên đầu `the_content()`.

#### 2. Bảng Thông số So sánh Chuẩn GEO (`core/table` + Filter `render_block_core/table`)
Để mọi bảng thông số kỹ thuật đọc tốt trên màn hình 360px và đạt chuẩn dữ liệu có cấu trúc cho AI:
- Hook `render_block_core/table` trong `BlockStyles` tự động:
  1. Bọc `<figure class="wp-block-table">` trong `<div class="drv-geo-table-wrap" tabindex="0" role="region" aria-label="Bảng thông số kỹ thuật (vuốt ngang để xem thêm)">` (cuộn ngang mượt trên mobile, ghim cột đầu tiên nếu bảng `≥ 5` cột).
  2. Tự động gắn `scope="col"` cho mọi thẻ `<th>` nằm trong `<thead>` nếu Gutenberg chưa xuất.
- Pattern `patterns/blog-geo-table.php` tạo sẵn khung `core/table` có `<thead>` và `<figcaption>` (render kèm `<caption class="sr-only">` hoặc dùng `<figcaption>` mô tả rõ ngữ cảnh bảng).

#### 3. Bảng Đối chiếu Ưu / Nhược điểm (`patterns/blog-pros-cons.php`)
Lưới 2 cột `core/columns` chia đường ron `1px` (`1 cột` trên `< 768px`):
- **Cột Ưu điểm (`.drv-pros`):** Header nền nhẹ `#F0FDFA`, tiêu đề `Ưu điểm thực tế`.
- **Cột Hạn chế cần lưu ý (`.drv-cons`):** Header nền `#FFFBEB`, tiêu đề `Điểm cần lưu ý khi thi công`.

#### 4. Hộp Công thức Tính Định mức Vật tư (`patterns/blog-formula-box.php`)
Trình bày công thức toán học và ví dụ tính thực tế bằng chữ HTML thuần (`font-variant-numeric: tabular-nums`):
```html
<!-- wp:group {"className":"drv-formula-box"} -->
<div class="wp-block-group drv-formula-box">
  <p class="drv-formula-box__label">CÔNG THỨC QUY ĐỔI SỐ THÙNG GẠCH CHUẨN</p>
  <p class="drv-formula-box__eq">
    Số thùng cần mua = Làm tròn lên [ (Diện tích đo thực tế × 1,05) ÷ Diện tích 1 thùng ]
  </p>
  <p class="drv-formula-box__example">
    Ví dụ: Phòng khách rộng 28 m², chọn gạch 80x80 cm (3 viên/thùng = 1,92 m²/thùng), hao hụt 5% → Cần 29,4 ÷ 1,92 = 15,31 → Đặt chẵn <strong>16 thùng (48 viên = 30,72 m²)</strong>.
  </p>
</div>
<!-- /wp:group -->
```

#### 5. Khối Quy trình Các Bước Trực quan (`patterns/blog-steps.php`)
Danh sách có thứ tự trình bày các bước thi công / kiểm tra gạch tại công trình. **Chỉ render giao diện HTML/CSS sạch, tuyệt đối KHÔNG sinh `HowTo` JSON-LD** (Google đã ngừng hỗ trợ `HowTo` Rich Results từ 2023 theo `06 §4`).

#### 6. Khối Câu hỏi Thường gặp Cuối bài (`patterns/blog-faq-group.php`)
Sử dụng tổ hợp `core/group` (class `.drv-faq-group`) chứa các block native `core/details` (`<details class="wp-block-details"><summary>Câu hỏi?</summary><p>Câu trả lời...</p></details>`):
- **Không gắn `itemscope` / `itemtype="https://schema.org/FAQPage"` trực tiếp vào HTML** (khắc phục lỗi trùng lặp Schema kép của prototype `08-blog-single.html`).
- Class `Dravit\SEO\BlogSchema` tự động đọc các cặp `<summary>` + nội dung bên trong `.drv-faq-group` của bài viết để bơm duy nhất 1 thực thể `FAQPage` sạch vào đồ thị `@graph` JSON-LD của Rank Math (§5.3).

---

## 4. Hệ thống Shortcodes Thương mại Nhúng trong Bài (`inc/Content/Shortcodes.php`)

### Quy tắc Bất biến: Nghiêm cấm Gõ cứng Giá hoặc Tồn kho trong Bài viết!
Mọi mức giá `đ/m²`, tình trạng còn hàng, số viên/thùng và nút hành động thương mại nhúng giữa bài viết Cẩm nang **bắt buộc** đi qua 6 Shortcodes động dưới đây. Bất kỳ khi nào bài viết thực thi shortcode sản phẩm (`[drv_products]`, `[drv_shop_the_look]`, `[drv_price_table]`), PHP tự động gọi `do_action('litespeed_tag_add', 'drv_catalog')` để LiteSpeed tự động làm mới cache trang bài viết ngay khi Admin cập nhật giá hoặc tồn kho sản phẩm.

### 4.1 `[drv_products]` - Lưới hoặc Băng chuyền Sản phẩm Gợi ý & Cầu nối Silo (`IL-05`)

**Cú pháp:**
```text
[drv_products collection="gach-terrazzo" skus="" limit="4" layout="grid" title="Mẫu gạch Terrazzo chống trượt nhà tắm tiêu biểu"]
```

**Tham số & Logic xử lý:**
| Tham số | Mặc định | Quy tắc xử lý an toàn |
|---|---|---|
| `collection` | `''` | Slug thuộc 28 Smart Collections tĩnh (ví dụ `gach-terrazzo`, `gach-nha-tam`). |
| `skus` | `''` | Danh sách mã SKU phân cách dấu phẩy (ví dụ `DRV-TRZ60,DRV-8801M`). Nếu truyền `skus`, ưu tiên lấy đúng các SKU này theo thứ tự truyền vào. |
| `limit` | `4` | Giới hạn cứng `min(8, max(2, absint($limit)))`. |
| `layout` | `'grid'` | Whitelist `'grid'` (lưới 2 - 4 cột) hoặc `'carousel'` (thanh cuộn ngang `scroll-snap` trên di động). |
| `title` | Tự sinh | Tiêu đề khối hiển thị phía trên lưới. |

**Đầu ra bắt buộc:**
- Từng sản phẩm render qua đúng component chuẩn **`drv_ui('product-card', ['product' => $product, 'context' => 'blog'])`**:
  - Hỗ trợ trọn vẹn cả sản phẩm có giá (`priced` / `sale` → hiện `đ/m²` + nút `Thêm giỏ` theo `D-24`) và sản phẩm dự án (`quote_only` → hiện `Liên hệ báo giá` + nút `Báo giá` `data-drv-action="quote-add"` theo `D-20`).
  - Không có nút Wishlist (`D-26`).
- Nếu có truyền `collection` hợp lệ, chân khối luôn render nút CTA nối thẳng về trang Silo tĩnh (`IL-05`):
  ```html
  <a class="drv-btn drv-btn--outline" href="/gach-terrazzo">
    <span>Xem trọn bộ 42+ mẫu Gạch Terrazzo chính hãng</span>
    <i class="ph ph-arrow-right" aria-hidden="true"></i>
  </a>
  ```
  *(Dùng URL tĩnh `/gach-terrazzo`, tuyệt đối không gắn query `?param`).*

### 4.2 `[drv_shop_the_look]` - Thanh Gắn Mã Gạch Dưới Chân Ảnh Phối Cảnh (`07 §7.2`)

Khắc phục thiếu sót của prototype `08-blog-single.html`: ngay bên dưới bức ảnh phối cảnh phòng thực tế, biên tập viên chèn shortcode này để gắn mã gạch đang lát/ốp trong ảnh mà không phá mạch đọc:

**Cú pháp:**
```text
[drv_shop_the_look sku="DRV-TRZ60" role="Gạch lát sàn trong ảnh" caption="Phối dưới ánh đèn vàng ấm 3000K"]
```
*(Hỗ trợ truyền nhiều mã `sku="DRV-TRZ60,DRV-3612T"` tối đa 2 mẫu: nền + tường).*

**Đầu ra (`drv_ui('shop-the-look', ...)`):**
```html
<div class="drv-stl-bar" role="region" aria-label="Sản phẩm sử dụng trong ảnh phối cảnh">
  <div class="drv-stl-bar__item">
    <img class="drv-stl-bar__thumb" src="..." width="48" height="48" loading="lazy" alt="">
    <div class="drv-stl-bar__info">
      <span class="drv-stl-bar__role">Gạch lát sàn trong ảnh · Mã <strong>DRV-TRZ60</strong></span>
      <a class="drv-stl-bar__name" href="/san-pham/gach-terrazzo-dravit-60x60-drvtrz60">
        Gạch Terrazzo Dravit Hạt Nhuyễn 60x60 Matt R10
      </a>
    </div>
    <div class="drv-stl-bar__right">
      <!-- Tự động rẽ nhánh priced vs quote_only (D-20) -->
      <strong class="drv-stl-bar__price">320.000đ/m²</strong>
      <a class="drv-btn drv-btn--sm drv-btn--outline" href="/san-pham/gach-terrazzo-dravit-60x60-drvtrz60">
        <span>Xem mẫu</span>
      </a>
    </div>
  </div>
</div>
```

### 4.3 `[drv_price_table]` - Bảng Báo giá & Quy cách Động Chuẩn GEO

Thay thế hoàn toàn các bảng gõ tay giá tiền trong bài viết định mức/báo giá:

**Cú pháp:**
```text
[drv_price_table skus="DRV-8801M,DRV-TRZ60,DRV-61209,DRV-3612T" collection="" limit="8" caption="Bảng quy cách đóng gói và đơn giá tham khảo cập nhật từ kho DRAVIT"]
```

**Cấu trúc cột HTML SSR (bọc trong `.drv-geo-table-wrap`):**

| Mã & Tên mẫu gạch | Kích thước (cm) | Bề mặt / Chống trượt | Quy cách thùng | Đơn giá niêm yết | Dự toán BOQ |
|---|---|---|---|---|---|
| Link `<a href="/san-pham/...">` + mã `SKU` | `80x80` (`drv_piece_w/h_mm`) | `Bóng kiếng · R9` (`pa_be-mat` + `pa_dcof`) | `3 viên = 1,92 m² (42 kg)` | **`385.000đ/m²`** *(hoặc chữ **`Liên hệ báo giá`** nếu `quote_only`)* | `<button type="button" class="drv-btn drv-btn--xs drv-btn--outline" data-drv-action="quote-add" data-product-id="101" data-sku="DRV-8801M" data-drv-area="15">+ Báo giá</button>` |

- Sử dụng duy nhất action chuẩn **`data-drv-action="quote-add"`** (xóa bỏ action rời rạc `blog-add-quote` của prototype).
- Ghi chú chân bảng tự động hiển thị ngày đồng bộ và liên kết sang công cụ tính chẵn thùng `/danh-sach-bao-gia`.

### 4.4 `[drv_lead_form]` - Biểu mẫu Nhận Mẫu Gạch / Tư vấn Bản vẽ Tại chỗ (`D-23`, `D-30`)

**Cú pháp:**
```text
[drv_lead_form type="sample" title="Đăng ký nhận mẫu gạch thật đối chiếu tại công trình" sku=""]
```
- `type`: `'sample'` (nhận mẫu gạch cắt/viên thực tế) hoặc `'consult'` (gửi mặt bằng nhờ kỹ thuật tính khối lượng).
- Khi `type === 'sample'` và `drv_setting('features.sample_order_enabled', true) === false`, tự động chuyển giao diện sang chế độ `'consult'`.
- **Hợp đồng gửi dữ liệu (`POST /wp-json/dravit/v1/lead`):**
  - Không in cứng WP Nonce vào HTML bài viết đang cache; khi người dùng focus vào bất kỳ input nào của form, JS lấy token tươi từ `GET /wp-json/dravit/v1/nonce` (`X-DRV-Nonce: {nonces.lead}`).
  - Gửi payload chuẩn với `source: "blog_sample"` (khi `type="sample"`, ánh xạ vào `_drv_lead_type = 'sample'` kèm `_drv_post_id` của bài viết; hoặc `source: "blog_inline"` khi `type="consult"`), trường ẩn `post_id = get_the_ID()`, honeypot `drv_website`, `elapsed_ms >= 3000`, và checkbox đồng ý xử lý dữ liệu liên hệ theo NĐ 13/2023/NĐ-CP.

### 4.5 `[drv_related_posts]` - Hộp Đọc thêm Nội bộ Giữa Bài

**Cú pháp:**
```text
[drv_related_posts ids="142,158" limit="2" label="Xem thêm hướng dẫn liên quan"]
```
- Nếu `ids` trống, tự truy vấn `limit` (`1..3`) bài cùng chuyên mục chính (`post__not_in => [get_the_ID()]`).
- Render thẻ `<aside class="drv-inline-related">` gọn nhẹ (chỉ icon `ph-article` + tiêu đề link tĩnh `/cam-nang/{slug}` + thời gian đọc), tuyệt đối không chèn ảnh lớn làm đứt mạch đọc.

### 4.6 `[drv_ai_card]` - Thẻ Gợi ý Hỏi Trợ lý AI (Khóa Cứng theo Cờ `D-25`)

**Cú pháp:**
```text
[drv_ai_card prompts="Nhà tắm 4m2 nên lát gạch 30x60 hay 60x60?|Tính giúp số thùng gạch 80x80 cho sàn 35m2"]
```
- **Quy tắc Cứng `D-25`:** Dòng đầu tiên của hàm callback kiểm tra:
  ```php
  if (!(bool) drv_setting('features.ai_enabled', false)) {
      return '';
  }
  ```
- Ở toàn bộ Phase 1 (`ai_enabled = false`), shortcode trả về chuỗi rỗng `''` (không xuất bất kỳ thẻ HTML ẩn nào ra DOM). Khi mở Phase 3 (`ai_enabled = true`), shortcode mới render các chip câu hỏi dẫn sang `/tro-ly-ai-tim-gach`.

---

## 5. Mục lục Tự động (`Content\Toc` + `<drv-toc>`), Lightbox PhotoSwipe 5 & Schema `BlogPosting`

### 5.1 Bộ Phân tích Heading Server-side (`Dravit\Content\Toc`) & Web Component `<drv-toc>`

#### A. Xử lý `the_content` trên Server (`Dravit\Content\Toc`, ưu tiên `priority = 12` sau `do_shortcode`)
1. Chỉ kích hoạt trên `is_singular('post')` khi `get_post_meta($post_id, 'drv_toc_enabled', true) !== '0'`.
2. Quét các thẻ `<h2>` và `<h3>` nằm trực tiếp trong luồng bài viết (bỏ qua mọi heading nằm bên trong `.drv-takeaways`, `.drv-faq-group`, `.drv-card` hoặc shortcode sản phẩm để mục lục không bị rác).
3. Nếu tổng số thẻ `<h2>` hợp lệ **`< 3`**: giữ nguyên nội dung và **không render khung TOC**.
4. **Sinh Anchor `id` ASCII Tất định:**
   - Nếu thẻ đã có thuộc tính `id="..."` do biên tập viên đặt tay: chuẩn hóa `sanitize_title($existing_id)` và giữ nguyên.
   - Nếu chưa có `id`: lấy `wp_strip_all_tags($heading_inner_html)` → bỏ dấu tiếng Việt (`Helpers::ascii_fold()` → `sanitize_title()`, giới hạn 60 ký tự, ví dụ `"1. Chỉ số chống trượt R10 là gì?"` → `id="1-chi-so-chong-truot-r10-la-gi"`).
   - Nếu trùng lặp `id` trong cùng bài viết: tự động nối hậu tố `-2`, `-3` (`$seen[$id]++`).
5. Lưu mảng cây mục lục `$items = [['id' => string, 'text' => string, 'level' => 2|3], ...]` vào biến tĩnh của request `Toc::get_current_items()` để sidebar Desktop và ngăn kéo Mobile lấy ra render SSR.
6. CSS gắn trên `.drv-prose :is(h2, h3)[id]`: `scroll-margin-top: calc(var(--header-h-current, 112px) + 20px)` để khi bấm mục lục không bị Header che mất dòng tiêu đề.

#### B. Giao diện Desktop Sticky TOC, Mobile Floating TOC & Thanh Tiến độ Đọc GPU (`assets/src/js/blog.js`)
1. **Desktop (`≥ 1024px`):** Cột trái hoặc phải `260px` (`position: sticky; top: calc(var(--header-h-current) + var(--s-5))`), bọc trong `<drv-toc class="drv-toc">`. Dùng `IntersectionObserver` (`rootMargin: '-15% 0px -72% 0px'`) tô sáng `.is-active` (`border-left-color: var(--c-teal-500); color: var(--c-navy-900); font-weight: 600`).
2. **Mobile (`< 1024px` - Chống đè lên `.drv-mobile-bar`):**
   - Nút nổi mở nhanh Mục lục `.drv-toc-fab` đặt tại góc phải dưới nhưng **nâng cao hơn hẳn thanh Mobile Bottom Bar 5 ô**:
     ```css
     .drv-toc-fab {
       position: fixed;
       right: var(--s-4);
       bottom: calc(var(--mobile-bar-h, 56px) + env(safe-area-inset-bottom, 0px) + var(--s-3));
       z-index: var(--z-fab); /* 25: nổi trên nội dung, dưới modal/header */
     }
     ```
   - Bấm `.drv-toc-fab` mở `<dialog id="drv-toc-drawer" class="drv-drawer drv-drawer--bottom">`; chọn 1 mục trong danh sách sẽ đóng `<dialog>` và cuộn mượt tới `#id`.
3. **Thanh Tiến độ Đọc Bài (`.drv-read-progress` - 100% Compositor GPU):**
   - Sửa triệt để lỗi cập nhật `style.width` gây reflow mỗi khung hình của prototype:
     ```css
     .drv-read-progress {
       position: fixed;
       inset: 0 0 auto 0;
       height: 3px;
       background: var(--c-teal-500);
       transform-origin: 0 50%;
       transform: scaleX(0);
       will-change: transform;
       z-index: calc(var(--z-header) + 1);
       pointer-events: none;
     }
     ```
   - Trong `blog.js`, lắng nghe `scroll` (`{ passive: true }`) gộp qua `requestAnimationFrame`, tính tỷ lệ `ratio = clamp((scrollY - articleTop) / articleReadableHeight, 0, 1)` và gán duy nhất **`progressEl.style.transform = \`scaleX(\${ratio.toFixed(4)})\``** (tắt khi `prefers-reduced-motion: reduce`).

### 5.2 Thống nhất Lightbox PhotoSwipe 5 & Ngân sách JS Trang Blog (`D-28`)

1. **Xóa bỏ hoàn toàn `<dialog id="drv-blog-lightbox">` và ~277 dòng `<script>` inline của prototype `08-blog-single.html`.**
2. Filter `render_block_core/image` và `render_block_core/gallery` trên `is_singular('post')` tự động gắn `data-pswp-src`, `data-pswp-width`, `data-pswp-height` (lấy kích thước ảnh gốc từ `wp_get_attachment_image_src($id, 'full')`) và `data-drv-action="lightbox-open"` cho các ảnh kỹ thuật/phối cảnh có độ phân giải lớn (`width >= 800`).
3. Khi độc giả bấm phóng to ảnh lần đầu tiên, `blog.js` mới gọi **`import('./modules/lightbox.js')`** (dùng chung 100% chunk **PhotoSwipe 5** với trang chi tiết sản phẩm PDP, hỗ trợ pinch-to-zoom xem sát bề mặt vân gạch trên điện thoại).
4. **Ngân sách JS cứng (`D-28`):** Tổng dung lượng JS tải ban đầu trên bài Cẩm nang (`core.js` + `blog.js`) **bắt buộc `≤ 10KB` gzip**.

### 5.3 Mở rộng Rank Math JSON-LD: `BlogPosting` + `reviewedBy` + `FAQPage` (`Dravit\SEO\BlogSchema`)

Can thiệp qua filter `rank_math/json_ld` trên `is_singular('post')` để chuẩn hóa đồ thị thực thể E-E-A-T:

1. **Ép kiểu `@type: "BlogPosting"`** (thay cho `"Article"` chung chung của prototype).
2. **Thực thể `author` & `reviewedBy` trung thực:**
   - Nếu tác giả bài viết có hồ sơ chuyên môn thật (`drv_job_title !== ''`), xuất `Person` đầy đủ (`@id`, `name`, `jobTitle`, `description`, `image`, `url: "https://gachdravit.vn/gioi-thieu#person-{id}"`, `sameAs`, `worksFor: { "@id": "https://gachdravit.vn/#organization" }`). Ngược lại, gán `author: { "@id": "https://gachdravit.vn/#organization" }`.
   - Nếu `drv_reviewer_id > 0` và user thẩm định hợp lệ: chèn thuộc tính **`reviewedBy`** kiểu `Person` kèm `dateModified` (ưu tiên `max(post_modified_gmt, drv_reviewed_at)`).
3. **Liên kết thực thể sản phẩm nhắc tới (`about` / `mentions`):** Tự động đính kèm URL tĩnh của các collection trong `drv_related_collections` vào mảng `mentions` để củng cố ngữ nghĩa chủ đề với Silo `/gach-*`.
4. **Trích xuất tự động `FAQPage` từ `.drv-faq-group`:**
   - Dùng `DOMDocument` / regex an toàn đọc tất cả cặp `<details>` (`<summary>` = câu hỏi, phần còn lại lọc sạch HTML chỉ giữ `<a>, <strong>, <em>, <br>, <ul>, <li>` = câu trả lời) bên trong `.drv-faq-group`.
   - Nếu trích xuất được `≥ 2` câu hỏi hợp lệ và Rank Math chưa có node `FAQPage`, tự động đẩy thêm node `FAQPage` vào `$data['faq']` của đồ thị JSON-LD.
5. **Quy tắc Cấm Tuyệt đối:**
   - **Cấm xuất `HowTo` Schema** dưới mọi hình thức.
   - **Cấm gắn thuộc tính Microdata (`itemscope`, `itemtype`, `itemprop`)** lên HTML của Breadcrumb hay FAQ để không tạo bản sao Schema xung đột với khối `<script type="application/ld+json">` của Rank Math.

```php
<?php
declare(strict_types=1);

namespace Dravit\SEO;

defined('ABSPATH') || exit;

final class BlogSchema
{
    public static function register(): void
    {
        add_filter('rank_math/json_ld', [self::class, 'enhance_post_graph'], 25, 2);
    }

    public static function enhance_post_graph(array $data, $jsonld): array
    {
        if (!is_singular('post')) {
            return $data;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return $data;
        }

        // 1. Nâng cấp node RichSnippet / Article của Rank Math thành BlogPosting chuẩn E-E-A-T
        foreach ($data as $key => &$entity) {
            if (!is_array($entity) || !isset($entity['@type'])) {
                continue;
            }
            $types = (array) $entity['@type'];
            if (array_intersect($types, ['Article', 'BlogPosting', 'NewsArticle'])) {
                $entity['@type'] = 'BlogPosting';
                $entity['author'] = self::build_author_reference((int) $post->post_author);

                $reviewer_id = (int) get_post_meta($post->ID, 'drv_reviewer_id', true);
                if ($reviewer_id > 0) {
                    $reviewer_node = self::build_person_node($reviewer_id);
                    if ($reviewer_node !== null) {
                        $entity['reviewedBy'] = $reviewer_node;
                    }
                }
            }
        }
        unset($entity);

        // 2. Bơm tự động FAQPage từ khối .drv-faq-group (nếu có >= 2 câu hỏi)
        $faq_items = self::extract_faq_entities((string) $post->post_content);
        if (count($faq_items) >= 2 && !isset($data['drv_faq'])) {
            $data['drv_faq'] = [
                '@type'      => 'FAQPage',
                '@id'        => get_permalink($post) . '#faq',
                'mainEntity' => $faq_items,
            ];
        }

        return $data;
    }
    // ... build_author_reference(), build_person_node(), extract_faq_entities()
}
```

---

## 6. Checklist Biên tập Nội dung Chuẩn SEO / GEO trước Xuất bản

Mỗi bài viết trên `/cam-nang/{slug}` phải đạt đủ 10 tiêu chí sau trước khi chuyển trạng thái `Publish`:

- [ ] **1. URL & Chuyên mục chuẩn Silo:** Slug ngắn gọn 4 - 8 từ không dấu, không chứa năm dễ lỗi thời (trừ bài xu hướng năm cụ thể), không có dấu `/` cuối; gán đúng **1 chuyên mục chính** trong 4 chuyên mục `/chu-de/{slug}`.
- [ ] **2. Hộp Tóm tắt GEO đầu bài:** Có khối `.drv-takeaways` (3 - 5 ý cốt lõi trả lời thẳng câu hỏi tìm kiếm trong 60 từ đầu tiên, kèm con số thông số cụ thể như `E ≤ 0,5%`, `R10`, `ron 1,5 - 2 mm`).
- [ ] **3. Phân cấp Heading sạch cho `<drv-toc>`:** Chỉ có duy nhất 1 thẻ `<h1>` (tiêu đề bài); tối thiểu 3 thẻ `<h2>` diễn đạt rõ ý; không nhảy cóc `<h2>` xuống `<h4>`.
- [ ] **4. Không gõ cứng Giá & Tồn kho:** Kiểm tra toàn bài **0 lần** gõ tay số tiền giá sản phẩm cụ thể vào văn bản hoặc bảng tĩnh; toàn bộ thẻ SP và bảng giá đều nhúng qua `[drv_products]`, `[drv_shop_the_look]` hoặc `[drv_price_table]`.
- [ ] **5. Dữ liệu Bảng văn bản cho AI (GEO):** Mọi so sánh kích thước, chỉ số `R`, độ hút nước, định mức keo/ron đều trình bày bằng block `core/table` thật (có hàng tiêu đề `<thead>`), **không chụp ảnh bảng Excel dán vào bài**.
- [ ] **6. Liên kết Nội bộ Ngược về Silo Thương mại (`IL-05`):** Đã chọn tối thiểu **1 - 2 Smart Collections** trong `drv_related_collections` và có ít nhất 1 anchor text tự nhiên trong bài trỏ về URL tĩnh `/gach-*` (không trỏ vào URL lọc `?param`).
- [ ] **7. Hình ảnh & `[drv_shop_the_look]`:** Ảnh đại diện tỷ lệ `16:9` (`≥ 1200×675px` WebP, `< 150KB`), đầy đủ `alt` mô tả thực chất bề mặt/không gian; các ảnh phối cảnh có gạch DRAVIT đã gắn `[drv_shop_the_look]` ngay bên dưới.
- [ ] **8. Minh bạch E-E-A-T & Văn phong (`10_Brand_Voice_And_Content_Guide.md`):** Chỉ gắn tác giả / kỹ sư thẩm định có hồ sơ thật (`O-13`, ngược lại để `Ban Kỹ thuật DRAVIT`); không dùng từ sáo rỗng AI (*"tối ưu hóa không gian sống đẳng cấp"*, *"bức tranh giao hưởng"*), không dùng ký tự em-dash trong UI/tiêu đề, định dạng số chuẩn Việt Nam (`1,92 m²`, `0,5%`, `385.000đ/m²`).
- [ ] **9. Khối FAQ & Kiểm tra Rich Results:** Nếu bài có phần giải đáp cuối bài, dùng đúng group `.drv-faq-group` chứa `core/details` (2 - 5 câu hỏi thực tế của chủ nhà/thầu thợ); kiểm tra mã nguồn trang chỉ xuất **1** `BlogPosting` + **1** `BreadcrumbList` + **1** `FAQPage`, không có `HowTo` và không có `itemscope` trùng.
- [ ] **10. Kiểm tra Hiển thị Mobile `375px`:** Bảng rộng cuộn ngang mượt trong `.drv-geo-table-wrap`, nút nổi Mục lục `.drv-toc-fab` nằm gọn phía trên thanh `.drv-mobile-bar` 5 ô, bấm phóng to ảnh mở mượt bằng PhotoSwipe 5.

