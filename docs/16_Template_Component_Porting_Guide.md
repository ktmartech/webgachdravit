# 16. Bản đồ Port Giao diện Mẫu sang WordPress & Thư viện Component drv_ui()

> **Nguồn đối chiếu:** Bộ 12 file HTML mẫu tại `docs/layout/` (`00`..`10` + `partials/` + `assets/{css,js}`) và báo cáo kiểm toán [00_LAYOUT_AUDIT_REPORT.md](layout/00_LAYOUT_AUDIT_REPORT.md).
> **Quyết định kiến trúc ràng buộc:** `D-01` (Classic PHP Theme), `D-04` (Vanilla CSS `@layer` + Vite ES2022), `D-15` (Classic Shortcode Cart/Checkout), `D-17` (Component 4 lớp `drv_ui()`), `D-18`..`D-34` (nâng cấp `D-31` Fluent Forms Bridge + `@layer plugins`, `D-32` mở sẵn `Dravit\Advisor\*` + `RuleOnlyDriver`, `D-34` cắt Bottom-Up 4 tầng & `drv_section()` `SectionRegistry` thuần code `S-3`).
> **Tài liệu liên quan:** [01_Brand_Design_System.md](01_Brand_Design_System.md) · [02_Technical_Architecture.md](02_Technical_Architecture.md) · [07_UI_UX_Page_Specs.md](07_UI_UX_Page_Specs.md) · [08_Performance_Hosting.md](08_Performance_Hosting.md) · [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md) · [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md) · [21_Theme_Build_Execution_Roadmap.md](21_Theme_Build_Execution_Roadmap.md).

---

## 1. Ma trận Ánh xạ 12 File HTML Mẫu & Các Template WordPress Bắt buộc

Mọi đường dẫn public trên production **tuyệt đối không có dấu `/` cuối** (`D-21`). Biến thể có `/` cuối bắt buộc redirect `301` về URL sạch.

### 1.1 Bảng ánh xạ tổng thể (Prototype → WordPress / WooCommerce)

| Nguồn Prototype (`docs/layout/`) | File Template PHP trong `wp-content/themes/dravit/` | Quy tắc URL & Robots (`D-21`, `06 §1`) | Vite CSS / JS Entries nạp (`Core\Assets`) | Nguồn dữ liệu SSR (Không dùng Mock JS/JSON) |
|---|---|---|---|---|
| `00-ui-kit.html` | `page-templates/template-ui-kit.php` *(chỉ kích hoạt khi `wp_get_environment_type() !== 'production'`; trả `404` trên production)* | `/ui-kit` · `noindex, nofollow` | `css/main.css`, `css/pages/ui-kit.css` · `js/core.js`, `js/product.js` | Token thực từ `tokens.css`, 4 sản phẩm seed lấy qua `wc_get_products(['limit' => 4])` |
| `01-home.html` | `front-page.php` + `template-parts/home/{hero,trust-strip,space-bento,explorer,size-guide,deals,showroom,guides}.php` | `/` · `index, follow` | Inline `critical-home.css` + `css/main.css`, `css/pages/home.css` · `js/core.js`, `js/home.js` | `drv_settings.home`, `drv_settings.showrooms`, `config/collections.php`, `config/size-guide.php`, `wc_get_products()`, `WP_Query` bài Cẩm nang |
| `01.1-ai-finder.html` | `page-templates/template-ai-finder.php` + `template-parts/ai/{workspace,context-strip,results}.php` *(Phase 3; Phase 1 ẩn lối vào khi `!drv_settings('features.ai_enabled')`)* | `/tro-ly-ai-tim-gach` *(khóa trong `SlugGuard`)* · Phase 1: `noindex, follow` | Phase 1: `css/main.css` · `js/core.js`. Phase 3: thêm `css/pages/ai-finder.css` · `js/ai-finder.js` | Phase 1: thông báo tính năng đang nâng cấp + điều hướng về `/gach` & `/danh-sach-bao-gia`. Phase 3: REST `/dravit/v1/advisor` (`13`) |
| `02-catalog-collection.html` | `woocommerce/archive-product.php` + `template-parts/catalog/{hero,silo-tiles,toolbar,filters,active-chips,grid,load-more,geo-block,seo-footer,faq,empty}.php` | `/gach`, 27 URL `/{collection-slug}`, `/bo-suu-tap/{slug}`, `/xuat-xu/{slug}`, `/page/{n}` (`index, follow`); có `?param` → `noindex, follow` (nhóm `seo`) hoặc `noindex, nofollow` (nhóm `tech`); `/san-pham` redirect `301` → `/gach` | Inline `critical-catalog.css` + `css/main.css`, `css/pages/catalog.css` · `js/core.js`, `js/catalog.js` | `WP_Query` chính thông qua `Filters\QueryBuilder`, `Filters\FacetCounter` (cache transient), term meta `drv_h1`, `drv_intro`, `drv_geo_table`, `drv_seo_footer`, `drv_faq` |
| `03-product-single.html` | `woocommerce/single-product.php`, `woocommerce/content-single-product.php` + `template-parts/product/{gallery,buy-box,series-swatches,spec-panel,faces-grid,notes,similar,viewed,sticky-bar}.php` | `/san-pham/{slug}` (`D-14`) · `index, follow` | Inline `critical-product.css` + `css/main.css`, `css/pages/product.css` · `js/core.js`, `js/product.js` (+ lazy `lightbox.js` khi bấm ảnh) | Đối tượng `WC_Product` (WC CRUD), `Units\PricingModel\AreaPack`, nhóm anh em `drv_series`, `PrimaryCategory` (`IL-04`), thông số tự gắn link `IL-03` |
| `04-cart.html` | `page.php` (`[woocommerce_cart]`, `D-15`) → `woocommerce/cart/{cart,cart-empty,cart-totals}.php` + `template-parts/cart/{ledger-row,vehicle-hint,commitments}.php` | `/gio-hang` · `noindex, follow` *(Exclude LSCache)* | `css/main.css`, `css/pages/cart.css` (`@layer woo`) · `js/core.js`, `js/cart.js` (+ WC `jquery` riêng trang giỏ) | `WC()->cart`, `Units\Calc` tính tổng `m²`, số thùng, tổng `kg`, cảnh báo lẻ viên, gợi ý tải trọng xe từ `drv_settings.order.vehicle_thresholds` |
| `04.1-quote-cart.html` | `page-templates/template-quote.php` + `template-parts/quote/{meta-strip,workspace,totals,lead-dialog,picker-dialog,print-a4}.php` | `/danh-sach-bao-gia` *(khóa trong `SlugGuard`)* · `noindex, follow` | `css/main.css`, `css/pages/quote.css` · `js/core.js`, `js/quote.js` | Khung HTML SSR + cấu hình bậc chiết khấu từ `drv_settings.quote.volume_tiers`; hydrate SKU từ `localStorage['drv_quote_v2']` qua `GET /dravit/v1/products?include=...` (`17`) |
| `05-checkout.html` | `page.php` (`[woocommerce_checkout]`, `D-15`) → `woocommerce/checkout/{form-checkout,review-order,payment}.php` + `template-parts/checkout/{steps,mobile-summary,vat-fields,sticky-cta}.php` | `/thanh-toan` · `noindex, follow` *(Exclude LSCache)* | `css/main.css`, `css/pages/checkout.css` (`@layer woo`) · `js/core.js`, `js/checkout-vn.js` (+ WC `wc-checkout`) | `WC()->checkout()`, `Woo\CheckoutVN` (34 tỉnh `config/provinces-vn.php` + lazy JSON phường/xã `assets/data/wards/{code}.json`), `QuoteShippingMethod`, trường VAT `_drv_vat_*` |
| `06-order-received.html` | `woocommerce/checkout/thankyou.php` + `woocommerce/myaccount/view-order.php` + `template-parts/order/{timeline,status-banner,vietqr-box,items-ledger}.php` | `/thanh-toan/dat-hang-thanh-cong/{order_id}?key=wc_order_...` · `noindex, nofollow` *(Exclude LSCache)* | `css/main.css`, `css/pages/checkout.css` (`@layer woo`) · `js/core.js`, `js/order.js` | `wc_get_order($order_id)` (HPOS CRUD), snapshot meta `_drv_*` trên line items, `Woo\BankQR` (chỉ sinh ảnh `img.vietqr.io` khi `bacs` + `on-hold`, `D-22`) |
| `07-blog-archive.html` | `home.php` + `template-parts/blog/{subnav,bento-hero,topic-cluster,tools-strip,card,card-compact}.php` | `/cam-nang` (Posts page) · `index, follow` | Inline `critical-blog.css` + `css/main.css`, `css/pages/blog.css` · `js/core.js`, `js/blog.js` *(tổng JS ≤ 10KB gzip, `D-28`)* | Bài Pillar (`drv_is_pillar = 1`), `drv_settings.blog.popular_ids`, các chuyên mục `category` con, bảng chỉ số `R` từ config kỹ thuật |
| `08-blog-single.html` | `single.php` + `template-parts/blog/{header,key-takeaways,toc,author-box,reviewer-badge,related,sticky-sidebar}.php` | `/cam-nang/{postname}` · `index, follow` | Inline `critical-blog.css` + `css/main.css`, `css/pages/blog.css` (+ block CSS on-demand) · `js/core.js`, `js/blog.js` (+ lazy `lightbox.js`) | `WP_Post`, post meta `drv_key_takeaways`, `drv_reading_time`, `drv_reviewer_id`, `Content\Toc`, shortcodes `[drv_products]`, `[drv_shop_the_look]`, `[drv_lead_form]` (`20`) |
| `09-contact.html` | `page-templates/template-contact.php` + `template-parts/common/lead-form.php` | `/lien-he` *(redirect `301` từ `/showroom`, `D-29`)* · `index, follow` | `css/main.css`, `css/pages/contact-404.css` · `js/core.js` | `drv_settings.company`, `drv_settings.contact`, `drv_settings.showrooms[]` (địa chỉ 2 cấp mới), Schema `ContactPage` + `HomeAndConstructionBusiness`, REST `POST /dravit/v1/lead` |
| `10-404.html` | `404.php` + `searchform.php` | Mọi URL `404` · HTTP `404`, `noindex, follow` | `css/main.css`, `css/pages/contact-404.css` · `js/core.js` | `get_search_form()` (tìm theo mã SKU/kích thước) + 4 thẻ Silo lấy từ `drv_settings.404_collections` (`/gach-lat-nen`, `/gach-phong-khach`, `/gach-van-da`, `/gach-80x80`) |
| *(Bổ sung WP bắt buộc)* | `search.php` | `/?s={q}&post_type=product` · `noindex, follow` | `css/main.css`, `css/pages/catalog.css` · `js/core.js` | `Catalog\SearchController`: chuẩn hóa không dấu + khớp SKU chính xác (`_sku`), render lưới bằng `drv_ui('product-card')` + khối bài viết Cẩm nang khớp từ khóa; nếu `0` kết quả gọi `template-parts/catalog/empty.php` |
| *(Bổ sung WP bắt buộc)* | `category.php` *(và `archive.php` fallback)* | `/chu-de/{slug}`, `/chu-de/{slug}/page/{n}` · `index, follow` *(rỗng: `noindex, follow`)* | Inline `critical-blog.css` + `css/main.css`, `css/pages/blog.css` · `js/core.js`, `js/blog.js` | Tái sử dụng `template-parts/blog/subnav.php` (active đúng term hiện tại), mô tả chuyên mục, lưới `template-parts/blog/card.php` 3 cột + `drv_pagination()` |
| *(Bổ sung WP bắt buộc)* | `page.php` *(áp dụng cho `/gioi-thieu`, `/tai-khoan` và trang nội dung chuẩn)* | `/gioi-thieu` (`index, follow`), `/tai-khoan` (`noindex, nofollow`) | `css/main.css` (+ block CSS on-demand; `/tai-khoan` nạp thêm `css/pages/checkout.css`) · `js/core.js` | Trang `/gioi-thieu` dựng bằng Gutenberg + Block Patterns (`patterns/about-hero.php`, `patterns/about-metrics.php`, `patterns/about-showroom.php`) theo copy `10 §4.5` (`D-01`) |
| *(Bổ sung WP bắt buộc)* | `page-templates/template-policy.php` | `/chinh-sach-van-chuyen`, `/chinh-sach-doi-tra`, `/chinh-sach-thanh-toan`, `/chinh-sach-bao-mat`, `/dieu-khoan-su-dung` · `index, follow` | `css/main.css`, `css/pages/blog.css` · `js/core.js`, `js/blog.js` | Layout 2 cột `3/9` (`07 §8`): cột trái `wp_nav_menu(['theme_location' => 'policy_nav'])` + `<drv-toc>`, cột phải nội dung Gutenberg `max-width: 70ch` + meta ngày hiệu lực `drv_effective_date` |

