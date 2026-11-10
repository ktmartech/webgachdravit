# 07. Đặc tả UI/UX từng trang (Architectural Grid System)

> Token, màu sắc, typography, component cơ bản: xem [01_Brand_Design_System.md](01_Brand_Design_System.md).
> Giọng văn & khung copy từng trang: xem [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md).
> Tài liệu tham chiếu đối chiếu: `docs/img layout sample/` (đã phân tích ưu/nhược điểm để chắt lọc hành vi chuyển đổi tốt và loại bỏ bố cục rối kiểu chợ điện tử).
> Skill áp dụng: `design-taste-frontend`, `frontend-design`, `ui-ux-designer`, `page-cro`, `form-cro`, `fixing-accessibility`, `baseline-ui`, `web-design-guidelines`.

---

## 1. Triết lý Thiết kế & Điểm Khác biệt Nhận diện (Differentiation Anchor)

### 1.1 Tuyên ngôn thẩm mỹ: "Architectural Grout-Line Grid" (Lưới Kiến trúc Đường Ron)

> **Câu hỏi kiểm tra (`frontend-design`):** *"Nếu chụp màn hình giao diện mà che logo DRAVIT đi, làm sao nhận ra đây là website của DRAVIT?"*
> **Câu trả lời:** Mọi khối thông tin, thẻ sản phẩm, bảng thông số và thanh điều hướng được phân tách bằng **hệ thống đường kẻ ron `1px solid var(--c-navy-200)` thẳng tắp, vuông vức (`border-radius: 4px–8px`)**, kết hợp bảng màu **Brand Primary `#083A4F` + Chữ Deep Ink `#0B192C` + Brand Teal `#0D9488` / `#0F766E` + Warm Amber Pop `#F59E0B` trên nền Clean Slate `#F8FAFC`**, cùng cơ chế **hiển thị song song 2 đơn vị `đ/m²` & `đ/viên`** ở mọi điểm chạm.

### 1.2 Bảng đối chiếu: Chắt lọc từ mẫu tham khảo & Khác biệt hóa cho DRAVIT

| Hạng mục | Mẫu tham khảo (`img layout sample`) | Quyết định thiết kế của DRAVIT (Chắt lọc & Khác biệt hóa) |
|---|---|---|
| **Tổng thể & Khuyến mãi** | Banner top đỏ/vàng rực, 2 banner dọc bám 2 bên màn hình, đồng hồ đếm ngược Flash Sale, nhãn "GIẢM SỐC" răng cưa. | **Loại bỏ hoàn toàn** banner bám 2 bên, đồng hồ đếm ngược giả và tem răng cưa chợ. Dùng nền phẳng `--c-ivory`, lưới 1320px cân đối 2 biên; ưu đãi trình bày chỉn chu bằng badge vuông `--c-sale` (`-15%`). |
| **Header & Mega Menu** | Hàng pill bo tròn nhiều màu; mega menu 6 cột chữ đơn điệu. | **Header 2 tầng ngăn bằng đường ron 1px.** Mega menu **"Silo Matrix 4+1"**: 4 cột tương ứng 4 nhóm Silo (kích thước có hình minh họa tỷ lệ viên gạch) + 1 cột Featured Lookbook nền `--c-champagne`. |
| **Hero Trang chủ** | Chia 3 banner nhỏ + nhét cột "Sản phẩm đã xem" ngay trên màn hình đầu. | **Bento Kiến trúc 8/4:** 8 cột ảnh không gian lát gạch khổ lớn + **Thanh tìm nhanh mẫu gạch** dưới chân; 4 cột phải chia 2 ô (*Đá nung kết Big Slab* & *Showroom thực tế*). "Sản phẩm đã xem" đưa xuống cuối trang. |
| **Khối Danh mục ở Home** | Tab ngang + hàng pill lọc + banner dọc trái + lưới 5 cột. | Nâng cấp thành **"Catalog Explorer Block"**: Tab 4 nhóm công năng + Chip lọc nhanh Không gian/Kích thước + Cột trái là **Lookbook Card** (ảnh phối cảnh + thông số gợi ý của nhóm đang chọn) + Lưới sản phẩm 3–4 cột thoáng rộng. |
| **Đầu trang Danh mục** | Khung carousel khuyến mãi lớn chiếm hết màn hình đầu + hàng icon danh mục hình tròn. | **Loại bỏ** carousel KM đầu trang (đẩy sản phẩm lên ngay màn hình 1). Thay icon tròn bằng **"Visual Sub-Silo Tiles"** hình chữ nhật vuông vức (`4px`) có ảnh cận vân 48×48px + số lượng mẫu. |
| **Card Sản phẩm** | Tên cắt cụt 1 dòng; tag kích thước nhỏ bên phải; chỉ hiện giá/m²; nút tim + giỏ tròn. | **Ảnh 1:1 chuyển sang ảnh phối cảnh/cận vân khi hover.** Dòng meta riêng cho **Tag Kích thước + Bề mặt**. Tên **2 dòng đầy đủ**. Chân card hiện **Giá/m² (chính) + Giá/viên (phụ)** + nút vuông dẫn vào tính số lượng. |
| **Gallery Trang SP** | Lưới ảnh 2 cột dọc trên desktop + nút "Tải map gạch". | **Kế thừa & nâng cấp:** Desktop dùng **Bento Gallery** (1 ảnh chính lớn trên + lưới 2 cột cận vân/phối cảnh dưới) kèm nút **`[↓ Tải ảnh vân gạch (Map CAD/3D)]`** cho KTS; Mobile dùng slider `scroll-snap` 1:1 gọn gàng. |
| **Bộ tính số lượng SP** | Nhập số `m²` → hiện `≈ X viên` + ghi chú làm tròn + Tổng tiền tự động + link "Nhận báo giá riêng" + thanh sticky đáy. | **Nâng cấp thành Bộ chuyển đổi Kép 2 chiều `m² ⇄ Viên`** + tùy chọn **Cộng 5% hao hụt / Chẵn thùng** + **Tổng tiền cập nhật tức thì** + link mở modal Báo giá công trình + **Sticky Purchase Bar** khi cuộn. |
| **Bài viết Cẩm nang** | Có hộp "Những điểm chính" đầu bài, FAQ cuối bài, nút "Nhận báo giá" đè lên góc ảnh, sidebar banner showroom. | Giữ cấu trúc chuẩn GEO (**Key Takeaways đầu bài + Mục lục + FAQ cuối bài**). Thay nút đè lên ảnh bằng **"Thẻ Mã Gạch Trong Ảnh" (Shop the Look bar)** ngay chân ảnh dẫn thẳng tới SKU thực tế. |

