/**
 * Lõi tính toán quy đổi đơn vị ngành gạch duy nhất phía Client (docs/04 §3.3, docs/15 §4.1, ADR D-20, D-24).
 * Đối xứng 100% đầu ra với `Dravit\Units\Calc` (PHP) qua `tests/fixtures/calc-vectors.json`.
 */

export const EPSILON = 1e-9;

export const DEFAULT_VOLUME_TIERS = [
  { min_m2: 100, rate: 8 },
  { min_m2: 30, rate: 5 },
  { min_m2: 0, rate: 0 },
];

export const DEFAULT_VEHICLE_THRESHOLDS = [
  { max_kg: 1000, label: 'Xe tải nhẹ 1 tấn' },
  { max_kg: 2500, label: 'Xe tải 2,5 tấn' },
  { max_kg: 5000, label: 'Xe tải 5 tấn' },
  { max_kg: 999999, label: 'Xe tải 8 tấn / chia chuyến' },
];

/**
 * Diện tích 1 viên gạch (m²) từ kích thước rộng × dài (mm).
 * @param {number} widthMm
 * @param {number} heightMm
 * @returns {number}
 */
export function pieceAreaM2(widthMm, heightMm) {
  const w = Number(widthMm) || 0;
  const h = Number(heightMm) || 0;
  if (w <= 0 || h <= 0) {
    return 0;
  }
  return Number(((w * h) / 1_000_000).toFixed(6));
}

/**
 * Giá 1 viên gạch (VND nguyên), khớp `_regular_price` / `_sale_price` của WooCommerce.
 * @param {number} priceM2
 * @param {number} areaPiece
 * @returns {number}
 */
export function pricePerPiece(priceM2, areaPiece) {
  const p = Number(priceM2) || 0;
  const a = Number(areaPiece) || 0;
  if (!(p > 0) || !(a > 0)) {
    return 0;
  }
  return Math.round(p * a);
}

/**
 * Chuẩn hóa số lượng viên theo cấu hình D-24 của từng SKU.
 * @param {number} rawPieces
 * @param {{ piecesPerBox?: number, roundMode?: string, qtyMin?: number }} options
 * @returns {number}
 */
export function normalizePieces(rawPieces, { piecesPerBox = 1, roundMode = 'box', qtyMin = 1 } = {}) {
  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  const minP = Math.max(1, Math.trunc(Number(qtyMin) || 1));
  const raw = Number(rawPieces) || 0;
  let pieces = Math.max(minP, Math.ceil(raw - EPSILON));
  if (roundMode === 'box') {
    pieces = Math.ceil((pieces - EPSILON) / ppb) * ppb;
  }
  return pieces;
}

/**
 * Tách tổng số viên thành thùng nguyên + viên lẻ + chuỗi hiển thị chuẩn tiếng Việt.
 * @param {number} pieces
 * @param {number} piecesPerBox
 * @param {string} [unitLabel='viên']
 * @returns {{ fullBoxes: number, loosePieces: number, boxText: string }}
 */
export function boxBreakdown(pieces, piecesPerBox, unitLabel = 'viên') {
  const qty = Math.max(0, Math.trunc(Number(pieces) || 0));
  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  const label = unitLabel || 'viên';
  const fullBoxes = Math.floor(qty / ppb);
  const loosePieces = qty % ppb;
  let boxText = `${fullBoxes} thùng`;
  if (loosePieces > 0) {
    boxText =
      fullBoxes > 0
        ? `${fullBoxes} thùng + ${loosePieces} ${label} lẻ`
        : `${loosePieces} ${label} lẻ`;
  }
  return { fullBoxes, loosePieces, boxText };
}

/**
 * Tổng hợp đầy đủ chỉ số đầu ra từ số viên nguyên đã chốt.
 */
export function buildCalcResult({
  pieces,
  needM2,
  areaPiece,
  piecesPerBox,
  kgPerBox = 0,
  priceM2 = 0,
  pricePiece: explicitPricePiece = null,
  unitLabel = 'viên',
}) {
  const pM2 = Number(priceM2) || 0;
  const aPiece = Number(areaPiece) || 0;
  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  const kgBox = Math.max(0, Number(kgPerBox) || 0);
  const isQuote = !(pM2 > 0);
  const resolvedPricePiece = isQuote
    ? 0
    : explicitPricePiece !== null && Number(explicitPricePiece) > 0
      ? Math.round(Number(explicitPricePiece))
      : pricePerPiece(pM2, aPiece);

  const actualM2 = Number((pieces * aPiece).toFixed(4));
  const kgPerPiece = ppb > 0 ? kgBox / ppb : 0;
  const totalKg = Number((pieces * kgPerPiece).toFixed(1));
  const totalVnd = resolvedPricePiece > 0 ? pieces * resolvedPricePiece : 0;
  const { fullBoxes, loosePieces, boxText } = boxBreakdown(pieces, ppb, unitLabel);

  return {
    pieces,
    fullBoxes,
    loosePieces,
    boxText,
    needM2: Number(Number(needM2 || 0).toFixed(4)),
    actualM2,
    totalKg,
    pricePiece: resolvedPricePiece,
    totalVnd,
    isQuote,
  };
}

