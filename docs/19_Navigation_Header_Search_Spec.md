# 19. Đặc tả Điều hướng, Header, Mega Menu 4+1 & Tìm kiếm Tức thì

> **Quyết định tham chiếu:** `D-01`, `D-04`, `D-08`, `D-11`, `D-12`, `D-17`, `D-18`, `D-20`, `D-21`, `D-25`, `D-26`, `D-28`, `D-29`, `D-30` ([00_Project_Brief_Decisions.md](00_Project_Brief_Decisions.md)).
> **Liên kết tài liệu:** [01_Brand_Design_System.md](01_Brand_Design_System.md) · [03_Catalog_Data_Model.md](03_Catalog_Data_Model.md) · [07_UI_UX_Page_Specs.md](07_UI_UX_Page_Specs.md) · [14_Data_Dictionary_And_Settings.md](14_Data_Dictionary_And_Settings.md) · [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md).
> **Module phụ trách:** `Dravit\Core\Menu\MenuSetup`, `Dravit\Core\Menu\MegaMenuRenderer`, `Dravit\Core\Menu\DropdownWalker`, `Dravit\Core\Menu\MobileAccordionWalker`, `Dravit\Catalog\SearchController` (`Dravit\Core\SearchController`), `assets/src/js/core.js`, `assets/src/js/search.js`.

---

## 1. Kiến trúc Header 2 Tầng & Hành vi Sticky (`D-18`, `D-29`)

### 1.1 Phân tách Template Shell (`header.php` & `template-parts/header/*`)

Mọi thành phần shell toàn cục render 100% bằng PHP SSR (xóa bỏ hoàn toàn cơ chế nạp partial phía client `<drv-site-header>` và thanh `<nav class="drv-proto-bar">` của bản HTML prototype).

| Đường dẫn file trong theme `dravit` | Nhiệm vụ | Điều kiện xuất hiện |
|---|---|---|
| `header.php` | `<head>`, Skip-link `#main-content`, kiểm tra ngữ cảnh trang để nạp Header chuẩn hoặc Topbar tối giản | Mọi trang frontend |
| `template-parts/header/site-header.php` | Khung `<header class="drv-header" data-drv-header>` gồm Tier 1 + Tier 2 + vùng neo `#drv-mega-menu` | Mọi trang **trừ** trang `/thanh-toan` đang nhập form |
| `template-parts/header/mega-menu.php` | Mảnh HTML Mega Menu 4+1 (nạp qua cache `drv_mega_html`) | Nhúng trong `site-header.php` |
| `template-parts/header/mobile-drawer.php` | `<dialog id="drv-mobile-menu-drawer">` chứa Mobile Category Accordion + menu phụ + CTA liên hệ | Cuối `footer.php` (trừ `/thanh-toan`) |
| `template-parts/cart/mini-cart-drawer.php` | `<dialog id="drv-minicart-drawer">` hiển thị nhanh giỏ hàng đồng bộ Store API | Cuối `footer.php` (trừ `/gio-hang` và `/thanh-toan`) |
| `template-parts/checkout/topbar.php` | Header tối giản chống xao nhãng trên trang Thanh toán (`D-30`) | Khi `is_checkout() && !is_wc_endpoint_url('order-received')` |

> **LiteSpeed Cache Tag:** Khi render `site-header.php` và `footer.php`, gọi `do_action('litespeed_tag_add', 'drv_global_shell')` để có thể xóa cache toàn vỏ giao diện ngay khi cập nhật menu hoặc `drv_settings`.

### 1.2 Cấu trúc Tầng 1 (`.drv-header__top`) & Hành vi Compact Zero-CLS

**Kích thước & Tokens CSS (`@layer layout`):**
- Chiều cao mặc định Desktop: `--header-top-h: 68px` (Mobile `< 1024px`: `60px`).
- Khi người dùng cuộn xuống quá `120px`: gắn class `.is-compact` lên `.drv-header`, thu chiều cao Tier 1 xuống `--header-top-h-compact: 56px` bằng transition `transform` / `padding-block` (`180ms var(--ease-out-expo)`). Khi cuộn ngược lên `≥ 16px` hoặc về đầu trang (`scrollY < 40`): gỡ `.is-compact`.
- **Chống nhảy bố cục (Zero CLS < 0.05):**
  - Thẻ `<header class="drv-header">` dùng `position: sticky; top: 0; z-index: var(--z-header)` (`40`).
  - Bao ngoài hoặc phần tử giữ chỗ đặt cố định `min-height: var(--header-h-desktop)` (`112px` = `68px + 44px` trên Desktop; `60px` trên Mobile).
  - Biến động `--header-h-current` cập nhật trên `:root` (`112px` mặc định → `100px` khi `.is-compact`) để đồng bộ `scroll-margin-top: calc(var(--header-h-current) + 16px)` cho các neo `<h2>/<h3>` và vị trí sticky sidebar (`top: calc(var(--header-h-current) + var(--s-4))`), tuyệt đối không đẩy giật luồng tài liệu bên dưới.

**Bố cục các cụm phần tử trên Tier 1 (`max-width: 1380px`):**

```html
<header class="drv-header" id="site-header" data-drv-header>
  <div class="drv-header__top">
    <div class="l-container drv-header__top-inner">
      <!-- 1. Nút mở Drawer Menu (chỉ hiện < 1024px, vùng chạm 44x44px) -->
      <button type="button" class="drv-header__burger"
              data-drv-action="dialog-open"
              data-drv-target="#drv-mobile-menu-drawer"
              aria-controls="drv-mobile-menu-drawer"
              aria-expanded="false"
              aria-label="Mở danh mục sản phẩm và điều hướng">
        <i class="ph ph-list" aria-hidden="true"></i>
      </button>

      <!-- 2. Logo thương hiệu (D-12: WebP 1x/2x, khai báo cứng width/height chống CLS) -->
      <a class="drv-header__logo" href="<?= esc_url(home_url('/')); ?>" aria-label="DRAVIT - Trang chủ">
        <img src="<?= esc_url($logo_1x); ?>"
             srcset="<?= esc_url($logo_1x); ?> 1x, <?= esc_url($logo_2x); ?> 2x"
             width="150" height="44"
             alt="Gạch ốp lát DRAVIT"
             decoding="async"
             <?= $is_text_only_above_fold ? 'fetchpriority="high"' : ''; ?>>
      </a>

      <!-- 3. Ô Tìm kiếm Tức thì (.drv-search, max-width: 440px, cao 38px) -->
      <?php get_template_part('template-parts/header/search-bar'); ?>

      <!-- 4. Cụm tiện ích bên phải (.drv-header__actions) -->
      <div class="drv-header__actions">
        <!-- 4a. Hotline gọi nhanh (Desktop >= 1200px) -->
        <a class="drv-header__hotline" href="tel:<?= esc_attr($hotline_tel); ?>">
          <i class="ph ph-phone-call" aria-hidden="true"></i>
          <span class="drv-header__hotline-meta">
            <small>Tư vấn kỹ thuật</small>
            <strong><?= esc_html($hotline_display); ?></strong>
          </span>
        </a>

        <!-- 4b. Nút Danh sách Báo giá BOQ (D-23, D-29) -->
        <a class="drv-header__tool" href="<?= esc_url(home_url('/danh-sach-bao-gia')); ?>"
           aria-label="Danh sách báo giá công trình">
          <i class="ph ph-file-text" aria-hidden="true"></i>
          <span class="drv-header__tool-label">DS Báo giá</span>
          <span class="drv-badge-count" data-drv-quote-count data-quote-badge-count hidden>0</span>
        </a>

        <!-- 4c. Nút Giỏ hàng mở Mini-cart Drawer (D-29 - khắc phục thiếu sót của prototype) -->
        <button type="button" class="drv-header__tool drv-header__cart-btn"
                data-drv-action="dialog-open"
                data-drv-target="#drv-minicart-drawer"
                aria-controls="drv-minicart-drawer"
                aria-expanded="false"
                aria-label="Xem giỏ hàng">
          <i class="ph ph-shopping-cart-simple" aria-hidden="true"></i>
          <span class="drv-header__tool-label">Giỏ hàng</span>
          <span class="drv-badge-count drv-badge-count--teal" data-drv-cart-count hidden>0</span>
        </button>

        <!-- 4d. Nút Trợ lý AI (D-25: CHỈ render khi drv_settings.features.ai_enabled === true) -->
        <?php if ((bool) drv_setting('features.ai_enabled', false)) : ?>
          <a class="drv-btn drv-btn--sm drv-btn--ai"
             href="<?= esc_url(home_url('/tro-ly-ai-tim-gach')); ?>">
            <i class="ph ph-sparkle" aria-hidden="true"></i>
            <span>Trợ lý AI</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Tầng 2 (.drv-header__nav) chỉ hiển thị trên Desktop >= 1024px -->
  <?php get_template_part('template-parts/header/nav-bar'); ?>
</header>
```