---

## 2. Layout Toàn cục (Header, Mega Menu, Footer, Mobile Bar)

### 2.1 Header & Mega Menu "Silo Matrix 4+1"

```text
Desktop (≥ 1024px) — Nền --c-white, viền đáy 1px solid --c-navy-200
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ TẦNG 1 (68px):                                                                         │
│ [LOGO DRAVIT]   [ 🔍 Tìm mẫu gạch, mã SKU (DRV-8801), kích thước 80x80...     ]        │
│                 📍 Showroom (8:00-21:00)  │  📞 09xx.xxx.xxx  │  [Báo giá]  [Giỏ (2)]  │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ TẦNG 2 (44px, ngăn bởi đường ron 1px):                                                 │
│ Tất cả gạch  │  Theo công năng ▾  │  Theo không gian ▾  │  Vân & chất liệu ▾  │        │
│ Theo kích thước ▾  │  Đá nung kết  │  Gạch ưu đãi          Cẩm nang  │  Về Dravit      │
└────────────────────────────────────────────────────────────────────────────────────────┘

Khi mở Mega Menu (Full-width container 1320px, chia 5 cột bằng đường ron dọc 1px):
┌───────────────┬───────────────┬───────────────┬───────────────────┬────────────────────┐
│ CÔNG NĂNG     │ KHÔNG GIAN    │ VÂN & CHẤT    │ KÍCH THƯỚC CHUẨN  │ [Ảnh nền Champagne]│
│ • Gạch lát nền│ • Phòng khách │ • Vân đá      │ [□] Gạch 60x60    │ ĐÁ NUNG KẾT        │
│ • Gạch ốp     │ • Nhà tắm/WC  │ • Giả gỗ      │ [■] Gạch 80x80    │ KHỔ LỚN BIG SLAB   │
│ • Trang trí   │ • Phòng bếp   │ • Terrazzo    │ [▭] Gạch 30x60    │ 120x240 cm         │
│ • Gạch bông   │ • Sân vườn    │ • Vân xi măng │ [▬] Gạch 60x120   │                    │
│ • Bông gió    │ • Mặt tiền    │ • Đá nung kết │ [─] Thanh dài gỗ  │ [Xem bộ sưu tập →] │
│ • Gạch thẻ    │ • Ngoài trời  │ • Porcelain   │ [Xem tất cả 28 →] │                    │
└───────────────┴───────────────┴───────────────┴───────────────────┴────────────────────┘
Mobile (< 1024px): [☰ Menu]   [LOGO DRAVIT]   [🔍]  [Giỏ (2)]   + Thanh đáy cố định (§2.3)
```

- **Quy chuẩn Icon trên toàn bộ giao diện:** Mọi biểu tượng minh họa trong wireframe ASCII bên dưới (như kính lúp, giỏ hàng, điện thoại, mũi tên, dấu cộng/trừ, dấu đóng, dấu kiểm, menu) khi đưa vào code HTML/JS **bắt buộc sử dụng thư viện Phosphor Icons CDN** (`<i class="ph ph-..." aria-hidden="true"></i>`). **Nghiêm cấm** dùng ký tự Unicode (`→`, `▾`, `▸`, `↓`, `⇄`, `×`, `−`, `+`, `✓`, `●`, `☰`) hoặc emoji làm icon.
- **Hành vi Sticky:** Khi cuộn xuống, Tầng 1 thu gọn còn 56px, ẩn Tầng 2 (hiện lại ngay khi cuộn nhẹ lên trên) để tối đa hóa vùng xem gạch mà không gây CLS.
- **Ô tìm kiếm tức thì (Instant Search Dropdown):** Sau khi gõ ≥ 2 ký tự (debounce 200ms), hiển thị khung gợi ý chia 2 cột: Cột trái (4/12) là **Danh mục & Kích thước khớp từ khóa** (`/gach-80x80`, `/gach-van-da`); Cột phải (8/12) là **6 Sản phẩm khớp mã SKU/tên** kèm ảnh vuông 48px, kích thước và giá `đ/m²`.

### 2.2 Footer (2 tầng: Dải Liên hệ Showroom + Chân trang 4 cột)

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ DẢI SHOWROOM & HỖ TRỢ NHANH (Nền --c-champagne, chữ --c-navy-900, viền ron 1px):       │
│ Cần tư vấn chọn gạch & tính số lượng?   │ 📞 Hotline: 09xx.xxx.xxx (8:00 - 21:00)      │
│ [Gửi yêu cầu báo giá]  [Chat Zalo ngay] │ 📍 Showroom: [Địa chỉ] · [Chỉ đường Maps →]  │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ FOOTER CHÍNH (Nền --c-navy-900, chữ --c-ivory, Logo âm bản DRAVIT-04):                 │
│ Cột 1: DRAVIT          │ Cột 2: Danh mục gạch │ Cột 3: Hỗ trợ mua hàng │ Cột 4: Cẩm nang│
│ Tagline, Sứ mệnh,      │ Link 8 collection    │ Chính sách vận chuyển, │ Chọn gạch,     │
│ Thông tin pháp nhân    │ trọng điểm           │ Đổi trả, Thanh toán    │ Kỹ thuật ốp lát│
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### 2.3 Thanh Hành động Cố định Đáy Mobile (`< 1024px`)

