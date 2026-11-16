import { beforeEach, describe, expect, it } from 'vitest';
import { computeSlot } from '../../assets/src/js/utils/calc.js';
import {
  DEFAULT_ROOM_ID,
  LEGACY_STORAGE_KEY,
  MAX_SLOTS,
  QUOTE_STORAGE_KEY,
  SHARE_MAX_LENGTH,
  addRoom,
  addSlot,
  encodeShare,
  getStats,
  mergeStates,
  moveSlot,
  parseShareParam,
  readState,
  sanitizeCsvCell,
  updateQuote,
} from '../../assets/src/js/utils/quote-store.js';
import { aggregateForCart, buildCsv, computeQuote } from '../../assets/src/js/quote.js';

/**
 * Step 8 · QuoteStore đa phòng (docs/17 §2–§3, §6): đa phòng, migrate v1 -> v2, link chia sẻ Base64URL an toàn,
 * trộn SP `priced` + `quote_only`, CSV chống Formula Injection.
 */

// SKU chuẩn U-1: 80×80, 3 viên/thùng (1,92 m²), 385.000đ/m² → 246.400đ/viên, 42 kg/thùng.
const PRICED = {
  id: 101,
  sku: 'DRV-8801M',
  name: 'Marble Calacatta 80x80',
  price_state: 'priced',
  price_m2: 385000,
  price_piece: 246400,
  area_piece: 0.64,
  pieces_per_box: 3,
  kg_per_box: 42,
  round_mode: 'box',
  qty_min: 3,
  unit_label: 'viên',
  in_stock: true,
  stock_qty: 600,
  buy_units: ['m2', 'thung'],
};
// Big slab 120×240 bán theo tấm lẻ, chưa niêm yết giá.
const QUOTE_ONLY = {
  id: 105,
  sku: 'DRV-SLAB01',
  name: 'Big Slab Onyx 120x240',
  price_state: 'quote_only',
  price_m2: 0,
  price_piece: 0,
  area_piece: 2.88,
  pieces_per_box: 1,
  kg_per_box: 68,
  round_mode: 'piece',
  qty_min: 1,
  unit_label: 'tấm',
  in_stock: true,
  stock_qty: null,
  buy_units: ['m2', 'vien'],
};

const products = new Map([
  [101, PRICED],
  [105, QUOTE_ONLY],
]);

beforeEach(() => {
  window.localStorage.clear();
});

describe('QuoteStore đa phòng (drv_quote_v2)', () => {
  it('thêm nhiều phòng, cộng dồn cùng SP + hạng mục, chuyển dòng giữa phòng', () => {
    const { state } = updateQuote((draft) => {
      addSlot(draft, DEFAULT_ROOM_ID, { product_id: 101, sku: 'DRV-8801M', role: 'Lát nền chính', input_mode: 'm2', target_m2: 20 });
      addSlot(draft, DEFAULT_ROOM_ID, { product_id: 101, sku: 'DRV-8801M', role: 'Lát nền chính', input_mode: 'm2', target_m2: 12.5 });
      const bath = addRoom(draft, 'Nhà tắm Master');
      addSlot(draft, bath.id, { product_id: 105, sku: 'DRV-SLAB01', role: 'Ốp tường', input_mode: 'vien', pieces: 4 });
    });

    expect(state.rooms).toHaveLength(2);
    expect(state.rooms[0].id).toBe(DEFAULT_ROOM_ID);
    expect(state.rooms[0].slots).toHaveLength(1);
    expect(state.rooms[0].slots[0].target_m2).toBe(32.5);
    expect(getStats(state)).toMatchObject({ totalSlots: 2, uniqueSkus: 2, roomCount: 2 });

    const slotId = state.rooms[0].slots[0].id;
    const moved = updateQuote((draft) => moveSlot(draft, slotId, draft.rooms[1].id)).state;
    expect(moved.rooms[0].slots).toHaveLength(0);
    expect(moved.rooms[1].slots).toHaveLength(2);
  });

  it('phát sự kiện drv:quote-updated kèm totalSlots/uniqueSkus/roomCount/source', () => {
    let detail = null;
    window.addEventListener('drv:quote-updated', (e) => (detail = e.detail), { once: true });
    updateQuote((draft) => addSlot(draft, DEFAULT_ROOM_ID, { product_id: 101, target_m2: 5 }), 'card');
    expect(detail).toMatchObject({ totalSlots: 1, uniqueSkus: 1, roomCount: 1, source: 'card' });
  });

  it(`giới hạn tối đa ${MAX_SLOTS} hạng mục trên toàn phiếu`, () => {
    let full = false;
    updateQuote((draft) => {
      for (let i = 1; i <= MAX_SLOTS + 3; i += 1) {
        full = addSlot(draft, DEFAULT_ROOM_ID, { product_id: i, input_mode: 'm2', target_m2: 1 }).full || full;
      }
    });
    expect(full).toBe(true);
    expect(getStats(readState()).totalSlots).toBe(MAX_SLOTS);
  });

  it('không lưu giá bán vào localStorage (luôn hydrate từ API)', () => {
    updateQuote((draft) => addSlot(draft, DEFAULT_ROOM_ID, { product_id: 101, target_m2: 5, price_m2: 1000, line_total: 99 }));
    const raw = window.localStorage.getItem(QUOTE_STORAGE_KEY);
    expect(raw).not.toContain('line_total');
    expect(raw).not.toContain('1000');
  });
});

