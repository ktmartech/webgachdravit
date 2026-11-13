import { describe, expect, it } from 'vitest';
import {
  initHeroAdvisor,
  parseHeroPrompt,
  registerHomeElements,
  resolveHeroDestination,
} from '../../assets/src/js/home.js';

describe('Hero Advisor NLP & AI/Static Fallback Routing', () => {
  it('extracts space, area m2, vein, R-rating, budget, and +5% rounded boxes from Vietnamese prompt', () => {
    const living = parseHeroPrompt('Phòng khách 35m² cần gạch vân đá sáng ít đường ron tầm 400k/m²');
    expect(living.spaceKey).toBe('living');
    expect(living.areaM2).toBe(35);
    expect(living.veinLabel).toBe('Vân đá Marble');
    expect(living.unitPrice).toBe(400000);
    // ceil((35 * 1.05) / 1.92) = ceil(19.140625) = 20 thùng -> 38.4 m2 * 400k = 15,360,000
    expect(living.estimatedBoxes).toBe(20);
    expect(living.estimatedCost).toBe(15360000);
    expect(living.collectionPath).toBe('/gach-phong-khach');

    const bath = parseHeroPrompt('Nhà tắm 6,5 m2 có người già cần chống trơn R11');
    expect(bath.spaceKey).toBe('bath');
    expect(bath.areaM2).toBe(6.5);
    expect(bath.rRating).toBe('R11');
    // ceil((6.5 * 1.05) / 1.44) = ceil(4.7395) = 5 thùng
    expect(bath.estimatedBoxes).toBe(5);
    expect(bath.collectionPath).toBe('/gach-nha-tam');
  });

  it('routes to /tro-ly-ai-tim-gach when aiEnabled is true and clean static collection URL when false', () => {
    const parsed = parseHeroPrompt('Phòng ngủ 20m2 lát gạch giả gỗ');

    const aiDest = resolveHeroDestination(parsed, {
      aiEnabled: true,
      aiUrl: 'https://gachdravit.vn/tro-ly-ai-tim-gach',
      catalogUrl: 'https://gachdravit.vn/gach',
    });
    expect(aiDest).toBe(
      `https://gachdravit.vn/tro-ly-ai-tim-gach?prompt=${encodeURIComponent('Phòng ngủ 20m2 lát gạch giả gỗ')}`,
    );

    const staticDest = resolveHeroDestination(parsed, {
      aiEnabled: false,
      aiUrl: 'https://gachdravit.vn/tro-ly-ai-tim-gach',
      catalogUrl: 'https://gachdravit.vn/gach',
    });
    expect(staticDest).toBe('https://gachdravit.vn/gach-phong-ngu');
    expect(staticDest.includes('?')).toBe(false);

    const fallbackCatalog = resolveHeroDestination(parseHeroPrompt('tư vấn chung'), {
      aiEnabled: false,
      aiUrl: 'https://gachdravit.vn/tro-ly-ai-tim-gach',
      catalogUrl: 'https://gachdravit.vn/gach',
    });
    expect(fallbackCatalog).toBe('https://gachdravit.vn/gach');
  });

  it('populates textarea, renders live NLP tags & box preview on chip click, and resets cleanly', () => {
    document.body.innerHTML = `
      <div data-drv-hero-advisor data-ai-enabled="1" data-ai-url="/tro-ly-ai-tim-gach" data-catalog-url="/gach">
        <form data-hero-advisor-form>
          <span data-hero-status><span data-hero-status-text>Sẵn sàng tư vấn</span></span>
          <textarea data-hero-prompt-input></textarea>
          <div data-hero-nlp-box hidden><div data-hero-nlp-tags></div></div>
          <button
            type="button"
            data-hero-chip
            data-prompt="Sân vườn 40m² chịu tải ô tô R11"
            data-fallback-url="/gach-san-vuon"
          >Sân vườn 40m²</button>
          <div data-hero-result-box hidden>
            <button type="button" data-hero-reset>Nhập lại</button>
            <p data-hero-result-summary></p>
            <strong data-hero-out-spec></strong>
            <strong data-hero-result-calc></strong>
            <strong data-hero-out-budget></strong>
            <a href="/tro-ly-ai-tim-gach" data-hero-open-ai></a>
            <a href="/gach" data-hero-open-cat></a>
          </div>
        </form>
      </div>
    `;

    const root = document.querySelector('[data-drv-hero-advisor]');
    initHeroAdvisor(root);

    root.querySelector('[data-hero-chip]').click();

    expect(root.querySelector('[data-hero-prompt-input]').value).toBe('Sân vườn 40m² chịu tải ô tô R11');
    expect(root.querySelector('[data-hero-nlp-box]').hidden).toBe(false);
    expect(root.querySelector('[data-hero-result-box]').hidden).toBe(false);
    // ceil((40 * 1.05) / 1.44) = ceil(29.166) = 30 thùng
    expect(root.querySelector('[data-hero-result-calc]').textContent).toContain('30 thùng');
    expect(root.querySelector('[data-hero-open-cat]').getAttribute('href')).toBe('/gach-san-vuon');

    root.querySelector('[data-hero-reset]').click();
    expect(root.querySelector('[data-hero-prompt-input]').value).toBe('');
    expect(root.querySelector('[data-hero-nlp-box]').hidden).toBe(true);
    expect(root.querySelector('[data-hero-result-box]').hidden).toBe(true);
  });
});

