/**
 * DRAVIT — AI Tile Finder & Quote System (Vanilla ES2022)
 * Implements <drv-ai-finder>, <drv-quote-panel>, #quickSpecModal,
 * 3-Mode Layout Switcher (split | stream | widget), NLP Intent Engine,
 * DCOF Safety Guard (R10+ for wet areas), and ADR D-05 Box Calculator.
 */
(() => {
  'use strict';

  /** Forward swallowed failures to the central error sink (core.js) when it is available */
  const report = (scope, error, context) => {
    if (window.Dravit && typeof window.Dravit.reportError === 'function') window.Dravit.reportError(scope, error, context);
  };


  const STORAGE_KEY_QUOTE = 'drv_quote_items_v1';
  const STORAGE_KEY_CONTEXT = 'drv_ai_context_v1';

  /* ==========================================================================
     1. CATALOG DATASET (12 SKUs - ADR D-05 & Silo Spec Compliant)
     ========================================================================== */
  const DRAVIT_PRODUCTS = [
    {
      sku: 'DRV-8801M',
      title: 'Gạch Vân Đá Marble Calacatta Trắng Khói 80x80',
      collection: 'Bộ sưu tập Milan',
      size: '80x80',
      size_label: '80×80 cm',
      thickness_mm: 9.8,
      material: 'Porcelain xương đá',
      surface: 'Bóng kiếng (Polished)',
      surface_key: 'bong-kieng',
      dcof: 'R9',
      dcof_level: 9,
      water_absorption: '≤ 0,15%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.64,
      pieces_per_box: 3,
      m2_per_box: 1.92,
      price_m2: 385000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 380m²',
      spaces: ['phong-khach', 'phong-ngu', 'sanh-lon'],
      functions: ['lat-nen', 'op-tuong'],
      style: 'hien-dai',
      vein: 'van-da-marble',
      color_tone: 'trang-sang',
      pattern_type: 'marble_white',
      origin: 'Việt Nam - Dây chuyền Sacmi Ý',
      highlight: 'Vân mây xám thanh lịch trên nền trắng ngà, mở rộng không gian phòng khách.'
    },
    {
      sku: 'DRV-8805M',
      title: 'Gạch Porcelain Vân Đá Hoàng Kim Kem Sáng 80x80',
      collection: 'Bộ sưu tập Royal',
      size: '80x80',
      size_label: '80×80 cm',
      thickness_mm: 10.0,
      material: 'Porcelain đồng chất phủ men',
      surface: 'Siêu bóng Diamond',
      surface_key: 'bong-kieng',
      dcof: 'R9',
      dcof_level: 9,
      water_absorption: '≤ 0,12%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.64,
      pieces_per_box: 3,
      m2_per_box: 1.92,
      price_m2: 410000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 520m²',
      spaces: ['phong-khach', 'sanh-lon', 'phong-ngu'],
      functions: ['lat-nen'],
      style: 'tan-co-dien',
      vein: 'van-da-marble',
      color_tone: 'kem-am',
      pattern_type: 'marble_cream',
      origin: 'Nhập khẩu Ấn Độ',
      highlight: 'Tông kem ấm sang trọng, chống bám bẩn cấp độ 5, giữ độ bóng bền bỉ.'
    },
    {
      sku: 'DRV-61201S',
      title: 'Gạch Khổ Lớn Vân Đá Pietra Grey Mờ Mịn 60x120',
      collection: 'Bộ sưu tập Torino',
      size: '60x120',
      size_label: '60×120 cm',
      thickness_mm: 10.5,
      material: 'Porcelain khổ lớn',
      surface: 'Mờ mịn (Matt Silk)',
      surface_key: 'mo-matt',
      dcof: 'R10',
      dcof_level: 10,
      water_absorption: '≤ 0,10%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh siêu phẳng',
      m2_per_piece: 0.72,
      pieces_per_box: 2,
      m2_per_box: 1.44,
      price_m2: 465000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 290m²',
      spaces: ['phong-khach', 'nha-tam', 'phong-bep', 'sanh-lon'],
      functions: ['lat-nen', 'op-tuong'],
      style: 'toi-gian',
      vein: 'van-da-tu-nhien',
      color_tone: 'xam-trung-tinh',
      pattern_type: 'stone_grey',
      origin: 'Việt Nam - Xuất khẩu Châu Âu',
      highlight: 'Khổ 60x120 giảm thiểu đường ron, bề mặt Matt Silk R10 êm chân, không chói đèn.'
    },
    {
      sku: 'DRV-6602C',
      title: 'Gạch Vân Xi Măng Urban Loft Xám Nhạt 60x60',
      collection: 'Bộ sưu tập Bauhaus',
      size: '60x60',
      size_label: '60×60 cm',
      thickness_mm: 9.2,
      material: 'Porcelain xương đá',
      surface: 'Mờ nhám nhẹ (Matt)',
      surface_key: 'mo-matt',
      dcof: 'R10',
      dcof_level: 10,
      water_absorption: '≤ 0,25%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.36,
      pieces_per_box: 4,
      m2_per_box: 1.44,
      price_m2: 295000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 840m²',
      spaces: ['phong-khach', 'phong-bep', 'nha-tam', 'ban-cong'],
      functions: ['lat-nen', 'op-tuong'],
      style: 'toi-gian',
      vein: 'van-xi-mang',
      color_tone: 'xam-trung-tinh',
      pattern_type: 'cement_light',
      origin: 'Việt Nam',
      highlight: 'Chuẩn chống trượt R10 an toàn cho bếp và nhà tắm khô/ướt, che vết bẩn hiệu quả.'
    },
    {
      sku: 'DRV-6608T',
      title: 'Gạch Terrazzo Hạt Đá Đa Sắc Venice 60x60',
      collection: 'Bộ sưu tập Venezia',
      size: '60x60',
      size_label: '60×60 cm',
      thickness_mm: 9.5,
      material: 'Porcelain xương đá',
      surface: 'Mờ vi hạt chống trượt',
      surface_key: 'nham-chong-truot',
      dcof: 'R11',
      dcof_level: 11,
      water_absorption: '≤ 0,20%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.36,
      pieces_per_box: 4,
      m2_per_box: 1.44,
      price_m2: 345000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 410m²',
      spaces: ['nha-tam', 'phong-bep', 'ban-cong', 'quan-cafe'],
      functions: ['lat-nen', 'op-tuong'],
      style: 'indochine',
      vein: 'terrazzo',
      color_tone: 'kem-am',
      pattern_type: 'terrazzo_warm',
      origin: 'Nhập khẩu Malaysia',
      highlight: 'Đạt chuẩn R11 bám chân ngay cả khi sàn ướt xà phòng, lý tưởng cho gia đình có người già & trẻ nhỏ.'
    },
    {
      sku: 'DRV-3604B',
      title: 'Gạch Ốp Lát Nhà Tắm Vân Đá Limestone Sáng 30x60',
      collection: 'Bộ sưu tập AquaSafe',
      size: '30x60',
      size_label: '30×60 cm',
      thickness_mm: 8.8,
      material: 'Porcelain chống thấm',
      surface: 'Nhám Micro-Grip chống trơn',
      surface_key: 'nham-chong-truot',
      dcof: 'R11',
      dcof_level: 11,
      water_absorption: '≤ 0,18%',
      wear_pei: 'PEI 3',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.18,
      pieces_per_box: 8,
      m2_per_box: 1.44,
      price_m2: 275000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 650m²',
      spaces: ['nha-tam', 'ban-cong', 'san-vuon'],
      functions: ['lat-nen', 'op-tuong'],
      style: 'hien-dai',
      vein: 'van-da-tu-nhien',
      color_tone: 'trang-sang',
      pattern_type: 'limestone_light',
      origin: 'Việt Nam',
      highlight: 'Khổ 30x60 dễ tạo độ dốc thoát sàn phễu thu nước, bề mặt Micro-Grip thoát nước nhanh không đọng cặn.'
    },
    {
      sku: 'DRV-1590W',
      title: 'Gạch Thanh Giả Gỗ Sồi Bắc Âu Nordic Oak 15x90',
      collection: 'Bộ sưu tập Woodland',
      size: '15x90',
      size_label: '15×90 cm',
      thickness_mm: 9.5,
      material: 'Porcelain xương đá',
      surface: 'Khuôn định hình vân gỗ (Mold)',
      surface_key: 'mo-matt',
      dcof: 'R10',
      dcof_level: 10,
      water_absorption: '≤ 0,15%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.135,
      pieces_per_box: 8,
      m2_per_box: 1.08,
      price_m2: 365000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 480m²',
      spaces: ['phong-ngu', 'phong-khach', 'ban-cong'],
      functions: ['lat-nen'],
      style: 'bac-au',
      vein: 'van-go',
      color_tone: 'nau-go',
      pattern_type: 'wood_oak',
      origin: 'Việt Nam',
      highlight: 'Cảm giác ấm chân như gỗ tự nhiên nhưng không mối mọt, không cong vênh khi thời tiết nồm ẩm.'
    },
    {
      sku: 'DRV-2012W',
      title: 'Gạch Giả Gỗ Óc Chó Trầm Ấm Walnut 20x120',
      collection: 'Bộ sưu tập Woodland',
      size: '20x120',
      size_label: '20×120 cm',
      thickness_mm: 10.2,
      material: 'Porcelain cao cấp',
      surface: 'Mờ sần thớ gỗ tự nhiên',
      surface_key: 'mo-matt',
      dcof: 'R10',
      dcof_level: 10,
      water_absorption: '≤ 0,12%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh siêu khít',
      m2_per_piece: 0.24,
      pieces_per_box: 5,
      m2_per_box: 1.20,
      price_m2: 435000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 210m²',
      spaces: ['phong-ngu', 'phong-khach'],
      functions: ['lat-nen'],
      style: 'indochine',
      vein: 'van-go',
      color_tone: 'nau-go',
      pattern_type: 'wood_walnut',
      origin: 'Nhập khẩu Tây Ban Nha',
      highlight: 'Thanh dài 1,2m lát xương cá hoặc lát lệch 1/3 sang trọng cho phòng ngủ Master và biệt thự.'
    },
    {
      sku: 'DRV-6612G',
      title: 'Gạch Sân Vườn Đá Chẻ Bazan Xám Đậm 60x60 Dày 20mm',
      collection: 'Bộ sưu tập Titan Outdoor',
      size: '60x60',
      size_label: '60×60 cm (Dày 20mm)',
      thickness_mm: 20.0,
      material: 'Porcelain 20mm chịu tải nặng',
      surface: 'Nhám sần định hình (Structured)',
      surface_key: 'nham-chong-truot',
      dcof: 'R12',
      dcof_level: 12,
      water_absorption: '≤ 0,08%',
      wear_pei: 'PEI 5',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.36,
      pieces_per_box: 2,
      m2_per_box: 0.72,
      price_m2: 520000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 310m²',
      spaces: ['san-vuon', 'gara-o-to', 'ban-cong'],
      functions: ['lat-nen'],
      style: 'hien-dai',
      vein: 'van-da-tu-nhien',
      color_tone: 'xam-dam',
      pattern_type: 'basalt_dark',
      origin: 'Việt Nam',
      highlight: 'Độ dày 20mm chịu tải xe ô tô 2,5 tấn đi qua không nứt vỡ, chống rêu mốc và chống trượt R12.'
    },
    {
      sku: 'DRV-61209M',
      title: 'Gạch Khổ Lớn Vân Đá Travertine Kem Ngà 60x120',
      collection: 'Bộ sưu tập Roma',
      size: '60x120',
      size_label: '60×120 cm',
      thickness_mm: 10.5,
      material: 'Porcelain khổ lớn',
      surface: 'Bán bóng (Lappato)',
      surface_key: 'ban-bong',
      dcof: 'R10',
      dcof_level: 10,
      water_absorption: '≤ 0,10%',
      wear_pei: 'PEI 4',
      rectified: 'Mài cạnh siêu phẳng',
      m2_per_piece: 0.72,
      pieces_per_box: 2,
      m2_per_box: 1.44,
      price_m2: 490000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 340m²',
      spaces: ['phong-khach', 'sanh-lon', 'mat-tien'],
      functions: ['lat-nen', 'op-tuong'],
      style: 'tan-co-dien',
      vein: 'van-da-marble',
      color_tone: 'kem-am',
      pattern_type: 'travertine_cream',
      origin: 'Nhập khẩu Ấn Độ',
      highlight: 'Hiệu ứng Lappato ánh kim nhẹ dưới đèn chùm, vừa sang trọng vừa đạt chuẩn bám chân R10.'
    },
    {
      sku: 'DRV-3609W',
      title: 'Gạch Ốp Tường Vân Đá Trắng Khói Phối Điểm 30x60',
      collection: 'Bộ sưu tập PureWall',
      size: '30x60',
      size_label: '30×60 cm',
      thickness_mm: 8.5,
      material: 'Semi-Porcelain nhẹ tường',
      surface: 'Men Matt kháng khuẩn dễ lau',
      surface_key: 'mo-matt',
      dcof: 'R9',
      dcof_level: 9,
      water_absorption: '≤ 0,50%',
      wear_pei: 'PEI 3',
      rectified: 'Mài cạnh chuẩn (Rectified)',
      m2_per_piece: 0.18,
      pieces_per_box: 8,
      m2_per_box: 1.44,
      price_m2: 255000,
      stock_status: 'in_stock',
      stock_label: 'Sẵn kho 790m²',
      spaces: ['nha-tam', 'phong-bep'],
      functions: ['op-tuong'],
      style: 'hien-dai',
      vein: 'van-da-marble',
      color_tone: 'trang-sang',
      pattern_type: 'marble_white',
      origin: 'Việt Nam',
      highlight: 'Trọng lượng nhẹ bám dính tường tối ưu, men kháng khuẩn chống bám dầu mỡ nhà bếp.'
    },
    {
      sku: 'DRV-1224S',
      title: 'Đá Nung Kết Slab Vân Đá Patagonia Luxury 120x240',
      collection: 'Bộ sưu tập Signature Slab',
      size: '120x240',
      size_label: '120×240 cm',
      thickness_mm: 12.0,
      material: 'Đá nung kết (Sintered Stone)',
      surface: 'Bóng gương pha lê',
      surface_key: 'bong-kieng',
      dcof: 'R9',
      dcof_level: 9,
      water_absorption: '≤ 0,05%',
      wear_pei: 'PEI 5',
      rectified: 'Mài cạnh CNC nguyên tấm',
      m2_per_piece: 2.88,
      pieces_per_box: 1,
      m2_per_box: 2.88,
      price_m2: 980000,
      stock_status: 'preorder',
      stock_label: 'Đặt trước 5-7 ngày',
      spaces: ['sanh-lon', 'phong-khach', 'mat-tien'],
      functions: ['op-tuong', 'lat-nen'],
      style: 'tan-co-dien',
      vein: 'van-da-marble',
      color_tone: 'kem-am',
      pattern_type: 'slab_patagonia',
      origin: 'Nhập khẩu Ý',
      highlight: 'Tấm đá nung kết khổ lớn 2,88m²/tấm tạo vách tivi và sảnh thông tầng liền mạch đẳng cấp.'
    }
  ];

  /* ==========================================================================
     2. PROCEDURAL SVG TILE TEXTURE GENERATOR
     ========================================================================== */
  function renderTilePatternSVG(patternType, sku) {
    const id = String(sku || 'tile').replace(/[^a-zA-Z0-9]/g, '');
    switch (patternType) {
      case 'marble_white':
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="bg_${id}" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#FCFCFA"/>
              <stop offset="60%" stop-color="#F1F5F9"/>
              <stop offset="100%" stop-color="#E2E8F0"/>
            </linearGradient>
          </defs>
          <rect width="320" height="200" fill="url(#bg_${id})"/>
          <path d="M-20 40 Q 80 65, 150 25 T 340 85" fill="none" stroke="#CBD5E1" stroke-width="2.5" opacity="0.75"/>
          <path d="M10 150 Q 120 110, 210 140 T 330 115" fill="none" stroke="#94A3B8" stroke-width="1.4" opacity="0.55"/>
          <path d="M60 0 Q 130 95, 260 195" fill="none" stroke="#E2E8F0" stroke-width="3.5" opacity="0.8"/>
          <line x1="160" y1="0" x2="160" y2="200" stroke="#083A4F" stroke-width="0.6" opacity="0.1"/>
        </svg>`;
      case 'marble_cream':
      case 'travertine_cream':
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="bg_${id}" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#FAF6EE"/>
              <stop offset="100%" stop-color="#EDE4D3"/>
            </linearGradient>
          </defs>
          <rect width="320" height="200" fill="url(#bg_${id})"/>
          <path d="M-10 60 C 90 40, 170 95, 330 50" fill="none" stroke="#D6C7B2" stroke-width="3" opacity="0.75"/>
          <path d="M-10 130 C 110 145, 200 105, 330 140" fill="none" stroke="#C4B299" stroke-width="1.8" opacity="0.6"/>
          <path d="M40 20 Q 180 75, 290 15" fill="none" stroke="#E5D9C5" stroke-width="4" opacity="0.65"/>
        </svg>`;
      case 'stone_grey':
      case 'limestone_light':
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="bg_${id}" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#94A3B8"/>
              <stop offset="100%" stop-color="#64748B"/>
            </linearGradient>
          </defs>
          <rect width="320" height="200" fill="url(#bg_${id})"/>
          <path d="M20 30 L 95 75 L 180 55 L 310 110" fill="none" stroke="#E2E8F0" stroke-width="1.4" opacity="0.65"/>
          <path d="M-10 140 L 110 120 L 210 165 L 330 135" fill="none" stroke="#F8FAFC" stroke-width="1" opacity="0.45"/>
        </svg>`;
      case 'cement_light':
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="320" height="200" fill="#CBD5E1"/>
          <circle cx="70" cy="60" r="55" fill="#94A3B8" opacity="0.22"/>
          <circle cx="230" cy="130" r="75" fill="#E2E8F0" opacity="0.35"/>
          <line x1="160" y1="0" x2="160" y2="200" stroke="#64748B" stroke-width="0.7" opacity="0.25"/>
          <line x1="0" y1="100" x2="320" y2="100" stroke="#64748B" stroke-width="0.7" opacity="0.25"/>
        </svg>`;
      case 'terrazzo_warm':
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="320" height="200" fill="#F3EFE6"/>
          <polygon points="35,30 52,24 48,44" fill="#0D9488" opacity="0.65"/>
          <polygon points="115,60 135,52 128,74 108,70" fill="#D97706" opacity="0.65"/>
          <polygon points="220,40 242,35 235,55" fill="#475569" opacity="0.6"/>
          <polygon points="75,135 95,125 90,148" fill="#64748B" opacity="0.55"/>
          <polygon points="175,120 198,115 190,138" fill="#083A4F" opacity="0.65"/>
          <polygon points="265,145 285,132 280,155" fill="#B45309" opacity="0.6"/>
          <circle cx="150" cy="35" r="5" fill="#94A3B8" opacity="0.6"/>
          <circle cx="50" cy="95" r="6" fill="#D97706" opacity="0.5"/>
          <circle cx="260" cy="90" r="5" fill="#0D9488" opacity="0.5"/>
        </svg>`;
      case 'wood_oak':
      case 'wood_walnut': {
        const isDark = patternType === 'wood_walnut';
        const base = isDark ? '#784B2A' : '#C89D66';
        const grain = isDark ? '#5A351B' : '#A67B44';
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="320" height="200" fill="${base}"/>
          <line x1="0" y1="50" x2="320" y2="50" stroke="#3E2411" stroke-width="1" opacity="0.35"/>
          <line x1="0" y1="100" x2="320" y2="100" stroke="#3E2411" stroke-width="1" opacity="0.35"/>
          <line x1="0" y1="150" x2="320" y2="150" stroke="#3E2411" stroke-width="1" opacity="0.35"/>
          <path d="M0 25 Q 140 12, 320 28" fill="none" stroke="${grain}" stroke-width="2" opacity="0.65"/>
          <path d="M0 75 Q 180 90, 320 72" fill="none" stroke="${grain}" stroke-width="2" opacity="0.65"/>
          <path d="M0 125 Q 110 112, 320 130" fill="none" stroke="${grain}" stroke-width="2" opacity="0.65"/>
          <path d="M0 175 Q 200 188, 320 170" fill="none" stroke="${grain}" stroke-width="2" opacity="0.65"/>
        </svg>`;
      }
      case 'basalt_dark':
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="320" height="200" fill="#334155"/>
          <path d="M0 45 L 320 38 M0 115 L 320 122 M0 165 L 320 160" stroke="#475569" stroke-width="2" opacity="0.6"/>
          <circle cx="90" cy="80" r="35" fill="#1E293B" opacity="0.35"/>
          <circle cx="240" cy="140" r="45" fill="#475569" opacity="0.3"/>
        </svg>`;
      default:
        return `<svg viewBox="0 0 320 200" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="320" height="200" fill="#F5EFE6"/>
          <polygon points="40,0 160,0 90,200 0,200" fill="#E6DAC6" opacity="0.7"/>
          <path d="M20 0 L 180 200 M 190 0 L 290 200" stroke="#D97706" stroke-width="2" opacity="0.55"/>
        </svg>`;
    }
  }

  /* ==========================================================================
     3. UNIT CONVERSION & VOLUME CALCULATOR (ADR D-05)
     ========================================================================== */
  function calculateTileUnits(product, areaM2, includeWaste = true) {
    const cleanArea = Math.max(1, Number(areaM2) || 25);
    const effectiveArea = includeWaste ? cleanArea * 1.05 : cleanArea;
    const rawPieces = Math.ceil(effectiveArea / product.m2_per_piece);
    const boxes = Math.ceil(rawPieces / product.pieces_per_box);
    const actualPieces = boxes * product.pieces_per_box;
    const actualAreaCovered = Number((actualPieces * product.m2_per_piece).toFixed(2));
    return {
      inputArea: cleanArea,
      effectiveArea: Number(effectiveArea.toFixed(2)),
      boxes,
      actualPieces,
      actualAreaCovered
    };
  }

  function formatDecVN(num) {
    return String(num).replace('.', ',');
  }

  function escapeHTML(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /* ==========================================================================
     4. NLP INTENT EXTRACTION & SAFETY SCORING ENGINE
     ========================================================================== */
  const FACET_LABELS = {
    space: {
      'phong-khach': 'Phòng khách',
      'nha-tam': 'Nhà tắm / WC',
      'phong-ngu': 'Phòng ngủ',
      'phong-bep': 'Phòng bếp',
      'san-vuon': 'Sân vườn / Ngoài trời',
      'ban-cong': 'Ban công / Lô gia',
      'gara-o-to': 'Gara ô tô',
      'sanh-lon': 'Sảnh lớn / Thông tầng',
      'mat-tien': 'Mặt tiền'
    },
    function: {
      'lat-nen': 'Gạch lát nền',
      'op-tuong': 'Gạch ốp tường'
    },
    surface: {
      'bong-kieng': 'Bóng kiếng',
      'mo-matt': 'Mờ mịn (Matt)',
      'nham-chong-truot': 'Nhám chống trượt',
      'ban-bong': 'Bán bóng (Lappato)'
    },
    vein: {
      'van-da-marble': 'Vân đá Marble',
      'van-da-tu-nhien': 'Vân đá tự nhiên',
      'van-xi-mang': 'Vân xi măng',
      'van-go': 'Gạch giả gỗ',
      'terrazzo': 'Hạt đá Terrazzo'
    },
    style: {
      'hien-dai': 'Hiện đại',
      'toi-gian': 'Tối giản (Minimalist)',
      'indochine': 'Đông Dương (Indochine)',
      'bac-au': 'Bắc Âu (Scandinavian)',
      'tan-co-dien': 'Tân cổ điển / Luxury'
    },
    color: {
      'trang-sang': 'Tông trắng sáng',
      'kem-am': 'Tông kem ấm',
      'xam-trung-tinh': 'Tông xám trung tính',
      'nau-go': 'Tông nâu gỗ ấm',
      'xam-dam': 'Tông xám đậm'
    }
  };

  function extractLocalIntent(inputText, existingContext = {}) {
    const text = String(inputText || '').toLowerCase();
    const next = { ...existingContext };

    // 1. Area m²
    const areaMatch = text.match(/(\d+(?:[.,]\d+)?)\s*(?:m2|m²|mét vuông|met vuong)/i);
    if (areaMatch) {
      const parsed = parseFloat(areaMatch[1].replace(',', '.'));
      if (parsed >= 1 && parsed <= 10000) {
        next.area_m2 = parsed;
      }
    }

    // 2. Space
    if (/nhà tắm|wc|toilet|vệ sinh|phòng tắm/.test(text)) next.space = 'nha-tam';
    else if (/phòng khách/.test(text)) next.space = 'phong-khach';
    else if (/phòng ngủ/.test(text)) next.space = 'phong-ngu';
    else if (/bếp|phòng ăn/.test(text)) next.space = 'phong-bep';
    else if (/sân vườn|ngoài trời|sân thượng/.test(text)) next.space = 'san-vuon';
    else if (/gara|ô tô|đỗ xe/.test(text)) next.space = 'gara-o-to';
    else if (/ban công|lô gia/.test(text)) next.space = 'ban-cong';
    else if (/sảnh|thông tầng|biệt thự/.test(text)) next.space = 'sanh-lon';
    else if (/mặt tiền/.test(text)) next.space = 'mat-tien';

    // 3. Function
    if (/ốp tường|vách/.test(text)) next.function = 'op-tuong';
    else if (/lát nền|lát sàn|nền|sàn/.test(text)) next.function = 'lat-nen';

    // 4. Surface & DCOF Safety Rules
    if (/chống trượt|chống trơn|người già|trẻ nhỏ|người lớn tuổi|nhám|r11|r12/.test(text)) {
      next.min_dcof = 10;
      if (/r11|r12|sân vườn|người già|trẻ nhỏ|nhà tắm/.test(text)) {
        next.min_dcof = 11;
      }
      if (/nhám/.test(text)) next.surface = 'nham-chong-truot';
    } else if (/bóng kiếng|siêu bóng|bóng gương/.test(text)) {
      next.surface = 'bong-kieng';
    } else if (/mờ|matt|không chói/.test(text)) {
      next.surface = 'mo-matt';
    }

    // Automatic Safety Guard: Bathroom or Outdoor Floor MUST have DCOF >= R10
    if ((next.space === 'nha-tam' || next.space === 'san-vuon' || next.space === 'ban-cong') && next.function !== 'op-tuong') {
      if (!next.min_dcof || next.min_dcof < 10) {
        next.min_dcof = 10;
      }
      if (next.surface === 'bong-kieng') {
        delete next.surface;
        next.safety_override_note = 'Dravit AI đã tự động loại bỏ gạch Bóng kiếng (R9) cho sàn khu vực ẩm ướt để đảm bảo an toàn chống trượt (ưu tiên R10-R12).';
      }
    }

    // 5. Vein / Pattern
    if (/giả gỗ|vân gỗ|gỗ/.test(text)) next.vein = 'van-go';
    else if (/terrazzo|hạt đá/.test(text)) next.vein = 'terrazzo';
    else if (/xi măng|bê tông|loft/.test(text)) next.vein = 'van-xi-mang';
    else if (/marble|vân đá|vân mây|calacatta/.test(text)) next.vein = 'van-da-marble';

    // 6. Color Tone
    if (/trắng|sáng/.test(text)) next.color = 'trang-sang';
    else if (/kem|ấm|vàng nhạt|be/.test(text)) next.color = 'kem-am';
    else if (/xám đậm|tối|đen/.test(text)) next.color = 'xam-dam';
    else if (/xám|ghi|trung tính/.test(text)) next.color = 'xam-trung-tinh';
    else if (/nâu/.test(text)) next.color = 'nau-go';

    // 7. Size
    const sizeMatch = text.match(/\b(80x80|60x120|60x60|30x60|15x90|20x120|120x240)\b/i);
    if (sizeMatch) {
      next.size = sizeMatch[1].toLowerCase();
    } else if (/khổ lớn/.test(text)) {
      next.size = '60x120';
    }

    // 8. Stock priority
    if (/sẵn kho|giao ngay|cần gấp/.test(text)) {
      next.stock_priority = true;
    }

    return next;
  }

  function scoreAndRankCatalog(params) {
    const scored = DRAVIT_PRODUCTS.map((product) => {
      let score = 50;
      const matchedReasons = [];

      // Hard Safety Constraint: Never recommend R9 for wet floors
      const isWetFloor = (params.space === 'nha-tam' || params.space === 'san-vuon' || params.space === 'gara-o-to') && params.function !== 'op-tuong';
      if (isWetFloor && product.dcof_level < 10) {
        return { product, score: -999, matchedReasons: [] };
      }

      if (params.space) {
        if (product.spaces.includes(params.space)) {
          score += 25;
          matchedReasons.push(`Chuẩn ${FACET_LABELS.space[params.space]}`);
        } else {
          score -= 15;
        }
      }

      if (params.function) {
        if (product.functions.includes(params.function)) {
          score += 15;
        } else {
          score -= 25;
        }
      }

      if (params.min_dcof) {
        if (product.dcof_level >= params.min_dcof) {
          score += 22;
          matchedReasons.push(`Chống trượt ${product.dcof}`);
        } else {
          score -= 30;
        }
      }

      if (params.surface) {
        if (product.surface_key === params.surface) {
          score += 18;
          matchedReasons.push(FACET_LABELS.surface[params.surface]);
        } else {
          score -= 10;
        }
      }

      if (params.vein) {
        if (product.vein === params.vein) {
          score += 20;
          matchedReasons.push(FACET_LABELS.vein[params.vein]);
        } else {
          score -= 8;
        }
      }

      if (params.color) {
        if (product.color_tone === params.color) {
          score += 12;
        }
      }

      if (params.size) {
        if (product.size === params.size) {
          score += 25;
          matchedReasons.push(`Khổ ${product.size_label}`);
        } else {
          score -= 12;
        }
      }

      if (params.stock_priority) {
        if (product.stock_status === 'in_stock') {
          score += 15;
        } else {
          score -= 25;
        }
      }

      return { product, score, matchedReasons };
    });

    const valid = scored
      .filter((item) => item.score > 20)
      .sort((a, b) => b.score - a.score);

    if (valid.length === 0) {
      return scored
        .filter((item) => item.score > -100)
        .sort((a, b) => b.score - a.score)
        .slice(0, 4);
    }

    return valid.slice(0, 6);
  }

  function buildAssistantExplanation(params, rankedList, includeWaste) {
    const area = params.area_m2 || 25;
    const wasteArea = includeWaste ? Number((area * 1.05).toFixed(2)) : area;
    const parts = [];

    if (params.space) {
      parts.push(`khu vực **${FACET_LABELS.space[params.space]}**`);
    }
    if (params.function) {
      parts.push(`hạng mục **${FACET_LABELS.function[params.function].toLowerCase()}**`);
    }
    if (params.vein) {
      parts.push(`họa tiết **${FACET_LABELS.vein[params.vein]}**`);
    }
    if (params.size) {
      parts.push(`kích thước **${params.size}**`);
    }
    if (params.min_dcof) {
      parts.push(`tiêu chuẩn chống trượt **≥ R${params.min_dcof}**`);
    }

    const criteriaSummary = parts.length > 0
      ? `Dựa trên yêu cầu cho ${parts.join(', ')}`
      : 'Dựa trên tiêu chuẩn kỹ thuật Porcelain xương đá Dravit';

    const topProduct = rankedList[0]?.product;
    const sampleCalc = topProduct ? calculateTileUnits(topProduct, area, includeWaste) : null;

    let explanation = `${criteriaSummary}, Dravit AI chọn lọc **${rankedList.length} mẫu gạch** tương thích tốt nhất. `;
    if (sampleCalc && topProduct) {
      explanation += `Với diện tích **${area} m²** (${includeWaste ? `cộng 5% hao hụt cắt gạch = **${formatDecVN(wasteArea)} m²**` : 'chưa cộng hao hụt'}), mẫu đầu bảng **${topProduct.sku}** (${topProduct.size_label}, ${formatDecVN(topProduct.m2_per_box)} m²/thùng) sẽ cần đúng **${sampleCalc.boxes} thùng nguyên (${sampleCalc.actualPieces} viên)**. Bạn có thể bấm **"Thêm nhận báo giá"** để nhận bảng chiết khấu theo số thùng.`;
    }

    let clarification = '';
    if (!params.area_m2) {
      clarification = 'Bạn có thể cho biết diện tích sàn/tường khoảng bao nhiêu m² để AI tính chính xác số thùng nguyên cần đặt không?';
    } else if (!params.space) {
      clarification = 'Bạn dự định lát cho không gian nào (phòng khách, nhà tắm hay sân vườn) để AI kiểm tra hệ số chống trượt R phù hợp?';
    } else if (!params.surface && !params.min_dcof) {
      clarification = 'Bạn thích bề mặt Bóng kiếng sang trọng hay Mờ mịn (Matt R10) chống chói đèn?';
    }

    return {
      explanation,
      clarification,
      tradeoffNote: params.safety_override_note || ''
    };
  }

  /* ==========================================================================
     5. SHARED QUOTE STATE MANAGER (Syncs across all pages & components)
     ========================================================================== */
  /** UTF-8 safe Base64 helpers (replace deprecated escape()/unescape()) */
  function b64EncodeUtf8(str) {
    const bytes = new TextEncoder().encode(str);
    let bin = '';
    bytes.forEach((b) => { bin += String.fromCharCode(b); });
    return btoa(bin);
  }

  function b64DecodeUtf8(b64) {
    const bin = atob(b64);
    const bytes = Uint8Array.from(bin, (c) => c.charCodeAt(0));
    return new TextDecoder().decode(bytes);
  }

  const MAX_QUOTE_ITEMS = 50;
  const SKU_PATTERN = /^[A-Za-z0-9._-]{1,40}$/;

  /**
   * Whitelist-validate untrusted quote items (from ?quote= URL or localStorage).
   * Only SKU + box count are trusted; everything else is recomputed from the catalog.
   * @param {unknown} raw
   * @returns {Array<{sku:string,boxes:number,pieces:number,areaCovered:number,targetArea:number}>}
   */
  function sanitizeQuoteItems(raw) {
    if (!Array.isArray(raw)) return [];
    const seen = new Set();
    const clean = [];
    for (const entry of raw) {
      if (clean.length >= MAX_QUOTE_ITEMS) break;
      if (!entry || typeof entry !== 'object') continue;
      const sku = typeof entry.sku === 'string' ? entry.sku.trim() : '';
      if (!SKU_PATTERN.test(sku) || seen.has(sku)) continue;
      const boxes = Math.max(1, Math.min(9999, Math.floor(Number(entry.boxes)) || 1));
      const product = QuoteManager.findProduct(sku);
      const pieces = boxes * product.pieces_per_box;
      const targetArea = Number(entry.targetArea);
      seen.add(sku);
      clean.push({
        sku,
        boxes,
        pieces,
        areaCovered: Number((pieces * product.m2_per_piece).toFixed(2)),
        targetArea: Number.isFinite(targetArea) ? Math.max(0, Math.min(100000, targetArea)) : 0
      });
    }
    return clean;
  }

  const QuoteManager = {
    items: [],

    load() {
      // Check URL ?quote= hash first
      try {
        const urlParams = new URLSearchParams(window.location.search);
        const quoteHash = urlParams.get('quote');
        if (quoteHash) {
          const decoded = sanitizeQuoteItems(JSON.parse(b64DecodeUtf8(quoteHash)));
          if (decoded.length > 0) {
            this.items = decoded;
            this.save();
            return this.items;
          }
        }
      } catch (e) {
        report('quote-url', e);
      }

      try {
        const raw = localStorage.getItem(STORAGE_KEY_QUOTE);
        if (raw) {
          const parsed = JSON.parse(raw);
          this.items = sanitizeQuoteItems(parsed);
        }
      } catch (e) {
        report('quote-storage', e, { op: 'read' });
        this.items = [];
      }
      return this.items;
    },

    save() {
      try {
        localStorage.setItem(STORAGE_KEY_QUOTE, JSON.stringify(this.items));
      } catch (e) {
        report('quote-storage', e, { op: 'write' });
      }
      window.dispatchEvent(new CustomEvent('drv:quote-updated', { detail: { items: this.items } }));
    },

    findProduct(sku) {
      const found = DRAVIT_PRODUCTS.find((p) => p.sku === sku);
      if (found) return found;
      // Fallback for any SKU on page cards not in the 12 core list
      return {
        sku: sku || 'DRV-8801M',
        title: `Mẫu gạch kiến trúc Dravit (${sku})`,
        collection: 'Bộ sưu tập Dravit',
        size: '80x80',
        size_label: '80×80 cm',
        thickness_mm: 10.0,
        material: 'Porcelain xương đá',
        surface: 'Mờ mịn (Matt)',
        surface_key: 'mo-matt',
        dcof: 'R10',
        dcof_level: 10,
        water_absorption: '≤ 0,5%',
        wear_pei: 'PEI 4',
        rectified: 'Mài cạnh chuẩn (Rectified)',
        m2_per_piece: 0.64,
        pieces_per_box: 3,
        m2_per_box: 1.92,
        price_m2: 385000,
        stock_status: 'in_stock',
        stock_label: 'Sẵn kho',
        pattern_type: 'marble_white',
        origin: 'Việt Nam'
      };
    },

    addOrUpdate(sku, areaM2 = 25, includeWaste = true, explicitBoxes = null) {
      const product = this.findProduct(sku);
      const calc = calculateTileUnits(product, areaM2, includeWaste);
      const boxes = explicitBoxes && explicitBoxes > 0 ? explicitBoxes : calc.boxes;
      const pieces = boxes * product.pieces_per_box;
      const areaCovered = Number((pieces * product.m2_per_piece).toFixed(2));

      const existingIdx = this.items.findIndex((i) => i.sku === product.sku);
      if (existingIdx > -1) {
        this.items[existingIdx].boxes = boxes;
        this.items[existingIdx].pieces = pieces;
        this.items[existingIdx].areaCovered = areaCovered;
        this.items[existingIdx].targetArea = calc.inputArea;
      } else {
        this.items.push({
          sku: product.sku,
          boxes,
          pieces,
          areaCovered,
          targetArea: calc.inputArea
        });
      }
      this.save();
      return { product, boxes, pieces, areaCovered };
    },

    updateBoxes(sku, newBoxes) {
      const idx = this.items.findIndex((i) => i.sku === sku);
      if (idx === -1) return;
      const product = this.findProduct(sku);
      const validBoxes = Math.max(1, Math.min(9999, parseInt(newBoxes, 10) || 1));
      this.items[idx].boxes = validBoxes;
      this.items[idx].pieces = validBoxes * product.pieces_per_box;
      this.items[idx].areaCovered = Number((this.items[idx].pieces * product.m2_per_piece).toFixed(2));
      this.save();
    },

    remove(sku) {
      this.items = this.items.filter((i) => i.sku !== sku);
      this.save();
    },

    clear() {
      this.items = [];
      this.save();
    },

    getTotals() {
      let totalSku = this.items.length;
      let totalBoxes = 0;
      let totalArea = 0;
      this.items.forEach((item) => {
        totalBoxes += item.boxes;
        totalArea += item.areaCovered;
      });
      return {
        totalSku,
        totalBoxes,
        totalArea: Number(totalArea.toFixed(2))
      };
    }
  };

  /* ==========================================================================
     6. GLOBAL SHELL INJECTOR (Ensures Quote Panel, Spec Modal & Widget exist)
     ========================================================================== */
  function ensureGlobalQuoteAndModalDOM() {
    // 1. Ensure Offcanvas Quote Panel exists
    if (!document.getElementById('drvQuotePanel')) {
      const backdrop = document.createElement('div');
      backdrop.id = 'quoteBackdrop';
      backdrop.className = 'drv-qp-backdrop';
      document.body.appendChild(backdrop);

      const panel = document.createElement('drv-quote-panel');
      panel.id = 'drvQuotePanel';
      panel.className = 'drv-qp-drawer';
      panel.setAttribute('aria-label', 'Danh sách yêu cầu báo giá công trình');
      panel.innerHTML = `
        <div class="drv-qp-head">
          <div>
            <div class="drv-qp-head__eyebrow">
              <i class="ph-fill ph-seal-check" aria-hidden="true"></i>
              <span>BÁO GIÁ NHANH 24H</span>
            </div>
            <h2 class="drv-qp-head__title">Danh sách yêu cầu báo giá</h2>
          </div>
          <div class="l-cluster l-cluster--xs">
            <button id="shareQuoteUrlBtn" type="button" class="drv-aif-head-pill" title="Sao chép link danh sách báo giá gửi cho KTS/Gia đình">
              <i class="ph-bold ph-share-network" aria-hidden="true"></i>
              <span id="shareQuoteBtnLabel">Chia sẻ</span>
            </button>
            <button id="closeQuotePanelBtn" type="button" class="drv-aif-icon-btn" aria-label="Đóng bảng báo giá">
              <i class="ph-bold ph-x" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="drv-qp-body">
          <div id="emptyQuoteState" class="drv-qp-empty">
            <div style="width: 48px; height: 48px; margin: 0 auto 10px; border-radius: 50%; background: var(--c-white); border: var(--bw); display: flex; align-items: center; justify-content: center; color: var(--c-text-muted); font-size: 22px;">
              <i class="ph-bold ph-stack" aria-hidden="true"></i>
            </div>
            <h3 class="u-text-sm u-bold u-text-navy">Chưa có mẫu gạch nào trong danh sách</h3>
            <p class="u-text-xs u-text-muted" style="margin-top: 4px; max-width: 32ch; margin-inline: auto;">
              Hãy nhờ <strong>Trợ lý AI</strong> tính số thùng theo m² hoặc bấm <strong>"Thêm nhận báo giá"</strong> trên các mẫu gạch bạn ưng ý.
            </p>
            <button id="emptyQuoteAskAiBtn" type="button" class="drv-btn drv-btn--primary drv-btn--sm" style="margin-top: 12px;">
              <i class="ph-fill ph-sparkle drv-icon--xs" aria-hidden="true"></i>
              <span>Nhờ AI gợi ý mẫu gạch ngay</span>
            </button>
          </div>

          <div id="quoteItemsContainer" class="drv-qp-items"></div>

          <div id="quoteSummarySection" class="drv-qp-summary" hidden>
            <div class="drv-qp-summary__row">
              <span>Tổng số mã gạch đã chọn:</span>
              <strong id="summarySkuCount" class="u-text-navy u-tabular">0 mẫu</strong>
            </div>
            <div class="drv-qp-summary__row">
              <span>Tổng diện tích phủ thực tế (đã làm tròn thùng):</span>
              <strong id="summaryTotalArea" class="u-text-navy u-tabular">0 m²</strong>
            </div>
            <div class="drv-qp-summary__total">
              <div>
                <strong class="u-text-xs u-text-navy" style="text-transform: uppercase; display: block;">Tổng khối lượng vật tư:</strong>
                <span class="u-text-xs u-text-muted">Báo giá sỉ/lẻ tốt nhất theo số thùng</span>
              </div>
              <div style="text-align: right;">
                <strong id="summaryTotalBoxes" class="u-text-lg u-text-moss u-tabular">0 thùng</strong>
                <span class="u-text-xs u-bold" style="display: block; color: #b45309;">Cam kết cùng 1 lô (Shade)</span>
              </div>
            </div>
          </div>

          <form id="quoteContactForm" class="l-stack l-stack--sm" hidden novalidate>
            <div style="padding-top: 8px; border-top: var(--bw);">
              <h3 class="u-text-xs u-bold u-text-navy" style="text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">
                Thông tin nhận bảng báo giá &amp; mẫu thực tế
              </h3>
              <p class="u-text-xs u-text-muted">
                Chuyên viên kỹ thuật Dravit sẽ kiểm tra tồn kho cùng lô màu (Batch/Caliber), tính cước vận chuyển và gửi báo giá qua Zalo/SĐT trong 30 phút.
              </p>
            </div>

            <div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
              <label for="drvWebsiteHoneypot">Website</label>
              <input id="drvWebsiteHoneypot" type="text" name="drv_website" tabindex="-1" autocomplete="off">
            </div>

            <div>
              <label class="u-text-xs u-bold u-text-navy" style="display: block; margin-bottom: 6px;">Bạn đang cần báo giá cho:</label>
              <div class="drv-qp-personas">
                <label class="drv-qp-persona">
                  <input type="radio" name="customer_persona" value="chu-nha" checked>
                  <span class="drv-qp-persona__box">Chủ nhà</span>
                </label>
                <label class="drv-qp-persona">
                  <input type="radio" name="customer_persona" value="nha-thau">
                  <span class="drv-qp-persona__box">Nhà thầu XD</span>
                </label>
                <label class="drv-qp-persona">
                  <input type="radio" name="customer_persona" value="kts">
                  <span class="drv-qp-persona__box">Kiến trúc sư</span>
                </label>
              </div>
              <p id="personaBenefitHint" class="u-text-xs u-text-moss" style="margin-top: 6px; background: #f0fdfa; border: 1px solid #99f6e4; padding: 6px 10px; border-radius: 6px;">
                <i class="ph-fill ph-check-circle" aria-hidden="true"></i>
                Hỗ trợ tính dư hao hụt chuẩn xác, miễn phí gửi mẫu tận nhà &amp; đổi trả thùng nguyên dư.
              </p>
            </div>

            <div class="l-grid l-grid--2" style="gap: 10px;">
              <div>
                <label for="custName" class="u-text-xs u-bold u-text-navy" style="display: block; margin-bottom: 4px;">Họ và tên <span class="u-text-sale">*</span></label>
                <input id="custName" type="text" required placeholder="Nguyễn Văn A" class="drv-input drv-input--sm">
              </div>
              <div>
                <label for="custPhone" class="u-text-xs u-bold u-text-navy" style="display: block; margin-bottom: 4px;">Số điện thoại / Zalo <span class="u-text-sale">*</span></label>
                <input id="custPhone" type="tel" required placeholder="090xxxxxxx" class="drv-input drv-input--sm u-tabular">
              </div>
            </div>

            <div>
              <label for="custEmail" class="u-text-xs u-bold u-text-navy" style="display: block; margin-bottom: 4px;">Email (Nhận file PDF báo giá &amp; Spec kỹ thuật)</label>
              <input id="custEmail" type="email" placeholder="kien-truc@congtrinh.vn (Không bắt buộc)" class="drv-input drv-input--sm">
            </div>

            <div>
              <label for="custNote" class="u-text-xs u-bold u-text-navy" style="display: block; margin-bottom: 4px;">Địa chỉ giao hàng dự kiến / Ghi chú công trình</label>
              <textarea id="custNote" rows="2" placeholder="VD: Giao tại Quận 7 TP.HCM, cần gửi kèm mẫu thực tế..." class="drv-textarea" style="font-size: 12px; padding: 8px 10px;"></textarea>
            </div>

            <label class="l-cluster l-cluster--xs u-text-xs" style="padding: 8px 10px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; cursor: pointer;">
              <input id="requestSampleCheck" type="checkbox" checked>
              <span style="color: #78350f;"><strong>Đăng ký nhận hộp mẫu gạch thực tế (Sample Box)</strong> đối chiếu ánh sáng tại công trình</span>
            </label>

            <label class="l-cluster l-cluster--xs u-text-xs u-text-muted" style="cursor: pointer;">
              <input id="consentCheck" type="checkbox" required checked>
              <span>Tôi đồng ý để Dravit liên hệ tư vấn kỹ thuật và gửi báo giá qua SĐT/Zalo.</span>
            </label>

            <div id="quoteFormError" class="u-text-xs u-bold" style="color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; padding: 8px 10px; border-radius: 6px;" hidden></div>

            <button id="submitQuoteFormBtn" type="submit" class="drv-btn drv-btn--primary drv-btn--full">
              <i class="ph-bold ph-paper-plane-tilt drv-icon--xs" aria-hidden="true"></i>
              <span>Gửi yêu cầu nhận báo giá chi tiết</span>
            </button>
          </form>

          <div id="quoteSuccessBanner" style="background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 10px; padding: 20px; text-align: center;" hidden>
            <div style="width: 44px; height: 44px; margin: 0 auto 10px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px;">
              <i class="ph-bold ph-check" aria-hidden="true"></i>
            </div>
            <h4 class="u-text-base u-bold u-text-navy">Đã tiếp nhận yêu cầu báo giá!</h4>
            <p class="u-text-xs u-text-muted" style="margin-top: 4px;">
              Mã hồ sơ báo giá: <strong id="quoteSuccessOrderCode" class="u-tabular u-text-navy">#DRV-202688</strong>
            </p>
            <p class="u-text-xs" style="margin-top: 8px; color: #065f46;">
              Bộ phận Dự toán Dravit đang kiểm tra tồn kho cùng lô màu (Shade) và sẽ gửi bảng báo giá PDF qua Zalo của bạn trong vòng <strong>15 - 30 phút</strong>.
            </p>
            <div class="l-cluster l-cluster--xs" style="justify-content: center; margin-top: 12px;">
              <a href="04.1-quote-cart.html" class="drv-btn drv-btn--outline drv-btn--sm">
                <i class="ph ph-files drv-icon--xs" aria-hidden="true"></i>
                <span>Mở Bảng dự toán theo phòng</span>
              </a>
              <button id="newQuoteResetBtn" type="button" class="drv-btn drv-btn--primary drv-btn--sm">
                <span>Tạo báo giá mới</span>
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(panel);
    }

    // 2. Ensure Quick Spec Modal exists
    if (!document.getElementById('quickSpecModal')) {
      const modalWrap = document.createElement('div');
      modalWrap.id = 'quickSpecModal';
      modalWrap.className = 'drv-spec-modal-backdrop';
      modalWrap.hidden = true;
      modalWrap.setAttribute('role', 'dialog');
      modalWrap.setAttribute('aria-modal', 'true');
      modalWrap.innerHTML = `
        <div class="drv-spec-modal">
          <div class="drv-qp-head">
            <div>
              <span id="modalProductSku" class="drv-qp-head__eyebrow">DRV-8801M</span>
              <h3 id="modalProductTitle" class="u-text-sm u-bold" style="color: #fff; margin: 2px 0 0;">Thông số kỹ thuật chi tiết</h3>
            </div>
            <button id="closeSpecModalBtn" type="button" class="drv-aif-icon-btn" aria-label="Đóng cửa sổ thông số">
              <i class="ph-bold ph-x" aria-hidden="true"></i>
            </button>
          </div>
          <div style="padding: 18px 20px;" class="l-stack l-stack--sm">
            <div id="modalTilePreviewBox" style="height: 132px; border-radius: 8px; overflow: hidden; border: var(--bw); position: relative;"></div>
            <div style="border: var(--bw); border-radius: 8px; overflow: hidden;">
              <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <tbody id="modalSpecTableBody"></tbody>
              </table>
            </div>
            <div class="l-cluster l-cluster--between" style="padding-top: 6px;">
              <a href="03-product-single.html" class="drv-btn drv-btn--outline drv-btn--sm">
                <span>Trang chi tiết SP</span>
                <i class="ph ph-arrow-up-right drv-icon--xs" aria-hidden="true"></i>
              </a>
              <button id="modalAddQuoteBtn" type="button" class="drv-btn drv-btn--primary drv-btn--sm">
                <i class="ph-bold ph-plus-circle drv-icon--xs" aria-hidden="true"></i>
                <span>Nhận báo giá mẫu này</span>
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modalWrap);
    }

    // 3. Ensure Floating AI Widget exists on pages that don't have <drv-ai-finder> in <main>
    if (!document.querySelector('drv-ai-finder')) {
      const widgetFinder = document.createElement('drv-ai-finder');
      widgetFinder.id = 'drvAiFinder';
      widgetFinder.setAttribute('data-layout-mode', 'widget');
      widgetFinder.setAttribute('data-widget-minimized', 'true');
      widgetFinder.innerHTML = buildWidgetOnlyMarkup();
      document.body.appendChild(widgetFinder);
    }

    // 4. Ensure Floating Launcher FAB exists
    if (!document.getElementById('floatingLauncherBtn')) {
      const fab = document.createElement('button');
      fab.id = 'floatingLauncherBtn';
      fab.type = 'button';
      fab.className = 'drv-aif-fab';
      fab.setAttribute('aria-label', 'Mở Trợ lý AI Tìm gạch & Tính số thùng');
      fab.innerHTML = `
        <span style="position: relative; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(20, 184, 166, 0.2); color: #2dd4bf;">
          <i class="ph-fill ph-robot" aria-hidden="true"></i>
          <span style="position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
        </span>
        <span>Trợ lý AI Tìm gạch</span>
      `;
      document.body.appendChild(fab);
    }
  }

  function buildWidgetOnlyMarkup() {
    return `
      <div class="drv-aif-workspace">
        <section id="chatColumnPanel" class="drv-aif-chat" aria-label="Hội thoại với Trợ lý AI Dravit">
          <div class="drv-aif-chat__head">
            <div class="drv-aif-chat__identity">
              <div class="drv-aif-chat__avatar">
                <i class="ph-fill ph-robot" aria-hidden="true"></i>
                <span class="drv-aif-chat__dot"></span>
              </div>
              <div class="drv-aif-chat__head-info">
                <div class="drv-aif-chat__title-row">
                  <h2 class="drv-aif-chat__title">Dravit AI</h2>
                  <span class="drv-aif-chat__status-pill">Trực tuyến</span>
                </div>
                <p id="aiEngineStatusText" class="drv-aif-chat__sub">Tư vấn thông số &amp; tính số thùng</p>
              </div>
            </div>
            <div class="drv-aif-chat__actions">
              <a href="01.1-ai-finder.html" class="drv-aif-head-pill drv-aif-open-split-link" title="Mở rộng không gian AI 2 cột" aria-label="Mở rộng không gian AI 2 cột">
                <i class="ph-bold ph-arrows-out-simple" aria-hidden="true"></i>
                <span>2 cột</span>
              </a>
              <button id="resetConversationBtn" type="button" class="drv-aif-icon-btn" title="Làm mới cuộc trò chuyện" aria-label="Làm mới cuộc trò chuyện">
                <i class="ph-bold ph-arrows-counter-clockwise" aria-hidden="true"></i>
              </button>
              <button id="minimizeWidgetBtn" type="button" class="drv-aif-icon-btn drv-aif-minimize-btn" title="Thu gọn cửa sổ chat" aria-label="Thu gọn cửa sổ chat">
                <i class="ph-bold ph-minus" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div id="activeContextStrip" class="drv-aif-context" hidden>
            <div class="drv-aif-context__list">
              <span class="drv-aif-context__label"><i class="ph-fill ph-funnel" aria-hidden="true"></i> Lọc:</span>
              <div id="contextFacetsContainer" class="drv-aif-context__tags"></div>
            </div>
            <button id="clearConstraintsBtn" type="button" class="drv-aif-context__clear">Đặt lại</button>
          </div>

          <div id="ai-messages" class="drv-aif-messages" role="log" aria-live="polite" aria-relevant="additions">
            <div class="drv-aif-msg">
              <div class="drv-aif-msg__avatar"><i class="ph-fill ph-user" aria-hidden="true"></i></div>
              <div class="drv-aif-msg__bubble">
                <span class="drv-aif-msg__sender">Cố vấn kỹ thuật Dravit</span>
                <p style="margin: 0;">Cho biết <strong>không gian, diện tích (m²)</strong> hoặc <strong>bề mặt mong muốn</strong> — hệ thống sẽ lọc mẫu đạt chuẩn DCOF và tính sẵn số thùng nguyên.</p>
              </div>
            </div>
          </div>

          <div id="chatSuggestionsBar" class="drv-aif-suggestions" aria-label="Gợi ý câu hỏi nhanh">
            <div class="drv-aif-suggestions__row">
              <button type="button" class="drv-aif-chip suggestion-chip" data-prompt="Tìm gạch lát nền phòng khách 35m² vân đá trắng sáng sang trọng">
                <i class="ph ph-sparkle" aria-hidden="true"></i>
                <span>Phòng khách 35m² vân đá</span>
              </button>
              <button type="button" class="drv-aif-chip suggestion-chip" data-prompt="Gạch lát nền nhà tắm 6m² chống trơn trượt cho người lớn tuổi">
                <i class="ph ph-sparkle" aria-hidden="true"></i>
                <span>Nhà tắm 6m² chống trượt R11</span>
              </button>
              <button type="button" class="drv-aif-chip suggestion-chip" data-prompt="Phòng ngủ 20m² muốn lát gạch giả gỗ tông ấm dễ vệ sinh">
                <i class="ph ph-sparkle" aria-hidden="true"></i>
                <span>Phòng ngủ 20m² giả gỗ</span>
              </button>
              <button type="button" class="drv-aif-chip suggestion-chip" data-prompt="Sân vườn ngoài trời 40m² cần gạch chịu lực chống rêu mốc">
                <i class="ph ph-sparkle" aria-hidden="true"></i>
                <span>Sân vườn 40m² chịu lực</span>
              </button>
            </div>
          </div>

          <form id="aiChatForm" class="drv-aif-form" novalidate>
            <div class="drv-aif-composer">
              <textarea id="aiQueryInput" name="query" rows="2" maxlength="500"
                class="drv-aif-form__input"
                aria-label="Mô tả công trình hoặc diện tích m²"
                placeholder="Nhập diện tích (m²), không gian hoặc bề mặt cần tìm..."
                required></textarea>
              <div class="drv-aif-composer__bar">
                <div class="drv-aif-composer__tools">
                  <label class="drv-aif-waste-toggle" title="Tự động cộng thêm 5% hao hụt cắt gạch khi tính số thùng">
                    <input id="wasteToggleCheckbox" type="checkbox" checked>
                    <span class="drv-aif-waste-toggle__pill">
                      <i class="ph-bold ph-check" aria-hidden="true"></i>
                      <span>+5% hao hụt</span>
                    </span>
                  </label>
                  <span id="charCounter" class="drv-aif-composer__count u-tabular">0/500</span>
                </div>
                <button id="sendQueryBtn" type="submit" class="drv-aif-form__submit" aria-label="Gửi yêu cầu tới AI">
                  <span>Gửi</span>
                  <i class="ph-bold ph-paper-plane-right" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </form>
        </section>
      </div>
    `;
  }

  /* ==========================================================================
     7. MAIN CONTROLLER INITIALIZATION
     ========================================================================== */
  function initDravitAISystem() {
    ensureGlobalQuoteAndModalDOM();
    QuoteManager.load();

    const aiFinderEl = document.querySelector('drv-ai-finder');
    const isStandaloneWidget = aiFinderEl && !document.getElementById('resultsColumnPanel');

    let conversationContext = { area_m2: 25 };
    try {
      const savedCtx = sessionStorage.getItem(STORAGE_KEY_CONTEXT);
      if (savedCtx) {
        const parsedCtx = JSON.parse(savedCtx);
        if (parsedCtx && typeof parsedCtx === 'object' && !Array.isArray(parsedCtx)) {
          conversationContext = { ...conversationContext, ...parsedCtx };
        }
      }
    } catch (e) {
      report('ai-context', e, { op: 'read' });
    }

    let currentRecommendedProducts = [];
    let includeWaste = true;
    let currentModalProduct = null;

    // DOM References
    const chatMessagesEl = document.getElementById('ai-messages');
    const aiChatForm = document.getElementById('aiChatForm');
    const aiQueryInput = document.getElementById('aiQueryInput');
    const charCounter = document.getElementById('charCounter');
    const wasteToggleCheckbox = document.getElementById('wasteToggleCheckbox');
    const activeContextStrip = document.getElementById('activeContextStrip');
    const contextFacetsContainer = document.getElementById('contextFacetsContainer');
    const clearConstraintsBtn = document.getElementById('clearConstraintsBtn');
    const resetConversationBtn = document.getElementById('resetConversationBtn');

    const aiProductGrid = document.getElementById('aiProductGrid');
    const resultsSkeletonGrid = document.getElementById('resultsSkeletonGrid');
    const matchedCountPill = document.getElementById('matchedCountPill');
    const mobileResultsBadge = document.getElementById('mobileResultsBadge');
    const calcAreaSubtitle = document.getElementById('calcAreaSubtitle');
    const aiExplanationText = document.getElementById('aiExplanationText');
    const aiTradeoffNote = document.getElementById('aiTradeoffNote');
    const latencyBadge = document.getElementById('latencyBadge');

    const drvQuotePanel = document.getElementById('drvQuotePanel');
    const quoteBackdrop = document.getElementById('quoteBackdrop');
    const quoteItemsContainer = document.getElementById('quoteItemsContainer');
    const emptyQuoteState = document.getElementById('emptyQuoteState');
    const quoteSummarySection = document.getElementById('quoteSummarySection');
    const quoteContactForm = document.getElementById('quoteContactForm');
    const quoteSuccessBanner = document.getElementById('quoteSuccessBanner');

    const floatingLauncherBtn = document.getElementById('floatingLauncherBtn');
    const minimizeWidgetBtn = document.getElementById('minimizeWidgetBtn');

    // Sync Floating Launcher Button visibility
    function syncFabVisibility() {
      if (!floatingLauncherBtn || !aiFinderEl) return;
      const mode = aiFinderEl.getAttribute('data-layout-mode') || 'split';
      const minimized = aiFinderEl.getAttribute('data-widget-minimized') === 'true';
      if (mode === 'widget') {
        floatingLauncherBtn.hidden = !minimized;
      } else {
        // In split/stream mode on dedicated page, hide FAB so it doesn't overlap
        floatingLauncherBtn.hidden = true;
      }
    }

    syncFabVisibility();

    // Render Product Card in Right Column (Split Mode)
    function createProductCardHTML(scoredItem) {
      const { product, score } = scoredItem;
      const calc = calculateTileUnits(product, conversationContext.area_m2 || 25, includeWaste);
      const inQuoteItem = QuoteManager.items.find((i) => i.sku === product.sku);
      const matchPercent = Math.min(99, Math.max(82, score + 10));

      const stockClass = product.stock_status === 'in_stock'
        ? 'drv-aif-tag-stock drv-aif-tag-stock--ready'
        : 'drv-aif-tag-stock drv-aif-tag-stock--order';

      const dcofClass = product.dcof_level >= 10
        ? 'drv-aif-tag-dcof drv-aif-tag-dcof--safe'
        : 'drv-aif-tag-dcof drv-aif-tag-dcof--std';

      return `
        <article class="drv-aif-card" data-sku="${escapeHTML(product.sku)}">
          <div class="drv-aif-card__media">
            ${renderTilePatternSVG(product.pattern_type, product.sku)}
            <div class="drv-aif-card__badges-top">
              <div class="drv-aif-card__badges-left">
                <span class="drv-aif-tag-sku">${escapeHTML(product.sku)}</span>
                <span class="drv-aif-tag-match">${matchPercent}% khớp</span>
              </div>
              <span class="${stockClass}">${escapeHTML(product.stock_label)}</span>
            </div>
            <div class="drv-aif-card__badges-bottom">
              <span class="${dcofClass}">
                <i class="ph-fill ph-shield-check" aria-hidden="true"></i> DCOF ${escapeHTML(product.dcof)}
              </span>
              <span class="drv-aif-tag-water">Hút nước ${escapeHTML(product.water_absorption)}</span>
            </div>
          </div>

          <div class="drv-aif-card__body">
            <div class="drv-aif-card__top">
              <h3 class="drv-aif-card__title" data-open-spec="${escapeHTML(product.sku)}">
                ${escapeHTML(product.title)}
              </h3>
              <div class="drv-aif-card__specs">
                <span class="drv-aif-spec-pill">${escapeHTML(product.size_label)}</span>
                <span class="drv-aif-spec-pill">${escapeHTML(product.surface)}</span>
                <span class="drv-aif-spec-pill">${product.pieces_per_box} viên/thùng · ${formatDecVN(product.m2_per_box)} m²</span>
              </div>
              <p class="drv-aif-card__desc">
                ${escapeHTML(product.highlight)}
              </p>
            </div>

            <div class="drv-aif-card__calc">
              <div class="drv-aif-card__calc-grid">
                <div>
                  <span class="drv-aif-card__calc-note">Đơn giá niêm yết</span>
                  <div class="drv-aif-card__price-val">
                    <strong>${new Intl.NumberFormat('vi-VN').format(product.price_m2)}đ</strong><small>/m²</small>
                  </div>
                </div>
                <div class="drv-aif-card__calc-right">
                  <strong class="drv-aif-card__calc-boxes">${calc.boxes} thùng</strong>
                  <span class="drv-aif-card__calc-area">${calc.actualPieces} viên · phủ ${formatDecVN(calc.actualAreaCovered)} m²</span>
                </div>
              </div>

              <div class="drv-aif-card__actions">
                <button type="button"
                  data-open-spec="${escapeHTML(product.sku)}"
                  class="drv-aif-btn-spec-full">
                  <i class="ph-bold ph-sliders-horizontal" aria-hidden="true"></i>
                  <span>Thông số</span>
                </button>
                <button type="button"
                  data-add-quote="${escapeHTML(product.sku)}"
                  class="drv-aif-btn-quote ${inQuoteItem ? 'is-in-quote' : ''}">
                  <i class="ph-bold ${inQuoteItem ? 'ph-check-circle' : 'ph-plus-circle'}" aria-hidden="true"></i>
                  <span>${inQuoteItem ? `Đã chọn (${inQuoteItem.boxes} thùng)` : 'Thêm báo giá'}</span>
                </button>
              </div>
            </div>
          </div>
        </article>
      `;
    }

    function renderRecommendationsGrid(rankedList, animate = true) {
      currentRecommendedProducts = rankedList;
      if (matchedCountPill) matchedCountPill.textContent = `${rankedList.length} mẫu phù hợp`;
      if (mobileResultsBadge) mobileResultsBadge.textContent = String(rankedList.length);

      const area = conversationContext.area_m2 || 25;
      const effectiveArea = includeWaste ? Number((area * 1.05).toFixed(2)) : area;
      if (calcAreaSubtitle) {
        calcAreaSubtitle.innerHTML = `Tính cho <strong class="u-text-navy u-tabular">${escapeHTML(String(area))} m²</strong> (${includeWaste ? `+5% hao hụt = <span class="u-tabular">${formatDecVN(effectiveArea)} m²</span>` : 'chưa cộng hao hụt'})`;
      }

      if (!aiProductGrid) return;
      aiProductGrid.innerHTML = rankedList.map((item) => createProductCardHTML(item)).join('');

      if (animate && typeof window.anime === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const cards = aiProductGrid.querySelectorAll('.drv-aif-card');
        window.anime({
          targets: cards,
          opacity: [0, 1],
          translateY: [14, 0],
          duration: 360,
          delay: window.anime.stagger(55),
          easing: 'easeOutCubic'
        });
      }
    }

    function renderActiveContextStrip() {
      if (!activeContextStrip || !contextFacetsContainer) return;
      const tags = [];

      if (conversationContext.space) {
        tags.push({ key: 'space', label: FACET_LABELS.space[conversationContext.space] });
      }
      if (conversationContext.function) {
        tags.push({ key: 'function', label: FACET_LABELS.function[conversationContext.function] });
      }
      if (conversationContext.area_m2) {
        tags.push({ key: 'area_m2', label: `Diện tích: ${conversationContext.area_m2} m²` });
      }
      if (conversationContext.surface) {
        tags.push({ key: 'surface', label: FACET_LABELS.surface[conversationContext.surface] });
      }
      if (conversationContext.min_dcof) {
        tags.push({ key: 'min_dcof', label: `Chống trượt ≥ R${conversationContext.min_dcof}` });
      }
      if (conversationContext.vein) {
        tags.push({ key: 'vein', label: FACET_LABELS.vein[conversationContext.vein] });
      }
      if (conversationContext.size) {
        tags.push({ key: 'size', label: `Khổ ${conversationContext.size}` });
      }
      if (conversationContext.stock_priority) {
        tags.push({ key: 'stock_priority', label: 'Ưu tiên sẵn kho' });
      }

      if (tags.length === 0) {
        activeContextStrip.hidden = true;
        return;
      }

      activeContextStrip.hidden = false;
      contextFacetsContainer.innerHTML = tags.map((t) => `
        <span class="drv-aif-facet-tag">
          <span>${escapeHTML(t.label)}</span>
          <button type="button" data-remove-facet="${escapeHTML(t.key)}" aria-label="Xóa tiêu chí ${escapeHTML(t.label)}"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
        </span>
      `).join('');
    }

    function appendUserBubble(text) {
      if (!chatMessagesEl) return;
      const div = document.createElement('div');
      div.className = 'drv-aif-msg drv-aif-msg--user';
      div.innerHTML = `<div class="drv-aif-msg__bubble">${escapeHTML(text)}</div>`;
      chatMessagesEl.appendChild(div);
      chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
    }

    function appendTypingIndicator() {
      if (!chatMessagesEl) return null;
      const div = document.createElement('div');
      div.id = 'aiTypingIndicator';
      div.className = 'drv-aif-msg';
      div.innerHTML = `
        <div class="drv-aif-msg__avatar"><i class="ph-fill ph-user" aria-hidden="true"></i></div>
        <div class="drv-aif-msg__bubble" style="display: flex; align-items: center; gap: 10px;">
          <div class="drv-aif-typing-dots"><span></span><span></span><span></span></div>
          <span class="u-text-xs u-text-muted">Đang lọc thông số &amp; tính số thùng...</span>
        </div>
      `;
      chatMessagesEl.appendChild(div);
      chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
      return div;
    }

    function formatSimpleMarkdown(text) {
      return escapeHTML(text).replace(/\*\*(.*?)\*\*/g, '<strong class="u-text-navy">$1</strong>');
    }

    function appendAssistantBubble({ explanation, clarification, rankedProducts }) {
      if (!chatMessagesEl) return;
      const area = conversationContext.area_m2 || 25;
      const topThree = rankedProducts.slice(0, 3);

      const inlineCardsHTML = topThree.map(({ product }) => {
        const calc = calculateTileUnits(product, area, includeWaste);
        const inQuote = QuoteManager.items.some((i) => i.sku === product.sku);
        return `
          <div class="drv-aif-mini-card">
            <div class="drv-aif-mini-card__info">
              <div class="drv-aif-mini-card__meta">
                <span class="u-text-navy">${escapeHTML(product.sku)}</span>
                <span>·</span>
                <span>${escapeHTML(product.size_label)}</span>
                <span>·</span>
                <span class="u-text-moss">DCOF ${escapeHTML(product.dcof)}</span>
              </div>
              <p class="drv-aif-mini-card__title">${escapeHTML(product.title)}</p>
            </div>
            <div class="drv-aif-mini-card__foot">
              <span class="drv-aif-mini-card__calc">${calc.boxes} thùng (${formatDecVN(calc.actualAreaCovered)} m²) · ${new Intl.NumberFormat('vi-VN').format(product.price_m2)}đ/m²</span>
              <div class="drv-aif-mini-card__actions">
                <button type="button" data-open-spec="${escapeHTML(product.sku)}" class="drv-aif-mini-btn drv-aif-mini-btn--ghost">
                  Chi tiết
                </button>
                <button type="button" data-add-quote="${escapeHTML(product.sku)}" class="drv-aif-mini-btn drv-aif-mini-btn--primary">
                  ${inQuote ? 'Đã chọn' : '+ Báo giá'}
                </button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      // Make sure inline cards always show in standalone widget or stream/widget mode
      const forceInlineStyle = isStandaloneWidget ? 'style="display: flex;"' : '';

      const div = document.createElement('div');
      div.className = 'drv-aif-msg';
      div.innerHTML = `
        <div class="drv-aif-msg__avatar"><i class="ph-fill ph-user" aria-hidden="true"></i></div>
        <div class="drv-aif-msg__bubble">
          <div class="drv-aif-msg__head">
            <span>Đề xuất kỹ thuật</span>
            <span class="drv-aif-pill drv-aif-pill--navy">${rankedProducts.length} mẫu phù hợp</span>
          </div>
          <p style="margin: 0;">${formatSimpleMarkdown(explanation)}</p>
          ${clarification ? `
            <div class="drv-aif-clarify">
              <i class="ph-fill ph-chat-circle-dots u-text-moss" style="margin-top: 2px;" aria-hidden="true"></i>
              <span>${escapeHTML(clarification)}</span>
            </div>
          ` : ''}
          <div class="drv-aif-inline-cards" ${forceInlineStyle}>
            ${inlineCardsHTML}
          </div>
        </div>
      `;
      chatMessagesEl.appendChild(div);
      chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
    }

    async function handleUserQuery(queryText) {
      const cleanQuery = String(queryText || '').trim();
      if (!cleanQuery) return;

      appendUserBubble(cleanQuery);
      if (aiQueryInput) {
        aiQueryInput.value = '';
        if (charCounter) charCounter.textContent = '0/500';
      }

      const typingEl = appendTypingIndicator();
      if (resultsSkeletonGrid && aiProductGrid) {
        resultsSkeletonGrid.hidden = false;
        aiProductGrid.hidden = true;
      }

      const t0 = performance.now();
      await new Promise((r) => setTimeout(r, 260));

      delete conversationContext.safety_override_note;
      conversationContext = extractLocalIntent(cleanQuery, conversationContext);
      try {
        sessionStorage.setItem(STORAGE_KEY_CONTEXT, JSON.stringify(conversationContext));
      } catch (e) {
        report('ai-context', e, { op: 'write' });
      }

      const ranked = scoreAndRankCatalog(conversationContext);
      const aiCopy = buildAssistantExplanation(conversationContext, ranked, includeWaste);
      const elapsedSec = ((performance.now() - t0) / 1000).toFixed(2).replace('.', ',');

      if (typingEl) typingEl.remove();
      if (resultsSkeletonGrid && aiProductGrid) {
        resultsSkeletonGrid.hidden = true;
        aiProductGrid.hidden = false;
      }

      renderActiveContextStrip();
      renderRecommendationsGrid(ranked, true);

      if (aiExplanationText) aiExplanationText.innerHTML = formatSimpleMarkdown(aiCopy.explanation);
      if (latencyBadge) latencyBadge.textContent = `Phản hồi ${elapsedSec}s · Danh mục chuẩn Dravit`;

      if (aiTradeoffNote) {
        if (aiCopy.tradeoffNote) {
          aiTradeoffNote.hidden = false;
          aiTradeoffNote.innerHTML = `<i class="ph-fill ph-warning-circle" aria-hidden="true"></i> ${escapeHTML(aiCopy.tradeoffNote)}`;
        } else {
          aiTradeoffNote.hidden = true;
        }
      }

      appendAssistantBubble({
        explanation: aiCopy.explanation,
        clarification: aiCopy.clarification,
        rankedProducts: ranked
      });
    }

    // Expose public API on window.Dravit.aiFinder
    window.Dravit = window.Dravit || {};
    window.Dravit.aiFinder = {
      ask(promptText) {
        if (!aiFinderEl) return;
        const mode = aiFinderEl.getAttribute('data-layout-mode') || 'split';
        if (mode === 'widget') {
          aiFinderEl.setAttribute('data-widget-minimized', 'false');
          syncFabVisibility();
        } else {
          aiFinderEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        handleUserQuery(promptText);
      },
      openWidget() {
        if (!aiFinderEl) return;
        if (document.getElementById('resultsColumnPanel') && aiFinderEl.getAttribute('data-layout-mode') !== 'widget') {
          aiFinderEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          if (aiQueryInput) aiQueryInput.focus();
          return;
        }
        aiFinderEl.setAttribute('data-layout-mode', 'widget');
        aiFinderEl.setAttribute('data-widget-minimized', 'false');
        syncFabVisibility();
        if (aiQueryInput) aiQueryInput.focus();
      },
      openQuotePanel,
      addQuoteItem(sku, areaM2, openDrawer = false) {
        const res = QuoteManager.addOrUpdate(sku, areaM2 || conversationContext.area_m2 || 25, includeWaste);
        if (openDrawer) {
          openQuotePanel();
        } else if (typeof window.Dravit.toast === 'function') {
          window.Dravit.toast(`Đã thêm ${res.product.sku} (${res.boxes} thùng = ${formatDecVN(res.areaCovered)} m²) vào Danh sách báo giá.`);
        }
      }
    };

    /* ========================================================================
       8. QUOTE PANEL RENDERING & EVENTS
       ======================================================================== */
    let prevTotalBoxes = 0;

    function renderQuoteState() {
      const items = QuoteManager.items;
      const totals = QuoteManager.getTotals();

      // Update all header badges across the page
      document.querySelectorAll('#headerQuoteBadge, [data-quote-badge-count]').forEach((el) => {
        el.textContent = String(totals.totalSku);
      });
      document.querySelectorAll('#headerTotalBoxesText, [data-quote-total-boxes]').forEach((el) => {
        el.textContent = `${totals.totalBoxes} thùng`;
      });

      if (typeof window.anime === 'function') {
        const badgeEl = document.getElementById('headerQuoteBadge');
        if (badgeEl) {
          window.anime({
            targets: badgeEl,
            scale: [1, 1.28, 1],
            duration: 380,
            easing: 'easeOutElastic(1, .6)'
          });
        }
      }

      if (!quoteItemsContainer) return;

      if (items.length === 0) {
        if (emptyQuoteState) emptyQuoteState.hidden = false;
        quoteItemsContainer.innerHTML = '';
        if (quoteSummarySection) quoteSummarySection.hidden = true;
        if (quoteContactForm) quoteContactForm.hidden = true;
      } else {
        if (emptyQuoteState) emptyQuoteState.hidden = true;
        if (quoteSummarySection) quoteSummarySection.hidden = false;
        if (quoteContactForm && quoteSuccessBanner && quoteSuccessBanner.hidden) {
          quoteContactForm.hidden = false;
        }

        quoteItemsContainer.innerHTML = items.map((item) => {
          const p = QuoteManager.findProduct(item.sku);
          return `
            <div class="drv-qp-item" data-quote-row="${escapeHTML(item.sku)}">
              <div class="drv-qp-item__top">
                <div style="display: flex; align-items: flex-start; gap: 10px; min-width: 0;">
                  <div class="drv-qp-item__media">
                    ${renderTilePatternSVG(p.pattern_type, p.sku)}
                  </div>
                  <div style="min-width: 0;">
                    <div class="l-cluster l-cluster--xs">
                      <span class="drv-aif-tag-sku">${escapeHTML(p.sku)}</span>
                      <span class="u-text-xs u-bold u-text-moss">DCOF ${escapeHTML(p.dcof)}</span>
                    </div>
                    <h4 class="u-text-xs u-bold u-text-navy" style="margin: 3px 0 2px; line-height: 1.35;">${escapeHTML(p.title)}</h4>
                    <span class="u-text-xs u-text-muted">${escapeHTML(p.size_label)} · ${p.pieces_per_box} viên/thùng (${formatDecVN(p.m2_per_box)} m²)</span>
                  </div>
                </div>
                <button type="button" data-remove-quote="${escapeHTML(item.sku)}" class="drv-cart-item__del-btn" aria-label="Xóa ${escapeHTML(p.sku)} khỏi báo giá">
                  <i class="ph-bold ph-trash" aria-hidden="true"></i>
                </button>
              </div>

              <div class="drv-qp-item__bottom">
                <div>
                  <span class="u-text-xs u-text-muted" style="display: block;">Diện tích thực nhận:</span>
                  <strong class="u-text-xs u-text-navy u-tabular">Đủ phủ ${formatDecVN(item.areaCovered)} m²</strong>
                </div>
                <drv-qty-input class="l-cluster l-cluster--xs">
                  <div class="drv-qp-stepper">
                    <button type="button" data-qty-delta="-1" data-sku="${escapeHTML(item.sku)}" aria-label="Giảm 1 thùng">-</button>
                    <input type="number" min="1" max="9999" value="${item.boxes}" data-qty-input="${escapeHTML(item.sku)}" aria-label="Số thùng ${escapeHTML(p.sku)}">
                    <button type="button" data-qty-delta="1" data-sku="${escapeHTML(item.sku)}" aria-label="Tăng 1 thùng">+</button>
                  </div>
                  <span class="u-text-xs u-bold u-text-navy u-tabular">thùng (${item.pieces} viên)</span>
                </drv-qty-input>
              </div>
            </div>
          `;
        }).join('');

        const skuCountEl = document.getElementById('summarySkuCount');
        const totalAreaEl = document.getElementById('summaryTotalArea');
        const totalBoxesEl = document.getElementById('summaryTotalBoxes');

        if (skuCountEl) skuCountEl.textContent = `${totals.totalSku} mẫu`;
        if (totalAreaEl) totalAreaEl.textContent = `${formatDecVN(totals.totalArea)} m²`;

        if (totalBoxesEl) {
          if (typeof window.anime === 'function') {
            const obj = { val: prevTotalBoxes };
            window.anime({
              targets: obj,
              val: totals.totalBoxes,
              round: 1,
              duration: 420,
              easing: 'easeOutQuad',
              update() {
                totalBoxesEl.textContent = `${obj.val} thùng`;
              }
            });
          } else {
            totalBoxesEl.textContent = `${totals.totalBoxes} thùng`;
          }
        }
        prevTotalBoxes = totals.totalBoxes;
      }

      // Sync AI Product Grid buttons if present
      if (currentRecommendedProducts.length > 0) {
        renderRecommendationsGrid(currentRecommendedProducts, false);
      }
    }

    function openQuotePanel() {
      if (!drvQuotePanel || !quoteBackdrop) return;
      drvQuotePanel.classList.add('is-open');
      quoteBackdrop.classList.add('is-open');
    }

    function closeQuotePanel() {
      if (!drvQuotePanel || !quoteBackdrop) return;
      drvQuotePanel.classList.remove('is-open');
      quoteBackdrop.classList.remove('is-open');
    }

    window.addEventListener('drv:quote-updated', renderQuoteState);

    /* ========================================================================
       9. QUICK SPEC MODAL
       ======================================================================== */
    function openSpecModal(sku) {
      const product = QuoteManager.findProduct(sku);
      if (!product) return;
      currentModalProduct = product;

      const modal = document.getElementById('quickSpecModal');
      const skuEl = document.getElementById('modalProductSku');
      const titleEl = document.getElementById('modalProductTitle');
      const previewEl = document.getElementById('modalTilePreviewBox');
      const tbodyEl = document.getElementById('modalSpecTableBody');

      if (!modal || !tbodyEl) return;
      if (skuEl) skuEl.textContent = `${product.sku} · ${product.collection}`;
      if (titleEl) titleEl.textContent = product.title;
      if (previewEl) previewEl.innerHTML = renderTilePatternSVG(product.pattern_type, product.sku);

      const calc = calculateTileUnits(product, conversationContext.area_m2 || 25, includeWaste);
      const rows = [
        ['Kích thước danh định', `${product.size_label} (Độ dày: ${formatDecVN(product.thickness_mm)} mm)`],
        ['Chất liệu xương gạch', product.material],
        ['Bề mặt & Chỉ số chống trượt', `${product.surface} · Chuẩn DCOF ${product.dcof}`],
        ['Độ hút nước & Độ mài mòn', `E ${product.water_absorption} · ${product.wear_pei}`],
        ['Xử lý cạnh viên gạch', product.rectified],
        ['Quy cách đóng thùng', `${product.pieces_per_box} viên/thùng = ${formatDecVN(product.m2_per_box)} m²`],
        ['Đơn giá niêm yết tham khảo', `${new Intl.NumberFormat('vi-VN').format(product.price_m2)}đ/m²`],
        [`Khối lượng tạm tính (${calc.inputArea} m²)`, `${calc.boxes} thùng = ${calc.actualPieces} viên (Đủ phủ ${formatDecVN(calc.actualAreaCovered)} m²)`]
      ];

      tbodyEl.innerHTML = rows.map(([k, v], i) => `
        <tr style="background: ${i % 2 === 0 ? 'var(--c-ivory)' : 'var(--c-white)'}; border-bottom: 1px solid var(--c-navy-100);">
          <th style="padding: 8px 12px; text-align: left; color: var(--c-text-muted); font-weight: 600; width: 42%;">${escapeHTML(k)}</th>
          <td style="padding: 8px 12px; font-weight: 700; color: var(--c-navy-900);">${escapeHTML(v)}</td>
        </tr>
      `).join('');

      modal.hidden = false;
    }

    function closeSpecModal() {
      const modal = document.getElementById('quickSpecModal');
      if (modal) modal.hidden = true;
    }

    /* ========================================================================
       10. EVENT BINDINGS & DELEGATION
       ======================================================================== */
    if (aiQueryInput && charCounter) {
      aiQueryInput.addEventListener('input', () => {
        charCounter.textContent = `${aiQueryInput.value.length}/500`;
      });
      aiQueryInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (aiChatForm) aiChatForm.requestSubmit();
        }
      });
    }

    if (aiChatForm) {
      aiChatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        if (aiQueryInput) handleUserQuery(aiQueryInput.value);
      });
    }

    if (wasteToggleCheckbox) {
      wasteToggleCheckbox.addEventListener('change', () => {
        includeWaste = wasteToggleCheckbox.checked;
        renderRecommendationsGrid(currentRecommendedProducts, false);
      });
    }

    if (clearConstraintsBtn) {
      clearConstraintsBtn.addEventListener('click', () => {
        conversationContext = { area_m2: 25 };
        sessionStorage.removeItem(STORAGE_KEY_CONTEXT);
        renderActiveContextStrip();
        renderRecommendationsGrid(scoreAndRankCatalog(conversationContext), true);
      });
    }

    if (resetConversationBtn) {
      resetConversationBtn.addEventListener('click', () => {
        conversationContext = { area_m2: 25 };
        sessionStorage.removeItem(STORAGE_KEY_CONTEXT);
        renderActiveContextStrip();
        const initialRanked = scoreAndRankCatalog(conversationContext);
        renderRecommendationsGrid(initialRanked, true);
        if (chatMessagesEl) {
          const firstMsg = chatMessagesEl.firstElementChild;
          chatMessagesEl.innerHTML = '';
          if (firstMsg) chatMessagesEl.appendChild(firstMsg);
        }
      });
    }

    if (minimizeWidgetBtn && aiFinderEl) {
      minimizeWidgetBtn.addEventListener('click', () => {
        aiFinderEl.setAttribute('data-widget-minimized', 'true');
        syncFabVisibility();
      });
    }

    if (floatingLauncherBtn && aiFinderEl) {
      floatingLauncherBtn.addEventListener('click', () => {
        aiFinderEl.setAttribute('data-layout-mode', 'widget');
        aiFinderEl.setAttribute('data-widget-minimized', 'false');
        syncFabVisibility();
        if (aiQueryInput) aiQueryInput.focus();
      });
    }

    // Global Click Delegation
    document.addEventListener('click', (e) => {
      const target = e.target;
      if (!(target instanceof Element)) return;

      // 1. Suggestion Chip or Refine Prompt
      const chipBtn = target.closest('.suggestion-chip, [data-refine-prompt]');
      if (chipBtn) {
        const prompt = chipBtn.getAttribute('data-prompt') || chipBtn.getAttribute('data-refine-prompt');
        if (prompt) handleUserQuery(prompt);
        return;
      }

      // 2. Remove Single Context Facet
      const removeFacetBtn = target.closest('[data-remove-facet]');
      if (removeFacetBtn) {
        const key = removeFacetBtn.getAttribute('data-remove-facet');
        if (key === 'area_m2') conversationContext.area_m2 = 25;
        else delete conversationContext[key];
        renderActiveContextStrip();
        renderRecommendationsGrid(scoreAndRankCatalog(conversationContext), true);
        return;
      }

      // 2b. Open Split Mode from Link
      const openSplitLink = target.closest('.drv-aif-open-split-link');
      if (openSplitLink && document.querySelector('[data-mode-switch="split"]')) {
        e.preventDefault();
        document.querySelector('[data-mode-switch="split"]').click();
        return;
      }

      // 3. Layout Mode Switcher (split | stream | widget)
      const modeBtn = target.closest('[data-mode-switch]');
      if (modeBtn && aiFinderEl) {
        const nextMode = modeBtn.getAttribute('data-mode-switch') || 'split';
        aiFinderEl.setAttribute('data-layout-mode', nextMode);
        aiFinderEl.setAttribute('data-widget-minimized', 'false');
        document.querySelectorAll('[data-mode-switch]').forEach((btn) => {
          const active = btn.getAttribute('data-mode-switch') === nextMode;
          btn.classList.toggle('is-active', active);
          btn.setAttribute('aria-pressed', String(active));
        });
        syncFabVisibility();
        return;
      }

      // 4. Mobile Tab Switcher (chat | results)
      const mobileTabBtn = target.closest('[data-mobile-tab]');
      if (mobileTabBtn && aiFinderEl) {
        const tab = mobileTabBtn.getAttribute('data-mobile-tab') || 'chat';
        aiFinderEl.setAttribute('data-mobile-tab', tab);
        document.querySelectorAll('[data-mobile-tab]').forEach((btn) => {
          btn.classList.toggle('is-active', btn.getAttribute('data-mobile-tab') === tab);
        });
        return;
      }

      // 5. Toggle Quick Refine Bar
      if (target.closest('#openRefineDrawerBtn')) {
        const bar = document.getElementById('quickRefineBar');
        if (bar) bar.hidden = !bar.hidden;
        return;
      }

      // 6. Add All Recommended to Quote
      if (target.closest('#addAllToQuoteBtn')) {
        currentRecommendedProducts.forEach(({ product }) => {
          QuoteManager.addOrUpdate(product.sku, conversationContext.area_m2 || 25, includeWaste);
        });
        openQuotePanel();
        return;
      }

      // 7. Add Single Item to Quote (from AI Cards, Catalog Cards, PDP)
      const addQuoteBtn = target.closest('[data-add-quote], [data-drv-action="quote-add-item"]');
      if (addQuoteBtn) {
        const sku = addQuoteBtn.getAttribute('data-add-quote') || addQuoteBtn.getAttribute('data-drv-sku') || 'DRV-8801M';
        const customBoxes = parseInt(addQuoteBtn.getAttribute('data-drv-boxes') || '0', 10) || null;
        const res = QuoteManager.addOrUpdate(sku, conversationContext.area_m2 || 25, includeWaste, customBoxes);
        if (typeof window.Dravit?.toast === 'function') {
          window.Dravit.toast(`Đã thêm ${res.product.sku} (${res.boxes} thùng) vào Danh sách báo giá.`);
        }
        return;
      }

      // 8. Quick 1-Touch Quote
      const quickQuoteBtn = target.closest('[data-quick-quote]');
      if (quickQuoteBtn) {
        const sku = quickQuoteBtn.getAttribute('data-quick-quote');
        if (sku) {
          QuoteManager.addOrUpdate(sku, conversationContext.area_m2 || 25, includeWaste);
          openQuotePanel();
        }
        return;
      }

      // 9. Open Spec Modal
      const specBtn = target.closest('[data-open-spec]');
      if (specBtn) {
        const sku = specBtn.getAttribute('data-open-spec');
        if (sku) openSpecModal(sku);
        return;
      }

      // 10. Close Spec Modal
      if (target.closest('#closeSpecModalBtn') || target.id === 'quickSpecModal') {
        closeSpecModal();
        return;
      }

      // 11. Add to Quote from Spec Modal
      if (target.closest('#modalAddQuoteBtn') && currentModalProduct) {
        QuoteManager.addOrUpdate(currentModalProduct.sku, conversationContext.area_m2 || 25, includeWaste);
        closeSpecModal();
        openQuotePanel();
        return;
      }

      // 12. Open / Close Quote Panel
      if (target.closest('#openQuoteHeaderBtn, [data-drv-action="quote-open-panel"]')) {
        openQuotePanel();
        return;
      }
      if (target.closest('#closeQuotePanelBtn') || target.id === 'quoteBackdrop') {
        closeQuotePanel();
        return;
      }

      // 13. Empty Quote -> Ask AI
      if (target.closest('#emptyQuoteAskAiBtn')) {
        closeQuotePanel();
        window.Dravit.aiFinder.openWidget();
        return;
      }

      // 14. Open AI Finder Trigger
      const aiOpenTrigger = target.closest('[data-drv-action="ai-finder-open"], #triggerWidgetFromBackdropBtn');
      if (aiOpenTrigger) {
        const prompt = aiOpenTrigger.getAttribute('data-drv-prompt');
        if (prompt) {
          window.Dravit.aiFinder.ask(prompt);
        } else {
          window.Dravit.aiFinder.openWidget();
        }
        return;
      }

      // 15. Quote Item Stepper (+/-) & Remove
      const deltaBtn = target.closest('[data-qty-delta]');
      if (deltaBtn) {
        const sku = deltaBtn.getAttribute('data-sku');
        const delta = parseInt(deltaBtn.getAttribute('data-qty-delta') || '0', 10);
        const item = QuoteManager.items.find((i) => i.sku === sku);
        if (item) {
          QuoteManager.updateBoxes(sku, item.boxes + delta);
        }
        return;
      }

      const removeQuoteBtn = target.closest('[data-remove-quote]');
      if (removeQuoteBtn) {
        const sku = removeQuoteBtn.getAttribute('data-remove-quote');
        if (sku) QuoteManager.remove(sku);
        return;
      }

      // 16. Share Quote URL
      if (target.closest('#shareQuoteUrlBtn')) {
        if (QuoteManager.items.length === 0) return;
        const encoded = b64EncodeUtf8(JSON.stringify(QuoteManager.items));
        const shareUrl = `${window.location.origin}${window.location.pathname}?quote=${encoded}`;
        if (navigator.clipboard?.writeText) {
          navigator.clipboard.writeText(shareUrl).catch((e) => report('clipboard', e));
        }
        const lbl = document.getElementById('shareQuoteBtnLabel');
        if (lbl) {
          lbl.textContent = 'Đã chép link!';
          setTimeout(() => { lbl.textContent = 'Chia sẻ'; }, 2200);
        }
        if (typeof window.Dravit?.toast === 'function') {
          window.Dravit.toast('Đã sao chép đường dẫn danh sách báo giá vào bộ nhớ tạm.');
        }
        return;
      }

      // 17. Convert Cart Items to Quote Panel (04-cart.html)
      if (target.closest('[data-drv-action="cart-to-quote-panel"]')) {
        const cartRows = document.querySelectorAll('[data-cart-row]');
        cartRows.forEach((row) => {
          const sku = row.getAttribute('data-cart-sku') || 'DRV-8801M';
          const qtyInput = row.querySelector('.drv-stepper__input');
          const pieces = parseInt(qtyInput?.value || '18', 10);
          const p = QuoteManager.findProduct(sku);
          const boxes = Math.max(1, Math.ceil(pieces / p.pieces_per_box));
          QuoteManager.addOrUpdate(sku, 25, true, boxes);
        });
        openQuotePanel();
        return;
      }

      // 18. AI Feedback Loop Buttons
      const fbBtn = target.closest('[data-feedback]');
      if (fbBtn) {
        const type = fbBtn.getAttribute('data-feedback');
        const negBox = document.getElementById('negativeFeedbackReasonBox');
        const thankMsg = document.getElementById('feedbackThankYouMsg');
        if (type === 'helpful') {
          if (negBox) negBox.hidden = true;
          if (thankMsg) {
            thankMsg.hidden = false;
            thankMsg.textContent = 'Cảm ơn bạn! Dravit AI đã ghi nhận phản hồi.';
          }
        } else if (negBox) {
          negBox.hidden = false;
        }
        return;
      }

      const reasonPill = target.closest('.reason-pill');
      if (reasonPill) {
        const reason = reasonPill.getAttribute('data-reason');
        const negBox = document.getElementById('negativeFeedbackReasonBox');
        if (negBox) negBox.hidden = true;
        if (reason) handleUserQuery(reason);
      }
    });

    // Direct Input Change on Quote Stepper
    document.addEventListener('change', (e) => {
      const target = e.target;
      if (!(target instanceof HTMLInputElement)) return;
      if (target.hasAttribute('data-qty-input')) {
        const sku = target.getAttribute('data-qty-input');
        if (sku) QuoteManager.updateBoxes(sku, target.value);
      }
      if (target.name === 'customer_persona') {
        const hintEl = document.getElementById('personaBenefitHint');
        if (!hintEl) return;
        const hints = {
          'chu-nha': 'Hỗ trợ tính dư hao hụt chuẩn xác, miễn phí gửi mẫu tận nhà & đổi trả thùng nguyên dư.',
          'nha-thau': 'Áp dụng bảng chiết khấu sỉ theo khối lượng công trình, cam kết tiến độ giao hàng & xuất VAT.',
          'kts': 'Cung cấp trọn bộ Map Texture 4K (CAD/3dsMax), thông số DCOF/PEI và gửi hộp mẫu Sample Box.'
        };
        hintEl.innerHTML = `<i class="ph-fill ph-check-circle" aria-hidden="true"></i> ${escapeHTML(hints[target.value] || hints['chu-nha'])}`;
      }
    });

    // Quote Lead Form Submit
    if (quoteContactForm) {
      quoteContactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const hp = document.getElementById('drvWebsiteHoneypot');
        if (hp && hp.value) return;

        const nameInput = document.getElementById('custName');
        const phoneInput = document.getElementById('custPhone');
        const consentInput = document.getElementById('consentCheck');
        const errBox = document.getElementById('quoteFormError');

        const nameVal = nameInput?.value.trim() || '';
        const phoneVal = (phoneInput?.value || '').replace(/\s+/g, '');
        const vnPhoneRegex = /^(0|\+84)(3|5|7|8|9)\d{8}$/;

        if (!nameVal) {
          if (errBox) {
            errBox.hidden = false;
            errBox.textContent = 'Vui lòng nhập họ và tên người nhận báo giá.';
          }
          nameInput?.focus();
          return;
        }
        if (!vnPhoneRegex.test(phoneVal)) {
          if (errBox) {
            errBox.hidden = false;
            errBox.textContent = 'Vui lòng nhập số điện thoại Việt Nam hợp lệ (10 số, VD: 0901234567).';
          }
          phoneInput?.focus();
          return;
        }
        if (consentInput && !consentInput.checked) {
          if (errBox) {
            errBox.hidden = false;
            errBox.textContent = 'Vui lòng xác nhận đồng ý nhận tư vấn báo giá.';
          }
          return;
        }

        if (errBox) errBox.hidden = true;
        const orderCode = `#DRV-${Math.floor(100000 + Math.random() * 900000)} (drv-quote-draft)`;
        const codeEl = document.getElementById('quoteSuccessOrderCode');
        if (codeEl) codeEl.textContent = orderCode;

        quoteContactForm.hidden = true;
        if (quoteSuccessBanner) quoteSuccessBanner.hidden = false;
      });
    }

    const newQuoteResetBtn = document.getElementById('newQuoteResetBtn');
    if (newQuoteResetBtn) {
      newQuoteResetBtn.addEventListener('click', () => {
        QuoteManager.clear();
        if (quoteSuccessBanner) quoteSuccessBanner.hidden = true;
      });
    }

    // Initial Render
    const initialRanked = scoreAndRankCatalog(conversationContext);
    renderActiveContextStrip();
    renderRecommendationsGrid(initialRanked, false);
    renderQuoteState();

    // Check URL ?ai_query= parameter for cross-page handoff
    try {
      const params = new URLSearchParams(window.location.search);
      const initialQuery = params.get('ai_query');
      if (initialQuery) {
        setTimeout(() => handleUserQuery(initialQuery), 150);
      }
    } catch (e) {
      report('ai-query', e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDravitAISystem);
  } else {
    initDravitAISystem();
  }
})();

