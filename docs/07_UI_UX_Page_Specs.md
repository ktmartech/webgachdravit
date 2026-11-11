# 07. Đặc tả UI/UX từng trang (Architectural Grid System)

> Token, màu sắc, typography, thang z-index & component cơ bản: xem [01_Brand_Design_System.md](01_Brand_Design_System.md).
> Giọng văn & khung copy từng trang: xem [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md).
> Bản đồ port 12 trang mẫu sang WordPress & hợp đồng JS: xem [15_REST_API_And_JS_Contract.md](15_REST_API_And_JS_Contract.md), [16_Template_Component_Porting_Guide.md](16_Template_Component_Porting_Guide.md), [19_Navigation_Header_Search_Spec.md](19_Navigation_Header_Search_Spec.md).
> Skill áp dụng: `design-taste-frontend`, `frontend-design`, `ui-ux-designer`, `page-cro`, `form-cro`, `fixing-accessibility`, `baseline-ui`, `web-design-guidelines`.

---

## 1. Triết lý Thiết kế & Điểm Khác biệt Nhận diện (Differentiation Anchor)

### 1.1 Tuyên ngôn thẩm mỹ: "Architectural Grout-Line Grid" (Lưới Kiến trúc Đường Ron)

> **Câu hỏi kiểm tra (`frontend-design`):** *"Nếu chụp màn hình giao diện mà che logo DRAVIT đi, làm sao nhận ra đây là website của DRAVIT?"*
> **Câu trả lời:** Mọi khối thông tin, thẻ sản phẩm, bảng thông số và thanh điều hướng được phân tách bằng **hệ thống đường kẻ ron `1px solid var(--c-navy-200)` thẳng tắp, vuông vức (`border-radius: 2px–8px`) trong container chuẩn `1380px`**, kết hợp bảng màu **Brand Primary `#083A4F` + Chữ Deep Ink `#0B192C` + Brand Teal `#0D9488` / `#0F766E` + Warm Amber Pop `#F59E0B` trên nền Clean Slate `#F8FAFC`**, cùng cơ chế **hiển thị song song 2 đơn vị `đ/m²` & `đ/viên`** và quy đổi `thùng · m² · kg` xuyên suốt mọi điểm chạm.

### 1.2 Bảng đối chiếu: Chắt lọc từ mẫu tham khảo & Khác biệt hóa cho DRAVIT

| Hạng mục | Mẫu tham khảo (`img layout sample`) | Quyết định thiết kế của DRAVIT (Chắt lọc & Khác biệt hóa) |
|---|---|---|
| **Tổng thể & Khuyến mãi** | Banner top đỏ/vàng rực, 2 banner dọc bám 2 bên màn hình, đồng hồ đếm ngược Flash Sale, nhãn "GIẢM SỐC" răng cưa. | **Loại bỏ hoàn toàn** banner bám 2 bên, đồng hồ đếm ngược giả và tem răng cưa chợ. Dùng nền phẳng `--c-ivory`, lưới `1380px` cân đối 2 biên; ưu đãi trình bày chỉn chu bằng badge vuông `--c-sale` (`-15%`). |
| **Header & Mega Menu** | Hàng pill bo tròn nhiều màu; mega menu 6 cột chữ đơn điệu; thiếu danh sách dự toán. | **Header 2 tầng ngăn bằng đường ron 1px (`D-29`).** Tier 1 có đồng thời **`[DS Báo giá (n)]`** và **`[Giỏ (n)]`**. Mega menu **"Silo Matrix 4+1"** sinh từ `config/collections.php` (7/8/7/5) + **Cột 5 Dynamic Preview** + **Bottom Utility Strip** (0 link `?param`). |
| **Hero Trang chủ** | Chia 3 banner nhỏ + nhét cột "Sản phẩm đã xem" ngay trên màn hình đầu. | **Hybrid Hero định hướng tư vấn (`D-25`):** Kết hợp ảnh phối cảnh `<picture>` thật làm phần tử LCP (`fetchpriority="high"`) với hộp tìm nhanh / tư vấn chọn gạch ngay trên màn đầu; "Sản phẩm đã xem" đưa xuống cuối trang sản phẩm. |
| **Khối Danh mục ở Home** | Tab ngang + hàng pill lọc + banner dọc trái + lưới 5 cột chật. | **"Catalog Explorer Block"**: 4 tab công năng + hàng chip liên kết tĩnh + Cột trái **Lookbook Card** (ảnh phối cảnh + thông số khuyến nghị) + Lưới sản phẩm 3 cột thoáng rộng. |
| **Đầu trang Danh mục** | Carousel khuyến mãi lớn chiếm hết màn hình đầu + hàng icon danh mục hình tròn. | **Loại bỏ** carousel KM đầu trang. Dùng Hero gọn (pills + nút nhảy xuống `#bang-thong-so-geo`) và **"Visual Sub-Silo Tiles"** chữ nhật (`4px`) có ảnh vân `48×48px` dẫn thẳng tới URL tĩnh. |
| **Card Sản phẩm** | Tên cắt cụt 1 dòng; chỉ hiện giá/m²; nút tim wishlist + giỏ tròn rối mắt. | **Ảnh 1:1 chuyển sang `gallery[0]` khi hover.** Hỗ trợ `.drv-card--priced` và `.drv-card--quote`, nhãn `drv_card_label`, vị trí `Kệ {drv_showroom_shelf}`, badge `Tạm hết`, thẻ `<h3>` đủ 2 dòng. **Bỏ hoàn toàn nút Wishlist (`D-26`).** |
| **Gallery & Biến thể PDP** | Lưới ảnh đơn giản; đổi giá giả bằng JS khi bấm màu. | **Gallery 6 vai trò ảnh** + nút **`[Tải Map gạch CAD/3D]`** + zoom **PhotoSwipe 5**. Swatch màu/kích thước/bề mặt là thẻ `<a href>` SSR nối các SKU cùng `drv_series` (`D-26`). |
| **Bộ tính số lượng SP** | Chỉ tính 1 chiều m² -> viên cố định. | **`<drv-unit-calc>` theo cấu hình từng SP (`D-24`):** Hỗ trợ chọn mua theo **`m²` \| `Thùng` \| `Viên`** (`drv_buy_units`), chế độ làm tròn `box` hoặc `piece`, hao hụt `0/5/10%`, xuất đủ `viên · thùng · m² · kg` + **Sticky Purchase Bar** cho cả `priced` và `quote`. |
| **Bài viết Cẩm nang** | Chèn chữ lên ảnh và đè nút "Nhận báo giá" góc ảnh, thiếu kiểm chứng chuyên môn. | Ảnh phối cảnh sạch + **"Shop the Look Bar"** ngay chân ảnh + hộp **Key Takeaways**, TOC server, shortcode `[drv_products]` / `[drv_price_table]` lấy giá động và hộp **Tác giả + Kỹ sư thẩm định thật**. |

---

## 2. Layout Toàn cục (Header, Mega Menu, Footer Dock, Footer, Mobile Bar — `D-28`, `D-29`)

> Đặc tả chi tiết menu walkers, live search và fragment cache: xem [19_Navigation_Header_Search_Spec.md](19_Navigation_Header_Search_Spec.md).

### 2.1 Header 2 tầng & Mega Menu "Silo Matrix 4+1"

