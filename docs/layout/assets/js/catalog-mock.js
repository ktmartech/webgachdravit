/**
 * DRAVIT Catalog Mock Data, <drv-filter-form> (14 Facets AJAX Sim), Instant Search & Load More
 * Spec: docs/05_Faceted_Filter_AJAX.md & docs/07_UI_UX_Page_Specs.md (§2.1, §4)
 */
(() => {
  'use strict';

  const formatVnd = (n) =>
    window.Dravit ? window.Dravit.formatVnd(n) : `${new Intl.NumberFormat('vi-VN').format(Math.round(n))}đ`;

  /* ==========================================================================
     1. 28 Smart Collections Silo Registry (Mock Data for Instant Search)
     ========================================================================== */
  const DRV_MOCK_SILOS = [
    { slug: '02-catalog-collection.html', name: 'Gạch phòng khách', group: 'Không gian', count: 124 },
    { slug: '02-catalog-collection.html?khong-gian=nha-tam', name: 'Gạch nhà tắm / WC chống trượt', group: 'Không gian', count: 86 },
    { slug: '02-catalog-collection.html?khong-gian=phong-bep', name: 'Gạch ốp lát phòng bếp', group: 'Không gian', count: 64 },
    { slug: '02-catalog-collection.html?khong-gian=san-vuon', name: 'Gạch sân vườn & ngoài trời R11', group: 'Không gian', count: 52 },
    { slug: '02-catalog-collection.html?khong-gian=mat-tien', name: 'Gạch ốp mặt tiền', group: 'Không gian', count: 40 },
    { slug: '02-catalog-collection.html?kich-thuoc=80x80', name: 'Gạch 80x80 cm', group: 'Kích thước', count: 92 },
    { slug: '02-catalog-collection.html?kich-thuoc=60x120', name: 'Gạch khổ lớn 60x120 cm', group: 'Kích thước', count: 78 },
    { slug: '02-catalog-collection.html?kich-thuoc=60x60', name: 'Gạch 60x60 cm', group: 'Kích thước', count: 110 },
    { slug: '02-catalog-collection.html?kich-thuoc=30x60', name: 'Gạch ốp tường 30x60 cm', group: 'Kích thước', count: 95 },
    { slug: '02-catalog-collection.html?kich-thuoc=15x90', name: 'Gạch thanh gỗ 15x90 cm', group: 'Kích thước', count: 38 },
    { slug: '02-catalog-collection.html?van-gach=van-da-marble', name: 'Gạch vân đá Marble', group: 'Vân & Chất liệu', count: 108 },
    { slug: '02-catalog-collection.html?van-gach=terrazzo', name: 'Gạch Terrazzo hạt đá', group: 'Vân & Chất liệu', count: 46 },
    { slug: '02-catalog-collection.html?van-gach=gia-go', name: 'Gạch giả gỗ Porcelain', group: 'Vân & Chất liệu', count: 54 },
    { slug: '02-catalog-collection.html?van-gach=van-xi-mang', name: 'Gạch vân xi măng Minimalist', group: 'Vân & Chất liệu', count: 62 },
    { slug: '02-catalog-collection.html?van-gach=da-nung-ket', name: 'Đá nung kết Big Slab', group: 'Vân & Chất liệu', count: 29 },
  ];

  /* ==========================================================================
     2. Mock Product Database (12 Authentic Tile SKUs with Full 14-Facet Meta)
     ========================================================================== */
  const DRV_MOCK_PRODUCTS = [
    {
      sku: 'DRV-8801M',
      title: 'Gạch Lát Nền Dravit Vân Đá Marble Calacatta Trắng Khói 80x80 Bóng Kiếng',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach,mat-tien',
      size: '80x80',
      surface: 'bong-kieng',
      surfaceLabel: 'Bóng kiếng',
      color: 'trang',
      pattern: 'van-da-marble',
      origin: 'dravit-signature',
      style: 'luxury',
      shape: 'vuong',
      dcof: 'r9',
      water: 'duoi-05',
      thickness: '10mm',
      priceBand: '350k-600k',
      priceM2: 385000,
      regularPriceM2: 450000,
      pricePiece: 246400,
      discount: '-14%',
      showroom: true,
      popularity: 98,
      created: 202612,
      img1: 'assets/img/tile-marble-calacatta.svg',
      img2: 'assets/img/room-living-calacatta.svg',
    },
    {
      sku: 'DRV-TRZ80',
      title: 'Gạch Lát Nền Dravit Terrazzo Kem Đá Hạt Nhuyễn 80x80 Mờ Mịn R10',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach,phong-bep,nha-tam',
      size: '80x80',
      surface: 'mo-matt',
      surfaceLabel: 'Matt R10',
      color: 'kem',
      pattern: 'terrazzo',
      origin: 'dravit-signature',
      style: 'wabi-sabi',
      shape: 'vuong',
      dcof: 'r10',
      water: 'duoi-05',
      thickness: '10mm',
      priceBand: '350k-600k',
      priceM2: 395000,
      regularPriceM2: 0,
      pricePiece: 252800,
      discount: '',
      showroom: true,
      popularity: 95,
      created: 202611,
      img1: 'assets/img/tile-terrazzo-ivory.svg',
      img2: 'assets/img/room-bathroom-terrazzo.svg',
    },
    {
      sku: 'DRV-61209',
      title: 'Gạch Khổ Lớn Dravit Vân Đá Pietra Grey 60x120 Mờ Matt Chống Chói',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach,nha-tam,mat-tien',
      size: '60x120',
      surface: 'mo-matt',
      surfaceLabel: 'Matt R10',
      color: 'xam',
      pattern: 'van-da-marble',
      origin: 'nhap-khau-an-do',
      style: 'hien-dai',
      shape: 'chu-nhat',
      dcof: 'r10',
      water: 'duoi-05',
      thickness: '10mm',
      priceBand: '350k-600k',
      priceM2: 520000,
      regularPriceM2: 590000,
      pricePiece: 374400,
      discount: '-12%',
      showroom: true,
      popularity: 92,
      created: 202610,
      img1: 'assets/img/tile-cement-grey.svg',
      img2: 'assets/img/room-kitchen-cement.svg',
    },
    {
      sku: 'DRV-WD1590',
      title: 'Gạch Thanh Giả Gỗ Dravit Sồi Tự Nhiên 15x90 Xương Đá Porcelain R10',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-ngu,phong-khach',
      size: '15x90',
      surface: 'mo-matt',
      surfaceLabel: 'Vân gỗ R10',
      color: 'nau-go',
      pattern: 'gia-go',
      origin: 'viet-nam-xuat-khau',
      style: 'indochine',
      shape: 'thanh-dai',
      dcof: 'r10',
      water: 'duoi-05',
      thickness: '9.5mm',
      priceBand: '200k-350k',
      priceM2: 335000,
      regularPriceM2: 0,
      pricePiece: 45225,
      discount: '',
      showroom: true,
      popularity: 88,
      created: 202608,
      img1: 'assets/img/tile-wood-oak.svg',
      img2: 'assets/img/room-living-calacatta.svg',
    },
    {
      sku: 'DRV-6604C',
      title: 'Gạch Lát Nền Dravit Vân Xi Măng Xám Nhạt 60x60 Matt Chống Trượt R10',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-bep,nha-tam,phong-khach',
      size: '60x60',
      surface: 'mo-matt',
      surfaceLabel: 'Matt R10',
      color: 'xam',
      pattern: 'van-xi-mang',
      origin: 'viet-nam-xuat-khau',
      style: 'toi-gian',
      shape: 'vuong',
      dcof: 'r10',
      water: 'duoi-05',
      thickness: '9.5mm',
      priceBand: '200k-350k',
      priceM2: 265000,
      regularPriceM2: 310000,
      pricePiece: 95400,
      discount: '-15%',
      showroom: true,
      popularity: 90,
      created: 202607,
      img1: 'assets/img/tile-cement-grey.svg',
      img2: 'assets/img/room-kitchen-cement.svg',
    },
    {
      sku: 'DRV-SL1224',
      title: 'Đá Nung Kết Khổ Lớn Dravit Armani Dark 60x120 Bề Mặt Carving Tinh Xảo',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach,mat-tien',
      size: '60x120',
      surface: 'carving',
      surfaceLabel: 'Carving R10',
      color: 'den-than',
      pattern: 'da-nung-ket',
      origin: 'nhap-khau-y',
      style: 'luxury',
      shape: 'chu-nhat',
      dcof: 'r10',
      water: 'duoi-01',
      thickness: '12mm',
      priceBand: 'tren-600k',
      priceM2: 680000,
      regularPriceM2: 760000,
      pricePiece: 489600,
      discount: '-11%',
      showroom: true,
      popularity: 86,
      created: 202612,
      img1: 'assets/img/tile-sintered-emperador.svg',
      img2: 'assets/img/room-living-calacatta.svg',
    },
    {
      sku: 'DRV-OUT60',
      title: 'Gạch Sân Vườn Dravit Đá Basalt Nhám Sần 60x60 Chống Trượt R11 Dày 12mm',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'san-vuon,nha-tam',
      size: '60x60',
      surface: 'nham-san',
      surfaceLabel: 'Nhám R11',
      color: 'xam',
      pattern: 'van-xi-mang',
      origin: 'viet-nam-xuat-khau',
      style: 'hien-dai',
      shape: 'vuong',
      dcof: 'r11',
      water: 'duoi-05',
      thickness: '12mm',
      priceBand: '200k-350k',
      priceM2: 345000,
      regularPriceM2: 0,
      pricePiece: 124200,
      discount: '',
      showroom: false,
      popularity: 80,
      created: 202605,
      img1: 'assets/img/tile-structured-outdoor.svg',
      img2: 'assets/img/room-outdoor-patio.svg',
    },
    {
      sku: 'DRV-3608W',
      title: 'Gạch Ốp Tường Dravit Sọc Đũa Kiến Trúc Kem Ngà 30x60 Mờ Mịn',
      url: '03-product-single.html',
      usage: 'op-tuong',
      space: 'nha-tam,phong-bep,phong-khach',
      size: '30x60',
      surface: 'mo-matt',
      surfaceLabel: 'Matt R9',
      color: 'kem',
      pattern: 'terrazzo',
      origin: 'dravit-signature',
      style: 'wabi-sabi',
      shape: 'chu-nhat',
      dcof: 'r9',
      water: '05-3',
      thickness: '8.5mm',
      priceBand: '200k-350k',
      priceM2: 290000,
      regularPriceM2: 340000,
      pricePiece: 52200,
      discount: '-15%',
      showroom: true,
      popularity: 84,
      created: 202606,
      img1: 'assets/img/tile-decor-fluted.svg',
      img2: 'assets/img/room-bathroom-terrazzo.svg',
    },
    {
      sku: 'DRV-8812G',
      title: 'Gạch Lát Nền Dravit Vân Đá Onyx Kem Vàng 80x80 Bóng Kiếng Toàn Phần',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach',
      size: '80x80',
      surface: 'bong-kieng',
      surfaceLabel: 'Bóng kiếng',
      color: 'kem',
      pattern: 'van-da-marble',
      origin: 'nhap-khau-an-do',
      style: 'luxury',
      shape: 'vuong',
      dcof: 'r9',
      water: 'duoi-05',
      thickness: '10mm',
      priceBand: '350k-600k',
      priceM2: 415000,
      regularPriceM2: 0,
      pricePiece: 265600,
      discount: '',
      showroom: true,
      popularity: 89,
      created: 202609,
      img1: 'assets/img/tile-marble-calacatta.svg',
      img2: 'assets/img/room-living-calacatta.svg',
    },
    {
      sku: 'DRV-61215',
      title: 'Gạch Khổ Lớn Dravit Vân Đá Travertine Kem Cát 60x120 Carving Mờ',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach,mat-tien',
      size: '60x120',
      surface: 'carving',
      surfaceLabel: 'Carving R10',
      color: 'kem',
      pattern: 'van-da-marble',
      origin: 'nhap-khau-tbn',
      style: 'wabi-sabi',
      shape: 'chu-nhat',
      dcof: 'r10',
      water: 'duoi-05',
      thickness: '10mm',
      priceBand: '350k-600k',
      priceM2: 545000,
      regularPriceM2: 620000,
      pricePiece: 392400,
      discount: '-12%',
      showroom: true,
      popularity: 91,
      created: 202611,
      img1: 'assets/img/tile-terrazzo-ivory.svg',
      img2: 'assets/img/room-kitchen-cement.svg',
    },
    {
      sku: 'DRV-8824S',
      title: 'Gạch Lát Nền Dravit Vân Đá Statuario Trắng Vân Mây 80x80 Mờ Matt R10',
      url: '03-product-single.html',
      usage: 'lat-nen',
      space: 'phong-khach,phong-ngu',
      size: '80x80',
      surface: 'mo-matt',
      surfaceLabel: 'Matt R10',
      color: 'trang',
      pattern: 'van-da-marble',
      origin: 'dravit-signature',
      style: 'hien-dai',
      shape: 'vuong',
      dcof: 'r10',
      water: 'duoi-05',
      thickness: '10mm',
      priceBand: '350k-600k',
      priceM2: 375000,
      regularPriceM2: 430000,
      pricePiece: 240000,
      discount: '-13%',
      showroom: true,
      popularity: 93,
      created: 202610,
      img1: 'assets/img/tile-marble-calacatta.svg',
      img2: 'assets/img/room-living-calacatta.svg',
    },
    {
      sku: 'DRV-61230',
      title: 'Đá Nung Kết Dravit Emperador Nâu Trầm 60x120 Bóng Kiếng Chống Trầy',
      url: '03-product-single.html',
      usage: 'op-tuong',
      space: 'phong-khach,mat-tien',
      size: '60x120',
      surface: 'bong-kieng',
      surfaceLabel: 'Bóng kiếng',
      color: 'nau-go',
      pattern: 'da-nung-ket',
      origin: 'nhap-khau-y',
      style: 'indochine',
      shape: 'chu-nhat',
      dcof: 'r9',
      water: 'duoi-01',
      thickness: '12mm',
      priceBand: 'tren-600k',
      priceM2: 640000,
      regularPriceM2: 0,
      pricePiece: 460800,
      discount: '',
      showroom: true,
      popularity: 85,
      created: 202609,
      img1: 'assets/img/tile-sintered-emperador.svg',
      img2: 'assets/img/room-living-calacatta.svg',
    },
  ];

  const FACET_LABELS = {
    'lat-nen': 'Công năng: Gạch lát nền',
    'op-tuong': 'Công năng: Gạch ốp tường',
    'phong-khach': 'Không gian: Phòng khách',
    'phong-ngu': 'Không gian: Phòng ngủ',
    'phong-bep': 'Không gian: Phòng bếp',
    'nha-tam': 'Không gian: Nhà tắm / WC',
    'san-vuon': 'Không gian: Sân vườn / Ngoài trời',
    'mat-tien': 'Không gian: Mặt tiền',
    '80x80': 'Kích thước: 80x80 cm',
    '60x120': 'Kích thước: 60x120 cm',
    '60x60': 'Kích thước: 60x60 cm',
    '30x60': 'Kích thước: 30x60 cm',
    '15x90': 'Kích thước: 15x90 cm',
    '120x240': 'Kích thước: 120x240 cm',
    'bong-kieng': 'Bề mặt: Bóng kiếng',
    'mo-matt': 'Bề mặt: Mờ (Matt)',
    'nham-san': 'Bề mặt: Nhám sần (Structured)',
    'carving': 'Bề mặt: Hiệu ứng Carving',
    'trang': 'Màu: Trắng vân mây',
    'kem': 'Màu: Kem đá (Ivory)',
    'xam': 'Màu: Xám xi măng',
    'nau-go': 'Màu: Nâu gỗ ấm',
    'den-than': 'Màu: Đen than',
    'van-da-marble': 'Vân đá Marble',
    'terrazzo': 'Gạch Terrazzo',
    'gia-go': 'Gạch giả gỗ',
    'van-xi-mang': 'Vân xi măng',
    'da-nung-ket': 'Đá nung kết',
    'dravit-signature': 'BST: Dravit Signature',
    'nhap-khau-y': 'Xuất xứ: Nhập khẩu Ý',
    'nhap-khau-tbn': 'Xuất xứ: Tây Ban Nha',
    'nhap-khau-an-do': 'Xuất xứ: Ấn Độ',
    'viet-nam-xuat-khau': 'Xuất xứ: VN Xuất khẩu',
    'hien-dai': 'Phong cách: Hiện đại',
    'toi-gian': 'Phong cách: Tối giản (Minimalist)',
    'wabi-sabi': 'Phong cách: Wabi-Sabi / Japandi',
    'indochine': 'Phong cách: Đông Dương (Indochine)',
    'luxury': 'Phong cách: Sang trọng (Luxury)',
    'vuong': 'Hình dạng: Vuông',
    'chu-nhat': 'Hình dạng: Chữ nhật',
    'thanh-dai': 'Hình dạng: Thanh thẻ dài',
    'duoi-200k': 'Giá: Dưới 200.000đ/m²',
    '200k-350k': 'Giá: 200.000đ - 350.000đ/m²',
    '350k-600k': 'Giá: 350.000đ - 600.000đ/m²',
    'tren-600k': 'Giá: Trên 600.000đ/m²',
    'r9': 'Chống trượt: R9 (Phòng khô)',
    'r10': 'Chống trượt: R10 (Bếp / Nhà tắm)',
    'r11': 'Chống trượt: R11 (Sân vườn / Ướt)',
    'duoi-05': 'Hút nước: ≤ 0,5% (Porcelain)',
    'duoi-01': 'Hút nước: ≤ 0,1% (Đá nung kết)',
    '05-3': 'Hút nước: 0,5% - 3%',
    '8.5mm': 'Độ dày: 8,5 mm',
    '9.5mm': 'Độ dày: 9,5 mm',
    '10mm': 'Độ dày: 10 mm',
    '12mm': 'Độ dày: 12 mm',
    'showroom': 'Có mẫu tại kệ Showroom',
  };

  /* ==========================================================================
     3. Render Product Card HTML Helper
     ========================================================================== */
  function renderProductCardHtml(item) {
    const badgesHtml = [
      item.discount ? `<span class="drv-badge drv-badge--sale">${item.discount}</span>` : '',
      item.showroom ? `<span class="drv-badge drv-badge--showroom">Mẫu tại kệ</span>` : '',
    ]
      .filter(Boolean)
      .join('');

    const priceHtml = item.regularPriceM2
      ? `<span class="drv-card__price-m2 drv-card__price-m2--sale">${formatVnd(item.priceM2)}/m²</span>
         <span class="drv-card__price-old">${formatVnd(item.regularPriceM2)}</span>`
      : `<span class="drv-card__price-m2">${formatVnd(item.priceM2)}/m²</span>`;

    return `
      <article class="drv-card"
        data-product-card
        data-usage="${item.usage || 'lat-nen'}"
        data-space="${item.space || 'phong-khach'}"
        data-size="${item.size}"
        data-surface="${item.surface}"
        data-color="${item.color}"
        data-pattern="${item.pattern}"
        data-origin="${item.origin || 'dravit-signature'}"
        data-style="${item.style || 'hien-dai'}"
        data-shape="${item.shape || 'vuong'}"
        data-price-band="${item.priceBand}"
        data-dcof="${item.dcof}"
        data-water="${item.water}"
        data-thickness="${item.thickness}"
        data-showroom="${item.showroom ? 'true' : 'false'}"
        data-price-m2="${item.priceM2}"
        data-popularity="${item.popularity}"
        data-created="${item.created || 202610}">
        <div class="drv-card__media">
          <div class="drv-card__badges">${badgesHtml}</div>
          <img class="drv-card__img drv-card__img--primary" src="${item.img1}" width="400" height="400" loading="lazy" decoding="async" alt="${item.title}">
          <img class="drv-card__img drv-card__img--hover" src="${item.img2}" width="400" height="400" loading="lazy" decoding="async" alt="Phối cảnh ${item.title}">
        </div>
        <div class="drv-card__meta">
          <div class="drv-card__tags">
            <span class="drv-card__tag">${item.size}</span>
            <span class="drv-card__tag">${item.surfaceLabel}</span>
          </div>
          <span class="drv-card__sku">${item.sku}</span>
        </div>
        <div class="drv-card__body">
          <h3 class="drv-card__title">
            <a class="drv-card__link" href="${item.url}">${item.title}</a>
          </h3>
          <div class="drv-card__footer">
            <div class="drv-card__prices">
              <div class="drv-card__price-main">${priceHtml}</div>
              <div class="drv-card__price-piece">≈ ${formatVnd(item.pricePiece)}/viên</div>
            </div>
            <a href="${item.url}" class="drv-card__action" aria-label="Tính số lượng và xem chi tiết ${item.sku}">
              <i class="ph ph-calculator drv-icon" aria-hidden="true"></i>
            </a>
          </div>
        </div>
      </article>
    `;
  }

  /* ==========================================================================
     4. <drv-filter-form> — 14-Facet Interactive Catalog Filter Web Component
     ========================================================================== */
  class DrvFilterForm extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      this.grid = document.getElementById('drv-catalog-grid');
      this.chipsContainer = document.getElementById('drv-active-chips');
      this.countEls = document.querySelectorAll('[data-catalog-count]');
      this.totalEls = document.querySelectorAll('[data-catalog-total]');
      this.sortSelect = document.getElementById('drv-catalog-sort');
      this.emptyState = document.getElementById('drv-catalog-empty');
      this.loadMoreWrap = document.getElementById('drv-load-more-wrap');
      this.progressBar = document.querySelector('[data-catalog-progress]');
      this.robotsBadge = document.querySelector('[data-catalog-robots-badge]');
      this.mobileDrawer = document.getElementById('drv-filter-drawer');

      this._bindEvents();
      this._applyUrlInitialParams();
      this.applyFilters();
    }

    _bindEvents() {
      this.addEventListener('change', (event) => {
        this._syncInputToMirror(event.target, this.mobileDrawer);
        this.applyFilters();
      });

      this.addEventListener('click', (event) => {
        const chipBtn = event.target.closest('[data-facet-chip]');
        if (chipBtn) {
          event.preventDefault();
          const isPressed = chipBtn.getAttribute('aria-pressed') === 'true';
          const nextState = !isPressed;
          chipBtn.setAttribute('aria-pressed', String(nextState));
          chipBtn.classList.toggle('is-active', nextState);
          this._syncChipToMirror(chipBtn.dataset.facet, chipBtn.dataset.value, nextState, this.mobileDrawer);
          this.applyFilters();
          return;
        }

        const resetBtn = event.target.closest('[data-filter-reset]');
        if (resetBtn) {
          event.preventDefault();
          this.resetAll();
        }
      });

      if (this.mobileDrawer) {
        this.mobileDrawer.addEventListener('change', (event) => {
          this._syncInputToMirror(event.target, this);
          this.applyFilters();
        });

        this.mobileDrawer.addEventListener('click', (event) => {
          const chipBtn = event.target.closest('[data-facet-chip]');
          if (chipBtn) {
            event.preventDefault();
            const isPressed = chipBtn.getAttribute('aria-pressed') === 'true';
            const nextState = !isPressed;
            chipBtn.setAttribute('aria-pressed', String(nextState));
            chipBtn.classList.toggle('is-active', nextState);
            this._syncChipToMirror(chipBtn.dataset.facet, chipBtn.dataset.value, nextState, this);
            this.applyFilters();
            return;
          }

          const resetBtn = event.target.closest('[data-filter-reset]');
          if (resetBtn) {
            event.preventDefault();
            this.resetAll();
          }
        });
      }

      if (this.sortSelect) {
        this.sortSelect.addEventListener('change', () => this.applyFilters());
      }

      // Active Chips removal, Sub-Silo quick filters, Load More & SEO Toggle
      document.addEventListener('click', (event) => {
        const removeChip = event.target.closest('[data-remove-filter]');
        if (removeChip) {
          event.preventDefault();
          const facetKey = removeChip.dataset.facetKey;
          const facetVal = removeChip.dataset.facetVal;
          this._uncheckFilter(facetKey, facetVal);
          this.applyFilters();
          return;
        }

        const clearAll = event.target.closest('[data-clear-all-filters]');
        if (clearAll) {
          event.preventDefault();
          this.resetAll();
          return;
        }

        const quickSilo = event.target.closest('[data-silo-quick-filter]');
        if (quickSilo) {
          event.preventDefault();
          const fKey = quickSilo.dataset.siloQuickFilter;
          const fVal = quickSilo.dataset.siloValue;
          const alreadyActive = quickSilo.classList.contains('is-active');
          this.resetAll(false);
          document.querySelectorAll('[data-silo-quick-filter]').forEach((el) => el.classList.remove('is-active'));
          if (!alreadyActive && fKey && fVal) {
            quickSilo.classList.add('is-active');
            this._activateFacetValue(fKey, fVal);
          }
          this.applyFilters();
          return;
        }

        const loadMoreBtn = event.target.closest('[data-drv-load-more]');
        if (loadMoreBtn && this.grid) {
          event.preventDefault();
          const appendedHtml = DRV_MOCK_PRODUCTS.slice(0, 6).map(renderProductCardHtml).join('');
          this.grid.insertAdjacentHTML('beforeend', appendedHtml);
          loadMoreBtn.hidden = true;
          this.applyFilters();
          if (window.Dravit) window.Dravit.toast('Đã tải thêm 6 mẫu gạch trong danh mục.');
          return;
        }

        const seoToggleBtn = event.target.closest('[data-drv-seo-toggle]');
        if (seoToggleBtn) {
          event.preventDefault();
          const contentBox = document.getElementById('drv-seo-guide-content');
          if (contentBox) {
            const isCollapsed = contentBox.classList.toggle('is-collapsed');
            seoToggleBtn.setAttribute('aria-expanded', String(!isCollapsed));
            const labelSpan = seoToggleBtn.querySelector('[data-seo-toggle-label]');
            const iconEl = seoToggleBtn.querySelector('i.ph');
            if (labelSpan) {
              labelSpan.textContent = isCollapsed
                ? 'Xem toàn bộ hướng dẫn chọn gạch phòng khách'
                : 'Thu gọn hướng dẫn';
            }
            if (iconEl) {
              iconEl.className = isCollapsed ? 'ph ph-caret-down drv-icon' : 'ph ph-caret-up drv-icon';
            }
          }
        }
      });
    }

    _syncChipToMirror(facet, value, isPressed, container) {
      if (!container) return;
      const mirrorBtn = container.querySelector(`[data-facet-chip][data-facet="${facet}"][data-value="${value}"]`);
      if (mirrorBtn) {
        mirrorBtn.setAttribute('aria-pressed', String(isPressed));
        mirrorBtn.classList.toggle('is-active', isPressed);
      }
    }

    _syncInputToMirror(inputEl, container) {
      if (!container || !inputEl || !inputEl.name) return;
      if (inputEl.type === 'radio') {
        container.querySelectorAll(`input[type="radio"][name="${inputEl.name}"]`).forEach((r) => {
          r.checked = r.value === inputEl.value;
        });
      } else if (inputEl.type === 'checkbox') {
        const mirrorChk = container.querySelector(`input[type="checkbox"][name="${inputEl.name}"][value="${inputEl.value}"]`);
        if (mirrorChk) mirrorChk.checked = inputEl.checked;
      }
    }

    _activateFacetValue(facetName, facetVal) {
      [this, this.mobileDrawer].forEach((root) => {
        if (!root) return;
        const chip = root.querySelector(`[data-facet-chip][data-facet="${facetName}"][data-value="${facetVal}"]`);
        if (chip) {
          chip.setAttribute('aria-pressed', 'true');
          chip.classList.add('is-active');
        }
        const chk = root.querySelector(`input[name="${facetName}"][value="${facetVal}"]`);
        if (chk) chk.checked = true;
      });
    }

    _applyUrlInitialParams() {
      const params = new URLSearchParams(window.location.search);
      ['kich-thuoc', 'mau-sac', 'van-gach', 'be-mat', 'khong-gian', 'cong-nang', 'do-day', 'dcof'].forEach((paramKey) => {
        const val = params.get(paramKey);
        if (val) {
          this._activateFacetValue(paramKey, val);
        }
      });
    }

    getActiveState() {
      const state = {
        usage: [],
        space: [],
        size: [],
        surface: [],
        color: [],
        pattern: [],
        origin: [],
        style: [],
        shape: [],
        priceBand: [],
        dcof: [],
        water: [],
        thickness: [],
        showroom: false,
      };

      this.querySelectorAll('[data-facet-chip][aria-pressed="true"]').forEach((btn) => {
        const facet = btn.dataset.facet;
        const val = btn.dataset.value;
        if (facet === 'kich-thuoc') state.size.push(val);
        if (facet === 'mau-sac') state.color.push(val);
      });

      this.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked').forEach((inp) => {
        if (!inp.value) return;
        switch (inp.name) {
          case 'cong-nang':
            state.usage.push(inp.value);
            break;
          case 'khong-gian':
            state.space.push(inp.value);
            break;
          case 'be-mat':
            state.surface.push(inp.value);
            break;
          case 'van-gach':
            state.pattern.push(inp.value);
            break;
          case 'bo-suu-tap':
            state.origin.push(inp.value);
            break;
          case 'phong-cach':
            state.style.push(inp.value);
            break;
          case 'hinh-dang':
            state.shape.push(inp.value);
            break;
          case 'gia':
            state.priceBand.push(inp.value);
            break;
          case 'dcof':
            state.dcof.push(inp.value);
            break;
          case 'do-hut-nuoc':
            state.water.push(inp.value);
            break;
          case 'do-day':
            state.thickness.push(inp.value);
            break;
          case 'showroom':
            state.showroom = true;
            break;
          default:
            break;
        }
      });

      return state;
    }

    _uncheckFilter(facetKey, facetVal) {
      [this, this.mobileDrawer].forEach((root) => {
        if (!root) return;
        if (facetKey === 'size' || facetKey === 'color') {
          const attrName = facetKey === 'size' ? 'kich-thuoc' : 'mau-sac';
          const btn = root.querySelector(`[data-facet-chip][data-facet="${attrName}"][data-value="${facetVal}"]`);
          if (btn) {
            btn.setAttribute('aria-pressed', 'false');
            btn.classList.remove('is-active');
          }
        } else if (facetKey === 'showroom') {
          const chk = root.querySelector('input[name="showroom"]');
          if (chk) chk.checked = false;
        } else {
          const inp = root.querySelector(`input[value="${facetVal}"]`);
          if (inp) inp.checked = false;
          if (facetKey === 'priceBand') {
            const allRadio = root.querySelector('input[name="gia"][value=""]');
            if (allRadio) allRadio.checked = true;
          }
        }
      });
    }

    resetAll(triggerApply = true) {
      [this, this.mobileDrawer].forEach((root) => {
        if (!root) return;
        root.querySelectorAll('[data-facet-chip]').forEach((btn) => {
          btn.setAttribute('aria-pressed', 'false');
          btn.classList.remove('is-active');
        });
        root.querySelectorAll('input[type="checkbox"]').forEach((chk) => {
          chk.checked = false;
        });
        root.querySelectorAll('input[type="radio"]').forEach((rad) => {
          rad.checked = rad.value === '';
        });
      });
      document.querySelectorAll('[data-silo-quick-filter]').forEach((el) => el.classList.remove('is-active'));
      if (triggerApply) this.applyFilters();
    }

    applyFilters() {
      if (!this.grid) return;
      const st = this.getActiveState();
      const cards = Array.from(this.grid.querySelectorAll('[data-product-card]'));

      let visibleCount = 0;

      cards.forEach((card) => {
        const cardSpaces = (card.dataset.space || '').split(',');
        const matchUsage = st.usage.length === 0 || st.usage.includes(card.dataset.usage);
        const matchSpace = st.space.length === 0 || st.space.some((sp) => cardSpaces.includes(sp));
        const matchSize = st.size.length === 0 || st.size.includes(card.dataset.size);
        const matchSurface = st.surface.length === 0 || st.surface.includes(card.dataset.surface);
        const matchColor = st.color.length === 0 || st.color.includes(card.dataset.color);
        const matchPattern = st.pattern.length === 0 || st.pattern.includes(card.dataset.pattern);
        const matchOrigin = st.origin.length === 0 || st.origin.includes(card.dataset.origin);
        const matchStyle = st.style.length === 0 || st.style.includes(card.dataset.style);
        const matchShape = st.shape.length === 0 || st.shape.includes(card.dataset.shape);
        const matchPrice = st.priceBand.length === 0 || st.priceBand.includes(card.dataset.priceBand);
        const matchDcof = st.dcof.length === 0 || st.dcof.includes(card.dataset.dcof);
        const matchWater = st.water.length === 0 || st.water.includes(card.dataset.water);
        const matchThick = st.thickness.length === 0 || st.thickness.includes(card.dataset.thickness);
        const matchShowroom = !st.showroom || card.dataset.showroom === 'true';

        const isMatch =
          matchUsage &&
          matchSpace &&
          matchSize &&
          matchSurface &&
          matchColor &&
          matchPattern &&
          matchOrigin &&
          matchStyle &&
          matchShape &&
          matchPrice &&
          matchDcof &&
          matchWater &&
          matchThick &&
          matchShowroom;

        card.hidden = !isMatch;
        if (isMatch) visibleCount += 1;
      });

      // Apply Sorting
      const sortMode = this.sortSelect?.value || 'ban-chay';
      const sortedCards = cards.slice().sort((a, b) => {
        const priceA = Number(a.dataset.priceM2 || 0);
        const priceB = Number(b.dataset.priceM2 || 0);
        const popA = Number(a.dataset.popularity || 0);
        const popB = Number(b.dataset.popularity || 0);
        const createdA = Number(a.dataset.created || 0);
        const createdB = Number(b.dataset.created || 0);
        if (sortMode === 'gia-tang') return priceA - priceB;
        if (sortMode === 'gia-giam') return priceB - priceA;
        if (sortMode === 'moi-nhat') return createdB - createdA;
        return popB - popA;
      });
      sortedCards.forEach((c) => this.grid.appendChild(c));

      // Update counters & empty state
      this.countEls.forEach((el) => {
        el.textContent = String(visibleCount);
      });

      if (this.emptyState) {
        this.emptyState.hidden = visibleCount > 0;
      }
      if (this.loadMoreWrap) {
        this.loadMoreWrap.hidden = visibleCount === 0;
      }
      if (this.progressBar) {
        const ratio = Math.min(1, visibleCount / Math.max(1, cards.length));
        this.progressBar.style.transform = `scaleX(${ratio})`;
      }

      this._renderActiveChips(st, sortMode);
    }

    _renderActiveChips(st, sortMode) {
      if (!this.chipsContainer) return;

      const activeItems = [];
      [
        'usage',
        'space',
        'size',
        'surface',
        'color',
        'pattern',
        'origin',
        'style',
        'shape',
        'priceBand',
        'dcof',
        'water',
        'thickness',
      ].forEach((key) => {
        st[key].forEach((val) => {
          activeItems.push({ key, val, label: FACET_LABELS[val] || val });
        });
      });
      if (st.showroom) {
        activeItems.push({ key: 'showroom', val: 'showroom', label: FACET_LABELS.showroom });
      }

      // Update Robots Matrix Indicator (index,follow vs noindex,follow per docs/05_Faceted_Filter_AJAX.md)
      if (this.robotsBadge) {
        const isNoindex = activeItems.length >= 2 || (sortMode && sortMode !== 'ban-chay');
        this.robotsBadge.textContent = isNoindex ? 'robots: noindex, follow' : 'robots: index, follow';
        this.robotsBadge.className = isNoindex
          ? 'drv-badge drv-badge--outline u-tabular'
          : 'drv-badge drv-badge--showroom u-tabular';
      }

      if (activeItems.length === 0) {
        this.chipsContainer.innerHTML =
          '<span class="u-text-xs u-text-muted">Chưa chọn bộ lọc phụ (Đang xem toàn bộ mẫu gạch phòng khách)</span>';
        return;
      }

      const chipsHtml = activeItems
        .map(
          (item) => `
          <button type="button" class="drv-chip drv-chip--square is-active" data-remove-filter data-facet-key="${item.key}" data-facet-val="${item.val}">
            <span>${item.label}</span>
            <i class="ph ph-x drv-icon--xs" aria-hidden="true"></i>
          </button>`
        )
        .join('');

      this.chipsContainer.innerHTML = `
        ${chipsHtml}
        <button type="button" class="drv-link u-text-xs" data-clear-all-filters>Xóa tất cả</button>
      `;
    }
  }

  if (!customElements.get('drv-filter-form')) {
    customElements.define('drv-filter-form', DrvFilterForm);
  }

  /* ==========================================================================
     5. Instant Search Dropdown Controller ([data-drv-search])
     ========================================================================== */
  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function normalizeVn(str) {
    return String(str || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .replace(/Đ/g, 'D')
      .toLowerCase();
  }

  function highlightKeyword(text, rawQuery) {
    const src = String(text || '');
    const q = String(rawQuery || '').trim();
    if (!q) return escapeHtml(src);

    const normSrc = normalizeVn(src);
    const normQ = normalizeVn(q);
    if (!normQ) return escapeHtml(src);

    let result = '';
    let lastIdx = 0;
    let matchIdx = normSrc.indexOf(normQ, lastIdx);

    while (matchIdx !== -1) {
      result += escapeHtml(src.slice(lastIdx, matchIdx));
      result += `<mark class="drv-search-hl">${escapeHtml(src.slice(matchIdx, matchIdx + normQ.length))}</mark>`;
      lastIdx = matchIdx + normQ.length;
      matchIdx = normSrc.indexOf(normQ, lastIdx);
    }

    result += escapeHtml(src.slice(lastIdx));
    return result;
  }

  function initInstantSearch() {
    const searchWrappers = document.querySelectorAll('.drv-search');
    searchWrappers.forEach((wrapper) => {
      const input = wrapper.querySelector('.drv-search__input');
      const drop = wrapper.querySelector('.drv-search-drop');
      if (!input || !drop) return;

      let backdrop = wrapper.querySelector('[data-drv-search-backdrop]');
      if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'drv-search-backdrop';
        backdrop.setAttribute('data-drv-search-backdrop', '');
        backdrop.hidden = true;
        wrapper.insertBefore(backdrop, wrapper.firstChild);
      }

      const headerEl = wrapper.closest('.drv-header');
      const siloListEl = drop.querySelector('[data-search-silos]');
      const prodListEl = drop.querySelector('[data-search-products]');
      const siloHeadingEl = drop.querySelector('[data-search-silo-heading]');
      const prodHeadingEl = drop.querySelector('[data-search-prod-heading]');
      const viewAllEl = drop.querySelector('[data-search-view-all]');

      const openSearchOverlay = () => {
        wrapper.classList.add('is-open');
        if (headerEl) headerEl.classList.add('has-search-open');
        backdrop.hidden = false;
        drop.hidden = false;
      };

      const closeSearchOverlay = () => {
        wrapper.classList.remove('is-open');
        if (headerEl) headerEl.classList.remove('has-search-open');
        backdrop.hidden = true;
        drop.hidden = true;
      };

      const runSearch = (rawQuery) => {
        const q = String(rawQuery || '').trim();
        const normQ = normalizeVn(q);
        const hasQuery = normQ.length > 0;

        const matchedSilos = hasQuery
          ? DRV_MOCK_SILOS.filter(
              (s) => normalizeVn(s.name).includes(normQ) || normalizeVn(s.group).includes(normQ)
            )
          : DRV_MOCK_SILOS.slice(0, 6);

        const matchedProds = hasQuery
          ? DRV_MOCK_PRODUCTS.filter(
              (p) =>
                normalizeVn(p.title).includes(normQ) ||
                normalizeVn(p.sku).includes(normQ) ||
                normalizeVn(p.size).includes(normQ) ||
                normalizeVn(p.surfaceLabel).includes(normQ)
            )
          : DRV_MOCK_PRODUCTS.slice(0, 4);

        if (siloHeadingEl) {
          siloHeadingEl.textContent = hasQuery
            ? `Danh mục khớp (${matchedSilos.length})`
            : 'Danh mục phổ biến';
        }

        if (prodHeadingEl) {
          prodHeadingEl.textContent = hasQuery
            ? `Sản phẩm khớp (${matchedProds.length})`
            : 'Sản phẩm tìm kiếm nhiều';
        }

        if (viewAllEl) {
          viewAllEl.href = hasQuery
            ? `10-policies-search-404.html?s=${encodeURIComponent(q)}`
            : '02-catalog-collection.html';
          viewAllEl.textContent = hasQuery ? `Xem tất cả (${matchedProds.length})` : 'Xem toàn bộ kho';
        }

        if (siloListEl) {
          const silosToRender = matchedSilos.length > 0 ? matchedSilos.slice(0, 6) : DRV_MOCK_SILOS.slice(0, 5);
          siloListEl.innerHTML = silosToRender
            .map(
              (s) => `
              <li>
                <a href="${s.slug}" class="drv-search-silo-link" title="${escapeHtml(s.name)}">
                  <span class="drv-search-silo-link__name">${highlightKeyword(s.name, q)}</span>
                  <span class="drv-badge drv-badge--outline">${s.count} mẫu</span>
                </a>
              </li>`
            )
            .join('');
        }

        if (prodListEl) {
          if (hasQuery && matchedProds.length === 0) {
            prodListEl.innerHTML = `
              <li class="drv-search-empty">
                Không tìm thấy mẫu gạch khớp với từ khóa <strong>"${escapeHtml(q)}"</strong>.<br>
                <span class="u-text-xs">Hãy thử tìm theo kích thước (80x80, 60x120) hoặc vân gạch (Marble, Terrazzo).</span>
              </li>`;
          } else {
            prodListEl.innerHTML = matchedProds
              .slice(0, 4)
              .map(
                (p) => `
                <li>
                  <a href="${p.url}" class="drv-search-item" title="${escapeHtml(p.title)}">
                    <img class="drv-search-item__thumb" src="${p.img1}" width="52" height="52" alt="${escapeHtml(p.title)}">
                    <div class="drv-search-item__info">
                      <div class="drv-search-item__title">${highlightKeyword(p.title, q)}</div>
                      <div class="drv-search-item__meta">
                        <span class="drv-badge drv-badge--outline">${highlightKeyword(p.size, q)}</span>
                        <span class="u-tabular">Mã: <strong>${highlightKeyword(p.sku, q)}</strong></span>
                        <span>· ${highlightKeyword(p.surfaceLabel, q)}</span>
                      </div>
                    </div>
                    <div class="drv-search-item__price">
                      <span class="u-text-sm u-bold u-text-navy u-tabular">${formatVnd(p.priceM2)}/m²</span>
                      <span class="u-text-xs u-text-muted u-tabular">≈ ${formatVnd(p.pricePiece)}/viên</span>
                    </div>
                  </a>
                </li>`
              )
              .join('');
          }
        }

        openSearchOverlay();
      };

      const debouncedSearch = window.Dravit ? window.Dravit.debounce(runSearch, 120) : runSearch;

      input.addEventListener('focus', () => runSearch(input.value));
      input.addEventListener('click', () => runSearch(input.value));
      input.addEventListener('input', () => debouncedSearch(input.value));

      backdrop.addEventListener('click', () => {
        closeSearchOverlay();
      });

      drop.addEventListener('click', (event) => {
        const tagBtn = event.target.closest('[data-search-keyword]');
        if (tagBtn) {
          event.preventDefault();
          const kw = tagBtn.dataset.searchKeyword || '';
          input.value = kw;
          input.focus();
          runSearch(kw);
        }
      });

      wrapper._closeSearchOverlay = closeSearchOverlay;
    });
  }

  /* ==========================================================================
     6. Blog Sidebar Quick Tile Calculator ([data-drv-blog-calc])
     ========================================================================== */
  function initBlogQuickCalc() {
    document.querySelectorAll('[data-drv-blog-calc]').forEach((box) => {
      const areaInput = box.querySelector('[data-blog-calc-area]');
      const sizeSelect = box.querySelector('[data-blog-calc-size]');
      const outEl = box.querySelector('[data-blog-calc-output]');
      const calcBtn = box.querySelector('[data-blog-calc-btn]');

      const compute = () => {
        const area = Math.max(1, Number(areaInput?.value || 25));
        const sizeStr = sizeSelect?.value || '80x80';
        const map = {
          '80x80': { areaPiece: 0.64, perBox: 3 },
          '60x120': { areaPiece: 0.72, perBox: 2 },
          '60x60': { areaPiece: 0.36, perBox: 4 },
          '30x60': { areaPiece: 0.18, perBox: 8 },
        };
        const cfg = map[sizeStr] || map['80x80'];
        const needM2 = area * 1.05;
        const pieces = Math.ceil(needM2 / cfg.areaPiece / cfg.perBox) * cfg.perBox;
        const boxes = pieces / cfg.perBox;
        if (outEl) {
          outEl.textContent = `Cần ${pieces} viên = ${boxes} thùng (đã cộng 5% hao hụt)`;
        }
      };

      if (calcBtn) calcBtn.addEventListener('click', compute);
      if (areaInput) areaInput.addEventListener('input', compute);
      if (sizeSelect) sizeSelect.addEventListener('change', compute);
      compute();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initInstantSearch();
    initBlogQuickCalc();
  });

  window.DravitCatalog = {
    DRV_MOCK_SILOS,
    DRV_MOCK_PRODUCTS,
    renderProductCardHtml,
  };
})();