/**
 * 1. Nhập theo diện tích m² (cộng % hao hụt và tùy chọn làm tròn chẵn thùng).
 */
export function calcFromM2({
  areaM2,
  wastePct = 0,
  forceRoundBox = null,
  areaPiece,
  piecesPerBox,
  kgPerBox = 0,
  priceM2 = 0,
  pricePiece = null,
  roundMode = 'box',
  qtyMin = 1,
  unitLabel = 'viên',
}) {
  const netM2 = Math.max(0, Number(areaM2) || 0);
  const waste = Math.max(0, Number(wastePct) || 0);
  const aPiece = Number(areaPiece) || 0;
  const needM2 = netM2 * (1 + waste / 100);
  const effectiveRoundMode = roundMode === 'box' || forceRoundBox === true ? 'box' : 'piece';
  const rawPieces = needM2 > 0 && aPiece > 0 ? needM2 / aPiece : qtyMin;
  const pieces = normalizePieces(rawPieces, {
    piecesPerBox,
    roundMode: effectiveRoundMode,
    qtyMin,
  });

  return buildCalcResult({
    pieces,
    needM2,
    areaPiece: aPiece,
    piecesPerBox,
    kgPerBox,
    priceM2,
    pricePiece,
    unitLabel,
  });
}

/**
 * 2. Nhập trực tiếp theo số thùng nguyên.
 */
export function calcFromBoxes({
  boxes,
  areaPiece,
  piecesPerBox,
  kgPerBox = 0,
  priceM2 = 0,
  pricePiece = null,
  qtyMin = 1,
  unitLabel = 'viên',
}) {
  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  const aPiece = Number(areaPiece) || 0;
  const validBoxes = Math.max(1, Math.ceil((Number(boxes) || 1) - EPSILON));
  const pieces = normalizePieces(validBoxes * ppb, {
    piecesPerBox: ppb,
    roundMode: 'box',
    qtyMin,
  });

  return buildCalcResult({
    pieces,
    needM2: pieces * aPiece,
    areaPiece: aPiece,
    piecesPerBox: ppb,
    kgPerBox,
    priceM2,
    pricePiece,
    unitLabel,
  });
}

/**
 * 3. Nhập trực tiếp theo số viên / tấm.
 */
export function calcFromPieces({
  pieces: inputPieces,
  areaPiece,
  piecesPerBox,
  kgPerBox = 0,
  priceM2 = 0,
  pricePiece = null,
  roundMode = 'piece',
  qtyMin = 1,
  unitLabel = 'viên',
}) {
  const aPiece = Number(areaPiece) || 0;
  const rawInput = Math.max(1, Math.round(Number(inputPieces) || 1));
  const pieces = normalizePieces(rawInput, {
    piecesPerBox,
    roundMode,
    qtyMin,
  });

  return buildCalcResult({
    pieces,
    needM2: pieces * aPiece,
    areaPiece: aPiece,
    piecesPerBox,
    kgPerBox,
    priceM2,
    pricePiece,
    unitLabel,
  });
}

/**
 * 4. Tính dòng Giỏ hàng / Đơn hàng từ số viên nguyên đã lưu trong WooCommerce.
 */
export function calcCartLine({
  pieces,
  areaPiece,
  piecesPerBox,
  kgPerBox = 0,
  pricePiece = 0,
  priceM2 = 0,
  unitLabel = 'viên',
}) {
  const qty = Math.max(1, Math.trunc(Number(pieces) || 1));
  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  const aPiece = Number(areaPiece) || 0;
  const kgBox = Math.max(0, Number(kgPerBox) || 0);
  const unitVnd =
    Number(pricePiece) > 0
      ? Math.round(Number(pricePiece))
      : pricePerPiece(Number(priceM2) || 0, aPiece);
  const actualM2 = Number((qty * aPiece).toFixed(4));
  const kgPerPiece = ppb > 0 ? kgBox / ppb : 0;
  const totalKg = Number((qty * kgPerPiece).toFixed(1));
  const { fullBoxes, loosePieces, boxText } = boxBreakdown(qty, ppb, unitLabel);
  const neededForNextBox = loosePieces > 0 ? ppb - loosePieces : 0;

  return {
    pieces: qty,
    fullBoxes,
    loosePieces,
    neededForNextBox,
    boxText,
    actualM2,
    totalKg,
    pricePiece: unitVnd,
    lineTotalVnd: qty * unitVnd,
  };
}

