/**
 * Blog & Policy Interactive Module (`assets/src/js/blog.js`, ADR D-28, Gate 10: `core.js + blog.js <= 10KB gzip`).
 * - `<drv-toc>`: Scroll-spy H2/H3 + Mobile `<dialog>` auto-close khi chạm link mục lục.
 * - Reading Progress Bar: GPU `transform: scaleX(--drv-read-progress)` qua `requestAnimationFrame`.
 * - Prose Lightbox: uỷ quyền click `a[data-pswp-item]` nạp động `chunks/lightbox.js`.
 * - Carousel nút trước/sau cho `[drv_products layout="carousel"]`.
 */
import { closeDialog, copyText, registerAction } from './core.js';
import { showToast } from './utils/toast.js';
import { prefersReducedMotion, qs, qsa } from './utils/dom.js';

/**
 * Hàm thuần tính tiến độ đọc bài viết `0..1` theo vị trí khung bài `[data-drv-article]`.
 *
 * @param {number} articleTop    `getBoundingClientRect().top` của bài viết.
 * @param {number} articleHeight Chiều cao tổng thể bài viết (`px`).
 * @param {number} viewportH     Chiều cao khung nhìn (`window.innerHeight`).
 * @returns {number}
 */
export function computeReadingProgress(articleTop, articleHeight, viewportH) {
  const scrollable = articleHeight - viewportH * 0.65;
  if (scrollable <= 0) {
    return articleTop <= 0 ? 1 : 0;
  }
  const passed = -articleTop;
  return Math.min(1, Math.max(0, passed / scrollable));
}

/**
 * Hàm thuần chọn `id` heading đang active theo toạ độ `top` tương đối với ngưỡng offset header.
 *
 * @param {Array<{id: string, top: number}>} positions Danh sách heading theo thứ tự xuất hiện.
 * @param {number} threshold Khoảng đệm dưới sticky header (`px`).
 * @returns {string}
 */
export function resolveActiveHeadingId(positions, threshold = 148) {
  if (!Array.isArray(positions) || positions.length === 0) {
    return '';
  }

  let activeId = positions[0].id;
  for (const item of positions) {
    if (item.top <= threshold) {
      activeId = item.id;
    } else {
      break;
    }
  }
  return activeId;
}

class DravitTocElement extends HTMLElement {
  connectedCallback() {
    this.links = qsa('a[data-toc-link]', this);
    if (this.links.length === 0) {
      return;
    }

    this.targets = [];
    for (const link of this.links) {
      const id = link.dataset.tocLink || '';
      const heading = id ? document.getElementById(id) : null;
      if (id && heading) {
        this.targets.push({ id, heading, link });
      }
    }

    this.onClick = (event) => {
      const link = event.target instanceof Element ? event.target.closest('a[data-toc-link]') : null;
      if (!link) {
        return;
      }
      const id = link.dataset.tocLink || '';
      const targetEl = id ? document.getElementById(id) : null;
      if (!targetEl) {
        return;
      }

      event.preventDefault();
      const parentDialog = this.closest('dialog');
      if (parentDialog) {
        closeDialog(parentDialog);
      }

      this.setActive(id);
      targetEl.scrollIntoView({
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        block: 'start',
      });
      if (window.history?.replaceState) {
        window.history.replaceState(null, '', `#${id}`);
      }
    };

    this.addEventListener('click', this.onClick);
    this.syncFromViewport();
  }

  disconnectedCallback() {
    if (this.onClick) {
      this.removeEventListener('click', this.onClick);
    }
  }

  syncFromViewport() {
    if (!this.targets || this.targets.length === 0) {
      return;
    }
    const positions = this.targets.map(({ id, heading }) => ({
      id,
      top: heading.getBoundingClientRect().top,
    }));
    this.setActive(resolveActiveHeadingId(positions, 148));
  }

  setActive(activeId) {
    if (!activeId || activeId === this.currentActiveId) {
      return;
    }
    this.currentActiveId = activeId;
    for (const link of this.links) {
      const matched = link.dataset.tocLink === activeId;
      link.classList.toggle('is-active', matched);
      if (matched) {
        link.setAttribute('aria-current', 'location');
      } else {
        link.removeAttribute('aria-current');
      }
    }
  }
}

export function registerTocElement() {
  if (typeof customElements !== 'undefined' && !customElements.get('drv-toc')) {
    customElements.define('drv-toc', DravitTocElement);
  }
}

