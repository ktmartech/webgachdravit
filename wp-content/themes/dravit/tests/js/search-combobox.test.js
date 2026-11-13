import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  highlightSafeText,
  initLiveSearch,
  resetSearchForTests,
  setSearchNavigateHandler,
} from '../../assets/src/js/search.js';

function renderSearchMarkup() {
  document.body.innerHTML = `
    <header class="drv-header">
      <div class="drv-search" data-drv-search>
        <div class="drv-search-backdrop" data-drv-search-backdrop hidden></div>
        <form class="drv-search__form" action="/" method="get" role="search">
          <input type="hidden" name="post_type" value="product">
          <input id="global-search-input"
                 name="s"
                 type="search"
                 class="drv-search__input"
                 role="combobox"
                 aria-autocomplete="list"
                 aria-expanded="false"
                 aria-controls="drv-search-panel"
                 aria-activedescendant=""
                 data-search-input>
          <button type="button" class="drv-search__clear" data-search-clear hidden>X</button>
          <button type="submit">Tìm</button>
        </form>
        <div id="drv-search-panel" class="drv-search-drop" role="listbox" data-search-panel hidden>
          <span data-search-silo-heading></span>
          <ul data-search-silos></ul>
          <span data-search-prod-heading></span>
          <a href="/gach" data-search-view-all>Xem toàn bộ kho</a>
          <ul data-search-products></ul>
          <p aria-live="polite" data-search-status></p>
        </div>
      </div>
    </header>
  `;
}

