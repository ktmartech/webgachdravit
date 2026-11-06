/**
 * DRAVIT Unit Calculator & Multi-Unit Quantity Web Components (Light DOM)
 * Spec: docs/04_Pricing_Units_Commerce.md (§2–§4) & docs/07_UI_UX_Page_Specs.md (§5.2)
 * Elements: <drv-unit-calc> (m2 <-> vien <-> thung <-> kg) & <drv-qty-input> (Cart/Mini-cart)
 */
(() => {
  'use strict';

  const formatVnd = (n) =>
    window.Dravit ? window.Dravit.formatVnd(n) : `${new Intl.NumberFormat('vi-VN').format(Math.round(n))}đ`;

  const formatDec = (n, d = 2) =>
    window.Dravit
      ? window.Dravit.formatDecimalVn(n, d)
      : new Intl.NumberFormat('vi-VN', { maximumFractionDigits: d }).format(n);

  /* ==========================================================================
     1. <drv-unit-calc> — Two-Way Dual-Unit Converter (m2 <-> Vien)
     ========================================================================== */
  class DrvUnitCalc extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      this.priceM2 = Number(this.dataset.priceM2 || 385000);
      this.pieceW = Number(this.dataset.pieceW || 800);
      this.pieceH = Number(this.dataset.pieceH || 800);
      this.piecesPerBox = Number(this.dataset.piecesPerBox || 3);
      this.weightPerBox = Number(this.dataset.weightPerBox || 42);
      this.sku = this.dataset.sku || 'DRV-8801M';

      this.areaPiece = (this.pieceW * this.pieceH) / 1_000_000;
      this.m2PerBox = Number((this.areaPiece * this.piecesPerBox).toFixed(4));
      this.pricePiece = Math.round(this.priceM2 * this.areaPiece);
      this.weightPerPiece = this.weightPerBox / this.piecesPerBox;

      this.inputM2 = this.querySelector('[data-calc-m2]');
      this.inputPieces = this.querySelector('[data-calc-pieces]');
      this.checkWaste = this.querySelector('[data-calc-waste]');
      this.checkRoundBox = this.querySelector('[data-calc-round-box]');

      this.outSummary = this.querySelector('[data-calc-summary]');
      this.outTotal = this.querySelector('[data-calc-total]');
      this.outRawNeed = this.querySelector('[data-calc-raw-need]');

      this._bindEvents();
      this.computeFromM2();
    }

    _bindEvents() {
      if (this.inputM2) {
        this.inputM2.addEventListener('input', () => this.computeFromM2());
      }
      if (this.inputPieces) {
        this.inputPieces.addEventListener('input', () => this.computeFromPieces());
      }
      if (this.checkWaste) {
        this.checkWaste.addEventListener('change', () => this.computeFromM2());
      }
      if (this.checkRoundBox) {
        this.checkRoundBox.addEventListener('change', () => this.computeFromM2());
      }

      this.addEventListener('click', (event) => {
        const stepBtn = event.target.closest('[data-calc-step]');
        if (!stepBtn) return;
        event.preventDefault();

        const targetType = stepBtn.dataset.calcTarget;
        const delta = Number(stepBtn.dataset.calcStep || 1);

        if (targetType === 'm2' && this.inputM2) {
          const cur = Math.max(1, Number(this.inputM2.value || 1) + delta);
          this.inputM2.value = String(cur);
          this.computeFromM2();
        } else if (targetType === 'pieces' && this.inputPieces) {
          const stepUnit = this.checkRoundBox && this.checkRoundBox.checked ? this.piecesPerBox : 1;
          const cur = Math.max(1, Number(this.inputPieces.value || 1) + delta * stepUnit);
          this.inputPieces.value = String(cur);
          this.computeFromPieces(true);
        }
      });
    }

    computeFromM2() {
      const areaVal = Math.max(0.5, Number(this.inputM2?.value || 10));
      const useWaste = Boolean(this.checkWaste?.checked);
      const roundBox = Boolean(this.checkRoundBox?.checked);

      const needM2 = areaVal * (useWaste ? 1.05 : 1);
      let pieces = Math.max(1, Math.ceil(needM2 / this.areaPiece - 1e-9));

      if (roundBox) {
        pieces = Math.ceil(pieces / this.piecesPerBox) * this.piecesPerBox;
      }

      if (this.inputPieces) {
        this.inputPieces.value = String(pieces);
      }

      this._renderOutput(pieces, areaVal, needM2);
    }

    computeFromPieces(syncM2Input = false) {
      let pieces = Math.max(1, Math.round(Number(this.inputPieces?.value || 1)));
      const actualM2 = pieces * this.areaPiece;

      if (syncM2Input && this.inputM2) {
        this.inputM2.value = String(Number(actualM2.toFixed(2)));
      }

      const rawM2 = Number(this.inputM2?.value || actualM2);
      this._renderOutput(pieces, rawM2, actualM2);
    }

    _renderOutput(pieces, inputM2, needM2) {
      const fullBoxes = Math.floor(pieces / this.piecesPerBox);
      const loosePieces = pieces % this.piecesPerBox;
      const actualM2 = pieces * this.areaPiece;
      const totalKg = Math.round(pieces * this.weightPerPiece);
      const totalVnd = pieces * this.pricePiece;

      let boxText = '';
      if (loosePieces === 0) {
        boxText = `${fullBoxes} thùng`;
      } else if (fullBoxes > 0) {
        boxText = `${fullBoxes} thùng + ${loosePieces} viên lẻ`;
      } else {
        boxText = `${loosePieces} viên lẻ`;
      }

      const summaryString = `${pieces} viên = ${boxText} (${formatDec(actualM2, 2)} m² · ${formatDec(totalKg, 0)} kg)`;

      if (this.outSummary) {
        this.outSummary.textContent = summaryString;
      }
      if (this.outTotal) {
        const prevTotal = this._lastTotalVnd;
        this._lastTotalVnd = totalVnd;
        if (
          typeof prevTotal === 'number' &&
          prevTotal !== totalVnd &&
          window.Dravit?.motion?.animateNumber
        ) {
          window.Dravit.motion.animateNumber(this.outTotal, prevTotal, totalVnd, formatVnd, 320);
          window.Dravit.motion.pulseElement(this.querySelector('.drv-calc__result'));
        } else {
          this.outTotal.textContent = formatVnd(totalVnd);
        }
      }
      if (this.outRawNeed) {
        const useWaste = Boolean(this.checkWaste?.checked);
        this.outRawNeed.textContent = useWaste
          ? `Diện tích tính cả 5% hao hụt cắt gạch: ${formatDec(needM2, 2)} m²`
          : `Diện tích thuần (chưa cộng hao hụt): ${formatDec(inputM2, 2)} m²`;
      }

      // Sync Sticky Purchase Bar & Add-to-Cart buttons on page
      document.querySelectorAll('[data-sync-calc-summary]').forEach((el) => {
        el.textContent = `${pieces} viên (${boxText} · ${formatDec(actualM2, 2)} m²)`;
      });
      document.querySelectorAll('[data-sync-calc-total]').forEach((el) => {
        el.textContent = formatVnd(totalVnd);
      });
      document.querySelectorAll('[data-drv-action="add-to-cart"]').forEach((btn) => {
        if (btn.dataset.drvSyncCalc === 'true') {
          btn.dataset.drvQty = `${pieces} viên (${boxText} · ${formatDec(actualM2, 2)} m²)`;
        }
      });
      document.querySelectorAll('[data-drv-quote-trigger]').forEach((btn) => {
        btn.dataset.drvArea = formatDec(actualM2, 2);
      });

      this.dispatchEvent(
        new CustomEvent('drv:calc-change', {
          bubbles: true,
          detail: { pieces, fullBoxes, loosePieces, actualM2, totalKg, totalVnd },
        })
      );
    }
  }

  /* ==========================================================================
     2. <drv-qty-input> — Multi-Unit Quantity Input for Cart & Mini-Cart
     ========================================================================== */
  class DrvQtyInput extends HTMLElement {
    connectedCallback() {
      if (this._initialized) return;
      this._initialized = true;

      this.model = this.dataset.model || 'area_pack';
      this.priceUnit = Number(this.dataset.priceUnit || 246400);
      this.areaPiece = Number(this.dataset.areaPiece || 0.64);
      this.piecesPerBox = Number(this.dataset.piecesPerBox || 3);
      this.weightPiece = Number(this.dataset.weightPiece || 14);
      this.unitLabel = this.dataset.unitLabel || 'viên';

      this.input = this.querySelector('input[type="number"]');
      this._bindEvents();
      this.updateRow();
    }

    _bindEvents() {
      this.addEventListener('click', (event) => {
        const stepBtn = event.target.closest('[data-qty-step]');
        if (stepBtn && this.input) {
          event.preventDefault();
          const delta = Number(stepBtn.dataset.qtyStep || 1);
          const nextVal = Math.max(1, Number(this.input.value || 1) + delta);
          this.input.value = String(nextVal);
          this.updateRow();
          return;
        }

        const boxBtn = event.target.closest('[data-qty-add-box]');
        if (boxBtn && this.input) {
          event.preventDefault();
          const nextVal = Number(this.input.value || 1) + this.piecesPerBox;
          this.input.value = String(nextVal);
          this.updateRow();
        }
      });

      if (this.input) {
        this.input.addEventListener('input', () => this.updateRow());
      }
    }

    getState() {
      const qty = Math.max(1, Math.round(Number(this.input?.value || 1)));
      const lineTotal = qty * this.priceUnit;
      const weightKg = qty * this.weightPiece;

      if (this.model === 'area_pack') {
        const m2 = qty * this.areaPiece;
        const boxes = Math.floor(qty / this.piecesPerBox);
        const loose = qty % this.piecesPerBox;
        return { qty, lineTotal, weightKg, m2, boxes, loose, isTile: true };
      }

      return { qty, lineTotal, weightKg, m2: 0, boxes: 0, loose: 0, isTile: false };
    }

    updateRow() {
      const state = this.getState();
      const row = this.closest('[data-cart-row]');
      if (row) {
        const subEl = row.querySelector('[data-row-subtotal]');
        if (subEl) subEl.textContent = formatVnd(state.lineTotal);

        const metaEl = row.querySelector('[data-row-conversion]');
        if (metaEl) {
          if (state.isTile) {
            const boxStr =
              state.loose === 0
                ? `${state.boxes} thùng`
                : state.boxes > 0
                ? `${state.boxes} thùng + ${state.loose} viên lẻ`
                : `${state.loose} viên lẻ`;
            metaEl.textContent = `= ${boxStr} · ${formatDec(state.m2, 2)} m² · ${formatDec(state.weightKg, 0)} kg`;
          } else {
            metaEl.textContent = `= ${state.qty} ${this.unitLabel} · ${formatDec(state.weightKg, 1)} kg`;
          }
        }
      }

      recalculateCartTotals();
    }
  }

  /**
   * Recalculates global Cart Page & Mini-Cart Drawer totals whenever any row changes
   */
  function recalculateCartTotals() {
    const rows = document.querySelectorAll('[data-cart-row]');
    if (rows.length === 0) return;

    let totalVnd = 0;
    let totalM2 = 0;
    let totalBoxes = 0;
    let totalKg = 0;
    let skuCount = 0;

    rows.forEach((row) => {
      if (row.hidden) return;
      const qtyComp = row.querySelector('drv-qty-input');
      if (!qtyComp || typeof qtyComp.getState !== 'function') return;
      const st = qtyComp.getState();
      skuCount += 1;
      totalVnd += st.lineTotal;
      totalM2 += st.m2;
      totalBoxes += st.boxes;
      totalKg += st.weightKg;
    });

    document.querySelectorAll('[data-cart-sku-count]').forEach((el) => {
      el.textContent = String(skuCount);
    });
    document.querySelectorAll('[data-cart-total-m2]').forEach((el) => {
      el.textContent = `${formatDec(totalM2, 2)} m²`;
    });
    document.querySelectorAll('[data-cart-total-boxes]').forEach((el) => {
      el.textContent = `${totalBoxes} thùng`;
    });
    document.querySelectorAll('[data-cart-total-kg]').forEach((el) => {
      el.textContent = `${formatDec(totalKg, 1)} kg`;
    });
    document.querySelectorAll('[data-cart-total-price]').forEach((el) => {
      el.textContent = formatVnd(totalVnd);
    });
  }

  // Handle Cart Row Removal
  document.addEventListener('click', (event) => {
    const removeBtn = event.target.closest('[data-cart-remove]');
    if (!removeBtn) return;
    event.preventDefault();
    const row = removeBtn.closest('[data-cart-row]');
    if (row) {
      row.remove();
      recalculateCartTotals();
      if (window.Dravit) window.Dravit.toast('Đã xóa sản phẩm khỏi giỏ hàng.');
      const remaining = document.querySelectorAll('[data-cart-row]');
      if (remaining.length === 0) {
        const emptyView = document.getElementById('drv-cart-empty');
        const fullView = document.getElementById('drv-cart-filled');
        if (emptyView && fullView) {
          fullView.hidden = true;
          emptyView.hidden = false;
        }
      }
    }
  });

  if (!customElements.get('drv-unit-calc')) {
    customElements.define('drv-unit-calc', DrvUnitCalc);
  }
  if (!customElements.get('drv-qty-input')) {
    customElements.define('drv-qty-input', DrvQtyInput);
  }
})();

