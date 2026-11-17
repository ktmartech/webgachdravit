/**
 * Entry Checkout VN `/thanh-toan` (05-checkout.html, docs/04 §5, docs/09 C-1, C-2, C-12).
 * - Tỉnh/TP → nạp lười `assets/data/wards/{KEY}.json`, KHÔNG tự chọn sẵn phường đầu tiên (C-1).
 * - Validate inline (SĐT VN, MST, email tùy chọn, VAT, điều khoản) và chặn submit khi lỗi (C-2).
 * - Không viết jQuery: WooCommerce `checkout.js` vẫn xử lý submit AJAX + fragment `update_order_review`;
 *   module này chặn trước ở capture phase khi form còn lỗi và đồng bộ tóm tắt mobile qua MutationObserver.
 */
import { qs, qsa } from './utils/dom.js';

export const PHONE_RE = /^0(3|5|7|8|9)\d{8}$/;
export const TAX_ID_RE = /^\d{10}(-\d{3})?$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/** Đồng bộ `CheckoutValidator::normalizePhone()` phía PHP. */
export function normalizePhone(raw) {
  let digits = String(raw ?? '').trim().replace(/[^\d+]/g, '');
  if (digits.startsWith('+84')) {
    digits = `0${digits.slice(3)}`;
  } else if (digits.startsWith('84') && digits.length === 11) {
    digits = `0${digits.slice(2)}`;
  }
  return digits.replace(/\D/g, '');
}

export function isValidPhone(raw) {
  return PHONE_RE.test(normalizePhone(raw));
}

export function isValidTaxId(raw) {
  return TAX_ID_RE.test(String(raw ?? '').trim());
}

/**
 * Trả về thông điệp lỗi của 1 trường (chuỗi rỗng = hợp lệ).
 * @param {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} field
 * @param {{vatOn?: boolean}} ctx
 */
export function fieldError(field, { vatOn = false } = {}) {
  if (!field) {
    return '';
  }
  // Ô Phường/Xã bị khóa khi chưa chọn Tỉnh vẫn phải báo lỗi bắt buộc.
  if (field.disabled && field.id !== 'billing_city') {
    return '';
  }
  const value = field.type === 'checkbox' ? (field.checked ? '1' : '') : String(field.value || '').trim();
  const required = field.hasAttribute('required') || (vatOn && field.hasAttribute('data-vat-required'));
  const empty = field.dataset.errorEmpty || 'Vui lòng điền thông tin này.';
  const pattern = field.dataset.errorPattern || 'Giá trị chưa hợp lệ.';

  if (value === '') {
    return required ? empty : '';
  }
  if (field.hasAttribute('data-drv-phone') && !isValidPhone(value)) {
    return pattern;
  }
  if (field.hasAttribute('data-drv-tax-id') && !isValidTaxId(value)) {
    return pattern;
  }
  if (field.type === 'email' && !EMAIL_RE.test(value)) {
    return pattern;
  }
  return '';
}

function errorEl(field) {
  return field.id ? document.getElementById(`err-${field.id}`) : null;
}

export function paintError(field, message) {
  const err = errorEl(field);
  const row = field.closest('.form-row, .drv-field');
  field.setAttribute('aria-invalid', message ? 'true' : 'false');
  row?.classList.toggle('is-invalid', Boolean(message));
  if (err) {
    err.textContent = message;
    err.hidden = !message;
  }
}

const wardCache = new Map();

async function loadWards(base, key) {
  if (wardCache.has(key)) {
    return wardCache.get(key);
  }
  const res = await fetch(`${base}/${encodeURIComponent(key)}.json`, { credentials: 'same-origin' });
  if (!res.ok) {
    throw new Error(`wards ${res.status}`);
  }
  const data = await res.json();
  const list = Array.isArray(data?.wards) ? data.wards : [];
  wardCache.set(key, list);
  return list;
}