describe('Migrate v1 -> v2', () => {
  it('chuyển drv_quote_items_v1 vào phòng "Hạng mục chung" một lần rồi xóa khóa cũ', () => {
    window.localStorage.setItem(
      LEGACY_STORAGE_KEY,
      JSON.stringify([
        { product_id: 101, sku: 'DRV-8801M', area_m2: 25 },
        { product_id: 0, sku: 'RAC' },
      ]),
    );
    const state = readState();
    expect(state.rooms).toHaveLength(1);
    expect(state.rooms[0]).toMatchObject({ id: DEFAULT_ROOM_ID, name: 'Hạng mục chung' });
    expect(state.rooms[0].slots).toHaveLength(1);
    expect(state.rooms[0].slots[0]).toMatchObject({ product_id: 101, target_m2: 25, input_mode: 'm2' });
    expect(window.localStorage.getItem(LEGACY_STORAGE_KEY)).toBeNull();
    expect(JSON.parse(window.localStorage.getItem(QUOTE_STORAGE_KEY)).rooms).toHaveLength(1);
  });

  it('chuyển bản v2 dạng phẳng `{items: []}` (trước Step 8) sang schema phòng', () => {
    window.localStorage.setItem(QUOTE_STORAGE_KEY, JSON.stringify({ version: 2, updated_at: Date.now(), items: [{ product_id: 105, sku: 'DRV-SLAB01', area_m2: 8.5 }] }));
    const state = readState();
    expect(state.rooms[0].slots[0]).toMatchObject({ product_id: 105, target_m2: 8.5 });
  });

  it('bỏ qua bản nháp quá TTL 30 ngày', () => {
    window.localStorage.setItem(QUOTE_STORAGE_KEY, JSON.stringify({ version: 2, updated_at: Date.now() - 31 * 86400 * 1000, rooms: [{ id: DEFAULT_ROOM_ID, name: 'x', slots: [{ product_id: 1, target_m2: 2 }] }] }));
    expect(readState().rooms).toHaveLength(0);
  });
});

