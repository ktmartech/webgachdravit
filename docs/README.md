# Bộ tài liệu dự án gachdravit.vn

> Website TMĐT bán lẻ gạch ốp lát **DRAVIT**: WordPress + WooCommerce + Rank Math, theme custom `dravit`.
> Tài liệu này là **nguồn tham chiếu duy nhất (Single Source of Truth)** cho người và AI Agent khi thực hiện dự án.

## Danh mục tài liệu

| # | File | Nội dung | Đọc khi |
|---|---|---|---|
| `00` | [00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md) | Mục tiêu, phạm vi Phase 1, persona, **nhật ký quyết định (ADR `D-01`..`D-35`; dải `D-41`..`D-49` cho AI mở rộng)**, giả định, việc còn mở (`O-01`..`O-15`) | Bắt đầu dự án / mọi task |
| `01` | [01_Brand_Design_System.md](01_Brand_Design_System.md) | Brand, design tokens, bảng màu `D-11`, typography, lưới ron 1px, kiến trúc component 4 lớp (`D-17`) | Mọi task Frontend/UI |
| `02` | [02_Technical_Architecture.md](02_Technical_Architecture.md) | Stack, cấu trúc theme `Dravit\`, PSR-4, Vite entries, whitelist thư viện (`D-28`), bảo mật, deploy | Mọi task Backend/Build |
| `03` | [03_Catalog_Data_Model.md](03_Catalog_Data_Model.md) | Taxonomy, 14 attributes chuẩn, 28 Smart Collections, `CollectionSync`, `PrimaryCategory` (`D-27`), `SlugGuard` | Catalog, import, admin |
| `04` | [04_Pricing_Units_Commerce.md](04_Pricing_Units_Commerce.md) | Trạng thái giá `priced`/`quote` (`D-20`), cấu hình đơn vị mua theo SP (`m²/thùng/viên`, `D-24`), `Units\Calc`, giỏ, checkout VN + VAT, `wc-cho-xac-nhan`, VietQR | Sản phẩm, giỏ, đặt hàng |
| `05` | [05_Faceted_Filter_AJAX.md](05_Faceted_Filter_AJAX.md) | 14 bộ lọc, URL grammar không `/` cuối, 1 `<form>` chung, REST `/catalog`, AJAX + load more, ma trận robots (`D-21`) | Trang danh mục, bộ lọc |
| `06` | [06_SEO_GEO_Spec.md](06_SEO_GEO_Spec.md) | Cấu hình Rank Math, meta template, schema JSON-LD, internal link `IL-01`..`06`, `llms.txt`, sitemap | SEO, Metadata, GEO |
| `07` | [07_UI_UX_Page_Specs.md](07_UI_UX_Page_Specs.md) | Wireframe & đặc tả 12 trang, component anatomy, microcopy, WCAG 2.2 AA | Dựng template PHP/HTML |
| `08` | [08_Performance_Hosting.md](08_Performance_Hosting.md) | Ngân sách hiệu suất, font & Phosphor subset tự host, ảnh WebP/AVIF, LiteSpeed Cache + chiến lược `/nonce` | Tối ưu tốc độ, build, deploy |
| `09` | [09_Roadmap_QA_Testplan.md](09_Roadmap_QA_Testplan.md) | Lộ trình Phase 0–6, phụ thuộc `O-01`..`O-15`, test case bắt buộc (8 SKU, `D-24`, BOQ, VAT, VietQR, bảo mật), preflight | Lập kế hoạch, QA, nghiệm thu |
| `10` | [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md) | Brand Story, Voice & Tone, từ điển thuật ngữ & chống văn phong AI, khung microcopy chuẩn | Viết copy, UI text, blog, email |
| `11` | [11_AI_Quote_System_Spec.md](11_AI_Quote_System_Spec.md) | Phác thảo đầu tiên Quote & AI *(Lưu ý: §2 Quote Builder đã **DEPRECATED**, chuyển sang `17`; phần AI cập nhật tại `12`–`13`)* | Tra cứu lịch sử thiết kế |
| `12` | [12_AI_Advisor_Strategy_Analysis.md](12_AI_Advisor_Strategy_Analysis.md) | Chiến lược Trợ lý AI Tìm gạch Phase 3 (`D-41`..`D-47`), phân tầng mở rộng Tier 0–2 *(Phase 1 tắt `ai_enabled=false` theo `D-25`)* | Chuẩn bị Phase 3 AI |
| `13` | [13_AI_Advisor_MVP_Spec.md](13_AI_Advisor_MVP_Spec.md) | Đặc tả MVP Trợ lý AI đóng kín (~420 SKU, `D-48`..`D-49`): `ScopeGate`, `CatalogSnapshot`, `Validator`, `kb_refs`, bộ test | Triển khai Phase 3 AI |
| `14` | [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md) | **Từ điển dữ liệu tập trung:** toàn bộ Product/Term/Post/User/Order meta, CPT `drv_lead`, `drv_settings`, CSV mapping | Mọi task Backend/DB |
| `15` | [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md) | **Hợp đồng API & JS:** REST `dravit/v1/*`, Store API ExtendSchema, `<drv-*>`, `data-drv-*`, Custom Events, `localStorage` | Tích hợp PHP ↔ JS |
| `16` | [16_Template_Component_Porting_Guide.md](16_Template_Component_Porting_Guide.md) | **Hướng dẫn Port Layout → WP:** ánh xạ 12 prototype → template PHP, `drv_ui()`, danh sách thành phần demo phải gỡ bỏ | Port HTML mẫu sang WP |
| `17` | [17_Quote_BOQ_And_Lead_Spec.md](17_Quote_BOQ_And_Lead_Spec.md) | Đặc tả Báo giá công trình BOQ (`/danh-sach-bao-gia`, `drv_quote_v2`), CPT `drv_lead` hợp nhất, chống spam, Admin tạo đơn (`D-23`) | Báo giá BOQ & Form Lead |
| `18` | [18_Order_Ops_And_Email_Spec.md](18_Order_Ops_And_Email_Spec.md) | Vận hành đơn hàng HPOS (`wc-cho-xac-nhan` → `on-hold`/`processing`), Action Scheduler tự hủy, Email templates & VietQR (`D-22`) | Xử lý đơn hàng & Email |
| `19` | [19_Navigation_Header_Search_Spec.md](19_Navigation_Header_Search_Spec.md) | Header 2 tầng, Mega Menu 4+1 từ config (`drv_mega_html`), Walkers, Live Search không dấu, Mobile Drawer/Bar/Dock (`D-29`) | Header, Menu, Tìm kiếm |
| `20` | [20_Blog_Editorial_And_Content_Model.md](20_Blog_Editorial_And_Content_Model.md) | Mô hình Cẩm nang (`/cam-nang`, `/chu-de/{slug}`), E-E-A-T, Gutenberg Patterns, Shortcodes nhúng SP/bảng giá, TOC | Cẩm nang / Gutenberg |
| `21` | [21_Theme_Build_Execution_Roadmap.md](21_Theme_Build_Execution_Roadmap.md) | **Roadmap thực thi 12 Steps xây dựng WP Theme `dravit` (`D-31`..`D-34`):** cắt Bottom-Up 4 tầng + `drv_section()` (`S-3`), Rules × Skills, Unit Tests, AI Ready, GEO-SEO, BOQ & Fluent Forms Bridge | Triển khai code Theme từng Step |
| `LA` | [layout/00_LAYOUT_AUDIT_REPORT.md](layout/00_LAYOUT_AUDIT_REPORT.md) | Báo cáo audit đối chiếu 12 HTML prototypes vs bộ docs và bảng chốt quyết định `Q1–Q28` → `D-21`..`D-30` | Tra cứu quyết định port |
| `LR` | [layout/00_UI_UX_Layout_Roadmap.md](layout/00_UI_UX_Layout_Roadmap.md) | Lộ trình 12 Steps dựng bộ HTML/CSS/JS prototype mẫu tại `docs/layout/` | Tham chiếu UI prototype |
| `S` | [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) (+ [`.xlsx`](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.xlsx)) | Silo URL không dấu `/` cuối, 28 collection (H1, breadcrumb, phase), 14 bộ lọc, seed 8 SKU, `IL-01`..`06` | Catalog, SEO, filter |
| `B` | [branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf](branding/DRAVIT_Brand%20Guidelines_Xanh%20Navy%20+%20R%C3%AAu.pdf), `branding/logo/` | Brand guideline gốc, logo PNG | Tham chiếu nhận diện gốc |

## Thứ tự ưu tiên khi tài liệu mâu thuẫn

1. **[00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md)** (Các quyết định kiến trúc ADR `D-01` → `D-34` đã chốt mới nhất).
2. Tài liệu chuyên đề, đặc tả hợp đồng & lộ trình thực thi **[01_Brand_Design_System.md](01_Brand_Design_System.md)** → **[21_Theme_Build_Execution_Roadmap.md](21_Theme_Build_Execution_Roadmap.md)** *(lưu ý `11 §2` đã deprecated và thay thế hoàn toàn bằng `17`; `12`–`13` là đặc tả kiến trúc AI với lõi `RuleOnlyDriver` + Interfaces đã mở sẵn ngay từ Phase 1 theo `D-32`)*.
3. **[branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md)**: chuẩn cho URL (không dấu `/` cuối, `D-21`), H1, breadcrumb, phase của 28 collection, 14 bộ lọc. Riêng **slug giá trị thuộc tính** và **trọng số `priority`** tuân theo bảng chuẩn hóa trong [03_Catalog_Data_Model.md](03_Catalog_Data_Model.md) (`D-27`).
4. **[branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf](branding/DRAVIT_Brand%20Guidelines_Xanh%20Navy%20+%20R%C3%AAu.pdf)** *(Lưu ý: Bảng màu thực tế tuân theo Quyết định `D-11` trong `00`: Brand Primary `#083A4F` + Deep Ink text `#0B192C` + Brand Teal `#0D9488` / `#0F766E` + Warm Amber `#F59E0B` trên Clean Slate `#F8FAFC`)*.

Khi gặp xung đột chưa có trong tài liệu: **dừng lại, hỏi chủ dự án**, sau đó ghi quyết định vào `00` (mục ADR).

## Quy trình cho AI Agent

1. Đọc `00` → tài liệu chuyên đề liên quan (`01`–`20`) → skill tương ứng (`09` §5).
2. Đề xuất phương án trước nếu task mang tính nghiên cứu/thiết kế; chỉ code khi đã được duyệt.
3. Code theo `02`, `14`, `15` (quy ước PSR-4, HPOS CRUD, bảo mật nonce/escaping, đơn ngôn ngữ tiếng Việt). Không thêm thư viện ngoài whitelist `02` §4 (`D-28`).
4. Kiểm thử đầy đủ các test case bắt buộc tại `09` §3 trước khi báo hoàn thành.

## Môi trường

| Mục | Giá trị |
|---|---|
| Local | Local by Flywheel, `c:\Users\Admin\Local Sites\gachdravit\app\public` |
| Core | WordPress 7.1.4 · WooCommerce 11.2.2 · Rank Math 1.0.281 (free) |
| Runtime | PHP 8.2 · Node 22 · Composer 2.9 |
| Production | AZDIGI hosting LiteSpeed + LiteSpeed Cache |
| Domain | https://gachdravit.vn |
