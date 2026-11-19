import { openDialog, registerAction } from './core.js';
import { registerCalcElements } from './chunks/calc-elements.js';
import { initShareMenu } from './chunks/share-menu.js';
import { prefersReducedMotion, qs, qsa } from './utils/dom.js';
import { readJsonStorage, writeJsonStorage } from './utils/storage.js';
import { showToast } from './utils/toast.js';

const VIEWED_STORAGE_KEY = 'drv_viewed';
const MAX_STORED_VIEWED = 20;
const MAX_VIEWED_ITEMS = 12;

let pdpAbort = null;

function getProductsEndpoint() {
  return window.DRV_CONFIG?.productsEndpoint || '/wp-json/dravit/v1/products';
}

export function recordViewedProduct(productId) {
  const pid = Number.parseInt(String(productId || '0'), 10) || 0;
  if (pid <= 0) {
    return [];
  }
  const existing = readJsonStorage(VIEWED_STORAGE_KEY, []);
  const clean = Array.isArray(existing)
    ? existing.map((v) => Number.parseInt(String(v), 10) || 0).filter((v) => v > 0 && v !== pid)
    : [];

  const next = [pid, ...clean].slice(0, MAX_STORED_VIEWED);
  writeJsonStorage(VIEWED_STORAGE_KEY, next);
  return next;
}

async function hydrateRecentlyViewedSection() {
  const section = qs('[data-drv-viewed-section]');
  const grid = qs('[data-drv-viewed-grid]', section || document);
  if (!section || !grid) {
    return;
  }

  const currentPid = Number.parseInt(section.getAttribute('data-current-product-id') || '0', 10) || 0;
  const allIds = recordViewedProduct(currentPid);
  const otherIds = allIds.filter((id) => id > 0 && id !== currentPid).slice(0, MAX_VIEWED_ITEMS);

  if (otherIds.length === 0) {
    section.hidden = true;
    return;
  }

  try {
    const endpoint = getProductsEndpoint();
    const sep = endpoint.includes('?') ? '&' : '?';
    const res = await fetch(`${endpoint}${sep}include=${otherIds.join(',')}&cards_html=1`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
      return;
    }

    const data = await res.json();
    if (typeof data?.cards_html === 'string' && data.cards_html.trim() !== '') {
      grid.innerHTML = data.cards_html;
      section.hidden = false;
      updateSliderButtons(section);

      if (!prefersReducedMotion()) {
        import('./chunks/motion.js')
          .then((m) => m.staggerReveal(grid, ':scope > .drv-card', { stagger: 45, duration: 340, distance: 12 }))
          .catch(() => {});
      }
    }
  } catch {
    // Silent fallback if offline
  }
}

function updateSliderButtons(slider) {
  const track = qs('[data-drv-slider-track]', slider);
  const prevBtn = qs('[data-drv-slider-prev]', slider);
  const nextBtn = qs('[data-drv-slider-next]', slider);
  if (!track) {
    return;
  }

  const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
  if (prevBtn instanceof HTMLButtonElement) {
    prevBtn.disabled = track.scrollLeft <= 2;
  }
  if (nextBtn instanceof HTMLButtonElement) {
    nextBtn.disabled = track.scrollLeft >= maxScroll - 2;
  }
}

function setupProductSliders(signal) {
  const sliders = qsa('[data-drv-slider]');
  for (const slider of sliders) {
    const track = qs('[data-drv-slider-track]', slider);
    if (!track) {
      continue;
    }
    updateSliderButtons(slider);
    track.addEventListener('scroll', () => updateSliderButtons(slider), { passive: true, signal });
  }

  window.addEventListener(
    'resize',
    () => {
      for (const slider of sliders) {
        updateSliderButtons(slider);
      }
    },
    { passive: true, signal },
  );
}

function setupStickyPurchaseObserver(signal) {
  const anchor = qs('[data-pdp-cta-anchor]');
  const stickyBar = qs('[data-drv-sticky-purchase]');
  if (!anchor || !stickyBar || typeof IntersectionObserver === 'undefined') {
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        const passedBelow = !entry.isIntersecting && entry.boundingClientRect.top < 0;
        stickyBar.classList.toggle('is-visible', passedBelow);
      }
    },
    { threshold: 0 },
  );

  observer.observe(anchor);
  signal.addEventListener('abort', () => observer.disconnect(), { once: true });
}