**Quy tắc chi tiết cho các nút trên Tier 1:**
1. **`fetchpriority="high"` trên Logo:** Chỉ gắn khi trang hiện tại **không** có ảnh LCP lớn trong viewport đầu tiên (ví dụ trang Giỏ hàng, BOQ, Chính sách, Tìm kiếm). Trên Trang chủ (`front-page.php`), Danh mục có banner, PDP và bài chi tiết Cẩm nang, `fetchpriority="high"` dành độc quyền cho ảnh LCP chính để không tranh băng thông HTTP/3 đầu tiên.
2. **Badge DS Báo giá (`[data-drv-quote-count][data-quote-badge-count]`):** Trong HTML SSR được cache, luôn có thuộc tính `hidden` và nội dung `0`. Ngay khi `core.js` khởi chạy, `quote-store.js` đọc `localStorage['drv_quote_v2']`, đếm tổng số slot có `product_id > 0`; nếu `> 0` thì cập nhật số và gỡ `hidden`. Lắng nghe sự kiện `drv:quote-updated` và `storage` (đồng bộ đa tab trình duyệt).
3. **Badge Giỏ hàng (`[data-drv-cart-count]`):** Trong HTML SSR luôn `hidden`. `core.js` kiểm tra cookie `woocommerce_items_in_cart`:
   - Không có cookie (khách mới chưa thêm giỏ): không tạo bất kỳ request REST nào.
   - Có cookie `> 0`: gọi `GET /wp-json/wc/store/v1/cart` trong `requestIdleCallback`, hiển thị tổng số dòng sản phẩm (`cart.items.length`) trên badge và phát `drv:cart-updated`.
   - Khi người dùng bấm nút Giỏ hàng (`data-drv-action="dialog-open" data-drv-target="#drv-minicart-drawer"`, hoặc alias `cart-drawer-open`), handler kiểm tra nếu `target === '#drv-minicart-drawer'` sẽ tự động `import('./components/mini-cart.js')` lần đầu để render danh sách sản phẩm quy đổi `viên · thùng · m²` trước/trong khi mở `<dialog>`.
4. **Cờ Trợ lý AI (`D-25`):** Ở Phase 1, `drv_setting('features.ai_enabled', false)` mặc định bằng `false`. PHP **không xuất DOM** nút Trợ lý AI (không dùng `display: none`) để tránh rò rỉ internal link tới trang đang `noindex`.

### 1.3 Cấu trúc Tầng 2 (`.drv-header__nav` - Cao `44px`, Lưới ron `1px`)

Hiển thị trên màn hình `≥ 1024px`, phân tách với Tier 1 và nội dung trang bằng đường ron `border-top: 1px solid var(--c-navy-200); border-bottom: 1px solid var(--c-navy-200)`, nền `--c-white`, chữ `--c-navy-900` (`#083A4F`, `font-weight: 600`, `font-size: 14px`).

```html
<div class="drv-header__nav" aria-label="Điều hướng danh mục chính">
  <div class="l-container drv-header__nav-inner">
    <!-- Cụm trái: Nút Mega Menu + Các mục Silo chính -->
    <div class="drv-header__nav-primary">
      <button type="button"
              class="drv-mega-trigger"
              data-drv-action="mega-toggle"
              aria-expanded="false"
              aria-controls="drv-mega-menu">
        <i class="ph ph-squares-four" aria-hidden="true"></i>
        <span>Tất cả gạch</span>
        <i class="ph ph-caret-down drv-mega-trigger__caret" aria-hidden="true"></i>
      </button>

      <nav class="drv-nav" aria-label="Nhóm sản phẩm trọng tâm">
        <ul class="drv-nav__list">
          <!-- Render từ DropdownWalker hoặc cấu hình Silo tĩnh (§2.5):
               Gạch lát nền | Gạch ốp tường | Theo không gian (Dropdown cấp 2 + Flyout cấp 3) |
               Vân đá & Giả gỗ | Đá nung kết | Gạch ưu đãi -->
        </ul>
      </nav>
    </div>

    <!-- Cụm phải: Menu tiện ích (nav_menu location: primary_utility) -->
    <nav class="drv-nav-utility" aria-label="Thông tin và showroom">
      <!-- Mặc định gồm: Bộ sưu tập (/bo-suu-tap/dravit-signature), Cẩm nang (/cam-nang), Liên hệ & Showroom (/lien-he) -->
    </nav>
  </div>

  <!-- Khung Mega Menu 4+1 neo sát đáy Tier 2 -->
  <?php echo \Dravit\Core\Menu\MegaMenuRenderer::render_desktop(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside renderer ?>
</div>
```

**Quy tắc màu sắc & trạng thái tương tác trên Tier 2 (`D-18`):**
- Trạng thái nghỉ: nền trong suốt, chữ `#083A4F`.
- Trạng thái `:hover`, `:focus-visible`, hoặc đang mở panel (`[aria-expanded="true"]`, `.is-open`): nền `#083A4F` (`--c-navy-900`), chữ `#FFFFFF` (`--c-white`), icon caret xoay `180deg` bằng `transform: rotate(180deg)`.
- Mục đang khớp URL hiện tại (`.is-current` được `core.js` đánh dấu theo `location.pathname` để an toàn với Page Cache): gạch chân dưới `2px solid var(--c-teal-500)`.

### 1.4 Topbar Tối giản trên Trang Thanh toán (`template-parts/checkout/topbar.php`, `D-30`)

