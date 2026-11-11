# 08. Hiệu suất & Hosting (AZDIGI LiteSpeed)

> Tham chiếu ADR: `D-08`, `D-19`, `D-25`, `D-28`, `D-29`, `D-30` trong [00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md).
> Skill: `web-performance-optimization`, `wp-performance`, `fixing-motion-performance`.

## 1. Ngân sách hiệu suất (`D-28`)

| Template | LCP element | CSS (gzip) | JS (gzip) | Ảnh trên màn đầu | Request màn đầu |
|---|---|---|---|---|---|
| Home (`/`) | Ảnh `<picture>` Hero | ≤ 14KB inline + ≤ 20KB | ≤ 12KB (`core` + `home`) | ≤ 180KB (hero AVIF/WebP) | ≤ 20 |
| Collection (`/gach*`) | H1 hoặc ảnh card đầu | ≤ 14KB inline + ≤ 22KB | ≤ 15KB (`core` + `catalog`) | ≤ 150KB | ≤ 25 |
| URL lọc (SSR) | Như Collection | ≤ 14KB inline + ≤ 22KB | ≤ 15KB (`core` + `catalog`) | ≤ 150KB | ≤ 25 |
| Sản phẩm (PDP) | Ảnh chính Gallery | ≤ 14KB inline + ≤ 22KB | ≤ 18KB (+ PhotoSwipe ~20KB khi click) | ≤ 160KB | ≤ 25 |
| Cẩm nang (`/cam-nang*`) | Ảnh đại diện / H1 | ≤ 14KB inline + ≤ 15KB | **≤ 10KB** (`core` + `blog`) | ≤ 150KB | ≤ 18 |
| Báo giá BOQ (`/danh-sach-bao-gia`) | H1 bảng dự toán | ≤ 14KB inline + ≤ 20KB | ≤ 16KB (`core` + `quote`) | ≤ 80KB | ≤ 18 |
| Cart / Checkout / Nhận đơn | H1 / Tóm tắt đơn | ≤ 30KB (`@layer woo`) | WC jQuery + ≤ 10KB | ≤ 80KB | ≤ 22 |

**CWV mục tiêu (field, p75):** LCP < 2.0s · INP < 200ms · CLS < 0.05 · TTFB < 600ms (cache hit < 200ms). **PSI mobile ≥ 90** trên toàn bộ các loại trang chính.

> **Đối chiếu Baseline từ bản Prototype HTML (Lý do bắt buộc tách Vite Entries):**
> Bản prototype tĩnh trước khi tách bundle đo được **`~75–101KB gzip JS`** và **`~99KB gzip CSS`** trên mỗi trang (do gộp toàn bộ `pages.css` 298KB raw cho mọi trang, nạp 3 file CSS Phosphor CDN ~36KB chặn render, tải chồng 6 file font ~122KB và để `core.js` tự động kéo `ai-finder.js` 23.5KB vào toàn site). Vì vậy, khi đóng gói lên WordPress bằng Vite, **bắt buộc tách riêng entry CSS/JS theo từng template, dùng `import()` động cho tác vụ phụ và đóng gói font/icon subset nội bộ** để giữ trọn ngân sách bên trên.

## 2. Chiến lược CSS/JS (`D-19`, `D-28`)

