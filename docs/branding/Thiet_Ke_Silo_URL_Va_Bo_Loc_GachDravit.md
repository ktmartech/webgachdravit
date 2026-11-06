---
project: gachdravit.vn
document_type: SEO_Silo_and_Database_Architecture_Blueprint
version: 2.0
target_agent: Fullstack_Developer_Agent, SEO_Architect_Agent, Database_Engineer_Agent
tech_stack_compatibility: Next.js / Nuxt.js / Laravel / Custom E-commerce CMS
---

# ĐẶC TẢ KIẾN TRÚC SILO, QUY HOẠCH URL & HỆ THỐNG BỘ LỌC (FACETED NAVIGATION) — DỰ ÁN GACHDRAVIT.VN

> **Hướng dẫn dành cho AI Agent:** Tài liệu này là nguồn tham chiếu chuẩn (Single Source of Truth) để xây dựng cấu trúc cơ sở dữ liệu (Database Schema), định tuyến đường dẫn (URL Routing), bộ lọc thông minh (Smart Collections & Faceted Navigation) và hệ thống liên kết nội bộ tự động (Automated Internal Linking) cho website thương mại điện tử **Gạch Dravit (`gachdravit.vn`)**.

---

## PHẦN 1: NGUYÊN LÝ KIẾN TRÚC & GIẢI PHÁP XỬ LÝ CHỒNG CHÉO 4 NHÓM GẠCH

### 1.1. Bài toán nghiệp vụ ngành Gạch ốp lát
Một mã sản phẩm gạch (SKU) trong thực tế luôn mang **đa thuộc tính chồng chéo** thuộc 4 nhóm phân loại khác nhau:
1. **Nhóm Công năng:** Gạch lát nền, Gạch ốp tường, Gạch trang trí...
2. **Nhóm Không gian:** Phòng khách, Nhà tắm, Phòng bếp, Sân vườn...
3. **Nhóm Vân gạch & Chất liệu:** Vân đá Marble, Giả gỗ, Terrazzo, Porcelain, Đá nung kết...
4. **Nhóm Kích thước:** 60x60, 80x80, 30x60, 60x120...

**Yêu cầu kỹ thuật:**
- **Cấm** đưa sản phẩm vào thư mục vật lý cố định theo kiểu `/gach-lat-nen/gach-phong-khach/san-pham-a` (gây trùng lặp URL hoặc giới hạn khả năng hiển thị sản phẩm ở các nhóm khác).
- **Cấm** đẩy toàn bộ 4 nhóm vào tham số `?filter=` ngay từ chiều lọc đầu tiên (vì làm mất khả năng lập chỉ mục và xếp hạng SEO cho các từ khóa ngách có lượng tìm kiếm lớn).

### 1.2. Giải pháp Kiến trúc: Tag-based Smart Collections + Hybrid Faceted Filter

```text
[DATABASE LAYER]
Bảng Products (1 SKU = 1 URL duy nhất: /san-pham/{slug})
   │
   ├── Gắn khoá ngoại đơn: primary_category_id (Cố định 1 Breadcrumb duy nhất tránh xung đột)
   └── Quan hệ Nhiều-Nhiều (Many-to-Many) qua bảng Product_Attributes:
         ├── Tag Công năng: [lat-nen, op-tuong]
         ├── Tag Không gian: [phong-khach, nha-tam]
         ├── Tag Vân/Chất liệu: [van-da-marble, porcelain]
         └── Tag Kích thước: [60x120]

[ROUTING & SEO LAYER]
   ├── CHIỀU LỌC THỨ 1 (Chọn 1 thuộc tính cốt lõi):
   │     └── Định tuyến ra URL TĨNH (Smart Collection) -> INDEX, FOLLOW
   │         VD: /gach-lat-nen, /gach-phong-khach, /gach-van-da, /gach-60x120
   │
   ├── CHIỀU LỌC THỨ 2+ (Giao cắt từ 2 thuộc tính trở lên):
   │     └── Định tuyến ra URL THAM SỐ (?filter=) -> NOINDEX, FOLLOW + Canonical về URL Tĩnh gốc
   │         VD: /gach-phong-khach?kich-thuoc=80x80&be-mat=bong
   │
   └── THUỘC TÍNH KỸ THUẬT SÂU / UI SORT (DCOF, Độ hút nước, Giá, Sắp xếp):
         └── Xử lý qua AJAX hoặc Query Param bị chặn -> NOINDEX, NOFOLLOW + Disallow trong robots.txt
```

### 1.3. Quy chuẩn Database Schema cốt lõi (Gợi ý cho Agent)