```text
Desktop (≥ 1024px) — Container 1380px, nền --c-white, viền đáy 1px solid --c-navy-200
┌────────────────────────────────────────────────────────────────────────────────────────────┐
│ TẦNG 1 (68px, thu gọn 56px khi cuộn xuống):                                                │
│ [LOGO DRAVIT]  [ Tìm mẫu gạch, mã SKU (DRV-8801M), kích thước 80x80... ]                   │
│                Showroom (8:00-21:00) │ 09xx.xxx.xxx │ [DS Báo giá (3)] │ [Giỏ (2)]         │
├────────────────────────────────────────────────────────────────────────────────────────────┤
│ TẦNG 2 (44px, ngăn bởi đường ron 1px):                                                     │
│ Tất cả gạch │ Theo công năng ▾ │ Theo không gian ▾ │ Vân & chất liệu ▾ │ Theo kích thước ▾│
│ Đá nung kết │ Gạch ưu đãi                                    Cẩm nang ▾ │ Về Dravit │ Liên hệ│
└────────────────────────────────────────────────────────────────────────────────────────────┘

Khi mở Mega Menu (Container 1380px, chia 5 cột bằng đường ron dọc 1px + Dải tiện ích đáy):
┌───────────────┬───────────────┬───────────────┬───────────────────┬────────────────────────┐
│ CÔNG NĂNG (7) │ KHÔNG GIAN (8)│ VÂN & CHẤT (7)│ KÍCH THƯỚC (5)    │ CỘT 5: DYNAMIC PREVIEW │
│ • Gạch lát nền│ • Phòng khách │ • Vân đá      │ [□] Gạch 60x60    │ [Ảnh phối cảnh nhóm]   │
│ • Gạch ốp     │ • Phòng ngủ   │ • Giả gỗ      │ [■] Gạch 80x80    │ GẠCH PHÒNG KHÁCH       │
│ • Trang trí   │ • Phòng bếp   │ • Terrazzo    │ [▭] Gạch 30x60    │ Khổ 80x80, 60x120      │
│ • Gạch bông   │ • Nhà tắm/WC  │ • Vân xi măng │ [▬] Gạch 60x120   │ Từ 265.000đ/m² · 64 mẫu│
│ • Bông gió    │ • Sân vườn    │ • Đá nung kết │ [─] Gạch khổ lớn  │ [Xem bộ sưu tập]       │
│ • Gạch thẻ    │ • Ban công    │ • Porcelain   │                   │                        │
│ • Gạch ưu đãi │ • Mặt tiền    │ • Men Ceramic │                   │                        │
│               │ • Ngoài trời  │               │                   │                        │
├───────────────┴───────────────┴───────────────┴───────────────────┴────────────────────────┤
│ DẢI TIỆN ÍCH ĐÁY: [Xem trọn bộ 28 danh mục gạch] · [Gạch ưu đãi tại kho] ·                 │
│                   [ Lập dự toán BOQ theo phòng ] · [ Xem mẫu thực tế tại Showroom ]         │
└────────────────────────────────────────────────────────────────────────────────────────────┘
Mobile (< 1024px): [Menu]   [LOGO DRAVIT]   [Tìm]  [DS Báo giá (3)]  [Giỏ (2)]  + Mobile Bar đáy
```

- **Quy chuẩn Icon bắt buộc (`D-28`):** Toàn bộ biểu tượng trên giao diện HTML/JS sử dụng **Phosphor Icons Subset tự host** (`assets/fonts/phosphor-subset.woff2`, cú pháp `<i class="ph ph-..." aria-hidden="true"></i>`). **Nghiêm cấm** dùng ký tự Unicode (`→`, `▾`, `▸`, `↓`, `⇄`, `×`, `−`, `+`, `✓`, `●`, `☰`) hoặc emoji làm icon.
- **Cụm hành động Tầng 1 kép (`D-29`):** Luôn hiển thị song song **`[DS Báo giá (n)]`** (đọc số lượng hạng mục từ `localStorage` `drv_quote_v2`, bấm mở `<drv-quote-panel>` hoặc `/danh-sach-bao-gia`) và **`[Giỏ (n)]`** (đọc cookie/Store API, bấm mở Mini-cart Drawer). Khi `drv_settings.features.ai_enabled = false` (mặc định Phase 1), ẩn hoàn toàn nút gọi Trợ lý AI trên Header và Mobile Bar.
- **Nguồn dữ liệu Mega Menu & Cấm `?param` (`D-29`):** Mega Menu 4+1 (đủ 27 danh mục con thuộc 4 nhóm + `/gach` = 28 Smart Collections) và Mobile Accordion 3 cấp được render từ `config/collections.php` + term meta và lưu fragment cache `drv_mega_html`. Cột 5 cập nhật ảnh, mô tả ngắn, thông số tiêu biểu và giá từ `price_min_m2` khi hover từng mục. **Tuyệt đối 0 liên kết trong Header/Mega Menu/Footer chứa tham số lọc `?param`.**
- **Hành vi Sticky:** Khi cuộn xuống, Tầng 1 thu gọn còn `56px` (`--header-top-compact-h`), ẩn Tầng 2 (hiện lại ngay khi cuộn nhẹ lên trên) không gây CLS.
- **Ô tìm kiếm tức thì (Live Search 2 cột):** Nạp động `chunks/search.js` khi focus; lúc chưa gõ hiện các từ khóa phổ biến (`drv_settings.search.popular_keywords`). Khi gõ `≥ 2` ký tự (hỗ trợ tìm không dấu và mã SKU, debounce `200ms`), hiển thị khung gợi ý 2 cột: Cột trái (4/12) là **Danh mục Silo khớp từ khóa**; Cột phải (8/12) là **tối đa 6 sản phẩm** kèm ảnh vuông `48×48px`, mã SKU, kích thước và giá `đ/m²` (hoặc `Liên hệ báo giá`) + nút xem toàn bộ kết quả.

### 2.2 Footer 2 tầng (`.drv-footer-dock` 3 hộp + Chân trang 4 cột)