- **Critical CSS** viết gọn theo từng nhóm template (`critical-home.css`, `critical-catalog.css`, `critical-product.css`, `critical-blog.css`, `critical-commerce.css`, `critical-page.css`), inline trong `<head>` `≤ 14KB`; file CSS đầy đủ nạp bất đồng bộ qua `rel="preload" as="style"` kèm `<noscript>`.
- **Phân tách CSS theo template:** `global.css` (reset, tokens, base, layout, header/footer, UI cơ bản) + 1 file riêng cho từng trang: `home.css`, `catalog.css`, `product.css`, `cart.css` & `checkout.css` (**bắt buộc đặt toàn bộ quy tắc ghi đè giỏ hàng/thanh toán vào trong `@layer woo`**), `quote.css`, `blog.css`.
- **JS chuẩn ES2022 Modules (`type="module"`):** Nạp qua `wp_enqueue_script_module`; giữ `core.js ≤ 8KB gzip`. Cấm viết classic script IIFE, cấm inline `<script>` xử lý logic và cấm tự chèn (`auto-inject`) module nặng vào mọi trang.
- **Không nạp jQuery** trên Home, Collection, PDP, BOQ, Cẩm nang và Liên hệ. Chỉ giữ jQuery của WooCommerce ở `/gio-hang`, `/thanh-toan` và `/tai-khoan`.
- **Icon tự host (`D-28`):** Đóng gói duy nhất 1 file **`assets/fonts/phosphor-subset.woff2` (`~15–20KB`)** chứa ~140 icon thực dùng trong dự án, thay thế hoàn toàn 3 file CSS CDN Phosphor nặng chặn render.
- **Animation & Lightbox tải lười (`import()` động):**
  - Hover & UI thường ngày chạy 100% bằng Pure CSS GPU (`transform`, `opacity`) 0KB JS; cấm listener `mouseover` toàn trang (`glass-shine`).
  - **Anime.js v3.2.2 (`~6.5KB gzip`)** tự host qua Vite chunk, chỉ `import()` động qua `chunks/motion.js` khi trang có `[data-drv-stagger]`, `[data-drv-reveal]` hoặc `<drv-unit-calc>`.
  - **PhotoSwipe 5 (`~20KB gzip`)** dùng chung 1 chunk duy nhất cho cả PDP và bài viết Cẩm nang, chỉ `import()` khi người dùng click phóng to ảnh.
  - Hero `<canvas>` chỉ khởi chạy trên desktop (`≥ 1024px`) sau khi ảnh LCP hoàn tất và tắt hẳn khi bật `prefers-reduced-motion` (`D-25`).
- Speculative loading của WP core giữ mặc định (prefetch khi hover, chế độ conservative).

## 3. Ma trận nạp asset theo Template (`D-28`)

| Asset | Home | Collection / Lọc | Sản phẩm (PDP) | Blog | Giỏ hàng | Checkout / Nhận đơn | Báo giá BOQ | Trang tĩnh / Liên hệ |
|---|---|---|---|---|---|---|---|---|
| `global.css`, `core.js` (`≤ 8KB`: action registry, menu, drawer, badge, toast) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `home.css`, `home.js` (tabs explorer, size-guide, showroom) | ✓ | | | | | | | |
| `catalog.css`, `catalog.js` (`<drv-filter-form>`, load more, URL state) | | ✓ | | | | | | |
| `product.css`, `product.js` (`<drv-unit-calc>`, gallery, sticky bar, viewed) | | | ✓ | | | | | |
| `cart.css` (`@layer woo`), `cart.js` (stepper thùng/viên, làm tròn) | | | | | ✓ | | | |
| `checkout.css` (`@layer woo`), `checkout-vn.js` (tỉnh -> phường/xã, VAT) | | | | | | ✓ (Checkout) | | |
| `order.js` (copy STK / số tiền / nội dung `DRV{id}`) | | | | | | ✓ (Nhận đơn) | | |
| `quote.css`, `chunks/quote.js` (dự toán BOQ theo phòng, in/CSV) | lazy* | lazy* | lazy* | lazy* | lazy* | | ✓ | lazy* |
| `blog.css`, `blog.js` (`<drv-toc>`, tiến độ đọc) | | | | ✓ | | | | CSS (Policy) |
| `chunks/search.js` (`import()` động khi focus ô tìm kiếm) | lazy | lazy | lazy | lazy | lazy | ✗ | lazy | lazy |
| `chunks/motion.js` + `animejs` (`import()` động khi có phần tử) | lazy | lazy | lazy | ✗ | ✗ | ✗ | ✗ | ✗ |
| `photoswipe` (`import()` động khi click ảnh) | ✗ | ✗ | lazy | lazy | ✗ | ✗ | ✗ | ✗ |
| `chunks/ai-finder.js` (Phase 3, chỉ khi `features.ai_enabled=true`) | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ (Trang AI P3) |
| `wp-block-library` | ✗ | ✗ | ✗ | on-demand | ✗ | ✗ | ✗ | on-demand |
| WC `woocommerce-layout/-smallscreen/-general` & `wc-blocks-style` | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| WC scripts (`woocommerce`, `wc-checkout`, `sourcebuster`) | ✗ | ✗ | ✗ | ✗ | ✓ (cart) | ✓ | ✗ | ✗ |

