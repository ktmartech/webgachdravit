# 09. Lộ trình, Nghiệm thu & Test plan

> Mỗi phase **phải qua checklist nghiệm thu** trước khi sang phase tiếp theo.

## 1. Lộ trình

| Phase | Nội dung | Đầu ra | Nghiệm thu |
|---|---|---|---|
| **0. Chuẩn bị** | Thay rules `.agents/rules` bằng `dravit-*` (`02` §13); cài LiteSpeed Cache (tắt ở local); cấu hình WC: VND, VN-only, permalink `/san-pham`, trang WC (slug tiếng Việt), COD + BACS, HPOS; Rank Math theo `06` §2; permalink `06` §1; thu thập O-02, O-03, O-05 | Rules, site cấu hình | Review rules; permalink đúng; Rank Math bỏ base category hoạt động |
| **1. Theme skeleton** | `style.css`, bootstrap/autoload, `Core\*`, Vite + manifest, `tokens.json` → `tokens.css` + `theme.json`, font subset, Phosphor Icons CDN, logo tối ưu, header/footer/mobile bar, menu, search UI, cleanup, Settings page | Theme kích hoạt được | Lighthouse trang trống ≥ 98; không PHP notice; a11y header/footer pass |
| **2. Catalog & Units** | Attributes + term chuẩn (`03` §2), taxonomy ẩn, seeder 28 `product_cat`, `CollectionSync`, `PrimaryCategory`, `SlugGuard`, `SaleSync`, Product Data tab, pricing model `area_pack` + registry, giá tự tính, import CSV mapping, seed 8 SKU | Dữ liệu chuẩn | Test §3.1, §3.2 pass 100% |
| **3. Danh mục + bộ lọc** | `archive-product.php`, card, pills, `FacetRegistry`, `UrlState`, `QueryBuilder`, `FacetCounter`, REST `catalog`, `catalog.js`, load more, `RobotsMatrix`, `Canonical`, SEO footer/FAQ, schema collection | Trang danh mục hoàn chỉnh | Test §3.3, §3.4, §3.5 pass; chạy được khi tắt JS; PSI collection ≥ 90 |
| **4. Sản phẩm** | Template sản phẩm, gallery + PhotoSwipe, swatch series, máy tính, quantity component, add-to-cart AJAX, mini-cart drawer (Store API), bảng thông số IL-03, related IL-06, schema Product | Trang SP hoàn chỉnh | Test §3.2, §3.6; Rich Results pass; PSI ≥ 90 |
| **5. Commerce & nội dung** | Cart/checkout VN (34 tỉnh + phường/xã), `QuoteShippingMethod`, trạng thái đơn, order action, email custom, VietQR, form báo giá (`drv_lead`); home, blog, trang tĩnh, chính sách, patterns, llms.txt | Đặt hàng end-to-end | Test §3.7, §3.8 pass |
| **6. QA & go-live** | CWV 5 template, WCAG 2.2 AA, bảo mật, cross-browser, staging AZDIGI + LiteSpeed, crawl kiểm tra, GSC/Bing, redirect, backup | Go-live | Preflight §4 pass 100% |

## 2. Phụ thuộc dữ liệu

| Phase cần | Dữ liệu | Mã |
|---|---|---|
| 1 | Hotline, Zalo, showroom | O-03 |
| 2 | CSV sản phẩm + ảnh + quy cách thùng | O-05 |
| 3 | Nội dung SEO 28 collection | O-07 |
| 5 | Tài khoản ngân hàng; nội dung chính sách; dữ liệu phường/xã | O-02, O-04, O-06 |

## 3. Test case bắt buộc

### 3.1 Tự gán danh mục (8 SKU seed, theo [branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md](branding/Thiet_Ke_Silo_URL_Va_Bo_Loc_GachDravit.md) Phần 4)