### 1.2 Quy tắc Override WooCommerce (`wp-content/themes/dravit/woocommerce/`)

Chỉ tạo đúng **10 file override** sau (mọi thay đổi nhỏ khác bắt buộc đi qua hook trong `Dravit\Woo\Hooks`):
1. `archive-product.php`
2. `content-product.php` (chỉ gọi `drv_ui('product-card', ['product' => $product])`)
3. `single-product.php` & `content-single-product.php`
4. `global/quantity-input.php` (gọi `drv_ui('qty-input', ...)`)
5. `cart/cart.php` & `cart/cart-empty.php` *(không override `mini-cart.php` của WC fragments vì Mini-cart Drawer dùng Store API)*
6. `checkout/form-checkout.php`, `checkout/review-order.php` *(bắt buộc tách riêng vì `wc-checkout` AJAX re-render khối này khi đổi tỉnh/phương thức thanh toán)*, `checkout/thankyou.php`
7. `myaccount/view-order.php` *(dùng chung partial timeline + VietQR với `thankyou.php`)*
8. `emails/email-order-items.php` & `emails/customer-confirmed-order.php` (custom email xác nhận phí vận chuyển + VietQR, xem `18`).

Mọi file trong `woocommerce/` phải giữ nguyên khối comment `@version` chuẩn của WooCommerce 11.2.x để cảnh báo tự động trong **WooCommerce → Status → Templates**.

---

## 2. Ánh xạ Global Shell (`partials/header.html` & `partials/footer.html`)

Trong prototype, mỗi trang HTML dùng thẻ giả lập `<drv-site-header data-partial="partials/header.html">` và `<drv-site-footer data-partial="partials/footer.html">` nạp qua `assets/js/shell-partials.js` (90KB chuỗi HTML inline) và `DrvSiteHeader`/`DrvSiteFooter` trong `core.js` (L1783–L1833).

**Quy tắc Port bắt buộc:**
- **XÓA SỔ 100%** file `shell-partials.js`, xóa 2 class `DrvSiteHeader` / `DrvSiteFooter` và hàm `renderShell()` / `syncActiveProtoLink()` khỏi `core.js`.
- Chuyển toàn bộ sang cơ chế SSR gốc của WordPress thông qua `get_header()` (`header.php`) và `get_footer()` (`footer.php`). Riêng trang `/thanh-toan` gọi `get_header('minimal')` (hoặc `get_header(null, ['variant' => 'minimal'])`) để render topbar tối giản chống xao nhãng (`D-30`).

### 2.1 Bảng phân rã từng khối trong `partials/header.html` & `partials/footer.html`

| Khối trong Prototype | Dòng nguồn | File PHP đích (`template-parts/...`) | Thay đổi bắt buộc khi Port sang WordPress (`D-18`, `D-29`, `D-30`) |
|---|---|---|---|
| **1. Prototype Navigator Bar** (`.drv-proto-bar`) | `header.html` L1–19 | *(Không tạo file - xóa bỏ)* | Xóa hoàn toàn cả HTML, CSS (`main.css` L454–515) và JS (`syncActiveProtoLink`). |
| **2A. Header Tier 1** (`.drv-header__top`: Logo, Search, Actions) | `header.html` L22–94 | `header.php` + `template-parts/header/tier-1.php` + `template-parts/header/search-box.php` | • Logo dùng `<picture>` WebP `150×44` (`@1x`/`@2x`) + PNG fallback (`D-12`), không đặt `fetchpriority="high"` cạnh tranh với ảnh Hero LCP.<br>• Ô tìm kiếm trỏ `action="<?php echo esc_url(home_url('/')); ?>"`, thêm `<input type="hidden" name="post_type" value="product">`; từ khóa gợi ý đọc từ `drv_settings.search.popular_keywords`; dropdown gọi `GET /wp-json/dravit/v1/search?q=...`.<br>• Cụm nút phải hiển thị **KÉP** (`D-29`): (1) Link `/danh-sach-bao-gia` kèm badge `[data-quote-badge-count]` đọc từ `localStorage['drv_quote_v2']`, và (2) Button mở `#drv-minicart-drawer` kèm badge `[data-drv-cart-count]` đọc cookie `woocommerce_items_in_cart` / Store API.<br>• Nút `Trợ lý AI` bọc trong `if (drv_feature('ai_enabled'))` (mặc định `false` ở Phase 1, `D-25`). |
| **2B. Header Tier 2 & Mega Menu "Silo Matrix 4+1"** | `header.html` L96–683 | `template-parts/header/tier-2.php` + `template-parts/header/mega-menu.php` | • Sinh tự động từ `Dravit\Core\Menu\CollectionMenuBuilder` kết hợp `config/collections.php` + term meta (`drv_menu_badge`, `drv_menu_desc`, `drv_thumb_id`, số đếm thật `$term->count`, giá thấp nhất `price_min_m2`).<br>• **CẤM** chứa link có `?param` trong menu (`?nhom=`, `?loai=`, `?van=`, `?be-mat=` đều bị cấm); 100% link trong Mega Menu và Dropdown Cấp 2-3 phải trỏ về 28 URL tĩnh Collection (`/{slug}`), `/bo-suu-tap/*` hoặc trang tĩnh.<br>• Lưu HTML của Mega Menu + Mobile Accordion vào transient `drv_mega_html` (purge khi chạy hook `drv/catalog/synced` hoặc lưu menu). Menu phụ bên phải Tier 2 dùng `wp_nav_menu(['theme_location' => 'header_secondary'])`. |
| **3. Modal Nhận Báo Giá Công Trình** (`#drv-quote-modal`) | `footer.html` L1–52 | `template-parts/footer/quote-modal.php` | • Loại bỏ các giá trị điền sẵn giả (`value="85"`, `value="DRV-8801M..."`). Khi mở từ PDP, JS truyền `data-product-id`, `data-drv-sku` và `m²` hiện tại của `<drv-unit-calc>` vào form.<br>• Bổ sung trường ẩn `lead_type="quote_modal"`, honeypot `drv_website`, time-trap `_drv_form_ts`, checkbox `consent` (Nghị định 13/2023) và gửi về `POST /wp-json/dravit/v1/lead` kèm fresh nonce (`17 §4`). |
| **4. Mini-Cart Drawer** (`#drv-minicart-drawer`) | `footer.html` L54–162 | `template-parts/footer/minicart-drawer.php` | • Xóa 2 dòng sản phẩm cứng (`DRV-8801M` và `DRV-RON01` keo chà ron).<br>• SSR khung `<dialog>` nhẹ (0 query giỏ hàng để giữ page cache LSCache). Khi mở drawer hoặc sau `add-to-cart`, render dòng sản phẩm từ JSON response của WC Store API `/wp-json/wc/store/v1/cart` (mở rộng qua `Woo\StoreApiExtend` namespace `drv`).<br>• Mọi dòng gạch trong drawer **luôn hiển thị rõ** giá `đ/m²`, `≈ đ/viên` và huy hiệu quy đổi `= X thùng · Y m² · Z kg` (sửa lỗi ẩn trên prototype). Không nạp ở trang `/gio-hang` và `/thanh-toan`. |
| **5. Mobile Menu Drawer** (`#drv-mobile-menu-drawer`) | `footer.html` L164–542 | `template-parts/footer/mobile-menu-drawer.php` | • Render cấu trúc Accordion 3 cấp `<details class="drv-mob-accordion">` từ cùng dữ liệu `CollectionMenuBuilder` với Mega Menu (không viết tay 2 nơi lệch số lượng mẫu).<br>• Mọi link đều là URL tĩnh sạch; touch target `summary` ≥ `44px`. |
| **6. Showroom & Hotline Dock** (`.drv-footer-dock`) | `footer.html` L544–627 | `template-parts/footer/dock.php` | • 3 ô (Hotline kỹ thuật, Showroom chính, Phòng dự án B2B) đọc hoàn toàn từ `drv_settings.contact` và `drv_settings.showrooms[0]`.<br>• Trạng thái mở cửa tính bằng PHP theo múi giờ `wp_timezone()` (`Asia/Ho_Chi_Minh`) hoặc cập nhật nhẹ theo cấu trúc giờ mở cửa, địa chỉ theo chuẩn hành chính 2 cấp mới (`O-09`). Ẩn trên `/thanh-toan`. |
| **7. Global Dark Footer** (`footer.drv-footer`) | `footer.html` L629–818 | `footer.php` + `template-parts/footer/{columns,bottom-bar}.php` | • Logo âm bản `logo-ivory@1x.webp` / `@2x.webp` (`140×40`).<br>• Cột 1 lấy tên pháp nhân, MST, hotline, email, địa chỉ showroom, link MXH và link Bộ Công Thương (chỉ render badge BCT khi `drv_settings.company.bct_url` khác rỗng, `O-14`).<br>• Cột 2, 3, 4 render qua `wp_nav_menu()` (`footer_guide`, `footer_policy`, `footer_company`) trỏ về các trang thật (`/chinh-sach-van-chuyen`, `/gioi-thieu`, `/lien-he`...), xóa sạch các link `#anchor` giả tới `10-policies-search-404.html`. |
| **8. Mobile Sticky Bottom Bar** (`.drv-mobile-bar`) | `footer.html` L820–842 | `template-parts/footer/mobile-action-bar.php` | • Hiển thị 5 ô ngăn bằng đường ron `1px` (`Danh mục`, `Gọi điện`, `Chat Zalo`, `Báo giá`, `Giỏ`) ở `< 1024px`.<br>• Tự động **không render** trên `is_product()` (nhường chỗ cho `template-parts/product/sticky-bar.php`) và trên `is_checkout()` (nhường chỗ cho sticky CTA đặt hàng). |
| **9. Offcanvas Quote Panel** (`<drv-quote-panel>`) | *(Tiêm bởi `ai-finder.js`)* | `template-parts/quote/quote-panel-drawer.php` | • Tách khỏi `ai-finder.js` thành `<dialog id="drv-quote-panel" class="drv-dialog drv-dialog--drawer-right">` độc lập ở Phase 1, nạp JS chunk `quote-panel.js` qua `import()` khi khách bấm mở hoặc thêm mẫu báo giá. |