- **Trên Trang chủ / Danh mục / Cẩm nang:** Cao 56px (`padding-bottom: env(safe-area-inset-bottom)`), chia 5 ô bằng đường ron dọc: `[☰ Danh mục]` · `[📞 Gọi]` · `[💬 Zalo]` · `[📋 Báo giá]` · `[🛒 Giỏ (2)]`.
- **Trên Trang Chi tiết Sản phẩm:** Thay bằng **Sticky Purchase Bar** (xem §5.5).

---

## 3. Trang chủ (Homepage Specification)

Thứ tự 8 section được thiết kế để phục vụ cả **người mới tìm hiểu** (chọn theo phòng) lẫn **thợ thầu/người đã có kích thước** (lọc nhanh thông số):

### Section 1: Architectural Bento Hero + Thanh Tìm Gạch Nhanh

```text
┌───────────────────────────────────────────────────┬────────────────────────────────────┐
│ KHUNG CHÍNH (8/12 cột — Ảnh phối cảnh thật LCP)   │ Ô PHỤ 1 (4/12 cột — Nền Champagne) │
│                                                   │ ĐÁ NUNG KẾT & KHỔ LỚN 60x120+      │
│ [Tag: VẬT LIỆU HOÀN THIỆN CHUẨN QUỐC TẾ]          │ Ít đường ron, vân đá liền mạch     │
│ H1 (Anton): GẠCH ỐP LÁT CHÍNH HÃNG DRAVIT         │ [Khám phá Big Slab →]              │
│ Subhead: Thẩm mỹ từ những đường nét nhỏ nhất.     ├────────────────────────────────────┤
│ Minh bạch giá/m², chuẩn xác trên từng đường ron.  │ Ô PHỤ 2 (4/12 cột — Ảnh Showroom)  │
│ [Xem bộ sưu tập gạch]   [Nhận báo giá công trình] │ XEM MẪU THẬT DƯỚI ÁNH ĐÈN THỰC TẾ  │
│                                                   │ 📍 Showroom Dravit · Đang mở cửa   │
│ ┌─ Tìm nhanh: [Không gian ▾] [Kích thước ▾] [Tìm]┐│ [Xem chỉ đường & Đặt lịch →]       │
└─┴────────────────────────────────────────────────┴┴────────────────────────────────────┘
```

### Section 2: Dải 4 Cam kết Chuẩn mực (Architectural Trust Strip)
- Lưới 4 ô ngang ngăn bởi đường kẻ ron `1px` trên nền `--c-white`:
  1. **Thông số minh bạch:** Công khai độ hút nước, chống trượt `R9–R13`, độ dày.
  2. **Giá kép rõ ràng:** Niêm yết cả `đ/m²` và `đ/viên`, tự tính số thùng & hao hụt.
  3. **Mẫu thật tại Showroom:** Xem trực tiếp bề mặt và sắc độ dưới ánh đèn thực.
  4. **Đồng hành công trình:** Báo phí vận chuyển tối ưu, chiết khấu riêng cho nhà mới & thợ thầu.

### Section 3: Chọn Gạch Theo Không Gian (Visual Space Grid — 8 Sub-Silos)
- **Header section:** H2 `"Chọn gạch đúng cho từng không gian"` + mô tả ngắn 1 dòng + link `"Xem tất cả không gian →"`.
- **Bố cục Bento bất đối xứng (tránh 8 ô bằng nhau nhàm chán):**
  - Hàng 1: **Phòng khách** (ô lớn 6/12) + **Nhà tắm / WC** (3/12) + **Phòng bếp** (3/12).
  - Hàng 2: **Sân vườn** (3/12) + **Mặt tiền** (3/12) + **Phòng ngủ** (3/12) + **Ngoài trời / Hồ bơi** (3/12).
- **Trên mỗi ô ảnh:** Tên không gian + **1 thông số gợi ý cốt lõi** (VD: *Nhà tắm — Chống trượt R10–R11* · *Phòng khách — Khổ 80x80, 60x120*) + số lượng mẫu.

### Section 4: Catalog Explorer Block — Khám phá Gạch theo Công năng & Kích thước
- Kế thừa ý tưởng lọc nhanh ngay tại trang chủ của mẫu tham khảo nhưng trình bày theo chuẩn kiến trúc DRAVIT:
  - **Tầng Tab chính (ngăn bằng đường ron):** `[ Gạch lát nền ]` · `[ Gạch ốp tường ]` · `[ Gạch trang trí / Gạch thẻ ]` · `[ Đá nung kết ]`.
  - **Tầng Chip điều hướng nhanh (Link tĩnh `<a>`):** `[Tất cả]` `[60x60]` `[80x80]` `[30x60]` `[60x120]` `[Vân đá Marble]` `[Giả gỗ]` `[Terrazzo]` `[Vân xi măng]`.
  - **Thân khối (12 cột):**
    - **Cột trái (3/12 — Lookbook Card nền `--c-navy-900` chữ `--c-ivory`):** Ảnh phối cảnh dọc của nhóm đang chọn + tiêu chuẩn kỹ thuật khuyến nghị + nút `"Xem toàn bộ {n}+ mẫu gạch lát nền →"`.
    - **Cột phải (9/12):** Lưới 1 hàng × 3 card sản phẩm (tổng 3 mẫu tiêu biểu của tab đó).

