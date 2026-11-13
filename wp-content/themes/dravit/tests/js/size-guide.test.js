import { beforeEach, describe, expect, it } from 'vitest';
import { computeGroutDiffText, registerHomeElements } from '../../assets/src/js/home.js';

function mountSizeGuideDom() {
  const config = {
    default_size: '80x80',
    baseline_size: '60x60',
    sizes: {
      '30x60': {
        label: '30×60 cm',
        w: 30,
        h: 60,
        rate: '5,56 viên/m²',
        box: '8 viên = 1,44 m²',
        grout: '1,5 - 2,0 mm',
        url: 'https://gachdravit.vn/gach-30x60',
      },
      '60x60': {
        label: '60×60 cm',
        w: 60,
        h: 60,
        rate: '2,78 viên/m²',
        box: '4 viên = 1,44 m²',
        grout: '1,5 - 2,0 mm',
        url: 'https://gachdravit.vn/gach-60x60',
      },
      '80x80': {
        label: '80×80 cm',
        w: 80,
        h: 80,
        rate: '1,56 viên/m²',
        box: '3 viên = 1,92 m²',
        grout: '1,5 - 2,0 mm',
        url: 'https://gachdravit.vn/gach-80x80',
      },
      '60x120': {
        label: '60×120 cm',
        w: 60,
        h: 120,
        rate: '1,39 viên/m²',
        box: '2 viên = 1,44 m²',
        grout: '1,5 - 2,0 mm',
        url: 'https://gachdravit.vn/gach-60x120',
      },
    },
  };

  document.body.innerHTML = `
    <drv-size-guide>
      <script type="application/json" data-sg-config>${JSON.stringify(config)}</script>
      <button type="button" data-sg-view="scale" class="is-active"></button>
      <button type="button" data-sg-view="floor"></button>
      <input type="checkbox" checked data-sg-ref>
      <div data-sg-ref-box></div>
      <div data-sg-scale-panel>
        <button type="button" data-sg-size="30x60"></button>
        <button type="button" data-sg-size="60x60"></button>
        <button type="button" data-sg-size="80x80" class="is-active"></button>
        <button type="button" data-sg-size="60x120"></button>
      </div>
      <div data-sg-floor-panel hidden>
        <span data-sg-floor-label></span>
        <span data-sg-grout-diff></span>
        <div data-sg-floor-grid></div>
      </div>
      <dd data-sg-out="label"></dd>
      <dd data-sg-out="rate"></dd>
      <dd data-sg-out="box"></dd>
      <dd data-sg-out="grout"></dd>
      <button type="button" data-sg-room="large" data-target-size="60x120" data-recommend="Ưu tiên 60×120 giảm mạch ron"></button>
      <button type="button" data-sg-zone-btn="all" class="is-active"></button>
      <button type="button" data-sg-zone-btn="wet"></button>
      <a href="https://gachdravit.vn/gach-80x80" data-sg-surface data-zones="dry"></a>
      <a href="https://gachdravit.vn/gach-nha-tam" data-sg-surface data-zones="damp,wet"></a>
      <p data-sg-bar-summary></p>
      <a href="https://gachdravit.vn/gach-80x80" data-sg-cat-link><span data-sg-cat-label></span></a>
    </drv-size-guide>
  `;

  return document.querySelector('drv-size-guide');
}

describe('SizeGuideElement (<drv-size-guide>)', () => {
  beforeEach(() => {
    registerHomeElements();
  });

  it('computes grout seam density difference relative to 60x60 accurately', () => {
    expect(computeGroutDiffText(80, 80, 60, 60)).toContain('Ít hơn ~25%');
    expect(computeGroutDiffText(60, 120, 60, 60)).toContain('Ít hơn ~25%');
    expect(computeGroutDiffText(60, 60, 60, 60)).toContain('Mật độ ron tiêu chuẩn');
    expect(computeGroutDiffText(30, 60, 60, 60)).toContain('Nhiều hơn ~50%');
  });

  it('updates specs, static category URL, and floor simulation when clicking size or room preset', () => {
    const el = mountSizeGuideDom();

    expect(el.querySelector('[data-sg-out="label"]').textContent).toBe('80×80 cm');
    expect(el.querySelector('[data-sg-cat-link]').getAttribute('href')).toBe('https://gachdravit.vn/gach-80x80');

    el.querySelector('[data-sg-size="30x60"]').click();
    expect(el.querySelector('[data-sg-out="rate"]').textContent).toBe('5,56 viên/m²');
    expect(el.querySelector('[data-sg-cat-link]').getAttribute('href')).toBe('https://gachdravit.vn/gach-30x60');
    expect(el.querySelector('[data-sg-grout-diff]').textContent).toContain('Nhiều hơn ~50%');

    el.querySelector('[data-sg-room="large"]').click();
    expect(el.querySelector('[data-sg-out="label"]').textContent).toBe('60×120 cm');
    expect(el.querySelector('[data-sg-bar-summary]').textContent).toContain('Ưu tiên 60×120 giảm mạch ron');
    expect(el.querySelector('[data-sg-cat-link]').getAttribute('href')).toBe('https://gachdravit.vn/gach-60x120');
  });

  it('switches between scale/floor views and filters R-rating surfaces by wet/dry zone', () => {
    const el = mountSizeGuideDom();

    el.querySelector('[data-sg-view="floor"]').click();
    expect(el.querySelector('[data-sg-scale-panel]').hidden).toBe(true);
    expect(el.querySelector('[data-sg-floor-panel]').hidden).toBe(false);
    expect(el.querySelector('[data-sg-ref-box]').hidden).toBe(true);

    el.querySelector('[data-sg-zone-btn="wet"]').click();
    const surfaces = el.querySelectorAll('[data-sg-surface]');
    expect(surfaces[0].hidden).toBe(true);
    expect(surfaces[1].hidden).toBe(false);
  });
});