---

## 3. Thư viện Component Dùng chung `drv_ui()` (`D-17`)

### 3.1 Hợp đồng Hàm Render PHP (`inc/Core/Template.php`)

Toàn bộ giao diện tuân thủ nguyên tắc **1 Component = 1 File Template duy nhất** đặt tại `template-parts/ui/{name}.php`. Nghiêm cấm viết lặp HTML của cùng một thành phần ở template khác, trong shortcode hay trong REST Controller.

```php
<?php
declare(strict_types=1);

namespace Dravit\Core;

defined('ABSPATH') || exit;

final class Template {
    /**
     * Xuất trực tiếp component UI chuẩn hóa.
     *
     * @param string               $name Tên file trong template-parts/ui/{name}.php.
     * @param array<string, mixed> $args Tham số truyền vào component (đọc qua biến $args trong view).
     */
    public static function render_ui(string $name, array $args = []): void {
        $slug = sanitize_file_name($name);
        get_template_part('template-parts/ui/' . $slug, null, $args);
    }

    /**
     * Trả về chuỗi HTML của component (dùng cho REST /catalog, shortcode).
     *
     * @param string               $name Tên component.
     * @param array<string, mixed> $args Tham số component.
     */
    public static function get_ui_html(string $name, array $args = []): string {
        ob_start();
        self::render_ui($name, $args);
        return (string) ob_get_clean();
    }
}

// Global template tags khai báo trong inc/Core/Helpers.php:
function drv_ui(string $name, array $args = []): void {
    \Dravit\Core\Template::render_ui($name, $args);
}

function drv_ui_html(string $name, array $args = []): string {
    return \Dravit\Core\Template::get_ui_html($name, $args);
}
```

### 3.2 Bảng Đặc tả 22 Component `template-parts/ui/{name}.php`

| Component (`$name`) | Tham số `$args` (`key: type = default`) | Lớp CSS & Biến cục bộ `--_*` (`@layer components`) | Hook JS (`<drv-*>` / `data-drv-action`) |
|---|---|---|---|
| `btn` | `label: string`, `href: ?string = null` *(có `href` sinh `<a>`, không có sinh `<button>`)*, `type: 'button'\|'submit' = 'button'`, `variant: 'primary'\|'moss'\|'outline'\|'soft'\|'ghost'\|'link' = 'primary'`, `size: 'xs'\|'sm'\|'md'\|'lg' = 'md'`, `icon_left: ?string = null`, `icon_right: ?string = null`, `icon_only: bool = false`, `full: bool = false`, `disabled: bool = false`, `action: ?string = null`, `target: ?string = null`, `attrs: array<string,scalar> = []`, `class: string = ''` | `.drv-btn`, `.drv-btn--{moss\|outline\|soft\|ghost}`, `.drv-btn--{xs\|sm\|lg}`, `.drv-btn--icon`, `.drv-btn--full` · Scoped vars: `--_bg`, `--_fg`, `--_bd`, `--_bg-h`, `--_fg-h`, `--_h`, `--_px`, `--_fs`. Nếu `icon_only === true` bắt buộc có `$args['attrs']['aria-label']`. | Tùy chọn gắn `data-drv-action="{action}"` và `data-drv-target="{target}"` |
| `icon` | `name: string` *(VD: `'shopping-bag'`, `'arrow-right'`)*, `weight: 'regular'\|'bold'\|'fill' = 'regular'`, `size: 'xs'\|'sm'\|'md'\|'lg' = 'md'`, `class: string = ''`, `label: ?string = null` *(mặc định `aria-hidden="true"`, nếu truyền `label` thì gắn `role="img" aria-label="..."`)* | `i.ph` / `i.ph-bold` / `i.ph-fill` + `ph-{name}` + `.drv-icon`, `.drv-icon--{xs\|sm\|lg}` (từ `phosphor-subset.woff2`) | Không có JS |
| `badge` | `text: string`, `variant: 'default'\|'sale'\|'showroom'\|'moss'\|'champagne'\|'outline'\|'success'\|'warning' = 'default'`, `icon: ?string = null`, `class: string = ''` | `.drv-badge`, `.drv-badge--{variant}` · Scoped vars: `--_bg`, `--_fg`, `--_bd` (`--r-sm: 4px`) | Không có JS |
| `chip` | `label: string`, `href: ?string = null` *(nhóm `seo` ở URL sạch sinh `<a>`, còn lại sinh `<button type="button">`)*, `count: ?int = null`, `active: bool = false`, `disabled: bool = false`, `square: bool = false`, `icon: ?string = null`, `action: ?string = null`, `attrs: array = []` | `.drv-chip`, `.drv-chip--square`, `.is-active` · Scoped vars: `--_bg`, `--_fg`, `--_bd`, `--r-pill` (hoặc `--r-sm` khi `square`) | `aria-pressed="true\|false"` (khi là `<button>`), `data-drv-action` hoặc bắt trong `<drv-filter-form>` |
| `field` | `id: string`, `name: string`, `label: string`, `type: 'text'\|'tel'\|'email'\|'number'\|'search'\|'select'\|'textarea'\|'checkbox' = 'text'`, `value: mixed = ''`, `required: bool = false`, `hint: ?string = null`, `error: ?string = null`, `placeholder: string = ''`, `options: array<string,string> = []` *(cho `select`)*, `autocomplete: ?string = null`, `inputmode: ?string = null`, `attrs: array = []` | `.drv-field`, `.drv-label`, `.drv-label__req`, `.drv-label__hint`, `.drv-input` / `.drv-select` / `.drv-textarea` / `.drv-check`, `.drv-field__error` (`role="alert"`) | Hỗ trợ `data-drv-province` / `data-drv-ward` và module validation `utils/form-validate.js` |
| `product-card` | `product: \WC_Product` *(bắt buộc)*, `context: 'catalog'\|'home'\|'related'\|'embed'\|'search' = 'catalog'`, `priority_load: bool = false` *(chỉ `true` cho card đầu trên mobile nếu là LCP)*, `show_stock_detail: bool = false` *(bật cho mục Gạch ưu đãi)*, `heading_tag: 'h2'\|'h3' = 'h3'` | `.drv-card`, `.drv-card--priced` / `.drv-card--quote` / `.drv-card--out`, `.drv-card__media`, `.drv-card__img--primary`, `.drv-card__img--hover`, `.drv-card__tags`, `.drv-card__title`, `.drv-card__footer` | `data-drv-action="add-to-cart"` (kèm `data-product-id`, `data-drv-qty-pieces`) hoặc `data-drv-action="quote-add"` (khi `quote_only`). Xem chi tiết §3.3 |
| `price` | `product: \WC_Product`, `context: 'card'\|'single'\|'cart'\|'mini'\|'boq'\|'email' = 'card'`, `qty_pieces: int = 1` | `.drv-price`, `.drv-price--{context}`, `.drv-price--sale`, `.drv-price--quote`, `u-tabular` | Không có JS; bọc logic `Dravit\Units\PriceHtml` (`drv_price_html()`) |
| `unit-calc` | `product: \WC_Product`, `default_area_m2: ?float = null`, `waste_default: float = 0.05`, `waste_options: int[] = [0, 5, 10]` | `.drv-calc`, `.drv-calc__tabs`, `.drv-calc__inputs`, `.drv-calc__options`, `.drv-calc__summary`, `.drv-calc__total-row` | Light DOM `<drv-unit-calc>` (`components/unit-calc.js`), phát sự kiện `drv:calc-change` cập nhật form Add-to-cart & Sticky Bar |
| `qty-input` | `product: \WC_Product`, `value_pieces: int`, `input_name: string = 'quantity'`, `cart_item_key: ?string = null`, `compact: bool = false` | `.drv-stepper`, `.drv-stepper__btn`, `.drv-stepper__input` (`tabular-nums`) + nút phụ `+1 thùng ({n}v)` khi `drv_round_mode === 'piece'` | Light DOM `<drv-qty-input>` (`components/qty-input.js`), tôn trọng `drv_buy_units`, `drv_qty_min`, `drv_qty_step` (`D-24`) |
| `sub-silo-tile` | `term: \WP_Term\|array`, `url: string` *(ưu tiên URL tĩnh collection, `D-29`)*, `name: string`, `count: int`, `thumb_id: int = 0`, `active: bool = false` | `a.drv-silo-tile`, `.drv-silo-tile__thumb` (`48×48px`), `.drv-silo-tile__name`, `.drv-silo-tile__count` | Không có JS (thẻ `<a href>` tĩnh phục vụ `IL-01`/`IL-02`) |
| `section-head` | `title: string`, `eyebrow: ?string = null`, `desc: ?string = null`, `heading_tag: 'h1'\|'h2'\|'h3' = 'h2'`, `action_label: ?string = null`, `action_url: ?string = null`, `badge_html: string = ''` | `.drv-sec-head`, `.drv-sec-head__eyebrow`, `.drv-sec-head__desc`, `.drv-link` | Không có JS |
| `spec-table` | `product: \WC_Product`, `limit: int = 0` *(`0` = toàn bộ 14+ dòng, `8` = bản tóm tắt cột phải PDP)*, `enable_silo_links: bool = true` (`IL-03`), `collapsible_rest: bool = false` | `table.drv-spec-table`, `th[scope="row"]`, `<details class="drv-spec-more">` | Nút sao chép mã SKU trong dòng đầu gọi `drv_ui('copy-inline')` |
| `shop-the-look` | `product: \WC_Product`, `image_id: int = 0` *(nếu `> 0` render cả `<figure>` ảnh phối cảnh + thanh chân ảnh; nếu `0` chỉ render thanh bar gắn dưới block ảnh)*, `caption: ?string = null` | `.drv-shop-look`, `.drv-shop-look__product`, `.drv-shop-look__swatch` (`44×44px`) | Nút chính `<a href>` tới PDP + nút phụ `data-drv-action="quote-add"` |
| `breadcrumb` | `items: ?array = null` *(mặc định lấy từ `Dravit\Seo\Breadcrumbs` tích hợp Rank Math, đảm bảo đúng 1 `BreadcrumbList` duy nhất theo `IL-04`)* | `nav.drv-breadcrumb[aria-label="Đường dẫn"]`, `.drv-breadcrumb__sep` (`aria-hidden="true"`), `[aria-current="page"]` | Không có JS |
| `geo-table` | `title: string`, `headers: string[]`, `rows: array<int, array<int, string>>`, `caption: ?string = null`, `note: ?string = null` *(dùng cho bảng thông số GEO trên Collection `02` và bài Cẩm nang `08`)* | `.drv-geo-table-wrap`, `table.drv-geo-table` | Không có JS (HTML tĩnh 100% cho AI/GEO bot đọc) |
| `faq` | `items: array<int, array{q: string, a: string}>`, `open_first: bool = true`, `register_schema: bool = true` *(đăng ký đúng mảng này vào `Seo\SchemaGraph` để `FAQPage` JSON-LD khớp 100% nội dung hiển thị)* | `.drv-faq`, `details.drv-faq__item`, `summary.drv-faq__q`, `.drv-faq__a` | Native `<details>`/`<summary>`, không cần JS |
| `dialog` | `id: string`, `title: string`, `eyebrow: ?string = null`, `variant: 'modal'\|'drawer-right'\|'drawer-left'\|'sheet-bottom' = 'modal'`, `body_html: string`, `foot_html: string = ''`, `class: string = ''` | `dialog.drv-dialog`, `.drv-dialog--{variant}`, `.drv-dialog__head`, `.drv-dialog__body`, `.drv-dialog__foot` | Native `<dialog>` điều khiển qua `data-drv-action="dialog-open"` / `"dialog-close"` trong `core.js` (khóa scroll, Esc đóng, trả focus về trigger) |
| `load-more` | `shown: int`, `total: int`, `next_url: ?string`, `per_page: int = 24` *(nếu `$shown >= $total` chỉ render thanh tiến độ đã xem hết, ẩn nút)* | `.drv-load-more-wrap`, `.drv-load-more__progress` (`--_pct`), `a.drv-btn.drv-btn--outline[rel="next"]` | Bắt buộc render thẻ `<a rel="next" href="{next_url}">` để Googlebot và trình duyệt tắt JS phân trang được; `catalog.js` chặn click để nối `fragment=items` |
| `copy-inline` | `value: string`, `label: ?string = null` *(nếu truyền sẽ hiển thị kèm chữ, mặc định chỉ icon cạnh text)*, `feedback: string = 'Đã chép'`, `aria_label: string = 'Sao chép'` | `button.drv-btn-copy-inline` | `data-drv-action="copy-text"`, `data-drv-value="{value}"`, `data-drv-feedback="{feedback}"` |
| `summary-box` | `rows: array<int, array{label: string, value_html: string, muted?: bool, highlight?: bool, data_attr?: string}>`, `total_label: string`, `total_html: string`, `footnote: ?string = null` *(dùng cho Cart, Mini-cart, Checkout, BOQ)* | `.drv-summary-box`, `.drv-summary-row`, `.drv-summary-row--total`, `u-tabular` | `aria-live="polite"` trên vùng chứa số tổng |
| `steps` | `steps: array<int, array{label: string, sub?: string, state: 'done'\|'current'\|'upcoming'}>`, `variant: 'compact'\|'timeline' = 'compact'` *(dùng cho thanh 3 bước Checkout `05` và Timeline 4 bước Order Received `06`)* | `ol.drv-steps`, `.drv-steps--{variant}`, `.drv-steps__item.is-{state}` | Không có JS; trạng thái tính từ route / `$order->get_status()` |
| `swatch` | `items: array<int, array{label: string, url: string, active: bool, hex?: string, thumb_url?: string, available?: bool, sub?: string}>`, `axis: 'color'\|'size'\|'surface'`, `group_label: string` | `.drv-swatch-group`, `a.drv-swatch`, `.drv-swatch--{axis}`, `.drv-swatch__dot` (`--_swatch-hex`), `.is-active`, `.is-unavailable` | **100% là thẻ `<a href>` SSR** dẫn tới SKU anh em cùng `drv_series` (`D-26`); mục đang xem dùng `<span aria-current="page" class="drv-swatch is-active">`. Không dùng JS đổi SKU giả |

