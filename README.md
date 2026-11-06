# DRAVIT — Architectural Tiles & Sintered Stone Platform

> Nền tảng thương mại điện tử và kiến trúc số chuyên ngành gạch ốp lát xương đá Porcelain & Đá nung kết Big Slab cao cấp DRAVIT ([gachdravit.vn](https://gachdravit.vn)).

[![Architecture](https://img.shields.io/badge/Architecture-Grout--Line%20Grid-083A4F?style=flat-square)](docs/01_Brand_Design_System.md)
[![Accessibility](https://img.shields.io/badge/WCAG-2.2%20AA%20Compliant-0D9488?style=flat-square)](docs/07_UI_UX_Page_Specs.md)
[![Performance](https://img.shields.io/badge/PageSpeed-Desktop%20%E2%89%A5%2095%20%7C%20Mobile%20%E2%89%A5%2090-F59E0B?style=flat-square)](docs/08_Performance_Hosting.md)
[![Stack](https://img.shields.io/badge/Stack-WordPress%206.x%20%2B%20WooCommerce-1E3A5F?style=flat-square)](docs/02_Technical_Architecture.md)

---

## 1. Giới thiệu dự án

Dự án phát triển nền tảng kỹ thuật số hoàn chỉnh cho thương hiệu **DRAVIT** — Đơn vị phân phối gạch ốp lát và đá nung kết khổ lớn tiêu chuẩn quốc tế. Hệ thống giải quyết các bài toán đặc thù của ngành vật liệu xây dựng cao cấp:

- **Báo giá kép minh bạch:** Tính toán đồng thời giá theo mét vuông ($đ/m^2$) và giá theo viên ($đ/viên$) dựa trên quy chuẩn đóng gói thực tế.
- **Bộ lọc đa diện Faceted Search (14 Facets):** Lọc theo không gian, kích thước, bề mặt, độ nhám R9–R13, độ hút nước $E \le 0.5\%$, ứng dụng kỹ thuật...
- **Nhận diện thiết kế "Architectural Grout-Line Grid":** Bố cục lấy cảm hứng từ đường ron gạch 1px tinh tế, typography Anton + Quicksand, dải màu Deep Ink `#0B192C`, Brand Navy `#083A4F`, Teal `#0D9488` và Warm Amber `#F59E0B`.
- **SEO & GEO Search:** Tối ưu hóa kiến trúc Silo theo không gian / kích thước / bề mặt và cấu trúc Schema.org đa tầng cho AI Overviews & Local Google Maps.

---

## 2. Cấu trúc kho lưu trữ (Repository Structure)

```text
├── .agents/                      # Hệ thống quy chuẩn cho AI Agent & Automation
│   ├── rules/                   # 6 bộ luật phát triển nghiêm ngặt
│   │   ├── dravit-architecture.md
│   │   ├── dravit-catalog-commerce.md
│   │   ├── dravit-coding-standards.md
│   │   ├── dravit-filter-seo-geo.md
│   │   ├── dravit-frontend-ui.md
│   │   └── dravit-security-testing.md
│   ├── skills/                  # 50+ Agent Skills chuyên biệt (PHP, JS, CSS, SEO, QA)
│   └── workflows/               # 4 quy trình phát triển mẫu
│       ├── catalog-and-silo-setup.md
│       ├── content-and-copywriting.md
│       ├── filter-and-seo-verification.md
│       └── frontend-design-ux.md
├── docs/                        # Tài liệu đặc tả kỹ thuật & Prototype Layout
│   ├── 00_Project_Brief_Decisions.md      # Quyết định kiến trúc & định hướng cốt lõi
│   ├── 01_Brand_Design_System.md          # Hệ thống Design Tokens & Identity
│   ├── 02_Technical_Architecture.md       # Kiến trúc tầng, plugin & cơ sở dữ liệu
│   ├── 03_Catalog_Data_Model.md           # Schema dữ liệu sản phẩm, biến thể & thuộc tính
│   ├── 04_Pricing_Units_Commerce.md       # Cơ chế tính giá kép m² / viên & tồn kho
│   ├── 05_Faceted_Filter_AJAX.md          # Bộ lọc đa diện AJAX URL-friendly
│   ├── 06_SEO_GEO_Spec.md                 # Chiến lược Silo SEO, Title, Meta & JSON-LD
│   ├── 07_UI_UX_Page_Specs.md             # Đặc tả chi tiết 11 màn hình cốt lõi
│   ├── 08_Performance_Hosting.md          # Tối ưu hóa Core Web Vitals, CDN & Hosting
│   ├── 09_Roadmap_QA_Testplan.md          # Lộ trình triển khai 4 giai đoạn & Checklist QA
│   ├── 10_Brand_Voice_And_Content_Guide.md # Hướng dẫn giọng văn & biên tập nội dung
│   └── layout/                            # Bộ giao diện mẫu HTML5/CSS3 chuẩn WCAG AA
│       ├── 00-ui-kit.html                 # UI Kit: Colors, Typography, Buttons, Badges, Shell
│       ├── 01-home.html                   # Trang chủ 8 Section kiến trúc (Bright Bento)
│       ├── 00_UI_UX_Layout_Roadmap.md      # Quy hoạch 11 layout mẫu cần dựng
│       └── assets/
│           ├── css/ (main.css, pages.css, tokens.css, fonts.css)
│           ├── js/ (core.js, theme.js)
│           └── img/ (Tập hợp ảnh phối cảnh SVG & hình khối sản phẩm)
└── README.md                    # Tài liệu tổng quan dự án
```

---

## 3. Hệ thống tài liệu kỹ thuật cốt lõi

| Mã tài liệu | Tên tài liệu | Nội dung chính |
| :--- | :--- | :--- |
| **D-00** | [00_Project_Brief_Decisions.md](docs/00_Project_Brief_Decisions.md) | Định vị thương hiệu cao cấp, quy chuẩn công nghệ không phụ thuộc Page Builder nặng |
| **D-01** | [01_Brand_Design_System.md](docs/01_Brand_Design_System.md) | Bảng màu 60-30-10, quy tắc lưới đường ron 1px (`--c-navy-200`), thang đo độ cao elevation |
| **D-02** | [02_Technical_Architecture.md](docs/02_Technical_Architecture.md) | Kiến trúc WordPress 6.x + WooCommerce, custom theme lightweight, Redis cache |
| **D-03** | [03_Catalog_Data_Model.md](docs/03_Catalog_Data_Model.md) | Phân tầng danh mục 3 cấp, taxonomy thuộc tính gạch (kích thước, bề mặt, xương đá) |
| **D-04** | [04_Pricing_Units_Commerce.md](docs/04_Pricing_Units_Commerce.md) | Thuật toán chuyển đổi đơn vị $m^2 \leftrightarrow viên$, xử lý làm tròn theo thùng |
| **D-05** | [05_Faceted_Filter_AJAX.md](docs/05_Faceted_Filter_AJAX.md) | Cơ chế lọc URLSearchParams, Roving tabindex, history pushState không giật trang |
| **D-06** | [06_SEO_GEO_Spec.md](docs/06_SEO_GEO_Spec.md) | Cấu trúc URL `/gach-op-lat/{khong-gian}/{kich-thuoc}/`, Local Business Schema KĐT Sala |
| **D-07** | [07_UI_UX_Page_Specs.md](docs/07_UI_UX_Page_Specs.md) | Bố cục chi tiết trang chủ, trang danh mục, trang chi tiết sản phẩm, giỏ hàng & thanh toán |
| **D-08** | [08_Performance_Hosting.md](docs/08_Performance_Hosting.md) | Chiến lược LCP < 1.8s, CLS = 0, font display swap, AVIF/WebP, Nginx FastCGI cache |
| **D-09** | [09_Roadmap_QA_Testplan.md](docs/09_Roadmap_QA_Testplan.md) | Kế hoạch kiểm thử đa màn hình (360px – 1920px), ma trận kiểm thử tính năng |
| **D-10** | [10_Brand_Voice_And_Content_Guide.md](docs/10_Brand_Voice_And_Content_Guide.md) | Giọng văn chuyên gia kiến trúc, trung thực, không dùng từ ngữ giật gân, bảng thuật ngữ |

---

## 4. Hướng dẫn chạy thử giao diện (Local Layout Preview)

Bộ giao diện mẫu (Prototypes) nằm hoàn toàn trong thư mục `docs/layout/` viết bằng HTML5 ngữ nghĩa, CSS Layers (`@layer reset, tokens, base, layout, components, utilities`) và JavaScript Vanilla, không phụ thuộc thư viện build nặng.

### Xem nhanh trên trình duyệt
1. Mở file [docs/layout/00-ui-kit.html](docs/layout/00-ui-kit.html) để kiểm tra:
   - Toàn bộ tokens màu sắc, kiểu chữ Anton & Quicksand, icons Phosphor.
   - Thư viện components: Nút bấm, thẻ sản phẩm, badges trạng thái kho, dialog popup báo giá.
   - Header sticky 2 tầng, Mega Menu flyout 3 cấp, Bottom bar di động.
2. Mở file [docs/layout/01-home.html](docs/layout/01-home.html) để trải nghiệm:
   - **Section 1: Hero Bento 8/4 & Floating Quick Tile Finder** (Phương án Bright Architectural Gallery nền sáng bừng, phối cảnh bắt sáng tự nhiên).
   - **Section 2:** Dải 4 cam kết chuẩn mực (Trust Strip).
   - **Section 3:** Bento lưới 8 không gian ứng dụng.
   - **Section 4:** Trình duyệt bộ sưu tập gạch tương tác (Catalog Explorer) với ARIA Tabs & Mobile Snap Scroll.
   - **Section 5:** Sơ đồ đối sánh kích thước thật theo tỷ lệ chuẩn (`--_scale` px/cm) & Thang đo độ bám R9–R13.
   - **Section 6:** Ưu đãi gạch lẻ lô tồn kho minh bạch số lượng $m^2$ và lưu ý Shade/Caliber.
   - **Section 7:** Không gian trải nghiệm Showroom KĐT Sala & Đặt lịch hẹn đèn rọi màu.
   - **Section 8:** Cẩm nang kỹ thuật ốp lát & Bài viết chuyên sâu.

### Kiểm thử Responsive & Khắc phục lỗi tràn ngang (Overflow)
Tất cả các layout đều được kiểm thử tự động qua Chrome DevTools Protocol (CDP) tại các breakpoint:
- Mobile: `360px`, `390px` (iPhone 12/13/14)
- Tablet: `768px` (iPad Portrait), `1024px` (iPad Landscape)
- Desktop: `1280px`, `1366px`, `1440px`, `1920px` (Full HD)

---

## 5. Quy chuẩn phát triển dành cho Developers & AI Agents

Khi phát triển thêm tính năng hoặc chuyển đổi template sang WordPress Theme, bắt buộc tuân thủ:

1. **Quy tắc CSS Tokens:** Không sử dụng mã màu HEX trực tiếp trong CSS components; luôn dùng CSS Custom Properties từ `tokens.css` (`var(--c-navy-900)`, `var(--c-teal-500)`, `var(--c-amber-500)`, v.v.).
2. **Quy chuẩn Không !important:** Tuyệt đối không sử dụng `!important`. Tận dụng `@layer` để quản lý độ đặc hiệu (specificity).
3. **Hiệu năng hoạt họa (Motion Performance):** Chỉ animate các thuộc tính Compositor (`transform`, `opacity`). Thời gian chuyển động $\le 260ms$. Tôn trọng thuộc tính `@media (prefers-reduced-motion: reduce)`.
4. **Tiêu chuẩn Accessibility:** Đảm bảo tỉ lệ tương phản màu WCAG 2.2 AA ($\ge 4.5:1$ cho chữ thông thường, $\ge 3:1$ cho chữ lớn và thành phần giao diện). Thẻ tương tác phải có vùng chạm $\ge 44 \times 44px$ trên thiết bị di động.

---

## 6. Bản quyền & Thông tin liên hệ

- **Đơn vị chủ quản:** Công ty Cổ phần Gạch Ốp Lát DRAVIT
- **Địa chỉ Showroom:** Khu đô thị Sala, TP. Thủ Đức, TP. Hồ Chí Minh
- **Website:** [https://gachdravit.vn](https://gachdravit.vn)
- **Hotline kỹ thuật & dự án:** 0903 000 000
