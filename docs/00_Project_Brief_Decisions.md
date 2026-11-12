# 00. Project Brief & Nhật ký quyết định

## 1. Tổng quan

| Mục | Nội dung |
|---|---|
| Thương hiệu | **DRAVIT** (Công ty TNHH Dravit) |
| Tagline | "Thẩm mỹ từ những đường nét nhỏ nhất." |
| Tính cách | Vững chãi, chuẩn xác, chân thực |
| Website | gachdravit.vn: TMĐT bán lẻ gạch ốp lát, có showroom |
| Mô hình | B2C bán lẻ (chủ nhà), phục vụ thêm thợ/thầu và KTS (báo giá công trình) |
| Nền tảng | WordPress + WooCommerce + Rank Math + theme custom `dravit` |

## 2. Mục tiêu & KPI

| Mục tiêu | KPI nghiệm thu |
|---|---|
| Tốc độ | PageSpeed Insights **mobile ≥ 90** trên 5 template (home, `/gach`, URL lọc, sản phẩm, cẩm nang). CWV: LCP < 2.0s, INP < 200ms, CLS < 0.05 |
| SEO | 28 URL collection được index; URL lọc đúng ma trận robots; Rich Results (Product, Breadcrumb, FAQ) hợp lệ |
| GEO | Nội dung SSR, thông số dạng bảng text, `llms.txt` hoạt động, schema đầy đủ |
| Chuyển đổi | Đặt đơn COD/Chuyển khoản trong ≤ 3 bước; CTA Gọi/Zalo/Báo giá luôn hiện |
| Bảo trì | Một hệ token, một bộ component, không thư viện thừa, code module hóa |

## 3. Phạm vi Phase 1

**Trong phạm vi**
- Ngành hàng: **gạch ốp lát** (silo `/gach` + 27 collection; ưu tiên các collection "Phase 1 - Core" trong tài liệu Silo, nhưng tạo đủ 28 term).
- Theme `dravit`: toàn bộ template public + WooCommerce (danh mục, sản phẩm, giỏ, danh sách báo giá BOQ, checkout, tài khoản tối giản).
- Bộ lọc AJAX + load more, ma trận SEO lọc, schema, llms.txt.
- Đơn vị bán & hiển thị giá:
  - Hỗ trợ song song **Sản phẩm có giá niêm yết (`priced`)** (giá/m² chính, giá/viên phụ, giao dịch lõi WC theo **số nguyên viên**) và **Sản phẩm không niêm yết giá (`quote` - "Liên hệ báo giá")** (vẫn quy đổi đủ m²/viên/thùng/kg, chuyển CTA sang nhận báo giá & thêm vào Danh sách báo giá công trình BOQ).
  - **Cấu hình đơn vị mua theo từng sản phẩm (`D-24`):** Admin thiết lập trên từng SKU các đơn vị khách được chọn mua (`m²` / `thùng` / `viên`), đơn vị mặc định và quy tắc làm tròn (bắt buộc chẵn thùng hoặc cho phép mua lẻ viên); hệ đơn vị mở rộng sẵn cho `piece`/`set`/`pack`.
- Hệ thống Báo giá công trình BOQ (`/danh-sach-bao-gia`) & Thu nhận Lead tập trung (`drv_lead` CPT, `D-23`).
- Thanh toán: **COD + Chuyển khoản (VietQR)**, hỗ trợ thông tin xuất hóa đơn VAT. Vận chuyển: **báo phí sau khi xác nhận đơn**.
- Trang nội dung: Giới thiệu (`/gioi-thieu`), Liên hệ & Showroom (`/lien-he`), Chính sách (đổi trả, vận chuyển, thanh toán, bảo mật, điều khoản), Cẩm nang (`/cam-nang`, `/chu-de/{slug}`).

**Ngoài phạm vi Phase 1** (kiến trúc phải sẵn sàng)
- Ngành hàng khác & vật tư phụ đi kèm (keo chà ron, ke cân bằng, thiết bị vệ sinh - ẩn khối FBT/phụ kiện ở UI Phase 1 theo `D-26`, nhưng `Units\PricingModel\Pack` sẵn sàng).
- Trợ lý AI hội thoại LLM (`/tro-ly-ai-tim-gach`, triển khai ở Phase 3; ở Phase 1 tắt cờ `features.ai_enabled = false` theo `D-25`).
- Thanh toán online (VNPay/MoMo), đối soát ngân hàng tự động, tính phí ship tự động, đa ngôn ngữ, visualizer phối cảnh, so sánh sản phẩm, **wishlist** (bỏ nút yêu thích trên toàn UI Phase 1 theo `D-26`).