function initScrollTelemetry() {
  const progressEl = qs('[data-drv-read-progress]');
  const articleEl = qs('[data-drv-article]');
  const getTocs = () => qsa('drv-toc');

  if (!progressEl && !articleEl && getTocs().length === 0) {
    return;
  }

  let rafPending = false;
  const update = () => {
    rafPending = false;
    if (articleEl) {
      const rect = articleEl.getBoundingClientRect();
      const ratio = computeReadingProgress(rect.top, rect.height, window.innerHeight || 800);
      const pct = `${Math.round(ratio * 100)}%`;
      if (progressEl) {
        progressEl.style.setProperty('--drv-read-progress', ratio.toFixed(4));
      }
      for (const el of qsa('[data-reading-progress], [data-reading-progress-mob]')) {
        el.textContent = pct;
      }
      for (const bar of qsa('[data-reading-progress-bar]')) {
        bar.style.width = pct;
      }
    }
    for (const toc of getTocs()) {
      if (typeof toc.syncFromViewport === 'function') {
        toc.syncFromViewport();
      }
    }
  };

  const requestUpdate = () => {
    if (!rafPending) {
      rafPending = true;
      window.requestAnimationFrame(update);
    }
  };

  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate, { passive: true });
  requestUpdate();
}

function initProseLightbox() {
  document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-drv-prose] a[data-pswp-item]') : null;
    if (!(trigger instanceof HTMLElement)) {
      return;
    }
    event.preventDefault();
    import('./chunks/lightbox.js').then((mod) => mod.openLightboxFromTrigger(trigger));
  });
}

function initEmbedCarousels() {
  for (const wrap of qsa('[data-drv-carousel-wrap]')) {
    const track = qs('[data-drv-carousel]', wrap);
    const prevBtn = qs('[data-carousel-prev]', wrap);
    const nextBtn = qs('[data-carousel-next]', wrap);
    if (!track || (!prevBtn && !nextBtn)) {
      continue;
    }

    const syncButtons = () => {
      const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 2);
      if (prevBtn) {
        prevBtn.disabled = track.scrollLeft <= 2;
      }
      if (nextBtn) {
        nextBtn.disabled = track.scrollLeft >= maxScroll;
      }
    };

    track.addEventListener('scroll', syncButtons, { passive: true });
    window.addEventListener('resize', syncButtons, { passive: true });
    syncButtons();
  }
}


/**
 * Menu chia sẻ bài viết: Facebook (link sharer), Zalo (sao chép link + mở Zalo Web, không nạp SDK ngoài),
 * Sao chép (action `copy-text` của core).
 */
function initShareMenu() {
  const closeAll = (except = null) => {
    for (const wrap of qsa('[data-drv-share]')) {
      if (wrap === except) {
        continue;
      }
      const menu = qs('.drv-share__menu', wrap);
      const btn = qs('[data-drv-action="share-toggle"]', wrap);
      if (menu && !menu.hidden) {
        menu.hidden = true;
        btn?.setAttribute('aria-expanded', 'false');
      }
    }
  };

  registerAction('share-toggle', (trigger, event) => {
    event?.preventDefault();
    const wrap = trigger.closest('[data-drv-share]');
    const menu = wrap ? qs('.drv-share__menu', wrap) : null;
    if (!wrap || !menu) {
      return;
    }
    const willOpen = menu.hidden;
    closeAll(wrap);
    menu.hidden = !willOpen;
    trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen) {
      qs('.drv-share__item', menu)?.focus();
    }
  });

  registerAction('share-close', () => closeAll());

  registerAction('share-zalo', async (trigger, event) => {
    event?.preventDefault();
    const url = trigger.dataset.shareUrl || window.location.href;
    const ok = await copyText(url);
    showToast(
      ok ? 'Đã sao chép liên kết, dán vào Zalo để gửi' : 'Hãy sao chép liên kết rồi dán vào Zalo',
      ok ? 'success' : 'default',
      3200,
    );
    window.open('https://chat.zalo.me/', '_blank', 'noopener,noreferrer');
    closeAll();
  });

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-drv-share]')) {
      closeAll();
    } else if (event.target.closest('[data-drv-action="copy-text"]')) {
      window.setTimeout(() => closeAll(), 250);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }
    const open = qs('[data-drv-share] .drv-share__menu:not([hidden])');
    if (open) {
      const wrap = open.closest('[data-drv-share]');
      closeAll();
      qs('[data-drv-action="share-toggle"]', wrap)?.focus();
    }
  });
}

export function initBlogPage() {
  registerTocElement();
  initScrollTelemetry();
  initProseLightbox();
  initEmbedCarousels();
  initShareMenu();
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBlogPage, { once: true });
  } else {
    initBlogPage();
  }
}
