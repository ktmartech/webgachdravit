import PhotoSwipe from 'photoswipe';
import '../../css/chunks/lightbox.css';
import { prefersReducedMotion, qsa } from '../utils/dom.js';

/**
 * Lightbox PDP/Blog — PhotoSwipe 5 (ADR D-28) khoác giao diện modal của mockup `03-product-single.html`
 * (`#drv-lightbox-dialog`): khung trắng 920px, header (tiêu đề + Tải Map 3D/CAD + đóng), stage nền ngà,
 * nút tròn trước/sau, pill đếm "3 / 6", chú thích 2 dòng và dải 6 thumbnail.
 * Giữ nguyên năng lực PhotoSwipe: zoom soi vân, pinch/vuốt mobile, phím ← → Esc, trả focus.
 * Chunk tải động hoàn toàn (0 byte khi chưa click ảnh — Test P-6).
 */

const MOBILE_BP = 640;
const PANEL_MAX_W = 920;
const THUMB_COLS = 6;

let activeLightbox = null;

/**
 * Hàm thuần: tính khung modal + vùng stage theo viewport (dùng chung cho CSS vars và `paddingFn`).
 *
 * @param {number} vw
 * @param {number} vh
 * @param {number} count
 */
export function computeLightboxLayout(vw, vh, count) {
  const mobile = vw < MOBILE_BP;
  const pad = mobile ? 12 : 24;
  const headH = mobile ? 56 : 62;
  const edge = mobile ? 0 : 16;
  const panelW = mobile ? vw : Math.min(PANEL_MAX_W, Math.round(vw * 0.95));
  const innerW = panelW - pad * 2;
  const gap = mobile ? 6 : 8;
  const hasThumbs = count > 1;
  const thumbW = (innerW - gap * (THUMB_COLS - 1)) / THUMB_COLS;
  const thumbH = hasThumbs ? Math.round(thumbW * (mobile ? 1 : 0.75)) : 0;
  const thumbsBlock = hasThumbs ? thumbH + (mobile ? 8 : 12) : 0;
  const captionH = mobile ? 42 : 46;

  const chrome = headH + pad + captionH + thumbsBlock + pad;
  const maxPanelH = Math.max(320, vh - edge * 2);
  let stageH = Math.round(innerW * (mobile ? 1 : 10 / 16));
  if (stageH + chrome > maxPanelH) {
    stageH = Math.max(160, maxPanelH - chrome);
  }

  const panelH = stageH + chrome;
  const panelLeft = Math.round((vw - panelW) / 2);
  const panelTop = Math.max(0, Math.round((vh - panelH) / 2));

  return {
    mobile,
    gap,
    headH,
    captionH,
    thumbH,
    panel: { left: panelLeft, top: panelTop, width: panelW, height: panelH },
    stage: { left: panelLeft + pad, top: panelTop + headH + pad, width: innerW, height: stageH },
  };
}

function applyLayoutVars(el, layout) {
  const set = (name, px) => el.style.setProperty(name, `${Math.round(px)}px`);
  set('--lb-panel-l', layout.panel.left);
  set('--lb-panel-t', layout.panel.top);
  set('--lb-panel-w', layout.panel.width);
  set('--lb-panel-h', layout.panel.height);
  set('--lb-stage-l', layout.stage.left);
  set('--lb-stage-t', layout.stage.top);
  set('--lb-stage-w', layout.stage.width);
  set('--lb-stage-h', layout.stage.height);
  set('--lb-head-h', layout.headH);
  set('--lb-caption-h', layout.captionH);
  set('--lb-thumb-h', layout.thumbH);
  set('--lb-gap', layout.gap);
}

function isInsidePanel(point, layout) {
  const { left, top, width, height } = layout.panel;
  return point.x >= left && point.x <= left + width && point.y >= top && point.y <= top + height;
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (ch) => `&#${ch.charCodeAt(0)};`);
}

/**
 * Mở PhotoSwipe 5 từ phần tử `<a data-pswp-item>` được click (ADR D-28, Test P-6).
 *
 * @param {HTMLElement} triggerLink
 */