## 4. Persona

| Persona | Nhu cầu | Đáp ứng |
|---|---|---|
| Chủ nhà | Chọn gạch theo không gian/phong cách, biết giá/m², tính số lượng | Collection theo không gian, ảnh phối cảnh, máy tính số viên, giá/m² rõ |
| Thợ/thầu | Tìm nhanh theo kích thước/thông số, giá, tồn kho, báo giá số lượng lớn | Lọc kích thước/DCOF/độ hút nước, SKU search, nút Báo giá công trình |
| KTS/thiết kế | Bộ sưu tập, thông số kỹ thuật, ảnh vân chất lượng | Bảng thông số đầy đủ, gallery zoom, bộ sưu tập |

## 5. Nhật ký quyết định (ADR)

| ID | Ngày | Quyết định | Lý do / hệ quả |
|---|---|---|---|
| D-01 | 2026-11-05 | Theme **PHP truyền thống (classic)**; `theme.json` chỉ khai báo token cho editor | Toàn quyền `<head>`/HTML/SEO, filter SSR, CSS nhẹ. Trang nội dung vẫn dùng Gutenberg + patterns |
| D-02 | 2026-11-05 | 28 Smart Collections = **`product_cat`**, tự gán theo thuộc tính khi lưu sản phẩm | Tận dụng Rank Math (bỏ base, primary term, breadcrumb, sitemap) và WC native |
| D-03 | 2026-11-05 | Bộ lọc **tự viết**, **AJAX không tải lại + Load more**, vẫn SSR và crawl được | Đúng tên param trong tài liệu Silo, kiểm soát `<a>`/`<button>` và robots |
| D-04 | 2026-11-05 | **Vanilla CSS hiện đại + Vite**, Vanilla JS ES modules, không jQuery mới | Nhẹ, một nguồn token, dễ bảo trì |
| D-05 | 2026-11-05 | Gạch: **giá/m² chính, giá/viên phụ, đơn vị giao dịch = viên** | Số lượng nguyên → tồn kho/báo cáo WC chính xác. Giá WC (`_price`) = giá/viên, tự tính từ giá/m² |
| D-06 | 2026-11-05 | Hệ đơn vị **mở rộng** (`area_pack`, `piece`, `set`, `pack`) | Sẵn sàng cho chiếc, bộ, tuýp, bao ở phase sau |
| D-07 | 2026-11-05 | **Không tách plugin**: mọi logic trong theme, module hóa namespace `Dravit\` | Rủi ro mất logic khi đổi theme → dữ liệu chỉ lưu ở cấu trúc chuẩn WC, module tách được sau |
| D-08 | 2026-11-05 | Hosting **AZDIGI LiteSpeed + LiteSpeed Cache** | Page cache + object cache; tắt tối ưu CSS/JS của LSCache (Vite đã làm) |
| D-09 | 2026-11-05 | Thanh toán **COD + Chuyển khoản** (VietQR) | Gateway native WC `cod`, `bacs` |
| D-10 | 2026-11-05 | Vận chuyển **báo phí sau khi xác nhận đơn** | Phương thức ship custom phí 0 + trạng thái đơn "Chờ xác nhận" + email xác nhận có phí ship |
| D-11 | 2026-11-05 | Bảng màu chốt theo **Phương án A (Deep Ink & Teal + Amber)** | Brand Primary `#083A4F`, Deep Ink text `#0B192C`, Brand Teal `#0D9488` / `#0F766E`, Warm Amber `#F59E0B`, Clean Slate `#F8FAFC` (thay thế rêu cũ `#3C7D7C` bị nhạt) |
| D-12 | 2026-11-05 | Logo tạm dùng **PNG** (tối ưu kích thước); thay SVG khi có file gốc | `<img>` có width/height, bản 1x/2x WebP |
| D-13 | 2026-11-05 | Định vị Phase 1 theo tagline "Thẩm mỹ từ những đường nét nhỏ nhất", tập trung **gạch ốp lát** | Menu, home, copy xoay quanh gạch; keo chà ron để phase sau |
| D-14 | 2026-11-05 | Giữ `/san-pham/{slug}`, **không** bật `wc_remove_product_base` của Rank Math | Tránh query phân giải slug mỗi request, tránh xung đột URL |
| D-15 | 2026-11-05 | Cart/Checkout dùng **shortcode classic**, không dùng Cart/Checkout block | Nhẹ, dễ tùy biến trường địa chỉ VN 2 cấp |
| D-16 | 2026-11-05 | Địa chỉ VN **2 cấp: Tỉnh/TP (34) → Phường/Xã** | Theo sắp xếp đơn vị hành chính từ 01/07/2025 |
| D-17 | 2026-11-05 | Kiến trúc Component 4 lớp: `drv_ui()` PHP + `.l-*` Layout Primitives & `--_*` Scoped CSS + `data-drv-action` & Light DOM Custom Elements (`<drv-*>`) | Tái sử dụng 100% component với Vanilla CSS/JS, tự động gắn logic khi chèn DOM qua AJAX |
| D-18 | 2026-11-05 | Phân định màu chữ & Header Menu Cấp 2-3 | Header text & H1–H6: `#083A4F`; Chữ nội dung: `#0B192C`; Hover menu header: nền `#083A4F` chữ trắng; Menu Cấp 2-3: Flyout Desktop + Accordion Mobile; Mega Menu áp dụng **Phương án 2 (Live Dynamic Preview Cột 5 + Bottom Utility Strip)** |
| D-19 | 2026-11-05 | Kiến trúc Animation **Option A + B kết hợp** | **Option A:** 100% Hover/UI bằng Pure CSS GPU (`transform`, `opacity`, `--ease-out-expo`). **Option B:** Nạp nhẹ **Anime.js v3.2.2** (~6.5KB gzip, tự host qua Vite chunk, `import()` động) cho Staggered Grid Reveal (`[data-drv-stagger]`), Hero/Section Reveal (`[data-drv-reveal]`), Number Odometer trong `<drv-unit-calc>` và Spring Cart Bounce. Loại bỏ hiệu ứng `glass-shine` toàn trang gây tốn INP |
| D-20 | 2026-11-11 | Hỗ trợ song song **Sản phẩm có giá (`priced`) và Không có giá (`quote` - "Liên hệ báo giá")** + chuẩn hóa `drv_price_state` | Cho phép để trống hoặc nhập `0` ở `drv_price_m2` đối với gạch dự án/khổ lớn/hàng đặt riêng. Trạng thái suy dẫn `drv_price_state` (`priced` / `sale` / `quote_only` / `out_of_stock`). Khi không có giá: khóa `add-to-cart` WC (`is_purchasable = false`), ẩn tổng tiền nhưng **giữ nguyên bộ tính quy đổi `m² ⇄ viên ⇄ thùng ⇄ kg`**, đổi CTA trên Card/PDP sang `Báo giá` / `Thêm vào DS báo giá`, tự gán term `gia-du-an`, không gắn chip `% giảm`, và ẩn `Offer` giá 0đ trong JSON-LD |
| D-21 | 2026-11-11 | **Ma trận Robots cho URL lọc & Cấm Trailing Slash** | Mọi URL có tham số (`?param`) đều `noindex`: chỉ gồm param nhóm `seo` (FLT-01..09) → `noindex, follow`; có bất kỳ param nhóm `tech` (FLT-10..14) → `noindex, nofollow`. Chỉ 28 URL tĩnh collection, `/bo-suu-tap/*`, `/xuat-xu/*` được `index, follow`. Toàn site **không** dùng dấu `/` cuối URL (biến thể có `/` redirect 301) |
| D-22 | 2026-11-11 | **Chuẩn hóa Slug Trạng thái Đơn HPOS (≤ 20 ký tự) & Quy tắc VietQR** | Trạng thái đơn: `wc-cho-xac-nhan` (*Chờ xác nhận*, 15 ký tự, trừ kho ngay) → `wc-on-hold` đổi nhãn *Chờ chuyển khoản* (BACS) hoặc `wc-processing` (*Đang xử lý*, COD). Loại bỏ `drv-awaiting-*` do vượt `varchar(20)` của WP/WC. Mã đơn hiển thị `DRV-{id}`, nội dung CK `DRV{id}` (chỉ chữ số). Chỉ render ảnh VietQR khi đơn BACS ở trạng thái `on-hold`; cấm hứa hẹn đối soát tự động |
| D-23 | 2026-11-11 | **BOQ & Lead Lưu vào CPT Private `drv_lead` + Chiết khấu Dự kiến** | Trang Danh sách Báo giá BOQ (`/danh-sach-bao-gia`) thuộc Phase 1: bản nháp lưu `localStorage` (`drv_quote_v2`), khi gửi lưu vào **CPT private `drv_lead`** (`_drv_lead_type = boq`) dùng chung với 5 nguồn lead khác (không tạo WC Order nháp, không tạo bảng DB riêng). Server tính lại 100% số liệu và có Admin Action *"Tạo đơn hàng từ báo giá"*. Bậc chiết khấu khối lượng (`≥ 30 m²: 5%`, `≥ 100 m²: 8%` cấu hình trong `drv_settings`) chỉ hiển thị dạng *"Chiết khấu dự kiến"* trên BOQ, không tự trừ vào Giỏ hàng bán lẻ |
| D-24 | 2026-11-11 | **Cấu hình Đơn vị Mua Theo Từng Sản Phẩm (`m²` / `thùng` / `viên`) & 1 Lõi Tính Toán** | Đơn vị tồn kho và `quantity` giao dịch dưới lõi WC vẫn là **số nguyên `viên`** (bảo toàn D-05), nhưng **trong cài đặt từng sản phẩm** (Tab *Đơn vị & Thông số*), Admin thiết lập cho từng SKU: (1) `drv_buy_units` (đa chọn `m2`, `thung`, `vien` - các tab đơn vị khách được nhập trên PDP/Giỏ); (2) `drv_default_buy_unit` (`m2` \| `thung` \| `vien` - đơn vị mở sẵn & hành vi Thêm nhanh trên Card); (3) `drv_round_mode` (`box` = bắt buộc chẵn thùng `qty_step = pieces_per_box` \| `piece` = cho phép mua lẻ viên `qty_step = 1`); (4) `drv_qty_min` (tối thiểu tính theo viên). Client (`utils/calc.js`) và Server (`Dravit\Units\Calc`) dùng chung 1 thuật toán duy nhất |
| D-25 | 2026-11-11 | **Hero Trang chủ Lai AI-First + Ảnh LCP & Cờ Bật/Tắt AI** | Giữ bố cục Hero định hướng tư vấn của prototype nhưng bắt buộc có ảnh phối cảnh `<picture>` thật làm phần tử LCP (`fetchpriority="high"`); hiệu ứng `<canvas>` chỉ khởi chạy trên desktop (`≥ 1024px`) sau LCP và tắt hẳn khi `prefers-reduced-motion`. Ở Phase 1 mặc định `drv_settings.features.ai_enabled = false` (ẩn nút/chip gọi LLM, trang `/tro-ly-ai-tim-gach` để `noindex` chờ Phase 3) |
| D-26 | 2026-11-11 | **Biến thể Series bằng Link SSR, Bỏ Wishlist & Ẩn Phụ kiện Phase 1** | Swatch màu/kích thước/bề mặt trên PDP là thẻ `<a href>` SSR nối tới các SKU anh em qua `drv_series` (1 SKU = 1 URL, cấm đổi giá/SKU giả phía client). Loại bỏ hoàn toàn nút Wishlist trên UI Phase 1. Ẩn khối mua kèm vật tư phụ (`pack` / FBT keo-ron) ở Phase 1 |
| D-27 | 2026-11-11 | **Hiệu chỉnh Trọng số `priority` cho Primary Category** | Giữ cơ chế chọn tự động theo số `priority` trong `config/collections.php`, cập nhật `S04 gach-san-vuon = 55`, `C03 gach-op-tuong = 42`, `M04 gach-van-xi-mang = 42`, `M02 gach-van-da = 30` để khớp chính xác 8/8 SKU chuẩn nghiệm thu |
| D-28 | 2026-11-11 | **Icon Phosphor Subset Self-host, PhotoSwipe 5 & Phân tách Vite Entries** | Thay 3 file CSS CDN Phosphor nặng bằng **1 font subset tự host** (`assets/fonts/phosphor-subset.woff2`, ~140 icon thực dùng, giữ cú pháp `<i class="ph ph-..."></i>`). Thống nhất 1 module **PhotoSwipe 5** (`import()` động) cho cả PDP và Blog. Container chuẩn `1380px`; ngân sách JS Blog `≤ 10KB gzip`. Cấm auto-inject script nặng toàn trang; mọi JS viết chuẩn ES Module tách theo template |
| D-29 | 2026-11-11 | **Header Tier 1 Kép, Mega Menu từ Config & Đường dẫn Tĩnh** | Header Tier 1 hiển thị cả **DS Báo giá** (badge đọc `localStorage`) và **Giỏ hàng** (mở Mini-cart Drawer). Mega Menu 4+1 & Mobile Accordion sinh từ `config/collections.php` + term meta (cache `drv_mega_html`), chỉ chứa URL tĩnh, không chứa `?param`. Slug trang liên hệ chốt `/lien-he` (`301` từ `/showroom`); chuyên mục blog `/chu-de/{slug}`; Tile sub-silo ưu tiên trỏ URL tĩnh |
| D-30 | 2026-11-11 | **Nonce dưới LiteSpeed Cache, VietQR Whitelist, Lô vs Shade & Checkout VAT** | Form public trên trang cache lấy token tươi qua `GET /wp-json/dravit/v1/nonce` (`no-store`) kèm honeypot + time-trap (`≥ 3s`) + rate-limit IP + kiểm tra `Origin`. Cho phép dịch vụ `img.vietqr.io`. Tách bạch `drv_shade` (biến thiên màu V1–V4 trên SP) và `_drv_lot_code` (mã lô xuất kho trên dòng đơn). Checkout 2 cột + topbar tối giản + nhóm trường xuất hóa đơn VAT (`_drv_vat_*`). Nút "Mua ngay" submit `<form method="post">` kèm `drv_buy_now=1` |
| D-31 | 2026-11-11 | **Fluent Forms Hybrid Adapter & Tầng Tương Thích Plugin (`@layer plugins`)** | Mở rộng hệ sinh thái plugin tương thích chuẩn với **Fluent Forms** + lớp `Core\Integrations\{FluentFormsBridge,WooCompat,RankMathCompat,LiteSpeedCompat}` và tầng CSS `@layer plugins`. Bảng dự toán đa phòng `/danh-sach-bao-gia` giữ lõi tính native (`BoqService` + `OrderConverter` tạo đơn HPOS 1-click) đồng thời bắn hook/đẩy bản ghi sang Fluent Forms Entries (nếu cài); 5 điểm form báo giá còn lại hỗ trợ **Dual-Mode** trong `drv_settings.forms` (nhập ID `[fluentform]` thì chạy Fluent Forms tự động nhận ngữ cảnh `SKU / m² / BOQ / UTM` và đồng bộ ngược về `drv_lead`; để trống thì chạy Native Form siêu nhẹ) |
| D-32 | 2026-11-11 | **Mở Sẵn Full Pipeline Trợ Lý AI Tìm Gạch & Bật Ngay `RuleOnlyDriver`** | Nâng cấp `D-25`: mở sẵn 100% kiến trúc `Dravit\Advisor\*` theo `13_AI_Advisor_MVP_Spec.md` (Interfaces `CatalogSearchInterface`, `LlmClientInterface`, `ResultCacheInterface`, `AdvisorLogInterface`, `SnapshotBuilder`, `SlotNormalizer`, `QueryEngine` lọc cứng chống trượt `R10/R11/R12`, `Validator`, REST `/dravit/v1/advisor` & `/advisor/feedback`) và cắt hoàn chỉnh trang `/tro-ly-ai-tim-gach` (`01.1-ai-finder.html`). Cho phép bật chạy thật ngay từ bản dựng đầu bằng driver mặc định `RuleOnlyDriver` (`< 200ms`, `0đ` phí API, nối thẳng sang BOQ); khi điền API Key (`Claude` / `Gemini`) trong `drv_settings.ai` tự nâng cấp lên hội thoại LLM Closed-World mà không sửa UI/Controller |
| D-33 | 2026-11-11 | **Khung Kiểm Thử Tự Động Mỗi Step (PHPUnit 10 + Vitest + Shared Vectors)** | Bắt buộc thiết lập bộ Unit Test & Lint dev-only gồm **PHPUnit 10 + Vitest + Shared JSON Vectors (`tests/fixtures/calc-vectors.json`) + PHPStan + WP-CLI Verify Script** trong `tests/` và `tools/`. Mọi Step trong lộ trình xây dựng theme phải pass 100% test trước khi qua Step tiếp theo; loại bỏ hoàn toàn `tests/`, `tools/`, `vendor/`, `node_modules/` khỏi file `.zip` deploy production |
| D-34 | 2026-11-11 | **Cắt Layout Bottom-Up 4 Tầng & Mở Rộng Section Thuần Code Config-Driven (`S-3`)** | Cắt 12 file HTML mẫu theo 4 tầng chống trùng lặp (`Tokens/.l-*` → `22 UI Primitives drv_ui()` → `Scoped CSS --_* & Light DOM <drv-*>` → `Reusable Sections drv_section(string $name, array $props = [])` tại `template-parts/sections/{name}.php` kèm `ViewModel` chuẩn hóa & nạp CSS/JS chunk theo nhu cầu). Mọi trang/section phát sinh về sau định nghĩa thuần code qua mảng config PHP gọi `drv_section()` & `drv_ui()` (không cài Page Builder hay SCF/ACF, chỉ giữ Gutenberg Block Patterns & Shortcodes cho bài viết Cẩm nang) |
| D-35 | 2026-11-12 | **Hệ thống Menu Chỉnh sửa 100% qua Native WP Menu + Custom Fields (`M-1`, 0 ACF)** | Toàn bộ 6 vùng điều hướng gồm `mega_menu` (Mega Menu 4+1 & Mobile Accordion hỗ trợ 3 cấp), `primary_silo_nav` (Tier 2 trái 3 cấp Dropdown + Flyout), `primary_utility` (Tier 2 phải), và `footer_col_2..4` (3 cột Footer tự lấy tiêu đề cột từ tên Menu WP) đều quản trị trực tiếp bằng kéo-thả trong `Giao diện -> Menu` kèm Custom Fields trên từng mục (`_drv_icon`, `_drv_badge`, `_drv_highlight`, `_drv_preview_img/badge/desc/price`), không cài ACF/SCF. Khi `mega_menu` chưa gán, tự động fallback 100% sang `config/collections.php` + `product_cat` stats; mọi link đi qua `MenuUrlSanitizer` cắt dấu `/` cuối & chặn rò rỉ `?param`, lưu cache transient `drv_mega_html` (`0` DB query khi có cache) |

