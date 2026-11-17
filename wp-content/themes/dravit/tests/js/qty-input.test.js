import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../assets/src/js/utils/cart-api.js', () => ({
  updateItem: vi.fn(async (key, quantity) => ({ ok: true, status: 200, data: { items: [{ key, quantity }] } })),
  errorText: () => 'lỗi',
}));

const { normalizeQty, QTY_DEBOUNCE_MS } = await import('../../assets/src/js/chunks/qty-input.js');
const cartApi = await import('../../assets/src/js/utils/cart-api.js');

function mount({ roundMode = 'box', ppb = 3, step = 3, min = 3, max = '', value = 18, key = 'abc' } = {}) {
  document.body.innerHTML = `
    <drv-qty-input data-cart-key="${key}" data-round-mode="${roundMode}" data-pieces-per-box="${ppb}"
      data-qty-step="${step}" data-qty-min="${min}" ${max !== '' ? `data-max-stock="${max}"` : ''} data-unit-label="viên">
      <div class="drv-stepper">
        <button type="button" data-qty-step="-1">-</button>
        <input type="number" value="${value}">
        <button type="button" data-qty-step="1">+</button>
      </div>
    </drv-qty-input>`;
  const el = document.querySelector('drv-qty-input');
  return { el, input: el.querySelector('input'), prev: el.querySelectorAll('button')[0], next: el.querySelectorAll('button')[1] };
}

describe('normalizeQty (D-24 · số nguyên viên)', () => {
  it('box mode làm tròn LÊN chẵn thùng và giữ tối thiểu', () => {
    expect(normalizeQty(19, { min: 3, step: 3, roundMode: 'box' })).toBe(21);
    expect(normalizeQty(1, { min: 3, step: 3, roundMode: 'box' })).toBe(3);
    expect(normalizeQty('abc', { min: 3, step: 3, roundMode: 'box' })).toBe(3);
  });

  it('piece mode giữ nguyên số viên lẻ', () => {
    expect(normalizeQty(20, { min: 1, step: 1, roundMode: 'piece' })).toBe(20);
    expect(normalizeQty(20.7, { min: 1, step: 1, roundMode: 'piece' })).toBe(20);
  });

  it('chặn trần tồn kho theo bội số thùng', () => {
    expect(normalizeQty(30, { min: 3, step: 3, max: 25, roundMode: 'box' })).toBe(24);
    expect(normalizeQty(30, { min: 1, step: 1, max: 25, roundMode: 'piece' })).toBe(25);
  });
});

describe('<drv-qty-input> element', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    cartApi.updateItem.mockClear();
  });
  afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
  });

  it('nút +/- nhảy đúng 1 thùng ở box mode và gửi update-item sau debounce', async () => {
    const { input, next, prev } = mount();
    next.click();
    expect(input.value).toBe('21');
    prev.click();
    prev.click();
    expect(input.value).toBe('15');
    expect(cartApi.updateItem).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(QTY_DEBOUNCE_MS + 10);
    expect(cartApi.updateItem).toHaveBeenCalledTimes(1);
    expect(cartApi.updateItem).toHaveBeenCalledWith('abc', 15);
  });

  it('piece mode nhảy 1 viên, addBox cộng 1 thùng, roundToBox làm tròn lên', () => {
    const { el, input, next } = mount({ roundMode: 'piece', step: 1, min: 1, value: 19 });
    next.click();
    expect(input.value).toBe('20');
    el.roundToBox();
    expect(input.value).toBe('21');
    el.addBox();
    expect(input.value).toBe('24');
  });

  it('phát drv:qty-input để dòng giỏ cập nhật lạc quan', () => {
    const { el, next } = mount();
    const spy = vi.fn();
    el.addEventListener('drv:qty-input', (e) => spy(e.detail));
    next.click();
    expect(spy).toHaveBeenCalledWith({ key: 'abc', quantity: 21 });
  });

  it('không gọi Store API khi giá trị không đổi', async () => {
    const { next, prev } = mount();
    next.click();
    prev.click();
    await vi.advanceTimersByTimeAsync(QTY_DEBOUNCE_MS + 10);
    expect(cartApi.updateItem).not.toHaveBeenCalled();
  });
});