describe('Step 3 Live Search Combobox (Debounce, AbortController, ARIA Keyboard & XSS Guard)', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    resetSearchForTests();
    renderSearchMarkup();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it('debounces typing by 200ms and aborts superseded in-flight requests via AbortController', async () => {
    const signals = [];
    globalThis.fetch = vi.fn().mockImplementation((url, opts) => {
      signals.push({ url, signal: opts.signal });
      return new Promise((resolve, reject) => {
        opts.signal.addEventListener('abort', () => {
          const abortErr = new Error('Aborted');
          abortErr.name = 'AbortError';
          reject(abortErr);
        });
        if (String(url).includes('80x80')) {
          resolve({
            ok: true,
            json: async () => ({
              query: 'da mo 80x80',
              silos: [{ slug: 'gach-80x80', name: 'Gạch 80x80', url: '/gach-80x80', count: 12 }],
              products: [
                {
                  id: 101,
                  sku: 'DRV-8801M',
                  name: 'Gạch Đá Mờ Calacatta 80x80',
                  url: '/san-pham/gach-da-mo-80x80',
                  thumb: '/img/a.webp',
                  size: '80x80 cm',
                  surface_label: 'Matt',
                  price_state: 'priced',
                  price_m2: 385000,
                },
              ],
              total: 1,
              view_all: '/?s=da+mo+80x80&post_type=product',
            }),
          });
        }
      });
    });

    initLiveSearch();
    const input = document.getElementById('global-search-input');

    // Gõ lần 1 chưa hết 200ms rồi gõ tiếp -> chỉ 1 request cho từ khóa đầu sau 200ms
    input.value = 'da';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    vi.advanceTimersByTime(100);
    expect(globalThis.fetch).toHaveBeenCalledTimes(0);

    input.value = 'da mo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    vi.advanceTimersByTime(200);
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    expect(signals[0].signal.aborted).toBe(false);

    // Khi request 1 đang treo, gõ tiếp "da mo 80x80" -> request 1 bị abort ngay khi request 2 phát đi
    input.value = 'da mo 80x80';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    vi.advanceTimersByTime(200);

    expect(globalThis.fetch).toHaveBeenCalledTimes(2);
    expect(signals[0].signal.aborted).toBe(true);

    await vi.runAllTimersAsync();
    await Promise.resolve();

    const panel = document.getElementById('drv-search-panel');
    expect(panel.hidden).toBe(false);
    expect(input.getAttribute('aria-expanded')).toBe('true');
    expect(panel.querySelectorAll('[role="option"]').length).toBe(2);
  });

  it('supports WAI-ARIA ArrowDown / ArrowUp / Enter / Escape keyboard navigation', async () => {
    const navigatedUrls = [];
    setSearchNavigateHandler((url) => navigatedUrls.push(url));

    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        query: '8801',
        silos: [{ slug: 'gach-lat-nen', name: 'Gạch Lát Nền', url: '/gach-lat-nen', count: 8 }],
        products: [
          {
            id: 101,
            sku: 'DRV-8801M',
            name: 'Gạch Marble 80x80',
            url: '/san-pham/drv-8801m',
            price_state: 'quote_only',
            price_m2: 0,
          },
        ],
        total: 1,
        view_all: '/?s=8801&post_type=product',
      }),
    });

    initLiveSearch();
    const input = document.getElementById('global-search-input');
    const panel = document.getElementById('drv-search-panel');

    input.value = '8801';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    vi.advanceTimersByTime(200);
    await Promise.resolve();
    await Promise.resolve();

    // Kiểm tra trạng thái quote_only hiển thị "Liên hệ báo giá", tuyệt đối không hiện 0đ
    expect(panel.textContent).toContain('Liên hệ báo giá');
    expect(panel.textContent).not.toContain('0đ');

    // ArrowDown lần 1 -> chọn Silo đầu tiên
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    expect(input.getAttribute('aria-activedescendant')).toBe('drv-search-opt-silo-0');

    // ArrowDown lần 2 -> chọn Product đầu tiên
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
    expect(input.getAttribute('aria-activedescendant')).toBe('drv-search-opt-prod-1');

    // ArrowUp -> quay lại Silo đầu tiên
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true }));
    expect(input.getAttribute('aria-activedescendant')).toBe('drv-search-opt-silo-0');

    // Enter -> điều hướng tới URL của option đang chọn và đóng panel
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    expect(navigatedUrls).toEqual(['/gach-lat-nen']);
    expect(panel.hidden).toBe(true);

    // Mở lại và bấm Escape -> đóng panel, xóa aria-activedescendant
    input.dispatchEvent(new Event('focus'));
    await Promise.resolve();
    expect(panel.hidden).toBe(false);
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    expect(panel.hidden).toBe(true);
    expect(input.getAttribute('aria-expanded')).toBe('false');
  });

  it('neutralizes XSS payloads in titles, queries, and javascript: URLs while highlighting diacritics safely', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        query: 'da mo',
        silos: [
          {
            slug: 'xss',
            name: '<img src=x onerror=alert(1)> Đá Mờ',
            url: 'javascript:alert(1)',
            count: 3,
          },
        ],
        products: [],
        total: 0,
        view_all: 'javascript:evil()',
      }),
    });

    initLiveSearch();
    const input = document.getElementById('global-search-input');
    const panel = document.getElementById('drv-search-panel');

    input.value = 'da mo';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    vi.advanceTimersByTime(200);
    await Promise.resolve();
    await Promise.resolve();

    // Không có bất kỳ thẻ <img> nào bị chèn trái phép vào danh sách Silo
    const siloList = panel.querySelector('[data-search-silos]');
    expect(siloList.querySelectorAll('img').length).toBe(0);
    expect(siloList.querySelector('a').getAttribute('href')).toBe('/gach');
    expect(panel.querySelector('[data-search-view-all]').getAttribute('href')).toBe('/gach');

    // Kiểm tra highlight không dấu chính xác trên tiếng Việt có dấu
    const span = document.createElement('span');
    highlightSafeText(span, 'Gạch Đá Mờ 80x80', 'da mo');
    const marks = Array.from(span.querySelectorAll('mark.drv-search-hl')).map((m) => m.textContent);
    expect(marks).toEqual(['Đá', 'Mờ']);
  });

  it('hydrates 0ms trending products & popular silos matching mockup UI and records click hits', () => {
    const beaconSpy = vi.fn().mockReturnValue(true);
    Object.defineProperty(navigator, 'sendBeacon', {
      value: beaconSpy,
      configurable: true,
    });

    window.DRV_CONFIG = {
      searchEndpoint: '/wp-json/dravit/v1/search',
      searchHitEndpoint: '/wp-json/dravit/v1/search/hit',
      gachUrl: '/gach',
      searchInitial: {
        query: '',
        mode: 'trending',
        silos: [
          { slug: 'gach-phong-khach', name: 'Gạch Phòng Khách', url: '/gach-phong-khach', count: 22 },
        ],
        products: [
          {
            id: 101,
            sku: 'DRV-8801M',
            name: 'Gạch Vân Đá Calacatta Gold 80x80 Bóng Kiếng',
            url: '/san-pham/drv-8801m',
            thumb: '/img/8801.webp',
            size: '80x80 cm',
            surface_label: 'Bóng kiếng',
            price_state: 'priced',
            price_m2: 385000,
            regular_price_m2: 385000,
            price_piece: 246400,
            unit_label: 'viên',
          },
        ],
        total: 1,
        view_all: '/gach',
      },
    };

    globalThis.fetch = vi.fn();
    initLiveSearch();

    const input = document.getElementById('global-search-input');
    const panel = document.getElementById('drv-search-panel');

    // Focus mở tức thì 0ms từ searchInitial mà không tốn 1 request fetch nào
    input.dispatchEvent(new Event('focus'));
    expect(globalThis.fetch).toHaveBeenCalledTimes(0);
    expect(panel.hidden).toBe(false);

    expect(panel.querySelector('[data-search-silo-heading]').textContent).toBe('Danh mục phổ biến');
    expect(panel.querySelector('[data-search-prod-heading]').textContent).toBe('Sản phẩm tìm kiếm nhiều');
    expect(panel.querySelector('[data-search-view-all]').textContent).toBe('Xem toàn bộ kho');
    expect(panel.querySelector('[data-search-silos] .drv-badge--outline').textContent).toBe('22 mẫu');

    const prodItem = panel.querySelector('.drv-search-item');
    expect(prodItem.querySelector('.drv-search-item__meta .drv-badge--outline').textContent).toBe('80x80');
    expect(prodItem.querySelector('.drv-search-item__meta').textContent).toContain('Mã: DRV-8801M');
    expect(prodItem.querySelector('.drv-search-item__meta').textContent).toContain('· Bóng kiếng');
    expect(prodItem.querySelector('.drv-search-item__price').textContent).toContain('385.000');
    expect(prodItem.querySelector('.drv-search-item__price').textContent).toContain('246.400');
    expect(prodItem.querySelector('.drv-search-item__price').textContent).toContain('/viên');

    // Click vào sản phẩm bắn beacon ghi nhận lượt click cho Tầng 2 (_drv_search_hits)
    prodItem.addEventListener('click', (e) => e.preventDefault());
    prodItem.click();
    expect(beaconSpy).toHaveBeenCalledTimes(1);
    expect(beaconSpy.mock.calls[0][0]).toBe('/wp-json/dravit/v1/search/hit');

    delete window.DRV_CONFIG;
  });
});

