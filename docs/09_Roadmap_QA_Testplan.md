# 09. Lộ trình, Nghiệm thu & Test plan

> Mỗi phase **phải qua checklist nghiệm thu** trước khi sang phase tiếp theo.

## 1. Lộ trình

| Phase | Nội dung | Đầu ra | Nghiệm thu |
|---|---|---|---|
| **0. Chuẩn bị** | Thay rules `.agents/rules` bằng `dravit-*` (`02` §13); cài LiteSpeed Cache (tắt ở local); cấu hình WC: VND, VN-only, permalink `/san-pham`, trang WC (slug tiếng Việt, không dấu `/` cuối), COD + BACS, HPOS; Rank Math theo `06` §2; permalink `06` §1; thu thập O-02, O-03, O-05, O-09, O-11 | Rules, site cấu hình | Review rules; permalink đúng; Rank Math bỏ base category hoạt động |
| **1. Theme skeleton** | `style.css`, bootstrap/autoload, `Core\*`, Vite + manifest (tách entry theo template, container `1380px`), `tokens.json` → `tokens.css` + `theme.json`, font subset preload, **Phosphor Icons subset tự host** (`phosphor-subset.woff2`, `D-28`), **Logo WebP 1x/2x**, **Header Tier 1 kép** (Giỏ hàng drawer + DS Báo giá badge `localStorage`, `D-29`), **Mega Menu 4+1 & Mobile Accordion từ `config/collections.php`** (cache `drv_mega_html`, chỉ URL tĩnh), live search UI + `/dravit/v1/search`, footer/mobile bar/dock, Settings page, **gỡ bỏ toàn bộ thanh demo/mock của prototype** ([16_Template_Component_Porting_Guide.md](16_Template_Component_Porting_Guide.md)) | Theme kích hoạt được | Lighthouse trang trống ≥ 98; không PHP notice; a11y header/footer pass; 0 link `?param` trong shell |
| **2. Catalog & Units** | Attributes + term chuẩn (`03` §2), taxonomy ẩn, seeder 28 `product_cat`, `CollectionSync`, `PrimaryCategory` (**trọng số `priority` cập nhật `D-27`**), `SlugGuard` (bảo vệ thêm `lien-he`, `danh-sach-bao-gia`, `tro-ly-ai-tim-gach`), `SaleSync`, Product/Term meta mới ([14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md)), trạng thái giá `priced`/`quote` (`D-20`), **cấu hình đơn vị mua theo từng SP** (`drv_buy_units`: `m2/thung/vien`, `drv_default_buy_unit`, `drv_round_mode`: `box\|piece`, `drv_qty_min`, `D-24`), **lõi tính toán dùng chung `Dravit\Units\Calc` + `utils/calc.js`**, import CSV mapping, seed 8 SKU | Dữ liệu & đơn vị chuẩn | Test §3.1, §3.2 (`U-1`..`U-16`) pass 100% |
| **3. Danh mục + bộ lọc** | `archive-product.php`, card (`drv_card_label`, kệ showroom, ảnh hover, `--priced/--quote`), pills, tile sub-silo ưu tiên URL tĩnh, **1 `<form>` filter duy nhất dùng chung sidebar desktop & `<dialog>` mobile** (dời DOM, không nhân đôi `name`), `FacetRegistry` (ẩn facet trùng chiều 1), `UrlState` (không `/` cuối), `QueryBuilder`, `FacetCounter`, REST `catalog` (thêm `fragment=count`), `catalog.js`, load more + `<a rel="next">`, `RobotsMatrix` (`D-21`), `Canonical`, **bảng thông số GEO** (`drv_geo_table`), **`FAQPage` JSON-LD chỉ lấy từ `drv_faq` đang hiển thị** | Trang danh mục hoàn chỉnh | Test §3.3, §3.4, §3.5 pass; chạy được khi tắt JS; PSI collection ≥ 90 |
| **4. Sản phẩm** | Template sản phẩm, gallery 6 vai trò + **PhotoSwipe 5** (`import()` động dùng chung PDP & Blog, `D-28`), **swatch biến thể `<a href>` SSR qua `drv_series`** (`D-26`, bỏ Wishlist & ẩn phụ kiện FBT), **máy tính đa đơn vị `<drv-unit-calc>` (`m² \| Thùng \| Viên`**, hao hụt `0/5/10%`, hiện kg), add-to-cart AJAX (gửi số nguyên viên), **nút Mua ngay `<form method="post">` (`drv_buy_now=1`)**, **Sticky bar hỗ trợ `priced`/`quote`**, mini-cart drawer (Store API + ExtendSchema), bảng thông số IL-03 + mặt vân + Map/CAD, related IL-06 + đã xem, schema Product (`D-20`, `D-30`) | Trang SP hoàn chỉnh | Test §3.2, §3.6 (`P-1`..`P-9`); Rich Results pass; PSI ≥ 90 |
| **5. Commerce, BOQ & nội dung** | **Trang BOQ `/danh-sach-bao-gia`** (`localStorage` `drv_quote_v2` → CPT `drv_lead`, chiết khấu dự kiến, Admin tạo đơn, [17_Quote_BOQ_And_Lead_Spec.md](17_Quote_BOQ_And_Lead_Spec.md)), **endpoint hợp nhất `/dravit/v1/lead` + `/dravit/v1/nonce`** (`D-30`), **Checkout 2 cột + topbar tối giản + xuất VAT (`_drv_vat_*`)** + 34 tỉnh/phường xã, `QuoteShippingMethod`, trạng thái **`wc-cho-xac-nhan` (`≤ 20` ký tự, `D-22`)**, **Action Scheduler tự hủy đơn chờ CK quá hạn**, vận hành đơn HPOS & Email templates + VietQR `on-hold` ([18_Order_Ops_And_Email_Spec.md](18_Order_Ops_And_Email_Spec.md)), trang chủ (Hero lai + ảnh LCP, `ai_enabled = false`, `D-25`), **Cẩm nang shortcodes/patterns/TOC/E-E-A-T** ([20_Blog_Editorial_And_Content_Model.md](20_Blog_Editorial_And_Content_Model.md)), `/lien-he` (`301` từ `/showroom`), 404, chính sách, `llms.txt` | Đặt hàng & BOQ end-to-end | Test §3.7, §3.7B, §3.8 pass |
| **6. QA & go-live** | CWV 5 template, WCAG 2.2 AA, bảo mật, cross-browser, staging AZDIGI + LiteSpeed, crawl kiểm tra, GSC/Bing, redirect, backup | Go-live | Preflight §4 pass 100% |