/**
 * 5. Tính dòng hạng mục dự toán BOQ (`0 m² -> 0 thùng`; `> 0 m²` luôn quy chẵn thùng nguyên).
 */
export function calcBoqSlot({
  targetM2,
  wastePct = 5,
  areaPiece,
  piecesPerBox,
  kgPerBox = 0,
  priceM2 = 0,
  pricePiece = null,
  unitLabel = 'viên',
}) {
  const netM2 = Math.max(0, Number(targetM2) || 0);
  const pM2 = Number(priceM2) || 0;
  if (netM2 <= 0) {
    return {
      pieces: 0,
      fullBoxes: 0,
      loosePieces: 0,
      boxText: '0 thùng',
      needM2: 0,
      actualM2: 0,
      totalKg: 0,
      pricePiece: 0,
      totalVnd: 0,
      isQuote: !(pM2 > 0),
    };
  }

  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  return calcFromM2({
    areaM2: netM2,
    wastePct,
    forceRoundBox: true,
    areaPiece,
    piecesPerBox: ppb,
    kgPerBox,
    priceM2: pM2,
    pricePiece,
    roundMode: 'box',
    qtyMin: ppb,
    unitLabel,
  });
}

/**
 * 6. Tính chiết khấu khối lượng dự kiến trên bảng BOQ (ADR D-23).
 */
export function calcVolumeDiscount(subtotalVnd, totalM2, tiers = DEFAULT_VOLUME_TIERS) {
  const sub = Math.max(0, Math.round(Number(subtotalVnd) || 0));
  const m2 = Math.max(0, Number(totalM2) || 0);
  const normalized = Array.isArray(tiers) && tiers.length > 0 ? tiers : DEFAULT_VOLUME_TIERS;
  const sorted = [...normalized]
    .map((t) => ({
      min_m2: Number(t.min_m2) || 0,
      rate: Number(t.rate ?? t.discount_pct ?? 0) || 0,
    }))
    .sort((a, b) => b.min_m2 - a.min_m2);

  const matched = sorted.find((t) => m2 + EPSILON >= t.min_m2) || { min_m2: 0, rate: 0 };
  const nextTier = [...sorted].reverse().find((t) => t.min_m2 > m2 + EPSILON) || null;
  const discountVnd = Math.round((sub * matched.rate) / 100);

  return {
    rate: matched.rate,
    minM2: matched.min_m2,
    discountVnd,
    grandVnd: sub - discountVnd,
    nextTier,
  };
}

/**
 * 7. Gợi ý phương tiện vận chuyển theo tổng khối lượng kg.
 */
export function resolveVehicleHint(totalKg, thresholds = DEFAULT_VEHICLE_THRESHOLDS) {
  const kg = Number(totalKg) || 0;
  if (!(kg > 0)) {
    return 'Chưa có khối lượng';
  }
  const list =
    Array.isArray(thresholds) && thresholds.length > 0 ? thresholds : DEFAULT_VEHICLE_THRESHOLDS;
  for (const t of list) {
    if (kg <= Number(t.max_kg)) {
      return String(t.label);
    }
  }
  return 'Xe tải 8 tấn / chia chuyến';
}

/**
 * 8. Số lượng viên mặc định khi bấm nút Thêm nhanh trên Card danh mục (docs/04 §3.4, D-24).
 */
export function quickAddPieces({
  defaultBuyUnit = 'm2',
  roundMode = 'box',
  piecesPerBox = 1,
  qtyMin = 1,
} = {}) {
  const ppb = Math.max(1, Math.trunc(Number(piecesPerBox) || 1));
  const minP = Math.max(1, Math.trunc(Number(qtyMin) || 1));
  if (roundMode === 'box') {
    const target = Math.max(ppb, minP);
    return Math.ceil((target - EPSILON) / ppb) * ppb;
  }
  if (defaultBuyUnit === 'm2' || defaultBuyUnit === 'thung') {
    return Math.max(ppb, minP);
  }
  return minP;
}

