# AGENTS.md — Hướng Dẫn & Chuẩn Mực Cho AI Agent (DRAVIT)

> **Website:** `gachdravit.vn` — TMĐT bán lẻ gạch ốp lát cao cấp.  
> **Nguồn tham chiếu duy nhất (Single Source of Truth):** Toàn bộ thư mục `docs/` và `.agents/rules/`.  
> Mọi AI Agent làm việc trên codebase này **BẮT BUỘC** tuân thủ 100% các nguyên tắc, kiến trúc và quyết định dưới đây.

---

## 1. Thứ Tự Ưu Tiên Tài Liệu (Document Hierarchy)

Khi có mâu thuẫn thông tin, tuân thủ nghiêm ngặt thứ tự:
1. `docs/00_Project_Brief_Decisions.md` (Các quyết định kiến trúc ADR D-01 → D-30 mới nhất).
2. Tài liệu chuyên đề & đặc tả hợp đồng hệ thống `docs/01_*.md` → `docs/20_*.md`.
3. `docs/branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md` (Chuẩn URL không dấu `/` cuối, H1, Breadcrumb, 28 Collection, 14 Facet).
4. `docs/branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf` (Lưu ý: Bảng màu thực tế tuân theo D-11 trong `docs/00_Project_Brief_Decisions.md`).

*Nếu phát hiện xung đột mới chưa có trong tài liệu: Dừng lại, hỏi Senior Staff/Chủ dự án, cập nhật vào ADR trong file `00`.*

---

## 2. Bản Đồ Tài Liệu & Rules

### 2.1 Thư mục `docs/`
| Mã | File | Nội dung cốt lõi | Đọc khi |
|---|---|---|---|
| `00` | [00_Project_Brief_Decisions.md](docs/00_Project_Brief_Decisions.md) | Persona, KPI, Phạm vi Phase 1, Nhật ký ADR D-01..D-30 | Bắt đầu mọi task |
| `01` | [01_Brand_Design_System.md](docs/01_Brand_Design_System.md) | Design tokens, Typography, Bảng màu, Lưới ron 1px, 4-layer UI | Mọi task Frontend/CSS |
| `02` | [02_Technical_Architecture.md](docs/02_Technical_Architecture.md) | Cấu trúc theme, PSR-4, Vite entries, Whitelist libs, Bảo mật | Mọi task Backend/Build |
| `03` | [03_Catalog_Data_Model.md](docs/03_Catalog_Data_Model.md) | 28 Collections, 14 Attributes, `CollectionSync`, `PrimaryCategory` | Xử lý Catalog & Data |
| `04` | [04_Pricing_Units_Commerce.md](docs/04_Pricing_Units_Commerce.md) | `AreaPack`, Cấu hình đơn vị mua từng SP (`m²/thùng/viên`), Checkout VN, VietQR | Giỏ hàng, Đặt hàng, Giá |
| `05` | [05_Faceted_Filter_AJAX.md](docs/05_Faceted_Filter_AJAX.md) | 14 Facets, URL grammar, Ma trận Robots, REST `/catalog` | Bộ lọc, Danh mục sản phẩm |
| `06` | [06_SEO_GEO_Spec.md](docs/06_SEO_GEO_Spec.md) | Rank Math config, Schema JSON-LD, Internal Links IL-01..06, `llms.txt` | SEO, Metadata, GEO |
| `07` | [07_UI_UX_Page_Specs.md](docs/07_UI_UX_Page_Specs.md) | Wireframe & đặc tả 12 templates, Microcopy, WCAG 2.2 AA | Dựng Template PHP/HTML |
| `08` | [08_Performance_Hosting.md](docs/08_Performance_Hosting.md) | Ngân sách hiệu suất, Font/Icon subset, WebP/AVIF, LiteSpeed Cache | Tối ưu tốc độ, Deploy |
| `09` | [09_Roadmap_QA_Testplan.md](docs/09_Roadmap_QA_Testplan.md) | Lộ trình Phase 0–6, Test cases 8 SKU seed, Preflight go-live | Lập kế hoạch, QA, Test |
| `10` | [10_Brand_Voice_And_Content_Guide.md](docs/10_Brand_Voice_And_Content_Guide.md) | Brand Voice, Tone, Từ điển thay thế văn phong AI, Khung nội dung | Viết copy, UI text, Blog |
| `12-13` | [12_AI_Advisor_Strategy_Analysis.md](docs/12_AI_Advisor_Strategy_Analysis.md), [13_AI_Advisor_MVP_Spec.md](docs/13_AI_Advisor_MVP_Spec.md) | Kiến trúc & MVP Spec cho Trợ lý AI Tìm gạch (Phase 3, D-41..D-49) | Chuẩn bị Phase 3 AI |
| `14` | [14_Data_Dictionary_And_Settings.md](docs/14_Data_Dictionary_And_Settings.md) | **Từ điển dữ liệu tập trung:** toàn bộ Meta keys, CPT, `drv_settings`, CSV | Mọi task Backend/DB |
| `15` | [15_REST_API_And_JS_Contract.md](docs/15_REST_API_And_JS_Contract.md) | **Hợp đồng API & JS:** REST endpoints, Store API, `<drv-*>`, `data-drv-*`, Events | Tích hợp PHP ↔ JS |
| `16` | [16_Template_Component_Porting_Guide.md](docs/16_Template_Component_Porting_Guide.md) | **Bản đồ Port Layout → WP:** 12 trang → PHP, `drv_ui()`, danh sách loại bỏ | Port HTML mẫu sang WP |
| `17` | [17_Quote_BOQ_And_Lead_Spec.md](docs/17_Quote_BOQ_And_Lead_Spec.md) | Đặc tả BOQ (`/danh-sach-bao-gia`), CPT `drv_lead`, Chống spam, Admin Tạo đơn | Báo giá công trình & Lead |
| `18` | [18_Order_Ops_And_Email_Spec.md](docs/18_Order_Ops_And_Email_Spec.md) | Vận hành đơn HPOS Admin, Action Scheduler auto-cancel, Email templates | Xử lý đơn & Email |
| `19` | [19_Navigation_Header_Search_Spec.md](docs/19_Navigation_Header_Search_Spec.md) | Header 2 tầng, Mega Menu 4+1 từ config, Walkers, Live Search không dấu | Header, Menu, Tìm kiếm |
| `20` | [20_Blog_Editorial_And_Content_Model.md](docs/20_Blog_Editorial_And_Content_Model.md) | Mô hình Cẩm nang, E-E-A-T, Block Styles/Patterns, Shortcodes, TOC | Cẩm nang / Gutenberg |
| `L` | [layout/00_LAYOUT_AUDIT_REPORT.md](docs/layout/00_LAYOUT_AUDIT_REPORT.md) | Báo cáo audit đối chiếu 12 HTML prototypes vs bộ docs | Tra cứu quyết định port |

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
4. **Không tạo Custom Database Tables (D-07, D-23):**
   - Lưu trữ qua chuẩn WP/WC: `product_cat`, `pa_*`, post meta (qua WC CRUD), term meta, user meta, CPT private `drv_lead` (cho báo giá BOQ & lead form), option `drv_settings`.
