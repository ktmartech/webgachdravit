# Lộ trình Chi tiết Xây dựng Bộ HTML Layout Mẫu (`docs/layout/`) — Phương án 1

> **Phạm vi Phase này:** Chỉ thực hiện **100% Frontend UI/UX (HTML5 ngữ nghĩa + Vanilla CSS `@layer` + Vanilla JS tương tác giao diện với Mock Data)**. Toàn bộ tính năng Backend (PHP WordPress, Database, WooCommerce Hook, REST API) sẽ thực hiện ở Phase sau khi toàn bộ giao diện tại `docs/layout/` đã được nghiệm thu.
> **Thư mục đích:** `c:\Users\Admin\Local Sites\gachdravit\app\public\docs\layout\`

---

## 1. Cấu trúc Thư mục Đích (`docs/layout/`)

```text
docs/layout/
├── 00_UI_UX_Layout_Roadmap.md       # Tài liệu lộ trình thực thi chi tiết (File này)
├── assets/
│   ├── css/
│   │   ├── tokens.css               # Design Tokens: Màu sắc, Typography, Spacing, Radius, Z-index
│   │   ├── main.css                 # @layer reset, base, layout (.l-*), components (.drv-*), utilities
│   │   └── pages.css                # CSS bố cục đặc thù từng trang (Home, Catalog, Product, Checkout, Blog)
│   ├── js/
│   │   ├── core.js                  # Event Delegation (data-drv-action: dialog, drawer, tabs, copy-sku, sticky)
│   │   ├── unit-calc.js             # Light DOM <drv-unit-calc> & <drv-qty-input> (m² ⇄ viên ⇄ thùng ⇄ kg)
│   │   └── catalog-mock.js          # Light DOM <drv-filter-form>, Instant Search mock, Sort & Load More
│   └── img/                         # SVG/WebP vân gạch mẫu, phối cảnh phòng & logo (Icon dùng Phosphor CDN)
├── 00-ui-kit.html                   # Step 1: Master UI Kit, Tokens, Global Header/Footer & Modals
├── 01-home.html                     # Step 2: Trang chủ (8 Sections Kiến trúc)
├── 02-catalog-collection.html       # Step 3: Trang Danh mục Silo & 14 Bộ lọc AJAX
├── 03-product-single.html           # Step 4: Trang Chi tiết Sản phẩm & Bộ tính m² ⇄ Viên
├── 04-cart.html                     # Step 5: Trang Giỏ hàng & Mini-Cart Drawer
├── 04.1-quote-cart.html             # Step 5.1 (Option 4.1): Trang Danh sách Báo giá Tổng (BOQ), Tải PDF/Excel & Tư vấn Sỉ
├── 05-checkout.html                 # Step 6: Trang Thanh toán 2 cột (Địa chỉ 2 cấp + Báo phí sau)
├── 06-order-received.html           # Step 7: Trang Đặt hàng Thành công & VietQR 2 trạng thái
├── 07-blog-archive.html             # Step 8: Trang Danh sách Cẩm nang (Editorial Bento)
├── 08-blog-single.html              # Step 9: Trang Chi tiết Bài viết Cẩm nang (GEO Box + Shop the Look)
├── 09-showroom-about-quote.html     # Step 10: Trang Showroom, Giới thiệu & Báo giá Công trình
└── 10-policies-search-404.html      # Step 11: Trang Chính sách, Kết quả Tìm kiếm & 404
```

---

## 2. Bảng Tổng hợp Ma trận Step × Rules × Skills

| Step | File đầu ra | Rules trọng tâm (`.agents/rules/`) | Skills trọng tâm (`.agents/skills/`) |
|---|---|---|---|
| **Step 0** | `assets/css/*`, `assets/js/*`, `assets/img/*` | `dravit-frontend-ui`, `dravit-coding-standards`, `dravit-architecture` | `ui-ux-designer`, `baseline-ui`, `javascript-pro`, `modern-javascript-patterns`, `clean-code` |
| **Step 1** | `00-ui-kit.html` | `dravit-frontend-ui`, `dravit-coding-standards`, `dravit-security-testing` | `frontend-design`, `design-taste-frontend`, `ui-ux-designer`, `fixing-accessibility`, `wcag-audit-patterns` |
| **Step 2** | `01-home.html` | `dravit-frontend-ui`, `dravit-catalog-commerce`, `dravit-filter-seo-geo` | `design-taste-frontend`, `frontend-design`, `page-cro`, `copywriting`, `avoid-ai-writing`, `web-performance-optimization` |
| **Step 3** | `02-catalog-collection.html` | `dravit-filter-seo-geo`, `dravit-frontend-ui`, `dravit-catalog-commerce` | `ui-ux-designer`, `page-cro`, `seo-structure-architect`, `fixing-accessibility`, `fixing-motion-performance` |
| **Step 4** | `03-product-single.html` | `dravit-catalog-commerce`, `dravit-frontend-ui`, `dravit-filter-seo-geo` | `page-cro`, `frontend-design`, `javascript-pro`, `fixing-accessibility`, `copywriting` |
| **Step 5** | `04-cart.html` | `dravit-catalog-commerce`, `dravit-frontend-ui`, `dravit-security-testing` | `page-cro`, `ui-ux-designer`, `baseline-ui`, `fixing-accessibility` |
| **Step 5.1** | `04.1-quote-cart.html` | `dravit-catalog-commerce`, `dravit-frontend-ui`, `dravit-coding-standards` | `page-cro`, `form-cro`, `ui-ux-designer`, `baseline-ui`, `fixing-accessibility` |
| **Step 6** | `05-checkout.html` | `dravit-catalog-commerce`, `dravit-frontend-ui`, `dravit-security-testing` | `form-cro`, `page-cro`, `fixing-accessibility`, `wcag-audit-patterns`, `copywriting` |
| **Step 7** | `06-order-received.html` | `dravit-catalog-commerce`, `dravit-frontend-ui` | `ui-ux-designer`, `page-cro`, `copywriting`, `baseline-ui` |
| **Step 8** | `07-blog-archive.html` | `dravit-frontend-ui`, `dravit-filter-seo-geo` | `design-taste-frontend`, `seo-structure-architect`, `ui-ux-designer`, `avoid-ai-writing` |
| **Step 9** | `08-blog-single.html` | `dravit-filter-seo-geo`, `dravit-frontend-ui`, `dravit-catalog-commerce` | `seo-structure-architect`, `page-cro`, `copywriting`, `avoid-ai-writing`, `fixing-accessibility` |
| **Step 10** | `09-showroom-about-quote.html` | `dravit-frontend-ui`, `dravit-catalog-commerce` | `design-taste-frontend`, `form-cro`, `copywriting`, `avoid-ai-writing`, `fixing-accessibility` |
| **Step 11** | `10-policies-search-404.html` | `dravit-frontend-ui`, `dravit-filter-seo-geo`, `dravit-security-testing` | `ui-ux-designer`, `baseline-ui`, `fixing-accessibility`, `web-design-guidelines` |

---

## 3. Chi tiết Thực thi Từng Step

### STEP 0: Khởi tạo Hệ thống CSS/JS Core & Mock Assets (`docs/layout/assets/`)

- **Mục tiêu:** Xây dựng nền tảng CSS `@layer`, Layout Primitives `.l-*`, Scoped Components `.drv-*` và các Light DOM Custom Elements `<drv-*>` dùng chung cho cả 11 file HTML để không viết lặp bất kỳ dòng CSS/JS nào (`D-17`).
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron `1px solid var(--c-navy-200)`, §2 Design Tokens, §3 Kiến trúc Component 4 lớp `D-17`).
  - [dravit-coding-standards.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-coding-standards.md) (§3 CSS `@layer reset, tokens, base, layout, components, woo, utilities`, §4 Vanilla ES2022+ Modules).
  - [dravit-architecture.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-architecture.md) (§4 Đơn ngôn ngữ Tiếng Việt, quy ước đặt tên).
- **Skills sử dụng (`.agents/skills/`):**
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md): Thiết lập hệ thống Design Tokens (`tokens.css`) cho màu sắc, font `Anton` + `Quicksand`, spacing `4px/8px` và lưới 12 cột `1320px`.
  - [baseline-ui](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/baseline-ui/SKILL.md): Áp dụng `font-variant-numeric: tabular-nums` cho mọi con số/giá tiền, `text-wrap: balance` cho tiêu đề, thang `z-index` cố định, chuyển động chỉ dùng `transform`/`opacity` ≤ `200ms`.
  - [javascript-pro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/03_javascript-pro/SKILL.md) & [modern-javascript-patterns](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/04_modern-javascript-patterns/SKILL.md): Viết `core.js` (Event Delegation `data-drv-action`), `unit-calc.js` (`<drv-unit-calc>`, `<drv-qty-input>`), `catalog-mock.js` (`<drv-filter-form>`).
  - [clean-code](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/05_clean-code/SKILL.md): Đảm bảo mỗi component CSS chỉ khai báo thuộc tính 1 lần qua biến cục bộ `--_*`.
- **Tài liệu tham chiếu:** [01_Brand_Design_System.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/01_Brand_Design_System.md), [02_Technical_Architecture.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/02_Technical_Architecture.md) (§8B).
- **Đầu ra nghiệm thu:**
  - `assets/css/tokens.css`, `assets/css/fonts.css`, `assets/css/main.css`, `assets/css/pages.css` (tích hợp **Phosphor Icons CDN** `@phosphor-icons/web@2.1.2`, loại bỏ hoàn toàn icon ký tự Unicode/emoji).
  - `assets/js/core.js`, `assets/js/unit-calc.js`, `assets/js/catalog-mock.js`.
  - Bộ ảnh SVG/WebP mẫu bề mặt gạch (Marble, Terrazzo, Giả gỗ, Xi măng) và phối cảnh phòng.

---

### STEP 1: Trang Master UI Kit & Global Shell (`00-ui-kit.html`)

- **Mục tiêu:** Trình diễn trực quan toàn bộ Design System, Global Header (Mega Menu `Silo Matrix 4+1` + Instant Search), Global Footer 2 tầng, Mobile Bottom Bar, và toàn bộ Component dùng chung trước khi ghép vào các trang.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1–§3: Màu sắc, Typography, Component 4 lớp).
  - [dravit-coding-standards.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-coding-standards.md) (HTML5 landmarks `<header>`, `<nav>`, `<main>`, `<footer>`, `<dialog>`).
  - [dravit-security-testing.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-security-testing.md) (Kiểm tra tương phản màu WCAG AA).
- **Skills sử dụng (`.agents/skills/`):**
  - [frontend-design](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/39_frontend-design/SKILL.md): Định hình rõ điểm neo nhận diện "Architectural Grout-Line Grid" (đường ron `1px`, góc vuông `4px–8px`, bảng màu Brand Navy `#083A4F` + Deep Ink `#0B192C` + Brand Teal `#0D9488` + Warm Amber `#F59E0B` trên nền Clean Slate `#F8FAFC`).
  - [design-taste-frontend](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/design-taste-frontend/SKILL.md): Thiết kế thẻ sản phẩm và khung mẫu vật liệu tinh gọn, micro-interaction chuẩn (card lift `2px`, ảnh đổi sang phối cảnh khi hover, header menu hover nền `#083A4F` chữ trắng, menu cấp 2-3).
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md): Tổ chức thư viện Atoms → Molecules → Organisms trên trang UI Kit.
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md) & [wcag-audit-patterns](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/17_wcag-audit-patterns/SKILL.md): Đảm bảo chữ chính dùng `--c-text-main` (`#0B192C`, `16.3:1`), Header & H1–H6 dùng `--c-navy-900` (`#083A4F`, `11.04:1`), chữ teal dùng `--c-teal-700` (`#0F766E`, `6.37:1`), mọi nút icon có `aria-label`, `<dialog>` đóng bằng phím `Esc`.
  - [fixing-motion-performance](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/12_fixing-motion-performance/SKILL.md) & [animejs-animation](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/37_animejs-animation/SKILL.md): Triển khai kiến trúc **Motion Option A + B kết hợp** (100% Hover/UI bằng CSS GPU `transform`/`opacity` + nạp nhẹ `Anime.js v3.2.2` ~6.5KB gzip cho Staggered Grid Reveal, Đếm số Odometer và Spring Cart Bounce).
- **Tài liệu tham chiếu:** [01_Brand_Design_System.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/01_Brand_Design_System.md), [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§2, §4.2).
- **Hạng mục UI/UX hoàn thiện:**
  1. Thanh **Prototype Navigator Bar** nhỏ gọn giúp chuyển nhanh giữa 11 trang HTML.
  2. Global Header 2 tầng + **Mega Menu "Silo Matrix 4+1" (Hover Desktop + Live Dynamic Preview Cột 5 + Bottom Utility Strip)** + **Instant Search Dropdown** (gõ thử từ khóa hiện 2 cột Danh mục & Sản phẩm).
  3. Bảng màu & Typography scale (`Anton` + `Quicksand`), hệ thống `.drv-btn`, `.drv-badge`, Form controls.
  4. **Product Card chuẩn DRAVIT** (nhấc nhẹ `-3px` `--ease-out-expo`, zoom ảnh `1.04` & crossfade sang ảnh phối cảnh khi hover, tag kích thước/bề mặt, SKU, giá kép `đ/m²` & `đ/viên`).
  5. **Visual Sub-Silo Tile**, **Shop the Look Bar**, **`<drv-unit-calc>`** (tích hợp đếm số Odometer & highlight pulse), **`<drv-qty-input>`**.
  6. 3 `<dialog>` dùng chung: **Modal Báo giá công trình**, **Mini-Cart Drawer**, **Mobile Menu Drawer (Accordion Cấp 2/3)**.
  7. **Section 07 (`#sec-motion`) — Motion, Hover GPU & Micro-interactions Showcase:** Trình diễn trực tiếp CSS GPU Hover, Anime.js Staggered Reveal (`data-drv-stagger`) và Number Odometer + Spring Cart Bounce.
  8. Global Footer 3 tầng (Tầng 1: Showroom, Hotline & B2B Consultation Dock 3 ô sáng · Tầng 2: Dải Corporate Bar & 4 Cột Sitemap E-commerce: Về DRAVIT, Hướng dẫn, Chính sách, Dịch vụ & Đối tác B2B kèm Social Media Buttons · Tầng 3: Huy hiệu Thanh toán VietQR/COD/VAT & Pháp lý) + Mobile Sticky Bottom Bar 5 ô.

---

### STEP 2: Trang Chủ — `01-home.html`

- **Mục tiêu:** Xây dựng trang chủ 8 sections vừa dẫn dắt chủ nhà chọn gạch theo không gian, vừa cho phép thợ thầu/KTS tra cứu nhanh theo kích thước và bề mặt kỹ thuật.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §4 Tối ưu LCP/CLS, §5 Brand Voice).
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§1 Hiển thị giá kép `đ/m²` & `đ/viên` trên mọi card sản phẩm).
  - [dravit-filter-seo-geo.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-filter-seo-geo.md) (Cấu trúc liên kết Silo từ trang chủ vào 28 Smart Collections).
- **Skills sử dụng (`.agents/skills/`):**
  - [design-taste-frontend](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/design-taste-frontend/SKILL.md): Mỗi section một bố cục riêng biệt (Bento 8/4, Lưới bất đối xứng, Lookbook 3/9, Sơ đồ tỷ lệ CSS, Editorial 8/4), không lặp lại cấu trúc.
  - [frontend-design](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/39_frontend-design/SKILL.md): Xây dựng **Section 5 (Tương quan Kích thước & 4 Bề mặt R9–R13)** làm điểm nhấn thị giác độc bản của DRAVIT.
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md): Đặt Value Proposition rõ trong ≤ 5 giây ở Hero + Thanh tìm gạch nhanh + Dải 4 Cam kết chuẩn mực ngay dưới Hero.
  - [copywriting](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/28_copywriting/SKILL.md) & [avoid-ai-writing](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/avoid-ai-writing/SKILL.md): Áp dụng chuẩn copy trang chủ từ `10_Brand_Voice_And_Content_Guide.md` §4.1 (không dùng từ sáo rỗng, không dùng đồng hồ Flash Sale giả ở mục Gạch Ưu Đãi).
  - [web-performance-optimization](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/11_web-performance-optimization/SKILL.md): Ảnh Hero có `fetchpriority="high"`, các ảnh dưới fold có `loading="lazy"`, cố định `aspect-ratio` chống CLS.
- **Tài liệu tham chiếu:** [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§3), [10_Brand_Voice_And_Content_Guide.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/10_Brand_Voice_And_Content_Guide.md) (§4.1).
- **Hạng mục UI/UX hoàn thiện (Đủ 8 Sections):**
  1. **Section 1 — Architectural Bento Hero (8/4) + Thanh Tìm Gạch Nhanh** (chọn Không gian + Kích thước + Bề mặt → dẫn tới trang danh mục).
  2. **Section 2 — Dải 4 Cam kết Chuẩn mực (Trust Strip)** ngăn bằng đường ron `1px`.
  3. **Section 3 — Chọn Gạch Theo Không Gian (Visual Space Grid 8 ô Bento bất đối xứng)** kèm thông số kỹ thuật gợi ý trên từng ô.
  4. **Section 4 — Catalog Explorer Block** (Chuyển tab mượt giữa *Gạch lát nền / Gạch ốp tường / Gạch trang trí / Đá nung kết* + hàng Chip kích thước/vân + Lookbook Card trái 3/12 + Lưới 6 sản phẩm phải 9/12).
  5. **Section 5 — Sơ đồ Tương quan Kích thước Thực tế (`30x60` → `60x120`) & 4 Thẻ Bề mặt (`R9–R13`)**.
  6. **Section 6 — Gạch Ưu Đãi & Lẻ Lô Tại Kho** (nền `--c-champagne`, hiển thị rõ tồn kho `Còn X m² · Y viên`).
  7. **Section 7 — Không gian Showroom Thực tế** (có nút chuyển 3 góc trưng bày kệ gạch + thông tin giờ mở cửa & Google Maps).
  8. **Section 8 — Cẩm nang Chọn gạch & Thi công** (Layout tạp chí 8/4 với danh sách đánh số `01–05` font Anton).

---

### STEP 3: Trang Danh mục Silo & Bộ lọc AJAX — `02-catalog-collection.html`

- **Mục tiêu:** Đại diện cho 28 trang Smart Collections (`/gach-phong-khach`), kết hợp điều hướng Silo trực quan với bộ lọc 14 tiêu chí tức thì mà không phải tải lại trang.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-filter-seo-geo.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-filter-seo-geo.md) (§1 14 bộ lọc `FLT-01..14`, §5 Internal Linking `IL-01/IL-02`).
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §3 `<drv-filter-form>`).
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§1 Hiển thị giá kép trên lưới sản phẩm).
- **Skills sử dụng (`.agents/skills/`):**
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md): Thiết kế Sidebar 14 bộ lọc gọn gàng (nhóm cơ bản mở sẵn, nhóm thông số chuyên sâu `R9–R13` / độ hút nước / độ dày đặt trong accordion `<details>`), kèm số đếm `(n)` và trạng thái `disabled` khi `count = 0`.
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md): Đưa sản phẩm lên ngay màn hình đầu tiên (không chèn banner quảng cáo lớn), tích hợp dải **Visual Sub-Silo Tiles** có ảnh vân `48×48px` để thu hẹp nhu cầu chỉ bằng 1 cú chạm.
  - [seo-structure-architect](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/44_seo-structure-architect/SKILL.md): Tổ chức Header danh mục (Breadcrumb + H1 + đoạn mở đầu ≤ 60 chữ + bảng `<dl>` 4 chỉ số tóm tắt) và khối **Bài viết SEO Footer 4 phần + FAQ Accordion** ở cuối trang.
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md) & [fixing-motion-performance](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/12_fixing-motion-performance/SKILL.md): Vùng kết quả lọc có `aria-live="polite"`, hiệu ứng lọc/Load More dùng skeleton/opacity mượt không giật khung.
- **Tài liệu tham chiếu:** [05_Faceted_Filter_AJAX.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/05_Faceted_Filter_AJAX.md), [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§4), [10_Brand_Voice_And_Content_Guide.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/10_Brand_Voice_And_Content_Guide.md) (§4.2).
- **Hạng mục UI/UX hoàn thiện:**
  1. Header Silo (`/gach-phong-khach`) + Bảng `<dl>` tóm tắt nhanh (124 mẫu · Từ 260.000đ/m² · Khổ 60x60, 80x80, 60x120 · Có mẫu tại kệ).
  2. Dải **Visual Sub-Silo Tiles** cuộn ngang (`Gạch 80x80`, `Gạch 60x120`, `Vân đá Marble`, `Giả gỗ`, `Terrazzo`).
  3. **Sidebar 14 Bộ lọc (`<drv-filter-form>`)** hoạt động thật trên trình duyệt: khi tick chọn kích thước/bề mặt/màu sắc → tự động cập nhật dải **Active Filter Chips** (`[80x80 ×] [Bóng kiếng ×] [Xóa tất cả]`), lọc danh sách thẻ sản phẩm và cập nhật bộ đếm.
  4. Lưới 12 Product Cards + Thanh tiến độ *"Đang hiển thị 12 / 124 mẫu"* + Nút **`[Xem thêm 24 sản phẩm ↓]`** (bấm vào chèn thêm sản phẩm mẫu).
  5. Khối CTA Báo giá công trình + Bài viết hướng dẫn 4 phần (có nút *Xem toàn bộ hướng dẫn ▾*) + Khối FAQ.
  6. **Mobile Filter Bottom Sheet (`<dialog>`)** mở từ nút cố định trên mobile.

---

### STEP 4: Trang Chi tiết Sản phẩm — `03-product-single.html`

- **Mục tiêu:** Giải quyết trọn vẹn bài toán bán gạch theo đơn vị **viên** nhưng khách suy nghĩ theo **`m²`**, đồng thời phục vụ cả 3 tệp khách (Chủ nhà, Thợ thầu, Kiến trúc sư).
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§1 Giá kép & công thức quy đổi `m² ⇄ viên ⇄ thùng ⇄ kg` trong `<drv-unit-calc>`).
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§3 Light DOM Custom Element `<drv-unit-calc>`, Event Delegation `copy-sku`, `dialog-open`).
  - [dravit-filter-seo-geo.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-filter-seo-geo.md) (§5 Bảng thông số gắn link nội bộ về các trang Silo `IL-03`, Breadcrumb `IL-04`, SP cùng Silo `IL-06`).
- **Skills sử dụng (`.agents/skills/`):**
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md): Thiết kế **Sticky Buy Box** hiển thị rõ giá/m², giá/viên, quy cách thùng, tích hợp **Bộ chuyển đổi Kép trực tiếp `m² ⇄ Viên`** ngay tại chỗ (không bắt mở popup phụ), kèm **Sticky Purchase Bar** khi cuộn trang.
  - [frontend-design](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/39_frontend-design/SKILL.md): Thiết kế **Architectural Bento Gallery (7/12)** trên desktop (1 ảnh phối cảnh lớn + 4 ảnh cận vân/góc nghiêng/cạnh gạch 10mm/đường ron) kèm nút **`[↓ Tải Map gạch (CAD/3D)]`** cho KTS.
  - [javascript-pro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/03_javascript-pro/SKILL.md): Xử lý tính toán 2 chiều tức thì trong `<drv-unit-calc>` (nhập `m²` → nhảy số `viên`, số `thùng`, `diện tích thực nhận`, `trọng lượng kg` và `Tổng tạm tính`; nhập `viên` → nhảy ngược lại `m²`; đồng bộ với Sticky Purchase Bar).
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md) & [copywriting](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/28_copywriting/SKILL.md): Đầy đủ `<label>` và `aria-live="polite"` cho kết quả quy đổi; hộp 3 Lưu ý Trách nhiệm (lô màu `Shade V2`, độ rộng đường ron `1,5–2mm`, kiểm hàng) viết đúng giọng văn Chuyên gia & Trách nhiệm.
- **Tài liệu tham chiếu:** [04_Pricing_Units_Commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/04_Pricing_Units_Commerce.md) (§1–§4), [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§5), [10_Brand_Voice_And_Content_Guide.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/10_Brand_Voice_And_Content_Guide.md) (§4.3).
- **Hạng mục UI/UX hoàn thiện:**
  1. **Bento Gallery 5 ảnh** + nút `[↓ Tải Map gạch (CAD/3D)]` + Lightbox xem ảnh phóng to.
  2. **Sticky Buy Box:** Mã SKU `DRV-8801M` + nút `[Copy]` + Badge Showroom + Giá niêm yết kép + Chọn kích thước/bề mặt cùng mẫu.
  3. **Bộ tính `<drv-unit-calc>` chạy thật 100%** (thử nhập `10 m²` → tự tính `18 viên = 6 thùng (11,52 m² · 252 kg)` → `4.435.200đ`).
  4. Cụm nút `[ĐẶT MUA NGAY]`, `[+ THÊM VÀO GIỎ]`, nút mở **Modal Nhận báo giá chiết khấu công trình**, và Bảng 14 thông số kỹ thuật có link Silo.
  5. Hộp 3 Lưu ý Trách nhiệm + Mô tả thực tế + Gạch cùng bộ sưu tập + 4 mẫu cùng Silo (`IL-06`) + Sản phẩm đã xem.
  6. **Sticky Purchase Bar** ở đáy màn hình (Desktop & Mobile) tự động đồng bộ số viên và tổng tiền với bộ tính phía trên.

---

### STEP 5: Trang Giỏ hàng & Mini-Cart Drawer — `04-cart.html`

- **Mục tiêu:** Hiển thị minh bạch số lượng giao dịch theo **viên** kèm quy đổi sang **số thùng, diện tích `m²` và tổng trọng lượng `kg`** để khách tự tin kiểm tra khối lượng trước khi đặt đơn.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§1 Hiển thị quy đổi viên/thùng/m²/kg trong giỏ hàng, §3 Thông báo báo phí vận chuyển sau).
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §3 `<drv-qty-input>`, §5 Tone Dịch vụ: Thấu hiểu).
- **Skills sử dụng (`.agents/skills/`):**
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md): Giải tỏa tâm lý lo lắng về phí ship và lệch lô màu ngay trong cột Tóm tắt đơn hàng; cung cấp thêm nút phụ `[Xuất bảng báo giá PDF / Gửi qua Zalo]` cho khách cần trình duyệt chi phí với gia đình/chủ đầu tư.
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md) & [baseline-ui](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/baseline-ui/SKILL.md): Căn cột số liệu bằng `tabular-nums`, thiết kế thêm trạng thái **Giỏ hàng trống (Empty State)** có hướng dẫn hành động rõ ràng.
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md): Nút tăng/giảm số lượng viên và nút xóa dòng có `aria-label` đầy đủ; cập nhật tổng tiền có `aria-live="polite"`.
- **Tài liệu tham chiếu:** [04_Pricing_Units_Commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/04_Pricing_Units_Commerce.md) (§5), [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§6).
- **Hạng mục UI/UX hoàn thiện:**
  1. Bảng giỏ hàng gồm 2 mặt hàng mẫu: (1) Gạch lát nền `DRV-8801M` (`area_pack`: `18 viên = 6 thùng · 11,52 m² · 252 kg`) và (2) Keo chà ron Dravit (`pack`: `3 túi`) để minh họa cơ chế đa đơn vị.
  2. Tương tác tăng/giảm số lượng viên (hoặc nút nhanh `[+1 thùng (3 viên)]`) tự động tính lại số thùng, diện tích `m²`, tổng `kg` và thành tiền.
  3. Cột **Tóm tắt đơn hàng Sticky**: Tổng số mã · Tổng diện tích `m²` · Tổng số thùng · Tổng trọng lượng `kg` · Ghi chú vận chuyển · Nút `[TIẾN HÀNH ĐẶT HÀNG]`.
  4. Nút bật xem thử **Mini-Cart Drawer** và nút chuyển xem thử trạng thái **Giỏ hàng trống**.

---

### STEP 5.1 (OPTION 4.1): Trang Danh sách Báo giá Tổng & Dự toán Công trình (BOQ) — `04.1-quote-cart.html`

- **Mục tiêu:** Phục vụ khách hàng mua sỉ, nhà thầu, KTS hoặc chủ nhà xây trọn gói cần lập **Bảng báo giá tổng hợp (BOQ)** giữ nguyên đầy đủ thông số kỹ thuật + đơn giá niêm yết (`đ/m²` & `đ/viên`), hỗ trợ tải danh sách PDF chuẩn A4 / Excel (`.csv`) và kết nối trực tiếp nhân viên tư vấn dự án.
- **Hạng mục UI/UX hoàn thiện (bản 2026-11-09 — Kiến trúc Room Cards & Đa loại gạch mỗi phòng / Multi-Tile per Room):**
  1. **Header phiếu (`#BG-2026-1109`)**: Mã phiếu, ngày lập, hiệu lực 7 ngày, tên công trình/chủ nhà in lên PDF; tùy chọn hao hụt cắt gạch (0% / 5% / 10%); nút `Tải PDF (A4)` / `Xuất Excel (.CSV)`.
  2. **Kiến trúc Room Card độc lập (Multi-Tile per Room — Mô hình Build PC Online cho Gạch)**:
     - 4 Không gian mặc định cố định: *Phòng khách*, *Phòng ngủ Master*, *Nhà tắm / WC*, *Phòng bếp*.
     - **Một phòng nhiều loại gạch**: Mỗi phòng là 1 Card chứa danh sách các vị trí thi công (*Lát nền chính*, *Ốp tường chính*, *Ốp vách Tivi*, *Lát ban công / sân phơi*, *Viền / Trang trí*...).
     - Nút `[+ Thêm loại gạch]` ngay trên đầu mỗi phòng để thêm slot mới linh hoạt.
     - Từng vị trí hỗ trợ: Dropdown chọn vai trò, thumbnail mẫu gạch, mã SKU, tên, kích thước, màu sắc swatch, đơn giá (đ/m² & đ/viên), ô số lượng m² tích hợp suffix m² tự động quy đổi số thùng/viên, nút `[Đổi mẫu khác]`, `[Gỡ gạch]` và `[Xóa vị trí]`.
     - Vị trí trống: Hiển thị nút `[+ Chọn mẫu gạch]` nổi bật mở Modal Picker.
  3. **Modal Picker (`#drv-tile-picker-modal`)**: Modal chọn gạch với ô tìm kiếm SKU/tên tức thì, chip lọc nhanh theo kích thước (Tất cả, 80×80, 60×120, 60×60, 15×90, 30×60, 30×90), lưới card sản phẩm kèm nút `[Chọn mẫu này]`, tự động nạp vào vị trí đang mở và focus ô m².
  4. **Thanh thêm không gian tùy chỉnh (`.drv-add-room-card`)**: Hàng chip preset nhanh (`+ Phòng ngủ 2`, `+ Ban công`, `+ Sân vườn`, `+ Cầu thang`, `+ Sảnh`, `+ Phòng thờ`, `+ Mặt tiền`) + Form tự nhập tên phòng bất kỳ. Phòng tùy chỉnh có nút xóa cả phòng.
  5. **Card Vật tư phụ thi công đồng bộ (`.drv-room-card--accessories`)**: Keo dán gạch C2, Ke cân bằng ron 1,5mm với đơn giá và ô nhập số lượng tự động tính tổng tiền.
  6. **Card Tổng hợp phẳng & Bậc chiết khấu sản lượng (`.drv-totals-card`)**: Tự động tính tổng diện tích (m²), tổng số thùng, tổng khối lượng (kg), xe tải kiến nghị, tạm tính niêm yết, chiết khấu theo bậc (0% < 30m² / -5% 30–100m² / -8% > 100m²) với thanh tiến trình 1 dòng phẳng gọn gàng.
  7. **CTA Báo giá sỉ `[LIÊN HỆ BÁO GIÁ SỈ (ZALO)]`** → Mở `<dialog>` form (`#drv-qs-lead`): tự động tóm tắt danh sách toàn bộ các phòng và gạch đã chọn, trường liên hệ (Họ tên, SĐT Zalo, Tỉnh/thành, Loại đối tác, Ghi chú công trình). Kèm tính năng Chép báo giá gửi Zalo và bản in PDF chuẩn A4 (`@media print`).
  8. **Clean, Flat & Responsive Perfect**: Chuẩn phẳng tối giản, triệt tiêu mọi dải text demo/thừa, responsive mượt mà từ màn hình Mobile 375px/390px đến Desktop 1440px. Logic: `assets/js/quote-sheet.js`, CSS: `assets/css/pages.css`.

---

### STEP 6: Trang Thanh toán 2 Cột — `05-checkout.html`

- **Mục tiêu:** Tối ưu hóa tỷ lệ hoàn tất đơn hàng (Checkout CRO) với biểu mẫu 6 trường tinh gọn, địa chỉ hành chính Việt Nam **2 cấp (`34 Tỉnh/TP → Phường/Xã`)** và thông điệp rõ ràng về quy trình xác nhận phí vận chuyển.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§2 Địa chỉ VN 2 cấp 34 tỉnh/thành, §3 Phương thức vận chuyển báo phí sau & Thanh toán COD/VietQR).
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§5 Tone Dịch vụ: Thấu hiểu).
  - [dravit-security-testing.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-security-testing.md) (Validation dữ liệu đầu vào trên form).
- **Skills sử dụng (`.agents/skills/`):**
  - [form-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/47_form-cro/SKILL.md): Thiết kế đạt **Form Health & Friction Index ≥ 92/100** (loại bỏ hoàn toàn trường Mã bưu điện/Quận huyện cũ/Công ty rườm rà; thêm các chip gợi ý nhanh cho ô Ghi chú giao hàng công trình như *"Hẻm xe tải nhỏ"*, *"Giao giờ hành chính"*, *"Gọi trước 60 phút"*).
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md): Đặt hộp **Cam kết kiểm tra trùng lô màu (Shade/Caliber) & Báo phí vận chuyển tối ưu** ngay trên nút Đặt hàng để xóa bỏ rào cản tâm lý cuối cùng.
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md) & [wcag-audit-patterns](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/17_wcag-audit-patterns/SKILL.md): Đúng `autocomplete`, `inputmode="tel"` cho SĐT, thông báo lỗi inline gắn `aria-describedby`.
  - [copywriting](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/28_copywriting/SKILL.md): Áp dụng chuẩn microcopy từ `10_Brand_Voice_And_Content_Guide.md` §4.4.
- **Tài liệu tham chiếu:** [04_Pricing_Units_Commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/04_Pricing_Units_Commerce.md) (§6–§8), [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§6).
- **Hạng mục UI/UX hoàn thiện:**
  1. Header Checkout tối giản (chỉ giữ Logo + Bước thanh toán + Hotline hỗ trợ để tránh khách thoát trang).
  2. **Cột trái (7/12):** Thông tin người nhận + **Cascading Select `Tỉnh/Thành phố (34) → Phường/Xã` hoạt động thật** + Địa chỉ công trình + Chip ghi chú giao hàng nhanh + Tùy chọn xuất hóa đơn VAT (ẩn gọn trong checkbox) + Chọn phương thức thanh toán (**COD** / **Chuyển khoản VietQR**).
  3. **Cột phải (5/12 Sticky):** Tóm tắt mặt hàng (viên/thùng/m²/kg) + Hộp thông báo thấu hiểu nền `--c-champagne` + Nút **`[ĐẶT HÀNG — CHỜ XÁC NHẬN PHÍ GIAO]`**.

---

### STEP 7: Trang Đặt hàng Thành công & VietQR — `06-order-received.html`

- **Mục tiêu:** Hướng dẫn rõ ràng các bước tiếp theo sau khi đặt hàng và trình bày khối thanh toán chuyển khoản **VietQR** minh bạch ở cả 2 trạng thái đơn.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§3 Luồng trạng thái `wc-cho-xac-nhan` → `wc-on-hold` và tạo mã VietQR động).
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §3 `data-drv-action="copy-text"`, §5 Tone Dịch vụ).
- **Skills sử dụng (`.agents/skills/`):**
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md): Thiết kế **Timeline 4 bước** trực quan (`1. Đã tiếp nhận` → `2. Báo phí vận chuyển & Chốt lô gạch` → `3. Thanh toán / Đặt cọc` → `4. Xuất kho giao công trình`).
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md) & [copywriting](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/28_copywriting/SKILL.md): Giảm tỷ lệ hủy đơn bằng thông báo thời gian gọi lại cụ thể (15–30 phút trong giờ làm việc) và nút chat Zalo kèm sẵn mã đơn `#DRV-202688`.
  - [baseline-ui](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/baseline-ui/SKILL.md): Trình bày bảng kê chi tiết đơn hàng và thông tin tài khoản ngân hàng rõ ràng, có nút `[Sao chép]` cho Số tài khoản, Số tiền và Nội dung chuyển khoản.
- **Tài liệu tham chiếu:** [04_Pricing_Units_Commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/04_Pricing_Units_Commerce.md) (§8, §9).
- **Hạng mục UI/UX hoàn thiện:**
  1. Thanh công cụ nhỏ đầu trang cho phép **chuyển đổi xem thử 2 trạng thái**:
     - *Trạng thái A (`Chờ xác nhận phí giao`):* Hiện thông báo chờ nhân viên gọi báo phí vận chuyển; khung QR để ở chế độ tùy chọn hoặc chờ tổng cuối.
     - *Trạng thái B (`Đã cập nhật phí vận chuyển`):* Hiện đầy đủ phí vận chuyển (`350.000đ`), tổng thanh toán cuối cùng và mã **VietQR** điền sẵn số tiền + nội dung `DRV 202688`.
  2. Timeline 4 bước + Bảng chi tiết mặt hàng (viên/thùng/m²/kg) + Thông tin người nhận & nút liên hệ Zalo/Hotline.

---

### STEP 8: Trang Danh sách Cẩm nang — `07-blog-archive.html`

- **Mục tiêu:** Xây dựng trung tâm tri thức ngành gạch ốp lát (`/cam-nang`) theo phong cách Tạp chí Kiến trúc, dễ quét thông tin và điều hướng người đọc sang các trang sản phẩm.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §2 Typography `Anton` cho số thứ tự `01–05`).
  - [dravit-filter-seo-geo.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-filter-seo-geo.md) (Cấu trúc chuyên mục Cẩm nang bổ trợ cho 4 nhóm Silo sản phẩm).
- **Skills sử dụng (`.agents/skills/`):**
  - [design-taste-frontend](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/design-taste-frontend/SKILL.md): Ảnh bài viết sạch 100% (không chèn chữ/watermark rối mắt lên ảnh như mẫu đối thủ), bố cục **Editorial Hero Bento 12 cột (5/4/3)**.
  - [seo-structure-architect](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/44_seo-structure-architect/SKILL.md): Phân cụm chủ đề rõ ràng (Topic Clusters): *Chọn gạch theo phòng · Kỹ thuật ốp lát & đường ron · Phối màu & xu hướng · Định mức & báo giá*.
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md) & [avoid-ai-writing](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/avoid-ai-writing/SKILL.md): Tiêu đề và tóm tắt bài viết cụ thể, hữu ích cho chủ nhà và thợ thầu.
- **Tài liệu tham chiếu:** [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§7.1).
- **Hạng mục UI/UX hoàn thiện:**
  1. **Thanh Sub-nav 5 chuyên mục Cẩm nang** (sticky nền `--c-ivory` viền `1px`) + Ô tìm kiếm tức thì theo từ khóa và tiêu chuẩn kỹ thuật có nút xóa nhanh.
  2. **Editorial Hero Bento (5/4/3):**
     - Cột trái (5/12): 1 bài hướng dẫn trụ cột lớn (ảnh 16:9, badge trọng tâm, tiêu đề H2, tóm tắt 3 dòng, thông tin tác giả Kỹ thuật Dravit, link sản phẩm liên quan).
     - Cột giữa (4/12): 3 bài viết mới (ảnh vuông nhỏ bên trái + tiêu đề bên phải, ngăn nhau bằng đường ron `1px`).
     - Cột phải (3/12 — Hộp *"Chủ đề được tra cứu nhiều"* nền `--c-champagne`): Danh sách đánh số `01–05` (font `Anton`) các bài viết nhiều người đọc nhất.
  3. **3 Khối chuyên mục chuyên sâu (Topic Clusters):** Mỗi khối 3 card bài viết tỷ lệ ảnh `3:2` sạch đẹp, không watermark, kèm thanh chip sản phẩm chuyển đổi (CRO) dẫn trực tiếp sang danh mục gạch và vật tư phụ tương ứng.
  4. **Khối công cụ tra cứu nhanh (Quick Tools & Reference):**
     - *Công cụ 1:* **Máy tính nhanh số viên gạch theo diện tích sàn** (cho phép chọn kích thước 80x80, 60x60, 30x60, 60x120, 15x90; tùy chọn +5% hao hụt; tự động tính ra số viên, số thùng, diện tích thực nhận và kg vận chuyển kèm nút xem sản phẩm tương ứng).
     - *Công cụ 2:* **Bảng tra cứu chỉ số chống trượt R9 – R13** (chuẩn DIN 51130, góc dốc, đặc tính bề mặt và khuyến nghị không gian sử dụng).
  5. **Khối chuyển đổi (CRO Lead Capture & Showroom):**
     - Form đăng ký nhận trọn bộ **Ebook Cẩm nang chọn gạch & Giám sát thi công 2026** (PDF 48 trang + file Excel dự toán) qua Zalo.
     - 2 Thẻ bảo chứng dịch vụ: Trải nghiệm xem mẫu gạch dưới ánh đèn chuẩn 3000K-4000K tại Showroom Sala + Dịch vụ mang 2-3 mẫu gạch tận chân công trình miễn phí.

---

### STEP 9: Trang Chi tiết Bài viết Cẩm nang — `08-blog-single.html`

- **Mục tiêu:** Tối ưu hóa khả năng đọc lướt, khả năng được AI trích dẫn (GEO) và khả năng chuyển đổi từ người đọc bài viết sang người mua gạch thực tế.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-filter-seo-geo.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-filter-seo-geo.md) (§4 Cấu trúc GEO: Key Takeaways đầu bài, bảng thông số HTML, FAQ cuối bài; §5 Internal Linking `IL-05`).
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §3 Component `shop-the-look`).
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (§1 Hiển thị giá kép trên thanh Shop the Look và khối sản phẩm nhúng).
- **Skills sử dụng (`.agents/skills/`):**
  - [seo-structure-architect](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/44_seo-structure-architect/SKILL.md): Tổ chức bài viết theo chuẩn GEO với hộp **Những điểm chính (Key Takeaways)** ngay sau H1, Mục lục tự động (TOC), bảng dữ liệu chuẩn `<table>`, và khối **Câu hỏi thường gặp (FAQ)** cuối bài.
  - [page-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/46_page-cro/SKILL.md): Thay thế nút đè lên ảnh của mẫu cũ bằng thanh **"Shop the Look Bar" (Thẻ Mã Gạch Trong Ảnh)** ngay dưới chân ảnh phối cảnh + **Widget Tính nhanh số viên gạch** ở cột phải Sticky.
  - [copywriting](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/28_copywriting/SKILL.md) & [avoid-ai-writing](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/avoid-ai-writing/SKILL.md): Viết nội dung bài mẫu chuẩn chuyên gia kỹ thuật Dravit.
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md): Nút mở Mục lục trên mobile (`☰ Mục lục`) mở bottom sheet `<dialog>` hỗ trợ bàn phím.
- **Tài liệu tham chiếu:** [06_SEO_GEO_Spec.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/06_SEO_GEO_Spec.md) (§5), [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§7.2).
- **Hạng mục UI/UX hoàn thiện (Phương án 1 — Clean Flat Architectural Sheet Grid 1px):**
  1. **Layout 8/4 Clean Flat (Zero Shadow):** Cột bài viết `8/12` đóng khung phẳng `1px solid var(--c-navy-200)` thẳng biên 100% (không bị hụt lề phải) + Cột Sidebar `4/12` tách nhóm Tĩnh (Showroom Sala) và nhóm Sticky (TOC tiến độ đọc `%` + Máy tính đa khổ gạch `80x80`/`60x60`/`60x120` & định mức keo C2).
  2. **Hộp "Những điểm chính" (Key Takeaways — GEO Box)** phẳng sạch nền `--c-champagne`, viền trái `4px` `--c-moss-600`, chứa internal links (`IL-05`) về `/gach-terrazzo` và `/gach-80x80`.
  3. **2 Bức ảnh phối cảnh thực tế + Lightbox 6 slides + "Shop the Look Bar" kép:** Cho phép bấm phóng to ảnh (`#drv-blog-lightbox` hỗ trợ phím mũi tên), thanh chân ảnh hiển thị SKU + Lô Shade + Giá kép (`đ/m²` & `đ/viên`) + 2 nút `[+ Thêm vào Báo giá]` và `[Xem & Tính viên]`.
  4. **Trọn bộ WordPress / Gutenberg Core Blocks kiến trúc phẳng:** `wp-block-pullquote` (3 ô chỉ số kỹ thuật), `wp-block-quote` có `<cite>`, Gallery 4 ảnh chi tiết bề mặt/cạnh mài 90°/xương đá 10mm, Bảng đối chiếu Ưu/Nhược điểm (`Pros & Cons` 2 cột), 3 biến thể Callout (`Note`, `Warning`, `Pro-Tip`), Hộp công thức & định mức vật tư, Quy trình `HowTo` 4 bước (font `Anton`), 2 Bảng dữ liệu GEO có thanh nhắc vuốt ngang trên mobile, Khối 4 sản phẩm nhúng `[drv_products]` lưới ron 1px, Form đăng ký nhận mẫu gạch cắt thực tế giữa bài, và FAQ Accordion 5 câu.
  5. **Hạ tầng E-E-A-T, Đánh giá & Liên kết cuối trang:** Khảo sát hữu ích (`Helpful Poll`), Thẻ chủ đề (`Post Tags`), Hộp **Dual E-E-A-T** (Tác giả biên soạn + Kiến trúc sư thẩm định với avatar vuông `4px`), Điều hướng Bài trước/Bài tiếp theo, và **Section Bài viết liên quan 3 cột full-width** kèm dải chip Silo danh mục.
  6. **Thanh nổi Mobile (`drv-mobile-toc-fab`):** Tích hợp 2 nút cân đối **`[Mục lục (%)]`** (mở bottom sheet `<dialog>`) và **`[Tính số viên & Keo]`**.

---

### STEP 10: Trang Showroom, Giới thiệu & Báo giá Công trình — `09-showroom-about-quote.html`

- **Mục tiêu:** Xây dựng niềm tin thương hiệu (E-E-A-T), kết nối trải nghiệm Online-to-Offline tại Showroom và thu thập yêu cầu báo giá công trình chất lượng cao.
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §2 Bảng màu & Typography, §5 Brand Voice).
  - [dravit-catalog-commerce.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-catalog-commerce.md) (Tùy chọn đơn vị `m²` / số lượng công trình trong form báo giá).
- **Skills sử dụng (`.agents/skills/`):**
  - [design-taste-frontend](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/design-taste-frontend/SKILL.md): Trình bày câu chuyện thương hiệu (Story, Mission, Vision, 6 trụ cột chữ tín) bằng typography lớn và khung ảnh thực tế kho bãi/showroom, không dùng icon minh họa chung chung.
  - [form-cro](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/47_form-cro/SKILL.md): Thiết kế **Form Nhận Báo Giá Công Trình / Đặt Lịch Xem Mẫu** phân theo vai trò người gửi (*Chủ nhà xây mới · Thợ thầu / Đội thi công · Kiến trúc sư / Thiết kế*) để hiển thị trường phù hợp nhất.
  - [copywriting](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/28_copywriting/SKILL.md) & [avoid-ai-writing](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/avoid-ai-writing/SKILL.md): Sử dụng đúng bản copy chuẩn tại `10_Brand_Voice_And_Content_Guide.md` §2 & §4.5.
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md): Đảm bảo các tab chuyển khu vực Showroom và form báo giá truy cập tốt bằng bàn phím.
- **Tài liệu tham chiếu:** [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§8), [10_Brand_Voice_And_Content_Guide.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/10_Brand_Voice_And_Content_Guide.md) (§2, §4.5).
- **Hạng mục UI/UX hoàn thiện:**
  1. **Khối Về DRAVIT (`/gioi-thieu`):** Hero định vị *"Thẩm mỹ từ những đường nét nhỏ nhất"* + Khối **Story / Vision / Mission** nguyên bản + 4 con số năng lực thực tế + 3 Cam kết trách nhiệm.
  2. **Khối Hệ thống Showroom (`/showroom`):** Bộ chuyển xem 3 khu vực trưng bày thực tế (*Khu Gạch Khổ Lớn Big Slab · Khu Gạch Phòng Tắm & Sân Vườn · Bảng Mẫu Bề Mặt & Đường Ron*) + Thông tin địa chỉ, giờ mở cửa (`8:00 – 21:00`), chỗ đậu xe ô tô & khung Google Maps.
  3. **Khối Form Nhận Báo Giá Công Trình & Đặt Lịch Xem Mẫu:** Chọn vai trò khách hàng, chọn hạng mục cần báo giá, nhập diện tích ước tính (`m²`) hoặc tải ảnh danh sách vật tư.

---

### STEP 11: Trang Chính sách, Kết quả Tìm kiếm & 404 — `10-policies-search-404.html`

- **Mục tiêu:** Hoàn thiện các trang tiện ích bắt buộc của một website E-commerce chuẩn chỉnh, đảm bảo khách hàng không bao giờ rơi vào điểm cụt (dead-end).
- **Rules áp dụng (`.agents/rules/`):**
  - [dravit-frontend-ui.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-frontend-ui.md) (§1 Lưới đường ron, §5 Tone Dịch vụ).
  - [dravit-filter-seo-geo.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-filter-seo-geo.md) (Điều hướng về 4 nhóm Silo chính trên trang 404/Empty Search).
  - [dravit-security-testing.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/rules/dravit-security-testing.md) (Rà soát tổng thể 11 trang ở 4 mốc viewport).
- **Skills sử dụng (`.agents/skills/`):**
  - [ui-ux-designer](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/26_ui-ux-designer/SKILL.md) & [baseline-ui](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/baseline-ui/SKILL.md): Thiết kế Layout 2 cột cho 5 trang Chính sách dễ tra cứu; thiết kế trạng thái 404 có một hành động tiếp theo rõ ràng (Rule `baseline-ui`: *"MUST give empty states one clear next action"*).
  - [fixing-accessibility](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/16_fixing-accessibility/SKILL.md) & [web-design-guidelines](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/.agents/skills/57_web-design-guidelines/SKILL.md): Kiểm tra điều hướng tab và cấu trúc heading trên toàn bộ trang.
- **Tài liệu tham chiếu:** [07_UI_UX_Page_Specs.md](file:///c:/Users/Admin/Local%20Sites/gachdravit/app/public/docs/07_UI_UX_Page_Specs.md) (§8).
- **Hạng mục UI/UX hoàn thiện:**
  1. **Layout Trang Chính sách (2 cột 3/9):** Cột trái là Menu chuyển đổi giữa 5 chính sách (*Chính sách vận chuyển & bốc xếp · Chính sách đổi trả & bảo hành lô màu · Chính sách kiểm hàng · Hướng dẫn thanh toán · Bảo mật thông tin*); Cột phải là nội dung trình bày rõ ràng kèm bảng tóm tắt.
  2. **Layout Kết quả Tìm kiếm (`/?s=80x80`):** Hiển thị các **Silo Danh mục khớp từ khóa** ở đầu (`/gach-80x80`, `/gach-van-da`), theo sau là lưới Sản phẩm và Bài viết Cẩm nang liên quan.
  3. **Layout 404 / Không tìm thấy mã gạch:** Thông điệp ngắn gọn + Ô tìm nhanh mã SKU + 4 thẻ dẫn trực tiếp về 4 nhóm Silo chính (`/gach-lat-nen`, `/gach-phong-khach`, `/gach-van-da`, `/gach-80x80`).

---

## 4. Kiến trúc Animation, Hover GPU & Micro-interactions (Option A + B Kết Hợp)

Toàn bộ 11 trang HTML trong `docs/layout/` áp dụng chiến lược **Option A + B Kết Hợp** nhằm đảm bảo hiệu năng **60fps**, **CLS = 0**, **LCP ≤ 2.0s** và tuân thủ `@media (prefers-reduced-motion: reduce)`:

### 4.1. Option A — Pure CSS GPU (`transform`, `opacity` — 0KB JS) cho toàn bộ Hover & UI
- **Motion Tokens (`tokens.css`):**
  - Thời lượng: `--dur-instant: 100ms` (active press), `--dur-fast: 160ms` (button/chip/nav hover), `--dur-base: 260ms` (card lift, dropdown, tab), `--dur-drawer: 280ms` (modal/drawer), `--dur-slow: 420ms` (image zoom & crossfade).
  - Đường cong gia tốc: `--ease-out: cubic-bezier(0.2, 0.7, 0.2, 1)`, `--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1)`, `--ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1)`.
- **Vi tương tác chuẩn (`.drv-*` trong `main.css`):**
  - **Product Card (`.drv-card`):** Hover nhấc nhẹ `translateY(-3px)` với `--ease-out-expo`, đổi viền `--c-teal-500`, crossfade sang ảnh phối cảnh 2 kèm zoom nhẹ `scale(1.04)` trong `420ms`.
  - **Buttons & Links (`.drv-btn`, `.drv-link-draw`):** Hover trượt icon mũi tên `translateX(3px)`, nhấn giữ hạ `scale(0.98)` xúc giác; link phụ vẽ đường gạch chân ngang `scaleX(0 → 1)`.
  - **Header Nav, Dropdown Cấp 2/3 & Dialogs:** Chuyển trạng thái mượt bằng `opacity` + `transform: translateY()`, không bao giờ animate `width`, `height`, `top`, `left` hay `margin`.

### 4.2. Option B — Anime.js v3.2.2 (`~6.5KB gzip` CDN, `defer`) cho Staggered Reveal & Đếm số Odometer
- **Tích hợp trong `assets/js/core.js` (`window.Dravit.motion`):**
  1. **Staggered Tile Grid Reveal (`Dravit.motion.staggerReveal` & `[data-drv-stagger]`):** Tự động kích hoạt qua `IntersectionObserver` khi cuộn tới lưới sản phẩm/danh mục hoặc khi lọc AJAX (`opacity: [0, 1]`, `translateY: [14, 0]`, `delay: anime.stagger(55)`, `duration: 420ms`, `easing: 'easeOutExpo'`).
  2. **Number Odometer (`Dravit.motion.animateNumber`):** Chạy số mượt khi quy đổi `m² ⇄ viên ⇄ thùng ⇄ Tổng tiền VND` trong `<drv-unit-calc>` (`assets/js/unit-calc.js`), kết hợp `font-variant-numeric: tabular-nums` để cố định độ rộng từng chữ số.
  3. **Spring Cart Badge Bounce (`Dravit.motion.bounceCartBadge`):** Hiệu ứng đàn hồi vật lý `spring(1, 80, 10, 0)` trên `[data-drv-cart-count]` khi thêm gạch vào giỏ hàng.
  4. **Safe Fallback & Accessibility:** Tự động kiểm tra `prefers-reduced-motion: reduce` hoặc khi thiếu CDN để fallback về cập nhật DOM tức thì, tuyệt đối không làm gãy luồng giao dịch.

---

## 5. Checklist Nghiệm thu Chung cho Mỗi Step (UI/UX Gate)

Trước khi chuyển từ Step hiện tại sang Step tiếp theo, file HTML phải vượt qua 7 kiểm tra:
1. **Nhận diện thương hiệu (`frontend-design`):** Nhìn thấy rõ hệ thống **Lưới Đường Ron `1px solid var(--c-navy-200)`**, đúng bảng màu Brand Navy `#083A4F` + Deep Ink `#0B192C` + Brand Teal `#0D9488` / `#0F766E` + Warm Amber `#F59E0B` trên nền Clean Slate `#F8FAFC`, đúng cặp font `Anton` + `Quicksand`.
2. **Đơn vị & Giá kép (`dravit-catalog-commerce`):** Mọi điểm chạm sản phẩm đều hiển thị **`đ/m²` (chính)** + **`đ/viên` (phụ)**; số lượng giao dịch là **số nguyên viên** kèm quy đổi thùng & `m²`.
3. **Không trùng lặp code (`D-17`):** Sử dụng lại 100% class `.l-*`, `.drv-*` từ `assets/css/main.css` và `<drv-*>` từ `assets/js/`.
4. **Responsive 4 mốc (`design-taste-frontend`):** Hiển thị cân đối 2 biên, không tràn ngang ở `1440px`, `1024px`, `768px`, `390px`.
5. **Accessibility (`fixing-accessibility`):** Đủ `<label>`, `aria-label`, `aria-live="polite"`, tương phản đạt WCAG 2.2 AA, đóng mở `<dialog>` bằng bàn phím.
6. **Ngôn ngữ & Giọng văn (`avoid-ai-writing`):** 100% Tiếng Việt chỉn chu, thực tế, không chứa từ sáo rỗng kiểu AI.
7. **Motion & Performance (`fixing-motion-performance`):** Chỉ animate `transform` và `opacity`; gắn `data-drv-stagger` cho các lưới thẻ chính; tôn trọng `prefers-reduced-motion: reduce`.