Trên trang `/thanh-toan` (trừ màn hình `order-received` sau khi đặt thành công), thay thế hoàn toàn Header 2 tầng, Mega Menu và thanh Mobile Bottom Bar bằng Topbar 1 tầng cao `60px` để tối đa hóa tỷ lệ hoàn tất đơn:

```html
<header class="drv-checkout-topbar">
  <div class="l-container drv-checkout-topbar__inner">
    <a class="drv-checkout-topbar__back" href="<?= esc_url(wc_get_cart_url()); ?>">
      <i class="ph ph-arrow-left" aria-hidden="true"></i>
      <span>Quay lại giỏ hàng</span>
    </a>

    <a class="drv-checkout-topbar__logo" href="<?= esc_url(home_url('/')); ?>" aria-label="DRAVIT">
      <img src="<?= esc_url($logo_1x); ?>"
           srcset="<?= esc_url($logo_1x); ?> 1x, <?= esc_url($logo_2x); ?> 2x"
           width="132" height="38" alt="DRAVIT">
    </a>

    <a class="drv-checkout-topbar__support" href="tel:<?= esc_attr($hotline_tel); ?>">
      <i class="ph ph-headset" aria-hidden="true"></i>
      <span>Hỗ trợ đặt hàng: <strong><?= esc_html($hotline_display); ?></strong></span>
    </a>
  </div>
</header>
```

---

## 2. Nguồn Dữ liệu Menu & Walkers (`inc/Core/Menu/*`, `D-29`)

### 2.1 Các Vị trí `register_nav_menus` Chuẩn

Đăng ký trong `Dravit\Core\Menu\MenuSetup::register()`:

| Location slug | Tên hiển thị trong `WP Admin -> Giao diện -> Menu` | Khu vực hiển thị | Độ sâu tối đa (`depth`) |
|---|---|---|---|
| `primary_silo_nav` | `Thanh điều hướng ngang - Nhóm gạch chính (Tầng 2 trái)` | Cạnh nút `"Tất cả gạch"` trên Tier 2 (có fallback tự động nếu Admin chưa gán menu) | `3` (Cấp 1 ngang → Cấp 2 Dropdown → Cấp 3 Flyout) |
| `primary_utility` | `Thanh điều hướng ngang - Tiện ích phải (Tầng 2 phải)` | Cụm phải Tier 2 & phần dưới Mobile Drawer (`Cẩm nang`, `Liên hệ & Showroom`) | `1` |
| `footer_col_2` | `Footer Cột 2 - Danh mục gạch chủ lực` | Cột 2 Footer (8 link danh mục trọng tâm) | `1` |
| `footer_col_3` | `Footer Cột 3 - Dịch vụ & Công cụ dự toán` | Cột 3 Footer (`BOQ`, `Cẩm nang`, `Giới thiệu`, `Liên hệ`) | `1` |
| `footer_col_4` | `Footer Cột 4 - Chính sách & Pháp lý` | Cột 4 Footer (Đổi trả, Vận chuyển, Thanh toán, Bảo mật, Điều khoản) | `1` |

> **URL Sanitizer trên Menu Admin (`wp_update_nav_menu_item`):** Hook kiểm tra mọi link nội bộ cùng domain trong `nav_menu`: tự động cắt dấu `/` cuối (`rtrim($url, '/')`, trừ trang chủ `'/'`) theo `D-21`, và hiển thị cảnh báo Admin Notice nếu biên tập viên dán nhầm URL có query `?param` vào menu điều hướng chính.

### 2.2 Tại sao Mega Menu 4+1 & Mobile Category Accordion Sinh Tự động từ `config/collections.php` (`D-29`)

Bản HTML prototype (`partials/header.html`) mắc 3 lỗi kiến trúc nghiêm trọng: (1) chèn hàng loạt link query không tồn tại (`?nhom=`, `?loai=`, `?van=`, `?do-day=20mm`, `?be-mat=chong-tron`) làm thất thoát PageRank vào các URL `noindex`, (2) bỏ sót nhiều collection trong 28 Smart Collections chuẩn, và (3) gõ cứng số lượng sản phẩm giả.

Để triệt tiêu 100% sai lệch giữa con người và cấu trúc SEO Silo:
1. **Nguồn dữ liệu duy nhất:** Cả **Mega Menu 4+1** (Desktop) và **Khối Danh mục Gạch trong Mobile Drawer** đều được class `Dravit\Core\Menu\MegaMenuRenderer` sinh tất định từ `config/collections.php` kết hợp `product_cat` term meta và bộ đếm `drv_term_stats_{term_id}`.
2. **Bảo toàn 100% URL Tĩnh (`index, follow`):** Mọi thẻ `<a href>` bên trong Mega Menu chỉ trỏ tới **đúng 28 URL tĩnh của Smart Collections** (không dấu `/` cuối, **0 link chứa `?param`**).
3. **Đồng bộ nhãn & thứ tự:** Khi thêm sản phẩm hoặc chạy `CollectionSync`, số lượng mẫu (`$term->count`) và giá khởi điểm (`price_min_m2`) trên Mega Menu tự cập nhật mà Admin không cần sửa menu thủ công.

**Bảng ánh xạ chính xác 4 Cột Danh mục trong Mega Menu (`7 + 8 + 7 + 5 = 27` collection con + 1 gốc `/gach` = đủ 28 URL):**

| Cột Mega Menu | Tiêu đề cột (`drv_group`) | Danh sách Slug Tĩnh theo đúng thứ tự hiển thị | Link chân cột |
|---|---|---|---|
| **Cột 1** | **Theo công năng** (`function` · 7 mục) | `/gach-lat-nen` · `/gach-op-tuong` · `/gach-trang-tri` · `/gach-bong` · `/gach-bong-gio` · `/gach-the` · `/gach-uu-dai` *(gắn badge KM)* | `/gach` (*Xem toàn bộ kho gạch*) |
| **Cột 2** | **Theo không gian** (`space` · 8 mục) | `/gach-phong-khach` · `/gach-nha-tam` · `/gach-phong-bep` · `/gach-san-vuon` · `/gach-ngoai-troi` · `/gach-mat-tien` · `/gach-phong-ngu` · `/gach-ho-boi` | - |
| **Cột 3** | **Vân & Chất liệu** (`material` · 7 mục) | `/gach-gia-go` · `/gach-van-da` · `/gach-terrazzo` · `/gach-van-xi-mang` · `/gach-mosaic` · `/da-nung-ket` · `/gach-porcelain` | - |
| **Cột 4** | **Theo kích thước** (`size` · 5 mục) | `/gach-60x60` · `/gach-80x80` · `/gach-30x60` · `/gach-60x120` · `/gach-15x80-20x100` | `/danh-sach-bao-gia` (*Tính gạch theo phòng BOQ*) |

> **Xử lý Collection trống ở giai đoạn đầu nhập liệu:** Nếu một collection Phase 2 đang có `$term->count === 0`, vẫn giữ link tĩnh để đảm bảo cấu trúc Silo 28 trang (trang đích hiển thị trạng thái chờ cập nhật mẫu + nhận báo giá theo `07 §3`), nhưng ẩn con số `(0)` bên cạnh tên mục để giao diện gọn đẹp.

### 2.3 Cột 5 Live Dynamic Preview & Thanh Bottom Utility Strip (`D-18`)

