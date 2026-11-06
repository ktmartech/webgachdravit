# Bộ tài liệu dự án gachdravit.vn

> Website TMĐT bán lẻ gạch ốp lát **DRAVIT**: WordPress + WooCommerce + Rank Math, theme custom `dravit`.
> Tài liệu này là **nguồn tham chiếu duy nhất (Single Source of Truth)** cho người và AI Agent khi thực hiện dự án.

## Danh mục tài liệu

| # | File | Nội dung | Đọc khi |
|---|---|---|---|
| 0 | [00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md) | Mục tiêu, phạm vi, persona, **nhật ký quyết định (ADR)**, giả định, việc còn mở | Bắt đầu dự án / mọi task |
| 1 | [01_Brand_Design_System.md](01_Brand_Design_System.md) | Brand, design tokens, typography, logo, component spec, giọng văn | Mọi task UI |
| 2 | [02_Technical_Architecture.md](02_Technical_Architecture.md) | Stack, cấu trúc theme, module, quy ước code, build Vite, bảo mật, deploy | Mọi task code |
| 3 | [03_Catalog_Data_Model.md](03_Catalog_Data_Model.md) | Taxonomy, attribute, 28 collection, cơ chế tự gán danh mục, product meta | Catalog, import, admin |
| 4 | [04_Pricing_Units_Commerce.md](04_Pricing_Units_Commerce.md) | Hệ đơn vị bán, giá/m² và giá/viên, máy tính, giỏ, checkout VN, báo phí ship, trạng thái đơn, VietQR | Sản phẩm, giỏ, checkout |
| 5 | [05_Faceted_Filter_AJAX.md](05_Faceted_Filter_AJAX.md) | 14 bộ lọc, URL grammar, REST API, AJAX + load more, ma trận robots | Trang danh mục |
| 6 | [06_SEO_GEO_Spec.md](06_SEO_GEO_Spec.md) | Cấu hình Rank Math, meta template, schema JSON-LD, internal link, llms.txt, sitemap | Mọi template public |
| 7 | [07_UI_UX_Page_Specs.md](07_UI_UX_Page_Specs.md) | Wireframe và đặc tả từng trang, microcopy, a11y | Dựng template |
| 8 | [08_Performance_Hosting.md](08_Performance_Hosting.md) | Ngân sách hiệu suất, chiến lược asset, ảnh, font, LiteSpeed Cache AZDIGI | Build, deploy |
| 9 | [09_Roadmap_QA_Testplan.md](09_Roadmap_QA_Testplan.md) | Lộ trình theo phase, nghiệm thu, test case bắt buộc, preflight | Lập kế hoạch, QA |
| 10 | [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md) | Brand Story/Mission/Vision, 6 Voice & 4 Tone, khung nội dung từng trang, từ điển & chống văn phong AI | Viết copy, UI text, SEO content, blog, email |
| L | [layout/00_UI_UX_Layout_Roadmap.md](layout/00_UI_UX_Layout_Roadmap.md) | Lộ trình 12 Steps dựng 11 file HTML/CSS/JS layout mẫu tại `docs/layout/` kèm Rules & Skills từng trang | Dựng HTML/UI prototype trước khi ghép Backend |
| S | [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) (+ [`.xlsx`](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.xlsx)) | Silo URL, 28 collection (H1, breadcrumb, phase), 14 bộ lọc, seed 8 SKU, IL-01..06 | Catalog, SEO, filter |
| B | [branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf](branding/DRAVIT_Brand%20Guidelines_Xanh%20Navy%20+%20R%C3%AAu.pdf), `logo/` | Brand guideline gốc, logo PNG | UI |

## Thứ tự ưu tiên khi tài liệu mâu thuẫn

1. **00_Project_Brief_Decisions.md** (quyết định đã chốt, mới nhất)
2. Tài liệu chuyên đề 01–10
3. **[branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md)**: chuẩn cho URL, H1, breadcrumb, phase của 28 collection. Riêng **slug giá trị thuộc tính** theo bảng chuẩn hóa trong `03_Catalog_Data_Model.md` §2.
4. [branding/DRAVIT_Brand Guidelines_Xanh Navy + Rêu.pdf](branding/DRAVIT_Brand%20Guidelines_Xanh%20Navy%20+%20R%C3%AAu.pdf) (Lưu ý: Bảng màu thực tế đã được cập nhật hiện đại theo Quyết định D-11 trong `00`: Brand Primary `#083A4F` + Deep Ink text `#0B192C` + Brand Teal `#0D9488` / `#0F766E` + Warm Amber `#F59E0B` trên Clean Slate `#F8FAFC`)

Khi gặp xung đột chưa có trong tài liệu: **dừng lại, hỏi chủ dự án**, sau đó ghi quyết định vào `00` (mục ADR).

## Quy trình cho AI Agent

1. Đọc `00` → tài liệu chuyên đề liên quan → skill tương ứng (`09` §5).
2. Đề xuất phương án trước nếu task mang tính nghiên cứu/thiết kế; chỉ code khi đã được duyệt.
3. Code theo `02` (quy ước, bảo mật, i18n). Không thêm thư viện ngoài danh sách `02` §4.
4. Kiểm thử theo `09` §3 trước khi báo hoàn thành.

## Môi trường

| Mục | Giá trị |
|---|---|
| Local | Local by Flywheel, `c:\Users\Admin\Local Sites\gachdravit\app\public` |
| Core | WordPress 7.1.4 · WooCommerce 11.2.2 · Rank Math 1.0.281 (free) |
| Runtime | PHP 8.2 · Node 22 · Composer 2.9 |
| Production | AZDIGI hosting LiteSpeed + LiteSpeed Cache |
| Domain | https://gachdravit.vn |