### 3.3 Đặc tả Chuyên sâu `drv_ui('product-card')` - Xóa bỏ 3 Bản Nhân bản trong Prototype

Trong prototype hiện tồn tại **3 bản code card sản phẩm rời rạc và lệch cấu trúc nhau**:
1. HTML tĩnh chép tay trên `00-ui-kit.html`, `01-home.html`, `02-catalog-collection.html`, `03-product-single.html`, `08-blog-single.html` (dùng bừa class `.drv-card--quote` cho cả sản phẩm có giá, chứa 52 nút `.drv-card__wish` và truyền chuỗi `data-drv-qty="1 thùng"`).
2. Hàm `renderProductCardHtml(item)` trong `assets/js/catalog-mock.js` (L508–596, gắn hàng loạt `data-usage`, `data-space`, `data-size` để lọc giả ở client).
3. Hàm `renderRecommendationsGrid()` trong `assets/js/ai-finder.js` (L1430–1585, vẽ bằng SVG giả lập vân gạch thay vì ảnh thật).

Khi port sang WordPress, **toàn bộ 3 nơi trên bị xóa bỏ** và quy về duy nhất `template-parts/ui/product-card.php`:

```php
<?php
// Rút gọn logic cốt lõi trong template-parts/ui/product-card.php
declare(strict_types=1);
defined('ABSPATH') || exit;

/** @var \WC_Product|null $product */
$product = $args['product'] ?? null;
if (!$product instanceof \WC_Product) {
    return;
}

$id          = $product->get_id();
$sku         = (string) $product->get_sku();
$price_state = \Dravit\Units\PriceHtml::get_price_state($product); // 'priced' | 'sale' | 'quote_only' | 'out_of_stock'
$is_quote    = ($price_state === 'quote_only');
$is_out      = ($price_state === 'out_of_stock');

// Quy tắc số viên thêm nhanh theo cài đặt từng sản phẩm (D-24): luôn là SỐ NGUYÊN VIÊN
$per_box      = max(1, (int) $product->get_meta('drv_pieces_per_box'));
$qty_min      = max(1, (int) $product->get_meta('drv_qty_min'));
$default_unit = (string) ($product->get_meta('drv_default_buy_unit') ?: 'thung');
$round_mode   = (string) ($product->get_meta('drv_round_mode') ?: 'box');
$unit_label   = (string) ($product->get_meta('drv_unit_label') ?: 'viên');

if ($round_mode === 'box' || in_array($default_unit, ['m2', 'thung'], true)) {
    $quick_pieces = (int) (ceil(max($per_box, $qty_min) / $per_box) * $per_box);
} else {
    $quick_pieces = $qty_min;
}

$gallery_ids = $product->get_gallery_image_ids();
$hover_id    = $gallery_ids[0] ?? 0; // Quy ước: ảnh đầu tiên trong gallery là ảnh phối cảnh hover
$priority    = !empty($args['priority_load']);
$heading_tag = (($args['heading_tag'] ?? 'h3') === 'h2') ? 'h2' : 'h3';
```

**Hợp đồng hiển thị trên `drv_ui('product-card')` theo `drv_price_state` (`D-20`, `D-24`, `D-26`):**

