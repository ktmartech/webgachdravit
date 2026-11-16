import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initCore, resetCoreForTests } from '../../assets/src/js/core.js';
import { registerCalcElements } from '../../assets/src/js/chunks/calc-elements.js';
import { QUOTE_STORAGE_KEY } from '../../assets/src/js/utils/storage.js';

/**
 * Step 7 · <drv-unit-calc> DOM (D-24): U-4..U-7, U-11..U-16.
 * SKU chuẩn U-1: 80×80, 3 viên/thùng (1,92 m²), 385.000đ/m² → 246.400đ/viên, 42 kg/thùng.
 */

const UNIT_NAMES = { m2: 'Theo m²', thung: 'Theo thùng', vien: 'Theo viên' };

function mountPdp({
  buyUnits = ['m2', 'thung'],
  defaultUnit = 'm2',
  roundMode = 'box',
  qtyMin = 3,
  priceState = 'priced',
  priceM2 = 385000,
  pricePiece = 246400,
  pieceW = 800,
  pieceH = 800,
  piecesPerBox = 3,
  unitLabel = 'viên',
  wasteDefault = 5,
  m2 = 10,
} = {}) {
  const tabs = buyUnits
    .map(
      (u) =>
        `<button type="button" role="tab" class="drv-calc__tab" data-calc-unit-tab="${u}">${UNIT_NAMES[u]}</button>`,
    )
    .join('');

  const panel = (u) => {
    if (!buyUnits.includes(u)) {
      return '';
    }
    const attr = { m2: 'data-calc-m2', thung: 'data-calc-boxes', vien: 'data-calc-pieces' }[u];
    const val = u === 'm2' ? m2 : u === 'thung' ? 1 : qtyMin;
    const step = u === 'm2' ? 1 : u === 'vien' ? (roundMode === 'box' ? piecesPerBox : 1) : 1;
    return `
      <div data-calc-unit-panel="${u}" role="tabpanel">
        <button type="button" data-calc-step="-${step}" data-calc-target="${u}" id="dec-${u}">-</button>
        <input type="number" data-calc-input="${u}" ${attr} value="${val}" id="in-${u}">
        <button type="button" data-calc-step="${step}" data-calc-target="${u}" id="inc-${u}">+</button>
        <strong data-calc-inline-pieces></strong>
        ${u === 'm2' ? '<button type="button" data-calc-preset="25" id="preset-25">25m²</button>' : ''}
      </div>`;
  };

  document.body.innerHTML = `
    <div data-drv-pdp-root data-product-id="101">
      <drv-unit-calc
        data-product-id="101"
        data-sku="DRV-TEST80"
        data-price-state="${priceState}"
        data-price-m2="${priceM2}"
        data-price-piece="${pricePiece}"
        data-piece-w="${pieceW}"
        data-piece-h="${pieceH}"
        data-pieces-per-box="${piecesPerBox}"
        data-kg-per-box="42"
        data-unit-label="${unitLabel}"
        data-buy-units='${JSON.stringify(buyUnits)}'
        data-default-unit="${defaultUnit}"
        data-round-mode="${roundMode}"
        data-qty-min="${qtyMin}"
        data-waste-default="${wasteDefault}">
        <div role="tablist">${tabs}</div>
        ${panel('m2')}${panel('thung')}${panel('vien')}
        <div data-calc-waste-wrap>
          <select data-calc-waste id="waste">
            <option value="0" ${wasteDefault === 0 ? 'selected' : ''}>0%</option>
            <option value="5" ${wasteDefault === 5 ? 'selected' : ''}>5%</option>
            <option value="10" ${wasteDefault === 10 ? 'selected' : ''}>10%</option>
          </select>
        </div>
        <input type="checkbox" data-calc-round-box id="round" checked>
        <strong data-calc-summary>0 viên = <span data-calc-out="boxes"></span>
          (<span data-calc-out="actual-m2"></span> · <span data-calc-out="kg"></span>)</strong>
        <span data-calc-out="raw-need"></span>
        ${priceState === 'quote_only' ? '<strong data-calc-boxes-quote></strong>' : '<output data-calc-total id="total">0đ</output>'}
      </drv-unit-calc>
      <form id="drv-pdp-buy-form" method="post">
        <input type="hidden" name="quantity" value="0" data-drv-qty-pieces>
        <input type="hidden" name="drv_m2_input" value="0" data-drv-m2-input>
        <input type="hidden" name="drv_waste_pct" value="0" data-drv-waste-input>
        <input type="hidden" name="drv_input_unit" value="" data-drv-unit-input>
        <button type="submit" name="drv_buy_now" value="1">Mua ngay</button>
      </form>
      <button type="button" id="quote-add" data-drv-action="quote-add" data-pdp-quote-add
              data-drv-product-id="101" data-drv-sku="DRV-TEST80" data-drv-title="Gạch test 80x80">
        + Thêm vào DS Báo giá
      </button>
    </div>`;

  return document.querySelector('drv-unit-calc');
}

