import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerCatalogElements } from '../../assets/src/js/catalog.js';

function buildCatalogDomFixture({ debounceMs = 0 } = {}) {
  return `
    <main id="main-content" data-drv-catalog-page data-base="product_cat:gach-phong-khach" data-base-url="http://localhost:3000/gach-phong-khach">
      <div class="drv-silo-strip">
        <a class="drv-silo-tile" href="http://localhost:3000/gach-nha-tam">Gạch Nhà Tắm</a>
      </div>

      <section id="drv-catalog-section">
        <div id="drv-catalog-layout" class="l-split l-split--catalog">
          <aside id="drv-filter-sidebar" data-filter-desktop-slot>
            <drv-filter-form
              data-base="product_cat:gach-phong-khach"
              data-base-url="http://localhost:3000/gach-phong-khach"
              data-archive-name="Gạch Phòng Khách"
              data-per-page="2"
              data-total="4"
              data-debounce-ms="${debounceMs}"
            >
              <form id="drv-catalog-filter-form" method="get" action="http://localhost:3000/gach-phong-khach" data-filter-form-el>
                <div class="drv-filter-group drv-filter-group--showroom" data-facet-group="showroom">
                  <label class="drv-filter-showroom-card">
                    <input type="checkbox" name="showroom" value="1" data-facet-param="showroom" />
                    <span>Có mẫu thực tế tại kệ Showroom</span>
                  </label>
                </div>

                <details class="drv-filter-group" data-facet-group="kich-thuoc" open>
                  <summary>1. Kích thước (cm)</summary>
                  <div class="drv-filter-chips-grid">
                    <div class="drv-chip drv-facet-pill">
                      <input type="checkbox" id="flt-kich-thuoc-60x60" name="kich-thuoc[]" value="60x60" data-facet-param="kich-thuoc" />
                      <a href="http://localhost:3000/gach-phong-khach?kich-thuoc=60x60" data-facet-link data-target-input="flt-kich-thuoc-60x60">60x60</a>
                    </div>
                    <div class="drv-chip drv-facet-pill">
                      <input type="checkbox" id="flt-kich-thuoc-80x80" name="kich-thuoc[]" value="80x80" data-facet-param="kich-thuoc" />
                      <a href="http://localhost:3000/gach-phong-khach?kich-thuoc=80x80" data-facet-link data-target-input="flt-kich-thuoc-80x80">80x80</a>
                    </div>
                  </div>
                </details>

                <details class="drv-filter-group" data-facet-group="be-mat" open>
                  <summary>2. Bề mặt gạch</summary>
                  <div class="drv-filter-check-list">
                    <div class="drv-check">
                      <input type="checkbox" id="flt-be-mat-bong" name="be-mat[]" value="bong" data-facet-param="be-mat" />
                      <label for="flt-be-mat-bong">Bóng kiếng</label>
                    </div>
                    <div class="drv-check">
                      <input type="checkbox" id="flt-be-mat-mo" name="be-mat[]" value="mo" data-facet-param="be-mat" />
                      <label for="flt-be-mat-mo">Mờ mịn</label>
                    </div>
                  </div>
                </details>
              </form>
            </drv-filter-form>
          </aside>

          <div class="drv-catalog-main">
            <div class="drv-cat-toolbar" data-drv-catalog-toolbar>
              <button type="button" data-drv-toggle-sidebar aria-expanded="true">
                <span data-sidebar-toggle-label>Ẩn bộ lọc</span>
              </button>
              <button type="button" data-drv-open-filter-sheet aria-controls="drv-filter-sheet" aria-expanded="false">
                <span>Bộ lọc</span>
                <span data-mobile-filter-count hidden>0</span>
              </button>
              <p>
                <strong data-catalog-shown>2</strong> / <strong data-catalog-total>4</strong>
              </p>
              <div id="drv-catalog-live-status" role="status" aria-live="polite">Đã tìm thấy 4 sản phẩm.</div>
              <select id="drv-catalog-sort" name="sort" form="drv-catalog-filter-form" data-catalog-sort>
                <option value="moi-ve" selected>Mới cập nhật</option>
                <option value="gia-tang">Giá thấp đến cao</option>
              </select>
              <div id="drv-active-chips">
                <span>Đang xem toàn bộ mẫu gạch phòng khách</span>
              </div>
            </div>

            <div id="drv-product-grid" class="drv-catalog-grid" aria-busy="false">
              <article class="drv-card" data-product-id="101"><a href="/san-pham/drv-8801m">DRV-8801M</a></article>
              <article class="drv-card" data-product-id="102"><a href="/san-pham/drv-8802m">DRV-8802M</a></article>
            </div>

            <div id="drv-catalog-pagination" data-drv-pagination-wrap>
              <div class="drv-load-more-wrap" data-drv-load-more-wrap>
                <a href="http://localhost:3000/gach-phong-khach/page/2" rel="next" data-drv-load-more>Xem thêm 2 mẫu gạch</a>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <dialog id="drv-filter-sheet">
      <span data-drawer-active-badge>14 tiêu chí</span>
      <button type="button" data-filter-sheet-close>Đóng</button>
      <div id="drv-drawer-active-bar" hidden>
        <div data-drawer-active-chips></div>
      </div>
      <div data-filter-sheet-slot></div>
      <button type="button" data-filter-sheet-reset disabled>Thiết lập lại</button>
      <button type="button" data-filter-sheet-apply>
        Xem <strong data-sheet-preview-count>4</strong> mẫu gạch phù hợp
      </button>
    </dialog>
  `;
}