*\*Ghi chú `quote.js`: nạp trực tiếp trên `/danh-sach-bao-gia`; ở các trang khác chỉ `import()` động khi người dùng mở ngăn `<drv-quote-panel>` (thao tác thêm nhanh `+ Báo giá` ghi vào `localStorage` được xử lý gọn ngay trong `core.js`).*

Thực hiện gỡ bỏ CSS/JS thừa trong `Woo\AssetsDequeue` (`woocommerce_enqueue_styles` → `[]`, `wp_dequeue_*` theo điều kiện). Kiểm tra lại sau mỗi lần nâng cấp WooCommerce.

## 4. Ảnh

| Size (`add_image_size`) | Kích thước | Dùng |
|---|---|---|
| `woocommerce_thumbnail` | 480×480 crop | Card sản phẩm (`srcset` 240/480) |
| `drv_card_sm` | 240×240 crop | Card mobile 2 cột, mini-cart, live search (`48×48` CSS) |
| `woocommerce_single` | 1000×1000 | Ảnh chính PDP (`srcset` 600/1000) |
| `drv_zoom` | 2000×2000 | PhotoSwipe zoom cận vân trên PDP & Blog |
| `woocommerce_gallery_thumbnail` | 160×160 | Ảnh phụ gallery |
| `drv_hero` / `drv_hero_m` | 1920×1080 / 828×1035 | Hero `<picture>` art direction (`D-25`) |
| `drv_post` | 1200×800 | Ảnh đại diện bài viết Cẩm nang, Open Graph |

- **Logo tối ưu bắt buộc trước Phase 1 (`D-12`):** Chuyển các file logo PNG gốc (~220KB) sang **WebP 1x (`150×44`) và 2x (`300×89`) dung lượng ~5KB** bằng `tools/optimize-logo.mjs` (chỉ giữ bản PNG nén nhỏ dự phòng cho email đơn hàng).
- **Quy tắc duy nhất 1 ảnh LCP mỗi trang:** Toàn bộ trang **chỉ được gắn `fetchpriority="high"` (và không `loading="lazy"`) cho đúng 1 phần tử `<img>` LCP duy nhất** (ảnh `<picture>` Hero trên Home, ảnh đầu tiên trên PDP, ảnh bài tiêu điểm trên Cẩm nang). Tuyệt đối không gắn `fetchpriority="high"` lên logo hay hàng loạt card sản phẩm.
- **Định dạng:** Tự sinh WebP qua `image_editor_output_format` (`image/jpeg` → `image/webp`) hoặc AVIF nếu Imagick trên AZDIGI hỗ trợ (chọn 1 cơ chế duy nhất, không bật chồng với Image Optimization của LSCache).
- Gỡ các image size thừa của WP/WC (`intermediate_image_sizes_advanced`); giới hạn ảnh upload gốc tối đa `2560px` cạnh dài, chất lượng `80`.
- Mọi thẻ `<img>` bắt buộc có `width`, `height`, `sizes` khớp layout, `decoding="async"`; toàn bộ ảnh ngoài màn đầu gắn `loading="lazy"`.
- **Nghiêm cấm tuyệt đối** hotlink ảnh từ dịch vụ ngoài (`images.unsplash.com`, `pexels.com`...) trong template và dữ liệu sản phẩm/bài viết.