Mega Menu áp dụng bố cục lưới 5 cột chia đường ron `1px solid var(--c-navy-200)` (`grid-template-columns: repeat(4, minmax(0, 1fr)) 300px`), bên dưới là thanh tiện ích ngang `.drv-mega__bottom`.

#### A. Hợp đồng Dữ liệu Preview trên Từng Link Cột 1–4 & Khung Cột 5

Mỗi thẻ `<a class="drv-mega__link">` ở Cột 1–4 mang bộ thuộc tính `data-drv-preview-*` được PHP nạp sẵn từ term meta của `product_cat` và cache thống kê `drv_term_stats_{term_id}`:

| Thuộc tính trên `<a>` | Nguồn lấy dữ liệu trong PHP (`MegaMenuRenderer`) | Hành vi hiển thị ở Cột 5 (`.drv-mega-preview`) |
|---|---|---|
| `data-drv-preview-img` | Ưu tiên term meta `drv_hero_image` → fallback `drv_thumb_id` / `thumbnail_id` (size `medium_large` `600×400` WebP) → fallback ảnh mặc định của nhóm `drv_group` | Cập nhật `src` ảnh minh họa tỉ lệ `3:2` (chỉ nạp ảnh mặc định lúc đầu; các ảnh hover tiền tải nhẹ khi mở Mega Menu lần đầu) |
| `data-drv-preview-badge` | Term meta `drv_menu_badge` (nếu trống dùng tên nhóm, vd `"Không gian"`, `"Vân & Chất liệu"`) | Cập nhật nhãn eyebrow `[data-mega-out="badge"]` |
| `data-drv-preview-title` | `$term->name` (vd `"Gạch Phòng Khách"`) | Cập nhật tiêu đề `[data-mega-out="title"]` |
| `data-drv-preview-desc` | Term meta `drv_menu_desc` → fallback cắt gọn 110 ký tự từ `drv_intro` | Cập nhật mô tả ngắn 2 dòng `[data-mega-out="desc"]` |
| `data-drv-preview-price` | Đọc `drv_term_stats_{term_id}`: nếu `price_min_m2 > 0` xuất `"Từ 245.000đ/m² · 48 mẫu"`; nếu danh mục chỉ có hàng dự án (`price_min_m2 === 0` và `count > 0`) xuất `"Báo giá công trình · {count} mẫu"`; nếu `count === 0` xuất `"Nhận đặt mẫu theo yêu cầu"` | Cập nhật dòng thông tin giá/mẫu `[data-mega-out="price"]` |
| `data-drv-preview-url` | `home_url('/' . $term->slug)` (không dấu `/` cuối) | Cập nhật `href` của nút CTA `"Xem danh mục"` trong Cột 5 |

**Markup chuẩn của Cột 5 & Thanh `.drv-mega__bottom`:**

```html
<!-- Cột 5: Live Dynamic Preview (khởi tạo mặc định theo /gach-phong-khach hoặc /gach-van-da) -->
<aside class="drv-mega__preview" aria-live="off" data-mega-preview>
  <a class="drv-mega__preview-media" href="/gach-phong-khach" data-mega-out="link" tabindex="-1">
    <img src="<?= esc_url($default_preview['img']); ?>"
         width="276" height="184" loading="lazy" decoding="async"
         alt="" data-mega-out="img">
    <span class="drv-mega__preview-badge" data-mega-out="badge">Không gian trọng tâm</span>
  </a>
  <div class="drv-mega__preview-body">
    <h3 class="drv-mega__preview-title" data-mega-out="title">Gạch Phòng Khách</h3>
    <p class="drv-mega__preview-desc" data-mega-out="desc">
      Khổ 80x80, 60x120 vân đá Marble và xi măng hiện đại, kiểm soát đồng lô cho mảng sàn rộng.
    </p>
    <div class="drv-mega__preview-meta" data-mega-out="price">Từ 285.000đ/m² · 64 mẫu</div>
    <a class="drv-mega__preview-cta" href="/gach-phong-khach" data-mega-out="cta">
      <span>Khám phá ngay</span>
      <i class="ph ph-arrow-right" aria-hidden="true"></i>
    </a>
  </div>
</aside>

<!-- Thanh tiện ích chân Mega Menu (.drv-mega__bottom) đọc từ drv_settings -->
<div class="drv-mega__bottom">
  <div class="drv-mega__bottom-item">
    <i class="ph ph-storefront" aria-hidden="true"></i>
    <span>Đối chiếu mẫu thật dưới đèn <strong>3000K / 6500K</strong> tại Showroom:
      <a href="/lien-he">Xem bản đồ & đặt lịch hẹn</a>
    </span>
  </div>
  <div class="drv-mega__bottom-links">
    <a href="/danh-sach-bao-gia">
      <i class="ph ph-calculator" aria-hidden="true"></i>
      <span>Lập bảng dự toán BOQ theo phòng</span>
    </a>
    <a href="<?= esc_url($zalo_url); ?>" target="_blank" rel="noopener">
      <i class="ph ph-chat-circle-dots" aria-hidden="true"></i>
      <span>Gửi mặt bằng qua Zalo (<?= esc_html($hotline_display); ?>)</span>
    </a>
  </div>
</div>
```

> Khi người dùng `pointerenter` hoặc `focus` vào bất kỳ `.drv-mega__link` nào ở Cột 1–4 (debounce nhẹ `50ms` để chống nhấp nháy khi rê chuột chéo qua cột), JS cập nhật `textContent` / `src` / `href` của các phần tử `[data-mega-out]` với hiệu ứng mờ nhẹ `opacity` trên ảnh (`140ms`).

### 2.4 Fragment Caching `drv_mega_html` & Cơ chế Xóa Cache Tự động

Để không phải lặp truy vấn 28 `product_cat` + term meta trên mọi trang khi cache trang bị miss:

```php
<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

defined('ABSPATH') || exit;

final class MegaMenuRenderer
{
    public const CACHE_KEY = 'drv_mega_html';
    public const CACHE_TTL = DAY_IN_SECONDS; // 24 giờ (86400s)

    public static function register(): void
    {
        $purge = [self::class, 'purge_cache'];
        add_action('created_product_cat', $purge);
        add_action('edited_product_cat', $purge);
        add_action('delete_product_cat', $purge);
        add_action('drv/catalog/synced', $purge);
        add_action('update_option_drv_settings', $purge);
        add_action('wp_update_nav_menu', $purge);
    }

    /**
     * Lưu chung mảng ['desktop' => string, 'mobile' => string] trong 1 transient duy nhất.
     * Tuyệt đối KHÔNG render class active theo URL hiện tại vào chuỗi cache.
     */
    public static function get_fragments(): array
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['desktop'], $cached['mobile'])) {
            return $cached;
        }

        $fragments = [
            'desktop' => self::build_desktop_html(),
            'mobile'  => self::build_mobile_accordion_html(),
        ];
        set_transient(self::CACHE_KEY, $fragments, self::CACHE_TTL);

        return $fragments;
    }

    public static function purge_cache(): void
    {
        delete_transient(self::CACHE_KEY);
        do_action('litespeed_purge', 'drv_global_shell');
    }
}
```

### 2.5 Hợp đồng `DropdownWalker`, `MobileAccordionWalker` & Custom Menu Fields