```sql
-- 1. Bảng Danh mục Tĩnh (Smart Collections)
CREATE TABLE smart_collections (
    id VARCHAR(20) PRIMARY KEY, -- VD: DRV-C02, DRV-S01
    slug VARCHAR(150) UNIQUE NOT NULL, -- VD: gach-lat-nen, gach-phong-khach
    parent_id VARCHAR(20) REFERENCES smart_collections(id), -- Mặc định trỏ về DRV-C01 (/gach)
    group_type ENUM('FUNCTION', 'SPACE', 'MATERIAL_PATTERN', 'SIZE') NOT NULL,
    name VARCHAR(255) NOT NULL,
    h1_title VARCHAR(255) NOT NULL,
    meta_title VARCHAR(255),
    meta_description TEXT,
    query_rule JSONB NOT NULL, -- Điều kiện tự động gom sản phẩm, VD: {"space": ["phong-khach"]}
    seo_footer_content TEXT,
    phase VARCHAR(50) DEFAULT 'Phase 1 - Core'
);

-- 2. Bảng Sản phẩm (Products)
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL, -- VD: DRV-8801M
    slug VARCHAR(255) UNIQUE NOT NULL, -- URL: /san-pham/{slug}
    name VARCHAR(255) NOT NULL,
    primary_collection_id VARCHAR(20) NOT NULL REFERENCES smart_collections(id), -- Dùng để render Breadcrumb cố định
    attributes JSONB NOT NULL -- Lưu toàn bộ Tag đa chiều để query vào Smart Collections và Faceted Filter
);
```

---

## PHẦN 2: QUY HOẠCH 28 URL DANH MỤC TĨNH (SMART COLLECTIONS)

Tất cả 28 URL dưới đây sử dụng cấu trúc phẳng cấp 1 (`https://gachdravit.vn/{slug}`), trạng thái **`INDEX, FOLLOW`**, thẻ Canonical tự trỏ về chính nó (`Self-referencing Canonical`).

### 2.1. Nhóm 1: Theo Công năng (Core Sub-Silos) — Tổng Volume: ~92.500/tháng

| Mã ID | Phân cấp | Tên Danh mục | URL Tĩnh (`gachdravit.vn/...`) | Điều kiện Query tự động (`query_rule`) | Khi lọc thêm Chiều thứ 2 (Sinh `?filter`) | H1 Chuẩn SEO | Breadcrumb Chuẩn | Giai đoạn |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `DRV-C01` | Cấp 1 (Pillar) | Gạch Ốp Lát (Trang trụ cột) | `/gach` | `category_root = 'gach'` (Lấy toàn bộ SP gạch) | `/gach?khong-gian=phong-khach&kich-thuoc=80x80` | Gạch Ốp Lát Cao Cấp Chính Hãng Dravit | `Trang chủ > Gạch` | Phase 1 - Core |
| `DRV-C02` | Cấp 2 | Gạch Lát Nền | `/gach-lat-nen` | `cong_nang IN ['lat-nen']` | `/gach-lat-nen?kich-thuoc=60x60&be-mat=bong` | Gạch Lát Nền Đẹp, Chống Trơn Chính Hãng | `Trang chủ > Gạch > Gạch lát nền` | Phase 1 - Core |
| `DRV-C03` | Cấp 2 | Gạch Ốp Tường | `/gach-op-tuong` | `cong_nang IN ['op-tuong']` | `/gach-op-tuong?kich-thuoc=30x60&khong-gian=nha-tam` | Gạch Ốp Tường Đẹp Cho Mọi Không Gian | `Trang chủ > Gạch > Gạch ốp tường` | Phase 1 - Core |
| `DRV-C04` | Cấp 2 | Gạch Trang Trí | `/gach-trang-tri` | `cong_nang IN ['trang-tri']` | `/gach-trang-tri?van-gach=mosaic&mau-sac=xanh` | Gạch Trang Trí Nội Ngoại Thất Độc Đáo | `Trang chủ > Gạch > Gạch trang trí` | Phase 1 - Core |
| `DRV-C05` | Cấp 2 | Gạch Bông | `/gach-bong` | `cong_nang IN ['gach-bong']` | `/gach-bong?kich-thuoc=20x20&phong-cach=indochine` | Gạch Bông Cổ Điển & Hiện Đại Trang Trí | `Trang chủ > Gạch > Gạch bông` | Phase 2 - Mở rộng |
| `DRV-C06` | Cấp 2 | Gạch Bông Gió | `/gach-bong-gio` | `cong_nang IN ['bong-gio']` | `/gach-bong-gio?chat-lieu=xi-mang&mau-sac=trang` | Gạch Bông Gió (Thông Gió) Mỹ Thuật Chắn Nắng | `Trang chủ > Gạch > Gạch bông gió` | Phase 2 - Mở rộng |
| `DRV-C07` | Cấp 2 | Gạch Thẻ | `/gach-the` | `cong_nang IN ['gach-the']` | `/gach-the?khong-gian=phong-bep&be-mat=bong` | Gạch Thẻ Ốp Tường Trang Trí Nội Ngoại Thất | `Trang chủ > Gạch > Gạch thẻ` | Phase 2 - Mở rộng |
| `DRV-C08` | Cấp 2 | Gạch Ưu Đãi / Thanh Lý | `/gach-uu-dai` | `khuyen_mai = true` | `/gach-uu-dai?cong-nang=lat-nen&kich-thuoc=60x60` | Gạch Ốp Lát Ưu Đãi Giá Tốt Tại Kho Dravit | `Trang chủ > Gạch > Gạch ưu đãi` | Phase 2 - Mở rộng |

