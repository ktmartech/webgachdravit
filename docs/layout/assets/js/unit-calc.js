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

      this.inputM2 = this.querySelector('[data-calc-m2]');
      this.inputPieces = this.querySelector('[data-calc-pieces]');
      this.checkWaste = this.querySelector('[data-calc-waste]');
      this.checkRoundBox = this.querySelector('[data-calc-round-box]');

      this.outSummary = this.querySelector('[data-calc-summary]');
      this.outTotal = this.querySelector('[data-calc-total]');
      this.outRawNeed = this.querySelector('[data-calc-raw-need]');

      this.refreshConfig(false);
      this._bindEvents();
      this.computeFromM2();
    }

    refreshConfig(recompute = true) {
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

      const outBoxRule = this.querySelector('[data-calc-box-rule]');
      if (outBoxRule) {
        outBoxRule.textContent = `${this.piecesPerBox} viên = ${formatDec(this.m2PerBox, 2)} m²`;
      }

      if (recompute) {
        this.computeFromM2();
      }
    }

    _bindEvents() {
      if (this.inputM2) {
        this.inputM2.addEventListener('input', () => this.computeFromM2());
      }
      if (this.inputPieces) {
        this.inputPieces.addEventListener('input', () => this.computeFromPieces(true));
      }
      if (this.checkWaste) {
        this.checkWaste.addEventListener('change', () => this.computeFromM2());
      }
      if (this.checkRoundBox) {
        this.checkRoundBox.addEventListener('change', () => this.computeFromM2());
      }

      this.addEventListener('click', (event) => {
        const presetBtn = event.target.closest('[data-calc-preset]');
        if (presetBtn && this.inputM2) {
          event.preventDefault();
          const presetVal = Number(presetBtn.dataset.calcPreset || 10);
          this.inputM2.value = String(presetVal);
          this.querySelectorAll('[data-calc-preset]').forEach((btn) => {
            const active = btn === presetBtn;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
          });
          this.computeFromM2();
          return;
        }

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
      const useWaste = this.checkWaste ? Boolean(this.checkWaste.checked) : this.dataset.defaultWaste !== 'false';
      const roundBox = this.checkRoundBox ? Boolean(this.checkRoundBox.checked) : this.dataset.roundBox !== 'false';

      const needM2 = areaVal * (useWaste ? 1.05 : 1);
      let pieces = Math.max(1, Math.ceil(needM2 / this.areaPiece - 1e-9));

      if (roundBox) {
        pieces = Math.ceil(pieces / this.piecesPerBox) * this.piecesPerBox;
      }

      if (this.inputPieces) {
        if ('value' in this.inputPieces) {
          this.inputPieces.value = String(pieces);
        } else {
          this.inputPieces.textContent = String(pieces);
        }
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

      const outActualM2 = this.querySelector('[data-calc-actual-m2]');
      if (outActualM2) {
        outActualM2.textContent = `${formatDec(actualM2, 2)} m²`;
      }
      const outBoxMeta = this.querySelector('[data-calc-box-meta]');
      if (outBoxMeta) {
        outBoxMeta.textContent = `${boxText} = ${pieces} viên · ${formatDec(totalKg, 0)} kg`;
      }

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
          window.Dravit.motion.pulseElement(
            this.querySelector('.drv-pdp-calc__total-row') ||
              this.querySelector('.drv-calc__summary') ||
              this.querySelector('.drv-calc__result')
          );
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
        el.textContent = `${pieces} viên = ${boxText} (${formatDec(actualM2, 2)} m² · ${formatDec(totalKg, 0)} kg)`;
      });
      document.querySelectorAll('[data-sync-calc-short]').forEach((el) => {
        el.textContent = `${pieces} viên (${boxText} · ${formatDec(actualM2, 2)} m²)`;
      });
      document.querySelectorAll('[data-sync-calc-sticky-meta]').forEach((el) => {
        el.textContent = `${formatDec(actualM2, 2)} m² | ${pieces} viên (${boxText})`;
      });
      document.querySelectorAll('[data-sync-calc-pieces]').forEach((el) => {
        el.textContent = String(pieces);
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
          return;
        }

        const roundBtn = event.target.closest('[data-qty-round-box]');
        if (roundBtn && this.input && this.model === 'area_pack') {
          event.preventDefault();
          const cur = Math.max(1, Math.round(Number(this.input.value || 1)));
          const rounded = Math.ceil(cur / this.piecesPerBox) * this.piecesPerBox;
          this.input.value = String(rounded);
          this.updateRow();
          if (window.Dravit) {
            window.Dravit.toast(`Đã làm tròn lên ${rounded} viên (${rounded / this.piecesPerBox} thùng nguyên).`);
          }
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

    updateRow(skipPeerSync = false) {
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

        const looseWarnEl = row.querySelector('[data-row-loose-warning]');
        if (looseWarnEl) {
          if (state.isTile && state.loose > 0) {
            const neededForFullBox = this.piecesPerBox - state.loose;
            const nextBoxes = state.boxes + 1;
            const textEl = looseWarnEl.querySelector('[data-row-loose-text]');
            if (textEl) {
              textEl.textContent = `Đang lẻ ${state.loose} viên (thêm ${neededForFullBox} viên để chẵn ${nextBoxes} thùng nguyên)`;
            }
            looseWarnEl.hidden = false;
          } else {
            looseWarnEl.hidden = true;
          }
        }

        // Sync matching SKU row between Cart Page and Mini-Cart Drawer
        const sku = row.dataset.cartSku;
        if (sku && !skipPeerSync) {
          document.querySelectorAll(`[data-cart-row][data-cart-sku="${sku}"]`).forEach((peerRow) => {
            if (peerRow === row) return;
            const peerQty = peerRow.querySelector('drv-qty-input');
            if (peerQty && peerQty.input) {
              peerQty.input.value = String(state.qty);
              peerQty.updateRow(true);
            }
          });
        }
      }

      recalculateCartTotals();
    }
  }

  /**
   * Recalculates global Cart Page & Mini-Cart Drawer totals whenever any row changes
   */
  function recalculateCartTotals() {
    const cartPageContainer = document.getElementById('drv-cart-filled');
    const scopeRoot = cartPageContainer || document.getElementById('drv-minicart-drawer') || document;
    const rows = scopeRoot.querySelectorAll('[data-cart-row]');

    let totalVnd = 0;
    let totalM2 = 0;
    let totalBoxes = 0;
    let totalTilePieces = 0;
    let totalLoosePieces = 0;
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
      if (st.isTile) {
        totalTilePieces += st.qty;
        totalLoosePieces += st.loose;
      }
      totalKg += st.weightKg;
    });

    document.querySelectorAll('[data-cart-sku-count], [data-drv-cart-count]').forEach((el) => {
      el.textContent = String(skuCount);
    });
    document.querySelectorAll('[data-cart-total-m2]').forEach((el) => {
      el.textContent = `${formatDec(totalM2, 2)} m²`;
    });
    document.querySelectorAll('[data-cart-total-boxes]').forEach((el) => {
      if (totalLoosePieces > 0) {
        el.textContent = `${totalBoxes} thùng + ${totalLoosePieces} viên lẻ (${totalTilePieces} viên)`;
      } else {
        el.textContent = totalTilePieces > 0 ? `${totalBoxes} thùng (${totalTilePieces} viên)` : `${totalBoxes} thùng`;
      }
    });
    document.querySelectorAll('[data-cart-total-kg]').forEach((el) => {
      el.textContent = `${formatDec(totalKg, 1)} kg`;
    });
    document.querySelectorAll('[data-cart-vehicle-hint]').forEach((el) => {
      if (totalKg <= 0) {
        el.textContent = 'Chưa có hàng hóa';
      } else if (totalKg <= 150) {
        el.textContent = 'Xe ba gác / Bán tải nhỏ (Nội thành)';
      } else if (totalKg <= 800) {
        el.textContent = 'Xe tải nhẹ 500kg - 1 tấn (Giao tận chân công trình)';
      } else {
        el.textContent = 'Xe tải chuyên dụng 2,5 tấn / Chành xe liên tỉnh';
      }
    });
    document.querySelectorAll('[data-cart-total-price]').forEach((el) => {
      const prev = Number(el.dataset.lastVal || totalVnd);
      el.dataset.lastVal = String(totalVnd);
      if (prev !== totalVnd && window.Dravit?.motion?.animateNumber) {
        window.Dravit.motion.animateNumber(el, prev, totalVnd, formatVnd, 280);
      } else {
        el.textContent = formatVnd(totalVnd);
      }
    });
  }

  function setCartEmptyState(isEmpty) {
    const emptyView = document.getElementById('drv-cart-empty');
    const fullView = document.getElementById('drv-cart-filled');
    if (!emptyView || !fullView) return;

    fullView.hidden = isEmpty;
    emptyView.hidden = !isEmpty;

    fullView.style.display = isEmpty ? 'none' : '';
    emptyView.style.display = isEmpty ? 'flex' : 'none';

    document.querySelectorAll('[data-drv-action="cart-toggle-empty"]').forEach((btn) => {
      const targetState = btn.dataset.drvState;
      const isCurrent = (targetState === 'empty' && isEmpty) || (targetState === 'filled' && !isEmpty);
      btn.classList.toggle('is-active', isCurrent);
      btn.setAttribute('aria-pressed', String(isCurrent));
    });
  }

  // Handle Cart Row Removal, State Toggles, Cross-sell & Quote Export
  document.addEventListener('click', (event) => {
    const removeBtn = event.target.closest('[data-cart-remove]');
    if (removeBtn) {
      event.preventDefault();
      const row = removeBtn.closest('[data-cart-row]');
      if (row) {
        const sku = row.dataset.cartSku;
        if (sku) {
          document.querySelectorAll(`[data-cart-row][data-cart-sku="${sku}"]`).forEach((r) => {
            r.hidden = true;
          });
        } else {
          row.hidden = true;
        }
        recalculateCartTotals();
        if (window.Dravit) window.Dravit.toast('Đã xóa sản phẩm khỏi giỏ hàng.');

        const cartPageContainer = document.getElementById('drv-cart-filled');
        if (cartPageContainer) {
          const visibleRows = Array.from(cartPageContainer.querySelectorAll('[data-cart-row]')).filter(
            (r) => !r.hidden
          );
          if (visibleRows.length === 0) {
            setCartEmptyState(true);
          }
        }
      }
      return;
    }

    const toggleEmptyBtn = event.target.closest('[data-drv-action="cart-toggle-empty"]');
    if (toggleEmptyBtn) {
      event.preventDefault();
      const wantEmpty = toggleEmptyBtn.dataset.drvState === 'empty';
      if (!wantEmpty) {
        // Ensure at least default rows are visible when switching back to filled view
        const cartPageContainer = document.getElementById('drv-cart-filled');
        if (cartPageContainer) {
          const visibleRows = Array.from(cartPageContainer.querySelectorAll('[data-cart-row]')).filter(
            (r) => !r.hidden
          );
          if (visibleRows.length === 0) {
            document.querySelectorAll('[data-cart-row][data-cart-default="true"]').forEach((r) => {
              r.hidden = false;
            });
          }
        }
      }
      setCartEmptyState(wantEmpty);
      recalculateCartTotals();
      return;
    }

    const restoreBtn = event.target.closest('[data-drv-action="cart-restore-demo"]');
    if (restoreBtn) {
      event.preventDefault();
      document.querySelectorAll('[data-cart-row]').forEach((r) => {
        if (r.dataset.cartDefault === 'true' || !r.dataset.cartCrosssell) {
          r.hidden = false;
        }
      });
      setCartEmptyState(false);
      recalculateCartTotals();
      if (window.Dravit) window.Dravit.toast('Đã khôi phục 2 sản phẩm mẫu vào giỏ hàng.');
      return;
    }

    const fbtSubmitBtn = event.target.closest('[data-drv-action="cart-fbt-submit"]');
    if (fbtSubmitBtn) {
      event.preventDefault();
      const checkedInputs = Array.from(document.querySelectorAll('.drv-fbt-item__check:checked'));
      if (!checkedInputs.length) {
        if (window.Dravit) window.Dravit.toast('Vui lòng tích chọn ít nhất 1 món vật tư để thêm.');
        return;
      }

      checkedInputs.forEach((input) => {
        const sku = input.dataset.fbtSku;
        if (!sku) return;
        const targetRows = document.querySelectorAll(`[data-cart-row][data-cart-sku="${sku}"]`);
        targetRows.forEach((row) => {
          row.hidden = false;
        });
      });

      setCartEmptyState(false);
      recalculateCartTotals();
      if (window.Dravit?.motion?.bounceCartBadge) window.Dravit.motion.bounceCartBadge();
      if (window.Dravit) {
        window.Dravit.toast(`Đã thêm ${checkedInputs.length} món vật tư thi công vào giỏ hàng.`);
      }

      const originalHtml = fbtSubmitBtn.innerHTML;
      fbtSubmitBtn.classList.remove('drv-btn--moss');
      fbtSubmitBtn.classList.add('drv-btn--navy');
      fbtSubmitBtn.innerHTML = '<i class="ph-bold ph-check drv-icon" aria-hidden="true"></i><span>ĐÃ THÊM VÀO ĐƠN</span>';
      setTimeout(() => {
        fbtSubmitBtn.classList.remove('drv-btn--navy');
        fbtSubmitBtn.classList.add('drv-btn--moss');
        fbtSubmitBtn.innerHTML = originalHtml;
      }, 2500);
      return;
    }

    const bundleBtn = event.target.closest('[data-drv-action="cart-add-bundle"]');
    if (bundleBtn) {
      event.preventDefault();
      const targetSkus = ['DRV-KEO-C2', 'DRV-KE-15'];
      targetSkus.forEach((sku) => {
        const rows = document.querySelectorAll(`[data-cart-row][data-cart-sku="${sku}"]`);
        rows.forEach((row) => {
          row.hidden = false;
        });
      });
      setCartEmptyState(false);
      recalculateCartTotals();
      if (window.Dravit?.motion?.bounceCartBadge) window.Dravit.motion.bounceCartBadge();
      if (window.Dravit) window.Dravit.toast('Đã thêm trọn bộ vật tư đồng bộ (Keo C2 + Ke cân bằng) vào giỏ hàng.');
      return;
    }

    const crossSellBtn = event.target.closest('[data-drv-action="cart-add-crosssell"]');
    if (crossSellBtn) {
      event.preventDefault();
      const targetSku = crossSellBtn.dataset.drvSku;
      if (!targetSku) return;
      const targetRows = document.querySelectorAll(`[data-cart-row][data-cart-sku="${targetSku}"]`);
      if (targetRows.length > 0) {
        targetRows.forEach((row) => {
          if (row.hidden) {
            row.hidden = false;
          } else {
            const qtyEl = row.querySelector('drv-qty-input');
            if (qtyEl && qtyEl.input) {
              qtyEl.input.value = String(Number(qtyEl.input.value || 1) + 1);
              qtyEl.updateRow(true);
            }
          }
        });
        setCartEmptyState(false);
        recalculateCartTotals();
        if (window.Dravit?.motion?.bounceCartBadge) window.Dravit.motion.bounceCartBadge();
        if (window.Dravit) window.Dravit.toast(`Đã thêm vật tư đồng bộ (${targetSku}) vào giỏ hàng.`);
      }
      return;
    }

    const exportQuoteBtn = event.target.closest('[data-drv-action="cart-export-quote"]');
    if (exportQuoteBtn) {
      event.preventDefault();
      populateCartQuotePreview();
      const modal = document.getElementById('drv-cart-quote-preview');
      if (modal && typeof modal.showModal === 'function') {
        modal.showModal();
      } else if (window.Dravit) {
        window.Dravit.toast('Đã tạo bảng tóm tắt khối lượng & báo giá tạm tính.');
      }
      return;
    }

    const printQuoteBtn = event.target.closest('[data-drv-action="cart-print-quote"]');
    if (printQuoteBtn) {
      event.preventDefault();
      window.print();
    }
  });

  document.addEventListener('change', (event) => {
    const fbtCheck = event.target.closest('.drv-fbt-item__check');
    if (fbtCheck) {
      updateFbtBundleSummary();
    }
  });

  function updateFbtBundleSummary() {
    const checks = document.querySelectorAll('.drv-fbt-item__check');
    if (!checks.length) return;
    let total = 0;
    let count = 0;
    checks.forEach((c) => {
      if (c.checked) {
        total += Number(c.dataset.fbtPrice || 0);
        count += 1;
      }
    });

    const totalEl = document.querySelector('[data-fbt-total]');
    const countEl = document.querySelector('[data-fbt-count]');
    const submitBtn = document.querySelector('[data-drv-action="cart-fbt-submit"]');

    if (totalEl) {
      totalEl.textContent = (window.Dravit?.formatVND ? window.Dravit.formatVND(total) : total.toLocaleString('vi-VN') + 'đ');
    }
    if (countEl) {
      countEl.textContent = String(count);
    }
    if (submitBtn) {
      submitBtn.disabled = count === 0;
      const textSpan = submitBtn.querySelector('span');
      if (textSpan) {
        textSpan.textContent = count === 0 ? 'CHỌN ÍT NHẤT 1 MÓN' : 'THÊM VẬT TƯ VÀO ĐƠN';
      }
    }
  }

  function populateCartQuotePreview() {
    const tbody = document.getElementById('drv-quote-preview-rows');
    const copyBtn = document.getElementById('drv-quote-copy-zalo');
    const cartPageContainer = document.getElementById('drv-cart-filled') || document;
    const rows = Array.from(cartPageContainer.querySelectorAll('[data-cart-row]')).filter((r) => !r.hidden);

    let html = '';
    const zaloLines = ['[BẢNG TẠM TÍNH KHỐI LƯỢNG GẠCH DRAVIT]'];
    let idx = 1;

    rows.forEach((row) => {
      const sku = row.dataset.cartSku || '';
      const title = row.querySelector('[data-row-title]')?.textContent?.trim() || sku;
      const conv = row.querySelector('[data-row-conversion]')?.textContent?.trim() || '';
      const sub = row.querySelector('[data-row-subtotal]')?.textContent?.trim() || '';
      const qtyComp = row.querySelector('drv-qty-input');
      const st = qtyComp?.getState ? qtyComp.getState() : { qty: 1 };
      const unitLabel = qtyComp?.unitLabel || 'đơn vị';

      html += `
        <tr>
          <td class="u-tabular">${idx}</td>
          <td>
            <strong class="u-text-navy">${title}</strong>
            <div class="u-text-xs u-text-muted u-tabular">Mã: ${sku} ${conv}</div>
          </td>
          <td class="u-tabular u-bold">${st.qty} ${unitLabel}</td>
          <td class="u-tabular u-bold u-text-navy">${sub}</td>
        </tr>
      `;
      zaloLines.push(`${idx}. ${title} (${sku}): ${st.qty} ${unitLabel} ${conv} -> ${sub}`);
      idx += 1;
    });

    if (tbody) tbody.innerHTML = html;

    const totalM2 = document.querySelector('[data-cart-total-m2]')?.textContent || '0 m²';
    const totalKg = document.querySelector('[data-cart-total-kg]')?.textContent || '0 kg';
    const totalPrice = document.querySelector('[data-cart-total-price]')?.textContent || '0đ';

    zaloLines.push(`• Tổng diện tích gạch: ${totalM2}`);
    zaloLines.push(`• Tổng trọng lượng: ${totalKg}`);
    zaloLines.push(`• Tổng tạm tính (chưa gồm phí xe): ${totalPrice}`);

    if (copyBtn) {
      copyBtn.dataset.drvValue = zaloLines.join('\n');
    }
  }

  // Handle PDP Sibling Variant Switcher ([data-pdp-variants])
  document.addEventListener('click', (event) => {
    const variantBtn = event.target.closest('[data-variant-type]');
    if (!variantBtn) return;
    const container = variantBtn.closest('[data-pdp-variants]');
    if (!container) return;

    event.preventDefault();
    const type = variantBtn.dataset.variantType;
    container.querySelectorAll(`[data-variant-type="${type}"]`).forEach((btn) => {
      const active = btn === variantBtn;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-checked', String(active));
    });

    const activeColor = container.querySelector('[data-variant-type="color"].is-active');
    const activeSize = container.querySelector('[data-variant-type="size"].is-active');
    const activeSurface = container.querySelector('[data-variant-type="surface"].is-active');

    const colorName = activeColor?.dataset.colorName || 'Trắng khói Calacatta';
    const colorCode = activeColor?.dataset.colorCode || '8801';
    const sizeLabel = activeSize?.dataset.sizeLabel || '80x80';
    const pieceW = Number(activeSize?.dataset.pieceW || 800);
    const pieceH = Number(activeSize?.dataset.pieceH || 800);
    const piecesBox = Number(activeSize?.dataset.piecesBox || 3);
    const weightBox = Number(activeSize?.dataset.weightBox || 42);
    const basePriceM2 = Number(activeSize?.dataset.priceM2 || 385000);
    const baseOldM2 = Number(activeSize?.dataset.priceOld || 450000);
    const surfaceDelta = Number(activeSurface?.dataset.priceDelta || 0);
    const surfaceSuffix = activeSurface?.dataset.surfaceSuffix || 'M';

    const finalPriceM2 = basePriceM2 + surfaceDelta;
    const finalOldM2 = baseOldM2 + surfaceDelta;
    const areaPiece = (pieceW * pieceH) / 1_000_000;
    const finalPricePiece = Math.round(finalPriceM2 * areaPiece);
    const sizePrefix = sizeLabel === '60x60' ? '66' : sizeLabel === '60x120' ? '612' : '88';
    const finalSku = `DRV-${sizePrefix}${colorCode.slice(-2)}${surfaceSuffix}`;
    const fullTitle = `Gạch lát nền Dravit Vân Đá Marble ${colorName} ${sizeLabel} ${finalSku}`;

    const colorLabelEl = container.querySelector('[data-variant-color-name]');
    if (colorLabelEl) colorLabelEl.textContent = colorName;

    document.querySelectorAll('[data-pdp-sku]').forEach((el) => {
      el.textContent = finalSku;
    });
    document.querySelectorAll('[data-pdp-sku-copy]').forEach((el) => {
      el.dataset.drvValue = finalSku;
    });
    document.querySelectorAll('[data-pdp-title-text]').forEach((el) => {
      el.textContent = fullTitle;
    });
    document.querySelectorAll('[data-pdp-price-m2]').forEach((el) => {
      el.textContent = `${formatVnd(finalPriceM2)}/m²`;
    });
    document.querySelectorAll('[data-pdp-price-old]').forEach((el) => {
      el.textContent = formatVnd(finalOldM2);
    });
    document.querySelectorAll('[data-pdp-price-piece]').forEach((el) => {
      el.textContent = `${formatVnd(finalPricePiece)}/viên`;
    });

    const calcEl = document.querySelector('drv-unit-calc');
    if (calcEl && typeof calcEl.refreshConfig === 'function') {
      calcEl.dataset.sku = finalSku;
      calcEl.dataset.priceM2 = String(finalPriceM2);
      calcEl.dataset.pieceW = String(pieceW);
      calcEl.dataset.pieceH = String(pieceH);
      calcEl.dataset.piecesPerBox = String(piecesBox);
      calcEl.dataset.weightPerBox = String(weightBox);
      calcEl.refreshConfig(true);
    }
  });

  // Handle PDP Sibling Variant Switcher ([data-pdp-variants])
  document.addEventListener('click', (event) => {
    const variantBtn = event.target.closest('[data-variant-type]');
    if (!variantBtn) return;
    const container = variantBtn.closest('[data-pdp-variants]');
    if (!container) return;

    event.preventDefault();
    const type = variantBtn.dataset.variantType;
    container.querySelectorAll(`[data-variant-type="${type}"]`).forEach((btn) => {
      const active = btn === variantBtn;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-checked', String(active));
    });

    const activeColor = container.querySelector('[data-variant-type="color"].is-active');
    const activeSize = container.querySelector('[data-variant-type="size"].is-active');
    const activeSurface = container.querySelector('[data-variant-type="surface"].is-active');

    const colorName = activeColor?.dataset.colorName || 'Trắng khói Calacatta';
    const colorCode = activeColor?.dataset.colorCode || '8801';
    const sizeLabel = activeSize?.dataset.sizeLabel || '80x80';
    const pieceW = Number(activeSize?.dataset.pieceW || 800);
    const pieceH = Number(activeSize?.dataset.pieceH || 800);
    const piecesBox = Number(activeSize?.dataset.piecesBox || 3);
    const weightBox = Number(activeSize?.dataset.weightBox || 42);
    const basePriceM2 = Number(activeSize?.dataset.priceM2 || 385000);
    const baseOldM2 = Number(activeSize?.dataset.priceOld || 450000);
    const surfaceDelta = Number(activeSurface?.dataset.priceDelta || 0);
    const surfaceSuffix = activeSurface?.dataset.surfaceSuffix || 'M';

    const finalPriceM2 = basePriceM2 + surfaceDelta;
    const finalOldM2 = baseOldM2 + surfaceDelta;
    const areaPiece = (pieceW * pieceH) / 1_000_000;
    const finalPricePiece = Math.round(finalPriceM2 * areaPiece);
    const sizePrefix = sizeLabel === '60x60' ? '66' : sizeLabel === '60x120' ? '612' : '88';
    const finalSku = `DRV-${sizePrefix}${colorCode.slice(-2)}${surfaceSuffix}`;
    const fullTitle = `Gạch lát nền Dravit Vân Đá Marble ${colorName} ${sizeLabel} ${finalSku}`;

    const colorLabelEl = container.querySelector('[data-variant-color-name]');
    if (colorLabelEl) colorLabelEl.textContent = colorName;

    document.querySelectorAll('[data-pdp-sku]').forEach((el) => {
      el.textContent = finalSku;
    });
    document.querySelectorAll('[data-pdp-sku-copy]').forEach((el) => {
      el.dataset.drvValue = finalSku;
    });
    document.querySelectorAll('[data-pdp-title-text]').forEach((el) => {
      el.textContent = fullTitle;
    });
    document.querySelectorAll('[data-pdp-price-m2]').forEach((el) => {
      el.textContent = `${formatVnd(finalPriceM2)}/m²`;
    });
    document.querySelectorAll('[data-pdp-price-old]').forEach((el) => {
      el.textContent = formatVnd(finalOldM2);
    });
    document.querySelectorAll('[data-pdp-price-piece]').forEach((el) => {
      el.textContent = `${formatVnd(finalPricePiece)}/viên`;
    });

    const calcEl = document.querySelector('drv-unit-calc');
    if (calcEl && typeof calcEl.refreshConfig === 'function') {
      calcEl.dataset.sku = finalSku;
      calcEl.dataset.priceM2 = String(finalPriceM2);
      calcEl.dataset.pieceW = String(pieceW);
      calcEl.dataset.pieceH = String(pieceH);
      calcEl.dataset.piecesPerBox = String(piecesBox);
      calcEl.dataset.weightPerBox = String(weightBox);
      calcEl.refreshConfig(true);
    }
  });

  if (!customElements.get('drv-unit-calc')) {
    customElements.define('drv-unit-calc', DrvUnitCalc);
  }
  if (!customElements.get('drv-qty-input')) {
    customElements.define('drv-qty-input', DrvQtyInput);
  }
})();

