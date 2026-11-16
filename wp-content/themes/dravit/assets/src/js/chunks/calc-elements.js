import {
  calcCartLine,
  calcFromBoxes,
  calcFromM2,
  calcFromPieces,
  pieceAreaM2,
} from '../utils/calc.js';
import { formatDecimalVn, formatVnd, parseNumberVn } from '../utils/format.js';
import { prefersReducedMotion, qs, qsa } from '../utils/dom.js';

function parseBuyUnitsAttr(raw) {
  const str = String(raw || '').trim();
  if (str.startsWith('[')) {
    try {
      const parsed = JSON.parse(str);
      if (Array.isArray(parsed)) {
        const filtered = parsed
          .map((u) => String(u).trim().toLowerCase())
          .filter((u) => ['m2', 'thung', 'vien'].includes(u));
        if (filtered.length > 0) {
          return filtered;
        }
      }
    } catch {
      // Fall through to CSV parser
    }
  }

  const csv = str
    .split(',')
    .map((s) => s.trim().toLowerCase())
    .filter((u) => ['m2', 'thung', 'vien'].includes(u));

  return csv.length > 0 ? csv : ['m2', 'thung'];
}

export class DrvUnitCalcElement extends HTMLElement {
  connectedCallback() {
    if (this._mounted) {
      return;
    }
    this._mounted = true;
    this._abort = new AbortController();
    const { signal } = this._abort;

    if (!this._validateRequiredConfig()) {
      return;
    }

    const buyUnits = parseBuyUnitsAttr(this.dataset.buyUnits);
    const rawDefault = String(this.dataset.defaultUnit || 'm2').toLowerCase();
    this._activeUnit = buyUnits.includes(rawDefault) ? rawDefault : buyUnits[0];
    this._lastTotalVnd = null;

    this._syncTabsUi();

    this.addEventListener(
      'input',
      (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        if (target.matches('[data-calc-input="vien"], [data-calc-pieces]')) {
          this._activeUnit = 'vien';
          this._m2Stale = true;
        } else if (target.matches('[data-calc-input="thung"], [data-calc-boxes]')) {
          this._activeUnit = 'thung';
          this._m2Stale = true;
        } else if (target.matches('[data-calc-input="m2"], [data-calc-m2]')) {
          this._activeUnit = 'm2';
        }
        this.recalculate({ animateTotal: true });
      },
      { signal },
    );

    this.addEventListener(
      'change',
      () => {
        this.recalculate({ animateTotal: true });
      },
      { signal },
    );

    this.addEventListener(
      'click',
      (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
          return;
        }

        const unitTab = target.closest('[data-calc-unit-tab]');
        if (unitTab) {
          event.preventDefault();
          const nextUnit = String(unitTab.getAttribute('data-calc-unit-tab') || '').toLowerCase();
          if (['m2', 'thung', 'vien'].includes(nextUnit)) {
            this.switchUnit(nextUnit);
          }
          return;
        }

        const presetBtn = target.closest('[data-calc-preset]');
        if (presetBtn) {
          event.preventDefault();
          const presetVal = parseNumberVn(presetBtn.getAttribute('data-calc-preset') || '0');
          const m2Input = qs('[data-calc-input="m2"], [data-calc-m2]', this);
          if (presetVal > 0 && m2Input instanceof HTMLInputElement) {
            this._activeUnit = 'm2';
            m2Input.value = String(presetVal);
            this._syncTabsUi();
            this.recalculate({ animateTotal: true });
          }
          return;
        }

        const stepBtn = target.closest('[data-calc-step]');
        if (stepBtn) {
          event.preventDefault();
          this._applyStepDelta(stepBtn);
        }
      },
      { signal },
    );

