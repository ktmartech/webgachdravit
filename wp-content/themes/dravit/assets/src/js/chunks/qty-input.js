/**
 * `<drv-qty-input>` — Bộ chỉnh số lượng Giỏ hàng & Mini-cart (docs/15 §5.2, D-24).
 * Light DOM: PHP/JS render sẵn `.drv-stepper` + `input[type=number]`; element chỉ gắn hành vi.
 *
 * - Giá trị luôn là SỐ NGUYÊN VIÊN; `- / +` nhảy theo `data-qty-step` (box = viên/thùng, piece = 1).
 * - `round_mode = box`: blur/nhập tay tự làm tròn LÊN chẵn thùng; chặn trần tồn kho `data-max-stock`.
 * - Có `data-cart-key`: debounce 400ms rồi gọi Store API `update-item`; lỗi → hoàn về giá trị cũ + toast.
 * - Sự kiện: `drv:qty-input` (tức thì, để row cập nhật quy đổi lạc quan), `drv:qty-change` (sau khi chốt).
 */
import { updateItem, errorText } from '../utils/cart-api.js';
import { showToast } from '../utils/toast.js';

export const QTY_DEBOUNCE_MS = 400;

/**
 * Hàm thuần chuẩn hóa số viên theo cấu hình SKU.
 * @param {unknown} raw
 * @param {{min?: number, step?: number, max?: number|null, roundMode?: string}} opts
 */
export function normalizeQty(raw, { min = 1, step = 1, max = null, roundMode = 'box' } = {}) {
  const safeStep = Math.max(1, Math.trunc(Number(step) || 1));
  const safeMin = Math.max(1, Math.trunc(Number(min) || 1));
  const parsed = typeof raw === 'number' ? raw : Number(String(raw ?? '').replace(/[^\d.-]/g, ''));
  let qty = Math.trunc(parsed);
  if (!Number.isFinite(qty) || qty < safeMin) {
    qty = safeMin;
  }
  if (roundMode === 'box' && safeStep > 1) {
    qty = Math.ceil(qty / safeStep) * safeStep;
  }
  const cap = max === null || max === undefined || max === '' ? null : Math.trunc(Number(max));
  if (cap !== null && Number.isFinite(cap) && cap > 0 && qty > cap) {
    const floored = roundMode === 'box' && safeStep > 1 ? Math.floor(cap / safeStep) * safeStep : cap;
    qty = Math.max(safeMin, floored);
  }
  return qty;
}

export class DrvQtyInput extends HTMLElement {
  connectedCallback() {
    if (this.dataset.drvBound === '1') {
      return;
    }
    this.dataset.drvBound = '1';
    this.committed = this.value;
    this.timer = 0;

    this.addEventListener('click', (event) => {
      const btn = event.target instanceof Element ? event.target.closest('[data-qty-step]') : null;
      if (btn && this.contains(btn)) {
        event.preventDefault();
        this.stepBy(Number(btn.getAttribute('data-qty-step')) < 0 ? -1 : 1);
        return;
      }
      const boxBtn = event.target instanceof Element ? event.target.closest('[data-qty-add-box]') : null;
      if (boxBtn && this.contains(boxBtn)) {
        event.preventDefault();
        this.addBox();
      }
    });

    const input = this.input;
    if (input) {
      input.addEventListener('input', () => this.emitPreview(Number(input.value) || 0));
      input.addEventListener('change', () => this.setValue(input.value));
      input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          this.setValue(input.value);
        }
      });
    }
  }

  disconnectedCallback() {
    window.clearTimeout(this.timer);
  }

  get input() {
    return this.querySelector('input[type="number"]');
  }

  get config() {
    const d = this.dataset;
    const ppb = Math.max(1, Number.parseInt(d.piecesPerBox || '1', 10) || 1);
    const roundMode = d.roundMode === 'piece' ? 'piece' : 'box';
    const step = Math.max(1, Number.parseInt(d.qtyStep || (roundMode === 'box' ? String(ppb) : '1'), 10) || 1);
    return {
      key: d.cartKey || '',
      ppb,
      roundMode,
      step,
      min: Math.max(1, Number.parseInt(d.qtyMin || String(step), 10) || step),
      max: d.maxStock !== undefined && d.maxStock !== '' ? Number.parseInt(d.maxStock, 10) : null,
      unitLabel: d.unitLabel || 'viên',
    };
  }

  get value() {
    return Math.max(0, Number.parseInt(this.input?.value || '0', 10) || 0);
  }

  stepBy(direction) {
    const { step } = this.config;
    this.setValue(this.value + direction * step);
  }

  addBox() {
    this.setValue(this.value + this.config.ppb);
  }

  roundToBox() {
    const { ppb } = this.config;
    this.setValue(Math.ceil(this.value / ppb) * ppb);
  }

  setValue(raw, { silent = false } = {}) {
    const cfg = this.config;
    const next = normalizeQty(raw, cfg);
    if (cfg.max !== null && Number(raw) > cfg.max && !silent) {
      showToast(`Tồn kho hiện còn ${cfg.max} ${cfg.unitLabel}.`, 'warning', 3600);
    }
    if (this.input) {
      this.input.value = String(next);
    }
    this.emitPreview(next);
    if (!silent) {
      this.scheduleCommit();
    }
    return next;
  }

  emitPreview(quantity) {
    this.dispatchEvent(
      new CustomEvent('drv:qty-input', { bubbles: true, detail: { key: this.config.key, quantity } }),
    );
  }

  scheduleCommit() {
    window.clearTimeout(this.timer);
    this.timer = window.setTimeout(() => this.commit(), QTY_DEBOUNCE_MS);
  }

  async commit() {
    const quantity = this.value;
    if (quantity === this.committed) {
      return;
    }
    const { key } = this.config;
    this.dispatchEvent(new CustomEvent('drv:qty-change', { bubbles: true, detail: { key, quantity } }));
    if (!key || this.hasAttribute('data-no-sync')) {
      this.committed = quantity;
      return;
    }

    this.setAttribute('aria-busy', 'true');
    try {
      const result = await updateItem(key, quantity);
      if (!result.ok) {
        showToast(errorText(result), 'error', 4200);
        this.setValue(this.committed, { silent: true });
        return;
      }
      this.committed = quantity;
    } catch {
      showToast('Mất kết nối. Số lượng chưa được lưu.', 'error', 4200);
      this.setValue(this.committed, { silent: true });
    } finally {
      this.removeAttribute('aria-busy');
    }
  }
}

export function defineQtyInput() {
  if (typeof customElements !== 'undefined' && !customElements.get('drv-qty-input')) {
    customElements.define('drv-qty-input', DrvQtyInput);
  }
}

defineQtyInput();