| Trạng thái (`drv_price_state`) | Class gốc `<article>` | Badges góc trên trái (`.drv-card__badges`, tối đa 2) | Khu vực Giá (`.drv-card__prices`) | Nút hành động chân card (`.drv-card__buy`) |
|---|---|---|---|---|
| `priced` | `drv-card drv-card--priced` | 1. `drv_card_label` (nếu có, VD: `Mới 2026`, `Bán chạy`)<br>2. Nếu `drv_showroom_shelf !== ''`: badge `Showroom` (hoặc `Kệ {shelf}`) | Dòng 1: `395.000đ/m²`<br>Dòng 2: `≈ 252.800đ/{unit_label}` (+ `· Còn {X} thùng` khi bật quản lý kho) | `<button data-drv-action="add-to-cart" data-product-id="{id}" data-drv-qty-pieces="{quick_pieces}">` Icon `ph-shopping-cart-simple` + `Thêm giỏ` |
| `sale` | `drv-card drv-card--priced drv-card--sale` | 1. Badge `-{pct}%` (nền `--c-sale`; nếu thuộc `/gach-uu-dai` lẻ lô hiện `-{pct}% Lẻ lô`)<br>2. Badge `Showroom` (nếu có kệ) | Dòng 1: `<span class="drv-card__price-m2--sale">385.000đ/m²</span> <del class="drv-card__price-old">450.000đ</del>`<br>Dòng 2: `≈ 246.400đ/{unit_label} · Còn {X} thùng` | Như `priced` |
| `quote_only` (`drv_price_m2 <= 0`) | `drv-card drv-card--quote` | **CẤM** gắn badge `% giảm`. Chỉ hiện `drv_card_label` (VD: `Big Slab`) + `Showroom` (nếu có) | Dòng 1: `<span class="drv-card__price-contact"><i class="ph-fill ph-tag"></i><span>Liên hệ báo giá</span></span>`<br>Dòng 2: `1 thùng = {m2_box} m² ({per_box} {unit_label})` | `<button data-drv-action="quote-add" data-product-id="{id}" data-drv-sku="{sku}">` Icon `ph-plus` + `Báo giá` |
| `out_of_stock` (có giá nhưng hết kho) | `drv-card drv-card--out` | Badge `Tạm hết kho` (`outline`) + `Showroom` (nếu vẫn còn mẫu trưng bày) | Giữ hiển thị giá tham khảo (hoặc `Liên hệ báo giá` nếu không có giá), dòng tồn ghi `Đặt trước theo lô` | `<button data-drv-action="quote-add" data-product-id="{id}" data-drv-sku="{sku}">` Icon `ph-bell` + `Báo giá lô mới` |

---

## 4. Chiến lược Tách `pages.css` (305KB) & Port JS IIFE sang Vite ES Modules (`D-28`)

### 4.1 Thứ tự `@layer` Chuẩn & Nguyên tắc Khử `!important` / HEX Cứng

Mọi entry CSS đều kế thừa thứ tự tầng duy nhất khai báo đầu `assets/src/css/main.css`:

```css
@layer reset, tokens, base, layout, components, woo, utilities;
```

- **Khử toàn bộ 29 `!important` trong prototype (`1` trong `main.css` + `28` trong `pages.css`):**
  1. `main.css` L45 & `pages.css` L4704, L5716, L6729, L7325, L12546, L12645, L12714, L12995 (`[hidden] { display: none !important; }`): Chuyển duy nhất 1 rule `[hidden] { display: none; }` xuống cuối `@layer utilities` (cao hơn cả `components` và `woo`, thắng mọi `display: flex/grid` trong component mà không cần `!important`).
  2. `pages.css` L6734–6741 (`@media print` A4 background/color/margin/display): Đặt trong `@layer utilities` bên trong `@media print` của `quote.css`.
  3. `pages.css` L9413–10265 (8 chỗ `!important` đè `list-style`, `margin`, `text-decoration` của thẻ trong `.drv-prose` bài viết Cẩm nang): Đổi selector `.drv-prose ul` trong `base.css` sang `:where(.drv-prose) :where(ul:not([class]))` có độ đặc hiệu `0,0,0` để mọi component nhúng trong bài (`.drv-geo-box`, `.drv-shop-look`, `.drv-card`) thắng tự nhiên ở `@layer components`.
  4. `pages.css` L4354–4355 (lightbox width `96vw !important`): Xóa hẳn vì thay bằng PhotoSwipe 5.
  5. `pages.css` L14046–14053 (nút AI dùng `linear-gradient(135deg, #0d9488, #10b981) !important`): Xóa bỏ hoàn toàn (vi phạm cả luật cấm gradient AI lẫn cấm `!important`, thay bằng `.drv-btn--moss` chuẩn token).
- **Token hóa 197 mã màu HEX cứng trong `main.css` & `pages.css`:** Chạy Stylelint rule `color-no-hex: true` trên toàn bộ `assets/src/css/layers/{base,layout,components,woo,utilities}` và `assets/src/css/pages/*` (chỉ cho phép mã HEX nằm trong `tokens.css` sinh từ `tokens.json`). Mọi alias cũ `--c-moss-*` ánh xạ sang `--c-teal-*` trong `tokens.css` để tương thích ngược, code mới dùng `--c-teal-*`.

### 4.2 Bảng Ánh xạ Dải dòng Chính xác từ Prototype CSS → Theme Vite CSS

| File & Dải dòng trong `docs/layout/assets/css/` | Nội dung hiện tại | File đích trong `wp-content/themes/dravit/assets/src/css/` | Layer & Biến đổi khi Port |
|---|---|---|---|
| `tokens.css` `L1–182` | Bảng màu, font, fluid scale, spacing, container `1380px`, z-index, motion curves | `assets/src/tokens/tokens.json` → build ra `layers/tokens.css` + `theme.json` | `@layer tokens`. Thống nhất `--container-max: 1380px` (`D-28`). |
| `fonts.css` `L1–50` | 6 `@font-face` tách lẻ (`latin`, `latin-ext`, `vietnamese`) | `layers/tokens.css` (hoặc `layers/fonts.css` import vào `main.css`) | Gộp còn **2 file font chữ** (`anton-vi.woff2`, `quicksand-vf-vi.woff2`) + `Quicksand Fallback` metric override + **1 `@font-face` `Phosphor-Dravit`** (`phosphor-subset.woff2`, ~140 icon). Xóa Google Fonts `JetBrains Mono`. |
| `main.css` `L14–98` | CSS Reset | `layers/reset.css` | `@layer reset`. Bỏ `!important` ở L45 (chuyển `[hidden]` sang `utilities.css`). |
| `main.css` `L102–194` | Base typography, headings, table, hr | `layers/base.css` | `@layer base`. Đảm bảo `h1..h6` dùng `--c-navy-900`, `body` dùng `--c-text-main` (`D-18`). |
| `main.css` `L198–404` | `.l-container`, `.l-stack`, `.l-cluster`, `.l-grid`, `.l-grout-grid`, `.l-section`, `.l-split` | `layers/layout.css` | `@layer layout`. Giữ nguyên 7 layout primitives. |
| `main.css` `L410–453` | Skip link + `.drv-icon` / `i.ph` | `layers/components/primitives.css` | `@layer components`. Kèm mapping glyph codes của `phosphor-subset.woff2`. |
| `main.css` `L454–515` | `.drv-proto-bar` | *(Xóa bỏ)* | Không đưa vào codebase WordPress. |
| `main.css` `L516–1015` | `.drv-btn`, `.drv-btn-copy-inline`, `.drv-badge`, `.drv-chip`, `.drv-field`/`.drv-input`/`.drv-check` | `layers/components/{buttons,badges-chips,forms}.css` | `@layer components`. Import vào `main.css`. |
| `main.css` `L1016–2034` + `L3397–3878` | Global Header 2 tầng, Mega Menu 4+1, Live Search, Dock 3 ô, Footer 4 cột, Mobile Bar | `layers/components/{header,mega-menu,footer}.css` | `@layer components`. Import vào `main.css` (tách phần above-the-fold của Header vào `critical-*.css`). |
| `main.css` `L2035–2631` | `.drv-card` + `.drv-shine` (`L2290–2313`) + `.drv-card--quote` | `layers/components/product-card.css` | `@layer components`. **Xóa khối `.drv-shine` (`L2290–2313`)** và `.drv-card__wish`. Tách rạch ròi `.drv-card--priced` và `.drv-card--quote`. |
| `main.css` `L2632–3396` + `L3879–4208` | `.drv-silo-tile`, `.drv-sec-head`, `.drv-spec-table`, `.drv-shop-look`, `.drv-calc`, `<drv-qty-input>`, `.drv-breadcrumb`, `.drv-dialog`, `.drv-load-more-wrap`, `.drv-geo-box`, `.drv-faq`, `.drv-toast-region`, motion base | Chia đúng phạm vi sử dụng:<br>• Toàn cục (`sec-head`, `breadcrumb`, `dialog`, `toast`, `qty-input`, `motion`): giữ trong `main.css`.<br>• Chỉ dùng ở Catalog/PDP/Blog (`silo-tile`, `spec-table`, `shop-look`, `calc`, `load-more`, `geo-box`, `faq`): tách thành partial CSS import vào đúng page bundle để giữ `main.css ≤ 20KB gzip`.<br>• Khối demo `.drv-motion-grid` (`L4090–4208`): chuyển sang `pages/ui-kit.css`. | `@layer components`. |
| `main.css` `L4212–4342` | `.u-*` helpers (`u-tabular`, `u-sr-only`, `u-bold`, `u-bd`...) | `layers/utilities.css` | `@layer utilities`. Thêm `[hidden] { display: none; }`. |
| `pages.css` `L10–2180` + `L13296–13692` + `L14080–14087` | Homepage 8 sections + Hero + `<drv-size-guide>` + `<drv-showroom>` | `pages/home.css` | `@layer components`. Tối ưu Hero có `<picture>` LCP thật dưới lớp `<canvas>` desktop (`D-25`). |
| `pages.css` `L2181–3129` + `L12727–13295` | Catalog layout, sticky toolbar, 14 facets sidebar, `.drv-filter-sheet`, bảng GEO & FAQ gọn | `pages/catalog.css` | `@layer components`. Hợp nhất rules bị ghi đè 2 lần của sidebar (`L2181` và `L12727`) thành 1 định nghĩa sạch. |
| `pages.css` `L3130–4591` + `L13693–13756` | PDP 2-col split, gallery, sticky buy box, faces 6 mặt, `[data-pdp-only="quote"]`, sticky bar đáy | `pages/product.css` | `@layer components`. Xóa CSS của `#drv-lightbox-dialog` tự viết (thay bằng `photoswipe/style.css` nạp động khi click ảnh). |
| `pages.css` `L4592–5754` | Trang Giỏ hàng (desktop + mobile ledger card) | `pages/cart.css` | **Đặt trong `@layer woo`**. Xóa `.drv-cart-demo-bar` và toàn bộ CSS `.drv-fbt-*` (phụ kiện FBT ẩn ở Phase 1, `D-26`). |
| `pages.css` `L5755–6793` + `L12456–12726` + `L13757–13806` | Trang Báo giá theo phòng (`04.1`), `@media print` A4, Modal Picker, `<drv-quote-panel>` | `pages/quote.css` (+ tách nhẹ phần drawer `<drv-quote-panel>` thành chunk CSS dùng chung khi mở drawer) | `@layer components` (riêng print helper nằm ở `@layer utilities` trong `@media print`). Xóa `.drv-pack-grid` vật tư phụ Phase 1. |
| `pages.css` `L6794–8093` | Checkout 2 cột (`L6794–7649`, desktop + mobile) & Order Received + VietQR (`L7650–8093`) | `pages/checkout.css` | **Đặt trong `@layer woo`** để thắng tự nhiên các selector còn sót của WooCommerce mà không dùng `!important`. |
| `pages.css` `L8094–10637` | Blog Archive Bento 5/4/3 (`L8094–8760`) & Blog Single Architectural Sheet (`L8761–10637`) | `pages/blog.css` | `@layer components`. Dùng chung cho `home.php`, `category.php`, `single.php`, `template-policy.php`. |
| `pages.css` `L10638–10825` | Trang Liên hệ (`09`) & 404 (`10`) | `pages/contact-404.css` (~2.5KB gzip) | `@layer components`. Bổ sung lưới 4 thẻ Silo cho `404.php`. |
| `pages.css` `L10826–12455` + `L13807–14079` | `<drv-ai-finder>` Split/Widget & In-article AI Chat Card | `pages/ai-finder.css` *(chỉ nạp ở Phase 3)* | `@layer components`. **Không nạp ở Phase 1** (`D-25`), giúp giảm tức thì ~45KB CSS raw. |