## 5. Font & Icon (`D-28`)

- Tổng cộng chỉ tự host **3 file `.woff2` nội bộ** tại `assets/fonts/`:
  1. `quicksand-vf-vi.woff2` (~45KB): gộp sẵn `latin` + `vietnamese` trong 1 file duy nhất, gắn `<link rel="preload" as="font" type="font/woff2" crossorigin>`.
  2. `anton-vi.woff2` (~25KB): gộp sẵn `latin` + `vietnamese` trong 1 file duy nhất, `font-display: swap` kèm fallback metric (không preload).
  3. `phosphor-subset.woff2` (~15–20KB): ~140 icon thực dùng toàn dự án.
- Không tách nhiều file font con có `unicode-range` chồng chéo.
- **Cấm tuyệt đối** gọi Google Fonts CDN hoặc jsDelivr CDN (bao gồm cấm tải font `JetBrains Mono`; mọi mã SKU, kích thước `mm` và thông số bảng dùng system stack `--f-mono`).

## 6. Backend & Fragment Cache (`D-29`)

- Tắt: WP emoji, oEmbed discovery, `wp_generator`, RSD/WLW, shortlink, REST link header; Heartbeat giảm 60s trong admin, tắt hoàn toàn ngoài frontend.
- Chống truy vấn `N+1` trong vòng lặp sản phẩm: prime cache meta và term trước khi render lưới (`update_meta_cache`, `update_object_term_cache`).
- **Fragment Cache cho Mega Menu (`drv_mega_html` — `D-29`):** Lưu toàn bộ HTML đã render của Mega Menu 4+1 và Mobile Accordion vào transient/object cache `drv_mega_html`; tự động xóa và dựng lại khi lưu `product_cat`, khi hoàn tất đồng bộ sản phẩm (`drv/catalog/synced`) hoặc khi lưu `drv_settings`.
- **Search Transient Cache:** Endpoint `GET /wp-json/dravit/v1/search` lưu kết quả vào transient `drv_search_{md5_normalized_q}` trong 10 phút (xóa khi `drv/catalog/synced`).
- Facet count + hộp `<dl>` tóm tắt nhanh trên Collection dùng object cache theo khóa tham số lọc (`05` §5).
- Option `drv_settings` để `autoload = yes` (dữ liệu gọn); các bảng cấu hình tĩnh (`collections.php`, `facets.php`, `size-guide.php`, `provinces-vn.php`) đọc trực tiếp từ file PHP tận dụng OPcache.
- Cron: bật `define('DISABLE_WP_CRON', true)` + cài cron hệ thống trên AZDIGI mỗi 5 phút gọi `wp-cron.php` để chạy Action Scheduler (tự động hủy đơn quá hạn chờ chuyển khoản và đồng bộ khuyến mãi đúng giờ).
- HPOS bật 100%; dọn log Action Scheduler > 30 ngày và transient hết hạn định kỳ. Mục tiêu TTFB khi chưa có page cache < 800ms.

## 7. LiteSpeed Cache (AZDIGI — `D-29`, `D-30`)