### Section 5: Tương quan Kích thước & Bề mặt Thực tế (Signature Section của DRAVIT)
- Điểm nhấn thị giác giúp khách hàng nhớ ngay thương hiệu:
  - **Cột trái (6/12 — Sơ đồ tỷ lệ kích thước CSS):** Vẽ trực quan bằng đường kẻ mảnh tỷ lệ thực giữa các viên `30x60`, `60x60`, `80x80`, `60x120`, `15x90` kèm diện tích phòng khuyến nghị (VD: *Phòng < 15m² → 60x60 · Phòng > 20m² → 80x80 hoặc 60x120*). Click vào từng hình chữ nhật dẫn thẳng tới `/gach-60x60`, `/gach-80x80`...
  - **Cột phải (6/12 — 4 Bề mặt & Chỉ số chống trượt):** 4 thẻ ngang giải thích nhanh kèm link lọc: *Bóng kiếng (Polished — Bắt sáng)* · *Mờ mịn (Matt R9–R10 — Tinh tế, chống chói)* · *Nhám sần (Structured R11 — Sân vườn, nhà tắm)* · *Hiệu ứng Carving/Sugar*.

### Section 6: Gạch Ưu Đãi Tại Kho (`/gach-uu-dai`)
- Không dùng đồng hồ đếm ngược giả; thiết kế thành khung nền `--c-champagne` viền `1px`:
  - Tiêu đề: **"Gạch Ưu Đãi & Lẻ Lô Tại Kho"** + mô tả chân thật: *"Các mã gạch chính hãng giảm giá 15–35% do dọn kho mẫu trưng bày hoặc còn lẻ lô số lượng ít. Phù hợp lát phòng phụ, sân sau, nhà cho thuê."*
  - Lưới 4 card sản phẩm có hiển thị thêm dòng: **`Còn tồn kho: {X} m² ({Y} viên)`** + nút `"Xem toàn bộ mã ưu đãi →"`.

### Section 7: Không gian Showroom Thực tế
- Khung 2 cột (7/12 Ảnh/Video thực tế khu vực kệ gạch tại Showroom có các nút chuyển khu vực trưng bày: *Khu Gạch Khổ Lớn · Khu Gạch Phòng Tắm · Bảng Mẫu Bề Mặt & Đường Ron*; 5/12 Thông tin địa chỉ, giờ mở cửa, lợi ích khi ghé showroom xem gạch dưới ánh đèn vàng/trắng thực tế + nút **Chỉ đường Google Maps** & **Đặt lịch tư vấn**).

### Section 8: Cẩm nang Chọn gạch & Thi công (Layout Tạp chí Kiến trúc 8/4)
- **Cột trái (8/12):** 1 bài viết tiêu điểm lớn (ảnh 16:9 + tiêu đề + tóm tắt 2 dòng) ở trên + 3 bài viết chia theo 3 chủ đề ở dưới.
- **Cột phải (4/12 — Hộp "Cẩm nang tra nhanh cho người làm nhà" nền `--c-white` viền ron):** Danh sách đánh số `01` đến `05` (font Anton màu `--c-moss-700`) các bài hướng dẫn quan trọng nhất (Cách tính số viên gạch, Chọn chỉ số chống trượt R cho nhà tắm, Cách phối màu đường ron...).

---

## 4. Trang Danh mục (28 Smart Collections, `/bo-suu-tap/*`, `/xuat-xu/*`)

### 4.1 Wireframe & Bố cục

