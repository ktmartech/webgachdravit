# 08. Hiệu suất & Hosting (AZDIGI LiteSpeed)

> Skill: `web-performance-optimization`, `wp-performance`, `fixing-motion-performance`.

## 1. Ngân sách hiệu suất

| Template | LCP element | CSS (gzip) | JS (gzip) | Ảnh trên màn đầu | Request màn đầu |
|---|---|---|---|---|---|
| Home | Ảnh hero | ≤ 14KB inline + ≤ 20KB | ≤ 12KB | ≤ 180KB (hero AVIF/WebP) | ≤ 20 |
| Collection | H1 hoặc ảnh card đầu | ≤ 14KB inline + ≤ 22KB | ≤ 15KB (core + catalog) | ≤ 150KB | ≤ 25 |
| URL lọc (SSR) | Như collection | | | | |
| Sản phẩm | Ảnh chính | ≤ 14KB inline + ≤ 22KB | ≤ 18KB (+ PhotoSwipe ~20KB khi click) | ≤ 160KB | ≤ 25 |
| Cẩm nang | Ảnh đại diện / H1 | ≤ 14KB inline + ≤ 15KB | ≤ 6KB | ≤ 150KB | ≤ 18 |
| Cart/Checkout | | ≤ 30KB | WC jQuery + ≤ 10KB | | |

**CWV mục tiêu (field, p75):** LCP < 2.0s · INP < 200ms · CLS < 0.05 · TTFB < 600ms (cache hit < 200ms). **PSI mobile ≥ 90** cả 5 template.

## 2. Chiến lược CSS/JS

- **Critical CSS** viết tay theo template (header, hero/H1, grid skeleton), inline ≤ 14KB; CSS còn lại nạp `rel=preload as=style` rồi chuyển `stylesheet`, có `<noscript>`.
- CSS chia theo template: `main.css` (chung) + `catalog.css` / `product.css` / `checkout.css` / `blog.css`.
- JS là ES module, `type="module"` (tự defer); không inline script lớn; chỉ cấu hình nhỏ qua `wp_add_inline_script`.
- Không jQuery trên home/collection/sản phẩm/blog. WC chỉ cần jQuery ở cart/checkout/account.
- Icon nạp qua **Phosphor Icons CDN** (`@phosphor-icons/web@2.1.2` trên `cdn.jsdelivr.net` kèm `<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>`).
- Animation áp dụng **Option A + B kết hợp (D-19)**: 100% Hover/UI chạy bằng Pure CSS GPU (`transform`, `opacity`) 0KB JS; nạp nhẹ **Anime.js v3.2.2** (`~6.5KB gzip`, `defer` qua `cdn.jsdelivr.net`) cho Staggered Grid Reveal (`[data-drv-stagger]`), Number Odometer trong `<drv-unit-calc>` và Spring Cart Bounce.
- Speculative loading của WP core giữ mặc định (prefetch khi hover, conservative).

## 3. Ma trận nạp asset

| Asset | Home | Collection | Sản phẩm | Blog | Cart/Checkout | Trang tĩnh |
|---|---|---|---|---|---|---|
| `main.css`, `core.js` (menu, search, mini-cart, tracking) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `catalog.css/js` (filter, load more) | | ✓ | | | | |
| `product.css/js` (gallery, calculator, swatch, add-to-cart) | | | ✓ | | | |
| `checkout.css`, `checkout-vn.js` (tỉnh → phường/xã) | | | | | ✓ | |
| `blog.css` (TOC, prose) | | | | ✓ | | |
| `wp-block-library` | ✗ (chỉ nạp block CSS on-demand nếu dùng Gutenberg) | ✗ | ✗ | on-demand | ✗ | on-demand |
| WC `woocommerce-layout/-smallscreen/-general` | ✗ | ✗ | ✗ | ✗ | ✗ (CSS theme thay thế) | ✗ |
| WC script `woocommerce`, `wc-add-to-cart`, `sourcebuster` (order attribution) | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ |
| `wc-blocks-style` | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

Thực hiện trong `Woo\AssetsDequeue` (`woocommerce_enqueue_styles` → `[]`, `wp_dequeue_*` theo điều kiện). Kiểm tra lại sau mỗi lần nâng cấp WC.

> Order Attribution của WC (`sourcebuster`) chỉ cần ở checkout; nếu vẫn cần dữ liệu nguồn, bật ở checkout, tắt nơi khác.

## 4. Ảnh

| Size (`add_image_size`) | Kích thước | Dùng |
|---|---|---|
| `woocommerce_thumbnail` | 480×480 crop | Card (srcset 240/480) |
| `drv_card_sm` | 240×240 crop | Card mobile 2 cột, mini-cart |
| `woocommerce_single` | 1000×1000 | Ảnh chính SP (srcset 600/1000) |
| `drv_zoom` | 2000×2000 | PhotoSwipe zoom vân |
| `woocommerce_gallery_thumbnail` | 160×160 | Thumb gallery |
| `drv_hero` | 1920×1080 / `drv_hero_m` 828×1035 | Hero `<picture>` art direction |
| `drv_post` | 1200×800 | Ảnh bài viết, OG |