describe('Link chia sẻ ?quote= (Base64URL, XSS guard)', () => {
  it('mã hóa / giải mã khứ hồi giữ đúng phòng, hạng mục, chế độ nhập và hao hụt', () => {
    const { state } = updateQuote((draft) => {
      draft.waste_pct = 10;
      draft.project_name = 'Nhà phố 3 tầng';
      const room = addRoom(draft, 'Phòng khách & Bếp');
      addSlot(draft, room.id, { product_id: 101, role: 'Lát nền chính', input_mode: 'm2', target_m2: 32.5 });
      addSlot(draft, room.id, { product_id: 105, role: 'Ốp vách Tivi', input_mode: 'vien', pieces: 4 });
    });
    const param = encodeShare(state);
    expect(param).toMatch(/^[\w-]+$/);

    const parsed = parseShareParam(param);
    expect(parsed.waste_pct).toBe(10);
    expect(parsed.project_name).toBe('Nhà phố 3 tầng');
    expect(parsed.rooms[0].name).toBe('Phòng khách & Bếp');
    expect(parsed.rooms[0].slots[0]).toMatchObject({ product_id: 101, role: 'Lát nền chính', input_mode: 'm2', target_m2: 32.5 });
    expect(parsed.rooms[0].slots[1]).toMatchObject({ product_id: 105, role: 'Ốp vách Tivi', input_mode: 'vien', pieces: 4 });
  });

  it('vô hiệu chuỗi độc hại trong tên phòng và từ chối chuỗi sai định dạng / quá dài', () => {
    const evil = `v2|w=7|p=x|r=${encodeURIComponent('<script>alert(1)</script>')}:101.0.m5`;
    const b64 = btoa(evil).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const parsed = parseShareParam(b64);
    expect(parsed.rooms[0].name).not.toMatch(/[<>]/);
    expect(parsed.waste_pct).toBe(5); // w=7 không hợp lệ -> 5

    expect(parseShareParam('a'.repeat(SHARE_MAX_LENGTH + 1))).toBeNull();
    expect(parseShareParam('!!!')).toBeNull();
    expect(parseShareParam(btoa('v1|w=5').replace(/=+$/, ''))).toBeNull();
  });

  it('cắt tối đa 30 dòng khi link bị sửa tay vượt giới hạn', () => {
    const tokens = Array.from({ length: 50 }, (_, i) => `${i + 1}.0.m1`).join(',');
    const b64 = btoa(`v2|w=5|p=|r=A:${tokens}`).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    expect(getStats(parseShareParam(b64)).totalSlots).toBe(MAX_SLOTS);
  });

  it('gộp phiếu chia sẻ vào phiếu hiện tại thay vì ghi đè', () => {
    const current = updateQuote((d) => addSlot(d, DEFAULT_ROOM_ID, { product_id: 101, target_m2: 5 })).state;
    const incoming = parseShareParam(btoa('v2|w=5|p=|r=Sanh:105.0.p2').replace(/=+$/, ''));
    const merged = mergeStates(current, incoming);
    expect(getStats(merged)).toMatchObject({ totalSlots: 2, roomCount: 2 });
  });
});

