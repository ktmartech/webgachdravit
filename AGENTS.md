# AGENTS.md — Hướng Dẫn & Chuẩn Mực Cho AI Agent (DRAVIT)

> **Website:** `gachdravit.vn` — TMĐT bán lẻ gạch ốp lát cao cấp.  
> **Nguồn tham chiếu duy nhất (Single Source of Truth):** Toàn bộ thư mục `docs/` và `.agents/rules/`.  
> Mọi AI Agent làm việc trên codebase này **BẮT BUỘC** tuân thủ 100% các nguyên tắc, kiến trúc và quyết định dưới đây.

---

## 1. Thứ Tự Ưu Tiên Tài Liệu (Document Hierarchy)

Khi có mâu thuẫn thông tin, tuân thủ nghiêm ngặt thứ tự:
1. `docs/00_Project_Brief_Decisions.md` (Các quyết định kiến trúc ADR D-01 → D-19 mới nhất).
2. Tài liệu chuyên đề `docs/01_*.md` → `docs/10_*.md`.
3. `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md` (Chuẩn URL, H1, Breadcrumb, 28 Collection, 14 Facet).
4. `docs/branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf` (Lưu ý: Bảng màu thực tế tuân theo D-11 trong `docs/00_Project_Brief_Decisions.md`).

*Nếu phát hiện xung đột mới chưa có trong tài liệu: Dừng lại, hỏi Senior Staff/Chủ dự án, cập nhật vào ADR trong file `00`.*

---

## 2. Bản Đồ Tài Liệu & Rules

### 2.1 Thư mục `docs/`
| Mã | File | Nội dung cốt lõi | Đọc khi |
|---|---|---|---|
| `00` | [00_Project_Brief_Decisions.md](docs/00_Project_Brief_Decisions.md) | Persona, KPI, Phạm vi Phase 1, Nhật ký ADR D-01..D-19 | Bắt đầu mọi task |
| `01` | [01_Brand_Design_System.md](docs/01_Brand_Design_System.md) | Design tokens, Typography, Bảng màu, Lưới ron 1px, 4-layer UI | Mọi task Frontend/CSS |
| `02` | [02_Technical_Architecture.md](docs/02_Technical_Architecture.md) | Cấu trúc theme, PSR-4, Vite build, Whitelist libs, Bảo mật | Mọi task Backend/Build |
| `03` | [03_Catalog_Data_Model.md](docs/03_Catalog_Data_Model.md) | 28 Collections, 14 Attributes, `CollectionSync`, `PrimaryCategory` | Xử lý Catalog & Data |
| `04` | [04_Pricing_Units_Commerce.md](docs/04_Pricing_Units_Commerce.md) | `AreaPack` (giá/m² vs giá/viên), Checkout VN 2 cấp, VietQR, Quote ship | Giỏ hàng, Đặt hàng, Giá |
| `05` | [05_Faceted_Filter_AJAX.md](docs/05_Faceted_Filter_AJAX.md) | 14 Facets, URL grammar (1 chiều tĩnh vs 2+ chiều `?param`), REST API | Bộ lọc, Danh mục sản phẩm |
| `06` | [06_SEO_GEO_Spec.md](docs/06_SEO_GEO_Spec.md) | Rank Math config, Schema JSON-LD, Internal Links IL-01..06, `llms.txt` | SEO, Metadata, GEO |
| `07` | [07_UI_UX_Page_Specs.md](docs/07_UI_UX_Page_Specs.md) | Wireframe & đặc tả 11 templates, Microcopy, WCAG 2.2 AA | Dựng Template PHP/HTML |
| `08` | [08_Performance_Hosting.md](docs/08_Performance_Hosting.md) | Ngân sách hiệu suất, Font subset, WebP/AVIF, LiteSpeed Cache | Tối ưu tốc độ, Deploy |
| `09` | [09_Roadmap_QA_Testplan.md](docs/09_Roadmap_QA_Testplan.md) | Lộ trình Phase 0–6, Test cases 8 SKU seed, Preflight go-live | Lập kế hoạch, QA, Test |
| `10` | [10_Brand_Voice_And_Content_Guide.md](docs/10_Brand_Voice_And_Content_Guide.md) | Brand Voice, Tone, Từ điển thay thế văn phong AI, Khung nội dung | Viết copy, UI text, Blog |
| `L` | [layout/00_UI_UX_Layout_Roadmap.md](docs/layout/00_UI_UX_Layout_Roadmap.md) | 12 bước dựng HTML layout prototypes tại `docs/layout/` | Dựng prototype mẫu |