export function openLightboxFromTrigger(triggerLink) {
  if (!(triggerLink instanceof HTMLElement)) {
    return;
  }

  const galleryScope = triggerLink.closest('[data-pswp-gallery]') || triggerLink.parentElement || document;
  const itemNodes = qsa('[data-pswp-item]', galleryScope);
  const nodes = itemNodes.length > 0 ? itemNodes : [triggerLink];
  const items = nodes.map((node) => {
    const img = node.querySelector('img');
    const src = node.getAttribute('href') || node.dataset.pswpSrc || img?.currentSrc || img?.src || '';
    const width = Math.max(400, Number.parseInt(node.dataset.pswpWidth || '1200', 10) || 1200);
    const height = Math.max(400, Number.parseInt(node.dataset.pswpHeight || '1200', 10) || 1200);
    const alt = img?.getAttribute('alt') || '';
    return {
      src,
      width,
      height,
      alt,
      caption: node.dataset.pswpCaption || alt,
      thumb: img?.currentSrc || img?.getAttribute('src') || src,
      element: node,
    };
  });

  if (items.length === 0 || !items[0].src) {
    return;
  }

  const startIndex = Math.max(
    0,
    nodes.indexOf(triggerLink) >= 0
      ? nodes.indexOf(triggerLink)
      : Number.parseInt(triggerLink.dataset.pswpIndex || '0', 10) || 0,
  );

  const pageTitle = document.querySelector('h1')?.textContent?.trim() || document.title;
  const dialogTitle = galleryScope instanceof HTMLElement && galleryScope.dataset.pswpTitle
    ? galleryScope.dataset.pswpTitle
    : `Chi tiết thực tế ${pageTitle}`;
  const mapUrl = galleryScope instanceof HTMLElement ? galleryScope.dataset.pswpMapUrl || '' : '';

  let layout = computeLightboxLayout(window.innerWidth, window.innerHeight, items.length);

  activeLightbox?.close();

  const outsideClose = function onOutsideTap(point) {
    if (!isInsidePanel(point, layout)) {
      this.close();
    }
  };

  const pswp = new PhotoSwipe({
    dataSource: items,
    index: startIndex,
    mainClass: 'drv-pswp',
    bgOpacity: 1,
    showHideAnimationType: prefersReducedMotion() ? 'none' : 'fade',
    wheelToZoom: true,
    returnFocus: true,
    trapFocus: true,
    // UI mặc định được thay bằng phần tử tùy biến bên dưới
    arrowPrev: false,
    arrowNext: false,
    close: false,
    zoom: false,
    counter: false,
    imageClickAction: 'zoom',
    tapAction: outsideClose,
    bgClickAction: outsideClose,
    doubleTapAction: 'zoom',
    paddingFn: (viewportSize) => {
      layout = computeLightboxLayout(viewportSize.x, viewportSize.y, items.length);
      if (pswp.element) {
        applyLayoutVars(pswp.element, layout);
      }
      return {
        top: layout.stage.top,
        left: layout.stage.left,
        right: viewportSize.x - layout.stage.left - layout.stage.width,
        bottom: viewportSize.y - layout.stage.top - layout.stage.height,
      };
    },
  });

  pswp.on('uiRegister', () => {
    applyLayoutVars(pswp.element, layout);
    pswp.element.setAttribute('aria-labelledby', 'drv-pswp-title');

    // Khung trắng + nền stage: phải nằm DƯỚI ảnh → dời ra trước scroll-wrap sau khi PhotoSwipe append xong
    pswp.ui.registerElement({
      name: 'drv-panel',
      order: 1,
      appendTo: 'root',
      onInit: (el) => {
        el.className = 'drv-pswp__panel';
        el.innerHTML = '<span class="drv-pswp__stage-bg"></span>';
        pswp.on('afterInit', () => {
          pswp.element?.insertBefore(el, pswp.scrollWrap);
        });
      },
    });

    pswp.ui.registerElement({
      name: 'drv-head',
      order: 2,
      appendTo: 'root',
      onInit: (el) => {
        el.className = 'drv-pswp__head drv-dialog__head';
        el.innerHTML = `
          <h2 id="drv-pswp-title" class="drv-dialog__title">
            <i class="ph ph-images drv-icon" aria-hidden="true"></i>
            <span>${escapeHtml(dialogTitle)}</span>
          </h2>
          <div class="drv-dialog__actions">
            ${mapUrl
              ? `<a class="drv-btn drv-btn--outline drv-btn--sm" href="${escapeHtml(mapUrl)}" download>
                  <i class="ph-bold ph-download-simple drv-icon--xs" aria-hidden="true"></i>
                  <span>Tải Map 3D/CAD</span>
                </a>`
              : ''}
            <button type="button" class="drv-btn drv-btn--ghost drv-btn--icon" data-drv-pswp-close aria-label="Đóng khung phóng to ảnh (Esc)">
              <i class="ph-bold ph-x drv-icon" aria-hidden="true"></i>
            </button>
          </div>`;
        el.querySelector('[data-drv-pswp-close]')?.addEventListener('click', () => pswp.close());
      },
    });

    const multi = items.length > 1;

    pswp.ui.registerElement({
      name: 'drv-prev',
      order: 3,
      appendTo: 'root',
      isButton: true,
      title: 'Xem ảnh trước',
      html: '<i class="ph-bold ph-caret-left" aria-hidden="true"></i>',
      onInit: (el) => {
        el.classList.add('drv-pswp__nav', 'drv-pswp__nav--prev');
        el.hidden = !multi;
      },
      onClick: 'prev',
    });

    pswp.ui.registerElement({
      name: 'drv-next',
      order: 4,
      appendTo: 'root',
      isButton: true,
      title: 'Xem ảnh tiếp theo',
      html: '<i class="ph-bold ph-caret-right" aria-hidden="true"></i>',
      onInit: (el) => {
        el.classList.add('drv-pswp__nav', 'drv-pswp__nav--next');
        el.hidden = !multi;
      },
      onClick: 'next',
    });

    pswp.ui.registerElement({
      name: 'drv-counter',
      order: 5,
      appendTo: 'root',
      onInit: (el) => {
        el.className = 'drv-pswp__counter u-tabular';
        el.setAttribute('aria-hidden', 'true');
        el.hidden = !multi;
        pswp.on('change', () => {
          el.textContent = `${pswp.currIndex + 1} / ${pswp.getNumItems()}`;
        });
      },
    });

    pswp.ui.registerElement({
      name: 'drv-caption',
      order: 6,
      appendTo: 'root',
      onInit: (el) => {
        el.className = 'drv-pswp__caption';
        el.setAttribute('aria-live', 'polite');
        pswp.on('change', () => {
          el.textContent = pswp.currSlide?.data?.caption || '';
        });
      },
    });

    if (multi) {
      pswp.ui.registerElement({
        name: 'drv-thumbs',
        order: 7,
        appendTo: 'root',
        onInit: (el) => {
          el.className = 'drv-pswp__thumbs';
          el.setAttribute('role', 'group');
          el.setAttribute('aria-label', 'Chọn ảnh chi tiết cần xem');
          el.innerHTML = items
            .map(
              (item, i) => `
              <button type="button" class="drv-pswp__thumb" data-drv-pswp-thumb="${i}" aria-label="${escapeHtml(`Xem ảnh ${i + 1}: ${item.alt}`)}">
                <img src="${escapeHtml(item.thumb)}" alt="" loading="lazy" decoding="async" draggable="false">
              </button>`,
            )
            .join('');
          el.addEventListener('click', (event) => {
            const btn = event.target instanceof Element ? event.target.closest('[data-drv-pswp-thumb]') : null;
            if (btn) {
              pswp.goTo(Number.parseInt(btn.getAttribute('data-drv-pswp-thumb') || '0', 10));
            }
          });
          pswp.on('change', () => {
            for (const btn of qsa('[data-drv-pswp-thumb]', el)) {
              const active = Number(btn.getAttribute('data-drv-pswp-thumb')) === pswp.currIndex;
              btn.classList.toggle('is-active', active);
              btn.setAttribute('aria-current', active ? 'true' : 'false');
              if (active) {
                btn.scrollIntoView({ block: 'nearest', inline: 'nearest' });
              }
            }
          });
        },
      });
    }
  });

  pswp.on('destroy', () => {
    if (activeLightbox === pswp) {
      activeLightbox = null;
    }
    if (triggerLink.isConnected) {
      triggerLink.focus({ preventScroll: true });
    }
  });

  activeLightbox = pswp;
  pswp.init();
}