| Tab | Thiết lập bắt buộc |
|---|---|
| Cache | Enable Cache: **ON**; Cache Logged-in Users: OFF; Cache Commenters: OFF; **Cache REST API: ON** (cho `/dravit/v1/catalog`, `/search`, `/products` theo header); Cache Mobile: OFF (responsive chung 1 HTML) |
| TTL | Public page: 1 tuần; Front page: 1 ngày; REST GET đọc danh mục: 5–10 phút theo `Cache-Control` |
| Purge & Khuyến mãi theo lịch | Tự purge khi cập nhật bài/sản phẩm + gắn tag `drv_catalog`. **Bắt buộc hook `wc_after_products_starting_sales` và `wc_after_products_ending_sales`** để chạy `SaleSync` cập nhật `/gach-uu-dai` và gọi LiteSpeed purge tag `drv_catalog` ngay thời điểm khuyến mãi bắt đầu/kết thúc |
| Excludes (URI & REST) | URI không cache động: `/gio-hang`, `/thanh-toan`, `/tai-khoan`. Với **`/danh-sach-bao-gia`**: loại trừ khỏi Page Cache **hoặc** chỉ cache khung vỏ HTML tĩnh rỗng và hydrate 100% danh sách hạng mục ở client từ `localStorage` (`drv_quote_v2`). **REST Exclude bắt buộc (`D-30`):** loại trừ `/wp-json/dravit/v1/nonce`, `/quote/submit`, `/lead` và luôn trả `Cache-Control: no-store, no-cache, must-revalidate` |
| Cookie & ESI | ESI: **OFF** (không cần ESI phức tạp vì mini-cart đọc qua Store API, badge BOQ đọc `localStorage`, còn form public lấy token tươi qua `GET /wp-json/dravit/v1/nonce`). Không vary cache toàn site theo cookie giỏ hàng |
| WooCommerce | Product Update Interval: Purge sản phẩm + danh mục liên quan khi tồn kho hoặc giá thay đổi |
| Object Cache | **ON** với **Redis** (hoặc LSMCD/Memcached trên gói AZDIGI); Persistent Connection: ON |
| Browser Cache | **ON**, TTL 1 năm cho tài nguyên tĩnh có hash (`assets/dist/*`, `assets/fonts/*`, uploads) |
| Page Optimization | **CSS/JS Minify & Combine: OFF**, UCSS/CCSS: OFF, JS Defer/Delay: OFF, Lazy Load Images: OFF (Vite & theme đã kiểm soát hoàn toàn), Load Google Fonts Async: OFF, Remove WP Emoji: ON |
| Crawler | **ON** theo sitemap để làm ấm cache 28 Smart Collections và các SKU chủ lực sau mỗi đợt purge |

**Tương thích Page Cache cho Giỏ hàng, BOQ & Form Lead:**
- HTML được cache không chứa dữ liệu phiên của khách: `core.js` cập nhật số lượng `[DS Báo giá (n)]` trực tiếp từ `localStorage` (`drv_quote_v2`), kiểm tra cookie `woocommerce_items_in_cart` để đồng bộ `[Giỏ (n)]` qua Store API `/wc/store/v1/cart`, và chỉ tải nội dung Mini-cart khi người dùng mở drawer.
- Mọi form public trên trang được cache lâu ngày (`/lien-he`, `/danh-sach-bao-gia`, form báo giá nhanh trên PDP/Blog) gọi `GET /wp-json/dravit/v1/nonce` (`no-store`) trước khi gửi nên không bao giờ gặp lỗi hết hạn nonce sau 12–24 giờ cache.

## 8. Kiểm tra hiệu suất

| Công cụ | Thời điểm chạy |
|---|---|
| PageSpeed Insights (Mobile + Desktop) | Cuối mỗi Phase trên bộ URL chuẩn |
| Lighthouse CI (local, giả lập Moto G4 / 4G chậm) | Mỗi khi thay đổi template, CSS `@layer` hoặc cấu hình Vite |
| WebPageTest (Singapore / Việt Nam, 4G) | Trước khi nghiệm thu Go-Live |
| CrUX / Google Search Console Core Web Vitals | Theo dõi định kỳ hàng tháng sau Go-Live |
| Script kiểm tra bundle (`vite build` report) | Đảm bảo không entry nào vượt ngưỡng KB gzip tại bảng §1 |

**Bộ URL kiểm thử bắt buộc:** `/`, `/gach`, `/gach-phong-khach?kich-thuoc=80x80`, `/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m`, `/danh-sach-bao-gia`, và 1 bài chi tiết trong `/cam-nang`.