| SKU | Danh mục mong đợi (đúng tập, không thừa, không thiếu) | Primary / Breadcrumb |
|---|---|---|
| DRV-8801M | gach, gach-lat-nen, gach-phong-khach, gach-mat-tien, gach-van-da, gach-porcelain, gach-80x80 (7) | gach-lat-nen |
| DRV-3605S | gach, gach-lat-nen, gach-op-tuong, gach-nha-tam, gach-phong-bep, gach-van-da, gach-porcelain, gach-30x60 (8) | gach-op-tuong |
| DRV-61209 | gach, gach-lat-nen, gach-op-tuong, gach-phong-khach, gach-nha-tam, gach-phong-ngu, gach-van-xi-mang, gach-porcelain, gach-60x120 (9) | gach-60x120 |
| DRV-SLAB01 | gach, gach-lat-nen, gach-op-tuong, gach-trang-tri, gach-phong-khach, gach-mat-tien, gach-phong-bep, da-nung-ket, gach-van-da (9) | da-nung-ket |
| DRV-W1590 | gach, gach-lat-nen, gach-op-tuong, gach-phong-ngu, gach-phong-khach, gach-ngoai-troi, gach-gia-go, gach-porcelain, gach-15x80-20x100 (9) | gach-gia-go |
| DRV-TRZ60 | gach, gach-lat-nen, gach-op-tuong, gach-trang-tri, gach-phong-khach, gach-nha-tam, gach-phong-bep, gach-ngoai-troi, gach-terrazzo, gach-porcelain, gach-60x60 (**11**, xem ghi chú) | gach-terrazzo |
| DRV-SV40 | gach, gach-lat-nen, gach-san-vuon, gach-ngoai-troi, gach-van-da, gach-porcelain (6) + lọc `?kich-thuoc=40x40` có SP | gach-san-vuon |
| DRV-TH7530 | gach, gach-op-tuong, gach-trang-tri, gach-the, gach-phong-bep, gach-nha-tam (6) + lọc `?mau-sac=xanh-la` có SP | gach-the |

> **Ghi chú DRV-TRZ60:** tài liệu Silo gắn tag `porcelain` nhưng danh sách URL bỏ sót `/gach-porcelain` (10 URL). Theo rule M07 (`chat-lieu ∋ porcelain`), kết quả đúng là **11 URL**. Test theo rule.

**Case bổ sung**

| # | Thao tác | Mong đợi |
|---|---|---|
| S-1 | Bỏ `phong-khach` khỏi DRV-8801M rồi lưu | Mất `gach-phong-khach`; các danh mục khác giữ nguyên |
| S-2 | Gán tay danh mục ngoài hệ thống (vd `noi-bat`) | Giữ nguyên sau khi sync |
| S-3 | Đặt giá KM cho DRV-TRZ60 | Thêm `gach-uu-dai`; hết lịch KM → tự gỡ |
| S-4 | Sản phẩm 1200×2400 không gán `da-nung-ket` | Vẫn vào `da-nung-ket` (large_format) |
| S-5 | Sản phẩm 600×1200 | **Không** vào `da-nung-ket` |
| S-6 | Primary bị xóa khỏi danh mục SP | Tự chọn lại theo priority + cảnh báo admin |
| S-7 | Import CSV 50 SP | Mỗi SP sync đúng, không timeout |
| S-8 | Tạo Page slug `gach-80x80` | Bị đổi thành `gach-80x80-trang` + notice |
| S-9 | Lưu SP 2 lần liên tiếp | Không vòng lặp save, không trùng term |

### 3.2 Đơn vị & giá

| # | Input | Mong đợi |
|---|---|---|
| U-1 | 800×800, 3 viên/thùng, 385.000đ/m² | `m2_per_box` 1,92; `_regular_price` 246.400; card "385.000đ/m² · ≈ 246.400đ/viên" |
| U-2 | 75×300, 44 viên/thùng, 450.000đ/m² | area 0,0225; giá/viên 10.125; m²/thùng 0,99 |
| U-3 | Giá KM 350.000đ/m² cho U-1 | `_sale_price` 224.000; hiển thị giá cũ gạch ngang + nhãn % |
| U-4 | Máy tính U-1: 28 m², hao hụt 5%, làm tròn thùng | need 29,4 m² → 46 viên → làm tròn **48 viên = 16 thùng = 30,72 m²**, thành tiền 11.827.200đ |
| U-5 | Như U-4, tắt làm tròn thùng | 46 viên = 15 thùng + 1 viên = 29,44 m² |
| U-6 | Nhập số lượng theo m² = 10 (U-1) | Quy đổi lên 16 viên (10,24 m²), gửi server 16 |
| U-7 | `drv_qty_step` = 3, thêm 4 viên | Server từ chối với thông báo rõ, hoặc làm tròn lên 6 (theo UI) |
| U-8 | Đơn hàng sau khi đổi giá SP | Order item meta giữ giá/m² và quy đổi cũ |
| U-9 | Tồn kho 10 viên, thêm 12 | Báo lỗi tồn kho |
| U-10 | Kích thước đổi 600×600 → `pa_kich-thuoc` | Tự đổi thành `60x60` và sync danh mục |

### 3.3 URL & chuẩn hóa