describe('ShowroomElement (<drv-showroom>)', () => {
  it('switches zones, swaps 3000K/6500K images, and updates keyboard comparison split', () => {
    registerHomeElements();
    document.body.innerHTML = `
      <drv-showroom>
        <button type="button" data-sr-zone="slabs" class="is-active" aria-pressed="true">Kệ tấm</button>
        <button type="button" data-sr-zone="bath" aria-pressed="false">Nhà tắm</button>
        <div data-sr-stage>
          <img data-sr-img-after src="/slab-1.jpg" alt="Slab 1">
          <div data-sr-before style="clip-path: inset(0 50% 0 0);">
            <img data-sr-img-before src="/slab-1.jpg" alt="">
          </div>
          <div data-sr-divider role="slider" tabindex="0" aria-valuenow="50" style="left: 50%;"></div>
          <p data-sr-caption>Caption 1</p>
        </div>
        <div data-sr-group="slabs">
          <button type="button" class="drv-sr__thumb is-active" data-sr-after="/slab-1.jpg" data-sr-before="/slab-1.jpg" data-sr-caption="Caption 1"></button>
        </div>
        <div data-sr-group="bath" hidden>
          <button type="button" class="drv-sr__thumb" data-sr-after="/bath-1.jpg" data-sr-before="/bath-1-before.jpg" data-sr-real-before="1" data-sr-caption="Cabin tắm mẫu"></button>
        </div>
      </drv-showroom>
    `;

    const sr = document.querySelector('drv-showroom');
    sr.querySelector('[data-sr-zone="bath"]').click();

    expect(sr.querySelector('[data-sr-group="slabs"]').hidden).toBe(true);
    expect(sr.querySelector('[data-sr-group="bath"]').hidden).toBe(false);
    expect(sr.querySelector('[data-sr-img-after]').getAttribute('src')).toBe('/bath-1.jpg');
    expect(sr.querySelector('[data-sr-img-before]').classList.contains('is-real-before')).toBe(true);
    expect(sr.querySelector('[data-sr-caption]').textContent).toBe('Cabin tắm mẫu');

    const divider = sr.querySelector('[data-sr-divider]');
    divider.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
    expect(divider.getAttribute('aria-valuenow')).toBe('55');
    expect(divider.style.left).toBe('55%');

    divider.dispatchEvent(new MouseEvent('dblclick', { bubbles: true }));
    expect(divider.getAttribute('aria-valuenow')).toBe('50');
    expect(divider.style.left).toBe('50%');
  });
});