    this.recalculate({ animateTotal: false });
  }

  disconnectedCallback() {
    this._abort?.abort();
    this._abort = null;
    this._mounted = false;
  }

  switchUnit(nextUnit) {
    const buyUnits = parseBuyUnitsAttr(this.dataset.buyUnits);
    if (!buyUnits.includes(nextUnit)) {
      return;
    }
    if (nextUnit === 'm2' && this._activeUnit !== 'm2' && this._m2Stale) {
      this._syncM2InputFromPieces();
    }
    this._m2Stale = false;
    this._activeUnit = nextUnit;
    this._syncTabsUi();
    this.recalculate({ animateTotal: false });
  }

  /**
   * Khách đã sửa số thùng/viên ở tab khác rồi quay về tab m²: quy ngược ra diện tích tịnh (trừ hao hụt),
   * làm tròn xuống 0,01 m² và hạ dần tới khi KHÔNG vượt số viên đang có (tránh nhảy 48 → 51 viên).
   */
  _syncM2InputFromPieces() {
    const m2Input = qs('[data-calc-input="m2"], [data-calc-m2]', this);
    const last = this._lastResult;
    if (!(m2Input instanceof HTMLInputElement) || !last || !(last.actualM2 > 0)) {
      return;
    }
    const wasteCtrl = qs('[data-calc-waste]', this);
    const wastePct = wasteCtrl instanceof HTMLSelectElement ? Math.max(0, Number.parseFloat(wasteCtrl.value) || 0) : 0;
    const params = this._calcBaseParams();
    let candidate = Math.floor((last.actualM2 / (1 + wastePct / 100)) * 100 + 1e-9) / 100;

    for (let i = 0; i < 20 && candidate > 0.5; i += 1) {
      const probe = calcFromM2({ ...params, areaM2: candidate, wastePct });
      if (probe.pieces <= last.pieces) {
        break;
      }
      candidate = Number((candidate - 0.01).toFixed(2));
    }
    m2Input.value = String(Math.max(0.5, candidate));
  }

  _calcBaseParams() {
    const pieceW = parseNumberVn(this.dataset.pieceW || '0');
    const pieceH = parseNumberVn(this.dataset.pieceH || '0');
    const rawArea = parseNumberVn(this.dataset.areaPiece || '0');
    const buyUnits = parseBuyUnitsAttr(this.dataset.buyUnits);
    const skuRoundMode = this.dataset.roundMode === 'piece' && buyUnits.includes('vien') ? 'piece' : 'box';
    const roundCheck = qs('[data-calc-round-box]', this);
    return {
      areaPiece: rawArea > 0 ? rawArea : pieceAreaM2(pieceW, pieceH),
      piecesPerBox: Math.max(1, Math.round(parseNumberVn(this.dataset.piecesPerBox || '1'))),
      qtyMin: Math.max(1, Math.round(parseNumberVn(this.dataset.qtyMin || '1'))),
      priceM2: 0,
      kgPerBox: 0,
      unitLabel: String(this.dataset.unitLabel || 'viên'),
      roundMode: skuRoundMode,
      forceRoundBox: skuRoundMode === 'box' || (roundCheck instanceof HTMLInputElement ? roundCheck.checked : true),
    };
  }

  _validateRequiredConfig() {
    const pieceW = parseNumberVn(this.dataset.pieceW || '0');
    const pieceH = parseNumberVn(this.dataset.pieceH || '0');
    const piecesPerBox = Number.parseInt(this.dataset.piecesPerBox || '0', 10) || 0;

    if (!(pieceW > 0 && pieceH > 0 && piecesPerBox >= 1)) {
      this.classList.add('is-invalid');
      for (const ctrl of qsa('input, select, button', this)) {
        ctrl.disabled = true;
      }
      for (const buyBtn of qsa('#drv-pdp-buy-form button[type="submit"], [data-drv-sticky-purchase] button')) {
        buyBtn.disabled = true;
      }

      const message = 'Thiếu thông số kích thước hoặc quy cách thùng bắt buộc trên <drv-unit-calc>.';
      window.Dravit?.reportError?.('unit-calc', message);
      this.dispatchEvent(
        new CustomEvent('drv:error', {
          bubbles: true,
          detail: { source: 'unit-calc', reason: 'missing_dimensions', message },
        }),
      );
      return false;
    }

    return true;
  }

  _syncTabsUi() {
    const buyUnits = parseBuyUnitsAttr(this.dataset.buyUnits);
    const tablist = qs('[role="tablist"]', this);
    if (tablist) {
      tablist.hidden = buyUnits.length <= 1;
    }

    for (const tab of qsa('[data-calc-unit-tab]', this)) {
      const u = String(tab.getAttribute('data-calc-unit-tab') || '').toLowerCase();
      const active = u === this._activeUnit;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.setAttribute('tabindex', active ? '0' : '-1');
    }

    for (const panel of qsa('[data-calc-unit-panel]', this)) {
      const u = String(panel.getAttribute('data-calc-unit-panel') || '').toLowerCase();
      panel.hidden = u !== this._activeUnit;
    }

    const wasteWrap = qs('[data-calc-waste-wrap]', this);
    if (wasteWrap) {
      wasteWrap.hidden = this._activeUnit !== 'm2';
    }
    const rawNeedEl = qs('[data-calc-out="raw-need"], [data-calc-raw-need]', this);
    if (rawNeedEl) {
      rawNeedEl.hidden = this._activeUnit !== 'm2';
    }
  }

  _applyStepDelta(stepBtn) {
    const delta = Number.parseFloat(stepBtn.getAttribute('data-calc-step') || '0') || 0;
    const rawTarget = String(stepBtn.getAttribute('data-calc-target') || this._activeUnit).toLowerCase();

    if (rawTarget === 'vien' || rawTarget === 'pieces') {
      const pInput = qs('[data-calc-input="vien"], [data-calc-pieces]', this);
      if (pInput instanceof HTMLInputElement) {
        this._activeUnit = 'vien';
        this._m2Stale = true;
        const min = Math.max(1, Number.parseInt(this.dataset.qtyMin || '1', 10) || 1);
        const cur = Math.max(min, Number.parseInt(pInput.value || String(min), 10) || min);
        pInput.value = String(Math.max(min, cur + Math.round(delta)));
        this._syncTabsUi();
        this.recalculate({ animateTotal: true });
      }
      return;
    }

    if (rawTarget === 'thung' || rawTarget === 'boxes') {
      const bInput = qs('[data-calc-input="thung"], [data-calc-boxes]', this);
      if (bInput instanceof HTMLInputElement) {
        this._activeUnit = 'thung';
        this._m2Stale = true;
        const cur = Math.max(1, Number.parseInt(bInput.value || '1', 10) || 1);
        bInput.value = String(Math.max(1, cur + Math.round(delta)));
        this._syncTabsUi();
        this.recalculate({ animateTotal: true });
      }
      return;
    }

    const mInput = qs('[data-calc-input="m2"], [data-calc-m2]', this);
    if (mInput instanceof HTMLInputElement) {
      this._activeUnit = 'm2';
      const cur = Math.max(0.5, parseNumberVn(mInput.value || '1'));
      mInput.value = String(Math.max(0.5, Number((cur + delta).toFixed(2))));
      this._syncTabsUi();
      this.recalculate({ animateTotal: true });
    }
  }

  recalculate({ animateTotal = false } = {}) {
    if (!this._validateRequiredConfig()) {
      return null;
    }

    const productId = Number.parseInt(this.dataset.productId || '0', 10) || 0;
    const sku = String(this.dataset.sku || '').trim();
    const pieceW = parseNumberVn(this.dataset.pieceW || '0');
    const pieceH = parseNumberVn(this.dataset.pieceH || '0');
    const rawArea = parseNumberVn(this.dataset.areaPiece || '0');
    const areaPiece = rawArea > 0 ? rawArea : pieceAreaM2(pieceW, pieceH);
    const piecesPerBox = Math.max(1, Math.round(parseNumberVn(this.dataset.piecesPerBox || '1')));
    const priceState = String(this.dataset.priceState || '').trim();
    const priceM2 = Math.max(0, Math.round(parseNumberVn(this.dataset.priceM2 || '0')));
    const isQuote = priceState === 'quote_only' || !(priceM2 > 0);
    const serverPricePiece = Math.max(0, Math.round(parseNumberVn(this.dataset.pricePiece || '0')));
    const kgPerBox = Math.max(
      0,
      parseNumberVn(this.dataset.kgPerBox || this.dataset.weightPerBox || '0'),
    );
    const qtyMin = Math.max(1, Math.round(parseNumberVn(this.dataset.qtyMin || '1')));
    const unitLabel = String(this.dataset.unitLabel || 'viên').trim() || 'viên';
    const buyUnits = parseBuyUnitsAttr(this.dataset.buyUnits);
    const skuRoundMode = this.dataset.roundMode === 'piece' && buyUnits.includes('vien') ? 'piece' : 'box';

    const m2Input = qs('[data-calc-input="m2"], [data-calc-m2]', this);
    const boxesInput = qs('[data-calc-input="thung"], [data-calc-boxes]', this);
    const piecesInput = qs('[data-calc-input="vien"], [data-calc-pieces]', this);
    const wasteCtrl = qs('[data-calc-waste]', this);
    const roundCheck = qs('[data-calc-round-box]', this);

    let wastePct = Number.parseFloat(this.dataset.wasteDefault || '5') || 0;
    if (wasteCtrl instanceof HTMLSelectElement) {
      wastePct = Math.max(0, Number.parseFloat(wasteCtrl.value || '0') || 0);
    } else if (wasteCtrl instanceof HTMLInputElement && wasteCtrl.type === 'checkbox') {
      wastePct = wasteCtrl.checked ? 5 : 0;
    }

    if (roundCheck instanceof HTMLInputElement && skuRoundMode === 'box') {
      roundCheck.checked = true;
      roundCheck.disabled = true;
    }

    const forceRoundBox =
      skuRoundMode === 'box' || (roundCheck instanceof HTMLInputElement ? roundCheck.checked : true);

    const baseParams = {
      areaPiece,
      piecesPerBox,
      qtyMin,
      priceM2: isQuote ? 0 : priceM2,
      kgPerBox,
      unitLabel,
    };

    let res;
    let targetM2 = 0;
    let appliedWastePct = 0;

    if (this._activeUnit === 'vien' && piecesInput instanceof HTMLInputElement) {
      const rawPieces = Math.max(qtyMin, parseNumberVn(piecesInput.value || String(qtyMin)));
      res = calcFromPieces({
        ...baseParams,
        pieces: rawPieces,
        roundMode: forceRoundBox ? 'box' : skuRoundMode,
      });
      targetM2 = res.actualM2;
      if (document.activeElement !== piecesInput) {
        piecesInput.value = String(res.pieces);
      }
      // Không ghi đè ô m² (nguồn nhập riêng của tab m²) — tránh cộng hao hụt lần 2 khi quay lại tab m².
      if (boxesInput instanceof HTMLInputElement && document.activeElement !== boxesInput) {
        boxesInput.value = String(Math.max(1, res.fullBoxes));
      }
    } else if (this._activeUnit === 'thung' && boxesInput instanceof HTMLInputElement) {
      const rawBoxes = Math.max(1, parseNumberVn(boxesInput.value || '1'));
      res = calcFromBoxes({
        ...baseParams,
        boxes: rawBoxes,
      });
      targetM2 = res.actualM2;
      if (document.activeElement !== boxesInput) {
        boxesInput.value = String(Math.max(1, res.fullBoxes));
      }
      if (piecesInput instanceof HTMLInputElement && document.activeElement !== piecesInput) {
        piecesInput.value = String(res.pieces);
      }
    } else {
      const inputArea = Math.max(0.5, parseNumberVn(m2Input instanceof HTMLInputElement ? m2Input.value : '10'));
      appliedWastePct = wastePct;
      res = calcFromM2({
        ...baseParams,
        areaM2: inputArea,
        wastePct: appliedWastePct,
        forceRoundBox,
        roundMode: skuRoundMode,
      });
      targetM2 = inputArea;
      if (piecesInput instanceof HTMLInputElement && document.activeElement !== piecesInput) {
        piecesInput.value = String(res.pieces);
      }
      if (boxesInput instanceof HTMLInputElement && document.activeElement !== boxesInput) {
        boxesInput.value = String(Math.max(1, res.fullBoxes));
      }
    }

    if (!isQuote && serverPricePiece > 0) {
      res.pricePiece = serverPricePiece;
      res.totalVnd = res.pieces * serverPricePiece;
    }

    for (const presetBtn of qsa('[data-calc-preset]', this)) {
      const pVal = parseNumberVn(presetBtn.getAttribute('data-calc-preset') || '0');
      presetBtn.classList.toggle('is-active', this._activeUnit === 'm2' && Math.abs(pVal - targetM2) < 0.01);
    }

    for (const inlinePieces of qsa('[data-calc-inline-pieces]', this)) {
      inlinePieces.textContent = String(res.pieces);
    }
    for (const inlineM2 of qsa('[data-calc-inline-m2]', this)) {
      inlineM2.textContent = `${formatDecimalVn(res.actualM2, 2)} m²`;
    }

    const rawEl = qs('[data-calc-out="raw-need"], [data-calc-raw-need]', this);
    if (rawEl) {
      rawEl.textContent =
        appliedWastePct > 0
          ? `· Đã gồm ${appliedWastePct}% hao hụt cắt gạch: ${formatDecimalVn(res.needM2, 2)} m²`
          : `· Diện tích tịnh chưa cộng hao hụt: ${formatDecimalVn(res.needM2, 2)} m²`;
    }

    const boxesOut = qs('[data-calc-out="boxes"]', this);
    const piecesBoxesOut = qs('[data-calc-out="pieces-boxes"]', this);
    const actualOut = qs('[data-calc-out="actual-m2"]', this);
    const kgOut = qs('[data-calc-out="kg"]', this);
    const sumEl = qs('[data-calc-out="summary"], [data-calc-summary]', this);

    if (piecesBoxesOut) {
      piecesBoxesOut.textContent = `${res.pieces} ${unitLabel} = ${res.boxText}`;
    }
    if (boxesOut || piecesBoxesOut || actualOut) {
      if (boxesOut) {
        const prefixNode = sumEl?.firstChild;
        if (prefixNode && prefixNode.nodeType === Node.TEXT_NODE) {
          prefixNode.nodeValue = `${res.pieces} ${unitLabel} = `;
        }
        boxesOut.textContent = res.boxText;
      }
      if (actualOut) {
        actualOut.textContent = `${formatDecimalVn(res.actualM2, 2)} m²`;
      }
      if (kgOut) {
        kgOut.textContent = `≈ ${formatDecimalVn(res.totalKg, 1)} kg`;
        kgOut.hidden = !(res.totalKg > 0);
      }
    } else if (sumEl) {
      const kgPart = res.totalKg > 0 ? ` · ≈ ${formatDecimalVn(res.totalKg, 1)} kg` : '';
      sumEl.textContent = `${res.pieces} ${unitLabel} = ${res.boxText} (${formatDecimalVn(res.actualM2, 2)} m²${kgPart})`;
    }

    for (const qBox of qsa('[data-calc-boxes-quote]', this)) {
      qBox.textContent = res.boxText;
    }
    for (const qM2 of qsa('[data-calc-m2-quote]', this)) {
      qM2.textContent = `${formatDecimalVn(res.actualM2, 2)} m²`;
    }

    const totalEl = qs('[data-calc-out="total-vnd"], [data-calc-total]', this);
    if (totalEl && !isQuote) {
      if (
        animateTotal &&
        this._lastTotalVnd !== null &&
        this._lastTotalVnd !== res.totalVnd &&
        !prefersReducedMotion()
      ) {
        import('./motion.js')
          .then((m) => m.animateNumber(totalEl, res.totalVnd, formatVnd))
          .catch(() => {
            totalEl.textContent = formatVnd(res.totalVnd);
          });
      } else {
        totalEl.textContent = formatVnd(res.totalVnd);
      }
      this._lastTotalVnd = res.totalVnd;
    }

    this._syncExternalPdpBindings({
      res,
      targetM2,
      appliedWastePct,
      unitLabel,
      isQuote,
    });

    this._lastResult = res;

    const detail = {
      ...res,
      productId,
      sku,
      activeUnit: this._activeUnit,
      targetM2: Number(targetM2.toFixed(2)),
      wastePct: appliedWastePct,
      unitLabel,
    };

    this.dispatchEvent(
      new CustomEvent('drv:calc-change', {
        bubbles: true,
        detail,
      }),
    );

    return detail;
  }

  _syncExternalPdpBindings({ res, targetM2, appliedWastePct, unitLabel, isQuote }) {
    const scope = this.closest('[data-drv-pdp-root]') || document;

    const qtyHidden = qs('input[name="quantity"][data-drv-qty-pieces]', scope);
    if (qtyHidden instanceof HTMLInputElement) {
      qtyHidden.value = String(res.pieces);
    }

    const m2Hidden = qs('[data-drv-m2-input]', scope);
    if (m2Hidden instanceof HTMLInputElement) {
      m2Hidden.value = String(Number(targetM2.toFixed(2)));
    }

    const wasteHidden = qs('[data-drv-waste-input]', scope);
    if (wasteHidden instanceof HTMLInputElement) {
      wasteHidden.value = String(appliedWastePct);
    }

    const unitHidden = qs('[data-drv-unit-input]', scope);
    if (unitHidden instanceof HTMLInputElement) {
      unitHidden.value = this._activeUnit;
    }

    for (const el of qsa('[data-sync-calc-pieces]')) {
      el.textContent = String(res.pieces);
    }
    for (const el of qsa('[data-sync-calc-boxes]')) {
      el.textContent = res.boxText;
    }
    if (!isQuote) {
      for (const el of qsa('[data-sync-calc-total]')) {
        el.textContent = formatVnd(res.totalVnd);
      }
    }

    const kgPart = res.totalKg > 0 ? ` · ≈ ${formatDecimalVn(res.totalKg, 0)} kg` : '';
    const stickyMetaText = `${formatDecimalVn(res.actualM2, 2)} m² · ${res.pieces} ${unitLabel} (${res.boxText}${kgPart})`;
    for (const el of qsa('[data-sync-calc-sticky-meta]')) {
      el.textContent = stickyMetaText;
    }

    for (const cartBtn of qsa('[data-drv-action="add-to-cart"][data-drv-sync-calc="true"]', scope)) {
      cartBtn.dataset.drvPieces = String(res.pieces);
      cartBtn.dataset.drvQtyPieces = String(res.pieces);
    }

    for (const qBtn of qsa('[data-pdp-quote-add], [data-pdp-quote-modal-open]', scope)) {
      qBtn.dataset.drvM2 = String(Number(targetM2.toFixed(2)));
      qBtn.dataset.drvPieces = String(res.pieces);
      qBtn.dataset.drvBoxesText = res.boxText;
    }
  }
}