5. **HPOS Bắt Buộc 100% & Slug Trạng thái ≤ 20 ký tự (D-22):**
   - Dùng WC Order CRUD (`$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`). **CẤM** gọi `get_post_meta()` / `update_post_meta()` trên đơn hàng.
   - Mọi custom `post_status` / order status bắt buộc dài `≤ 20` ký tự tính cả tiền tố `wc-`.
6. **Classic Shortcode cho Giỏ hàng & Thanh toán (D-15, D-30):**
   - Bắt buộc dùng `[woocommerce_cart]` và `[woocommerce_checkout]` (2 cột desktop + topbar tối giản). **CẤM** dùng WooCommerce Cart/Checkout Blocks.
7. **Whitelist Frontend & Animation (D-04, D-18, D-19, D-28, D-30):**
   - CSS: Vanilla CSS `@layer`, CSS nesting, CSS custom properties, container queries, `:has()`. Container `1380px`.
   - JS: Vanilla JS ES2022 modules đóng gói qua Vite (tách entry theo template + `import()` động). **CẤM** dùng jQuery mới, React, Vue, Alpine.js, classic script IIFE toàn cục hay auto-inject widget nặng toàn site.
   - Hover & Micro-interactions: **Pure CSS GPU** (`transform`, `opacity`, `--ease-out-expo`).
   - Staggered Reveal / Odometer / Cart bounce: **Anime.js v3.2.2** (~6.5KB gzip, tự host qua Vite chunk, `import()` động).
   - Icons: **Phosphor Icons Subset tự host** (`assets/fonts/phosphor-subset.woff2`, cú pháp `<i class="ph ph-..."></i>`). **CẤM** dùng icon Unicode/emoji.
   - Lightbox: **PhotoSwipe 5** (1 module duy nhất `import()` động cho cả PDP và Blog).
   - QR dịch vụ ngoài duy nhất được phép: `img.vietqr.io` (chỉ gọi khi đơn BACS ở trạng thái `on-hold`).

---

## 4. Nghiệp Vụ Cốt Lõi (Domain Invariants)

### 4.1 28 Smart Collections & Tự Gán Danh Mục (`CollectionSync`)
- Toàn bộ URL trên site **không** dùng dấu `/` cuối (`D-21`).
- Danh mục gốc: `/gach`. 27 danh mục con phân theo 4 nhóm (`03 §3.1`): **Công năng (7), Không gian (8), Vân & Chất liệu (7), Kích thước (5)**.
- Sản phẩm **tự động gán** danh mục khi lưu/import dựa trên thuộc tính (`pa_cong-nang`, `pa_khong-gian`, `pa_van-gach`, `pa_chat-lieu`, `pa_kich-thuoc`...) và cờ (`on_sale`, `large_format`) theo luật trong `docs/03_Catalog_Data_Model.md`.
- `PrimaryCategory`: Ưu tiên lựa chọn tay của Admin; nếu trống tự chọn theo trọng số số học `priority` trong `config/collections.php` (`D-27`, khớp 8/8 SKU nghiệm thu) để gắn breadcrumb và `rank_math_primary_product_cat`.
- `SlugGuard`: Bảo vệ URL 28 collections và các trang hệ thống (`/lien-he`, `/danh-sach-bao-gia`, `/tro-ly-ai-tim-gach`...), chặn trùng slug khi tạo Page/Post.