/** Dựng lại `<select>` Phường/Xã: placeholder trước, KHÔNG chọn sẵn phường nào (C-1). */
export function fillWardSelect(select, wards, keepName = '') {
  select.replaceChildren();
  const placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = 'Chọn Phường / Xã';
  select.append(placeholder);
  for (const w of wards) {
    const opt = document.createElement('option');
    opt.value = String(w.name);
    opt.textContent = String(w.name);
    opt.dataset.code = String(w.code);
    if (keepName && keepName === w.name) {
      opt.selected = true;
    }
    select.append(opt);
  }
  if (!keepName) {
    select.value = '';
  }
  select.disabled = wards.length === 0;
}

function syncWardCode(form) {
  const ward = qs('#billing_city', form);
  const code = qs('[data-drv-ward-code]', form);
  if (ward && code) {
    code.value = ward.selectedOptions?.[0]?.dataset.code || '';
  }
}

function setupAddress(form) {
  const province = qs('[data-drv-province]', form);
  const ward = qs('[data-drv-ward]', form);
  if (!province || !ward) {
    return;
  }
  const base = form.dataset.wardsBase || '/wp-content/themes/dravit/assets/data/wards';
  let hints = { map: {}, default: '' };
  try {
    hints = JSON.parse(form.dataset.zoneHints || '{}');
  } catch {
    // giữ mặc định
  }
  const status = qs('[data-ward-status]', form);
  const zone = qs('[data-checkout-shipping-zone]');

  province.addEventListener('change', async () => {
    const key = province.value;
    const label = province.selectedOptions?.[0]?.textContent?.trim() || '';
    if (zone) {
      zone.textContent = key ? hints.map?.[key] || hints.default || '' : 'Chọn Tỉnh / Thành phố để xem tuyến giao dự kiến.';
    }
    for (const el of qsa('[data-checkout-province-name]')) {
      el.textContent = label || 'Chưa chọn';
    }
    fillWardSelect(ward, []);
    syncWardCode(form);
    if (!key) {
      if (status) status.textContent = 'Chọn Tỉnh/TP trước';
      return;
    }
    if (status) status.textContent = 'Đang tải danh sách...';
    ward.setAttribute('aria-busy', 'true');
    try {
      const list = await loadWards(base, key);
      if (province.value !== key) {
        return;
      }
      fillWardSelect(ward, list);
      if (status) status.textContent = `${list.length} phường/xã`;
    } catch {
      if (status) status.textContent = 'Không tải được danh sách, vui lòng thử lại';
    } finally {
      ward.removeAttribute('aria-busy');
    }
  });

  ward.addEventListener('change', () => {
    syncWardCode(form);
    paintError(ward, '');
  });
  syncWardCode(form);
}

function setupVat(form) {
  const toggle = qs('#require_vat_invoice', form);
  const box = qs('[data-drv-vat-fields]', form);
  if (!toggle || !box) {
    return;
  }
  const sync = () => {
    box.hidden = !toggle.checked;
    toggle.setAttribute('aria-expanded', toggle.checked ? 'true' : 'false');
    if (!toggle.checked) {
      for (const f of qsa('input', box)) {
        paintError(f, '');
      }
    }
  };
  toggle.addEventListener('change', sync);
  sync();
}

function setupNoteChips(form) {
  const area = qs('#order_comments', form);
  if (!area) {
    return;
  }
  const parts = () =>
    area.value
      .split(';')
      .map((s) => s.trim())
      .filter(Boolean);
  const syncPressed = () => {
    const current = parts();
    for (const chip of qsa('[data-drv-note-chip]', form)) {
      const on = current.includes(chip.dataset.drvNoteChip || '');
      chip.setAttribute('aria-pressed', on ? 'true' : 'false');
      chip.classList.toggle('is-active', on);
    }
  };
  form.addEventListener('click', (event) => {
    const chip = event.target instanceof Element ? event.target.closest('[data-drv-note-chip]') : null;
    if (!chip) {
      return;
    }
    event.preventDefault();
    const text = chip.dataset.drvNoteChip || '';
    const list = parts();
    const idx = list.indexOf(text);
    if (idx >= 0) {
      list.splice(idx, 1);
    } else {
      list.push(text);
    }
    area.value = list.join('; ');
    syncPressed();
  });
  area.addEventListener('input', syncPressed);
  syncPressed();
}