| # | Request | Mong đợi |
|---|---|---|
| R-1 | `/gach-lat-nen/` | 301 → `/gach-lat-nen` |
| R-2 | `/product-category/gach/gach-lat-nen` | 301 → `/gach-lat-nen` |
| R-3 | `/gach?cong-nang=lat-nen` (chỉ 1 param, có collection) | 301 → `/gach-lat-nen` |
| R-4 | `/gach-phong-khach?be-mat=mo&kich-thuoc=80x80` | 301 → `/gach-phong-khach?kich-thuoc=80x80&be-mat=mo` (thứ tự config) |
| R-5 | `/gach-phong-khach?mau-sac=Xam,trang,xam` | 301 → `?mau-sac=trang,xam` |
| R-6 | `/gach-phong-khach?abc=1` | 301 → `/gach-phong-khach` |
| R-7 | `/gach-phong-khach?utm_source=fb` | 200, giữ nguyên, canonical sạch |
| R-8 | `/gach-phong-khach?kich-thuoc=99x99` | 301 → `/gach-phong-khach` |
| R-9 | `/gach-phong-khach/page/999` | 404 |
| R-10 | `/san-pham` | 301 → `/gach` (Phase 1) |

### 3.4 Ma trận robots/canonical

| # | URL | robots | canonical |
|---|---|---|---|
| M-1 | `/gach-lat-nen` | index, follow | `/gach-lat-nen` |
| M-2 | `/gach-lat-nen/page/2` | index, follow | `/gach-lat-nen/page/2` |
| M-3 | `/gach-lat-nen?kich-thuoc=60x60` | noindex, follow | `/gach-lat-nen` |
| M-4 | `/gach-lat-nen?kich-thuoc=60x60&be-mat=bong-kieng` | noindex, follow | `/gach-lat-nen` |
| M-5 | `/gach-nha-tam?dcof=r10` | noindex, nofollow | `/gach-nha-tam` |
| M-6 | `/gach-nha-tam?be-mat=nham&gia=200k-350k` | noindex, nofollow | `/gach-nha-tam` |
| M-7 | `/gach-lat-nen/page/2?kich-thuoc=60x60` | noindex, follow | `/gach-lat-nen` |
| M-8 | `/bo-suu-tap/dravit-luxury` | index, follow | chính nó |
| M-9 | `/kich-thuoc/80x80` (pa không public) | 404 | |
| M-10 | Collection 0 sản phẩm | noindex, follow | chính nó |
| M-11 | `/wp-json/dravit/v1/catalog?...` | Header `X-Robots-Tag: noindex` | |
| M-12 | URL có param: không có `ItemList` JSON-LD; URL sạch có | | |
| M-13 | URL sạch: facet nhóm seo là `<a href>`; URL có param: không còn `<a>` 1-param; facet tech không bao giờ là `<a>` | | |

### 3.5 Bộ lọc AJAX & load more

| # | Thao tác | Mong đợi |
|---|---|---|
| F-1 | Tick "80x80" trên `/gach-phong-khach` | Grid cập nhật không reload; URL `?kich-thuoc=80x80`; title đổi; count đúng |
| F-2 | Back | Về trạng thái trước, grid đúng |
| F-3 | Tải trực tiếp URL ở F-1 | SSR cho cùng kết quả, cùng HTML card |
| F-4 | Tắt JS, chọn filter + "Áp dụng" | Hoạt động bằng GET, phân trang số hoạt động |
| F-5 | Tick nhanh 3 lần | Chỉ request cuối được áp dụng (abort) |
| F-6 | Load more 2 lần | 72 sản phẩm, không trùng; nút ẩn khi hết |
| F-7 | Load more → vào SP → Back | Khôi phục 72 SP và vị trí cuộn |
| F-8 | Mobile bottom sheet | Nút "Xem {n} sản phẩm" cập nhật số; Esc/đóng trả focus |
| F-9 | Count facet | Đếm "ngoại trừ chính nó" đúng; giá trị 0 bị disabled |
| F-10 | Click link collection khác trong filter | Thay `<main>`, cập nhật canonical/robots/JSON-LD, URL tĩnh |
| F-11 | Đổi giá 1 SP | Cache REST + facet được purge, kết quả mới |
| F-12 | Screen reader | Đọc "Đã tìm thấy n sản phẩm" sau mỗi lần lọc |

### 3.6 Trang sản phẩm