## 2. Phụ thuộc dữ liệu

| Phase cần | Dữ liệu | Mã |
|---|---|---|
| Khi có | Logo SVG gốc (thay bản PNG/WebP tạm) | O-01 |
| 1 | Hotline, Zalo OA/số Zalo, địa chỉ + giờ mở cửa showroom, Google Maps | O-03 |
| 1 | Favicon (tạo từ ký tự "D" của logo) | O-08 |
| 1 | Tên pháp nhân chính xác, MST, domain email chính thức, địa chỉ Showroom/Kho theo đơn vị hành chính 2 cấp mới | O-09 |
| 1 | SLA phản hồi báo giá/gọi xác nhận đơn thống nhất toàn site (trong giờ làm việc) | O-11 |
| 1 | Link mạng xã hội chính thức, link Bộ Công Thương (nếu có), 5 từ khóa tìm kiếm phổ biến mặc định | O-14 |
| 2 | CSV sản phẩm + ảnh + quy cách thùng + cấu hình đơn vị mua (`m²/thùng/viên`, `round_mode`) từng SKU | O-05 |
| 2 | Mã kệ trưng bày showroom theo SKU (`drv_showroom_shelf`); bảng chỉ số chống trượt `R` tối thiểu theo không gian có kỹ thuật ký duyệt | O-15 |
| 3 | Nội dung SEO 28 collection (intro, bảng thông số GEO, SEO footer, FAQ hiển thị) | O-07 |
| 4–5 | Điều kiện thương mại: số ngày giữ lô, đổi trả thùng dư, gửi mẫu miễn phí, tải Map/CAD, cọc đơn liên tỉnh, hạn giữ đơn chờ chuyển khoản | O-12 |
| 5 | Tài khoản ngân hàng + mã BIN cho VietQR; nội dung các trang chính sách; dữ liệu Phường/Xã & mã 34 Tỉnh/TP chính thức | O-02, O-04, O-06 |
| 5 | Bộ ảnh Showroom thực tế (gồm cặp ảnh đối chiếu ánh đèn `3000K` và `6500K` thật nếu bật thanh trượt so sánh) | O-10 |
| 5 | Hồ sơ nhân sự thật (tác giả & kỹ sư thẩm định: họ tên, chức danh, ảnh, kinh nghiệm) cho bài viết Cẩm nang E-E-A-T | O-13 |

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