```text
┌────────────────────────────────────────────────────────────────────────────────────────────┐
│ DẢI 3 HỘP HÀNH ĐỘNG NHANH (.drv-footer-dock — Nền --c-champagne, lưới ron 1px):            │
│ [1] TƯ VẤN KỸ THUẬT · ● Đang làm việc │ [2] SHOWROOM & KHO HÀNG  │ [3] BÁO GIÁ CÔNG TRÌNH  │
│     Hotline: 09xx.xxx.xxx (8:00-21:00)│     [Địa chỉ 2 cấp mới]  │     Bóc tách m², thùng  │
│     [Gọi ngay]  [Chat Zalo kỹ thuật]  │     [Chỉ đường Maps]     │     [Mở bảng BOQ]       │
├───────────────────────────────────────┴──────────────────────────┴─────────────────────────┤
│ FOOTER CHÍNH 4 CỘT (Nền --c-navy-900, chữ --c-ivory, Logo âm bản DRAVIT-04 WebP):          │
│ Cột 1: Pháp nhân DRAVIT │ Cột 2: Danh mục gạch │ Cột 3: Hỗ trợ mua hàng │ Cột 4: Cẩm nang  │
│ Tagline, Tên công ty,   │ 8 URL Collection     │ Vận chuyển, Đổi trả,   │ Chọn gạch, Kỹ    │
│ MST, Trụ sở, Email      │ tĩnh trọng điểm      │ Thanh toán, Bảo mật    │ thuật & Liên kết │
└────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 2.3 Thanh Hành động Cố định Đáy Mobile (`< 1024px`)

- **Trên Trang chủ / Danh mục / Cẩm nang / Trang tĩnh:** Cao `56px` (`--mobile-bar-h`, cộng `env(safe-area-inset-bottom)`), `z-index: var(--z-header)`, chia 5 ô bằng đường ron dọc: `[Danh mục]` · `[Gọi]` · `[Zalo]` · `[DS Báo giá (n)]` · `[Giỏ (n)]`.
- **Trên Trang Chi tiết Sản phẩm:** Thay thế bằng **Sticky Purchase Bar** (§5.3).

---

## 3. Trang chủ (`front-page.php` — `D-25`, `D-28`)

Thứ tự 8 section phục vụ đồng thời **chủ nhà** (tìm theo không gian, xem kích thước thực) và **thợ thầu / KTS** (tra thông số, lập dự toán nhanh). Bảo đảm phân cấp heading chuẩn `H1` -> `H2` -> `H3` (không nhảy từ `H1` xuống `H3`).

### Section 1: Hybrid Hero Định hướng Tư vấn + Ảnh LCP Thật (`.drv-hero-void` — `D-25`)

```text
┌───────────────────────────────────────────────────┬────────────────────────────────────────┐
│ CỘT TRÁI (7/12 — Trên nền <picture> phối cảnh LCP)│ CỘT PHẢI (5/12 — Hộp Tìm & Tư vấn Gạch)│
│ [Nhãn: VẬT LIỆU HOÀN THIỆN TIÊU CHUẨN QUỐC TẾ]    │ TÌM NHANH MẪU GẠCH THEO CÔNG TRÌNH     │
│ H1 (Anton): GẠCH ỐP LÁT CHÍNH HÃNG DRAVIT         │ [Ô nhập: Phòng tắm 6m², gạch 80x80...] │
│ Subhead: Thẩm mỹ từ những đường nét nhỏ nhất.     │ Gợi ý chọn nhanh (Link tĩnh):          │
│ Minh bạch giá/m², quy đổi chuẩn từng thùng gạch.  │ [Phòng khách] [Nhà tắm R10] [Sân vườn] │
│                                                   │ [Gạch 80x80]  [Gạch 60x120] [Vân đá]   │
│ [Xem bộ sưu tập gạch]   [Lập danh sách báo giá]   │ [Tìm mẫu gạch phù hợp]                 │
└───────────────────────────────────────────────────┴────────────────────────────────────────┘
```

- **Yêu cầu hiệu năng & LCP bắt buộc (`D-25`):** Nền Hero bắt buộc render thẻ `<picture>` ảnh không gian lát gạch thật (`drv_hero` 1920×1080 desktop / `drv_hero_m` 828×1035 mobile) làm phần tử LCP duy nhất của trang (`fetchpriority="high"`, không `loading="lazy"`), phủ lớp gradient tối đảm bảo độ tương phản chữ trắng `≥ 7:1`.
- **Quy tắc `<canvas>` trang trí:** Chỉ khởi chạy trên màn hình desktop (`≥ 1024px`) **sau khi ảnh LCP đã tải xong**, tự tạm dừng khi cuộn khuất màn hình và tắt hoàn toàn khi bật `prefers-reduced-motion: reduce`.
- **Hành vi theo cờ AI (`drv_settings.features.ai_enabled`):** Ở Phase 1 (`ai_enabled = false`), các chip gợi ý là thẻ `<a href>` dẫn thẳng tới các URL Collection tĩnh và form tìm kiếm điều hướng tới trang danh mục/tìm kiếm chuẩn (ẩn mọi nhãn hứa hẹn hội thoại AI LLM). Khi bật Phase 3 (`ai_enabled = true`), form chuyển ngữ cảnh sang `/tro-ly-ai-tim-gach`.

### Section 2: Dải Cam kết Chuẩn mực (Architectural Trust Strip)
- Lưới 4 ô ngang ngăn bởi đường kẻ ron `1px` trên nền `--c-white`:
  1. **Thông số minh bạch:** Công khai độ hút nước, chỉ số chống trượt `R9-R13`, độ dày `mm`.
  2. **Giá kép rõ ràng:** Niêm yết cả `đ/m²` và `đ/viên`, tự quy đổi số thùng và khối lượng `kg`.
  3. **Mẫu thật tại Showroom:** Đối chiếu trực tiếp bề mặt và sắc độ dưới ánh đèn thực tế.
  4. **Đồng hành công trình:** Xuất kho đồng bộ mã lô (`Lot`), báo phí vận chuyển tối ưu theo tải trọng.

### Section 3: Chọn Gạch Theo Không Gian (Đủ 8 Không gian `pa_khong-gian`)
- **Header section:** `H2` `"Chọn gạch đúng cho từng không gian"` + mô tả ngắn + link `"Xem tất cả danh mục gạch"` (`/gach`).
- **Hiển thị đủ cả 8 Collection nhóm Không gian** (`Phòng khách`, `Phòng ngủ`, `Phòng bếp`, `Nhà tắm / WC`, `Sân vườn`, `Ban công / Sân thượng`, `Mặt tiền`, `Ngoài trời / Hồ bơi`) theo lưới Bento kiến trúc.
- **Trên mỗi thẻ (`H3`):** Ảnh phối cảnh thật + Tên không gian + **1 tiêu chuẩn kỹ thuật cốt lõi** (VD: `Nhà tắm: Chống trượt R10-R11` · `Phòng bếp: Hút nước ≤ 0,5%, dễ lau dầu mỡ` · `Phòng khách: Khổ 80x80, 60x120`) + số lượng mẫu thực từ term cache. Toàn bộ 8 thẻ trỏ về 8 URL Collection tĩnh.

### Section 4: Catalog Explorer Block (4 Tab Công năng + Lookbook Card)
- **Tầng Tab chính (ngăn bằng đường ron):** `Gạch lát nền` · `Gạch ốp tường` · `Gạch trang trí` · `Đá nung kết`.
- **Tầng Chip điều hướng nhanh (Link tĩnh `<a href>`):** `Gạch 60x60` · `Gạch 80x80` · `Gạch 30x60` · `Gạch 60x120` · `Vân đá` · `Giả gỗ` · `Terrazzo` · `Vân xi măng` (chỉ dùng slug chuẩn của 28 Smart Collections).
- **Thân khối (12 cột):** Cột trái (3/12) là **Lookbook Card** nền `--c-navy-900` chữ `--c-ivory` (ảnh phối cảnh dọc + thông số khuyến nghị từ `drv_home_spec` + nút `"Xem toàn bộ {n} mẫu"` trỏ URL tĩnh); Cột phải (9/12) là lưới 3–6 card sản phẩm tiêu biểu của nhóm đang chọn.

### Section 5: Tương quan Kích thước & Bề mặt Thực tế (`<drv-size-guide>`)
- Đọc cấu hình từ `config/size-guide.php`:
  - **Cột trái (6/12 — Sơ đồ tỷ lệ khổ gạch trực quan):** Minh họa tỷ lệ thực giữa các khổ `30x60`, `60x60`, `80x80`, `60x120`, `120x240`, `15x90` kèm diện tích phòng khuyến nghị, tích hợp công tắc bật/tắt **chế độ xem mật độ đường ron (Grout Density)**. Bấm vào từng khổ gạch dẫn thẳng tới URL tĩnh (`/gach-60x60`, `/gach-80x80`, `/gach-60x120`...).
  - **Cột phải (6/12 — Bề mặt & Bộ lọc Vùng khô / Vùng ướt):** Cho phép lọc nhanh theo khu vực sử dụng (*Khu khô ráo trong nhà* / *Khu ẩm ướt nhà tắm, bếp* / *Ngoài trời, sân vườn*) để làm nổi bật bề mặt phù hợp (*Bóng kiếng*, *Mờ mịn Matt*, *Nhám sần Structured*, *Hiệu ứng Sugar/Carving*) kèm nhãn `.drv-r-badge` (`R9`–`R13`) và liên kết tĩnh tương ứng.

### Section 6: Gạch Ưu Đãi & Lẻ Lô Tại Kho (`/gach-uu-dai`)
- Không dùng đồng hồ đếm ngược giả; thiết kế trên nền `--c-champagne` viền `var(--bw)`:
  - Tiêu đề `H2`: **"Gạch Ưu Đãi & Lẻ Lô Tại Kho"** + mô tả rõ nguồn hàng chính hãng dọn mẫu trưng bày hoặc còn lẻ lô số lượng giới hạn.
  - Lưới 4 card sản phẩm hiển thị rõ tồn kho thực tế **`Còn {X} thùng ({Y} m²)`** + nút `"Xem toàn bộ mã ưu đãi"` (`/gach-uu-dai`).

### Section 7: Không gian Showroom Thực tế (`<drv-showroom>`)
- Bố cục 2 cột (7/12 khung hình ảnh · 5/12 thông tin & hành động):
  - Chuyển đổi **3 khu vực trưng bày** (*Khu Gạch Khổ Lớn*, *Khu Gạch Phòng Tắm & Bếp*, *Bảng Mẫu Bề Mặt & Đường Ron*).
  - **Thanh trượt đối chiếu nhiệt độ màu đèn `3000K` (vàng ấm) / `6500K` (trắng ban ngày):** Chỉ render khi trong `drv_settings.showrooms` đã khai báo đủ **cặp 2 file ảnh chụp thực tế khác nhau** (`O-10`); nếu chưa có ảnh đôi thật thì hiển thị ảnh tĩnh chuẩn, tuyệt đối không dùng chung 1 ảnh giả lập màu bằng CSS filter.
  - **Badge trạng thái mở cửa (`Đang mở cửa` / `Đã đóng cửa`):** Tính toán bằng PHP phía server theo múi giờ `Asia/Ho_Chi_Minh` từ khung giờ mở cửa có cấu trúc. Kèm địa chỉ chuẩn, thông tin bãi đỗ ô tô và 2 nút **`Chỉ đường Google Maps`** + **`Đặt lịch xem mẫu`** (`/lien-he`).

### Section 8: Cẩm nang Chọn gạch & Thi công (Layout Tạp chí Kiến trúc 8/4)
- **Cột trái (8/12):** 1 bài viết trụ cột lớn (ảnh 16:9 + chủ đề + tiêu đề `H3` + tóm tắt 2 dòng) ở trên + 3 bài viết chia theo chủ đề ở dưới.
- **Cột phải (4/12 — Hộp "Cẩm nang tra nhanh cho người làm nhà" nền `--c-white` viền ron):** Danh sách đánh số `01` đến `05` (font Anton màu `--c-teal-700`) các bài hướng dẫn thiết yếu đọc từ `drv_settings.blog.popular_ids`.

### 3A. Trang Trợ lý AI Tìm gạch (`/tro-ly-ai-tim-gach` — `template-ai-finder.php`, Phase 3)
- **Bố cục chuẩn (khi mở ở Phase 3, xem [13_AI_Advisor_MVP_Spec.md](13_AI_Advisor_MVP_Spec.md)):** Chia đôi **5/7 trên desktop** (5 cột khung hội thoại + thanh tiêu chí slot dùng đúng slug `pa_*`; 7 cột lưới gợi ý gạch kèm điểm phù hợp, cảnh báo an toàn chỉ số `R` cho cả nhà tắm, phòng bếp, sân vườn và thanh context strip) và **chuyển tab trên mobile**.
- **Trạng thái ở Phase 1 (`D-25`):** Giữ `features.ai_enabled = false`, trang được bảo vệ slug qua `SlugGuard`, đặt `noindex, nofollow` và ẩn khỏi menu. Loại bỏ hoàn toàn thanh chuyển chế độ demo (`mode-switcher`) và lớp nền thử nghiệm của bản prototype.

---

## 4. Trang Danh mục (28 Smart Collections, `/bo-suu-tap/*`, `/xuat-xu/*`)

### 4.1 Wireframe, Sticky Toolbar & Bộ lọc dùng chung 1 `<form>`

```text
Desktop (≥ 1024px) — Container 1380px
┌ Breadcrumb: Trang chủ / Gạch / Gạch phòng khách ────────────────────────────────────────────┐
│ H1 (Anton): GẠCH LÁT NỀN & ỐP TƯỜNG PHÒNG KHÁCH SANG TRỌNG        │ TÓM TẮT NHANH (<dl>)    │
│ Intro (≤ 60 chữ): Tổng hợp các mẫu gạch phòng khách xương đá...   │ 124 mẫu  │ Từ 265k/m²   │
│ [Pills nhanh] · [↓ Xem bảng thông số kỹ thuật chuẩn]              │ Khổ 80x80│ Có mẫu tại kệ│
├───────────────────────────────────────────────────────────────────┴─────────────────────────┤
│ VISUAL SUB-SILO TILES (Cuộn ngang, thẻ 4px có ảnh vân 48x48px, ưu tiên trỏ URL tĩnh D-29):  │
│ [[Ảnh] Gạch 80x80 (48)] [[Ảnh] Gạch 60x120 (32)] [[Ảnh] Vân đá (54)] [[Ảnh] Giả gỗ (26)]    │
├─────────────────────────────────────────────────────────────────────────────────────────────┤
│ STICKY TOOLBAR: [Ẩn/Hiện bộ lọc (3↔4 cột)] │ 124 mẫu │ Đang lọc: [80x80 ×] [Xóa tất cả]     │
│                                                                    Sắp xếp: [Mới về ▾]      │
├────────────────────┬────────────────────────────────────────────────────────────────────────┤
│ <drv-filter-form>  │ ┌──────────────────────┐ ┌──────────────────────┐ ┌──────────────────┐ │
│ (1 <form> duy nhất)│ │ [-14%] [Kệ A-08]     │ │ [Mới 2026] [Kệ B-02] │ │ [Dự án]          │ │
│ [ ] Có mẫu Showroom│ │      ẢNH 1:1         │ │      ẢNH 1:1         │ │     ẢNH 1:1      │ │
│ ▾ Kích thước (1)   │ │  (Hover: gallery[0]) │ │  (Hover: gallery[0]) │ │                  │ │
│   [60x60] [80x80●] │ ├──────────────────────┤ ├──────────────────────┤ ├──────────────────┤ │
│ ▾ Bề mặt           │ │ [80x80] [Bóng] · SKU │ │ [60x120] [Matt R10]  │ │ [120x240] [Matt] │ │
│ ▾ Màu sắc (Swatch) │ │ h3: Gạch Lát Nền...  │ │ h3: Gạch Ốp Lát...   │ │ h3: Đá Nung Kết..│ │
│ ▸ Vân & Chất liệu  │ │ 385.000đ/m²          │ │ 520.000đ/m²          │ │ Liên hệ báo giá  │ │
│ ▸ Khoảng giá/m²    │ │ ≈ 246.400đ/viên · 46t│ │ ≈ 374.400đ/viên · 18t│ │ 2,88 m²/thùng    │ │
│ ▸ Chống trượt R    │ │      [Thêm vào giỏ]  │ │      [Thêm vào giỏ]  │ │     [+ Báo giá]  │ │
│ ▸ Độ hút nước / Dày│ └──────────────────────┘ └──────────────────────┘ └──────────────────┘ │
│                    │       Đang xem 24 / 124 mẫu · [Thanh tiến độ] · [Xem thêm 24 mẫu]      │
├────────────────────┴────────────────────────────────────────────────────────────────────────┤
│ #bang-thong-so-geo (.drv-geo-card / .drv-geo-table) · Bài SEO Footer (4 phần) · Khối FAQ    │
└─────────────────────────────────────────────────────────────────────────────────────────────┘
```

- **Catalog Hero & Neo thông số:** Ngoài `H1`, đoạn `drv_intro` và hộp `<dl>` tóm tắt nhanh, dưới chân intro có hàng chip điều hướng nhanh và liên kết neo trượt mượt xuống **`#bang-thong-so-geo`** cuối trang để thợ thầu/KTS tra cứu tiêu chuẩn ngay.
- **Visual Sub-Silo Tiles (`D-29`):** Mọi thẻ nhánh con bắt buộc ưu tiên gắn `<a href>` tới **URL Collection tĩnh** tương ứng (`/gach-80x80`, `/gach-van-da`); chỉ dùng liên kết 1 tham số khi nhánh đó không nằm trong 28 Smart Collections.
- **Sticky Toolbar & Chuyển đổi cột (`3↔4` cột):** Thanh công cụ bám dính (`z-index: var(--z-sticky)`) có nút thu gọn/mở rộng cột lọc bên trái trên desktop (khi ẩn sidebar, lưới sản phẩm tự mở rộng từ 3 cột lên 4 cột). Giá trị sắp xếp mặc định `moi-ve` không ghi vào URL.
- **Kiến trúc 1 `<form>` Bộ lọc dùng chung Desktop & Mobile:**
  - Trang chỉ xuất **1 thẻ `<form>` duy nhất** bên trong `<drv-filter-form>` (công tắc `Có mẫu tại Showroom` đặt trên cùng, 3 nhóm đầu mở sẵn, mỗi nhóm hiện badge số tiêu chí đang chọn, tự động ẩn facet trùng chiều 1 của danh mục hiện tại như ẩn `khong-gian` trên `/gach-phong-khach`).
  - Trên mobile (`< 1024px`), khi bấm nút mở bộ lọc, JS di chuyển chính phần tử `<form>` này vào trong `<dialog>` bottom-sheet và trả về chỗ cũ khi đóng (tránh nhân đôi DOM gây trùng `name` input).
  - Mọi thay đổi lọc gọi `/wp-json/dravit/v1/catalog`, đồng bộ thanh địa chỉ qua `history.pushState`; nút Load More hiển thị tiến độ và kèm thẻ `<a rel="next">` cho trình duyệt tắt JS.
- **Cuối trang & Empty State:**
  - Cuối trang gồm khối **Bảng thông số kỹ thuật chuẩn GEO** (`id="bang-thong-so-geo"`, render từ `drv_geo_table`), bài viết SEO Footer 4 phần và khối **FAQ** (Schema `FAQPage` sinh trực tiếp từ chính mảng câu hỏi đang hiển thị trên trang).
  - **Trạng thái 0 kết quả (Empty State):** Giải thích rõ không có mẫu khớp tổ hợp đang chọn, kèm 2 nút hành động trực tiếp: **`[Xóa bộ lọc hiện tại]`** và **`[Gửi ảnh mẫu / Hỏi kho qua Zalo]`**.

### 4.2 Giải phẫu Card Sản phẩm Chuẩn DRAVIT (`.drv-card--priced` & `.drv-card--quote`)

1. **Khung ảnh vuông `1:1` (nền `--c-champagne`):**
   - Ảnh chính là mặt gạch hoặc phối cảnh chuẩn; khi hover trên desktop chuyển mượt (`opacity` + `scale(1.04)`) sang ảnh đầu tiên trong thư viện (`gallery[0]`).
   - **Cụm Badge góc trên:** Hỗ trợ tối đa 2 nhãn bên trái + nhãn kệ bên phải:
     - Chip giảm giá **`-X%`** (nền `--c-sale`, **chỉ hiển thị trên sản phẩm `priced` đang giảm giá**, cấm gắn lên sản phẩm `quote`).
     - Nhãn nổi bật **`drv_card_label`** (`≤ 20` ký tự như `Bán chạy`, `Mới 2026`, fallback tự động theo ngày đăng/doanh số) hoặc nhãn **`Tạm hết`** khi `out_of_stock`.
     - Nhãn vị trí trưng bày **`Kệ {drv_showroom_shelf}`** (VD: `Kệ A-08`, nền `--c-amber-100` chữ `--c-amber-800`) khi sản phẩm có mẫu thật tại showroom.
   - **Không có nút Wishlist (trái tim)** ở bất kỳ góc nào của card (`D-26`).
2. **Hàng Meta Kỹ thuật (ngay dưới ảnh, ngăn bằng đường ron mảnh):**
   - Bên trái: Pill kích thước (`80x80`) + Pill bề mặt / chống trượt (`Matt R10`).
   - Bên phải: Mã `SKU` (`DRV-8801M`, font `--f-mono`, màu `--c-text-muted`).
3. **Tiêu đề sản phẩm (`<h3>`):** Thẻ `<h3>` chứa `<a class="drv-card__link">`, font Quicksand 600 `15px`, cố định 2 dòng (`line-clamp: 2`, `min-height: 2.8em`) để lưới phẳng tuyệt đối.
4. **Chân card theo 2 trạng thái giá (`D-20`, `D-24`):**
   - **`.drv-card--priced` (Có giá niêm yết):**
     - Cột trái: Dòng 1 **`385.000đ/m²`** (Quicksand 700, `18px`, `--c-navy-900`; hoặc `--c-sale` kèm giá gốc gạch ngang khi KM); Dòng 2 **`≈ 246.400đ/viên · Còn 46 thùng`** (hoặc `Sắp hết` khi dưới ngưỡng tồn kho thấp).
     - Cột phải: Nút **`[Thêm vào giỏ]`** (`data-drv-action="add-to-cart"`, gửi đúng số nguyên viên tương ứng 1 đơn vị mặc định `drv_default_buy_unit` / `drv_qty_min`). Nếu `out_of_stock`, đổi thành nút xem chi tiết / liên hệ đặt hàng.
   - **`.drv-card--quote` (Không niêm yết giá - Liên hệ báo giá):**
     - Cột trái: Dòng 1 **`Liên hệ báo giá`**; Dòng 2 quy cách đóng gói **`1,92 m²/thùng (3 viên)`**.
     - Cột phải: Nút **`[+ Báo giá]`** (`data-drv-action="quote-add"`, `aria-label="Thêm vào danh sách báo giá"`, đưa thẳng SKU vào bảng BOQ).

---

## 5. Trang Chi tiết Sản phẩm (`single-product.php` — `D-20`, `D-24`, `D-26`, `D-30`)

### 5.1 Wireframe Khu vực Mua hàng (Above the Fold)

```text
Desktop (≥ 1024px): Cột trái 7/12 (Gallery 6 vai trò) │ Cột phải 5/12 (Sticky Buy Box)
┌ Breadcrumb: Trang chủ / Gạch / Gạch lát nền / Gạch Lát Nền Dravit... (IL-04) ───────────────┐
├───────────────────────────────────────────┬─────────────────────────────────────────────────┤
│ GALLERY KIẾN TRÚC 6 VAI TRÒ (7/12 cột)    │ HỘP THÔNG TIN & ĐẶT HÀNG (5/12, Sticky)         │
│ ┌───────────────────────────────────────┐ │ Mã: DRV-8801M [Sao chép] │ ● Còn 46 thùng       │
│ │   ẢNH 1: PHỐI CẢNH PHÒNG (LCP)        │ │ Kệ Showroom: A-08                               │
│ │   (fetchpriority="high", không lazy)  │ │                                                 │
│ └───────────────────────────────────────┘ │ <h1>Gạch Lát Nền Dravit Vân Đá Marble           │
│ ┌───────────────────┐ ┌─────────────────┐ │ Calacatta 80x80 Bóng Kiếng</h1>                 │
│ │ ẢNH 2: CẬN VÂN    │ │ ẢNH 3: DƯỚI ĐÈN │ │                                                 │
│ │ [Tải Map CAD/3D]  │ │ THỰC TẾ         │ │ 385.000đ/m²   450.000đ/m² [-14%]                │
│ └───────────────────┘ └─────────────────┘ │ ≈ 246.400đ/viên · 1 thùng = 3 viên = 1,92 m²    │
│ ┌───────────────────┐ ┌─────────────────┐ │ (Trọng lượng: 42 kg/thùng · Đã gồm VAT)         │
│ │ ẢNH 4: ĐƯỜNG RON  │ │ ẢNH 5 & 6: KỆ   │ ├─────────────────────────────────────────────────┤
│ │ & MÀI CẠNH        │ │ SHOWROOM & CẠNH │ │ Màu cùng dòng:  (○ Kem) (● Trắng vân) (<a href>)│
│ └───────────────────┘ └─────────────────┘ │ Kích thước:     [60x60] [80x80●] [60x120]       │
│ (Click bất kỳ ảnh nào -> PhotoSwipe 5)    │ Bề mặt:         [Bóng kiếng●] [Mờ Matt]         │
│                                           ├─────────────────────────────────────────────────┤
│                                           │ <drv-unit-calc> (Tab theo drv_buy_units):       │
│                                           │ Mua theo: [Theo m²●] [Theo thùng] [Theo viên]   │
│                                           │ [ - ] [  10  ] [ + ] m²    [Tùy chọn hao hụt ▾] │
│                                           │ (Đang cộng 5% hao hụt: 0% | 5%● | 10%)          │
│                                           │ -> Thực nhận: 18 viên = 6 thùng (11,52 m² ·     │
│                                           │    ~252 kg) · Tạm tính: 4.435.200đ              │
│                                           ├─────────────────────────────────────────────────┤
│                                           │ <form method="post">:                           │
│                                           │ [     MUA NGAY     ]   [   THÊM VÀO GIỎ   ]     │
│                                           │ Công trình lớn? [Thêm vào DS báo giá]           │
│                                           ├─────────────────────────────────────────────────┤
│                                           │ BẢNG 8 THÔNG SỐ CỐT LÕI (Link Silo IL-03)       │
│                                           │ + <details> Xem thêm thông số chuyên sâu        │
└───────────────────────────────────────────┴─────────────────────────────────────────────────┘
```

### 5.2 Đặc tả Tương tác & Quy tắc Nghiệp vụ trên PDP

1. **Gallery 6 vai trò ảnh & Tải Map CAD (`drv_img_role`, `drv_map_file_id`):**
   - Ảnh đầu tiên là phần tử LCP duy nhất (`fetchpriority="high"`, không lazy); các ảnh tiếp theo gắn nhãn vai trò từ attachment meta (`Phối cảnh`, `Cận vân`, `Ánh sáng`, `Đường ron`, `Tại kệ Showroom`, `Cạnh & độ dày`).
   - Nút **`[Tải Map gạch CAD/3D]`** xuất hiện khi SKU có `drv_map_file_id` (tải file nén/ảnh mặt vân độ phân giải cao cho KTS). Click vào bất kỳ ảnh nào sẽ `import()` động module **PhotoSwipe 5**.
2. **Mã SKU & Nút sao chép chuẩn Accessibility:**
   - Cụm `Mã: DRV-8801M` và nút `.drv-copy-inline` **bắt buộc đặt ở dòng meta phía trên, tách biệt bên ngoài thẻ `<h1>`** để không làm bẩn nội dung heading đối với công cụ tìm kiếm và trình đọc màn hình.
3. **Biến thể cùng dòng bằng Link SSR (`drv_series` — `D-26`):**
   - Tối đa 3 trục (**Màu sắc** dùng `.drv-swatch-dot` + `drv_color_name`, **Kích thước**, **Bề mặt**) render bằng các thẻ **`<a href>` SSR** trỏ thẳng tới URL của SKU anh em cùng `drv_series` (mẫu hiện tại mang `aria-current="page"`). Nghiêm cấm đổi giá, ảnh hoặc tự ghép mã SKU giả phía client.
4. **Bộ tính & Chọn đơn vị mua theo cấu hình sản phẩm (`<drv-unit-calc>` — `D-24`):**
   - Hiển thị các tab đơn vị được phép nhập dựa trên `drv_buy_units` (**`Theo m²`** | **`Theo thùng`** | **`Theo viên`**) và kích hoạt sẵn `drv_default_buy_unit`.
   - Giao diện 1 dòng gọn gàng với `.drv-stepper` kèm nút mở nhanh mức hao hụt thi công **`0%` / `5%` / `10%`** (mặc định từ `drv_waste_rate` hoặc `drv_settings.order.waste_default`).
   - Tuân thủ `drv_round_mode`: nếu `box` (mặc định gạch hộp), tự làm tròn lên chẵn thùng (`qty_step = pieces_per_box`); nếu `piece` (viên lẻ / tấm khổ lớn), cho phép nhập lẻ viên (`qty_step = 1`) từ `drv_qty_min`.
   - Vùng kết quả (`aria-live="polite"`) luôn xuất đủ **số viên, số thùng, diện tích thực nhận `m²` và tổng khối lượng `kg`**. Tuyệt đối không fallback sang thông số cứng của mã sản phẩm mẫu nếu thiếu data attribute.
5. **Form Mua hàng & Chế độ `priced` vs `quote` (`D-20`, `D-30`):**
   - **Với sản phẩm có giá (`priced`):** Cả 2 nút **`Thêm vào giỏ`** và **`Mua ngay`** nằm trong `<form class="cart" method="post">`; trường `quantity` gửi lên WooCommerce **luôn là số nguyên viên** (`data-drv-qty-pieces`), kèm hidden `drv_m2_input` và `drv_waste_pct`. Nút **`Mua ngay`** gửi kèm `drv_buy_now=1` để thêm vào giỏ và chuyển thẳng tới `/thanh-toan` (chạy đúng cả khi trình duyệt tắt JS). Dưới nút chính có liên kết **`Thêm vào DS báo giá`** cho khách làm dự toán nhiều phòng.
   - **Với sản phẩm không niêm yết giá (`quote`):** Ẩn tổng tiền, giữ nguyên toàn bộ máy tính `m² ⇄ thùng ⇄ viên ⇄ kg`; hiển thị nhãn **`Liên hệ báo giá`** kèm thời gian phản hồi từ `drv_settings.order.sla_text`; cụm CTA chính chuyển thành **`Liên hệ báo giá`** (truyền đúng số `m²` đang tính trên `<drv-unit-calc>`) và **`Thêm vào DS báo giá`** (`data-drv-action="quote-add"`).
6. **Bảng Thông số 2 tầng:**
   - **8 dòng cốt lõi luôn mở:** Công năng · Không gian phù hợp · Kích thước danh nghĩa & thực tế (`mm`) · Chất liệu xương gạch · Vân gạch & số mặt vân · Bề mặt & Chống trượt `R` · Độ hút nước & Độ chịu mài mòn `PEI` · Biến thiên màu (`Shade V1-V4`, tách biệt hoàn toàn với mã lô kho `Lot`). Các giá trị trùng Smart Collection tự động gắn link tĩnh (`IL-03`).
   - **Khối `<details>` mở rộng:** Cường độ chịu uốn (`N/mm²`), lực gãy (`N`), cấp chống bám bẩn (`Class 1-5`), độ rộng đường ron khuyến nghị (`mm`), ghi chú dưới ánh sáng đèn.

### 5.3 Khu vực Dưới (Below the Fold) & Sticky Purchase Bar

- **Lưới 6 Mặt vân ngẫu nhiên (`drv_face_image_ids`):** Hiển thị trực quan các mặt vân không lặp của mẫu gạch (mở zoom chung qua PhotoSwipe 5).
- **3 Khối Lưu ý Trách nhiệm (`<details>` / thẻ ngang):** (1) Biến thiên sắc độ `Shade` & cam kết xuất cùng mã lô `Lot` cho một lần đặt; (2) Độ rộng đường ron khuyến nghị (`1,5–2mm` với cạnh mài Rectified); (3) Quy trình kiểm hàng và báo phí vận chuyển theo tải trọng. *(Ẩn khối gợi ý mua kèm keo/ke ron ở Phase 1 theo `D-26`)*.
- **Lưới 8 Sản phẩm tương tự (`4 cột × 2 hàng`, `IL-06`)** + dải **Sản phẩm vừa xem** (đọc tối đa 8 ID từ `localStorage` `drv_viewed`, nạp thẻ qua `/wp-json/dravit/v1/products?include=...`).
- **Sticky Purchase Bar (hiện ở đáy màn hình khi cuộn qua khỏi `<form class="cart">`, `z-index: var(--z-header)`):**
  - **Chế độ `priced`:** Hiển thị ảnh nhỏ + tên + đang chọn `{X} viên ({Y} thùng · {Z} m²)` + tạm tính + nút `Thêm vào giỏ` & `Mua ngay`.
  - **Chế độ `quote`:** Hiển thị ảnh nhỏ + tên + đang tính `{Y} thùng ({Z} m² · ~{W} kg)` + nút `Thêm vào DS báo giá` & `Liên hệ báo giá`.

---

## 6. Giao dịch Thương mại & Dự toán BOQ (Commerce & Quote Flow)

### 6.1 Giỏ hàng (`/gio-hang`) & Mini-cart Drawer (`D-24`)
- Đầu trang `/gio-hang` hiển thị thanh tiến trình 3 bước `.drv-steps` (`01 Giỏ hàng` đang active).
- **Quy tắc hiển thị bất biến trên cả `/gio-hang` và Mini-cart Drawer:** Mỗi dòng sản phẩm luôn hiển thị rõ **đơn giá kép (`385.000đ/m²` và `246.400đ/viên`)**, cụm tăng giảm `.drv-stepper--cart` theo đúng bước nhảy sản phẩm (kèm nút nhanh `+1 thùng`) và dòng quy đổi đầy đủ **`6 thùng · 11,52 m² · ~252 kg`** (tuyệt đối không ẩn giá/viên hay dòng quy đổi trong drawer).
- **Cảnh báo lẻ thùng:** Với SKU cho phép mua lẻ viên (`drv_round_mode = piece`), nếu tổng số viên chưa chẵn thùng, hiển thị dải nhắc nhẹ màu `--c-warning-bg` kèm nút 1-click **`[Làm tròn chẵn thùng (+N viên)]`**.
- **Hộp tổng kết `.drv-summary-box`:** Tổng số thùng · Tổng diện tích `m²` · Tổng trọng lượng ước tính `kg` · **Gợi ý phương tiện giao hàng** theo ngưỡng tải trọng (`drv_settings.order.vehicle_thresholds`: ba gác/bán tải/xe tải 1,5–5 tấn) · Phí vận chuyển: *"Báo sau khi xác nhận đơn"* (không ghi `"0đ"`) · Nút tiến hành thanh toán + Liên kết chuyển giỏ hàng sang Danh sách Báo giá BOQ.
- **Phạm vi sạch Phase 1:** Chủ động tắt ô nhập mã ưu đãi (coupon) và ẩn khối mua kèm phụ kiện. Khi giỏ trống, hiển thị thông báo gọn kèm 4 thẻ dẫn về 4 nhóm Silo chính.

### 6.2 Danh sách Báo giá Công trình BOQ (`/danh-sach-bao-gia` — `template-quote.php`, `D-23`)
> Đặc tả chi tiết luồng dữ liệu, công thức và xử lý Admin: xem [17_Quote_BOQ_And_Lead_Spec.md](17_Quote_BOQ_And_Lead_Spec.md).

- Đọc/ghi trực tiếp kho `localStorage` chuẩn hóa `drv_quote_v2` (kết nối thông suốt từ nút `+ Báo giá` trên Card, PDP, `<drv-quote-panel>` và trang Giỏ hàng) và hydrate thông tin sản phẩm tươi từ `GET /wp-json/dravit/v1/products?include=...`.
- **Tổ chức theo Hạng mục Không gian:** Gom dòng theo **Phòng (`Phòng khách`, `Phòng bếp`, `WC tầng 1`...) → Hạng mục ốp/lát (`Lát nền`, `Ốp tường`, `Điểm nhấn`) → Diện tích `m²`**, cho phép chỉnh mức hao hụt **`0%` / `5%` / `10%`** toàn phiếu hoặc từng dòng, cộng thêm nhanh mẫu gạch ngay trong bảng.
- **Hỗ trợ hỗn hợp `priced` + `quote`:** Các mẫu `quote` (*Liên hệ báo giá*) nằm chung bảng với mẫu `priced`, vẫn cộng dồn đầy đủ diện tích `m²`, số thùng và khối lượng `kg` toàn công trình.
- **Bậc chiết khấu khối lượng dự kiến:** Tự động hiển thị mức chiết khấu tham khảo theo tổng diện tích (`≥ 30 m²: 5%`, `≥ 100 m²: 8%` từ `drv_settings.quote.volume_tiers`) kèm ghi chú rõ đây là mức dự kiến để nhân viên xác nhận cùng phí vận chuyển.
- **Tiện ích xuất & Gửi yêu cầu:** Hỗ trợ **In phiếu dự toán A4** (`@media print`), **Tải file CSV**, **Sao chép tóm tắt gửi Zalo**, và Form **`Gửi yêu cầu báo giá`** (gồm Họ tên, SĐT, Tỉnh/TP, Vai trò `Chủ nhà / Nhà thầu / KTS`, Ghi chú, checkbox đồng ý bảo mật dữ liệu cá nhân, honeypot + nonce tươi -> lưu vào CPT `drv_lead` với `_drv_lead_type = boq`).

### 6.3 Thanh toán (`/thanh-toan` — Classic Shortcode 2 cột, `D-15`, `D-16`, `D-30`)
- **Distraction-free Topbar:** Thay Header đầy đủ bằng thanh đỉnh tối giản chỉ gồm Logo DRAVIT, thanh tiến trình `.drv-steps` (Bước 2/3) và Hotline hỗ trợ.
- **Bố cục 2 cột trên desktop (7/12 trái · 5/12 phải `.drv-summary-box--sticky`) / 1 cột trên mobile:**
  1. **Khối 1 - Người nhận hàng:** Họ và tên, Số điện thoại VN (kiểm tra regex 10 số), Email (tùy chọn).
  2. **Khối 2 - Địa chỉ giao hàng 2 cấp & Điều kiện hạ hàng:** Chọn **Tỉnh/Thành phố (34 đơn vị)** -> nạp động danh sách **Phường/Xã** tương ứng (**mặc định để trống nhắc khách chọn, không tự chọn sẵn phường đầu tiên**) + Địa chỉ số nhà/tên đường + gợi ý tuyến giao theo tỉnh + hàng **Chip chọn nhanh ghi chú giao hàng** (*Hẻm nhỏ dưới 2,5m*, *Giao giờ hành chính*, *Chung cư cần đăng ký thang hàng*, *Gọi trước 30 phút*).
  3. **Khối 3 - Xuất hóa đơn GTGT / VAT (tùy chọn, `_drv_vat_*`):** Checkbox *"Yêu cầu xuất hóa đơn VAT công ty"* mở 4 trường: Tên đơn vị, Mã số thuế (kiểm tra `10` hoặc `13` chữ số), Địa chỉ đăng ký, Email nhận hóa đơn điện tử.
  4. **Khối 4 - Phương thức thanh toán:** **Thanh toán khi nhận hàng (COD)** hoặc **Chuyển khoản ngân hàng (VietQR)** (nêu rõ khách chỉ chuyển khoản sau khi nhân viên gọi xác nhận mã lô và báo phí vận chuyển).
- **Cột phải (`woocommerce/checkout/review-order.php`):** Render lại toàn bộ quy đổi `thùng · m² · kg` mỗi khi WC chạy `update_checkout`, hiện hộp giải thích phí vận chuyển theo tổng tải trọng `{total_kg} kg`, checkbox đồng ý điều khoản (**mặc định không tick sẵn**) và nút **`Đặt hàng`**. Trên mobile có thanh tóm tắt thu gọn đầu trang và thanh Sticky CTA cố định ở đáy.

### 6.4 Đặt hàng thành công (`/thanh-toan/dat-hang-thanh-cong/{id}` — `D-22`, `D-30`)
> Đặc tả trạng thái HPOS, Action Scheduler và Email: xem [18_Order_Ops_And_Email_Spec.md](18_Order_Ops_And_Email_Spec.md).

- Hiển thị mã đơn hàng định dạng **`DRV-{order_id}`**, thanh **Timeline 4 bước** (`Tiếp nhận đơn` -> `Xác nhận lô & phí giao` -> `Thanh toán / Chuẩn bị hàng` -> `Giao hàng tới công trình`) và tách biệt giao diện theo đúng trạng thái thực của đơn:
  - **Trạng thái A - Chờ xác nhận (`wc-cho-xac-nhan`, ngay sau khi đặt):** Thông báo đã giữ hàng tạm thời và thời gian nhân viên liên hệ theo `drv_settings.order.sla_text` để xác nhận tồn kho cùng mã lô và báo phí vận chuyển. **Nghiêm cấm hiển thị ảnh VietQR hoặc thông tin tài khoản chuyển tiền ở trạng thái này.**
  - **Trạng thái B - Chờ chuyển khoản (`wc-on-hold` với phương thức `bacs`, sau khi Admin nhập phí ship và xác nhận):** Hiển thị bảng tổng thanh toán cuối cùng (đã gồm phí vận chuyển), **ảnh mã VietQR** (`img.vietqr.io` đúng tổng tiền + nội dung `DRV{order_id}` chỉ gồm chữ cái và chữ số) kèm các nút `.drv-copy-inline` sao chép Số tài khoản, Số tiền chính xác và Nội dung `DRV{order_id}`. Không hứa hẹn đối soát tự động 3 phút; hướng dẫn khách gửi ảnh biên lai qua Zalo nếu cần xuất kho gấp và nêu rõ thời hạn giữ đơn (`transfer_due_days`).
  - **Biến thể COD (`wc-processing`) & Đơn hủy (`wc-cancelled`):** Với COD đã xác nhận phí giao, hiển thị tổng số tiền chuẩn bị thanh toán khi nhận hàng và phương tiện giao dự kiến; với đơn đã hủy, hiển thị lý do/trạng thái và nút liên hệ hỗ trợ.

---

## 7. Silo Cẩm nang (`home.php`, `category.php`, `single.php`)

> Đặc tả chi tiết taxonomy `/chu-de/{slug}`, block styles, patterns và shortcodes: xem [20_Blog_Editorial_And_Content_Model.md](20_Blog_Editorial_And_Content_Model.md).

### 7.1 Trang Lưu trữ Cẩm nang (`/cam-nang` & `/chu-de/{slug}`)
1. **Thanh Chuyên mục & Tìm kiếm bài viết:** Danh sách liên kết `<a href="/chu-de/{slug}">` (`Tất cả bài viết`, `Chọn gạch theo phòng`, `Kỹ thuật ốp lát & đường ron`, `Phối màu & xu hướng`, `Định mức & báo giá`, mục hiện tại gắn `aria-current="page"` - không gán sai `role="tablist"` lên thẻ điều hướng) + form tìm kiếm bài viết GET chuẩn server.
2. **Editorial Hero Bento (12 cột: 5/4/3):** Cột 5/12 bài trụ cột (`drv_is_pillar`) + Cột 4/12 danh sách 3 bài mới ngăn bằng đường ron + Cột 3/12 hộp **"Được tra cứu nhiều"** đánh số `01–05` (font Anton).
3. **3–4 Cụm Chủ đề Chuyên sâu (Topic Clusters):** Mỗi cụm gồm tiêu đề `H2`, liên kết `"Xem tất cả"` về `/chu-de/{slug}` và lưới 3 card bài viết (ảnh 3:2 sạch không chèn chữ, chuyên mục, tiêu đề `H3`, thời gian đọc).
4. **Bảng Tra cứu Nhanh Chỉ số Chống trượt `R9–R13`:** Bảng thông số trực quan dùng `.drv-r-badge` đối chiếu từng khu vực trong nhà với bề mặt và Collection tương ứng.
5. **Dải Công cụ & CTA Báo giá Công trình** + **Phân trang chuẩn SEO (`/page/n`)**.

### 7.2 Trang Chi tiết Bài viết Cẩm nang (`single.php`)

```text
Desktop: Cột nội dung chính 8/12 (.drv-prose) │ Cột phải Sticky 4/12
┌ Breadcrumb (1 nguồn duy nhất): Trang chủ / Cẩm nang / Chọn gạch theo phòng / Tiêu đề bài ───┐
├─────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ H1: Cách chọn gạch lát nền nhà tắm chống trơn   │ STICKY SIDEBAR (4/12 cột):                │
│ Tác giả: [Tên thật] · Thẩm định: KS. [Tên thật] │ ┌─ <drv-toc> MỤC LỤC + TIẾN ĐỘ ĐỌC ──────┐│
│ Cập nhật: 05/11/2026 · 6 phút đọc               │ │ 1. Tiêu chuẩn chống trượt R cho nhà tắm││
│                                                 │ │ 2. Bảng đối chiếu bề mặt & độ hút nước ││
│ ┌─ NHỮNG ĐIỂM CHÍNH (.drv-key-takeaways) ──────┐│ │ 3. Các mẫu gạch nhà tắm R10-R11        ││
│ │ • Sàn nhà tắm ướt xà phòng cần gạch đạt từ   ││ └────────────────────────────────────────┘│
│ │   chỉ số Chống trượt R10 trở lên...          ││ ┌─ DỰ TOÁN & XEM MẪU THẬT ───────────────┐│
│ └──────────────────────────────────────────────┘│ │ [Mở bảng tính BOQ theo phòng]          ││
│                                                 │ │ Showroom: [Địa chỉ] · [Chỉ đường Maps] ││
│ [Ảnh phối cảnh sạch] + [Shop the Look Bar]      │ └────────────────────────────────────────┘│
│ [Hộp .drv-callout --tip / --warning]            │                                           │
│ [Bảng thông số .drv-geo-table / Ưu - Nhược]     │                                           │
│ [Shortcode [drv_products] & [drv_price_table]]  │                                           │
│ [Form nhận mẫu gạch / tư vấn [drv_lead_form]]   │                                           │
│ [Khối FAQ cuối bài] + [Hộp Tác giả & Kỹ sư]     │                                           │
└─────────────────────────────────────────────────┴───────────────────────────────────────────┘
Mobile: Nút nổi "Mục lục" (z-index: --z-fab, đặt trên Mobile Bar) mở bottom-sheet mục lục.
```

- **Quy tắc bắt buộc trên bài viết chi tiết:**
  - **Mục lục (`<drv-toc>`):** Sinh danh sách neo `H2/H3` sẵn từ PHP trên server (SEO đọc được ngay), JS chỉ làm nhiệm vụ highlight mục đang xem, cập nhật thanh tiến độ đọc và mở `<dialog>` mục lục từ nút nổi trên mobile (`bottom: calc(var(--mobile-bar-h) + 16px)`, `z-index: var(--z-fab)`).
  - **Dữ liệu sản phẩm & giá luôn lấy động:** Mọi thẻ sản phẩm nhúng (`[drv_products]`), thanh **Shop the Look Bar** dưới ảnh phối cảnh (`[drv_shop_the_look]`) và bảng giá tham khảo (`[drv_price_table]`) đều truy xuất giá `đ/m²` và trạng thái tồn kho thời gian thực từ WooCommerce; nghiêm cấm gõ cứng giá hay tồn kho vào nội dung bài.
  - **E-E-A-T chân thực & Schema sạch:** Hộp Tác giả và Kỹ sư thẩm định (`drv_reviewer_id`) chỉ lấy từ hồ sơ nhân sự thật (`O-13`). Chỉ xuất 1 đồ thị JSON-LD `BlogPosting` + `BreadcrumbList` qua Rank Math filter (cấm gắn thêm microdata trùng lặp hoặc viết inline `<script>` và widget bình chọn số liệu giả).

---

## 8. Trang Liên hệ (`/lien-he`), Giới thiệu, Chính sách & 404

### 8.1 Bố cục các trang hệ thống

| Trang | Template & Slug chuẩn | Đặc tả bố cục & chức năng |
|---|---|---|
| **Liên hệ & Showroom** | `page-templates/template-contact.php` (`/lien-he`, redirect `301` từ `/showroom` — `D-29`) | **Layout 2 cột (6/6 hoặc 5/7):**<br>• **Cột trái:** Thông tin pháp nhân đầy đủ (Tên công ty, MST, Email chính thức), danh sách thẻ **Showroom** & **Tổng kho** từ `drv_settings.showrooms` (địa chỉ hành chính 2 cấp mới, giờ mở cửa kèm badge trạng thái server, hướng dẫn đỗ ô tô, nút mở Google Maps).<br>• **Cột phải:** Form hợp nhất (`Liên hệ tư vấn / Đặt lịch xem mẫu / Nhận báo giá`) gửi về `POST /dravit/v1/lead` (`drv_lead`), gồm Họ tên, SĐT, Tỉnh/TP, Nhu cầu, Ghi chú, **checkbox đồng ý xử lý dữ liệu cá nhân**, honeypot và nonce tươi. Xuất Schema `ContactPage` + `LocalBusiness`. |
| **Giới thiệu** | `page.php` (`/gioi-thieu`) | Bản copy chuẩn tại `10_Brand_Voice_And_Content_Guide.md` §4.5 + ảnh thực tế showroom/kho hàng + các chuẩn mực kiểm định vật liệu + dải liên hệ cuối trang. |
| **5 Trang Chính sách** | `page-templates/template-policy.php` | **Layout 2 cột:** Cột trái (3/12) là menu chuyển nhanh giữa 5 chính sách (Vận chuyển, Đổi trả & kiểm hàng, Thanh toán, Bảo mật thông tin, Điều khoản) + mục lục trang; Cột phải (9/12, `max-width: 70ch`) trình bày văn bản rõ ràng kèm ngày hiệu lực. |
| **404 Không tìm thấy** | `404.php` | Thông điệp ngắn gọn giải thích liên kết không tồn tại hoặc mã gạch đã ngừng kinh doanh + **Ô tìm kiếm mã gạch/kích thước (`get_search_form()`)** + **Lưới 4 thẻ danh mục tĩnh trọng điểm** từ `drv_settings.catalog.404_collections` (`/gach-lat-nen`, `/gach-phong-khach`, `/gach-van-da`, `/gach-80x80`) + nút hỏi mẫu thay thế qua Zalo. |

### 8.2 Danh sách phần tử chỉ có ở bản Prototype (Loại bỏ 100% khi lên Production)
Khi chuyển đổi 12 file HTML mẫu trong `docs/layout/` sang WordPress theme (xem chi tiết tại [16_Template_Component_Porting_Guide.md](16_Template_Component_Porting_Guide.md)), **bắt buộc gỡ bỏ hoàn toàn**:
- Thanh điều hướng chuyển mẫu đầu trang `.drv-proto-bar` và toàn bộ CSS đi kèm.
- Các thanh giả lập trạng thái (`demo state switcher` trên PDP `priced/quote`, Cart `empty/loose`, Thank-you `State A/B`, AI Finder `mode-switcher` và lớp backdrop thử nghiệm).
- Các khối dữ liệu mẫu nhúng cứng (`window.DRAVIT_PRODUCTS`, `#qs-catalog` JSON inline, script inline ~277 dòng trong bài blog mẫu) và cặp custom element dev `<drv-site-header>` / `<drv-site-footer>`.

---

## 9. Microcopy Giao diện Chuẩn & Accessibility (WCAG 2.2 AA)

- **Nhãn nút & trạng thái thống nhất toàn site** (đối chiếu [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md) §5):
  - Mua lẻ có giá (`priced`): `Thêm vào giỏ` · `Mua ngay`.
  - Sản phẩm không niêm yết giá (`quote`): `Liên hệ báo giá`.
  - Đưa vào dự toán BOQ (`/danh-sach-bao-gia`): `Thêm vào DS báo giá` (trên card gọn: `+ Báo giá`).
  - Gửi biểu mẫu dự toán / tư vấn: `Gửi yêu cầu báo giá`.
  - Bộ tính số lượng: `Theo m²` · `Theo thùng` · `Theo viên` · `Tùy chọn hao hụt` (`0%` / `5%` / `10%`) · `Thực nhận: {X} viên = {Y} thùng ({Z} m² · ~{W} kg)`.
  - Vận chuyển & SLA: `Phí vận chuyển: báo sau khi xác nhận đơn` · thời gian phản hồi đọc từ `drv_settings.order.sla_text`.
- **Accessibility (WCAG 2.2 AA):**
  - `<html lang="vi">`, skip link đầu trang (`#main-content`), đầy đủ landmark `<header>`, `<nav>`, `<main>`, `<aside>`, `<footer>`.
  - Nút sao chép mã SKU đặt ngoài `<h1>`; mọi nút chỉ có icon (đóng dialog, xóa dòng giỏ, nút `+/-` trong `.drv-stepper`) bắt buộc có `aria-label` tiếng Việt rõ nghĩa.
  - Mọi phần tử tương tác hiển thị rõ `--focus-ring` (`2px solid var(--c-teal-500)`) khi điều hướng bàn phím (`:focus-visible`).
  - Kết quả tính toán trong `<drv-unit-calc>`, thông báo sao chép và tổng số sản phẩm lọc được gắn `aria-live="polite"`.
  - Touch target trên mobile tối thiểu `44×44px`; tôn trọng triệt để `@media (prefers-reduced-motion: reduce)`.
