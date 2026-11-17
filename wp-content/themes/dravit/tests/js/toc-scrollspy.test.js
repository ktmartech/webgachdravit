import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  computeReadingProgress,
  registerTocElement,
  resolveActiveHeadingId,
} from '../../assets/src/js/blog.js';

describe('Blog TOC Scroll-Spy & Reading Progress (<drv-toc>)', () => {
  beforeEach(() => {
    registerTocElement();
    document.body.innerHTML = '';
  });

  it('clamps reading progress strictly between 0 and 1', () => {
    expect(computeReadingProgress(300, 2400, 800)).toBe(0);
    expect(computeReadingProgress(-940, 2400, 800)).toBeCloseTo(0.5, 2);
    expect(computeReadingProgress(-2200, 2400, 800)).toBe(1);
  });

  it('resolves active heading id based on sticky header threshold', () => {
    const positions = [
      { id: 'muc-1', top: -240 },
      { id: 'muc-2', top: 110 },
      { id: 'muc-3', top: 420 },
    ];
    expect(resolveActiveHeadingId(positions, 148)).toBe('muc-2');
    expect(resolveActiveHeadingId([{ id: 'muc-1', top: 320 }], 148)).toBe('muc-1');
  });

  it('highlights active TOC link on sync and closes mobile dialog when clicked', () => {
    document.body.innerHTML = `
      <article data-drv-article>
        <h2 id="chon-r10">Chọn R10</h2>
        <h2 id="quy-doi-thung">Quy đổi thùng</h2>
      </article>
      <dialog id="drv-mobile-toc" open>
        <drv-toc data-toc-mobile="1">
          <a href="#chon-r10" class="drv-toc__link" data-toc-link="chon-r10">Chọn R10</a>
          <a href="#quy-doi-thung" class="drv-toc__link" data-toc-link="quy-doi-thung">Quy đổi thùng</a>
        </drv-toc>
      </dialog>
    `;

    const h1 = document.getElementById('chon-r10');
    const h2 = document.getElementById('quy-doi-thung');
    h1.scrollIntoView = vi.fn();
    h2.scrollIntoView = vi.fn();
    vi.spyOn(h1, 'getBoundingClientRect').mockReturnValue({ top: -80 });
    vi.spyOn(h2, 'getBoundingClientRect').mockReturnValue({ top: 360 });

    const toc = document.querySelector('drv-toc');
    toc.syncFromViewport();

    const link1 = toc.querySelector('[data-toc-link="chon-r10"]');
    const link2 = toc.querySelector('[data-toc-link="quy-doi-thung"]');
    expect(link1.classList.contains('is-active')).toBe(true);
    expect(link1.getAttribute('aria-current')).toBe('location');
    expect(link2.classList.contains('is-active')).toBe(false);

    vi.useFakeTimers();
    try {
      link2.click();
      expect(h2.scrollIntoView).toHaveBeenCalledTimes(1);
      expect(link2.classList.contains('is-active')).toBe(true);
      vi.advanceTimersByTime(200);
      expect(document.getElementById('drv-mobile-toc').hasAttribute('open')).toBe(false);
    } finally {
      vi.useRealTimers();
    }
  });
});