| # | Kiểm tra | Mong đợi |
|---|---|---|
| P-1 | Breadcrumb | Theo primary, 1 BreadcrumbList duy nhất |
| P-2 | Bảng thông số | Giá trị thuộc collection là link đúng URL |
| P-3 | Swatch kích thước | Link sang SKU anh em cùng `drv_series` |
| P-4 | Thêm vào giỏ | AJAX, mở drawer, số lượng giỏ cập nhật, page cache không lộ giỏ người khác |
| P-5 | Schema | Product + Offer (giá/viên) + UnitPriceSpecification m²; Rich Results pass |
| P-6 | Gallery | PhotoSwipe chỉ tải khi click; ảnh LCP không lazy |

### 3.7 Checkout & đơn hàng

| # | Thao tác | Mong đợi |
|---|---|---|
| C-1 | Chọn tỉnh | Danh sách phường/xã của tỉnh tải đúng |
| C-2 | SĐT sai định dạng | Lỗi inline, không submit |
| C-3 | Không nhập email | Đặt hàng được |
| C-4 | Đặt COD | Trạng thái **Chờ xác nhận**; tồn kho bị trừ; email "Đã nhận đơn" (khách) + "Đơn mới" (admin) |
| C-5 | Đặt BACS | Như C-4; trang cảm ơn **không** hiện QR |
| C-6 | Admin thêm phí ship + "Xác nhận đơn" (BACS) | Tổng có phí ship; trạng thái **Chờ chuyển khoản**; email có VietQR đúng số tiền + nội dung `DRV{mã}` |
| C-7 | Như C-6 với COD | Trạng thái **Đang xử lý**; email nêu số tiền thu khi giao |
| C-8 | Hủy đơn Chờ xác nhận | Hoàn tồn kho |
| C-9 | Giỏ/checkout | Hiển thị "Phí vận chuyển: báo sau khi xác nhận đơn", không hiện "0đ" |
| C-10 | Email/đơn admin | Dòng SP có quy đổi m²/thùng |
| C-11 | Form báo giá | Lưu `drv_lead`, gửi email, chặn spam (honeypot, rate limit) |

### 3.8 Bảo mật

| # | Kiểm tra |
|---|---|
| X-1 | REST `catalog` với param ngoài whitelist / SQL payload → bị loại, không lỗi PHP |
| X-2 | `per_page=1000` → giới hạn 48 |
| X-3 | Form báo giá thiếu nonce → từ chối |
| X-4 | Order action/Tools resync bằng user không có `manage_woocommerce` → từ chối |
| X-5 | Mọi output có escape (rà PHPCS `WordPress.Security`) |
| X-6 | Không lộ phiên bản WP/WC/Rank Math trong HTML |

## 4. Preflight go-live

**Hiệu suất:** PSI mobile ≥ 90 × 5 URL mẫu · CLS < 0.05 · LiteSpeed cache hit (header `x-litespeed-cache: hit`) · object cache hoạt động · cron thật chạy.
**SEO:** sitemap đúng thành phần · robots.txt đúng · 28 collection index, title/H1/description riêng · URL lọc noindex · canonical đúng · không 2 BreadcrumbList · Rich Results pass · llms.txt truy cập được · GSC + Bing đã submit · redirect cũ (nếu có).
**UI/A11y:** 390/768/1024/1440 không cuộn ngang · tương phản AA · bàn phím đầy đủ · focus rõ · alt ảnh · reduced-motion · không em-dash trong UI · một hệ nút/card/radius.
**Commerce:** đặt đơn COD và BACS thật trên staging · email tới hộp thư (SMTP/SPF/DKIM qua AZDIGI) · VietQR quét được · trang chính sách đủ.
**Kỹ thuật:** không PHP warning trong `debug.log` · WC Status không có template outdated · PHPCS/ESLint/Stylelint sạch · backup tự động · `DISALLOW_FILE_EDIT` · staging noindex, production index.

## 5. Skill theo phase

| Phase | Skill |
|---|---|
| 0 | `wordpress-router`, `wp-project-triage`, `agy-customizations` (rules), `create-design-md` |
| 1 | `wordpress-theme-development`, `php-pro`, `frontend-developer`, `design-taste-frontend`, `baseline-ui`, `web-performance-optimization` |
| 2 | `wp-plugin-development` (hook/data pattern), `wp-rest-api`, `php-pro`, `clean-code` |
| 3 | `wp-rest-api`, `javascript-pro`, `modern-javascript-patterns`, `seo-structure-architect`, `programmatic-seo`, `fixing-accessibility` |
| 4 | `schema-markup`, `frontend-design`, `fixing-motion-performance`, `page-cro` |
| 5 | `form-cro`, `backend-security-coder`, `seo-content-writer`, `content-creator`, `avoid-ai-writing` |
| 6 | `seo-audit`, `wcag-audit-patterns`, `web-design-guidelines`, `wp-performance`, `007`, `code-review-checklist` |