#### A. Custom Fields cho Mục Menu (`_drv_icon`, `_drv_badge`)
Class `Dravit\Core\Menu\MenuSetup` móc vào `wp_nav_menu_item_custom_fields` và `wp_update_nav_menu_item` để thêm 2 trường gọn trong màn hình sửa Menu Admin (không dùng ACF):
- `_drv_icon`: Tên icon Phosphor trong bộ subset tự host (ví dụ `ph-sparkle`, `ph-book-open`, `ph-map-pin`). Validate bằng regex `/^(ph(-fill|-bold)?\s+)?ph-[a-z0-9-]+$/`.
- `_drv_badge`: Nhãn nổi tối đa `15` ký tự (`sanitize_text_field`, ví dụ `"Ưu đãi"`, `"Mới"`).

#### B. `DropdownWalker` (Menu Ngang Desktop Cấp 2 + Flyout Cấp 3, `D-18`)
Dùng cho `primary_silo_nav` trên Tier 2 khi mục điều hướng có con cấp 2 và cấp 3:

```html
<li class="drv-nav__item drv-nav__item--has-sub">
  <div class="drv-nav__item-head">
    <a class="drv-nav__link" href="/gach-lat-nen">
      <i class="ph ph-squares-four" aria-hidden="true"></i>
      <span>Gạch lát nền</span>
    </a>
    <button type="button" class="drv-nav__sub-toggle"
            aria-expanded="false"
            aria-label="Mở menu con Gạch lát nền">
      <i class="ph ph-caret-down" aria-hidden="true"></i>
    </button>
  </div>

  <!-- Dropdown Cấp 2 -->
  <ul class="drv-dropdown" hidden>
    <li class="drv-dropdown__item drv-dropdown__item--has-flyout">
      <a class="drv-dropdown__link" href="/gach-phong-khach">
        <span>Phòng khách</span>
        <i class="ph ph-caret-right" aria-hidden="true"></i>
      </a>
      <!-- Flyout Cấp 3 bung ngang sang phải (tự lật sang trái nếu tràn mép phải viewport) -->
      <ul class="drv-dropdown__flyout">
        <li><a class="drv-dropdown__link" href="/gach-80x80">Gạch khổ 80x80</a></li>
        <li><a class="drv-dropdown__link" href="/gach-60x120">Gạch khổ 60x120</a></li>
        <li><a class="drv-dropdown__link" href="/gach-van-da">Gạch vân đá Marble</a></li>
      </ul>
    </li>
    <li class="drv-dropdown__item">
      <a class="drv-dropdown__link" href="/gach-nha-tam"><span>Nhà tắm & WC</span></a>
    </li>
  </ul>
</li>
```
- Tách bạch `<a class="drv-nav__link">` (click đi thẳng tới trang tĩnh) và `<button class="drv-nav__sub-toggle">` (dành cho người dùng bàn phím / thiết bị cảm ứng màn hình lớn mở menu con mà không kích hoạt điều hướng nhầm).

#### C. `MobileAccordionWalker` & Khối Danh mục Mobile (`<details class="drv-mob-accordion">`)
Bên trong `<dialog id="drv-mobile-menu-drawer">`, dùng phần tử native `<details>` + `<summary>` để hoạt động mượt ngay cả khi mạng chậm chưa chạy xong JS, vùng chạm tối thiểu `min-height: 44px`:

```html
<details class="drv-mob-accordion" open>
  <summary class="drv-mob-accordion__sum">
    <span class="drv-mob-accordion__title">Theo không gian</span>
    <i class="ph ph-caret-down drv-mob-accordion__icon" aria-hidden="true"></i>
  </summary>
  <ul class="drv-mob-accordion__list">
    <!-- Nếu mục cha là 1 URL đích (trong MobileAccordionWalker), luôn chèn dòng đầu "Xem tất cả {Tên mục}" -->
    <li><a class="drv-mob-accordion__link" href="/gach-phong-khach">Gạch phòng khách</a></li>
    <li><a class="drv-mob-accordion__link" href="/gach-nha-tam">Gạch nhà tắm</a></li>
    <!-- ... đủ 8 không gian tĩnh ... -->
  </ul>
</details>
```
- JS gắn hiệu ứng đóng mở hoặc chế độ single-open tùy chọn (khi mở 1 nhóm `<details data-mob-group="cats">`, tự đóng các nhóm anh em khác để danh sách không trôi quá dài trên màn hình điện thoại).

---

## 3. Tìm kiếm Tức thì 2 Cột (`Catalog\SearchController` & `assets/src/js/search.js`)

### 3.1 Progressive Enhancement & Template SSR `search.php`

Mọi thao tác tìm kiếm bắt đầu từ `<form>` chuẩn HTML để khi nhấn `Enter` hoặc khi tắt JS, trình duyệt điều hướng về trang kết quả SSR:

```html
<div class="drv-search" data-drv-search>
  <form class="drv-search__form" role="search" method="get" action="<?= esc_url(home_url('/')); ?>">
    <input type="hidden" name="post_type" value="product">
    <label class="sr-only" for="drv-search-input">Tìm gạch theo mã SKU, kích thước hoặc không gian</label>
    <i class="ph ph-magnifying-glass drv-search__icon" aria-hidden="true"></i>
    <input id="drv-search-input"
           class="drv-search__input"
           type="search"
           name="s"
           value="<?= esc_attr(is_search() && get_query_var('post_type') === 'product' ? get_search_query() : ''); ?>"
           placeholder="Tìm gạch 80x80, terrazzo, nhà tắm, mã DRV..."
           autocomplete="off"
           autocapitalize="off"
           spellcheck="false"
           role="combobox"
           aria-autocomplete="list"
           aria-expanded="false"
           aria-controls="drv-search-panel"
           data-search-input>
    <button type="button" class="drv-search__clear" data-search-clear hidden aria-label="Xóa từ khóa">
      <i class="ph ph-x-circle" aria-hidden="true"></i>
    </button>
  </form>

  <!-- Panel kết quả tức thì 2 cột (ẩn mặc định, JS dựng DOM an toàn bên trong) -->
  <div id="drv-search-panel" class="drv-search-panel" hidden data-search-panel>
    <div class="sr-only" aria-live="polite" data-search-status></div>
    <div class="drv-search-panel__body" data-search-body></div>
  </div>
</div>
```

**Đặc tả trang kết quả SSR `search.php` (`/?s=...&post_type=product`):**
- Meta `robots`: luôn ép **`noindex, follow`** qua Rank Math filter (`06 §2.1`) để chống spam truy vấn tìm kiếm nội bộ.
- Thực thi cùng thuật toán mở rộng không dấu / chuẩn hóa kích thước / khớp SKU như REST API thông qua hook `posts_clauses` khi `$query->is_main_query() && $query->is_search()`.
- Hiển thị lưới sản phẩm chuẩn `drv_ui('product-card')` kèm phân trang.
- **Trạng thái 0 kết quả (Empty State):** Không bao giờ để trang trắng; hiển thị thông báo rõ từ khóa vừa tìm, gợi ý 4 thẻ Silo trọng tâm (`drv_setting('collections_404')`: `/gach-lat-nen`, `/gach-op-tuong`, `/gach-phong-khach`, `/gach-80x80`), danh sách từ khóa phổ biến và hộp CTA gửi ảnh mẫu gạch cần tìm qua Zalo kỹ thuật.