### 3.2 Đơn vị, giá & cấu hình đơn vị mua theo SP (`D-20`, `D-24`)

| # | Input | Mong đợi |
|---|---|---|
| U-1 | 800×800, 3 viên/thùng, 385.000đ/m² | `m2_per_box` 1,92; `_regular_price` 246.400; card "385.000đ/m² · ≈ 246.400đ/viên" |
| U-2 | 75×300, 44 viên/thùng, 450.000đ/m² | area 0,0225; giá/viên 10.125; m²/thùng 0,99 |
| U-3 | Giá KM 350.000đ/m² cho U-1 | `_sale_price` 224.000; hiển thị giá cũ gạch ngang + nhãn % |
| U-4 | Máy tính U-1: 28 m², hao hụt 5%, làm tròn thùng | need 29,4 m² → 46 viên → làm tròn **48 viên = 16 thùng = 30,72 m²**, thành tiền 11.827.200đ |
| U-5 | Như U-4, tắt làm tròn thùng | 46 viên = 15 thùng + 1 viên = 29,44 m² |
| U-6 | Nhập số lượng theo m² = 10 (U-1) | Quy đổi lên 16 viên (10,24 m²), gửi server 16 |
| U-7 | `drv_round_mode = box` (`qty_step` = 3), thêm lẻ 4 viên | Server từ chối với thông báo rõ hoặc làm tròn lên 6 viên (theo ngữ cảnh UI) |
| U-8 | Đơn hàng sau khi đổi giá SP | Order item meta giữ giá/m² và quy đổi cũ |
| U-9 | Tồn kho 10 viên, thêm 12 | Báo lỗi tồn kho |
| U-10 | Kích thước đổi 600×600 → `pa_kich-thuoc` | Tự đổi thành `60x60` và sync danh mục |
| U-11 | SP không nhập `drv_price_m2` (trống hoặc `0`, D-20) | `_regular_price` rỗng, `is_purchasable = false`, tự gán `drv_price_band = gia-du-an`; Card hiện "Liên hệ báo giá" + nút "Báo giá"; PDP `<drv-unit-calc>` vẫn tính đúng số thùng/viên/m²/kg nhưng ẩn tổng tiền và đổi CTA sang "Nhận báo giá ngay" / "Thêm vào DS báo giá"; không xuất `Offer` giá 0đ trong JSON-LD; sort `gia-tang` xếp sau SP có giá |
| U-12 | SKU cấu hình `drv_buy_units=[m2,thung]`, `drv_round_mode=box` (U-1: 3 viên/thùng, `1,92 m²/thùng`) | Tab `Viên` bị ẩn; nhập `10 m²` làm tròn chẵn thùng lên `18 viên` (`6 thùng = 11,52 m²`); nhập đúng bội số `1,92 m²` (hao hụt `0%`, kiểm sai số dấu phẩy động epsilon `1e-9`) ra đúng **`3 viên` (`1 thùng`)**, tuyệt đối không nhảy lên `6 viên`; gửi request lẻ `4 viên` lên server bị từ chối hoặc ép tròn lên `6` |
| U-13 | SKU cấu hình `drv_buy_units=[m2,thung,vien]`, `drv_round_mode=piece`, `drv_default_buy_unit=vien` | Hiện đủ 3 tab `m² \| Thùng \| Viên` (mặc định mở tab `Viên`); cho phép mua lẻ `4 viên` (`1 thùng + 1 viên lẻ`); nút Thêm nhanh trên Card cộng `1 viên` (hoặc `drv_qty_min`); trong Giỏ hàng hiển thị cảnh báo lẻ viên kèm nút "Làm tròn chẵn thùng" |
| U-14 | Kiểm thử đối xứng `Dravit\Units\Calc` (PHP) === `utils/calc.js` (JS) | Khớp tuyệt đối 100% đầu ra (`pieces`, `boxes`, `loose_pieces`, `actual_m2`, `kg`, `total_vnd`) trên toàn bộ 8 SKU seed + các ca biên (`0,5 m²`, đang khuyến mãi, trạng thái `quote`) |
| U-15 | Card sản phẩm trạng thái `quote` (có tồn kho quản lý) | Tuyệt đối không hiển thị chip `% giảm`; hiển thị nhãn tồn kho quy đổi đúng theo thùng (`Còn X thùng` hoặc `Sắp hết`) |
| U-16 | SKU cấu hình `drv_qty_min = 6` (viên) hoặc chỉ bật 1 đơn vị `drv_buy_units=[vien]` (`drv_unit_label=tấm`) | Ẩn thanh chuyển tab khi chỉ có 1 đơn vị; khóa giảm dưới `drv_qty_min` trên UI; gửi `quantity < 6` lên server bị từ chối hoặc tự nâng lên mức tối thiểu hợp lệ |

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