function validatableFields(form) {
  return qsa('input[data-error-empty], input[data-error-pattern], select[data-error-empty], #terms', form).filter(
    (f) => !f.closest('[hidden]'),
  );
}

export function validateForm(form) {
  const vatOn = Boolean(qs('#require_vat_invoice', form)?.checked);
  let first = null;
  for (const field of validatableFields(form)) {
    const msg = fieldError(field, { vatOn });
    paintError(field, msg);
    if (msg && !first) {
      first = field;
    }
  }
  return first;
}

function setupValidation(form) {
  form.addEventListener(
    'blur',
    (event) => {
      const field = event.target;
      if (field instanceof HTMLElement && (field.dataset.errorEmpty || field.dataset.errorPattern || field.id === 'terms')) {
        const vatOn = Boolean(qs('#require_vat_invoice', form)?.checked);
        paintError(field, fieldError(field, { vatOn }));
      }
    },
    true,
  );
  form.addEventListener('change', (event) => {
    const field = event.target;
    if (field instanceof HTMLInputElement && field.type === 'checkbox' && field.id === 'terms') {
      paintError(field, fieldError(field));
    }
  });

  // Capture phase tại document: chạy TRƯỚC handler submit (jQuery) của WooCommerce trên form.
  document.addEventListener(
    'submit',
    (event) => {
      if (event.target !== form) {
        return;
      }
      const first = validateForm(form);
      if (first) {
        event.preventDefault();
        event.stopImmediatePropagation();
        first.focus({ preventScroll: true });
        first.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
    },
    true,
  );
}

function setupPaymentSummary(form) {
  form.addEventListener('change', (event) => {
    const radio = event.target;
    if (!(radio instanceof HTMLInputElement) || radio.name !== 'payment_method') {
      return;
    }
    const label = radio.value === 'cod' ? 'COD - thanh toán khi nhận hàng' : 'Chuyển khoản VietQR (sau khi chốt đơn)';
    for (const el of qsa('[data-checkout-pay-summary]')) {
      el.textContent = label;
    }
  });
}

/** Đồng bộ tóm tắt thu gọn mobile + thanh CTA dính đáy từ fragment `review-order.php`. */
export function syncMobileSummary() {
  const review = qs('[data-drv-review]');
  if (!review) {
    return;
  }
  const totalText = qs('[data-review-total]', review)?.textContent?.trim() || '';
  for (const el of qsa('[data-checkout-mini-total]')) {
    if (totalText) {
      el.textContent = totalText;
    }
  }
  const label = qs('[data-checkout-mini-label]');
  if (label) {
    label.textContent = `Đơn hàng: ${review.dataset.lineCount || '0'} mã · ${review.dataset.totalM2 || '0'} m²`;
  }
  const target = qs('[data-checkout-mini-content]');
  const items = qs('.drv-checkout-items', review);
  if (target && items) {
    const clone = items.cloneNode(true);
    for (const img of qsa('img', clone)) {
      img.loading = 'lazy';
    }
    target.replaceChildren(clone);
  }
}

export function initCheckout() {
  const form = qs('[data-drv-checkout]');
  if (!form || form.dataset.drvCheckoutBound === '1') {
    return;
  }
  form.dataset.drvCheckoutBound = '1';

  setupAddress(form);
  setupVat(form);
  setupNoteChips(form);
  setupValidation(form);
  setupPaymentSummary(form);

  syncMobileSummary();
  const reviewRoot = qs('#order_review');
  if (reviewRoot && typeof MutationObserver !== 'undefined') {
    new MutationObserver(() => syncMobileSummary()).observe(reviewRoot, { childList: true, subtree: false });
  }
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCheckout, { once: true });
  } else {
    initCheckout();
  }
}