### 2.2 Quy tắc chuyên biệt `.agents/rules/`
- `dravit-architecture.md`: Cấu trúc thư mục theme, namespace `Dravit\`, HPOS, cấm đa ngôn ngữ.
- `dravit-coding-standards.md`: Chuẩn code PHP 8.1+, định dạng tiền VND/m², TypeScript/JS ES2022.
- `dravit-frontend-ui.md`: Lưới ron 1px, CSS `@layer`, tokens, Custom Elements `<drv-*>`.
- `dravit-catalog-commerce.md`: Đồng bộ tự động 28 Smart Collections, công thức `_price` = viên, VietQR.
- `dravit-filter-seo-geo.md`: AJAX Load More, ma trận Robots index/noindex, canonical, JSON-LD Schema.
- `dravit-security-testing.md`: Nonce, capability check, SQL injection guard, checklist nghiệm thu.

---

## 3. Các Quyết Định Kiến Trúc Bất Biến (Non-Negotiables)

1. **Classic PHP Theme + `theme.json` (D-01):**
   - Codebase nằm tại `wp-content/themes/dravit/`.
   - `theme.json` chỉ khai báo tokens cho Gutenberg editor. Toàn bộ logic render qua template PHP.
2. **Không tách Plugin riêng ở Phase 1 (D-07):**
   - Mọi logic nghiệp vụ đóng gói trong theme dưới namespace `Dravit\`, nạp qua PSR-4 autoloader tự viết trong `functions.php`.
   - Không cài thêm plugin ngoài bộ 3: **WooCommerce**, **Rank Math Free**, **LiteSpeed Cache**.
3. **Đơn ngôn ngữ 100% Tiếng Việt (D-01, Rule Architecture):**
   - Chỉ `lang="vi"`. Nghiêm cấm cài Polylang/WPML, cấm prefix URL `/vi/` hay `/en/`.
4. **Không tạo Custom Database Tables (D-07):**
   - Lưu trữ qua chuẩn WP/WC: `product_cat`, `pa_*`, post meta (qua WC CRUD), term meta, option `drv_settings`.
5. **HPOS Bắt Buộc 100%:**
   - Dùng WC Order CRUD (`$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`). **CẤM** gọi `get_post_meta()` / `update_post_meta()` trên đơn hàng.
6. **Classic Shortcode cho Giỏ hàng & Thanh toán (D-15):**
   - Bắt buộc dùng `[woocommerce_cart]` và `[woocommerce_checkout]`. **CẤM** dùng WooCommerce Cart/Checkout Blocks.
7. **Whitelist Frontend & Animation (D-04, D-18, D-19):**
   - CSS: Vanilla CSS `@layer`, CSS nesting, CSS custom properties, container queries, `:has()`.
   - JS: Vanilla JS ES2022 modules đóng gói qua Vite. **CẤM** dùng jQuery mới, React, Vue, Alpine.js.
   - Hover & Micro-interactions: **Pure CSS GPU** (`transform`, `opacity`, `--ease-out-expo`).
   - Staggered Reveal / Odometer / Cart bounce: **Anime.js v3.2.2** (~6.5KB gzip, nạp `defer` qua `Dravit.motion`).
   - Icons: **Phosphor Icons CDN** (`@phosphor-icons/web@2.1.2`, tag `<i class="ph ph-..."></i>`). **CẤM** dùng icon Unicode/emoji.
   - Lightbox: **PhotoSwipe 5** (dynamic import khi click ảnh).

---

## 4. Nghiệp Vụ Cốt Lõi (Domain Invariants)

### 4.1 28 Smart Collections & Tự Gán Danh Mục (`CollectionSync`)
- Danh mục cha duy nhất: `/gach/`. 27 danh mục con phân theo: Không gian (6), Bề mặt & Họa tiết (7), Kích thước (6), Loại gạch & Công năng (8).
- Sản phẩm **tự động gán** danh mục khi lưu/import dựa trên thuộc tính (`pa_kich_thuoc`, `pa_chat_lieu`, `pa_be_mat`, `pa_khong_gian`, `pa_hoa_van`...) theo luật trong `docs/03_Catalog_Data_Model.md`.
- `PrimaryCategory`: Tự động xác định danh mục chính theo thứ tự ưu tiên (Kích thước > Công năng > Họa tiết > Không gian) để gắn breadcrumb và gán Rank Math Primary Category.
- `SlugGuard`: Bảo vệ URL 28 collections, chặn trùng slug khi tạo Page/Post.

### 4.2 Định Giá & Đơn Vị Bán (`AreaPack`)
- **Hiển thị:** Giá/m² là giá CHÍNH (nổi bật, cỡ lớn), giá/viên là giá PHỤ.
- **Lưu trữ WC:** Thuộc tính `_price` của WooCommerce là **giá theo VIÊN** (`_price = round(price_per_m2 * m2_per_piece)`).
- **Giao dịch:** Bán theo số lượng **VIÊN** (làm tròn theo thùng nguyên).
- **Máy tính số lượng (`<drv-unit-calc>`):** Khách nhập m² (+ % hao hụt mặc định 5-10%) → tự động tính ra số thùng và số viên cần mua.

### 4.3 Bộ Lọc AJAX & Ma Trận Robots
- 14 Facet (FLT-01..14).
- **1 chiều lọc:** Chuyển hướng sang URL tĩnh nếu trùng 1 trong 28 collections (`/gach-lat-nen/`), hoặc URL canonical sạch (`/gach/?kich-thuoc=80x80`). Cho phép Google **INDEX**.
- **2+ chiều lọc hoặc có phân trang / sắp xếp:** Render qua AJAX / REST API `/wp-json/dravit/v1/catalog`. Thẻ meta `robots` bắt buộc là **`noindex, follow`**.

### 4.4 Thanh Toán & Vận Chuyển Việt Nam
- Địa chỉ 2 cấp: **Tỉnh/Thành phố (34 đơn vị hành chính mới) → Phường/Xã** (lazy load file JSON từ `assets/data/wards/{province-code}.json`).
- Vận chuyển: **Báo phí vận chuyển sau khi xác nhận đơn** (phí ship 0đ ở checkout, ghi rõ "Báo sau").
- Trạng thái đơn: Đặt hàng thành công → `drv-awaiting-quote` (Chờ xác nhận). Admin điền phí ship và click "Xác nhận đơn" → Chuyển sang `drv-awaiting-transfer` (Chờ chuyển khoản VietQR) hoặc `processing` (Đang xử lý với COD).
- VietQR: Sinh mã QR tự động theo chuẩn VietQR NAPAS (ngân hàng + số TK + số tiền gồm phí ship + nội dung `DRV{order_id}`).

---

## 5. Quy Trình Làm Việc Của AI Agent

1. **Khảo sát & Đọc tài liệu:**
   - Luôn đọc `docs/00_Project_Brief_Decisions.md` và file docs chuyên đề tương ứng trước khi động vào code.
2. **Nghiên cứu & Đề xuất:**
   - Khi gặp bài toán mới hoặc yêu cầu thiết kế: **Lập phương án, giải thích ưu/nhược điểm ngắn gọn và xin phê duyệt trước khi viết code.**
3. **Thực thi code:**
   - Code chuẩn PHP 8.1+ (`declare(strict_types=1);`), tuân thủ triệt để nguyên tắc Clean Code và Security (Nonce, Data escaping, Sanitization, Prepared SQL).
   - Kiểm tra CSS/JS qua Vite build, đảm bảo không vượt ngân sách hiệu suất (Lighthouse Mobile ≥ 90).
4. **Kiểm thử bắt buộc:**
   - Trước khi báo hoàn thành, phải đối soát checklist nghiệm thu trong `docs/09_Roadmap_QA_Testplan.md` §3:
     - Test 3.1: 8 SKU seed tự gán đúng 100% collections.
     - Test 3.2: Quy đổi m² ↔ viên ↔ thùng chuẩn xác.
     - Test 3.3: Lọc AJAX không vỡ layout, URL state chuẩn, robots meta đúng ma trận.
     - Test 3.7: Luồng checkout 2 cấp + VietQR chạy mượt mà.
     - Test 3.8: Bảo mật endpoint REST và form lead.

---

## 6. Môi Trường & Lệnh Thao Tác Nhanh

- **Local Path:** `c:\Users\Admin\Local Sites\gachdravit\app\public`
- **Theme Path:** `wp-content/themes/dravit`
- **Build Assets:** Chạy tại thư mục theme:
  ```bash
  cd wp-content/themes/dravit
  npm install
  npm run dev       # Local development với Vite HMR
  npm run build     # Đóng gói assets/dist và manifest.json
  ```
- **Quy tắc giao tiếp AI (Zero Fluff):**
  - Trả lời cực kỳ ngắn gọn, trực tiếp, tập trung vào giải pháp và code.
  - Không mở bài/kết bài rườm rà, không chào hỏi, không giải thích dông dài.
