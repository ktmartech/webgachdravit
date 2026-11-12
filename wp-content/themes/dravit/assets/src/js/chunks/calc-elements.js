import {
  calcCartLine,
  calcFromBoxes,
  calcFromM2,
  calcFromPieces,
  pieceAreaM2,
} from '../utils/calc.js';
import { formatDecimalVn, formatVnd, parseNumberVn } from '../utils/format.js';
import { qs } from '../utils/dom.js';

class DrvUnitCalcElement extends HTMLElement {
  connectedCallback() {
    if (this._initialized) {
      return;
    }
    this._initialized = true;
    this._mode = this.dataset.defaultUnit === 'vien' ? 'pieces' : 'm2';

    this.addEventListener('input', (event) => {
      if (event.target?.matches('[data-calc-pieces]')) {
        this._mode = 'pieces';
      } else if (event.target?.matches('[data-calc-boxes]')) {
        this._mode = 'boxes';
      } else if (event.target?.matches('[data-calc-m2]')) {
        this._mode = 'm2';
      }
      this.recalculate();
    });

    this.addEventListener('change', () => this.recalculate());

    this.addEventListener('click', (event) => {
      const stepBtn = event.target.closest('[data-calc-step]');
      if (!stepBtn) {
        return;
      }
      const delta = Number.parseFloat(stepBtn.dataset.calcStep || '0') || 0;
      const targetKey = stepBtn.dataset.calcTarget || 'm2';
      if (targetKey === 'pieces') {
        const pInput = qs('[data-calc-pieces]', this);
        if (pInput) {
          this._mode = 'pieces';
          const min = Math.max(1, Number.parseInt(this.dataset.qtyMin || '1', 10) || 1);
          const cur = Math.max(min, Number.parseInt(pInput.value || String(min), 10) || min);
          pInput.value = String(Math.max(min, cur + Math.round(delta)));
          this.recalculate();
        }
      } else if (targetKey === 'boxes') {
        const bInput = qs('[data-calc-boxes]', this);
        if (bInput) {
          this._mode = 'boxes';
          const cur = Math.max(1, Number.parseInt(bInput.value || '1', 10) || 1);
          bInput.value = String(Math.max(1, cur + Math.round(delta)));
          this.recalculate();
        }
      } else {
        const mInput = qs('[data-calc-m2]', this);
        if (mInput) {
          this._mode = 'm2';
          const cur = Math.max(0.5, parseNumberVn(mInput.value || '1'));
          mInput.value = String(Math.max(0.5, Number((cur + delta).toFixed(2))));
          this.recalculate();
        }
      }
    });

    this.recalculate();
  }

  recalculate() {
    const pieceW = parseNumberVn(this.dataset.pieceW || '0');
    const pieceH = parseNumberVn(this.dataset.pieceH || '0');
    const rawArea = parseNumberVn(this.dataset.areaPiece || '0');
    const areaPiece = rawArea > 0 ? rawArea : pieceAreaM2(pieceW, pieceH);
    const piecesPerBox = Math.max(1, Math.round(parseNumberVn(this.dataset.piecesPerBox || '1')));
    const priceM2 = Math.max(0, Math.round(parseNumberVn(this.dataset.priceM2 || '0')));
    const kgPerBox = Math.max(0, parseNumberVn(this.dataset.weightPerBox || '0'));
    const qtyMin = Math.max(1, Math.round(parseNumberVn(this.dataset.qtyMin || '1')));
    const unitLabel = this.dataset.unitLabel || 'viên';
    const buyUnits = (this.dataset.buyUnits || 'm2,thung')
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);

    const m2Input = qs('[data-calc-m2]', this);
    const boxesInput = qs('[data-calc-boxes]', this);
    const piecesInput = qs('[data-calc-pieces]', this);
    const wasteCheck = qs('[data-calc-waste]', this);
    const roundCheck = qs('[data-calc-round-box]', this);

    const hasWaste = wasteCheck ? wasteCheck.checked : true;
    const wastePct = hasWaste ? 5 : 0;
    const allowPiece = buyUnits.includes('vien');
    const forceBox = !allowPiece || (roundCheck ? roundCheck.checked : this.dataset.roundMode !== 'piece');
    const roundMode = forceBox ? 'box' : 'piece';

    const baseParams = {
      areaPiece,
      piecesPerBox,
      roundMode,
      qtyMin,
      priceM2,
      kgPerBox,
      unitLabel,
    };

    let res;
    if (this._mode === 'pieces' && piecesInput) {
      res = calcFromPieces({
        ...baseParams,
        pieces: Math.max(1, parseNumberVn(piecesInput.value || '1')),
      });
      if (m2Input && document.activeElement !== m2Input) {
        m2Input.value = String(Number(res.actualM2.toFixed(2)));
      }
      if (boxesInput && document.activeElement !== boxesInput) {
        boxesInput.value = String(Math.max(1, res.fullBoxes));
      }
      if (forceBox && document.activeElement !== piecesInput) {
        piecesInput.value = String(res.pieces);
      }
    } else if (this._mode === 'boxes' && boxesInput) {
      res = calcFromBoxes({
        ...baseParams,
        boxes: Math.max(1, parseNumberVn(boxesInput.value || '1')),
      });
      if (m2Input && document.activeElement !== m2Input) {
        m2Input.value = String(Number(res.actualM2.toFixed(2)));
      }
      if (piecesInput && document.activeElement !== piecesInput) {
        piecesInput.value = String(res.pieces);
      }
    } else {
      const baseM2 = Math.max(0.5, parseNumberVn(m2Input ? m2Input.value : '10'));
      res = calcFromM2({
        ...baseParams,
        areaM2: baseM2,
        wastePct,
      });
      if (piecesInput && document.activeElement !== piecesInput) {
        piecesInput.value = String(res.pieces);
      }
      if (boxesInput && document.activeElement !== boxesInput) {
        boxesInput.value = String(Math.max(1, res.fullBoxes));
      }
    }

