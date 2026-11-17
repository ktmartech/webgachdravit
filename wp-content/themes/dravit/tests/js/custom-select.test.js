import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  initCustomSelects,
  resetCustomSelectsForTests,
} from '../../assets/src/js/chunks/custom-select.js';
import { fillWardSelect } from '../../assets/src/js/checkout.js';

afterEach(() => {
  resetCustomSelectsForTests();
  document.body.innerHTML = '';
});

describe('Global Custom Select & Popover Dropdown (D-37)', () => {
  it('upgrades .drv-select into accessible combobox + .drv-dropdown-popover while keeping FormData intact', () => {
    document.body.innerHTML = `
      <form id="f">
        <div class="drv-field">
          <label for="billing_state" class="drv-label">
            <span>Tỉnh / Thành phố <span class="drv-label__req">*</span></span>
          </label>
          <select id="billing_state" name="billing_state" class="drv-select" required>
            <option value="">Chọn Tỉnh / Thành phố</option>
            <option value="HCM">TP. Hồ Chí Minh</option>
            <option value="DT">Đồng Tháp</option>
          </select>
        </div>
      </form>
    `;

    initCustomSelects(document);

    const form = document.getElementById('f');
    const select = document.getElementById('billing_state');
    const wrap = select.closest('.drv-select-ui');
    const btn = wrap?.querySelector('.drv-select-ui__btn');
    const popover = wrap?.querySelector('.drv-dropdown-popover');
    const title = wrap?.querySelector('.drv-dropdown-popover__title');

    expect(wrap).not.toBeNull();
    expect(btn?.getAttribute('role')).toBe('combobox');
    expect(btn?.classList.contains('is-placeholder')).toBe(true);
    expect(title?.textContent).toBe('Tỉnh / Thành phố:');
    expect(popover?.hidden).toBe(true);

    const onChange = vi.fn();
    select.addEventListener('change', onChange);

    btn.click();
    expect(wrap.classList.contains('is-open')).toBe(true);
    expect(btn.getAttribute('aria-expanded')).toBe('true');

    const dtBtn = [...wrap.querySelectorAll('.drv-dropdown-popover__item')].find(
      (el) => el.dataset.optValue === 'DT',
    );
    dtBtn.click();

    expect(select.value).toBe('DT');
    expect(new FormData(form).get('billing_state')).toBe('DT');
    expect(btn.querySelector('.drv-select-ui__label')?.textContent).toBe('Đồng Tháp');
    expect(btn.classList.contains('is-placeholder')).toBe(false);
    expect(wrap.classList.contains('is-open')).toBe(false);
    expect(onChange).toHaveBeenCalledTimes(1);
  });

  it('syncs programmatic .value, .disabled, aria-invalid and dynamic ward options (fillWardSelect)', async () => {
    document.body.innerHTML = `
      <div class="drv-field">
        <label for="billing_city" class="drv-label">Phường / Xã</label>
        <select id="billing_city" name="billing_city" class="drv-select" disabled required>
          <option value="">Chọn Phường / Xã</option>
        </select>
      </div>
    `;

    initCustomSelects(document);
    const ward = document.getElementById('billing_city');
    const btn = ward.parentElement.querySelector('.drv-select-ui__btn');
    expect(btn.disabled).toBe(true);

    const wards = Array.from({ length: 12 }, (_, idx) => ({
      code: `DT-${idx + 1}`,
      name: idx === 4 ? 'Phường Cao Lãnh' : `Phường Mẫu ${idx + 1}`,
    }));
    fillWardSelect(ward, wards);

    // Đợi MutationObserver microtask đồng bộ danh sách phường mới
    await Promise.resolve();

    expect(btn.disabled).toBe(false);
    expect(ward.value).toBe('');

    // Kiểm tra tìm kiếm không dấu tiếng Việt trong popover (>= 10 options)
    btn.click();
    const searchInput = ward.parentElement.querySelector('.drv-dropdown-popover__search-input');
    expect(searchInput.closest('.drv-dropdown-popover__search').hidden).toBe(false);

    searchInput.value = 'cao lanh';
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));

    const visibleItems = [...ward.parentElement.querySelectorAll('.drv-dropdown-popover__item')].filter(
      (el) => !el.hidden,
    );
    expect(visibleItems).toHaveLength(1);
    expect(visibleItems[0].textContent).toBe('Phường Cao Lãnh');

    // Nhấn Enter chọn mục đang khớp
    searchInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(ward.value).toBe('Phường Cao Lãnh');
    expect(btn.querySelector('.drv-select-ui__label')?.textContent).toBe('Phường Cao Lãnh');

    // Gán trực tiếp `.value` bằng JS (như catalog.js / quote.js) và `aria-invalid`
    ward.value = '';
    expect(btn.querySelector('.drv-select-ui__label')?.textContent).toBe('Chọn Phường / Xã');

    ward.setAttribute('aria-invalid', 'true');
    await Promise.resolve();
    expect(btn.getAttribute('aria-invalid')).toBe('true');
  });

  it('supports keyboard navigation and closes on Escape without bubbling to parent dialog', () => {
    document.body.innerHTML = `
      <select id="sort" class="drv-select" aria-label="Sắp xếp">
        <option value="moi-ve">Mới về</option>
        <option value="gia-tang">Giá thấp đến cao</option>
        <option value="gia-giam">Giá cao đến thấp</option>
      </select>
    `;

    initCustomSelects(document);
    const select = document.getElementById('sort');
    const wrap = select.closest('.drv-select-ui');
    const btn = wrap.querySelector('.drv-select-ui__btn');

    btn.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    expect(wrap.classList.contains('is-open')).toBe(true);

    btn.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    btn.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(select.value).toBe('gia-tang');
    expect(wrap.classList.contains('is-open')).toBe(false);

    btn.click();
    expect(wrap.classList.contains('is-open')).toBe(true);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    expect(wrap.classList.contains('is-open')).toBe(false);
  });
});