### 3.2 Chiến lược Lazy-load `assets/src/js/search.js` & Trạng thái UI

Để giữ bundle `core.js` siêu nhẹ trên mọi trang:
1. **Dynamic Import khi tương tác:** `core.js` không bundle logic tìm kiếm. Chỉ khi người dùng `focusin` lần đầu vào `[data-search-input]` hoặc bấm nút `data-drv-action="search-toggle"` trên Mobile, `core.js` mới gọi `import('./search.js').then((m) => m.mountSearch(rootEl))`.
2. **Nhúng sẵn trạng thái rỗng từ `window.DRV_CONFIG.search`:** PHP truyền sẵn mảng `popular_keywords` và 6 `featured_silos` (chỉ ~600 bytes JSON) vào cấu hình inline để khi khách vừa click vào ô tìm kiếm (`q.length === 0`), panel mở tức thì `0ms` hiển thị:
   - **Cột trái:** *Danh mục xem nhiều* (6 link tĩnh `/gach-phong-khach`, `/gach-nha-tam`, `/gach-80x80`, `/gach-van-da`, `/gach-gia-go`, `/da-nung-ket`).
   - **Cột phải:** *Từ khóa tìm kiếm phổ biến* (`drv_settings.search.popular_keywords`: click vào chip sẽ điền vào ô input và chạy tìm kiếm ngay).
3. **Luồng gõ từ khóa (`q.trim().length >= 2`):**
   - Debounce **`200ms`**.
   - Hủy request đang bay dở bằng `AbortController` trước khi gửi `GET /wp-json/dravit/v1/search?q={encodeURIComponent(q)}&limit=6`.
   - Hiển thị trạng thái skeleton mảnh trong lần tải đầu nếu mạng trễ `> 150ms`.
   - **Chống XSS tuyệt đối:** `search.js` dựng phần tử bằng `document.createElement` + `textContent` (kèm hàm highlight đoạn khớp an toàn tách `TextNode` + `<mark>`), **cấm nối chuỗi HTML thô vào `innerHTML`**.
4. **Bố cục Panel Kết quả 2 Cột (`max-width: 680px` trên Desktop, full-screen sheet trên Mobile `< 768px`):**
   - **Cột trái (`220px`, nền `--c-ivory`):** *Danh mục phù hợp* (tối đa **6** collection tĩnh khớp từ khóa, hiển thị tên + số mẫu).
   - **Cột phải (`1fr`, nền `--c-white`):** *Sản phẩm gợi ý* (tối đa **6** SKU):
     - Ảnh vuông `48×48` WebP (`loading="lazy"`) · Tên sản phẩm (1 dòng ellipsis) · Dòng meta `SKU · Kích thước · Bề mặt`.
     - Cụm giá bên phải tuân thủ `D-20`:
       - Nếu `price_state === 'priced' || 'sale'`: hiện **`385.000đ/m²`** (kèm `<del>` nếu đang giảm).
       - Nếu `price_state === 'quote_only'`: hiện nhãn **`Liên hệ báo giá`** màu `--c-navy-900`.
   - **Chân panel:** Link `<a class="drv-search-panel__all" href="{res.view_all}">Xem tất cả {res.total} kết quả cho "{q}"</a>`.

### 3.3 Thuật toán Khớp Tiếng Việt Không Dấu, Mã SKU & Kích thước (`SearchController`)

Class `Dravit\Catalog\SearchController` (đăng ký alias hoặc kế thừa tại `Dravit\Core\SearchController` khớp bảng định tuyến [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md) §2) xử lý `GET /wp-json/dravit/v1/search`:

#### Bước 1: Chuẩn hóa Chuỗi Truy vấn (`Helpers::normalize_search_query(string $raw): array`)
1. Cắt tối đa `80` ký tự, `mb_strtolower($raw, 'UTF-8')`.
2. Bỏ dấu tiếng Việt (`remove_accents($str)` kết hợp `strtr($str, ['đ' => 'd', 'Đ' => 'd'])`), loại bỏ ký tự đặc biệt ngoài `a-z0-9.x×-\s`, gom khoảng trắng thừa.
3. **Chuẩn hóa thông minh kích thước gạch (mm → cm):**
   - Bắt biểu thức `/\b(\d{2,4})\s*[x×*]\s*(\d{2,4})\b/u`:
   - Nếu cả 2 chiều `>= 75` và chia hết cho `10` (hoặc dạng `75x300`), tự động sinh thêm token kích thước chuẩn theo cm của DRAVIT:
     - `800x800` hoặc `800 x 800` → sinh thêm `80x80`.
     - `600x1200` hoặc `1200x600` → sắp `min x max` thành `60x120`.
     - `75x300` → đổi thành `7-5x30` và `7,5x30`.
4. Tách mảng `$tokens` (tối đa 6 từ có nghĩa).

#### Bước 2: Khớp Cột Trái - Danh mục Tĩnh (`max 6` mục)
- Duyệt cấu hình 28 Smart Collections trong bộ nhớ từ `config/collections.php` (đối chiếu `$q_ascii` với tên không dấu, slug và danh sách từ đồng nghĩa khai báo sẵn, ví dụ gõ `"wc"`, `"toilet"`, `"ve sinh"` khớp `/gach-nha-tam`; gõ `"san go"` khớp `/gach-gia-go`; gõ `"khuyen mai"` khớp `/gach-uu-dai`).
- Gắn kèm `count` từ `drv_term_stats_{term_id}` và URL tĩnh không dấu `/` cuối.

#### Bước 3: Khớp Cột Phải - Sản phẩm (`max 6` mục, Xếp hạng Trọng số)
Truy vấn sử dụng bảng `wp_wc_product_meta_lookup` kết hợp cột chỉ mục tiền tính toán `_drv_search_index` (được tạo sẵn khi lưu sản phẩm tại `14 §2.3` gồm `"{sku_clean} {title_ascii} {color_ascii} {series} {terms_ascii}"`) bằng `$wpdb->prepare()`:

```sql
SELECT p.ID,
  (CASE
    WHEN lkp.sku = %s THEN 100
    WHEN lkp.sku LIKE %s THEN 85
    WHEN p.post_title LIKE %s THEN 60
    ELSE 30
  END) + (CASE WHEN lkp.stock_status = 'instock' THEN 10 ELSE 0 END) AS relevance_score
FROM {$wpdb->posts} p
INNER JOIN {$wpdb->prefix}wc_product_meta_lookup lkp ON lkp.product_id = p.ID
INNER JOIN {$wpdb->postmeta} idx ON (idx.post_id = p.ID AND idx.meta_key = '_drv_search_index')
WHERE p.post_type = 'product'
  AND p.post_status = 'publish'
  AND (
    lkp.sku LIKE %s
    OR (idx.meta_value LIKE %s /* ... AND idx.meta_value LIKE %s cho từng token ... */)
  )
ORDER BY relevance_score DESC, lkp.total_sales DESC, p.ID DESC
LIMIT %d
```
- Mọi tham số `LIKE` bắt buộc đi qua `$wpdb->esc_like($token)` rồi mới truyền vào `$wpdb->prepare()`.
- Đếm `total` bằng truy vấn nhẹ riêng (hoặc `FOUND_ROWS` có cache) để gắn vào link `view_all` (`/?s={raw}&post_type=product`).