describe('Tính toán BOQ trộn priced + quote_only (Q-2, Q-3, Q-4)', () => {
  it('computeSlot: m² cộng hao hụt -> chẵn thùng; viên lẻ cho round_mode=piece', () => {
    const calcPriced = { areaPiece: 0.64, piecesPerBox: 3, kgPerBox: 42, priceM2: 385000, roundMode: 'box', qtyMin: 3 };
    const r = computeSlot(calcPriced, { inputMode: 'm2', targetM2: 42 }, 5);
    // 42 × 1,05 = 44,1 m² / 1,92 = 22,97 -> 23 thùng = 69 viên = 44,16 m²
    expect(r).toMatchObject({ pieces: 69, fullBoxes: 23, loosePieces: 0, actualM2: 44.16, totalVnd: 69 * 246400 });

    const slab = computeSlot({ areaPiece: 2.88, piecesPerBox: 1, kgPerBox: 68, priceM2: 0, roundMode: 'piece', qtyMin: 1, unitLabel: 'tấm' }, { inputMode: 'vien', pieces: 4 }, 10);
    expect(slab).toMatchObject({ pieces: 4, actualM2: 11.52, totalKg: 272, totalVnd: 0, isQuote: true });

    const boxes = computeSlot(calcPriced, { inputMode: 'thung', pieces: 30 }, 10);
    expect(boxes.pieces).toBe(30); // giữ 10 thùng khách chỉ định, không cộng hao hụt
  });

  it('SP quote vẫn cộng m²/thùng/kg nhưng không làm sai tạm tính; chiết khấu 5% từ 30 m²', () => {
    const state = updateQuote((draft) => {
      addSlot(draft, DEFAULT_ROOM_ID, { product_id: 101, input_mode: 'm2', target_m2: 42 });
      addSlot(draft, DEFAULT_ROOM_ID, { product_id: 105, role: 'Ốp tường', input_mode: 'vien', pieces: 4 });
    }).state;
    const { totals } = computeQuote(state, products, {});
    expect(totals.total_m2).toBe(55.68);
    expect(totals.subtotal_vnd).toBe(69 * 246400);
    expect(totals.priced_count).toBe(1);
    expect(totals.quote_count).toBe(1);
    expect(totals.discount_rate).toBe(5);
    expect(totals.grand_est_vnd).toBe(69 * 246400 - Math.round((69 * 246400 * 5) / 100));
  });

  it('đổi hao hụt 0/5/10% tính lại số viên dòng nhập theo m²', () => {
    const base = updateQuote((d) => addSlot(d, DEFAULT_ROOM_ID, { product_id: 101, input_mode: 'm2', target_m2: 40 })).state;
    const pieces = [0, 5, 10].map((w) => computeQuote({ ...base, waste_pct: w }, products, {}).rooms[0].lines[0].res.pieces);
    expect(pieces).toEqual([63, 66, 69]);
  });

  it('boq-to-cart: gộp cùng SP giữa các phòng, bỏ qua mẫu quote_only, không áp chiết khấu bậc', () => {
    const state = updateQuote((draft) => {
      addSlot(draft, DEFAULT_ROOM_ID, { product_id: 101, input_mode: 'm2', target_m2: 20 });
      const room = addRoom(draft, 'Sinh hoạt chung');
      addSlot(draft, room.id, { product_id: 101, input_mode: 'm2', target_m2: 10 });
      addSlot(draft, room.id, { product_id: 105, input_mode: 'vien', pieces: 2 });
    }).state;
    const { ready, skipped } = aggregateForCart(computeQuote(state, products, {}), state.waste_pct);
    expect(ready).toHaveLength(1);
    expect(ready[0].id).toBe(101);
    expect(ready[0].quantity).toBe(33 + 18);
    expect(ready[0].drv_cart_data).toMatchObject({ drv_m2_input: '30', drv_waste_pct: '5', drv_input_unit: 'm2' });
    expect(ready[0]).not.toHaveProperty('price');
    expect(skipped).toBe(1);
  });
});

describe('CSV UTF-8 BOM chống Formula Injection (Q-11)', () => {
  it('vô hiệu ô bắt đầu bằng = + - @ và bọc ngoặc kép', () => {
    expect(sanitizeCsvCell("=CMD|' /C calc'!A0")).toBe(`"'=CMD|' /C calc'!A0"`);
    expect(sanitizeCsvCell('@SUM(1)')).toBe(`"'@SUM(1)"`);
    expect(sanitizeCsvCell('Phòng "khách"\nTầng 1')).toBe('"Phòng ""khách"" Tầng 1"');
  });

  it('file bắt đầu bằng BOM và giữ tiếng Việt có dấu', () => {
    const state = updateQuote((draft) => {
      draft.project_name = '=CMD|calc';
      addSlot(draft, DEFAULT_ROOM_ID, { product_id: 105, input_mode: 'vien', pieces: 2 });
    }).state;
    const csv = buildCsv(state, computeQuote(state, products, {}));
    expect(csv.charCodeAt(0)).toBe(0xfeff);
    expect(csv).toContain(`"'=CMD|calc"`);
    expect(csv).toContain('"Liên hệ báo giá"');
    expect(csv).toContain('"Chờ báo giá"');
  });
});