export class DrvQtyInputElement extends HTMLElement {
  connectedCallback() {
    if (this._mounted) {
      return;
    }
    this._mounted = true;
    this._abort = new AbortController();
    const { signal } = this._abort;

    this.addEventListener(
      'click',
      (event) => {
        const input = qs('input[type="number"]', this);
        if (!(input instanceof HTMLInputElement)) {
          return;
        }
        const target = event.target instanceof Element ? event.target : null;
        if (!target) {
          return;
        }
        const stepBtn = target.closest('[data-qty-step]');
        if (stepBtn) {
          const delta = Number.parseInt(stepBtn.getAttribute('data-qty-step') || '0', 10) || 0;
          const min = Number.parseInt(input.min || '1', 10) || 1;
          input.value = String(Math.max(min, (Number.parseInt(input.value || '1', 10) || 1) + delta));
          this.syncRow(input);
        }
        const boxBtn = target.closest('[data-qty-add-box]');
        if (boxBtn) {
          const perBox = Math.max(1, Number.parseInt(this.dataset.piecesPerBox || '1', 10));
          input.value = String((Number.parseInt(input.value || '0', 10) || 0) + perBox);
          this.syncRow(input);
        }
      },
      { signal },
    );

    this.addEventListener(
      'change',
      () => {
        const input = qs('input[type="number"]', this);
        if (input instanceof HTMLInputElement) {
          this.syncRow(input);
        }
      },
      { signal },
    );
  }

  disconnectedCallback() {
    this._abort?.abort();
    this._abort = null;
    this._mounted = false;
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