### 4.3 Bảng Ánh xạ Prototype JS IIFE → Vite ES2022 Modules & Dynamic `import()`

Mọi script classic IIFE (`(() => { ... })()`) và biến toàn cục `window.DravitCalc` / `DRAVIT_PRODUCTS` chuyển sang **ES2022 Modules** đóng gói qua Vite (`type="module"`), nạp đúng theo template (`Core\Assets`), tuyệt đối không vượt ngân sách JS tại `08 §1`.

| File / Inline Script trong Prototype | Vấn đề trong Prototype | Phân tách sang Module đích (`assets/src/js/...`) | Cơ chế nạp trên WordPress |
|---|---|---|---|
| `shell-partials.js` (90KB) + `DrvSiteHeader`/`DrvSiteFooter` trong `core.js` (`L1783–1833`) | Tải lại 90KB HTML chuỗi cứng trên mọi trang | **XÓA BỎ HOÀN TOÀN** (đã render bằng `header.php` & `footer.php`) | 0KB JS |
| `core.js` (`L1–1974`, 77KB raw) | Ôm đồm cả Checkout, Order Received, Lightbox, `VN_PROVINCES`, auto-inject `ai-finder.js`, `glass-shine` | Tách nhỏ thành:<br>1. `utils/{format,dom,http,toast,storage}.js` (shared helpers)<br>2. `core.js` (**≤ 8KB gzip**): Action Registry `registerAction()`, `<dialog>` open/close/Esc, Header sticky + hover nav + Mega preview, `copy-text`, badge hydration (`localStorage` quote count + cookie `woocommerce_items_in_cart`), lead form submit handler.<br>3. Tách `initCheckoutController` (`L1036–1200`) → `checkout-vn.js`.<br>4. Tách `initOrderReceivedController` (`L1202–1294`) → `order.js`.<br>5. Tách `initScrollStagger` & Anime.js (`L1684–1755`) → `chunks/motion.js`.<br>6. **Xóa bỏ:** `VN_PROVINCES` giả (`L177`), `lightbox-*` tự viết (`L527`), `wish-toggle` (`L608`), `motion-demo-*` (`L619`), auto-inject `ai-finder.js` (`L1846`), `.drv-shine` (`L1880`). | `core.js` nạp mọi trang; `chunks/motion.js` (+ `animejs` v3.2.2 tự host) chỉ `import()` động trong `IntersectionObserver` khi gặp `[data-drv-stagger]`/`[data-drv-reveal]` và `!prefersReducedMotion` |
| `calc-core.js` (`L1–177`, 5.7KB) | UMD/Global `window.DravitCalc`, `VOLUME_TIERS` đóng cứng trong JS | `utils/calc.js` (Pure ES module export `pieceAreaM2`, `pricePerPiece`, `tilesForArea`, `boxesForArea`, `boxBreakdown`, `pieceTotals`, `volumeDiscount`, `vehicleHint`). Đồng bộ 1:1 bộ unit test với class PHP `Dravit\Units\Calc` (`D-24`). Nhận `volumeTiers` & `vehicleThresholds` từ config server truyền xuống. | Vite tự gom vào shared chunk cho `product.js`, `cart.js`, `quote.js` |
| `unit-calc.js` (`L1–1159`, 47.8KB) | Trộn lẫn `<drv-unit-calc>`, `<drv-qty-input>`, logic demo giỏ hàng, FBT vật tư, BOQ CSV cũ và **2 bản copy-paste handler đổi SKU giả** (`L1006–1150`) | Tách thành:<br>1. `components/unit-calc.js` (`<drv-unit-calc>` chuẩn `D-20` + `D-24`, đọc bắt buộc `data-product-id`, `data-piece-w`, `data-piece-h`, `data-pieces-per-box`, không fallback `'DRV-8801M'`).<br>2. `components/qty-input.js` (`<drv-qty-input>` dùng cho Cart & Mini-cart).<br>3. `product.js`: nạp `<drv-unit-calc>`, đồng bộ Sticky Purchase Bar, ghi `drv_viewed` (`localStorage` tối đa 8 ID), gắn trigger `import('./chunks/lightbox.js')` (PhotoSwipe 5) khi click ảnh gallery.<br>4. `cart.js`: nạp `<drv-qty-input>`, nút *"Làm tròn chẵn thùng"*, nút `cart-to-quote`.<br>5. **Xóa bỏ:** toàn bộ FBT (`L618–696`, `L788–817`), demo cart (`L550–587`), BOQ preview trùng lặp (`L819–1004`), và 2 listener biến thể (`L1006–1150`). | `product.js` chỉ nạp ở `is_product()`; `cart.js` chỉ nạp ở `is_cart()` (và `qty-input.js` lazy-load cùng Mini-cart Drawer) |
| `catalog-mock.js` (`L1–1527`, 59.3KB) | Chứa 450 dòng sản phẩm giả, lọc mảng trong RAM, không `pushState`, gộp cả Live Search và Blog Quick Calc | Tách thành:<br>1. `catalog.js` (**≤ 7KB gzip**): `<drv-filter-form>` gửi `GET /wp-json/dravit/v1/catalog` (có `AbortController` hủy request cũ khi tick nhanh), cập nhật DOM grid/facets/active-chips/count (`aria-live="polite"`), gọi `history.pushState()`, xử lý `popstate` Back/Forward, nối Load More + lưu vị trí cuộn vào `sessionStorage`, reparent 1 form duy nhất vào `#drv-filter-sheet` trên mobile.<br>2. `chunks/search.js`: Live Search 2 cột (debounce `200ms`, gọi `GET /wp-json/dravit/v1/search?q=...`), chỉ `import()` động khi user focus vào `#global-search-input` hoặc bấm nút kính lúp mobile.<br>3. **Xóa bỏ:** mảng mock items, `hashSku`, `stockOf`, `renderProductCardHtml`. | `catalog.js` chỉ nạp ở trang danh mục/archive SP; `chunks/search.js` lazy-load khi focus ô tìm kiếm |
| `quote-sheet.js` (`L1–922`, 35.3KB) + QuoteManager trong `ai-finder.js` (`L795–920`, `L1740–2288`) | Đứt chuỗi dữ liệu giữa PDP/Card (`drv_quote_items_v1`) và trang `04.1` (mảng `spacesState` cứng + `#qs-catalog` inline) | Hợp nhất thành:<br>1. `utils/quote-store.js`: quản lý thống nhất `localStorage['drv_quote_v2']`, phát sự kiện `drv:quote-updated`.<br>2. `chunks/quote-panel.js`: điều khiển `<drv-quote-panel>` drawer nhanh.<br>3. `quote.js`: trang `/danh-sach-bao-gia` (`04.1`), hydrate qua `GET /wp-json/dravit/v1/products`, Modal Picker tìm kiếm live REST, in A4, xuất CSV UTF-8 BOM, copy Zalo, gửi `POST /wp-json/dravit/v1/quote/submit` (`17`). | `quote.js` chỉ nạp ở `template-quote.php`; `chunks/quote-panel.js` chỉ `import()` khi mở drawer hoặc bấm `quote-add` |
| `showroom.js` (`L1–232`) + `size-guide.js` (`L1–201`) + `hero-ai.js` (`L1–871`) | Nạp rời 3 file trên Home; `hero-ai.js` (31KB) chạy canvas ngay lúc tải trang làm nghẽn LCP; `size-guide.js` đóng cứng link `.html` | Gộp entry `home.js`:<br>• Đăng ký `<drv-size-guide>` (đọc dữ liệu & URL tĩnh từ `data-sg-config` do PHP xuất từ `config/size-guide.php`, không hardcode `.html`) và `<drv-showroom>` (chỉ bật thanh kéo so sánh `3000K/6500K` khi admin đã upload đủ 2 ảnh thật `cool_img_id` ≠ `warm_img_id`, `O-10`).<br>• Tách phần hiệu ứng `<canvas>` của `hero-ai.js` thành `chunks/hero-canvas.js`, chỉ `import()` động thông qua `requestIdleCallback` **sau khi ảnh `<picture>` LCP đã tải xong**, trên màn hình `≥ 1024px` và `!prefersReducedMotion` (`D-25`). Phần `parseVietnameseTilePrompt` tách sang Phase 3. | `home.js` chỉ nạp ở `is_front_page()` |
| Inline `<script>` cuối `03-product-single.html` (`L2228–2235`) | Đổi `data-pdp-mode="quote"` theo query `?gia=lien-he` | **XÓA BỎ HOÀN TOÀN**. Chế độ `priced` hay `quote` do PHP quyết định 100% từ `$product->get_meta('drv_price_m2')` (`D-20`). | 0KB JS |
| Inline `<script>` cuối `07-blog-archive.html` (`L818–856`) | Lọc ẩn/hiện bài viết bằng JS trên DOM tĩnh + đổi `aria-selected` trên thẻ `<a>` | **XÓA BỎ HOÀN TOÀN**. Thanh chuyên mục là các thẻ `<a href="/chu-de/{slug}">` điều hướng thật tới `category.php` (bỏ `role="tablist"` sai chuẩn trên link); ô tìm kiếm bài viết là `<form method="get" action="/cam-nang">` SSR chuẩn. | 0KB JS |
| Inline `<script>` cuối `08-blog-single.html` (`L1616–1891`, 276 dòng) | Chứa Lightbox riêng thứ 2, Poll đếm giả `148`, form mẫu giả, TOC scrollspy inline, carousel inline | Chuyển gọn vào `blog.js` (**tổng `core.js` + `blog.js` ≤ 10KB gzip**, `D-28`):<br>1. `<drv-toc>` Light DOM: Scrollspy `IntersectionObserver` + thanh tiến độ đọc + mở `<dialog>` mục lục mobile.<br>2. Nút prev/next cho `.drv-carousel` (`scroll-snap`).<br>3. Khi click ảnh trong `.drv-prose`, gọi chung `import('./chunks/lightbox.js')` (PhotoSwipe 5).<br>4. Nút `quote-add` và `[drv_lead_form]` đi qua handler chuẩn của `core.js`. **Xóa khối Poll giả `148`.** | `blog.js` nạp ở `home.php`, `category.php`, `single.php`, `template-policy.php` |