#### Bước 4: Bộ nhớ Đệm & Giới hạn Tần suất (Cache & Rate-limit)
- **IP Rate-limit:** Tối đa **`60` requests / `60` giây / IP** (`drv_rl_search_{ip_hash}`). Nếu vượt ngưỡng, trả `429 Too Many Requests` (`drv_rate_limited`) kèm header `Retry-After: 60`.
- **Server Cache:** Lưu JSON response vào object cache / transient `drv_search_{ver}_{md5($q_ascii . ':' . $limit)}` trong **`600` giây (10 phút)**, gắn HTTP header `Cache-Control: public, max-age=300` và LiteSpeed tag `drv_catalog`.

---

## 4. Mobile Bottom Action Bar (5 ô) & Footer 2 Tầng (`drv-footer-dock` + 4 cột)

### 4.1 Thanh Tác vụ Đáy Màn hình Mobile (`.drv-mobile-bar` - 5 Ô)

Hiển thị cố định dưới đáy màn hình trên thiết bị `< 1024px` (`position: fixed; inset: auto 0 0 0; z-index: var(--z-header)`), chiều cao nội dung `--mobile-bar-h: 56px` cộng phần đệm tai thỏ iOS `padding-bottom: env(safe-area-inset-bottom, 0px)`. Thẻ `<body>` trên mobile tự động cộng `padding-bottom: calc(var(--mobile-bar-h) + env(safe-area-inset-bottom, 0px))` để không che khuất chân Footer.

**Quy tắc Ẩn / Thay thế theo Ngữ cảnh Trang:**
| Ngữ cảnh trang | Hiển thị `.drv-mobile-bar` 5 ô? | Thành phần thay thế |
|---|---|---|
| Trang chủ, Danh mục `/gach/*`, Cẩm nang, Giới thiệu, Liên hệ, BOQ, Giỏ hàng | **Có** | - |
| Trang Chi tiết Sản phẩm (`is_product()`) | **Không** (PHP không render) | Thay bằng **Sticky Purchase Bar** (`Giá/m²` hoặc *Liên hệ báo giá* + nút `Thêm vào giỏ` / `Nhận báo giá`) để tránh chồng 2 thanh đáy |
| Trang Thanh toán (`is_checkout() && !is_wc_endpoint_url('order-received')`) | **Không** | Giao diện tối giản tập trung điền đơn |

**Cấu trúc 5 Ô chuẩn (Chia đường ron dọc `1px solid var(--c-navy-200)`):**

| Ô | Nhãn hiển thị | Icon Phosphor | Hành động / Đích đến |
|---:|---|---|---|
| **1** | `Danh mục` | `ph-squares-four` | `<button data-drv-action="dialog-open" data-drv-target="#drv-mobile-menu-drawer">` |
| **2** | `Tìm kiếm` | `ph-magnifying-glass` | `<button data-drv-action="search-toggle">` (cuộn/mở hộp tìm kiếm và focus input) |
| **3** | `Báo giá` | `ph-file-text` | `<a href="/danh-sach-bao-gia">` kèm badge `[data-drv-quote-count]` |
| **4** | `Zalo` | `ph-chat-circle-dots` | `<a href="{zalo_url}" target="_blank" rel="noopener">` (bắn sự kiện GA4 `click_zalo`) |
| **5** | `Gọi ngay` | `ph-phone-call` | `<a href="tel:{hotline_tel}" class="drv-mobile-bar__call">` (nền nhấn `--c-navy-900`, chữ trắng) |

### 4.2 Footer 2 Tầng (`footer.php`: `.drv-footer-dock` + `.drv-footer` 4 Cột)

#### Tầng 1: Thanh Cam kết & Kết nối Nhanh (`.drv-footer-dock`)
Nền `--c-ivory` (`#F8FAFC`), lưới 3 ô bằng nhau ngăn bởi đường ron `1px`:
1. **Ô 1 - Hotline Kỹ thuật:** Icon `ph-phone-call`, số `drv_setting('contact.hotline_display')`, phụ đề `drv_setting('contact.working_hours')`.
2. **Ô 2 - Gửi Bản vẽ / Nhận Báo giá:** Icon `ph-ruler`, link thẳng sang `/danh-sach-bao-gia` kèm cam kết `drv_setting('contact.sla_response')`.
3. **Ô 3 - Trạng thái Showroom Thời gian thực:** Icon `ph-storefront`, tên Showroom chính + huy hiệu **Đang mở cửa** / **Đã đóng cửa** + link `/lien-he` (tuyệt đối không trỏ `/showroom` cũ, `D-29`).

**Thuật toán tính Trạng thái Mở cửa Showroom An toàn với Page Cache (`Asia/Ho_Chi_Minh`):**
1. **Phía PHP (`Helpers::showroom_open_state(): array`):** Dùng `new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'))` lấy giờ hiện tại `$hour = (int) $now->format('G')`, so sánh với `open_hour` (`8`) và `close_hour` (`21`) trong `drv_settings.contact`:
   ```html
   <span class="drv-open-badge <?= $is_open ? 'is-open' : 'is-closed'; ?>"
         data-drv-open-hours="<?= esc_attr($open_h . '-' . $close_h); ?>">
     <span class="drv-open-badge__dot" aria-hidden="true"></span>
     <span data-drv-open-label><?= esc_html($is_open ? 'Đang mở cửa (08:00 - 21:00)' : 'Ngoài giờ mở cửa · Mở lại 08:00'); ?></span>
   </span>
   ```
2. **Bù trễ LiteSpeed Page Cache phía Client (`core.js`):** Vì HTML trang có thể được LiteSpeed lưu cache xuyên qua mốc `21:00`, hàm nhẹ 8 dòng trong `core.js` đọc `[data-drv-open-hours]`, lấy giờ Việt Nam hiện tại qua `Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Ho_Chi_Minh', hour: 'numeric', hour12: false }).format(new Date())` và cập nhật lại `.is-open` / `.is-closed` để trạng thái luôn chính xác từng phút mà không cần tắt cache trang.

#### Tầng 2: Thân Footer 4 Cột Nền Tối (`.drv-footer`, Nền `--c-navy-900` `#083A4F`, Chữ sáng)
- **Cột 1 - Thương hiệu & Pháp nhân (`1.3fr`):**
  - Logo bản sáng `assets/images/logo-ivory@1x.webp` / `@2x.webp` (`width="150" height="44" loading="lazy"`).
  - Tagline: *"Thẩm mỹ từ những đường nét nhỏ nhất."*
  - Tên pháp nhân `company.legal_name`, Mã số thuế `company.tax_id` (chỉ render dòng MST khi khác rỗng), địa chỉ đăng ký & Showroom viết đúng **chuẩn hành chính 2 cấp mới (Số nhà, Đường, Phường/Xã, Tỉnh/TP - xóa bỏ hoàn toàn địa danh Quận/Huyện/TP. Thủ Đức cũ của prototype)**.
  - Cụm mạng xã hội (`social.*`) và logo Đã thông báo Bộ Công Thương (`company.bct_badge_url` - **chỉ render khi đã điền URL thật**, cấm chèn link giả `#`).
