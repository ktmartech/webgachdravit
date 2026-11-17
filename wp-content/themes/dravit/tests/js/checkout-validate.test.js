import { afterEach, describe, expect, it } from 'vitest';
import {
  fieldError,
  fillWardSelect,
  isValidPhone,
  isValidTaxId,
  normalizePhone,
  validateForm,
} from '../../assets/src/js/checkout.js';

describe('Checkout VN — parity với CheckoutValidator PHP', () => {
  it('chuẩn hóa & kiểm SĐT VN (C-2)', () => {
    expect(normalizePhone('+84 909-385-880')).toBe('0909385880');
    expect(isValidPhone('0909 385 880')).toBe(true);
    expect(isValidPhone('84362123456')).toBe(true);
    expect(isValidPhone('0281234567')).toBe(false);
    expect(isValidPhone('090938588')).toBe(false);
  });

  it('mã số thuế 10 số hoặc 10-3 (C-12)', () => {
    expect(isValidTaxId('0318688999')).toBe(true);
    expect(isValidTaxId('0318688999-001')).toBe(true);
    expect(isValidTaxId('0318688999001')).toBe(false);
  });
});

describe('DOM checkout', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('dựng select Phường/Xã KHÔNG tự chọn sẵn phường đầu tiên (C-1)', () => {
    document.body.innerHTML = '<select id="billing_city"></select>';
    const select = document.getElementById('billing_city');
    fillWardSelect(select, [
      { code: '26734', name: 'Phường Tân Định' },
      { code: '26740', name: 'Phường Sài Gòn' },
    ]);
    expect(select.options).toHaveLength(3);
    expect(select.value).toBe('');
    expect(select.options[1].dataset.code).toBe('26734');
    expect(select.disabled).toBe(false);
  });

  it('email để trống hợp lệ (C-3), sai định dạng thì báo lỗi', () => {
    document.body.innerHTML = '<input id="billing_email" type="email" data-error-pattern="Sai email">';
    const email = document.getElementById('billing_email');
    expect(fieldError(email)).toBe('');
    email.value = 'abc@';
    expect(fieldError(email)).toBe('Sai email');
  });

  it('validateForm chặn submit khi thiếu SĐT / terms chưa tick, VAT chỉ bắt buộc khi bật', () => {
    document.body.innerHTML = `
      <form>
        <div class="drv-field"><input id="billing_phone" required data-drv-phone data-error-empty="Nhập SĐT" data-error-pattern="SĐT sai"><span id="err-billing_phone" hidden></span></div>
        <input type="checkbox" id="require_vat_invoice">
        <div id="vat"><input id="vat_tax_id" data-vat-required data-drv-tax-id data-error-empty="Nhập MST" data-error-pattern="MST sai"><span id="err-vat_tax_id" hidden></span></div>
        <input type="checkbox" id="terms" required data-error-empty="Đồng ý điều khoản"><span id="err-terms" hidden></span>
      </form>`;
    const form = document.querySelector('form');
    let first = validateForm(form);
    expect(first?.id).toBe('billing_phone');
    expect(document.getElementById('err-billing_phone').textContent).toBe('Nhập SĐT');
    expect(document.getElementById('err-vat_tax_id').hidden).toBe(true);

    document.getElementById('billing_phone').value = '0909385880';
    document.getElementById('terms').checked = true;
    expect(validateForm(form)).toBeNull();

    document.getElementById('require_vat_invoice').checked = true;
    first = validateForm(form);
    expect(first?.id).toBe('vat_tax_id');
  });
});