- Định dạng: WebP mặc định qua `image_editor_output_format` (`image/jpeg` → `image/webp`); AVIF nếu Imagick trên AZDIGI hỗ trợ (kiểm tra `wp_image_editor_supports(['mime_type'=>'image/avif'])`). Hoặc dùng LiteSpeed Image Optimization (QUIC.cloud) để sinh WebP/AVIF, chọn **1** trong 2 cách.
- Gỡ các size thừa của WP/WC không dùng (`intermediate_image_sizes_advanced`).
- Mọi `<img>`: `width`, `height`, `sizes` đúng layout, `decoding="async"`; ảnh LCP `fetchpriority="high"` + không lazy; còn lại `loading="lazy"`.
- Upload gốc tối đa 2560px cạnh dài (`big_image_size_threshold`), chất lượng 80.
- Logo: bản WebP 150×44 / 300×89 (D-12), preload không cần (nhỏ, nằm trong HTML đầu).

## 5. Font

- 2 file: `anton-vi.woff2` (~25KB), `quicksand-vf-vi.woff2` (~45KB), subset Latin + Vietnamese bằng `tools/subset-fonts.mjs`.
- `<link rel="preload" as="font" type="font/woff2" crossorigin>` cho Quicksand. Anton không preload (chỉ H1/section title, swap với fallback metric).
- Không gọi Google Fonts CDN.

## 6. Backend

- Tắt: emoji, oEmbed discovery, `wp_generator`, RSD/WLW, shortlink, REST link header; Heartbeat chỉ 60s trong admin, tắt ở frontend.
- Không truy vấn trong vòng lặp card (N+1): lấy meta bằng `update_meta_cache`/WC lookup trước khi render grid; term của các card prime bằng `update_object_term_cache`.
- Facet count + "Tóm tắt nhanh" collection dùng object cache (`05` §5).
- Autoload: option `drv_settings` autoload `yes` (nhỏ); dữ liệu lớn (config) đọc từ file PHP (opcache).
- Cron: `define('DISABLE_WP_CRON', true)` + cron thật trên AZDIGI (cPanel/DirectAdmin) mỗi 5 phút gọi `wp-cron.php`. Action Scheduler chạy qua cron này.
- HPOS bật; dọn Action Scheduler log > 30 ngày (WC mặc định), dọn transient hết hạn.
- Đo: `wp profile` (khi có WP-CLI), Query Monitor ở local/staging; TTFB không cache < 800ms.

## 7. LiteSpeed Cache (AZDIGI)

| Tab | Thiết lập |
|---|---|
| Cache | Enable Cache: **ON**; Cache Logged-in Users: OFF; Cache Commenters: OFF; **Cache REST API: ON** (cho `dravit/v1/catalog`, `search`); Cache Login Page: ON; Cache Mobile: OFF (theme responsive, cùng HTML) |
| TTL | Public 1 tuần; Front page 1 ngày; REST 5 phút (theo header `Cache-Control` của endpoint) |
| Purge | Purge All On Upgrade: ON; tự purge khi publish (mặc định) + tag `drv_catalog` từ theme |
| Excludes | URI: `/gio-hang`, `/thanh-toan`, `/tai-khoan` (LSCache tự nhận diện WC). Query string: `add-to-cart`. Cookie: `woocommerce_items_in_cart`, `wp_woocommerce_session_` (chỉ khi cần; mini-cart đã render client-side nên **không** cần vary toàn site) |
| ESI | OFF (không cần, mini-cart client-side) |
| WooCommerce | Product Update Interval: "Purge product + categories khi tồn kho thay đổi"; Vary Group: mặc định |
| Object Cache | ON với **Redis** (hoặc Memcached/LSMCD) nếu gói AZDIGI hỗ trợ; Persistent Connection ON |
| Browser Cache | ON, TTL 1 năm cho asset có hash (`assets/dist/*`, fonts, uploads) |
| Page Optimization | **CSS/JS Minify/Combine: OFF**, UCSS/CCSS: OFF, JS Defer/Delay: OFF, Lazy Load Images: OFF (theme đã xử lý), HTML Minify: ON (tùy chọn), Remove Query Strings: OFF, Load Google Fonts Async: OFF, Remove WP Emoji: ON |
| Image Optimization | Chỉ bật nếu chọn LSCache tạo WebP/AVIF (§4) |
| Crawler | ON cho sitemap (làm ấm cache 28 collection + sản phẩm) sau mỗi lần purge, giới hạn tải nhẹ |
| CDN | QUIC.cloud CDN (tùy chọn, cân nhắc sau khi đo) |

**Mini-cart với page cache:** HTML cache không chứa số lượng giỏ. `core.js` đọc cookie `woocommerce_items_in_cart`; nếu có → gọi `GET /wp-json/wc/store/v1/cart` để hiển thị số lượng; drawer mở mới tải chi tiết. Thêm vào giỏ cập nhật trực tiếp từ response.

## 8. Kiểm tra hiệu suất

| Công cụ | Khi nào |
|---|---|
| PageSpeed Insights (mobile + desktop) | Mỗi phase, 5 URL mẫu |
| Lighthouse CI (local, throttling mobile) | Mỗi PR thay đổi template/asset |
| WebPageTest (Việt Nam / Singapore, 4G) | Trước go-live |
| CrUX / GSC Core Web Vitals | Sau go-live, hàng tháng |
| `perf.mjs` của skill `design-taste-frontend` | Kiểm tra trọng lượng ảnh, CLS trên bản HTML tĩnh |

**URL mẫu:** `/`, `/gach`, `/gach-phong-khach?kich-thuoc=80x80`, `/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m`, 1 bài cẩm nang.