- **Cột 2 - Khám phá Gạch Ốp Lát (`1fr`):** Render menu `footer_col_2` (fallback 8 URL tĩnh: `/gach-lat-nen`, `/gach-op-tuong`, `/gach-phong-khach`, `/gach-nha-tam`, `/gach-van-da`, `/gach-gia-go`, `/da-nung-ket`, `/gach-80x80`).
- **Cột 3 - Công cụ & Cẩm nang (`1fr`):** Render menu `footer_col_3` (`/danh-sach-bao-gia`, `/cam-nang`, `/chu-de/ky-thuat-op-lat`, `/chu-de/dinh-muc-bao-gia`, `/gioi-thieu`, `/lien-he`).
- **Cột 4 - Chính sách Khách hàng (`1fr`):** Render menu `footer_col_4` (`/chinh-sach-doi-tra`, `/chinh-sach-van-chuyen`, `/chinh-sach-thanh-toan`, `/chinh-sach-bao-mat`, `/dieu-khoan-su-dung`).
- **Dải Bản quyền dưới cùng (`.drv-footer__copy`):** Bản quyền năm động `wp_date('Y')` + thông tin thanh toán minh bạch (`COD` & `Chuyển khoản ngân hàng VietQR`).

---

## 5. Hợp đồng Bàn phím & Accessibility (WCAG 2.2 AA)

### 5.1 Tương tác Chuột, Bàn phím & Focus trên Mega Menu / Dropdown

| Tình huống tương tác | Quy tắc thực thi bắt buộc trong `core.js` & CSS |
|---|---|
| **Hover Intent (Desktop chuột thật `@media (hover: hover) and (pointer: fine)`)** | Mở Mega Menu / Dropdown sau độ trễ **`180ms`** (`pointerenter`) để tránh bung nhầm khi lướt chuột từ thanh địa chỉ trình duyệt xuống trang; giữ thời gian ân hạn đóng **`220ms`** (`pointerleave`) để người dùng rê chéo chuột từ nút `"Tất cả gạch"` sang Cột 5 không bị sập menu. |
| **Kích hoạt bằng Bàn phím (`Enter` / `Space`)** | Trên nút `.drv-mega-trigger` hoặc `.drv-nav__sub-toggle`: nhấn `Enter` hoặc `Space` đảo trạng thái `aria-expanded="true\|false"`, gỡ/gắn `hidden` trên `#drv-mega-menu` và chuyển focus vào link đầu tiên bên trong nếu bấm tiếp `ArrowDown`. |
| **Giữ mở theo `:focus-within`** | Khi người dùng nhấn `Tab` di chuyển qua các link từ Cột 1 sang Cột 5, Mega Menu giữ nguyên trạng thái mở; khi `focusout` rời khỏi hoàn toàn vùng `.drv-header__nav` (kiểm tra `!navEl.contains(event.relatedTarget)`), tự động đóng Mega Menu và đặt lại `aria-expanded="false"`. |
| **Phím `Escape`** | Khi Mega Menu hoặc bất kỳ Dropdown cấp 2/3 nào đang mở, nhấn `Escape` lập tức đóng panel và **trả focus về đúng nút kích hoạt** (`.drv-mega-trigger` hoặc `.drv-nav__sub-toggle`). |
| **Click ngoài vùng (Click Outside)** | Click vào vùng nội dung trang bên ngoài `.drv-header__nav` đóng ngay Mega Menu đang mở. |

### 5.2 Quản lý Focus cho `<dialog>` Drawer (`#drv-mobile-menu-drawer`, `#drv-minicart-drawer`)

1. Bắt buộc mở bằng phương thức chuẩn **`dialog.showModal()`** (không dùng `dialog.open = true`) để trình duyệt tự động kích hoạt focus trap native và lớp nền `::backdrop`.
2. Trước khi gọi `showModal()`, lưu `dialog._returnFocusEl = document.activeElement` và gắn `document.documentElement.classList.add('has-modal-open')` (`overflow: hidden; scrollbar-gutter: stable` chống giật ngang màn hình).
3. Trong sự kiện `close` của `<dialog>`: gỡ `.has-modal-open`, đồng bộ `aria-expanded="false"` trên nút mở, và gọi `dialog._returnFocusEl?.focus({ preventScroll: true })`.
4. Click vào nền tối ngoài khung nội dung (`event.target === dialog`) tự động gọi bước đóng mượt `.is-closing` rồi `dialog.close()`.

### 5.3 Điều hướng Bàn phím WAI-ARIA Combobox / Listbox trong Live Search

Hộp tìm kiếm `.drv-search` tuân thủ mẫu **WAI-ARIA 1.2 Combobox with Listbox Popup**:

1. **Thuộc tính trên `<input id="drv-search-input">`:**
   - `role="combobox"`, `aria-autocomplete="list"`, `aria-controls="drv-search-panel"`, `aria-expanded="false"` (chuyển thành `"true"` khi panel hiển thị), `aria-activedescendant=""` (cập nhật sang `id` của mục đang được tô sáng, ví dụ `"drv-srch-opt-3"`).
2. **Thuộc tính trong `#drv-search-panel`:**
   - Các mục chọn được (chip từ khóa, danh mục cột trái, sản phẩm cột phải, nút *Xem tất cả*) gắn `role="option"`, `id="drv-srch-opt-{index}"` và `aria-selected="false"`.
3. **Bảng phím tắt bắt buộc trong `search.js`:**
   - `ArrowDown`: Nếu panel đang đóng và có dữ liệu, mở panel; di chuyển chỉ số active xuống mục kế tiếp (vòng về đầu nếu ở cuối), cập nhật `.is-active`, `aria-selected="true"`, `aria-activedescendant` và `scrollIntoView({ block: 'nearest' })`.
   - `ArrowUp`: Di chuyển chỉ số active lên mục phía trước.
   - `Enter`: Nếu đang có mục `aria-selected="true"` (`activeIndex >= 0`), điều hướng thẳng tới `href` của mục đó (hoặc điền từ khóa nếu là chip phổ biến); nếu `activeIndex === -1`, để `<form>` submit tự nhiên sang trang SSR `/?s=...&post_type=product`.
   - `Escape`: Nếu panel đang mở -> đóng panel, xóa `aria-activedescendant`, giữ nguyên chữ trong ô input; nếu nhấn `Escape` lần 2 khi panel đã đóng -> xóa trắng ô input.
4. **Thông báo Trình đọc Màn hình (`[data-search-status]`):**
   - Sau mỗi lần nhận kết quả REST, cập nhật vùng `aria-live="polite"`: *"Tìm thấy {silos.length} danh mục và {total} sản phẩm phù hợp. Dùng phím mũi tên xuống để duyệt kết quả."*
5. **Tương phản & Vùng chạm WCAG 2.2 AA:**
   - Tỷ lệ tương phản chữ `#083A4F` trên nền `#FFFFFF` đạt `11.8:1` (`AAA`); chữ trắng trên nền `#083A4F` khi hover đạt `11.8:1`.
   - Mọi nút icon trên Header, Mobile Drawer và Mobile Bottom Bar có kích thước vùng chạm tối thiểu `44×44px` (`2.5.8 Target Size`) và vòng `:focus-visible` rõ nét `outline: 2px solid var(--c-teal-500); outline-offset: 2px`.