    const rawEl = qs('[data-calc-raw-need]', this);
    if (rawEl) {
      rawEl.textContent = hasWaste
        ? `Diện tích tính cả 5% hao hụt cắt gạch: ${formatDecimalVn(res.needM2, 2)} m²`
        : `Diện tích tịnh không cộng hao hụt: ${formatDecimalVn(res.needM2, 2)} m²`;
    }

    const sumEl = qs('[data-calc-summary]', this);
    if (sumEl) {
      const kgPart = res.totalKg > 0 ? ` · ${formatDecimalVn(res.totalKg, 0)} kg` : '';
      sumEl.textContent = `${res.pieces} ${unitLabel} = ${res.boxText} (${formatDecimalVn(res.actualM2, 2)} m²${kgPart})`;
    }

    const totalEl = qs('[data-calc-total]', this);
    if (totalEl) {
      totalEl.textContent = priceM2 > 0 ? formatVnd(res.totalVnd) : 'Liên hệ báo giá';
    }

    this.dispatchEvent(
      new CustomEvent('drv:calc-change', {
        bubbles: true,
        detail: res,
      }),
    );
  }
}

class DrvQtyInputElement extends HTMLElement {
  connectedCallback() {
    if (this._initialized) {
      return;
    }
    this._initialized = true;
    this.addEventListener('click', (event) => {
      const input = qs('input[type="number"]', this);
      if (!input) {
        return;
      }
      const stepBtn = event.target.closest('[data-qty-step]');
      if (stepBtn) {
        const delta = Number.parseInt(stepBtn.dataset.qtyStep || '0', 10);
        const min = Number.parseInt(input.min || '1', 10) || 1;
        input.value = String(Math.max(min, (Number.parseInt(input.value || '1', 10) || 1) + delta));
        this.syncRow(input);
      }
      const boxBtn = event.target.closest('[data-qty-add-box]');
      if (boxBtn) {
        const perBox = Math.max(1, Number.parseInt(this.dataset.piecesPerBox || '1', 10));
        input.value = String((Number.parseInt(input.value || '0', 10) || 0) + perBox);
        this.syncRow(input);
      }
    });
    this.addEventListener('change', () => {
      const input = qs('input[type="number"]', this);
      if (input) {
        this.syncRow(input);
      }
    });
  }

  syncRow(input) {
    const min = Math.max(1, Number.parseInt(input.min || '1', 10) || 1);
    const step = Math.max(1, Number.parseInt(input.step || '1', 10) || 1);
    let qty = Math.max(min, Number.parseInt(input.value || String(min), 10) || min);
    if (step > 1 && qty % step !== 0) {
      qty = Math.ceil(qty / step) * step;
    }
    input.value = String(qty);

    const priceUnit = parseNumberVn(this.dataset.priceUnit || '0');
    const areaPiece = parseNumberVn(this.dataset.areaPiece || '0');
    const perBox = Math.max(1, Number.parseInt(this.dataset.piecesPerBox || '1', 10));
    const kgPerBox = parseNumberVn(this.dataset.kgPerBox || '0');
    const weightPiece = parseNumberVn(this.dataset.weightPiece || '0');
    const unitLabel = this.dataset.unitLabel || 'viên';
    const row = this.closest('[data-cart-row]');
    if (!row) {
      return;
    }
    const subtotalEl = qs('[data-row-subtotal]', row);
    if (subtotalEl && priceUnit > 0) {
      subtotalEl.textContent = formatVnd(qty * priceUnit);
    }
    const convEl = qs('[data-row-conversion]', row);
    if (convEl) {
      if (this.dataset.model === 'area_pack' && areaPiece > 0) {
        const line = calcCartLine({
          pieces: qty,
          areaPiece,
          piecesPerBox: perBox,
          kgPerBox: kgPerBox > 0 ? kgPerBox : weightPiece * perBox,
          unitLabel,
        });
        const m2 = formatDecimalVn(line.actualM2, 2);
        const kg = line.totalKg > 0 ? ` · ${formatDecimalVn(line.totalKg, 0)} kg` : '';
        convEl.textContent = `= ${line.boxText} · ${m2} m²${kg}`;
      } else {
        convEl.textContent = `= ${qty} ${unitLabel} · ${formatDecimalVn(qty * weightPiece, 1)} kg`;
      }
    }
  }
}

export function registerCalcElements() {
  if (typeof customElements === 'undefined') {
    return;
  }
  if (!customElements.get('drv-unit-calc')) {
    customElements.define('drv-unit-calc', DrvUnitCalcElement);
  }
  if (!customElements.get('drv-qty-input')) {
    customElements.define('drv-qty-input', DrvQtyInputElement);
  }
}

registerCalcElements();