### 2.2. Nhóm 2: Theo Không gian ứng dụng (Space Sub-Silos) — Tổng Volume: ~54.500/tháng

| Mã ID | Phân cấp | Tên Danh mục | URL Tĩnh (`gachdravit.vn/...`) | Điều kiện Query tự động (`query_rule`) | Khi lọc thêm Chiều thứ 2 (Sinh `?filter`) | H1 Chuẩn SEO | Breadcrumb Chuẩn | Giai đoạn |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `DRV-S01` | Cấp 2 | Gạch Phòng Khách | `/gach-phong-khach` | `khong_gian IN ['phong-khach']` | `/gach-phong-khach?kich-thuoc=80x80&van-gach=van-da` | Gạch Lát Nền & Ốp Tường Phòng Khách Sang Trọng | `Trang chủ > Gạch > Gạch phòng khách` | Phase 1 - Core |
| `DRV-S02` | Cấp 2 | Gạch Nhà Tắm / WC | `/gach-nha-tam` | `khong_gian IN ['nha-tam']` | `/gach-nha-tam?be-mat=nham&dcof=r10-r11` | Gạch Ốp Lát Nhà Tắm, Phòng Vệ Sinh Chống Trượt | `Trang chủ > Gạch > Gạch nhà tắm` | Phase 1 - Core |
| `DRV-S03` | Cấp 2 | Gạch Phòng Bếp | `/gach-phong-bep` | `khong_gian IN ['phong-bep']` | `/gach-phong-bep?cong-nang=op-tuong&van-gach=mosaic` | Gạch Ốp Tường & Lát Bếp Dễ Vệ Sinh, Chống Bám Bẩn | `Trang chủ > Gạch > Gạch phòng bếp` | Phase 1 - Core |
| `DRV-S04` | Cấp 2 | Gạch Sân Vườn | `/gach-san-vuon` | `khong_gian IN ['san-vuon']` | `/gach-san-vuon?chiu-luc=cao&dcof=r11-r13` | Gạch Lát Sân Vườn Chống Trơn, Chịu Lực Cao | `Trang chủ > Gạch > Gạch sân vườn` | Phase 1 - Core |
| `DRV-S05` | Cấp 2 | Gạch Ngoài Trời / Ban Công | `/gach-ngoai-troi` | `khong_gian IN ['ngoai-troi']` | `/gach-ngoai-troi?do-hut-nuoc=duoi-0.5` | Gạch Ốp Lát Ngoài Trời, Ban Công, Sân Thượng | `Trang chủ > Gạch > Gạch ngoài trời` | Phase 2 - Mở rộng |
| `DRV-S06` | Cấp 2 | Gạch Mặt Tiền / Cổng | `/gach-mat-tien` | `khong_gian IN ['mat-tien']` | `/gach-mat-tien?van-gach=van-da&mau-sac=vang-kem` | Gạch Ốp Mặt Tiền Nhà Đẹp, Bền Màu Vĩnh Cửu | `Trang chủ > Gạch > Gạch mặt tiền` | Phase 2 - Mở rộng |
| `DRV-S07` | Cấp 2 | Gạch Phòng Ngủ | `/gach-phong-ngu` | `khong_gian IN ['phong-ngu']` | `/gach-phong-ngu?van-gach=gia-go&be-mat=mo-matt` | Gạch Lát Nền Phòng Ngủ Ấm Cúng, Tinh Tế | `Trang chủ > Gạch > Gạch phòng ngủ` | Phase 2 - Mở rộng |
| `DRV-S08` | Cấp 2 | Gạch Hồ Bơi | `/gach-ho-boi` | `khong_gian IN ['ho-boi']` | `/gach-ho-boi?van-gach=mosaic&mau-sac=xanh-duong` | Gạch Ốp Lát Hồ Bơi Chuyên Dụng Chống Thấm | `Trang chủ > Gạch > Gạch hồ bơi` | Phase 2 - Mở rộng |

### 2.3. Nhóm 3: Theo Vân gạch & Chất liệu (Material/Pattern Sub-Silos) — Tổng Volume: ~63.000/tháng