### 3.4 Ma trận robots, canonical & Schema

| # | URL / Ngữ cảnh | robots | canonical / Kỳ vọng Schema |
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
| M-14 | Trang danh mục có `drv_faq` | index, follow (URL sạch) | `FAQPage` JSON-LD khớp 100% danh sách câu hỏi đang hiển thị từ `drv_faq` (không dư/lệch câu hỏi viết cứng) |
| M-15 | Bài viết Cẩm nang (`single.php`) | index, follow | Chỉ có duy nhất 1 `BreadcrumbList` (qua `/chu-de/{slug}`), dùng `BlogPosting` (có `reviewedBy` khi khai báo), **không** sinh `HowTo` hay microdata HTML trùng lặp |
| M-16 | Crawl toàn bộ Header, Mega Menu, Mobile Drawer, Footer, bài Blog | — | **`0` đường dẫn nội bộ chứa `?param`** (100% trỏ về URL tĩnh không dấu `/` cuối) |

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
| F-8 | Mobile bottom sheet | Nút "Xem {n} sản phẩm" cập nhật số (`fragment=count`); Esc/đóng trả focus |
| F-9 | Count facet | Đếm "ngoại trừ chính nó" đúng; giá trị 0 bị disabled |
| F-10 | Click link collection khác trong filter | Thay `<main>`, cập nhật canonical/robots/JSON-LD, URL tĩnh |
| F-11 | Đổi giá 1 SP | Cache REST + facet được purge, kết quả mới |
| F-12 | Screen reader | Đọc "Đã tìm thấy n sản phẩm" sau mỗi lần lọc |
| F-13 | Kiểm tra DOM trên desktop & mobile | Toàn trang chỉ có **duy nhất 1 `<form>` bộ lọc** (di chuyển node khi mở `<dialog>` mobile), không nhân đôi input trùng `name` |
| F-14 | Mở trang collection chiều 1 `/gach-phong-khach` | Ẩn giá trị lọc trùng chính chiều 1 (`phong-khach`) trong nhóm Không gian |
| F-15 | Kiểm tra giao diện danh mục trên production | Không render badge debug robots hay thanh chuyển trạng thái demo của prototype |

### 3.6 Trang sản phẩm

| # | Kiểm tra | Mong đợi |
|---|---|---|
| P-1 | Breadcrumb | Theo primary, 1 BreadcrumbList duy nhất |
| P-2 | Bảng thông số | Giá trị thuộc collection là link đúng URL |
| P-3 | Swatch màu/kích thước/bề mặt | Thẻ `<a href>` SSR sang SKU anh em cùng `drv_series` (`D-26`, không đổi giá giả phía client) |
| P-4 | Thêm vào giỏ | AJAX (gửi số nguyên viên), mở drawer, số lượng giỏ cập nhật, page cache không lộ giỏ người khác |
| P-5 | Schema | Product (`brand = DRAVIT`, `mpn` không chứa mã lô) + Offer (giá/viên) + UnitPriceSpecification m²; ẩn Offer 0đ nếu `quote`; Rich Results pass |
| P-6 | Gallery | PhotoSwipe 5 chỉ tải khi click (`import()` động); ảnh LCP không lazy |
| P-7 | Nút "Mua ngay" | Submit `<form method="post">` kèm `drv_buy_now=1` (số nguyên viên), thêm vào giỏ và chuyển thẳng `/thanh-toan` (chạy được cả khi tắt JS) |
| P-8 | Sticky bar khi cuộn trang | Đổi đúng thông tin giá và CTA tương ứng ở cả sản phẩm `priced` (giá/m² + Thêm vào giỏ) và `quote` ("Liên hệ báo giá" + Nhận báo giá) |
| P-9 | Mã SKU & dữ liệu khởi tạo JS | Nút sao chép SKU nằm **ngoài** thẻ `<h1>`; khi thiếu `data-*` trên DOM tuyệt đối không fallback về dữ liệu mẫu giả (`DRV-8801M`) |