---

## 5. Danh sách BẮT BUỘC LOẠI BỎ / SỬA KHI PORT (Strict Strip & Fix List)

Mọi lập trình viên thực hiện port template **bắt buộc rà soát từng mã dưới đây**. Bất kỳ mục nào từ `STRIP-01` đến `STRIP-26` còn xuất hiện trong `wp-content/themes/dravit/` sẽ bị chặn ngay ở bước Code Review.

| Mã | Thành phần trong Prototype (Vị trí xuất hiện) | Hậu quả nếu lọt lên Production | Hành động Bắt buộc khi Port sang WordPress |
|---|---|---|---|
| `STRIP-01` | Thanh `.drv-proto-bar` & `syncActiveProtoLink()` (`partials/header.html` L1–19, `03` L148, `05` L24, `main.css` L454, `core.js` L1758) | Lộ thanh điều hướng dev HTML cho khách hàng, hỏng SEO internal link | Xóa toàn bộ HTML, CSS và JS liên quan. |
| `STRIP-02` | Thanh `.drv-cart-demo-bar`, action `cart-toggle-empty`, `cart-restore-demo` (`04-cart.html` L93, `unit-calc.js` L550–587) | Lộ công cụ giả lập giỏ rỗng/đầy trên trang `/gio-hang` | Xóa hoàn toàn; trạng thái giỏ rỗng do WooCommerce tự gọi `woocommerce/cart/cart-empty.php`. |
| `STRIP-03` | Thanh demo Checkout & các action `checkout-demo-fill`, `checkout-demo-error`, `checkout-demo-reset` (`05-checkout.html` L137–160, `core.js` L1036+) | Khách bấm nhầm ghi đè thông tin người nhận bằng dữ liệu mẫu giả | Xóa hoàn toàn cả thanh HTML và các nhánh xử lý demo trong JS. |
| `STRIP-04` | Thanh demo trạng thái đơn `data-order-demo-state="awaiting-quote\|quote-confirmed"` (`06-order-received.html` L81–105, `core.js` L1202+) | Khách tự bấm đổi giao diện xác nhận đơn/hiện VietQR khi chưa có phí ship | Xóa hoàn toàn; trạng thái trên `thankyou.php` render 100% từ `$order->get_status()` phía PHP. |
| `STRIP-05` | Huy hiệu debug `[data-catalog-robots-badge]` (`02-catalog-collection.html`, `catalog-mock.js`) | Lộ nhãn kỹ thuật `INDEX, FOLLOW` / `NOINDEX` trên giao diện danh mục | Xóa khỏi HTML/JS; giá trị robots chỉ xuất trong thẻ `<meta name="robots">` qua `Seo\RobotsMatrix`. |
| `STRIP-06` | `#catalogMockBackdrop` & cụm nút `[data-mode-switch="split\|stream\|widget"]` (`01.1-ai-finder.html` L93, `ai-finder.js` L2016) | Nặng DOM, lộ khung giả lập chế độ hiển thị cho dev | Xóa hoàn toàn; mỗi vị trí nhúng dùng cố định 1 layout chuẩn. |
| `STRIP-07` | Inline `<script data-pdp-mode-switch>` (`03-product-single.html` L2228–2235) | Cho phép sửa giao diện giá PDP qua tham số URL `?gia=lien-he` | Xóa hoàn toàn; PHP render nhánh `priced` hoặc `quote` dựa trên `drv_price_m2` (`D-20`). |
| `STRIP-08` | `<drv-site-header>`, `<drv-site-footer>` & toàn bộ `assets/js/shell-partials.js` (90KB) | Chặn render header/footer bằng JS, mất nội dung với Googlebot, CLS nặng | Xóa file `shell-partials.js` và custom elements tương ứng; thay bằng `header.php` & `footer.php`. |
| `STRIP-09` | Đoạn tự chèn `<script src="assets/js/ai-finder.js">` trên mọi trang (`core.js` L1846–1854) | Phình thêm ~23.5KB gzip JS trên mọi trang kể cả Blog, phá vỡ ngân sách hiệu suất | Xóa bỏ hoàn toàn; ở Phase 1 tắt `features.ai_enabled = false` (`D-25`). |
| `STRIP-10` | 14 thuộc tính lọc client-side `data-usage`, `data-space`, `data-size`, `data-surface`, `data-dcof`... trên `.drv-card` (`02`, `catalog-mock.js`) | Phình kích thước HTML trang danh mục, lộ logic lọc giả trên RAM | Xóa sạch khỏi `<article class="drv-card">`; chỉ gắn `data-product-id` trên nút CTA. |
| `STRIP-11` | Các mảng dữ liệu giả viết cứng: `DRAVIT_PRODUCTS` (`ai-finder.js` L22, `catalog-mock.js` L35), `<script id="qs-catalog">` (`04.1` L467), `VN_PROVINCES` chứa mã sai như `BDG` = Tây Ninh (`core.js` L177) | Lệch giá/mã gạch thật, sai địa giới hành chính Việt Nam trên đơn hàng | Xóa toàn bộ; đọc sản phẩm qua WC / REST `/dravit/v1/products`, đọc 34 tỉnh từ `config/provinces-vn.php` và phường/xã từ `assets/data/wards/{code}.json` chính thức (`O-06`). |
| `STRIP-12` | Fallback mã gạch cứng `\|\| 'DRV-8801M'` khi thiếu `data-*` (`unit-calc.js` L62, `core.js` L580/587/603, `ai-finder.js` L2061/2174) | **Rủi ro thương mại nghiêm trọng:** nếu DOM thiếu thuộc tính, khách mua mã A nhưng giỏ hàng đặt nhầm sang `DRV-8801M` | Xóa mọi fallback `'DRV-8801M'`; nếu thiếu `data-product-id` hợp lệ thì ghi `reportError()`, vô hiệu hóa nút mua và báo lỗi rõ. |
| `STRIP-13` | Listener click `[data-variant-type]` bị nhân đôi 2 lần liên tiếp & tự ghép chuỗi mã SKU/giá giả ở client (`unit-calc.js` L1006–1077 & L1079–1150) | Chạy lặp sự kiện 2 lần, tạo ra mã SKU không tồn tại trong kho WooCommerce, mất SEO URL riêng | Xóa cả 2 khối listener; render biến thể màu/kích thước/bề mặt bằng `drv_ui('swatch')` sinh thẻ `<a href>` SSR sang SKU anh em qua `drv_series` (`D-26`). |
| `STRIP-14` | 52 nút Wishlist `.drv-card__wish` / `[data-drv-action="wish-toggle"]` (`00`, `01`, `02`, `03`, `core.js` L608–617) | Tính năng ngoài phạm vi Phase 1, lưu giả không bền vững gây hiểu nhầm cho khách | Gỡ sạch nút tim khỏi `product-card`, PDP và `core.js` (`D-26`). |
| `STRIP-15` | Khối mua kèm vật tư phụ FBT / keo-ron (`.drv-fbt-*` trên `03`/`04`, `.drv-pack-item` trên `04.1` L139–185, `DRV-RON01` trong `footer.html` L108, `unit-calc.js` L618–696 & L788–817) | Ngành hàng keo/ke ron thuộc Phase sau (`00 §3`, `D-13`, `D-26`) | Ẩn/gỡ toàn bộ khối FBT & vật tư phụ trên giao diện Phase 1; vẫn giữ nguyên class PHP `Units\PricingModel\Pack` để sẵn sàng mở ở Phase 2. |
| `STRIP-16` | Google Fonts CDN `JetBrains Mono` (`01-home.html` L8–10) & 3 thẻ `<link>` CDN `@phosphor-icons/web@2.1.2` trên đầu mọi trang HTML | Vi phạm luật cấm Google Fonts ngoài (`08 §5`), 3 file CSS Phosphor (~36KB gzip) chặn render FCP/LCP | Xóa hoàn toàn; tự host `anton-vi.woff2`, `quicksand-vf-vi.woff2` và `phosphor-subset.woff2` (`D-28`). |
| `STRIP-17` | Ảnh hotlink Unsplash/Pexels, SVG gạch giả lập và việc gắn `fetchpriority="high"` tràn lan trên 12 ảnh cùng trang | Vi phạm bản quyền/thương hiệu (`01 §7`), tranh băng thông làm chậm ảnh LCP thật | Thay bằng ảnh upload qua WP Media (`wp_get_attachment_image()`, WebP/AVIF); mỗi template chỉ có **đúng 1 ảnh LCP duy nhất** mang `fetchpriority="high"` và không `loading="lazy"`. |
| `STRIP-18` | 2 hộp thoại Lightbox `<dialog>` tự viết riêng lẻ (`#drv-lightbox-dialog` trong `03` + `core.js` L527, và `#blog-lightbox-dialog` trong `08` L1708) | Thiếu pinch-to-zoom mượt trên mobile để soi vân gạch, trùng lặp code | Xóa cả 2 dialog tự viết; thay bằng **1 chunk `chunks/lightbox.js` bọc PhotoSwipe 5** (`import()` động khi click ảnh trên PDP và Blog, `D-28`). |
| `STRIP-19` | Listener `mouseover` toàn cục chèn `.drv-shine` (`glass-shine` trong `core.js` L1880–1915 & `main.css` L2290–2313) | Đột biến DOM trên mỗi lần rê chuột, làm tăng INP trên thiết bị di động tầm trung | Xóa hoàn toàn khỏi `core.js` và `main.css` (`D-19`). |
| `STRIP-20` | `<script type="application/ld+json">` viết tay trong HTML + Microdata `itemscope`/`itemprop` (`02` L19, `03` L19 nhồi mã lô vào `mpn` & sai `brand`, `08` L41 sinh 2 `BreadcrumbList` + `HowTo` deprecated + `FAQPage` lệch nội dung) | Lỗi Rich Results trên Google Search Console, mâu thuẫn dữ liệu với Rank Math | Xóa 100% JSON-LD viết tay và mọi thuộc tính `itemscope`/`itemtype`/`itemprop` trong HTML; xuất tập trung qua `Dravit\Seo\SchemaGraph` móc vào filter `rank_math/json_ld` (`06 §4`). |
| `STRIP-21` | Số bình chọn giả `#poll-yes-count` (`148` trong `08` L1826), tên nhân viên hư cấu (*"Trần Minh Quân"* trong `unit-calc.js` L781, tác giả giả trong `08`) và con số đếm SP viết cứng lệch nhau trong Mega Menu | Vi phạm nguyên tắc trung thực E-E-A-T (`01 §10`, `10 §5`, `O-13`) | Xóa khối Poll giả; hồ sơ tác giả & kỹ sư thẩm định đọc từ User Meta thật (`O-13`); số lượng mẫu lấy động từ `$term->count`. |
| `STRIP-22` | Render mã QR chuyển khoản ngay ở trạng thái A (`wc-cho-xac-nhan`) và câu hứa *"Hệ thống đối soát tự động 24/7 trong 3 phút"* (`06-order-received.html`) | Khách chuyển khoản thiếu phí vận chuyển; cam kết sai phạm vi kỹ thuật Phase 1 (`00 §3`, `D-22`) | Chỉ render `Woo\BankQR` khi `$order->get_payment_method() === 'bacs' && $order->has_status('on-hold')`; đổi thông báo sang *"Nhân viên kế toán kiểm tra và cập nhật trạng thái đơn ngay sau khi nhận khoản chuyển"*. |
| `STRIP-23` | Tick sẵn `checked` ở ô Điều khoản đặt hàng & `data-drv-autoselect="true"` tự chọn Phường/Xã đầu tiên (`05-checkout.html`, `core.js` L768) | Giao nhầm phường/xã do khách quên sửa, không hợp lệ về mặt đồng thuận điều khoản (`D-30`) | Bỏ `checked` mặc định trên `#terms`; dropdown Phường/Xã luôn mở đầu bằng `-- Chọn Phường / Xã --` (`value=""`, `required`). |
| `STRIP-24` | Nút `Thêm vào giỏ` / `Mua ngay` truyền chuỗi `"18 viên (6 thùng...)"` không gửi form thật; nút `data-quick-quote` trên PDP lấy nhầm `25 m²` mặc định của AI (`03`, `core.js` L588, `ai-finder.js` L2075) | Không thêm được hàng vào WooCommerce, báo giá sai khối lượng khách vừa tính | Form PDP gửi `POST` / Store API với `quantity` là **số nguyên viên** (`int`) lấy từ `<drv-unit-calc>`; nút `Mua ngay` gửi kèm `<input type="hidden" name="drv_buy_now" value="1">` chuyển thẳng `/thanh-toan` (`D-30`); nút báo giá đọc đúng `target_m2` và `pieces` hiện tại của `<drv-unit-calc>`. |
| `STRIP-25` | Nhân đôi HTML bộ lọc ở Sidebar Desktop và `<dialog>` Mobile + thiếu `history.pushState()` + nút Load More dùng `<button>` (`02-catalog-collection.html`) | Trùng `id`/`name` input form, mất trạng thái khi bấm Back/refresh, bot không cào được trang 2+ | Render **1 `<drv-filter-form>` duy nhất** (dời vào `<dialog id="drv-filter-sheet">` khi mở trên `< 1024px` rồi trả về chỗ cũ khi đóng), bắt buộc `pushState` và dùng `<a rel="next">` cho Load More. |
| `STRIP-26` | Địa chỉ cũ (*"TP. Thủ Đức"*, *"TP. Dĩ An"*), SLA lẫn lộn (*15 phút / 30 phút / 24h*), từ cấm AI (*"tuyệt đối"*, *"siêu phẩm"*) và dấu em-dash trong chuỗi UI (`09-contact.html`, `partials/footer.html`) | Sai chuẩn hành chính 2 cấp từ 01/07/2025 (`D-16`), thiếu nhất quán cam kết dịch vụ, sai quy chuẩn ngôn ngữ (`10`) | Đọc địa chỉ và chuỗi SLA duy nhất từ `drv_settings.showrooms` & `drv_settings.order.sla_text` (`O-09`, `O-11`); rà thay toàn bộ dấu gạch dài trong UI bằng dấu `-` hoặc `·`. |