const $ = (sel) => document.querySelector(sel);
const qtyHidden = () => $('input[name="quantity"]');
const norm = (s) => String(s || '').replace(/\s+/g, ' ').trim();

function typeInto(input, value) {
  input.value = String(value);
  input.dispatchEvent(new Event('input', { bubbles: true }));
}

describe('Step 7 <drv-unit-calc> DOM (D-24)', () => {
  beforeEach(() => {
    resetCoreForTests();
    localStorage.clear();
    window.matchMedia = vi.fn().mockReturnValue({ matches: true }); // prefers-reduced-motion → no anime import
    registerCalcElements();
    initCore();
  });

  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('U-4: 28 m² + 5% hao hụt, bắt buộc chẵn thùng → 48 viên = 16 thùng = 30,72 m², 11.827.200đ', () => {
    const calc = mountPdp({ m2: 28, wasteDefault: 5 });
    const detail = calc.recalculate();

    expect(detail.pieces).toBe(48);
    expect(detail.fullBoxes).toBe(16);
    expect(detail.actualM2).toBeCloseTo(30.72, 6);
    expect(detail.totalVnd).toBe(11827200);
    expect(norm($('#total').textContent)).toContain('11.827.200');
    expect(norm($('[data-calc-out="boxes"]').textContent)).toBe('16 thùng');
    expect(qtyHidden().value).toBe('48');
  });

  it('U-5: round_mode=piece + bỏ làm tròn → 46 viên = 15 thùng + 1 viên lẻ = 29,44 m²', () => {
    const calc = mountPdp({ m2: 28, wasteDefault: 5, roundMode: 'piece', buyUnits: ['m2', 'thung', 'vien'], qtyMin: 1 });
    const round = $('#round');
    expect(round.disabled).toBe(false);
    round.checked = false;
    const detail = calc.recalculate();

    expect(detail.pieces).toBe(46);
    expect(detail.boxText).toBe('15 thùng + 1 viên lẻ');
    expect(detail.actualM2).toBeCloseTo(29.44, 6);
    expect(qtyHidden().value).toBe('46');
  });

  it('U-6: nhập 10 m² (piece mode, 0% hao hụt, không chẵn thùng) → gửi server 16 viên số nguyên', () => {
    const calc = mountPdp({ m2: 10, wasteDefault: 0, roundMode: 'piece', buyUnits: ['m2', 'vien'], qtyMin: 1 });
    $('#round').checked = false;
    const detail = calc.recalculate();

    expect(detail.pieces).toBe(16);
    expect(detail.actualM2).toBeCloseTo(10.24, 6);
    expect(qtyHidden().value).toBe('16');
    expect(Number.isInteger(Number(qtyHidden().value))).toBe(true);
  });

  it('U-7: round_mode=box, nhập lẻ 4 viên → tự làm tròn lên 6 viên (2 thùng)', () => {
    mountPdp({ buyUnits: ['m2', 'thung', 'vien'], roundMode: 'box', defaultUnit: 'vien' });
    typeInto($('#in-vien'), 4);

    expect(qtyHidden().value).toBe('6');
    expect($('#round').checked).toBe(true);
    expect($('#round').disabled).toBe(true);
  });

  it('U-11: SP quote_only vẫn quy đổi thùng/m² nhưng không render tổng tiền, quantity vẫn là số viên', () => {
    const calc = mountPdp({ priceState: 'quote_only', priceM2: 0, pricePiece: 0, m2: 28 });
    const detail = calc.recalculate();

    expect(detail.pieces).toBe(48);
    expect(detail.totalVnd).toBe(0);
    expect($('#total')).toBeNull();
    expect(norm($('[data-calc-boxes-quote]').textContent)).toBe('16 thùng');
    expect(document.body.textContent).not.toMatch(/\b0đ\b/);
  });

  it('U-12: buy_units=[m2,thung] ẩn tab Viên; 10 m² → 18 viên (6 thùng); 1,92 m² @0% → đúng 3 viên', () => {
    const calc = mountPdp({ buyUnits: ['m2', 'thung'], m2: 10, wasteDefault: 0 });

    expect($('[data-calc-unit-tab="vien"]')).toBeNull();
    expect(qtyHidden().value).toBe('18');
    expect(calc.recalculate().actualM2).toBeCloseTo(11.52, 6);

    typeInto($('#in-m2'), 1.92);
    expect(qtyHidden().value).toBe('3');
    expect(norm($('[data-calc-out="boxes"]').textContent)).toBe('1 thùng');
  });

  it('U-13: 3 tab, mặc định Viên, round_mode=piece cho mua lẻ 4 viên = 1 thùng + 1 viên lẻ', () => {
    mountPdp({ buyUnits: ['m2', 'thung', 'vien'], roundMode: 'piece', defaultUnit: 'vien', qtyMin: 1 });

    const tabs = document.querySelectorAll('[data-calc-unit-tab]');
    expect(tabs).toHaveLength(3);
    expect($('[data-calc-unit-tab="vien"]').getAttribute('aria-selected')).toBe('true');
    expect($('[data-calc-unit-panel="vien"]').hidden).toBe(false);
    expect($('[data-calc-unit-panel="m2"]').hidden).toBe(true);
    expect($('[data-calc-waste-wrap]').hidden).toBe(true);

    $('#round').checked = false;
    typeInto($('#in-vien'), 4);
    expect(qtyHidden().value).toBe('4');
    expect(norm($('[data-calc-out="boxes"]').textContent)).toBe('1 thùng + 1 viên lẻ');

    $('[data-calc-unit-tab="m2"]').click();
    expect($('[data-calc-unit-tab="m2"]').getAttribute('aria-selected')).toBe('true');
    expect($('[data-calc-waste-wrap]').hidden).toBe(false);
  });

  it('U-16: chỉ 1 đơn vị [vien] (nhãn "tấm") ẩn tablist; qty_min=6 khóa không cho giảm dưới 6', () => {
    mountPdp({ buyUnits: ['vien'], defaultUnit: 'vien', roundMode: 'piece', qtyMin: 6, unitLabel: 'tấm', piecesPerBox: 2 });

    expect($('[role="tablist"]').hidden).toBe(true);
    $('#round').checked = false;
    $('#in-vien').value = '6';
    $('#dec-vien').click();
    $('#dec-vien').click();
    expect(Number($('#in-vien').value)).toBeGreaterThanOrEqual(6);
    expect(Number(qtyHidden().value)).toBeGreaterThanOrEqual(6);

    typeInto($('#in-vien'), 2);
    expect(Number(qtyHidden().value)).toBeGreaterThanOrEqual(6);
  });

  it('đồng bộ hidden inputs form POST (m², hao hụt, đơn vị) cho luồng tắt JS / Mua ngay', () => {
    mountPdp({ m2: 28, wasteDefault: 5 });
    $('#waste').value = '10';
    $('#waste').dispatchEvent(new Event('change', { bubbles: true }));

    expect($('[data-drv-m2-input]').value).toBe('28');
    expect($('[data-drv-waste-input]').value).toBe('10');
    expect($('[data-drv-unit-input]').value).toBe('m2');
    // 28 × 1,1 = 30,8 m² → 48,125 viên → chẵn thùng 51 viên
    expect(qtyHidden().value).toBe('51');
  });

  it('truyền đúng target_m2 vừa tính sang QuoteStore (drv_quote_v2) khi bấm Thêm vào DS Báo giá', async () => {
    mountPdp({ m2: 10 });
    $('#preset-25').click();
    expect($('#quote-add').dataset.drvM2).toBe('25');

    $('#quote-add').click();
    // Step 8: schema đa phòng `rooms[].slots[]` (docs/17 §2.2), PDP giữ đúng m² khách nhập (Q-01).
    await vi.waitFor(() => {
      const state = JSON.parse(localStorage.getItem(QUOTE_STORAGE_KEY) || 'null');
      expect(state?.rooms?.[0]?.slots).toHaveLength(1);
    });
    const state = JSON.parse(localStorage.getItem(QUOTE_STORAGE_KEY));
    expect(state.rooms[0].id).toBe('room-general');
    expect(state.rooms[0].slots[0]).toMatchObject({ product_id: 101, sku: 'DRV-TEST80', input_mode: 'm2', target_m2: 25 });
  });

  it('đổi tab m² → Thùng → m² (không sửa gì) giữ nguyên ô m² và số viên, không cộng hao hụt 2 lần', () => {
    mountPdp({ m2: 28, wasteDefault: 5 });
    expect(qtyHidden().value).toBe('48');

    $('[data-calc-unit-tab="thung"]').click();
    expect($('#in-thung').value).toBe('16');
    expect(qtyHidden().value).toBe('48');

    $('[data-calc-unit-tab="m2"]').click();
    expect($('#in-m2').value).toBe('28');
    expect(qtyHidden().value).toBe('48');
  });

  it('sửa số thùng ở tab Thùng rồi quay lại m² → quy ngược diện tích tịnh, giữ đúng số viên vừa chọn', () => {
    mountPdp({ m2: 28, wasteDefault: 5 });
    $('[data-calc-unit-tab="thung"]').click();
    typeInto($('#in-thung'), 20);
    expect(qtyHidden().value).toBe('60');

    $('[data-calc-unit-tab="m2"]').click();
    // 20 thùng = 38,4 m² thực nhận → /1,05 = 36,57 m² tịnh → vẫn 60 viên (20 thùng)
    expect(Number($('#in-m2').value)).toBeCloseTo(36.57, 2);
    expect(qtyHidden().value).toBe('60');
  });

  it('ghi chú dạng mockup: "Thực nhận là X m² (N viên = M thùng · ≈ kg)" cập nhật live', () => {
    mountPdp({ m2: 28, wasteDefault: 5 });
    const note = document.createElement('p');
    note.innerHTML =
      '<strong data-calc-out="actual-m2"></strong> (<span data-calc-out="pieces-boxes"></span> · <span data-calc-out="kg"></span>)';
    const calc = $('drv-unit-calc');
    calc.querySelector('[data-calc-summary]').remove();
    calc.appendChild(note);
    calc.recalculate();

    expect(norm($('[data-calc-out="actual-m2"]').textContent)).toBe('30,72 m²');
    expect(norm($('[data-calc-out="pieces-boxes"]').textContent)).toBe('48 viên = 16 thùng');
    expect(norm($('[data-calc-out="kg"]').textContent)).toBe('≈ 672 kg');
  });

  it('P-9: thiếu kích thước → khóa máy tính & nút mua, không fallback dữ liệu giả DRV-8801M', () => {
    const errors = [];
    document.addEventListener('drv:error', (e) => errors.push(e.detail), { once: true });
    const calc = mountPdp({ pieceW: 0, pieceH: 0 });

    expect(calc.classList.contains('is-invalid')).toBe(true);
    expect($('#in-m2').disabled).toBe(true);
    expect($('#drv-pdp-buy-form button[type="submit"]').disabled).toBe(true);
    expect(errors[0]?.reason).toBe('missing_dimensions');
    expect(document.body.innerHTML).not.toContain('8801');
    expect(qtyHidden().value).toBe('0');
  });
});