### 4.2 Định Giá (`priced` / `quote`) & Cấu Hình Đơn Vị Mua Theo Sản Phẩm (`AreaPack`, D-20, D-24)
- **Hiển thị:** Với SP có giá (`priced`), Giá/m² là giá CHÍNH, giá/viên là giá PHỤ. Với SP không niêm yết giá (`quote`, `drv_price_m2` trống hoặc `0`), khóa `is_purchasable = false`, hiện *"Liên hệ báo giá"*, ẩn `Offer` 0đ trong JSON-LD nhưng giữ nguyên máy tính quy đổi khối lượng.
- **Lưu trữ & Giao dịch lõi WC:** Thuộc tính `_price` của WooCommerce là **giá theo VIÊN** (`_price = round(price_m2 * area_piece)`). Số lượng giao dịch gửi lên WC và quản lý tồn kho luôn là **số nguyên VIÊN**.
- **Cài đặt đơn vị mua trên từng sản phẩm (`D-24`):** Trong tab *Đơn vị & Thông số* của mỗi SKU, Admin cấu hình:
  1. Các đơn vị khách được chọn mua: **`m²` / `thùng` / `viên`** (`drv_buy_units`) và đơn vị mở sẵn mặc định (`drv_default_buy_unit`).
  2. Chế độ làm tròn (`drv_round_mode`): **`box`** (bắt buộc chẵn thùng, `qty_step = pieces_per_box`) hoặc **`piece`** (cho phép mua lẻ viên, `qty_step = 1`, kèm gợi ý làm tròn thùng trong giỏ) và mức tối thiểu `drv_qty_min`.
- **1 Lõi tính toán duy nhất:** Client (`assets/src/js/utils/calc.js`) và Server (`Dravit\Units\Calc`) dùng chung công thức quy đổi `m² ⇄ viên ⇄ thùng ⇄ kg`.

### 4.3 Bộ Lọc AJAX & Ma Trận Robots (`D-21`)
- 14 Facet (`FLT-01..14`) khai báo trong `config/facets.php`.
- **0 tham số lọc (URL tĩnh `/gach`, 27 collections, `/bo-suu-tap/*`, `/xuat-xu/*`, kể cả `/page/n`):** Cho phép Google **`index, follow`**.
- **Ở `/gach` chọn đúng 1 giá trị đã có trang Collection tĩnh:** Redirect `301` sang URL tĩnh đó (`/{slug}`).
- **Mọi URL có tham số (`?param`):** Render SSR + AJAX qua `/wp-json/dravit/v1/catalog`. Meta `robots` bắt buộc là **`noindex, follow`** (nếu chỉ gồm param nhóm `seo` FLT-01..09) hoặc **`noindex, nofollow`** (nếu có bất kỳ param nhóm `tech` FLT-10..14). Canonical luôn trỏ về URL tĩnh gốc không query.

### 4.4 Thanh Toán, Vận Chuyển, Đơn Hàng & BOQ (`D-22`, `D-23`, `D-30`)
- Địa chỉ 2 cấp: **Tỉnh/Thành phố (34 đơn vị hành chính mới) → Phường/Xã** (lazy load `assets/data/wards/{province-code}.json`), hỗ trợ xuất hóa đơn VAT (`_drv_vat_*`).
- Vận chuyển: **Báo phí vận chuyển sau khi xác nhận đơn** (`drv_quote_later`, hiển thị rõ *"Báo sau khi xác nhận đơn"*, không ghi `"0đ"`).
- Trạng thái đơn chuẩn HPOS: Đặt hàng thành công → **`wc-cho-xac-nhan`** (*Chờ xác nhận*, trừ tồn kho ngay). Admin nhập phí ship và bấm Order Action *"Xác nhận đơn & gửi email"* → chuyển sang **`wc-on-hold`** (đổi nhãn *Chờ chuyển khoản*, kèm VietQR) hoặc **`wc-processing`** (*Đang xử lý* với COD).
- VietQR: Sinh qua `img.vietqr.io` (mã BIN + số TK + tổng tiền gồm phí ship + nội dung `DRV{order_id}` chỉ gồm chữ số; mã đơn hiển thị `DRV-{order_id}`). **Chỉ hiển thị khi đơn BACS ở trạng thái `on-hold`.**
- Báo giá công trình BOQ (`/danh-sach-bao-gia`) & Form Lead: Lưu bản nháp tại `localStorage` (`drv_quote_v2`), khi gửi lưu vào CPT private `drv_lead` (có Admin Action chuyển thành đơn hàng).

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