---

## 6. Checklist Nghiệm thu Port Từng Template (Quality Gate)

Trước khi đánh dấu hoàn tất bất kỳ template nào trong Phase 1–5, lập trình viên bắt buộc kiểm tra đạt 100% 4 cổng nghiệm thu dưới đây:

### 6.1 Cổng HTML, Logic Nghiệp vụ & Tương thích No-JS
- [ ] View Source HTML: có đúng **1 thẻ `<h1>` duy nhất**, phân cấp `h1 → h2 → h3` liền mạch (sửa lỗi nhảy `h1 → h3` trên trang chủ prototype).
- [ ] Mọi thành phần dùng lại (`btn`, `badge`, `product-card`, `unit-calc`, `qty-input`, `spec-table`, `breadcrumb`, `faq`...) đều đi qua `drv_ui()`, không có HTML chép tay.
- [ ] Tắt JavaScript trên trình duyệt (DevTools → Disable JavaScript):
  - Trang Danh mục lọc được bằng nút submit GET và chuyển trang bằng `<a rel="next">`.
  - Trang Sản phẩm thêm vào giỏ và bấm `Mua ngay` chuyển thẳng tới `/thanh-toan` hoạt động qua `<form method="post">`.
  - Trang Cẩm nang chuyển chuyên mục (`/chu-de/{slug}`) và tìm kiếm bài viết hoạt động bình thường.
  - Form Liên hệ & Báo giá gửi được qua fallback `admin-post.php`.
- [ ] Kiểm tra song song 1 SKU có giá (`DRV-8801M`) và 1 SKU không giá (`DRV-3612T`, `drv_price_m2 = 0`): hiển thị đúng nhánh `priced` vs `quote` trên Card, PDP và BOQ (`D-20`).

### 6.2 Cổng Accessibility (WCAG 2.2 AA)
- [ ] `<html lang="vi">`, có `.drv-skip-link` đầu `<body>` nhảy đúng tới `#main-content` trên mọi template (kể cả PDP từng bị thiếu trong prototype).
- [ ] Mọi nút chỉ có icon (`search-toggle`, `dialog-close`, `copy-inline`, stepper `+/-`, xóa dòng giỏ hàng) đều có `aria-label` tiếng Việt rõ nghĩa; mọi `<i class="ph ...">` trang trí có `aria-hidden="true"`.
- [ ] Mọi `<input>` / `<select>` / `<textarea>` đều có `<label for="...">` hiển thị hoặc `.u-sr-only`; thông báo lỗi có `role="alert"` và nối `aria-describedby`.
- [ ] Mở `<dialog>` (Mega/Mobile Menu, Mini-cart, Filter Sheet, Quote Modal, Tile Picker): khóa cuộn nền, nhấn `Escape` đóng được và trả focus chính xác về nút vừa mở.
- [ ] Độ tương phản màu đạt chuẩn `01 §2.2` (0 lỗi trên axe DevTools / Lighthouse Accessibility = `100`); kiểm tra chế độ `prefers-reduced-motion: reduce` tắt sạch hiệu ứng canvas/stagger/odometer.

### 6.3 Cổng SEO, Đường dẫn & GEO Schema
- [ ] Rà toàn bộ link trong Header, Mega Menu, Mobile Drawer, Footer, Breadcrumb và Sub-Silo Tiles: **0 link chứa dấu `/` cuối**, **0 link chứa `?param` lọc**, **0 link sót đuôi `.html`**.
- [ ] Kiểm tra `<meta name="robots">` và `<link rel="canonical">` khớp chính xác ma trận `05 §6` & `09 §3.4` (`M-1` đến `M-13`).
- [ ] Kiểm tra Google Rich Results Test trên HTML render thực tế: chỉ có **1 `BreadcrumbList` duy nhất**, `FAQPage` khớp từng chữ với khối `<details class="drv-faq__item">` trên trang, `Product` của SKU `quote` không xuất `Offer` giá `0đ`.

### 6.4 Cổng Hiệu suất & LiteSpeed Cache (`08 §1`, `08 §7`)
- [ ] Tổng kích thước bundle thực tế (gzip) kiểm tra trong `npm run build` nằm trong ngân sách: CSS inline critical `≤ 14KB`, JS trang Blog (`core` + `blog`) `≤ 10KB`, JS trang Home `≤ 12KB` (trước lazy canvas), JS Catalog `≤ 15KB`, JS PDP `≤ 18KB` (trước khi bấm zoom PhotoSwipe).
- [ ] Đúng **1 ảnh LCP duy nhất** có `fetchpriority="high"` và không `loading="lazy"`; mọi `<img>` khác có đủ `width`, `height`, `decoding="async"`, `loading="lazy"`.
- [ ] Đã gỡ sạch CSS/JS mặc định không dùng của WooCommerce (`woocommerce-general`, `woocommerce-layout`, `wc-blocks-style`, `wc-cart-fragments`) trên Home, Catalog, PDP và Blog.
- [ ] Kiểm tra dưới LiteSpeed Page Cache (`x-litespeed-cache: hit`): HTML cache không dính số lượng giỏ hàng hay danh sách báo giá của khách khác; form báo giá/liên hệ trên trang đã cache quá 12 giờ vẫn lấy được fresh nonce qua `/wp-json/dravit/v1/nonce` và gửi thành công.