function buildMockGridPayload(overrides = {}) {
  return {
    total: 3,
    shown: 2,
    page: 1,
    per_page: 2,
    total_pages: 2,
    url: 'http://localhost:3000/gach-phong-khach?kich-thuoc=80x80',
    canonical: 'http://localhost:3000/gach-phong-khach',
    robots: 'noindex, follow',
    title: 'Gạch Phòng Khách 80x80 | DRAVIT',
    redirect_to: null,
    next_url: 'http://localhost:3000/gach-phong-khach/page/2?kich-thuoc=80x80',
    active_chips: [
      {
        param: 'kich-thuoc',
        group_label: 'Kích thước',
        value: '80x80',
        label: '80x80',
        remove_url: 'http://localhost:3000/gach-phong-khach',
      },
    ],
    facets: [],
    items_html: `
      <article class="drv-card" data-product-id="101"><a href="/san-pham/drv-8801m">DRV-8801M</a></article>
      <article class="drv-card" data-product-id="105"><a href="/san-pham/drv-8805m">DRV-8805M</a></article>
    `,
    facets_html: '',
    chips_html: `<button type="button" data-filter-remove-param="kich-thuoc" data-filter-remove-val="80x80">Kích thước: 80x80</button>`,
    ...overrides,
  };
}

async function flushPromises() {
  await new Promise((resolve) => setTimeout(resolve, 0));
  await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('Step 6 <drv-filter-form> Catalog Filter, Reparenting, AbortController, History & Load More', () => {
  beforeEach(() => {
    window.DRV_CONFIG = { catalogEndpoint: '/wp-json/dravit/v1/catalog' };
    window.sessionStorage.clear();
    window.history.replaceState(null, '', 'http://localhost:3000/gach-phong-khach');
    document.head.innerHTML = `
      <title>Gạch Phòng Khách Đẹp | DRAVIT</title>
      <link rel="canonical" href="http://localhost:3000/gach-phong-khach" />
      <meta name="robots" content="index, follow" />
      <script type="application/ld+json">{"@type":"CollectionPage","name":"Gạch Phòng Khách"}</script>
    `;
    document.body.innerHTML = buildCatalogDomFixture({ debounceMs: 0 });
    registerCatalogElements();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    window.sessionStorage.clear();
  });

  it('F-13 & F-8: keeps strictly 1 <form> across DOM when reparenting to Mobile Sheet and previews fragment=count before apply', async () => {
    const fetchUrls = [];
    globalThis.fetch = vi.fn().mockImplementation(async (url) => {
      const u = String(url);
      fetchUrls.push(u);
      if (u.includes('fragment=count')) {
        return {
          ok: true,
          status: 200,
          json: async () =>
            buildMockGridPayload({
              total: 7,
              items_html: '',
            }),
        };
      }
      return {
        ok: true,
        status: 200,
        json: async () => buildMockGridPayload({ total: 7, shown: 2 }),
      };
    });

    // Trước khi mở drawer: đúng 1 <form> nằm trong #drv-filter-sidebar
    expect(document.querySelectorAll('form#drv-catalog-filter-form').length).toBe(1);
    expect(document.querySelector('#drv-filter-sidebar drv-filter-form')).not.toBeNull();

    // Mở Mobile Filter Sheet -> di chuyển nguyên vẹn node <drv-filter-form> sang [data-filter-sheet-slot], vẫn đúng 1 <form>
    document.querySelector('[data-drv-open-filter-sheet]').click();
    expect(document.querySelectorAll('form#drv-catalog-filter-form').length).toBe(1);
    expect(document.querySelector('[data-filter-sheet-slot] drv-filter-form')).not.toBeNull();
    expect(document.querySelector('#drv-filter-sidebar drv-filter-form')).toBeNull();

    // Tick chọn trong Sheet -> chỉ gọi fragment=count, chưa đổi URL và chưa đổi #drv-product-grid
    const cb80 = document.querySelector('#flt-kich-thuoc-80x80');
    cb80.checked = true;
    cb80.dispatchEvent(new Event('change', { bubbles: true }));
    await flushPromises();

    expect(fetchUrls.length).toBe(1);
    expect(fetchUrls[0]).toContain('fragment=count');
    expect(document.querySelector('[data-sheet-preview-count]').textContent).toBe('7');
    expect(window.location.search).toBe('');

    // Bấm đóng (không bấm Áp dụng) -> hủy nháp, trả <drv-filter-form> về #drv-filter-sidebar
    document.querySelector('[data-filter-sheet-close]').click();
    await flushPromises();
    expect(document.querySelector('#drv-filter-sidebar drv-filter-form')).not.toBeNull();
    expect(document.querySelector('#flt-kich-thuoc-80x80').checked).toBe(false);

    // Mở lại, tick 80x80 và bấm "Xem 7 mẫu gạch phù hợp" -> gọi fragment=grid, cập nhật URL & trả form về sidebar
    document.querySelector('[data-drv-open-filter-sheet]').click();
    const cb80Again = document.querySelector('#flt-kich-thuoc-80x80');
    cb80Again.checked = true;
    cb80Again.dispatchEvent(new Event('change', { bubbles: true }));
    await flushPromises();

    document.querySelector('[data-filter-sheet-apply]').click();
    await flushPromises();

    expect(fetchUrls.some((u) => u.includes('fragment=grid'))).toBe(true);
    expect(window.location.search).toBe('?kich-thuoc=80x80');
    expect(document.querySelector('#drv-filter-sidebar drv-filter-form')).not.toBeNull();
    expect(document.querySelector('#drv-catalog-live-status').textContent).toBe('Đã tìm thấy 7 sản phẩm.');
  });

  it('F-1, F-2, F-5, F-12: aborts superseded in-flight fetches on rapid ticks, updates head/URL, and restores on popstate', async () => {
    const signals = [];
    const resolvers = [];

    globalThis.fetch = vi.fn().mockImplementation((url, opts = {}) => {
      signals.push({ url: String(url), signal: opts.signal });
      return new Promise((resolve, reject) => {
        opts.signal?.addEventListener('abort', () => {
          const abortErr = new DOMException('Aborted', 'AbortError');
          reject(abortErr);
        });
        resolvers.push({ url: String(url), resolve });
      });
    });

    // Tick nhanh 3 lần liên tiếp -> 2 request đầu phải bị abort ngay lập tức
    const cb60 = document.querySelector('#flt-kich-thuoc-60x60');
    const cb80 = document.querySelector('#flt-kich-thuoc-80x80');
    const cbMo = document.querySelector('#flt-be-mat-mo');

    cb60.checked = true;
    cb60.dispatchEvent(new Event('change', { bubbles: true }));

    cb80.checked = true;
    cb80.dispatchEvent(new Event('change', { bubbles: true }));

    cbMo.checked = true;
    cbMo.dispatchEvent(new Event('change', { bubbles: true }));

    expect(signals.length).toBe(3);
    expect(signals[0].signal.aborted).toBe(true);
    expect(signals[1].signal.aborted).toBe(true);
    expect(signals[2].signal.aborted).toBe(false);
    expect(document.querySelector('#drv-product-grid').getAttribute('aria-busy')).toBe('true');
    expect(document.querySelector('[data-drv-catalog-toolbar]').classList.contains('is-loading')).toBe(true);

    // Hoàn tất request thứ 3
    resolvers[2].resolve({
      ok: true,
      status: 200,
      json: async () =>
        buildMockGridPayload({
          total: 5,
          shown: 2,
          url: 'http://localhost:3000/gach-phong-khach?kich-thuoc=60x60,80x80&be-mat=mo',
          title: 'Gạch Phòng Khách 60x60, 80x80 | DRAVIT',
          robots: 'noindex, follow',
        }),
    });
    await flushPromises();

    expect(document.querySelector('#drv-product-grid').getAttribute('aria-busy')).toBe('false');
    expect(document.querySelector('[data-drv-catalog-toolbar]').classList.contains('is-loading')).toBe(false);

    expect(window.location.search).toBe('?kich-thuoc=60x60,80x80&be-mat=mo');
    expect(document.title).toBe('Gạch Phòng Khách 60x60, 80x80 | DRAVIT');
    expect(document.querySelector('meta[name="robots"]').getAttribute('content')).toBe('noindex, follow');
    expect(document.querySelector('#drv-catalog-live-status').textContent).toBe('Đã tìm thấy 5 sản phẩm.');

    // F-2: Bấm Back (popstate về URL sạch) -> tự gọi lại grid của URL trước đó mà không pushState chồng
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () =>
        buildMockGridPayload({
          total: 4,
          shown: 2,
          url: 'http://localhost:3000/gach-phong-khach',
          robots: 'index, follow',
          title: 'Gạch Phòng Khách Đẹp | DRAVIT',
          active_chips: [],
          chips_html: '<span>Đang xem toàn bộ</span>',
        }),
    });

    window.history.replaceState(null, '', 'http://localhost:3000/gach-phong-khach');
    window.dispatchEvent(new PopStateEvent('popstate'));
    await flushPromises();

    expect(document.querySelector('[data-catalog-total]').textContent).toBe('4');
    expect(document.querySelector('meta[name="robots"]').getAttribute('content')).toBe('index, follow');
  });

  it('F-6 & F-10: appends unique Load More cards until exhausted and swaps <main> + JSON-LD on static Silo click', async () => {
    globalThis.fetch = vi.fn().mockImplementation(async (url) => {
      const u = String(url);
      if (u.includes('fragment=items')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            total: 4,
            shown: 4,
            page: 2,
            per_page: 2,
            total_pages: 2,
            next_url: null,
            items_html: `
              <article class="drv-card" data-product-id="102"><a href="/san-pham/drv-8802m">DRV-8802M</a></article>
              <article class="drv-card" data-product-id="103"><a href="/san-pham/drv-8803m">DRV-8803M</a></article>
              <article class="drv-card" data-product-id="104"><a href="/san-pham/drv-8804m">DRV-8804M</a></article>
            `,
          }),
        };
      }

      // F-10: Trả về trang HTML tĩnh của Silo Gạch Nhà Tắm
      return {
        ok: true,
        status: 200,
        url: 'http://localhost:3000/gach-nha-tam',
        text: async () => `<!doctype html>
          <html lang="vi">
            <head>
              <title>Gạch Nhà Tắm Chống Trượt R10 | DRAVIT</title>
              <link rel="canonical" href="http://localhost:3000/gach-nha-tam" />
              <meta name="robots" content="index, follow" />
              <script type="application/ld+json">{"@type":"CollectionPage","name":"Gạch Nhà Tắm"}</script>
            </head>
            <body>
              <main id="main-content" data-drv-catalog-page data-base="product_cat:gach-nha-tam" data-base-url="http://localhost:3000/gach-nha-tam">
                <h1>Gạch Nhà Tắm</h1>
                <strong data-catalog-total>9</strong>
                <div id="drv-catalog-live-status" role="status" aria-live="polite"></div>
                <div id="drv-product-grid"><article class="drv-card" data-product-id="201"></article></div>
              </main>
              <dialog id="drv-filter-sheet"><div data-filter-sheet-slot></div></dialog>
            </body>
          </html>`,
      };
    });

    // F-6: Load more nối thêm 2 sản phẩm mới (bỏ qua ID 102 trùng) và tự ẩn nút Load More khi đã đủ 4/4
    document.querySelector('[data-drv-load-more]').click();
    await flushPromises();

    expect(document.querySelectorAll('#drv-product-grid .drv-card').length).toBe(4);
    expect(document.querySelector('[data-drv-load-more]')).toBeNull();

    // F-10: Bấm vào Silo Gạch Nhà Tắm -> thay <main>, cập nhật URL, title, canonical và JSON-LD
    document.querySelector('.drv-silo-tile').click();
    await flushPromises();

    expect(window.location.pathname).toBe('/gach-nha-tam');
    expect(document.title).toBe('Gạch Nhà Tắm Chống Trượt R10 | DRAVIT');
    expect(document.querySelector('main#main-content h1').textContent).toBe('Gạch Nhà Tắm');
    expect(document.querySelector('script[type="application/ld+json"]').textContent).toContain('Gạch Nhà Tắm');
  });
});