> **Dải mã D-41 → D-49:** Dành cho các đặc tả chi tiết tầng mở rộng LLM/Tier 1-2 của Trợ lý AI (xem [12_AI_Advisor_Strategy_Analysis.md](12_AI_Advisor_Strategy_Analysis.md) & [13_AI_Advisor_MVP_Spec.md](13_AI_Advisor_MVP_Spec.md)); toàn bộ Interfaces & lõi `RuleOnlyDriver` đã được mở sẵn ngay từ Phase 1 theo `D-32`.

## 6. Giả định

- Mỗi SKU (mẫu + kích thước + bề mặt) là 1 simple product, 1 URL.
- Giá niêm yết đã gồm VAT (nếu không, cần bổ sung cấu hình thuế).
- Chủ dự án cung cấp ảnh sản phẩm thật (ảnh viên gạch + ảnh phối cảnh) và dữ liệu thông số.
- Một ngôn ngữ (tiếng Việt).

## 7. Việc còn mở (cần chủ dự án cung cấp)

| # | Hạng mục | Cần cho phase |
|---|---|---|
| O-01 | Logo SVG gốc (thay PNG) | Khi có |
| O-02 | Thông tin tài khoản ngân hàng (ngân hàng, mã BIN, số TK, chủ TK) cho VietQR | 5 |
| O-03 | Hotline, Zalo OA/số Zalo, địa chỉ + giờ mở cửa showroom, Google Maps | 1 |
| O-04 | Nội dung chính sách: đổi trả, vận chuyển, thanh toán, bảo mật, bảo hành, điều khoản | 5 |
| O-05 | Dữ liệu sản phẩm (CSV) + ảnh + quy cách thùng + cài đặt đơn vị mua (`m²/thùng/viên`) từng SKU | 2 |
| O-06 | Bộ dữ liệu Phường/Xã & mã 34 Tỉnh/TP chính thức sau sắp xếp (nguồn Tổng cục Thống kê / Cổng dữ liệu quốc gia) | 5 |
| O-07 | Nội dung SEO cho 28 collection (intro, bảng thông số GEO, SEO footer, FAQ) | 3 |
| O-08 | Favicon (tạo từ ký tự "D" của logo khi có SVG) | 1 |
| O-09 | Tên pháp nhân chính xác, MST, tên miền email chính thức, địa chỉ Showroom/Kho viết theo đơn vị hành chính 2 cấp mới | 1 |
| O-10 | Bộ ảnh Showroom thực tế (gồm ảnh cặp đối chiếu ánh đèn `3000K` và `6500K` thật nếu bật thanh trượt so sánh nhiệt độ màu) | 5 |
| O-11 | Cam kết SLA phản hồi báo giá/gọi xác nhận đơn thống nhất toàn site (VD: trong 30 phút giờ làm việc `8:00 - 21:00`) | 1 |
| O-12 | Điều kiện thương mại cụ thể: giữ lô tối đa bao nhiêu ngày, đổi trả thùng dư, gửi mẫu miễn phí, tải file Map CAD, cọc đơn liên tỉnh, số ngày giữ đơn chờ chuyển khoản | 4–5 |
| O-13 | Hồ sơ nhân sự thật (tác giả & kỹ sư thẩm định: họ tên, chức danh, ảnh, kinh nghiệm) cho bài viết Cẩm nang E-E-A-T | 5 |
| O-14 | Link mạng xã hội chính thức, link thông báo Bộ Công Thương (nếu có), danh sách 5 từ khóa tìm kiếm phổ biến mặc định | 1 |
| O-15 | Mã kệ trưng bày showroom theo từng SKU (`drv_showroom_shelf`) và bảng chỉ số chống trượt `R` tối thiểu theo không gian do kỹ thuật ký duyệt | 2 |