```text
Desktop (≥ 1024px)
┌ Breadcrumb: Trang chủ / Gạch / Gạch phòng khách ────────────────────────────────────────┐
│ H1 (Anton): GẠCH LÁT NỀN & ỐP TƯỜNG PHÒNG KHÁCH SANG TRỌNG        │ TÓM TẮT NHANH (<dl>)│
│ Intro (≤ 60 chữ): Tổng hợp các mẫu gạch phòng khách xương đá      │ 124 mẫu  │ Từ 260k/m²│
│ Porcelain chịu lực, bề mặt bóng kiếng & mờ mịn...                 │ Khổ 80x80│ Có mẫu kệ │
├───────────────────────────────────────────────────────────────────┴─────────────────────┤
│ VISUAL SUB-SILO TILES (Cuộn ngang, thẻ chữ nhật 4px có ảnh vân 48x48px — IL-01/IL-02):  │
│ [[Ảnh] Gạch 80x80 (48)] [[Ảnh] Gạch 60x120 (32)] [[Ảnh] Vân đá Marble (54)] [[Ảnh] Giả gỗ]│
├────────────────────┬────────────────────────────────────────────────────────────────────┤
│ BỘ LỌC (280px)     │ Thanh trạng thái: Hiển thị 24/124 mẫu │ Đang lọc: [80x80 ×] [Xóa]  │
│ (Sticky, đường ron)│                                        Sắp xếp: [Phổ biến nhất ▾] │
│ ▾ Kích thước (Chip)├────────────────────────────────────────────────────────────────────┤
│   [60x60] [80x80●] │ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐        │
│   [30x60] [60x120] │ │ [-15%] [Showroom]│ │                 │ │                 │        │
│ ▾ Bề mặt           │ │   ẢNH 1:1       │ │   ẢNH 1:1       │ │   ẢNH 1:1       │        │
│   [ ] Bóng kiếng   │ │ (Hover: ảnh 2)  │ │                 │ │                 │        │
│   [ ] Mờ (Matt)    │ ├─────────────────┤ ├─────────────────┤ ├─────────────────┤        │
│ ▾ Màu sắc (Swatch) │ │ [80x80] [Bóng]  │ │ [60x120] [Matt] │ │ [80x80] [Bóng]  │        │
│ ▾ Vân & Chất liệu  │ │ Gạch Lát Nền... │ │ Gạch Ốp Lát...  │ │ Gạch Vân Đá...  │        │
│ ▾ Khoảng giá/m²    │ │ (2 dòng đầy đủ) │ │ (2 dòng đầy đủ) │ │ (2 dòng đầy đủ) │        │
│ ▸ Chống trượt (R)  │ │ 385.000đ/m²     │ │ 520.000đ/m²     │ │ 410.000đ/m²     │        │
│ ▸ Độ hút nước      │ │ ≈ 246.400đ/viên │ │ ≈ 374.400đ/viên │ │ ≈ 262.400đ/viên │        │
│ ▸ Độ dày           │ └─────────────────┘ └─────────────────┘ └─────────────────┘        │
│ [✓] Có mẫu Showroom│                                                                    │
│                    │       Đang xem 24 / 124 mẫu · [ Xem thêm 24 sản phẩm ↓ ]           │
├────────────────────┴────────────────────────────────────────────────────────────────────┤
│ Khối CTA Báo giá công trình · Bài viết SEO Footer (4 phần chuẩn 10 §4.2) · Khối FAQ     │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Giải phẫu Card Sản phẩm Chuẩn DRAVIT (Product Card Anatomy)

Card sản phẩm được thiết kế để truyền tải đủ **Thẩm mỹ + Thông số + Giá kép** chỉ trong 1 cái nhìn:
1. **Khung ảnh vuông `1:1` (nền `--c-champagne`):**
   - Ảnh mặc định: Ảnh bề mặt viên gạch hoặc ảnh phối cảnh chuẩn.
   - **Hover trên desktop:** Chuyển mượt (`opacity 200ms`) sang ảnh thứ 2 (nếu ảnh 1 là viên gạch thì ảnh 2 là phối cảnh phòng, và ngược lại).
   - **Badge góc trên trái:** Tối đa 2 badge nhỏ (`--r-sm: 4px`): Badge `% giảm` (nền `--c-sale`) và Badge `Mẫu tại Showroom` (nền `--c-amber-100`, chữ `--c-amber-800`).
2. **Hàng Meta Kỹ thuật (ngay dưới ảnh, ngăn bằng đường ron mảnh):**
   - Bên trái: **Pill vuông Kích thước** (`80x80`) + **Pill Bề mặt** (`Bóng kiếng` / `Matt R10`).
   - Bên phải: Mã `SKU` (`DRV-8801M`) màu `--c-navy-800`.
3. **Tên sản phẩm:** Font Quicksand 600, `15px`, hiển thị **đủ 2 dòng** (`line-clamp: 2`, chiều cao cố định `2.8em` để mọi card bằng nhau tuyệt đối).
4. **Khu vực Giá kép & Hành động (chân card):**
   - Cột trái:
     - Dòng 1: **`385.000đ/m²`** (font Quicksand 700, `18px`, màu `--c-navy-900`; nếu có KM thì màu `--c-sale` kèm giá gốc gạch ngang bên cạnh).
     - Dòng 2: **`≈ 246.400đ/viên`** (font `13px`, màu `--c-navy-800`).
   - Cột phải: Nút vuông `36×36px` (`--r-sm`) có icon máy tính/mũi tên (`aria-label="Tính số lượng và xem chi tiết"`).

---

## 5. Trang Chi tiết Sản phẩm (Single Product Specification)

Kế thừa các ưu điểm chuyển đổi xuất sắc của mẫu tham khảo (**Lưới ảnh 2 cột trên desktop**, **Bộ quy đổi trực tiếp `m² ⇄ viên` kèm Tổng tiền**, **Nút tải Map gạch cho KTS**, **Thanh Sticky đáy khi cuộn**) và nâng cấp theo hệ thống DRAVIT:

### 5.1 Wireframe Khu vực Mua hàng (Above the Fold)

```text
Desktop (≥ 1024px): Cột trái 7/12 (Architectural Gallery) │ Cột phải 5/12 (Sticky Buy Box)
┌ Breadcrumb: Trang chủ / Gạch / Gạch lát nền / Gạch Lát Nền Dravit... (IL-04) ───────────┐
├───────────────────────────────────────────┬─────────────────────────────────────────────┤
│ GALLERY KIẾN TRÚC (7/12 cột)              │ HỘP THÔNG TIN & ĐẶT HÀNG (5/12, Sticky)     │
│ ┌───────────────────────────────────────┐ │ SKU: DRV-8801M [Copy] │ ● Còn hàng tại kho  │
│ │                                       │ │ 📍 Có mẫu thực tế tại Showroom Dravit       │
│ │   ẢNH CHÍNH 1:1 HOẶC 4:3 (LCP)        │ │                                             │
│ │   (Phối cảnh không gian thực tế)      │ │ H1: Gạch Lát Nền Dravit Vân Đá Marble       │
│ │                                       │ │ Calacatta 80x80 Bóng Kiếng                  │
│ └───────────────────────────────────────┘ │                                             │
│ ┌───────────────────┐ ┌─────────────────┐ │ GIÁ NIÊM YẾT:                               │
│ │ ẢNH 2: CẬN VÂN    │ │ ẢNH 3: GÓC NGHIÊNG│ │ 385.000đ/m²   (450.000đ/m²  [-14%])         │
│ │ (Map mặt gạch)    │ │ (Bề mặt & ánh sáng│ │ ≈ 246.400đ/viên · Quy cách: 3 viên/thùng   │
│ │ [↓ Tải Map gạch]  │ │                 │ │ (1 thùng = 1,92 m² · Nặng 42 kg)            │
│ └───────────────────┘ └─────────────────┘ ├─────────────────────────────────────────────┤
│ ┌───────────────────┐ ┌─────────────────┐ │ Kích thước cùng mẫu: [60x60] [80x80●] [60x120]│
│ │ ẢNH 4: CẠNH & ĐỘ  │ │ ẢNH 5: PHỐI CẢNH│ │ Bề mặt cùng mẫu:     [Bóng kiếng●] [Mờ Matt]│
│ │ DÀY VIÊN GẠCH     │ │ CHI TIẾT ĐƯỜNG  │ ├─────────────────────────────────────────────┤
│ │ (10mm Rectified)  │ │ RON THỰC TẾ     │ │ BỘ TÍNH SỐ LƯỢNG & THÀNH TIỀN (Nền Ivory):  │
│ └───────────────────┘ └─────────────────┘ │ Diện tích cần dùng:      Số viên giao dịch: │
│ (Click bất kỳ ảnh nào → PhotoSwipe Zoom)  │ [ - ] [ 10 ] [ + ] m² ⇄  [ - ] [ 17 ] [ + ] │
│                                           │ [✓] Cộng 5% hao hụt   [✓] Làm tròn chẵn thùng│
│                                           │ → Thực nhận: 18 viên = 6 thùng (11,52 m²)   │
│                                           │ TỔNG TẠM TÍNH:                  4.435.200đ  │
│                                           │ (Phí vận chuyển báo sau khi xác nhận đơn)   │
│                                           ├─────────────────────────────────────────────┤
│                                           │ [   ĐẶT MUA NGAY   ]  [ + THÊM VÀO GIỎ ]    │
│                                           │ Xây nhà hoặc mua số lượng lớn?              │
│                                           │ → [Nhận báo giá chiết khấu công trình]      │
│                                           ├─────────────────────────────────────────────┤
│                                           │ ┌📞 Đặt nhanh: 09xx.xxx┐ ┌📍 Xem mẫu tại kệ┐│
│                                           │ └💬 Chat Zalo tư vấn   ┘ └   Chỉ đường Maps┘│
│                                           ├─────────────────────────────────────────────┤
│                                           │ BẢNG THÔNG SỐ KỸ THUẬT (Link Silo IL-03):   │
│                                           │ • Công năng:    [Gạch lát nền]              │
│                                           │ • Không gian:   [Phòng khách], [Mặt tiền]   │
│                                           │ • Kích thước:   [80x80 cm] (800×800×10 mm)  │
│                                           │ • Chất liệu:    [Xương đá Porcelain]        │
│                                           │ • Vân gạch:     [Vân đá Marble] (6 mặt vân) │
│                                           │ • Bề mặt / R:   Bóng kiếng · Chống trượt R9 │
│                                           │ • Độ hút nước:  ≤ 0,5% · Độ cứng PEI IV     │
│                                           │ [Xem toàn bộ 14 thông số & Lưu ý thi công ↓]│
└───────────────────────────────────────────┴─────────────────────────────────────────────┘
```

### 5.2 Chi tiết các Tương tác Đặc thù trên Trang Sản phẩm

1. **Nút `[↓ Tải Map gạch (CAD/3D)]`:** Đặt ở góc dưới ảnh mặt gạch phẳng (Ảnh 2), cho phép KTS/nhà thiết kế tải ngay file ảnh các mặt vân (faces) độ phân giải cao để đưa vào SketchUp/3dsMax.
2. **Nút `[Copy]` bên cạnh mã SKU:** Click sao chép nhanh mã `DRV-8801M` (hiện tooltip *"Đã chép mã DRV-8801M"*) để khách gửi nhanh qua Zalo hoặc ghi vào sổ.
3. **Bộ chuyển đổi Kép `m² ⇄ Viên` trực tiếp (Inline Dual-Unit Converter):**
   - Không bắt khách phải bấm mở popup máy tính riêng: bộ quy đổi nằm ngay tại vị trí chọn số lượng.
   - Khi khách gõ `10` vào ô `m²` (với checkbox *Cộng 5% hao hụt* và *Làm tròn chẵn thùng* đang bật):
     - Diện tích cần = `10 × 1,05 = 10,5 m²`.
     - Số viên = `ceil(10,5 / 0,64) = 17 viên` → làm tròn chẵn thùng (3 viên/thùng) = **`18 viên (6 thùng = 11,52 m²)`**.
     - Ô `viên` bên cạnh tự nhảy số `18`, dòng giải thích ghi rõ *"Thực nhận: **18 viên = 6 thùng (11,52 m²)**"*, và **`Tổng tạm tính`** cập nhật ngay thành `4.435.200đ`.
   - Nếu khách muốn mua lẻ đúng số viên (với mẫu cho phép mua lẻ): bỏ tick *Làm tròn chẵn thùng* hoặc gõ trực tiếp vào ô `viên`. Giá trị gửi vào giỏ hàng luôn là **số nguyên viên** (`18`).
4. **Khu vực Dưới (Below the Fold):**
   - **Hộp Lưu ý Trách nhiệm (3 ô ngang nền `--c-ivory`):** Lưu ý lô màu (`Shade V2`) · Khuyến nghị độ rộng đường ron (`1,5–2mm` với cạnh mài Rectified) · Chính sách kiểm hàng & báo phí vận chuyển.
   - **Mô tả chi tiết & Phối cảnh thực tế** (nếu có).
   - **Cùng bộ sưu tập / Cùng dòng (`drv_series`)** → **Các mẫu gạch tương tự trong cùng Silo (`IL-06` — lưới 4 cột)** → **Sản phẩm bạn đã xem** (hàng ngang gọn ở cuối trang).
5. **Thanh Sticky Purchase Bar (Hiện ở đáy màn hình khi cuộn qua khỏi nút Mua chính):**
   ```text
   Desktop:
   ┌────────────────────────────────────────────────────────────────────────────────────────┐
   │ [Ảnh] Gạch Lát Nền Dravit Calacatta 80x80 │ Đang chọn: 18 viên (6 thùng · 11,52 m²)    │
   │       385.000đ/m² (246.400đ/viên)         │ Tạm tính: 4.435.200đ [Mua ngay] [+ Vào giỏ]│
   └────────────────────────────────────────────────────────────────────────────────────────┘
   Mobile:
   ┌────────────────────────────────────────────────────────────────────────────────────────┐
   │ Đang chọn: 18 viên (11,52 m²) · Tạm tính: 4.435.200đ          [Nhận báo giá riêng →]  │
   │ [💬 Zalo]   [📞 Gọi]   [ + Thêm vào giỏ ]   [ ĐẶT MUA NGAY ]                           │
   └────────────────────────────────────────────────────────────────────────────────────────┘
   ```

---

## 6. Giỏ hàng, Checkout & Trang Cảm ơn

- **Giỏ hàng Drawer (Mini-cart) & Trang Giỏ hàng (`/gio-hang`):**
  - Mỗi dòng hiển thị: Ảnh vuông, Tên SP, Mã SKU, Số lượng **`18 viên`** kèm dòng quy đổi nổi bật **`= 6 thùng · 11,52 m²`**, Đơn giá kép `246.400đ/viên (385.000đ/m²)`, Thành tiền.
  - Khung tóm tắt: Tổng số lượng viên/thùng · **Tổng diện tích gạch** · **Tổng trọng lượng ước tính (kg)** (giúp khách hiểu vì sao cần báo phí vận chuyển theo xe/chành) · Phí vận chuyển: *"Báo sau khi xác nhận đơn"* · Tổng tạm tính.
- **Checkout (`/thanh-toan` — 1 trang, 2 cột trên desktop):**
  - Cột trái (7/12): Thông tin người nhận (Họ tên, SĐT, Email tùy chọn, **Tỉnh/TP (34) → Phường/Xã → Địa chỉ nhận hàng**, Ghi chú giờ nhận/đặc điểm hẻm công trình) + Chọn phương thức thanh toán (**COD** hoặc **Chuyển khoản ngân hàng**).
  - Cột phải (5/12, sticky): Tóm tắt đơn hàng + Hộp thông báo thấu hiểu (nền `--c-champagne`): *"Vì gạch ốp lát có trọng lượng lớn ({total_kg} kg), nhân viên Dravit sẽ gọi xác nhận tồn kho cùng lô màu và báo phí vận chuyển tốt nhất trước khi giao hàng."* + Nút **`[ ĐẶT HÀNG — CHỜ XÁC NHẬN PHÍ GIAO ]`**.
- **Trang Cảm ơn (`order-received`):** Xem chi tiết tại `04_Pricing_Units_Commerce.md` §9.

---

## 7. Silo Cẩm nang (Blog Archive & Single Post Specification)

Chắt lọc cấu trúc đọc lướt cực tốt của mẫu tham khảo (**Sub-nav chuyên mục**, **Bento tiêu điểm + bảng Xu hướng**, **Hộp "Những điểm chính" đầu bài**, **FAQ cuối bài**) và nâng cấp cơ chế gắn sản phẩm để tăng chuyển đổi:

### 7.1 Trang Danh sách Cẩm nang (`/cam-nang`)
1. **Thanh Điều hướng Chuyên mục Cẩm nang (ngay dưới Header):**
   - Nền `--c-ivory`, viền đáy `1px`: `Tất cả bài viết` · `Chọn gạch theo phòng` · `Kỹ thuật ốp lát & đường ron` · `Phối màu & xu hướng` · `Định mức & báo giá` | Bên phải: Ô tìm kiếm bài viết.
2. **Khối Tiêu điểm (Editorial Hero Bento 12 cột):**
   - Cột trái (5/12): 1 bài hướng dẫn trụ cột lớn (ảnh 16:9, chủ đề, tiêu đề H2, tóm tắt 3 dòng).
   - Cột giữa (4/12): 3 bài viết mới (ảnh vuông nhỏ bên trái + tiêu đề bên phải, ngăn nhau bằng đường ron `1px`).
   - Cột phải (3/12 — Hộp **"Chủ đề được tra cứu nhiều"** nền `--c-champagne`): Danh sách đánh số `01–05` (font Anton) các bài viết nhiều người đọc nhất.
3. **Các Khối Chủ đề Chuyên sâu:** Mỗi chủ đề 1 hàng gồm tiêu đề chủ đề + link `"Xem tất cả →"` + lưới 3 card bài viết (ảnh 3:2 không chèn chữ rối mắt lên ảnh, tiêu đề rõ ràng, thời gian đọc).

### 7.2 Trang Chi tiết Bài viết Cẩm nang (`/cam-nang/{slug}`)

```text
Desktop: Cột nội dung chính 8/12 (max-width 72ch) │ Cột phải Sticky 4/12
┌ Breadcrumb: Trang chủ / Cẩm nang / Chọn gạch theo phòng ────────────────────────────────┐
├─────────────────────────────────────────────────┬───────────────────────────────────────┤
│ H1: Tổng hợp mẫu gạch Terrazzo 80x80 đẹp,       │ STICKY SIDEBAR (4/12 cột):            │
│ chống trơn và báo giá mới nhất                  │ ┌─ MỤC LỤC BÀI VIẾT (Tự highlight) ──┐│
│ Cập nhật: 05/11/2026 · Kiểm duyệt: Kỹ thuật Dravit│ │ 1. Các mẫu Terrazzo 80x80         ││
│                                                 │ │ 2. Cách chọn theo từng phòng       ││
│ ┌─ NHỮNG ĐIỂM CHÍNH (Key Takeaways - GEO Box) ─┐│ │ 3. Bảng giá & quy cách thùng       ││
│ │ • Gạch Terrazzo 80x80 giúp mặt sàn ít đường  ││ └────────────────────────────────────┘│
│ │   ron, phù hợp phòng từ 20m² trở lên.        ││ ┌─ CÔNG CỤ TÍNH NHANH SỐ VIÊN GẠCH ──┐│
│ │ • Khu vực bếp/nhà tắm nên chọn bề mặt Matt   ││ │ Nhập m² sàn: [ 25 ] [Tính số viên] ││
│ │   chống trượt R10, xương đá Porcelain.       ││ └────────────────────────────────────┘│
│ │ • Xem ngay trọn bộ [Gạch Terrazzo] và        ││ ┌─ XEM MẪU THẬT TẠI SHOWROOM DRAVIT ─┐│
│ │   [Gạch 80x80] chính hãng tại kho Dravit.    ││ │ Trải nghiệm bề mặt dưới ánh đèn thực││
│ └──────────────────────────────────────────────┘│ │ 📍 [Địa chỉ Showroom]               ││
│                                                 │ │ [Chỉ đường Maps] [Nhận báo giá]    ││
│ 1. Các mẫu gạch Terrazzo 80x80 theo tông màu    │ └────────────────────────────────────┘│
│ ...                                             │                                       │
│ ┌─────────────────────────────────────────────┐ │                                       │
│ │      ẢNH PHỐI CẢNH THỰC TẾ TRONG BÀI        │ │                                       │
│ ├─────────────────────────────────────────────┤ │                                       │
│ │ [Ảnh] Mẫu trong ảnh: Gạch Terrazzo Dravit   │ │  <-- "Shop the Look Bar" dưới chân ảnh│
│ │ DRV-TRZ80 · 385.000đ/m² · [Xem & Tính viên→]│ │      (Thay cho nút đè lên ảnh của mẫu)│
│ └─────────────────────────────────────────────┘ │                                       │
│                                                 │                                       │
│ [Khối Shortcode 4-8 Sản phẩm thực tế + Nút CTA] │                                       │
│ [Khối Câu hỏi thường gặp (FAQ Schema)]          │                                       │
│ [Hộp Thông tin Showroom & Liên hệ tư vấn]       │                                       │
└─────────────────────────────────────────────────┴───────────────────────────────────────┘
Mobile: Có nút tròn nhỏ "☰ Mục lục" cố định góc dưới trái khi cuộn bài dài (mở bottom sheet mục lục).
```

- **Điểm nâng cấp quan trọng ("Shop the Look Bar" dưới ảnh bài viết):**
  - Mẫu tham khảo chèn chữ watermark lên ảnh và đè nút *"Nhận báo giá >"* ở góc ảnh nhưng khách bấm vào không biết đó là mã gạch nào.
  - DRAVIT giữ bức ảnh phối cảnh **sạch 100%**, và gắn một thanh ngang gọn (`--c-ivory`, viền `1px`) ngay sát chân ảnh: hiển thị ảnh nhỏ viên gạch + Tên/Mã SKU + Giá `đ/m²` + nút **`[Xem mã gạch này →]`** hoặc **`[Nhận báo giá mẫu này]`**.

---

## 8. Các Trang Tĩnh & Tiện ích Khác

| Trang | Bố cục chính |
|---|---|
| **Giới thiệu (`/gioi-thieu`)** | Bản copy chuẩn tại `10_Brand_Voice_And_Content_Guide.md` §4.5 + Ảnh thực tế kho hàng/showroom + 4 con số năng lực thật + Dải liên hệ. |
| **Showroom & Liên hệ (`/showroom`)** | Danh sách showroom (ảnh thực tế, địa chỉ, giờ mở cửa, nút Chỉ đường Google Maps, SĐT) + Form "Đặt lịch xem mẫu / Nhận báo giá công trình". |
| **5 Trang Chính sách** | Layout 2 cột: Cột trái (3/12) là Menu chuyển nhanh giữa 5 chính sách + Mục lục trang; Cột phải (9/12, max-width 70ch) là nội dung chính sách rõ ràng, ngày hiệu lực. |
| **404 Không tìm thấy** | Thông điệp ngắn gọn + Ô tìm kiếm mã gạch + 4 thẻ dẫn về 4 nhóm Silo chính (`/gach-lat-nen`, `/gach-phong-khach`, `/gach-van-da`, `/gach-80x80`). |

---

## 9. Microcopy Giao diện Chuẩn & Accessibility (WCAG 2.2 AA)

- **Microcopy chuẩn cho nút & trạng thái:** Tuân thủ nghiêm ngặt mục 4 & 5 của [10_Brand_Voice_And_Content_Guide.md](10_Brand_Voice_And_Content_Guide.md) và mục 11 bản đặc tả trước:
  - Nút chính trang SP: `Đặt mua ngay` · `Thêm vào giỏ` · `Nhận báo giá chiết khấu công trình`.
  - Bộ tính số lượng: `Diện tích cần dùng (m²)` · `Số viên giao dịch` · `Cộng 5% hao hụt` · `Làm tròn chẵn thùng` · `Thực nhận: {X} viên = {Y} thùng ({Z} m²)`.
  - Vận chuyển: `Phí vận chuyển: báo sau khi xác nhận đơn`.
- **Accessibility (WCAG 2.2 AA):**
  - `<html lang="vi">`, skip link đầu trang, đầy đủ landmark `<header>`, `<nav>`, `<main>`, `<aside>`, `<footer>`.
  - Tương phản màu đạt chuẩn theo `01` §2.2 (chữ chính dùng `--c-text-main #0B192C` 16.3:1; Header & H1–H6 dùng `--c-navy-900 #083A4F` 11.04:1; link chữ teal trên nền sáng bắt buộc dùng `--c-teal-700 #0F766E` 6.37:1; chữ badge showroom dùng `--c-amber-800` trên nền `--c-amber-100` 5.8:1).
  - Mọi ô nhập trong Bộ chuyển đổi `m² ⇄ viên` và Bộ lọc đều có `<label>` (hoặc `aria-label` rõ ràng cho nút `+/-`), vùng kết quả số viên/thành tiền và số sản phẩm lọc được gắn `aria-live="polite"`.
  - Touch target trên mobile tối thiểu `44×44px`; khoảng cách giữa các nút/chip tối thiểu `8px`.
  - Tôn trọng `@media (prefers-reduced-motion: reduce)`.