| Mã ID | Phân cấp | Tên Danh mục | URL Tĩnh (`gachdravit.vn/...`) | Điều kiện Query tự động (`query_rule`) | Khi lọc thêm Chiều thứ 2 (Sinh `?filter`) | H1 Chuẩn SEO | Breadcrumb Chuẩn | Giai đoạn |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `DRV-M01` | Cấp 2 | Gạch Giả Gỗ (Vân Gỗ) | `/gach-gia-go` | `van_gach IN ['gia-go']` | `/gach-gia-go?kich-thuoc=15x80&khong-gian=phong-ngu` | Gạch Giả Gỗ (Gạch Vân Gỗ) Lát Nền & Ốp Tường | `Trang chủ > Gạch > Gạch giả gỗ` | Phase 1 - Core |
| `DRV-M02` | Cấp 2 | Gạch Vân Đá Marble | `/gach-van-da` | `van_gach IN ['van-da-marble', 'van-da-tu-nhien']` | `/gach-van-da?be-mat=bong-kieng&kich-thuoc=80x80` | Gạch Vân Đá Tự Nhiên & Vân Đá Marble Cao Cấp | `Trang chủ > Gạch > Gạch vân đá` | Phase 1 - Core |
| `DRV-M03` | Cấp 2 | Gạch Terrazzo | `/gach-terrazzo` | `van_gach IN ['terrazzo']` | `/gach-terrazzo?kich-thuoc=60x60&mau-sac=xam-trang` | Gạch Terrazzo Hạt Đá Hiện Đại Cho Nội Ngoại Thất | `Trang chủ > Gạch > Gạch Terrazzo` | Phase 1 - Core |
| `DRV-M04` | Cấp 2 | Gạch Vân Xi Măng | `/gach-van-xi-mang` | `van_gach IN ['van-xi-mang']` | `/gach-van-xi-mang?phong-cach=wabi-sabi&be-mat=mo` | Gạch Vân Xi Măng (Giả Bê Tông) Tối Giản | `Trang chủ > Gạch > Gạch vân xi măng` | Phase 1 - Core |
| `DRV-M05` | Cấp 2 | Gạch Mosaic | `/gach-mosaic` | `van_gach IN ['mosaic']` | `/gach-mosaic?hinh-dang=luc-giac&khong-gian=phong-bep` | Gạch Mosaic Trang Trí Bếp, Phòng Tắm & Hồ Bơi | `Trang chủ > Gạch > Gạch Mosaic` | Phase 2 - Mở rộng |
| `DRV-M06` | Cấp 2 | Đá Nung Kết (Khổ Lớn) | `/da-nung-ket` | `chat_lieu IN ['da-nung-ket'] OR kich_thuoc_lon = true` | `/da-nung-ket?kich-thuoc=120x240&be-mat=bong` | Đá Nung Kết (Sintered Stone) & Gạch Khổ Lớn Big Slab | `Trang chủ > Gạch > Đá nung kết` | Phase 1 - Core |
| `DRV-M07` | Cấp 2 | Gạch Xương Đá Porcelain | `/gach-porcelain` | `chat_lieu IN ['porcelain', 'granite']` | `/gach-porcelain?cong-nang=lat-nen&kich-thuoc=80x80` | Gạch Xương Đá Porcelain & Granite Chịu Lực Cao | `Trang chủ > Gạch > Gạch Porcelain` | Phase 2 - Mở rộng |

### 2.4. Nhóm 4: Theo Kích thước chuẩn (Size Sub-Silos) — Tổng Volume: ~68.500/tháng

| Mã ID | Phân cấp | Tên Danh mục | URL Tĩnh (`gachdravit.vn/...`) | Điều kiện Query tự động (`query_rule`) | Khi lọc thêm Chiều thứ 2 (Sinh `?filter`) | H1 Chuẩn SEO | Breadcrumb Chuẩn | Giai đoạn |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `DRV-Z01` | Cấp 2 | Gạch 60x60 | `/gach-60x60` | `kich_thuoc = '60x60'` | `/gach-60x60?cong-nang=lat-nen&be-mat=bong` | Gạch Lát Nền 60x60 (600x600) Đẹp, Báo Giá Mới Nhất | `Trang chủ > Gạch > Gạch 60x60` | Phase 1 - Core |
| `DRV-Z02` | Cấp 2 | Gạch 80x80 | `/gach-80x80` | `kich_thuoc = '80x80'` | `/gach-80x80?van-gach=van-da&khong-gian=phong-khach` | Gạch Lát Nền 80x80 (800x800) Cao Cấp Cho Không Gian Rộng | `Trang chủ > Gạch > Gạch 80x80` | Phase 1 - Core |
| `DRV-Z03` | Cấp 2 | Gạch 30x60 | `/gach-30x60` | `kich_thuoc = '30x60'` | `/gach-30x60?cong-nang=op-tuong&khong-gian=nha-tam` | Gạch Ốp Lát 30x60 (300x600) Đa Năng Cho Phòng Tắm & Bếp | `Trang chủ > Gạch > Gạch 30x60` | Phase 1 - Core |
| `DRV-Z04` | Cấp 2 | Gạch 60x120 | `/gach-60x120` | `kich_thuoc = '60x120'` | `/gach-60x120?van-gach=van-da&be-mat=bong-kieng` | Gạch Ốp Lát 60x120 (600x1200) Khổ Lớn Sang Trọng | `Trang chủ > Gạch > Gạch 60x120` | Phase 1 - Core |
| `DRV-Z05` | Cấp 2 | Gạch Giả Gỗ Thanh Dài | `/gach-15x80-20x100` | `kich_thuoc IN ['15x80', '15x90', '20x100', '20x120']` | `/gach-15x80-20x100?mau-sac=nau-go&be-mat=mo` | Gạch Thanh Dài 15x80, 15x90, 20x100, 20x120 Vân Gỗ | `Trang chủ > Gạch > Gạch thanh dài` | Phase 2 - Mở rộng |

---