## 8. Thuật ngữ

| Thuật ngữ | Nghĩa |
|---|---|
| Collection / Smart Collection | 1 trong 28 URL danh mục tĩnh (term `product_cat`) |
| Silo | Cấu trúc chủ đề `/gach` → 27 collection → sản phẩm, liên kết nội bộ khép kín |
| Facet | Một bộ lọc (FLT-01..14) |
| Chiều lọc 1 / 2+ | Số thuộc tính đang được chọn: chiều 1 = URL tĩnh (`index`), có bất kỳ `?param` = URL lọc (`noindex`, `D-21`) |
| Primary category | Danh mục chính quyết định breadcrumb sản phẩm (`rank_math_primary_product_cat`) |
| Đơn vị mua theo SP (`D-24`) | Cấu hình trên từng sản phẩm cho phép khách chọn mua theo `m²`, `thùng`, hoặc `viên` (lẻ viên hay bắt chẵn thùng), quy đổi về số nguyên `viên` khi giao dịch WC |
| BOQ (Bill of Quantities) | Bảng dự toán & danh sách báo giá công trình theo hạng mục phòng (`/danh-sach-bao-gia`) |
| Lead (`drv_lead`) | Bản ghi yêu cầu tư vấn/báo giá lưu trong CPT private `drv_lead` |
| Shade (`V1–V4`) vs Mã lô | `drv_shade` là độ biến thiên vân/màu thiết kế của mẫu gạch; `_drv_lot_code` là mã lô sản xuất thực tế trong kho khi xuất hàng |
| SSR | Server-side render: HTML đầy đủ từ PHP, không phụ thuộc JS |
| GEO | Generative Engine Optimization: tối ưu để AI (ChatGPT, Gemini, Perplexity) đọc và trích dẫn |

