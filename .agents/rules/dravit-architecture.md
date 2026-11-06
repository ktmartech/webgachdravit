# Dravit Theme Architecture Rules

> Áp dụng cho toàn bộ codebase trong `wp-content/themes/dravit/` trên dự án `gachdravit.vn`.
> Tham chiếu bắt buộc: `docs/00_Project_Brief_Decisions.md` và `docs/02_Technical_Architecture.md`.

---

## 1. Quyết định Kiến trúc Cốt lõi (Bất biến)

- **Loại Theme:** WordPress **Classic PHP Theme** kết hợp `theme.json` (chỉ khai báo design tokens cho Gutenberg editor).
- **Không tách Plugin riêng:** Toàn bộ logic nghiệp vụ (`Catalog`, `Units`, `Filters`, `Seo`, `Woo`, `Admin`) gộp chung trong `wp-content/themes/dravit/inc/` dưới namespace `Dravit\` và nạp qua PSR-4 autoloader trong `functions.php` (không dùng `vendor/` runtime trên server).
- **Đơn ngôn ngữ (Tiếng Việt 100%):** Website chỉ sử dụng **1 ngôn ngữ duy nhất là Tiếng Việt (`lang="vi"`)**.
  - **CẤM:** cài Polylang/WPML, tạo cấu trúc URL đa ngôn ngữ (`/en/`, `/zh/`), tạo helper dịch đa ngôn ngữ hoặc tách template theo ngôn ngữ (`-{lang}.php`).
- **Không tạo bảng Database riêng ở Phase 1:** Mọi dữ liệu lưu trong cấu trúc chuẩn của WordPress và WooCommerce (`product_cat`, `pa_*`, post meta qua WC CRUD, term meta, option `drv_settings`).
- **Tương thích HPOS 100%:** Mọi thao tác với đơn hàng bắt buộc dùng `wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`. **CẤM** dùng `get_post_meta()` / `update_post_meta()` cho đơn hàng.

---

## 2. Cấu trúc Thư mục Chuẩn (`wp-content/themes/dravit/`)

```text
wp-content/themes/dravit/
├── style.css · functions.php · theme.json
├── config/
│   ├── collections.php          # 28 Smart Collections (slug, group, h1, rule, priority, phase)
│   ├── facets.php               # 14 bộ lọc (FLT-01..14, param, taxonomy, UI, nhóm SEO)
│   ├── units.php                # Hệ đơn vị bán & pricing models (area_pack, piece, set, pack)
│   └── provinces-vn.php         # 34 Tỉnh/Thành phố Việt Nam
├── inc/
│   ├── Theme.php                # Bootstrap các module theo thứ tự
│   ├── Core/                    # Setup, Assets, Vite, Cleanup, Settings, Helpers, Template
│   ├── Catalog/                 # CollectionSync, RuleMatcher, PrimaryCategory, SlugGuard, SaleSync, ResyncJob
│   ├── Units/                   # UnitRegistry, PricingModel/{AreaPack,Piece,Set,Pack}, PriceHtml, QuantityRules
│   ├── Filters/                 # FacetRegistry, UrlState, QueryBuilder, FacetCounter, CatalogController, Renderer
│   ├── Seo/                     # RobotsMatrix, Canonical, SchemaGraph, Breadcrumbs, InternalLinks, LlmsTxt
│   ├── Woo/                     # Hooks, AssetsDequeue, CheckoutVN, QuoteShippingMethod, OrderFlow, Emails/*, BankQR
│   └── Admin/                   # ProductDataTab, TermFields, ToolsResync
├── header.php · footer.php · index.php · front-page.php · page.php · single.php · archive.php · search.php · 404.php
├── page-templates/              # template-showroom.php, template-policy.php
├── template-parts/              # header/, footer/, catalog/, product/, blog/, common/
├── woocommerce/                 # Chỉ override các template bắt buộc (archive-product, content-product, single-product, cart, checkout)
├── patterns/                    # Gutenberg block patterns cho trang nội dung
├── assets/
│   ├── src/tokens/tokens.json   # Nguồn token duy nhất
│   ├── src/css/                 # @layer reset, tokens, base, layout, components, woo, utilities
│   ├── src/js/                  # ES Modules: core.js, catalog.js, load-more.js, gallery.js, calculator.js, cart.js, search.js, checkout-vn.js
│   ├── dist/                    # Vite build output + .vite/manifest.json
│   ├── fonts/                   # anton-vi.woff2, quicksand-vf-vi.woff2
│   ├── images/                  # Logo WebP/PNG tối ưu
│   └── data/wards/              # JSON Phường/Xã theo mã tỉnh (lazy load)
└── tools/                       # Scripts build-time (tokens, fonts, wards)
```

---

## 3. Nguyên tắc Module hóa

- Mỗi class trong `inc/` chỉ đảm nhận 1 trách nhiệm rõ ràng và đăng ký hook qua phương thức `register(): void`.
- Thứ tự khởi tạo trong `Dravit\Theme::boot()`: `Core → Units → Catalog → Filters → Seo → Woo → Admin (chỉ khi is_admin())`.
- Không gọi trực tiếp hàm của plugin thứ ba (`RankMath`, `LiteSpeed`) mà không kiểm tra `class_exists()` / `function_exists()` hoặc dùng `apply_filters()` / `do_action()`.
- Cart và Checkout bắt buộc dùng **WooCommerce Classic Shortcode** (`[woocommerce_cart]`, `[woocommerce_checkout]`), **CẤM** dùng WooCommerce Cart/Checkout Blocks.