### 3.7 Checkout & đơn hàng

| # | Thao tác | Mong đợi |
|---|---|---|
| C-1 | Chọn tỉnh | Danh sách phường/xã của tỉnh tải đúng (không tự chọn sẵn phường đầu tiên) |
| C-2 | SĐT sai định dạng | Lỗi inline, không submit |
| C-3 | Không nhập email | Đặt hàng được |
| C-4 | Đặt COD | Trạng thái **Chờ xác nhận** (`wc-cho-xac-nhan`); tồn kho bị trừ; email "Đã nhận đơn" (khách) + "Đơn mới" (admin) |
| C-5 | Đặt BACS | Như C-4; trang cảm ơn **không** hiện QR |
| C-6 | Admin thêm phí ship + "Xác nhận đơn" (BACS) | Tổng có phí ship; trạng thái **Chờ chuyển khoản** (`wc-on-hold`); email & trang đơn có VietQR đúng số tiền + nội dung `DRV{id}` |
| C-7 | Như C-6 với COD | Trạng thái **Đang xử lý** (`wc-processing`); email nêu số tiền thu khi giao |
| C-8 | Hủy đơn Chờ xác nhận | Hoàn tồn kho |
| C-9 | Giỏ/checkout | Hiển thị "Phí vận chuyển: báo sau khi xác nhận đơn", không hiện "0đ"; điều khoản không tick sẵn |
| C-10 | Email/đơn admin | Dòng SP có quy đổi m²/thùng/kg; kho nhập được `_drv_lot_code` khi xuất |
| C-11 | Form liên hệ / lead nhanh | Gửi qua `/dravit/v1/lead`, lưu CPT `drv_lead`, gửi email, chặn spam |
| C-12 | Tick "Xuất hóa đơn công ty (VAT)" | Bắt buộc nhập tên công ty, địa chỉ, mã số thuế khớp regex `^\d{10}(-\d{3})?$`, lưu `_drv_vat_*` qua HPOS CRUD |
| C-13 | Quy tắc VietQR & mã đơn (`D-22`) | VietQR **tuyệt đối không render** khi đơn ở `wc-cho-xac-nhan` (chỉ render khi đơn BACS ở `on-hold`); mã đơn hiển thị `DRV-{id}`, nội dung QR `DRV{id}` (chỉ chữ và số); không có câu hứa đối soát tự động |
| C-14 | Đơn chờ chuyển khoản quá hạn | Action Scheduler tự động hủy đơn `on-hold` quá `transfer_due_days` và hoàn lại tồn kho |
| C-15 | Độ dài slug trạng thái đơn | Mọi custom order status (tính cả tiền tố `wc-`, vd `wc-cho-xac-nhan` = 15 ký tự) dài **`≤ 20` ký tự**, lưu đúng vào `wc_orders.status` |

### 3.7B Báo giá công trình (BOQ) & Lead (`D-23`, `D-30`)