## PHẦN 3: MA TRẬN KỸ THUẬT 14 BỘ LỌC (FACETED NAVIGATION) & QUY TẮC CRAWL BUDGET

### 3.1. Bảng đặc tả hành vi 14 Bộ lọc trên giao diện `/gach`

| Mã Filter | Tên Bộ lọc UI | Các giá trị thuộc tính trong Database | Hành vi Chiều lọc 1 (Chọn đơn lẻ) | Hành vi Chiều lọc 2+ (Chọn kết hợp) | Thẻ Meta Robots (Khi có `?filter=`) | Quy tắc Thẻ Canonical (Khi có `?filter=`) | Cấu hình `robots.txt` |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `FLT-01` | Loại gạch (Công năng) | Lát nền (`lat-nen`), Ốp tường (`op-tuong`), Trang trí (`trang-tri`), Gạch bông (`gach-bong`), Bông gió (`bong-gio`), Gạch thẻ (`gach-the`) | Điều hướng sang URL tĩnh (`/gach-lat-nen`...) | Nối thêm `?cong-nang=...` vào URL tĩnh đang đứng | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc (bỏ toàn bộ chuỗi sau `?`) | `Allow` (Để bot đọc `noindex` ở chiều 2, chặn khi từ 3 dấu `&` trở lên) |
| `FLT-02` | Không gian ứng dụng | Phòng khách, Nhà tắm, Phòng bếp, Sân vườn, Ngoài trời, Mặt tiền, Phòng ngủ, Hồ bơi | Điều hướng sang URL tĩnh (`/gach-phong-khach`...) | Nối thêm `?khong-gian=...` vào URL tĩnh đang đứng | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Allow` chiều 2, `Disallow` khi kết hợp >= 3 tham số |
| `FLT-03` | Vân gạch & Chất liệu | Giả gỗ, Vân đá Marble, Terrazzo, Vân xi măng, Mosaic, Đá nung kết, Porcelain, Ceramic | 7 loại chính $\rightarrow$ URL tĩnh. Các loại phụ $\rightarrow$ `?van-gach=...` | Nối thêm `?van-gach=...` hoặc `?chat-lieu=...` | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Allow` chiều 2, `Disallow` khi kết hợp >= 3 tham số |
| `FLT-04` | Kích thước | 60x60, 80x80, 30x60, 60x120, Thanh dài (15x80, 20x100), Khổ lớn (>120cm), Kích thước lẻ (20x20, 30x30, 40x40) | 5 nhóm lớn $\rightarrow$ URL tĩnh. Kích thước lẻ $\rightarrow$ `?kich-thuoc=...` | Nối thêm `?kich-thuoc=...` vào URL tĩnh đang đứng | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Allow` chiều 2, `Disallow` khi kết hợp >= 3 tham số |
| `FLT-05` | Bộ sưu tập / Thương hiệu | Dravit Luxury, Dravit Signature, Dravit Eco, Nhập khẩu Tây Ban Nha, Ấn Độ, Ý, Việt Nam | Điều hướng về Silo `/bo-suu-tap/[slug]` hoặc `/xuat-xu/[slug]` | Nối thêm `?bo-suu-tap=...` khi lọc trên trang Gạch | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Allow` |
| `FLT-06` | Màu sắc | Trắng, Xám, Kem, Vàng, Nâu, Đen, Xanh dương, Xanh lá, Hồng, Đa sắc | Giữ ở tham số động: `/gach?mau-sac=...` | Nối thêm `&mau-sac=...` | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?mau-sac=*&` |
| `FLT-07` | Bề mặt (Finish) | Bóng kiếng (Polished), Mờ (Matt), Bán bóng (Lappato), Nhám sần (Structured), Hiệu ứng Sugar/Carving | Giữ ở tham số động: `/gach?be-mat=...` | Nối thêm `&be-mat=...` | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?be-mat=*&` |
| `FLT-08` | Phong cách thiết kế | Hiện đại (Modern), Tối giản (Minimalism), Wabi-Sabi, Japandi, Đông Dương (Indochine), Tân cổ điển | Giữ ở tham số động: `/gach?phong-cach=...` | Nối thêm `&phong-cach=...` | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?phong-cach=*&` |
| `FLT-09` | Hình dạng viên gạch | Hình vuông, Hình chữ nhật, Lục giác, Vảy cá, Lông vũ, Đèn lồng, Ziczac | Giữ ở tham số động: `/gach?hinh-dang=...` | Nối thêm `&hinh-dang=...` | `noindex, follow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?hinh-dang=` |
| `FLT-10` | Chỉ số chống trượt (DCOF / R-Value) | R9 (Khô ráo), R10 (Nhà tắm/Bếp), R11 (Sân vườn/Ngoài trời), R12-R13 (Hồ bơi/Dốc nghiêng) | Lọc bằng AJAX hoặc tham số `?dcof=...` | Nối thêm `&dcof=...` (Không dùng `<a href>` cho bot cào) | `noindex, nofollow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?*dcof=` |
| `FLT-11` | Độ hút nước (Water Absorption) | Siêu chống thấm (`<= 0.5%` - Porcelain), Thấp (`0.5% - 3%`), Trung bình (`3% - 6%`), Cao (`> 10%` - Ceramic) | Lọc bằng AJAX hoặc tham số `?do-hut-nuoc=...` | Nối thêm `&do-hut-nuoc=...` | `noindex, nofollow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?*do-hut-nuoc=` |
| `FLT-12` | Độ dày & Chịu lực | Mỏng (`6mm`), Tiêu chuẩn (`8.5 - 10mm`), Dày chịu lực (`12 - 20mm` cho gara ô tô) | Lọc bằng AJAX hoặc tham số `?do-day=...` | Nối thêm `&do-day=...` | `noindex, nofollow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?*do-day=` |
| `FLT-13` | Khoảng giá (VNĐ/m2) | Dưới 200k, 200k - 350k, 350k - 600k, Trên 600k, Giá dự án | Lọc bằng AJAX hoặc `?gia=...` | Nối thêm `&gia=...` | `noindex, nofollow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?*gia=` |
| `FLT-14` | Sắp xếp & Tiện ích | Bán chạy nhất, Mới về, Giá tăng/giảm, Có mẫu tại Showroom (`in_showroom=1`) | Lọc bằng AJAX / JS State (Không sinh thẻ `<a>`) | Nối thêm `&sort=...` | `noindex, nofollow` | Trỏ về URL danh mục tĩnh gốc đang đứng | `Disallow: /*?*sort=`<br>`Disallow: /*?*showroom=` |

### 3.2. Cấu hình file `robots.txt` chuẩn cho `gachdravit.vn`

```txt
User-agent: *
Allow: /

# Chặn các tham số lọc kỹ thuật sâu và tiện ích UI (Tầng 4)
Disallow: /*?*dcof=
Disallow: /*?*do-hut-nuoc=
Disallow: /*?*do-day=
Disallow: /*?*gia=
Disallow: /*?*sort=
Disallow: /*?*showroom=

# Chặn các tổ hợp lọc từ 2 tham số phụ trở lên (Tránh Spider Trap)
Disallow: /*?mau-sac=*&
Disallow: /*?be-mat=*&
Disallow: /*?phong-cach=*&
Disallow: /*?hinh-dang=
Disallow: /*?*&*&*

Sitemap: https://gachdravit.vn/sitemap.xml
```

---

## PHẦN 4: MẪU DỮ LIỆU GẮN TAG SẢN PHẨM ĐA CHIỀU (SEED DATA CHO DATABASE)

Dưới đây là 8 mã SKU mẫu đại diện cho các dòng sản phẩm của **Gạch Dravit**. Mỗi sản phẩm chỉ có **1 URL duy nhất** (`/san-pham/{slug}`), nhưng nhờ cơ chế gắn Tag 4 chiều, sản phẩm sẽ tự động xuất hiện tại 7–9 URL danh mục tĩnh khác nhau mà không bị trùng lặp:

| Mã SKU | Tên Sản phẩm | URL Sản phẩm Duy nhất (`/san-pham/{slug}`) | Tag 1: Công năng | Tag 2: Không gian | Tag 3: Vân / Chất liệu | Tag 4: Kích thước | Các URL Danh mục Tĩnh tự động hiển thị SP này | Primary Category (Cố định Breadcrumb) |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `DRV-8801M` | Gạch Lát Nền Dravit Vân Đá Marble Calacatta 80x80 Bóng Kiếng | `/san-pham/gach-lat-nen-dravit-van-da-marble-calacatta-80x80-drv8801m` | `lat-nen` | `phong-khach`, `mat-tien` | `van-da-marble`, `porcelain` | `80x80` | `/gach`, `/gach-lat-nen`, `/gach-phong-khach`, `/gach-mat-tien`, `/gach-van-da`, `/gach-porcelain`, `/gach-80x80` (7 URL) | `/gach-lat-nen` (`Trang chủ > Gạch > Gạch lát nền > SP`) |
| `DRV-3605S` | Gạch Ốp Lát Dravit Vân Đá Mờ 30x60 Chống Trượt R10 | `/san-pham/gach-op-lat-dravit-van-da-mo-30x60-drv3605s` | `lat-nen`, `op-tuong` | `nha-tam`, `phong-bep` | `van-da-tu-nhien`, `porcelain` | `30x60` | `/gach`, `/gach-lat-nen`, `/gach-op-tuong`, `/gach-nha-tam`, `/gach-phong-bep`, `/gach-van-da`, `/gach-porcelain`, `/gach-30x60` (8 URL) | `/gach-op-tuong` (`Trang chủ > Gạch > Gạch ốp tường > SP`) |
| `DRV-61209` | Gạch Ốp Lát Khổ Lớn Dravit 60x120 Vân Xi Măng Wabi-Sabi | `/san-pham/gach-op-lat-kho-lon-dravit-60x120-van-xi-mang-drv61209` | `lat-nen`, `op-tuong` | `phong-khach`, `nha-tam`, `phong-ngu` | `van-xi-mang`, `porcelain` | `60x120` | `/gach`, `/gach-lat-nen`, `/gach-op-tuong`, `/gach-phong-khach`, `/gach-nha-tam`, `/gach-phong-ngu`, `/gach-van-xi-mang`, `/gach-porcelain`, `/gach-60x120` (9 URL) | `/gach-60x120` (`Trang chủ > Gạch > Gạch 60x120 > SP`) |
| `DRV-SLAB01` | Đá Nung Kết Dravit Big Slab 120x240 Vân Đá Onyx Cao Cấp | `/san-pham/da-nung-ket-dravit-big-slab-120x240-van-da-onyx-drvslab01` | `lat-nen`, `op-tuong`, `trang-tri` | `phong-khach`, `mat-tien`, `phong-bep` | `da-nung-ket`, `van-da-marble` | `120x240` | `/gach`, `/gach-lat-nen`, `/gach-op-tuong`, `/gach-trang-tri`, `/gach-phong-khach`, `/gach-mat-tien`, `/gach-phong-bep`, `/da-nung-ket`, `/gach-van-da` (9 URL) | `/da-nung-ket` (`Trang chủ > Gạch > Đá nung kết > SP`) |
| `DRV-W1590` | Gạch Giả Gỗ Dravit 15x90 Thanh Dài Chống Trơn | `/san-pham/gach-gia-go-dravit-15x90-thanh-dai-drvw1590` | `lat-nen`, `op-tuong` | `phong-ngu`, `phong-khach`, `ngoai-troi` | `gia-go`, `porcelain` | `15x90` | `/gach`, `/gach-lat-nen`, `/gach-op-tuong`, `/gach-phong-ngu`, `/gach-phong-khach`, `/gach-ngoai-troi`, `/gach-gia-go`, `/gach-porcelain`, `/gach-15x80-20x100` (9 URL) | `/gach-gia-go` (`Trang chủ > Gạch > Gạch giả gỗ > SP`) |
| `DRV-TRZ60` | Gạch Terrazzo Hạt Đá Đa Sắc Dravit 60x60 Bề Mặt Matt | `/san-pham/gach-terrazzo-hat-da-dravit-60x60-drvtrz60` | `lat-nen`, `op-tuong`, `trang-tri` | `phong-khach`, `nha-tam`, `phong-bep`, `ngoai-troi` | `terrazzo`, `porcelain` | `60x60` | `/gach`, `/gach-lat-nen`, `/gach-op-tuong`, `/gach-trang-tri`, `/gach-phong-khach`, `/gach-nha-tam`, `/gach-phong-bep`, `/gach-ngoai-troi`, `/gach-terrazzo`, `/gach-60x60` (10 URL) | `/gach-terrazzo` (`Trang chủ > Gạch > Gạch Terrazzo > SP`) |
| `DRV-SV40` | Gạch Lát Sân Vườn Dravit 40x40 Giả Đá Tự Nhiên Dày 15mm | `/san-pham/gach-lat-san-vuon-dravit-40x40-gia-da-drvsv40` | `lat-nen` | `san-vuon`, `ngoai-troi` | `van-da-tu-nhien`, `porcelain` | `40x40` | `/gach`, `/gach-lat-nen`, `/gach-san-vuon`, `/gach-ngoai-troi`, `/gach-van-da`, `/gach-porcelain` (6 URL + Lọc `?kich-thuoc=40x40`) | `/gach-san-vuon` (`Trang chủ > Gạch > Gạch sân vườn > SP`) |
| `DRV-TH7530` | Gạch Thẻ Ốp Tường Dravit 7.5x30 Màu Xanh Ngọc Bề Mặt Bóng | `/san-pham/gach-the-op-tuong-dravit-75x30-xanh-ngoc-drvth7530` | `op-tuong`, `trang-tri`, `gach-the` | `phong-bep`, `nha-tam` | `ceramic` | `7.5x30` | `/gach`, `/gach-op-tuong`, `/gach-trang-tri`, `/gach-the`, `/gach-phong-bep`, `/gach-nha-tam` (6 URL + Lọc `?mau-sac=xanh-la`) | `/gach-the` (`Trang chủ > Gạch > Gạch thẻ > SP`) |

---

## PHẦN 5: MA TRẬN LIÊN KẾT NỘI BỘ TỰ ĐỘNG (AUTOMATED INTERNAL LINKING RULES)

AI Agent phụ trách Frontend & Backend cần lập trình tự động 6 luồng liên kết nội bộ dưới đây để khóa chặt dòng chảy PageRank trong Silo Gạch:

| Mã Luồng | Điểm xuất phát (Source Page) | Điểm đích (Target Page) | Vị trí hiển thị trên UI / HTML | Quy tắc Anchor Text | Tác động Kỹ thuật & SEO |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `IL-01` | **Trang Trụ cột (`/gach`)** | 27 URL Danh mục Tĩnh Cấp 2 (Công năng, Không gian, Vân, Kích thước) | 1. Thanh **Quick Category Pills** (thẻ `<a href>` tĩnh) ngay đầu danh sách SP.<br>2. Khối bài viết **Footer SEO Content** ở cuối trang. | Từ khóa chính xác (VD: *Gạch lát nền, Gạch phòng khách, Gạch giả gỗ, Gạch 80x80*) | Phân phối sức mạnh từ trang Pillar xuống trực tiếp 27 trang con mà không bị chặn bởi JavaScript của bộ lọc. |
| `IL-02` | **27 Trang Danh mục con** (`/gach-lat-nen`, `/gach-san-vuon`...) | Trang Trụ cột (`/gach`) & Các danh mục con cùng ngữ cảnh | 1. **Breadcrumb** (kèm JSON-LD Schema `BreadcrumbList`) ở đầu trang.<br>2. Khối **"Gợi ý theo nhu cầu"** (Cross-link Pills) trên đầu trang. | - Breadcrumb: `Gạch`<br>- Cross-link: Từ khóa danh mục liên quan (VD ở `/gach-lat-nen` đặt link sang `/gach-80x80`, `/gach-phong-khach`) | Củng cố quan hệ Cha - Con giữa `/gach` và các URL phẳng cấp 2; luân chuyển khách hàng và bot giữa 4 nhóm chồng chéo. |
| `IL-03` | **Trang Chi tiết Sản phẩm (`/san-pham/{slug}`)** | Tất cả các URL Danh mục Tĩnh mà sản phẩm đó mang Tag | **Bảng Thông số Kỹ thuật (Product Specifications Table):** Biến từng giá trị thuộc tính từ Plain Text thành thẻ `<a href>`. | Giá trị thuộc tính kết hợp từ khóa:<br>- Công năng: `[Gạch lát nền](/gach-lat-nen)`<br>- Không gian: `[Gạch phòng khách](/gach-phong-khach)`<br>- Kích thước: `[Gạch 80x80](/gach-80x80)` | Biến hàng nghìn trang sản phẩm thành hàng nghìn nguồn cấp Backlink nội bộ tự động trỏ thẳng về 28 trang Danh mục tĩnh. |
| `IL-04` | **Trang Chi tiết Sản phẩm (`/san-pham/{slug}`)** | 1 URL Danh mục chính (`primary_collection_id`) | Thanh **Breadcrumb** duy nhất ở đầu trang sản phẩm (`Trang chủ > Gạch > [Danh mục chính] > Tên SP`). | Tên Danh mục chính của sản phẩm đó | Tránh lỗi sinh nhiều đường dẫn Breadcrumb mâu thuẫn trên cùng 1 sản phẩm khi sản phẩm thuộc nhiều nhóm. |
| `IL-05` | **Silo Bài viết Cẩm nang (`/cam-nang/{slug}`)** | URL Danh mục Tĩnh mục tiêu + Các sản phẩm thuộc danh mục đó | 1. **Đoạn mở bài (150 chữ đầu - First Link Priority):** Link văn bản.<br>2. **Giữa bài:** Shortcode nhúng 4-8 sản phẩm thực tế + Nút CTA *"Xem tất cả"*. | - Mở bài: Từ khóa chính xác của Danh mục.<br>- Nút CTA: `Xem trọn bộ [X]+ mẫu [Tên Danh mục] chính hãng` | Đẩy sức mạnh Topical Authority từ Blog sang trang Danh mục thương mại, ngăn chặn lỗi ăn thịt từ khóa (Keyword Cannibalization). |
| `IL-06` | **Quy tắc Cô lập Silo (Silo Isolation Rule)** | Nội bộ ngành Gạch (`/gach`, 27 danh mục con, `/san-pham/...`) | Khu vực nội dung chính (`<main>`), Sidebar và Related Posts của các trang thuộc nhánh Gạch. | **CẤM** chèn link ngữ cảnh sang các ngành hàng khác (như Thiết bị vệ sinh, Bếp) trong phần `<main>`. | Giữ 100% độ thuần khiết ngữ nghĩa (Topical Relevance) cho Silo Gạch. Liên kết sang ngành khác chỉ nằm ở Header Mega Menu và Footer chung. |

---

## PHẦN 6: MẪU JSON-LD SCHEMA BẮT BUỘC CHO FRONTEND AGENT

Khi render bất kỳ trang Danh mục con nào trong 27 URL (ví dụ `/gach-phong-khach`), Frontend Agent cần tự động xuất cấu trúc `BreadcrumbList` và `CollectionPage` theo mẫu sau để Google hiểu rõ phân cấp Silo dù URL ở dạng phẳng:

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Trang chủ",
          "item": "https://gachdravit.vn/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "Gạch",
          "item": "https://gachdravit.vn/gach"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "Gạch Phòng Khách",
          "item": "https://gachdravit.vn/gach-phong-khach"
        }
      ]
    },
    {
      "@type": "CollectionPage",
      "@id": "https://gachdravit.vn/gach-phong-khach#webpage",
      "url": "https://gachdravit.vn/gach-phong-khach",
      "name": "Gạch Lát Nền & Ốp Tường Phòng Khách Sang Trọng | Gạch Dravit",
      "isPartOf": {
        "@id": "https://gachdravit.vn/gach#webpage"
      }
    }
  ]
}
```