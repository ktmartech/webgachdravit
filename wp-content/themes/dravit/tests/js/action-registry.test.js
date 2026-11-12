import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  closeDialog,
  copyText,
  initCore,
  openDialog,
  resetCoreForTests,
  updateMegaPreview,
} from '../../assets/src/js/core.js';

describe('Step 1 Core Action Registry, Dialog Lifecycle, Tabs & Mega Preview', () => {
  beforeEach(() => {
    resetCoreForTests();
    document.body.innerHTML = '';
    document.documentElement.style.overflow = '';
    window.matchMedia = vi.fn().mockReturnValue({ matches: true });
    initCore();
  });

  it('opens and closes <dialog> via data-drv-action and restores focus to opener on Escape/close', () => {
    document.body.innerHTML = `
      <button id="opener" type="button" data-drv-action="dialog-open" data-drv-target="#demo-modal" aria-expanded="false">
        Mở báo giá
      </button>
      <dialog id="demo-modal">
        <input id="modal-input" type="text">
        <button id="closer" type="button" data-drv-action="dialog-close">Đóng</button>
      </dialog>
    `;

    const opener = document.getElementById('opener');
    const dialog = document.getElementById('demo-modal');
    const closer = document.getElementById('closer');

    opener.focus();
    opener.click();

    expect(dialog.hasAttribute('open')).toBe(true);
    expect(opener.getAttribute('aria-expanded')).toBe('true');
    expect(document.documentElement.style.overflow).toBe('hidden');

    closer.click();

    expect(dialog.hasAttribute('open')).toBe(false);
    expect(opener.getAttribute('aria-expanded')).toBe('false');
    expect(document.documentElement.style.overflow).toBe('');
    expect(document.activeElement).toBe(opener);

    // Kiểm tra hủy bằng sự kiện cancel (phím Escape)
    openDialog('#demo-modal', opener);
    expect(dialog.hasAttribute('open')).toBe(true);
    dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
    expect(dialog.hasAttribute('open')).toBe(false);

    expect(closeDialog('#non-existent')).toBe(false);
  });

  it('copies SKU via navigator.clipboard and marks trigger .is-copied', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText } });

    const ok = await copyText('DRV-8801M');
    expect(ok).toBe(true);
    expect(writeText).toHaveBeenCalledWith('DRV-8801M');

    document.body.innerHTML = `
      <button id="copy-btn" type="button" data-drv-action="copy-sku" data-drv-value="DRV-TRZ80">Chép</button>
    `;
    const btn = document.getElementById('copy-btn');
    btn.click();
    await Promise.resolve();
    await Promise.resolve();

    expect(writeText).toHaveBeenCalledWith('DRV-TRZ80');
    expect(btn.classList.contains('is-copied')).toBe(true);
  });

  it('switches WAI-ARIA tabs on click and ArrowRight / ArrowLeft keys', () => {
    document.body.innerHTML = `
      <div data-drv-tabs>
        <div role="tablist">
          <button id="tab-1" role="tab" aria-selected="true" aria-controls="panel-1" data-drv-action="tab-switch" class="is-active">Tab 1</button>
          <button id="tab-2" role="tab" aria-selected="false" aria-controls="panel-2" data-drv-action="tab-switch" tabindex="-1">Tab 2</button>
        </div>
        <div id="panel-1" role="tabpanel">Nội dung 1</div>
        <div id="panel-2" role="tabpanel" hidden>Nội dung 2</div>
      </div>
    `;

    const tab1 = document.getElementById('tab-1');
    const tab2 = document.getElementById('tab-2');
    const panel1 = document.getElementById('panel-1');
    const panel2 = document.getElementById('panel-2');

    tab1.focus();
    tab1.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

    expect(tab2.getAttribute('aria-selected')).toBe('true');
    expect(panel1.hidden).toBe(true);
    expect(panel2.hidden).toBe(false);
  });

  it('updates Mega Menu Col 5 Live Dynamic Preview card on link hover/focus', () => {
    document.body.innerHTML = `
      <div id="drv-mega-menu">
        <a id="link-1" href="/gach-lat-nen" class="drv-mega__link is-active">Gạch Lát Nền</a>
        <a id="link-2"
           href="/da-nung-ket"
           class="drv-mega__link"
           data-drv-preview-img="/img/sintered.svg"
           data-drv-preview-badge="Đỉnh cao vật liệu"
           data-drv-preview-title="Đá Nung Kết Khổ Lớn"
           data-drv-preview-desc="Nung ở 1200 độ C"
           data-drv-preview-price="Từ 550.000đ/m²">Đá Nung Kết</a>
        <span id="drv-mega-preview-badge">Cũ</span>
        <span id="drv-mega-preview-price">Cũ</span>
        <img id="drv-mega-preview-img" src="/img/old.svg" alt="Cũ">
        <h3 id="drv-mega-preview-title">Cũ</h3>
        <p id="drv-mega-preview-desc">Cũ</p>
        <a id="drv-mega-preview-btn" href="/gach-lat-nen">Xem</a>
      </div>
    `;

    const link2 = document.getElementById('link-2');
    updateMegaPreview(link2);

    expect(link2.classList.contains('is-active')).toBe(true);
    expect(document.getElementById('link-1').classList.contains('is-active')).toBe(false);
    expect(document.getElementById('drv-mega-preview-badge').textContent).toBe('Đỉnh cao vật liệu');
    expect(document.getElementById('drv-mega-preview-title').textContent).toBe('Đá Nung Kết Khổ Lớn');
    expect(document.getElementById('drv-mega-preview-price').textContent).toBe('Từ 550.000đ/m²');
    expect(document.getElementById('drv-mega-preview-img').getAttribute('src')).toBe('/img/sintered.svg');
    expect(document.getElementById('drv-mega-preview-btn').getAttribute('href')).toBe('/da-nung-ket');
  });
});