| # | Thao tác | Mong đợi |
|---|---|---|
| Q-1 | Thêm vào báo giá từ Card, PDP và nút chuyển từ Giỏ hàng | Đồng bộ xuyên suốt sang `<drv-quote-panel>`, badge Header Tier 1 và trang `/danh-sach-bao-gia` qua cùng khóa `localStorage` `drv_quote_v2` (tự migrate từ `v1` nếu có) |
| Q-2 | Đổi mức hao hụt `0% / 5% / 10%` trên `/danh-sach-bao-gia` | Cập nhật tức thì số viên, thùng (theo `drv_round_mode` từng SKU), tổng m² và tổng kg toàn phiếu |
| Q-3 | Phiếu BOQ trộn lẫn SP `priced` và SP `quote` | SP `quote` vẫn cộng đầy đủ m²/thùng/kg nhưng không hiện `0đ`, không làm sai tổng tiền tạm tính; tách rõ dòng số mẫu chờ báo giá riêng |
| Q-4 | Đạt ngưỡng khối lượng `≥ 30 m²` (5%) / `≥ 100 m²` (8%) | Chỉ hiển thị dạng "Chiết khấu dự kiến" trên BOQ để sale xác nhận; **không** tự trừ phí vào Giỏ hàng / Checkout bán lẻ |
| Q-5 | Gửi phiếu qua `POST /wp-json/dravit/v1/quote/submit` & tạo đơn | Server tự tính lại 100% số lượng/thành tiền từ DB (bỏ qua chuỗi format từ client), lưu vào CPT private `drv_lead` (`_drv_lead_type = boq`); Admin bấm "Tạo đơn hàng từ báo giá" sinh đơn HPOS `wc-cho-xac-nhan` gắn `_drv_source_lead_id` |
| Q-6 | Mở trang cache tĩnh sau > 24h rồi gửi form BOQ / Lead | Vẫn submit thành công lần đầu nhờ client lấy token tươi từ `GET /wp-json/dravit/v1/nonce` (`Cache-Control: no-store`) ngay trước khi POST |

### 3.8 Bảo mật & tài nguyên ngoài

| # | Kiểm tra |
|---|---|
| X-1 | REST `catalog` với param ngoài whitelist / SQL payload → bị loại, không lỗi PHP |
| X-2 | `per_page=1000` → giới hạn 48 |
| X-3 | Form báo giá / lead thiếu hoặc sai nonce → từ chối (`403`) |
| X-4 | Order action/Tools resync/Convert lead bằng user không có `manage_woocommerce` → từ chối |
| X-5 | Mọi output có escape (rà PHPCS `WordPress.Security`) |
| X-6 | Không lộ phiên bản WP/WC/Rank Math trong HTML |
| X-7 | Chống spam trên `POST /dravit/v1/lead` và `/quote/submit`: điền honeypot hoặc submit `< 3s` (time-trap) bị chặn; vượt ngưỡng IP trả HTTP `429`; sai `Origin` bị từ chối |
| X-8 | Rà HTML & bundle production: **`0` request Google Fonts CDN**, **`0` inline `<script>` nghiệp vụ lớn**, **`0` ảnh hotlink Unsplash/Pexels** (ngoài `img.vietqr.io` ở trang đơn BACS `on-hold`) |

## 4. Preflight go-live

**Hiệu suất:** PSI mobile ≥ 90 × 5 URL mẫu · CLS < 0.05 · LiteSpeed cache hit (header `x-litespeed-cache: hit`, `/dravit/v1/nonce` `no-store`) · object cache hoạt động · cron thật + Action Scheduler chạy.
**SEO:** sitemap đúng thành phần · robots.txt đúng · 28 collection index (không `/` cuối), title/H1/description riêng · URL lọc noindex đúng ma trận `D-21` · canonical đúng · không 2 BreadcrumbList · `FAQPage` khớp FAQ hiển thị · Rich Results pass · `llms.txt` truy cập được · GSC + Bing đã submit · redirect `301` (`/showroom` → `/lien-he`, trailing slash).
**UI/A11y:** 390/768/1024/1440 không cuộn ngang · tương phản AA · bàn phím đầy đủ · focus rõ · alt ảnh · reduced-motion · không em-dash/emoji trong UI · 0 thanh demo prototype · cờ `features.ai_enabled = false` ở Phase 1.
**Commerce & BOQ:** đặt đơn COD + BACS (kèm VAT) và gửi phiếu BOQ thật trên staging · chuyển Lead → Đơn HPOS hoạt động · email tới hộp thư (SMTP/SPF/DKIM qua AZDIGI) · VietQR chỉ hiện ở `on-hold` và quét được · trang chính sách đủ.
**Kỹ thuật:** không PHP warning trong `debug.log` · WC Status không có template outdated, HPOS bật 100% · PHPCS/ESLint/Stylelint sạch · backup tự động · `DISALLOW_FILE_EDIT` · staging noindex, production index.

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