function registerPdpActions() {
  registerAction('pdp-quote-modal', (trigger, event) => {
    event?.preventDefault();
    // Ưu tiên dialog Tư vấn riêng của PDP (slot `pdp_consult`), dự phòng modal báo giá toàn site.
    const modal = qs('#drv-pdp-consult-modal') || qs('#drv-quote-modal');
    if (!modal) {
      return;
    }

    const sku = String(trigger.dataset.drvSku || '').trim();
    const title = String(trigger.dataset.drvTitle || '').trim();
    const boxesText = String(trigger.dataset.drvBoxesText || '').trim();
    const rawM2 = Number.parseFloat(trigger.dataset.drvM2 || '0') || 0;

    // Bơm ngữ cảnh SP + m² vừa tính vào form native lẫn Hidden field Fluent Forms (D-31).
    modal.dispatchEvent(
      new CustomEvent('drv:lead-context', {
        bubbles: true,
        detail: {
          product_id: Number.parseInt(trigger.dataset.productId || trigger.dataset.drvProductId || '0', 10) || 0,
          sku,
          title,
          area_m2: rawM2,
          boxes_text: boxesText,
        },
      }),
    );

    openDialog(modal, trigger);
  });
}

export function initProductPage() {
  registerCalcElements();
  registerPdpActions();
  initShareMenu();

  pdpAbort?.abort();
  pdpAbort = new AbortController();
  const { signal } = pdpAbort;

  document.addEventListener(
    'click',
    (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      const pswpItem = target.closest('[data-pswp-item]');
      if (
        pswpItem instanceof HTMLElement &&
        !(event instanceof MouseEvent && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey))
      ) {
        event.preventDefault();
        import('./chunks/lightbox.js')
          .then((m) => m.openLightboxFromTrigger(pswpItem))
          .catch(() => {
            const fallbackHref = pswpItem.getAttribute('href');
            if (fallbackHref) {
              window.open(fallbackHref, '_blank', 'noopener');
            }
          });
        return;
      }

      const sliderNavBtn = target.closest('[data-drv-slider-prev], [data-drv-slider-next]');
      if (sliderNavBtn instanceof HTMLElement) {
        event.preventDefault();
        const slider = sliderNavBtn.closest('[data-drv-slider]');
        const track = slider ? qs('[data-drv-slider-track]', slider) : null;
        if (track) {
          const isNext = sliderNavBtn.hasAttribute('data-drv-slider-next');
          const firstCard = qs('.drv-card', track);
          const cardW = firstCard ? firstCard.getBoundingClientRect().width + 16 : track.clientWidth * 0.5;
          const step = window.innerWidth >= 1024 ? cardW * 2 : cardW;
          track.scrollBy({ left: isNext ? step : -step, behavior: 'smooth' });
        }
        return;
      }

      const clearViewedBtn = target.closest('[data-drv-clear-viewed]');
      if (clearViewedBtn) {
        event.preventDefault();
        writeJsonStorage(VIEWED_STORAGE_KEY, []);
        const viewedSec = qs('[data-drv-viewed-section]');
        if (viewedSec) {
          viewedSec.hidden = true;
        }
        showToast('Đã xóa lịch sử sản phẩm vừa xem', 'info', 2200);
      }
    },
    { signal },
  );

  const specMore = qs('details.drv-pdp-spec-more');
  if (specMore) {
    specMore.addEventListener(
      'toggle',
      () => {
        const lbl = qs('[data-spec-more-label]', specMore);
        if (lbl) {
          lbl.textContent = specMore.open ? 'Thu gọn' : 'Xem tất cả';
        }
      },
      { signal },
    );
  }

  setupProductSliders(signal);
  setupStickyPurchaseObserver(signal);
  void hydrateRecentlyViewedSection();
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductPage, { once: true });
  } else {
    initProductPage();
  }
}
