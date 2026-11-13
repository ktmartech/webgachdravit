/**
 * Homepage Interactive Bundle (`assets/src/js/home.js`, `docs/15 §5.4-5.5`, `D-25`, `D-28`, `D-32`).
 * - `<drv-size-guide>`: Tỷ lệ 5 khổ gạch thật trên khung 1 m², mật độ đường ron & lọc R9-R12.
 * - `<drv-showroom>`: Thanh kéo đối chiếu ảnh trước/sau khi lát gạch, chuyển khu trưng bày, copy nhanh & 3 trải nghiệm.
 * - Hero Advisor: Bóc tách thực thể tiếng Việt, LiDAR scan, spotlight 3 thẻ cam kết & điều hướng AI/Static.
 * - Hậu LCP: Nạp động `./chunks/hero-canvas.js` (4-layer Void AI Motion Engine) qua `requestIdleCallback`.
 */

import { prefersReducedMotion, qs, qsa } from './utils/dom.js';
import { formatDecimalVn, formatVnd } from './utils/format.js';
import { showToast } from './utils/toast.js';

let heroCanvasCtrl = null;

const SPACE_RULES = [
  {
    key: 'living',
    label: 'Phòng khách',
    path: '/gach-phong-khach',
    size: '80×80 cm',
    m2PerBox: 1.92,
    r: 'R9 - R10',
    spec: 'Porcelain BIa · R9',
    unitPrice: 385000,
    re: /phong\s*khach|dai\s*sanh/i,
  },
  {
    key: 'bath',
    label: 'Nhà tắm & WC',
    path: '/gach-nha-tam',
    size: '30×60 / 60×60 cm',
    m2PerBox: 1.44,
    r: 'R10 - R11',
    spec: 'Porcelain · R11',
    unitPrice: 325000,
    re: /nha\s*tam|phong\s*tam|wc|ve\s*sinh|toilet/i,
  },
  {
    key: 'kitchen',
    label: 'Phòng bếp',
    path: '/gach-phong-bep',
    size: '60×60 / 60×120 cm',
    m2PerBox: 1.44,
    r: 'R10',
    spec: 'Porcelain BIa · R10',
    unitPrice: 365000,
    re: /phong\s*bep|\bbep\b|ban\s*dao/i,
  },
  {
    key: 'bedroom',
    label: 'Phòng ngủ',
    path: '/gach-phong-ngu',
    size: '15×90 cm',
    m2PerBox: 1.08,
    r: 'R9 - R10',
    spec: 'Matt mịn · R10',
    unitPrice: 340000,
    re: /phong\s*ngu/i,
  },
  {
    key: 'facade',
    label: 'Ốp mặt tiền',
    path: '/gach-mat-tien',
    size: '60×120 cm',
    m2PerBox: 1.44,
    r: 'Chống thấm BIa',
    spec: 'Sintered · ≤ 0,5%',
    unitPrice: 460000,
    re: /mat\s*tien/i,
  },
  {
    key: 'garden',
    label: 'Sân vườn & Gara',
    path: '/gach-san-vuon',
    size: '60×60 cm dày',
    m2PerBox: 1.44,
    r: 'R11 - R12',
    spec: 'Xương đá dày · R11',
    unitPrice: 355000,
    re: /san\s*vuon|gara|o\s*to|dau\s*xe/i,
  },
  {
    key: 'outdoor',
    label: 'Ban công & Sân thượng',
    path: '/gach-ngoai-troi',
    size: '60×60 cm',
    m2PerBox: 1.44,
    r: 'R11',
    spec: 'Chống rêu · R11',
    unitPrice: 335000,
    re: /ban\s*cong|san\s*thuong|ngoai\s*troi|lo\s*gia/i,
  },
  {
    key: 'pool',
    label: 'Hồ bơi',
    path: '/gach-ho-boi',
    size: 'Mosaic vỉ',
    m2PerBox: 1.0,
    r: 'R12',
    spec: 'Mosaic kháng kiềm · R12',
    unitPrice: 480000,
    re: /ho\s*boi|be\s*boi/i,
  },
];

const VEIN_RULES = [
  { label: 'Đá nung kết', path: '/da-nung-ket', unitPrice: 540000, re: /da\s*nung\s*ket|big\s*slab/i },
  { label: 'Vân đá Marble', path: '/gach-van-da', unitPrice: 385000, re: /van\s*da|marble|calacatta|travertine/i },
  { label: 'Giả gỗ', path: '/gach-gia-go', unitPrice: 340000, re: /gia\s*go|van\s*go|\bgo\b|soi/i },
  { label: 'Vân xi măng', path: '/gach-van-xi-mang', unitPrice: 330000, re: /xi\s*mang|be\s*tong/i },
  { label: 'Terrazzo', path: '/gach-terrazzo', unitPrice: 360000, re: /terrazzo|hat\s*da/i },
  { label: 'Gạch bông', path: '/gach-bong', unitPrice: 390000, re: /gach\s*bong/i },
  { label: 'Mosaic', path: '/gach-mosaic', unitPrice: 450000, re: /mosaic/i },
];

const SIZE_RULES = [
  { key: '80x80', label: '80×80 cm', path: '/gach-80x80', m2PerBox: 1.92, re: /80\s*[x×]\s*80/i },
  { key: '60x120', label: '60×120 cm', path: '/gach-60x120', m2PerBox: 1.44, re: /60\s*[x×]\s*120/i },
  { key: '60x60', label: '60×60 cm', path: '/gach-60x60', m2PerBox: 1.44, re: /60\s*[x×]\s*60/i },
  { key: '30x60', label: '30×60 cm', path: '/gach-30x60', m2PerBox: 1.44, re: /30\s*[x×]\s*60/i },
  { key: '15x90', label: '15×90 cm', path: '/gach-15x90', m2PerBox: 1.08, re: /15\s*[x×]\s*90/i },
];

export function stripAccents(input) {
  return String(input ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D');
}

export function computeGroutDiffText(w, h, baseW = 60, baseH = 60) {
  const safeW = Math.max(1, Number(w) || 60);
  const safeH = Math.max(1, Number(h) || 60);
  const refW = Math.max(1, Number(baseW) || 60);
  const refH = Math.max(1, Number(baseH) || 60);

  const density = 1 / safeW + 1 / safeH;
  const refDensity = 1 / refW + 1 / refH;
  const diffPct = Math.round(((density - refDensity) / refDensity) * 100);

  if (Math.abs(diffPct) <= 2) {
    return `Mật độ ron tiêu chuẩn (mốc ${refW}×${refH})`;
  }
  if (diffPct < 0) {
    return `Ít hơn ~${Math.abs(diffPct)}% đường ron so với ${refW}×${refH}`;
  }
  return `Nhiều hơn ~${diffPct}% đường ron so với ${refW}×${refH}`;
}

export function parseHeroPrompt(rawText) {
  const clean = String(rawText ?? '').trim();
  const folded = stripAccents(clean).toLowerCase();

  let areaM2 = null;
  const areaMatch = folded.match(/(\d+(?:[.,]\d+)?)\s*(?:m2|m²|met\s*vuong)/i);
  if (areaMatch) {
    const parsedArea = Number.parseFloat(areaMatch[1].replace(',', '.'));
    if (Number.isFinite(parsedArea) && parsedArea > 0 && parsedArea <= 10000) {
      areaM2 = parsedArea;
    }
  }

  const spaceMatch = SPACE_RULES.find((rule) => rule.re.test(folded)) || null;
  const veinMatch = VEIN_RULES.find((rule) => rule.re.test(folded)) || null;
  const sizeMatch = SIZE_RULES.find((rule) => rule.re.test(folded)) || null;

  const rMatch = clean.match(/\bR(9|10|11|12|13)\b/i);
  const rRating = rMatch ? `R${rMatch[1]}` : spaceMatch?.r || null;

  let customUnitPrice = null;
  const fullPriceMatch = folded.match(/(\d{3})[.,](\d{3})\s*(?:d|vnd)?/i);
  const shortKMatch = folded.match(/(\d{2,4})\s*k\b/i);
  if (fullPriceMatch) {
    customUnitPrice = Number.parseInt(`${fullPriceMatch[1]}${fullPriceMatch[2]}`, 10);
  } else if (shortKMatch) {
    customUnitPrice = Number.parseInt(shortKMatch[1], 10) * 1000;
  }

  const sizeLabel = sizeMatch?.label || spaceMatch?.size || '80×80 cm';
  const m2PerBox = sizeMatch?.m2PerBox || spaceMatch?.m2PerBox || 1.92;
  const estimatedBoxes = areaM2 ? Math.max(1, Math.ceil((areaM2 * 1.05) / m2PerBox)) : null;
  const actualM2 = estimatedBoxes ? Number((estimatedBoxes * m2PerBox).toFixed(2)) : null;

  const unitPrice = customUnitPrice || veinMatch?.unitPrice || spaceMatch?.unitPrice || 365000;
  const estimatedCost = actualM2 ? Math.round((actualM2 * unitPrice) / 10000) * 10000 : null;
  const specRec = rMatch ? `Porcelain · R${rMatch[1]}` : spaceMatch?.spec || 'Porcelain BIa · R10';

  const primaryPath = spaceMatch?.path || veinMatch?.path || sizeMatch?.path || null;

  return {
    prompt: clean,
    areaM2,
    spaceKey: spaceMatch?.key || null,
    spaceLabel: spaceMatch?.label || null,
    veinLabel: veinMatch?.label || null,
    sizeLabel,
    rRating,
    specRec,
    m2PerBox,
    estimatedBoxes,
    actualM2,
    unitPrice,
    hasCustomPrice: Boolean(customUnitPrice),
    estimatedCost,
    collectionPath: primaryPath,
  };
}

function joinSitePath(catalogUrl, targetPath) {
  const base = String(catalogUrl || '/gach').replace(/\/+$/, '');
  if (!targetPath) {
    return base || '/gach';
  }
  const cleanTarget = `/${String(targetPath).replace(/^\/+|\/+$/g, '')}`;
  if (base.endsWith('/gach')) {
    return `${base.slice(0, -5)}${cleanTarget}`;
  }
  try {
    const u = new URL(base);
    return `${u.origin}${cleanTarget}`;
  } catch {
    return cleanTarget;
  }
}

export function resolveHeroDestination(parsed, options = {}) {
  const aiEnabled = Boolean(options.aiEnabled);
  const aiUrl = String(options.aiUrl || '/tro-ly-ai-tim-gach').replace(/\/+$/, '');
  const catalogUrl = String(options.catalogUrl || '/gach').replace(/\/+$/, '');
  const chipFallbackUrl = String(options.chipFallbackUrl || '').replace(/\/+$/, '');

  if (aiEnabled) {
    const prompt = String(parsed?.prompt || '').trim();
    return prompt ? `${aiUrl}?prompt=${encodeURIComponent(prompt)}` : aiUrl;
  }

  if (chipFallbackUrl) {
    return chipFallbackUrl;
  }

  if (parsed?.collectionPath) {
    return joinSitePath(catalogUrl, parsed.collectionPath);
  }

  return catalogUrl;
}

async function copyStringSafe(value) {
  const text = String(value ?? '').trim();
  if (!text) {
    return false;
  }
  if (typeof window !== 'undefined' && typeof window.Dravit?.copyText === 'function') {
    return window.Dravit.copyText(text);
  }
  if (navigator?.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      return false;
    }
  }
  return false;
}

export class SizeGuideElement extends HTMLElement {
  connectedCallback() {
    if (this._initialized) {
      return;
    }
    this._initialized = true;

    const cfgScript = qs('[data-sg-config]', this);
    let parsed = {};
    if (cfgScript?.textContent) {
      try {
        parsed = JSON.parse(cfgScript.textContent);
      } catch {
        parsed = {};
      }
    }

    this.sizes = parsed.sizes && typeof parsed.sizes === 'object' ? parsed.sizes : {};
    this.baselineKey = parsed.baseline_size || '60x60';
    this.activeSize = parsed.default_size || Object.keys(this.sizes)[0] || '80x80';
    this.activeView = 'scale';
    this.activeZone = 'all';
    this.customRecommendation = '';

    this.bindEvents();
    this.selectSize(this.activeSize);
  }

  bindEvents() {
    this.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      const sizeBtn = target.closest('[data-sg-size]');
      if (sizeBtn) {
        event.preventDefault();
        this.customRecommendation = '';
        for (const rBtn of qsa('[data-sg-room]', this)) {
          rBtn.classList.remove('is-active');
        }
        this.selectSize(sizeBtn.getAttribute('data-sg-size') || '');
        return;
      }

      const viewBtn = target.closest('[data-sg-view]');
      if (viewBtn) {
        event.preventDefault();
        this.switchView(viewBtn.getAttribute('data-sg-view') || 'scale');
        return;
      }

      const roomBtn = target.closest('[data-sg-room]');
      if (roomBtn) {
        event.preventDefault();
        for (const btn of qsa('[data-sg-room]', this)) {
          btn.classList.toggle('is-active', btn === roomBtn);
        }
        this.customRecommendation = roomBtn.getAttribute('data-recommend') || '';
        const targetSize = roomBtn.getAttribute('data-target-size') || '';
        if (targetSize) {
          this.selectSize(targetSize);
        }
        return;
      }

      const zoneBtn = target.closest('[data-sg-zone-btn]');
      if (zoneBtn) {
        event.preventDefault();
        this.filterZone(zoneBtn.getAttribute('data-sg-zone-btn') || 'all');
      }
    });

    const refCheckbox = qs('[data-sg-ref]', this);
    if (refCheckbox) {
      refCheckbox.addEventListener('change', () => {
        const refBox = qs('[data-sg-ref-box]', this);
        if (refBox) {
          refBox.hidden = !refCheckbox.checked || this.activeView !== 'scale';
        }
      });
    }
  }

  selectSize(sizeKey) {
    const info = this.sizes[sizeKey];
    if (!info) {
      return;
    }
    this.activeSize = sizeKey;

    for (const btn of qsa('[data-sg-size]', this)) {
      const active = btn.getAttribute('data-sg-size') === sizeKey;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    }

    const setOut = (key, val) => {
      const el = qs(`[data-sg-out="${key}"]`, this);
      if (el) {
        el.textContent = String(val ?? '');
      }
    };

    setOut('label', info.label);
    setOut('rate', info.rate);
    setOut('box', info.box);
    setOut('grout', info.grout);

    const baseInfo = this.sizes[this.baselineKey] || { w: 60, h: 60 };
    const diffText = computeGroutDiffText(info.w, info.h, baseInfo.w, baseInfo.h);

    const floorLabel = qs('[data-sg-floor-label]', this);
    if (floorLabel) {
      floorLabel.textContent = `Mô phỏng mặt sàn 3,6 × 2,4 m lát khổ ${info.label}`;
    }

    const groutBadge = qs('[data-sg-grout-diff]', this);
    if (groutBadge) {
      groutBadge.textContent = diffText;
    }

    const floorGrid = qs('[data-sg-floor-grid]', this);
    if (floorGrid) {
      const cellW = Math.min(50, Math.max(12.5, Number(((info.h / 360) * 100).toFixed(2))));
      const cellH = Math.min(50, Math.max(16, Number(((info.w / 240) * 100).toFixed(2))));
      floorGrid.style.setProperty('--sg-cell-w', `${cellW}%`);
      floorGrid.style.setProperty('--sg-cell-h', `${cellH}%`);
    }

    const barSummary = qs('[data-sg-bar-summary]', this);
    if (barSummary) {
      barSummary.textContent = this.customRecommendation
        ? `Khuyến nghị (${info.label}): ${this.customRecommendation}`
        : `Đang xem khổ ${info.label}: ${info.box}, mạch ron ${info.grout} (${diffText.toLowerCase()}).`;
    }

    const catLink = qs('[data-sg-cat-link]', this);
    if (catLink && info.url) {
      catLink.setAttribute('href', info.url);
    }

    const catLabel = qs('[data-sg-cat-label]', this);
    if (catLabel) {
      catLabel.textContent = `Xem gạch ${info.label}`;
    }
  }

  switchView(nextView) {
    this.activeView = nextView === 'floor' ? 'floor' : 'scale';

    for (const btn of qsa('[data-sg-view]', this)) {
      const active = btn.getAttribute('data-sg-view') === this.activeView;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    }

    const scalePanel = qs('[data-sg-scale-panel]', this);
    const floorPanel = qs('[data-sg-floor-panel]', this);
    const refBox = qs('[data-sg-ref-box]', this);
    const refCheckbox = qs('[data-sg-ref]', this);

    if (scalePanel) {
      scalePanel.hidden = this.activeView !== 'scale';
    }
    if (floorPanel) {
      floorPanel.hidden = this.activeView !== 'floor';
    }
    if (refBox) {
      refBox.hidden = this.activeView !== 'scale' || Boolean(refCheckbox && !refCheckbox.checked);
    }
  }

  filterZone(zoneKey) {
    this.activeZone = zoneKey || 'all';

    for (const btn of qsa('[data-sg-zone-btn]', this)) {
      const active = btn.getAttribute('data-sg-zone-btn') === this.activeZone;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    }

    for (const card of qsa('[data-sg-surface]', this)) {
      if (this.activeZone === 'all') {
        card.hidden = false;
        continue;
      }
      const zones = (card.getAttribute('data-zones') || '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
      card.hidden = !zones.includes(this.activeZone);
    }
  }
}

export class ShowroomElement extends HTMLElement {
  connectedCallback() {
    if (this._initialized) {
      return;
    }
    this._initialized = true;

    this.split = 50;
    this._userInteracted = false;

    this.stage = qs('[data-sr-stage]', this);
    this.afterImg = qs('[data-sr-img-after]', this);
    this.beforeWrap = qs('[data-sr-before]', this);
    this.beforeImg = qs('[data-sr-img-before]', this);
    this.divider = qs('[data-sr-divider]', this);
    this.caption = qs('[data-sr-caption]', this);

    this.bindEvents();
    this.applySplit(50);
    this.initStageIntro();
  }

  applySplit(nextPct) {
    const clamped = Math.max(5, Math.min(95, Number(nextPct) || 50));
    this.split = clamped;
    if (this.beforeWrap) {
      this.beforeWrap.style.clipPath = `inset(0 ${100 - clamped}% 0 0)`;
    }
    if (this.divider) {
      this.divider.style.left = `${clamped}%`;
      this.divider.setAttribute('aria-valuenow', String(Math.round(clamped)));
    }
  }

  updateSplitFromClientX(clientX) {
    if (!this.stage) {
      return;
    }
    const rect = this.stage.getBoundingClientRect();
    const pct = ((clientX - rect.left) / Math.max(rect.width, 1)) * 100;
    this.applySplit(Math.max(8, Math.min(92, pct)));
  }

  initStageIntro() {
    if (!this.stage || prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
      return;
    }

    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) {
            continue;
          }
          io.unobserve(entry.target);
          if (this._userInteracted || prefersReducedMotion()) {
            return;
          }
          import('animejs')
            .then(({ default: anime }) => {
              if (this._userInteracted) {
                return;
              }
              const state = { pct: 50 };
              anime({
                targets: state,
                pct: [50, 36, 64, 50],
                duration: 1150,
                delay: 200,
                easing: 'easeInOutSine',
                update: () => {
                  if (!this._userInteracted) {
                    this.applySplit(state.pct);
                  }
                },
              });
            })
            .catch(() => {});
        }
      },
      { threshold: 0.35 },
    );

    io.observe(this.stage);
  }

  switchZone(zoneKey, animate = true) {
    if (!zoneKey) {
      return;
    }

    for (const tab of qsa('[data-sr-zone]', this)) {
      const on = tab.getAttribute('data-sr-zone') === zoneKey;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    for (const group of qsa('[data-sr-group]', this)) {
      group.hidden = group.getAttribute('data-sr-group') !== zoneKey;
    }

    const activeGroup = qs(`[data-sr-group="${zoneKey}"]`, this);
    if (!activeGroup) {
      return;
    }

    const targetThumb = qs('.drv-sr__thumb.is-active', activeGroup) || qs('.drv-sr__thumb', activeGroup);
    if (targetThumb) {
      this.selectThumb(targetThumb, animate);
    }

    if (animate && !prefersReducedMotion()) {
      const thumbs = qsa('.drv-sr__thumb', activeGroup);
      if (thumbs.length > 0) {
        import('animejs')
          .then(({ default: anime }) => {
            anime.remove(thumbs);
            anime({
              targets: thumbs,
              opacity: [0, 1],
              translateY: [8, 0],
              delay: anime.stagger(50),
              duration: 320,
              easing: 'cubicBezier(0.16, 1, 0.3, 1)',
              complete: () => {
                for (const el of thumbs) {
                  el.style.opacity = '';
                  el.style.transform = '';
                }
              },
            });
          })
          .catch(() => {});
      }
    }
  }

  selectThumb(thumbBtn, animate = true) {
    const parent = thumbBtn.parentElement;
    if (parent) {
      for (const btn of qsa('.drv-sr__thumb', parent)) {
        btn.classList.toggle('is-active', btn === thumbBtn);
      }
    }

    const imgInThumb = qs('img', thumbBtn);
    const afterSrc = thumbBtn.getAttribute('data-sr-after') || imgInThumb?.getAttribute('src') || '';
    const beforeSrc = thumbBtn.getAttribute('data-sr-before') || afterSrc;
    const isRealBefore = thumbBtn.getAttribute('data-sr-real-before') === '1';
    const altText = thumbBtn.getAttribute('data-sr-alt') || imgInThumb?.getAttribute('alt') || '';
    const captionText = thumbBtn.getAttribute('data-sr-caption') || altText;

    if (this.afterImg && afterSrc) {
      this.afterImg.src = afterSrc;
      this.afterImg.alt = altText;
    }
    if (this.beforeImg && beforeSrc) {
      this.beforeImg.src = beforeSrc;
      this.beforeImg.classList.toggle('is-real-before', isRealBefore);
    }
    if (this.caption) {
      this.caption.textContent = captionText;
    }

    if (animate && !prefersReducedMotion() && this.afterImg && this.beforeImg) {
      import('animejs')
        .then(({ default: anime }) => {
          const targets = [this.afterImg, this.beforeImg];
          anime.remove(targets);
          anime({
            targets,
            opacity: [0.72, 1],
            duration: 380,
            easing: 'easeOutQuad',
            complete: () => {
              for (const el of targets) {
                el.style.opacity = '';
              }
            },
          });
        })
        .catch(() => {});
    }
  }

  async handleCopy(copyBtn) {
    const raw = copyBtn.getAttribute('data-sr-copy') || '';
    await copyStringSafe(raw);

    const icon = qs('i', copyBtn);
    copyBtn.classList.add('is-copied');
    if (icon) {
      icon.className = 'ph-bold ph-check';
    }
    window.setTimeout(() => {
      copyBtn.classList.remove('is-copied');
      if (icon) {
        icon.className = 'ph ph-copy';
      }
    }, 1600);

    showToast('Đã sao chép vào bộ nhớ tạm', 'success', 2200);
  }

  runExperience(kind) {
    if (kind === 'lighting') {
      this.switchZone('slabs', false);
      this._userInteracted = true;
      if (!prefersReducedMotion()) {
        import('animejs')
          .then(({ default: anime }) => {
            const state = { pct: 22 };
            anime({
              targets: state,
              pct: [22, 78, 50],
              duration: 1200,
              easing: 'easeInOutQuad',
              update: () => {
                this.applySplit(state.pct);
              },
            });
          })
          .catch(() => {});
      } else {
        this.applySplit(35);
      }
      showToast('Kéo thanh giữa ảnh để so sánh trước và sau khi lát gạch', 'info', 3000);
    } else if (kind === 'bath' || kind === 'lab') {
      this.switchZone(kind, true);
    }

    this.stage?.scrollIntoView({
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
      block: 'nearest',
    });
  }

  bindEvents() {
    let dragging = false;

    const startDrag = (captureTarget, event) => {
      event.preventDefault();
      this._userInteracted = true;
      dragging = true;
      this.stage?.classList.add('is-dragging');
      captureTarget?.setPointerCapture?.(event.pointerId);
      this.updateSplitFromClientX(event.clientX);
    };

    const stopDrag = () => {
      if (!dragging) {
        return;
      }
      dragging = false;
      this.stage?.classList.remove('is-dragging');
    };

    if (this.divider) {
      this.divider.addEventListener('pointerdown', (event) => {
        startDrag(this.divider, event);
      });

      this.divider.addEventListener('pointermove', (event) => {
        if (dragging) {
          this.updateSplitFromClientX(event.clientX);
        }
      });

      this.divider.addEventListener('pointerup', stopDrag);
      this.divider.addEventListener('pointercancel', stopDrag);
      this.divider.addEventListener('lostpointercapture', stopDrag);

      this.divider.addEventListener('dblclick', (event) => {
        event.preventDefault();
        this._userInteracted = true;
        this.applySplit(50);
      });

      this.divider.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
          return;
        }
        event.preventDefault();
        this._userInteracted = true;
        if (event.key === 'Home') {
          this.applySplit(10);
          return;
        }
        if (event.key === 'End') {
          this.applySplit(90);
          return;
        }
        const delta = event.key === 'ArrowRight' ? 5 : -5;
        this.applySplit(this.split + delta);
      });
    }

    if (this.stage) {
      this.stage.addEventListener('pointerdown', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('[data-sr-divider]')) {
          return;
        }
        if (event.pointerType === 'mouse' && event.button === 0) {
          startDrag(this.stage, event);
        }
      });

      this.stage.addEventListener('pointermove', (event) => {
        if (dragging) {
          this.updateSplitFromClientX(event.clientX);
        }
      });

      this.stage.addEventListener('pointerup', stopDrag);
      this.stage.addEventListener('pointercancel', stopDrag);
      this.stage.addEventListener('lostpointercapture', stopDrag);

      this.stage.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        if (target?.closest('[data-sr-divider]')) {
          return;
        }
        this._userInteracted = true;
        this.updateSplitFromClientX(event.clientX);
      });
    }

    this.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) {
        return;
      }

      const zoneBtn = target.closest('[data-sr-zone]');
      if (zoneBtn) {
        event.preventDefault();
        this.switchZone(zoneBtn.getAttribute('data-sr-zone') || '', true);
        return;
      }

      const thumbBtn = target.closest('.drv-sr__thumb');
      if (thumbBtn) {
        event.preventDefault();
        this.selectThumb(thumbBtn, true);
        return;
      }

      const copyBtn = target.closest('[data-sr-copy]');
      if (copyBtn) {
        event.preventDefault();
        this.handleCopy(copyBtn);
        return;
      }

      const expBtn = target.closest('[data-sr-exp]');
      if (expBtn) {
        event.preventDefault();
        this.runExperience(expBtn.getAttribute('data-sr-exp') || '');
      }
    });
  }
}

function initHeroSpecSpotlight(heroSection) {
  if (!(heroSection instanceof HTMLElement) || heroSection.dataset.spotlightBound === '1') {
    return;
  }
  const specCards = qsa('[data-hero-spec-grid] .drv-spec-card', heroSection);
  if (specCards.length === 0) {
    return;
  }
  heroSection.dataset.spotlightBound = '1';

  let activeIdx = 0;
  let pausedByHover = false;
  let inView = true;
  let timerId = null;

  for (const card of specCards) {
    card.addEventListener('pointerenter', () => {
      pausedByHover = true;
      for (const c of specCards) {
        c.classList.toggle('is-active', c === card);
      }
    });
    card.addEventListener('pointerleave', () => {
      pausedByHover = false;
    });
  }

  const tick = () => {
    if (pausedByHover || prefersReducedMotion()) {
      return;
    }
    activeIdx = (activeIdx + 1) % specCards.length;
    for (let i = 0; i < specCards.length; i += 1) {
      specCards[i].classList.toggle('is-active', i === activeIdx);
    }
  };

  const syncLoop = () => {
    const shouldRun = inView && !document.hidden && !prefersReducedMotion();
    if (shouldRun && timerId === null) {
      timerId = window.setInterval(tick, 2800);
    } else if (!shouldRun && timerId !== null) {
      window.clearInterval(timerId);
      timerId = null;
    }
  };

  if (typeof IntersectionObserver !== 'undefined') {
    const io = new IntersectionObserver(
      (entries) => {
        inView = Boolean(entries[0]?.isIntersecting);
        syncLoop();
      },
      { threshold: 0.1 },
    );
    io.observe(heroSection);
  }

  document.addEventListener('visibilitychange', syncLoop);
  syncLoop();
}

export function initHeroAdvisor(root) {
  if (!(root instanceof HTMLElement) || root.dataset.heroBound === '1') {
    return;
  }
  root.dataset.heroBound = '1';

  const aiEnabled = root.dataset.aiEnabled === '1';
  const aiUrl = root.dataset.aiUrl || window.DRV_CONFIG?.aiFinderUrl || '/tro-ly-ai-tim-gach';
  const catalogUrl = root.dataset.catalogUrl || window.DRV_CONFIG?.gachUrl || '/gach';

  const form = qs('[data-hero-advisor-form]', root);
  const input = qs('[data-hero-prompt-input]', root);
  const statusEl = qs('[data-hero-status]', root);
  const statusText = qs('[data-hero-status-text]', root);
  const scanOverlay = qs('[data-hero-scan-overlay]', root);
  const nlpBox = qs('[data-hero-nlp-box]', root);
  const nlpTags = qs('[data-hero-nlp-tags]', root);
  const resultBox = qs('[data-hero-result-box]', root);
  const resultSummary = qs('[data-hero-result-summary]', root);
  const resultSpec = qs('[data-hero-out-spec]', root);
  const resultCalc = qs('[data-hero-result-calc]', root);
  const resultBudget = qs('[data-hero-out-budget]', root);
  const openAiBtn = qs('[data-hero-open-ai]', root);
  const openCatBtn = qs('[data-hero-open-cat]', root);
  const resetBtn = qs('[data-hero-reset]', root);

  let activeChipFallbackUrl = '';
  let typingTimer = null;
  const idleStatusLabel = aiEnabled ? 'Sẵn sàng tư vấn' : '28 Danh mục chuẩn';

  const setStatus = (mode) => {
    if (!statusEl || !statusText) {
      return;
    }
    statusEl.classList.toggle('is-typing', mode === 'typing');
    if (mode === 'typing') {
      statusText.textContent = 'Đang nhận diện...';
    } else if (mode === 'matched') {
      statusText.textContent = 'Đã khớp thông số';
    } else {
      statusText.textContent = idleStatusLabel;
    }
  };

  const triggerLidarSweep = () => {
    if (!scanOverlay || prefersReducedMotion()) {
      return;
    }
    scanOverlay.classList.remove('is-scanning');
    void scanOverlay.offsetWidth;
    scanOverlay.classList.add('is-scanning');
  };

  const syncActionLinks = (parsed) => {
    const primaryDest = resolveHeroDestination(parsed, {
      aiEnabled,
      aiUrl,
      catalogUrl,
      chipFallbackUrl: activeChipFallbackUrl,
    });
    const staticCatDest = resolveHeroDestination(parsed, {
      aiEnabled: false,
      aiUrl,
      catalogUrl,
      chipFallbackUrl: activeChipFallbackUrl,
    });
    if (openAiBtn) {
      openAiBtn.setAttribute('href', primaryDest);
    }
    if (openCatBtn) {
      openCatBtn.setAttribute('href', staticCatDest);
    }
  };

  const renderLiveTags = (parsed, animate = false) => {
    if (!nlpBox || !nlpTags) {
      return;
    }

    const tags = [];
    if (parsed.spaceLabel) tags.push(parsed.spaceLabel);
    if (parsed.areaM2) tags.push(`${formatDecimalVn(parsed.areaM2, 1)} m²`);
    if (parsed.veinLabel) tags.push(parsed.veinLabel);
    if (parsed.rRating) tags.push(`Nhám ${parsed.rRating}`);
    if (parsed.hasCustomPrice && parsed.unitPrice) {
      tags.push(`~${Math.round(parsed.unitPrice / 1000)}k/m²`);
    }
    if (parsed.estimatedBoxes) tags.push(`~${parsed.estimatedBoxes} thùng (+5%)`);

    if (tags.length === 0) {
      nlpBox.hidden = true;
      nlpTags.replaceChildren();
      return;
    }

    const chipNodes = tags.map((text) => {
      const chip = document.createElement('span');
      chip.className = 'drv-ai-nlp-tag';
      chip.textContent = text;
      return chip;
    });

    nlpTags.replaceChildren(...chipNodes);
    nlpBox.hidden = false;

    if (animate && !prefersReducedMotion()) {
      import('animejs')
        .then(({ default: anime }) => {
          anime.remove(chipNodes);
          anime({
            targets: chipNodes,
            opacity: [0, 1],
            translateY: [6, 0],
            scale: [0.96, 1],
            delay: anime.stagger(38),
            duration: 260,
            easing: 'cubicBezier(0.16, 1, 0.3, 1)',
            complete: () => {
              for (const n of chipNodes) {
                n.style.opacity = '';
                n.style.transform = '';
              }
            },
          });
        })
        .catch(() => {});
    }
  };

  const renderPreviewSummary = (parsed, animate = false) => {
    if (!resultBox || !resultSummary || !resultCalc) {
      return;
    }
    if (!parsed.prompt) {
      resultBox.hidden = true;
      return;
    }

    const targetLabel = parsed.spaceLabel || parsed.veinLabel || 'Hạng mục công trình';
    resultSummary.textContent = `${targetLabel}: Khuyến nghị khổ ${parsed.sizeLabel}${parsed.rRating ? `, chuẩn bề mặt ${parsed.rRating}` : ''}.`;

    if (resultSpec) {
      resultSpec.textContent = parsed.specRec || 'Porcelain BIa · R10';
    }

    if (parsed.areaM2 && parsed.estimatedBoxes && parsed.actualM2) {
      resultCalc.textContent = `${parsed.estimatedBoxes} thùng (${formatDecimalVn(parsed.actualM2, 2)} m²)`;
    } else {
      resultCalc.textContent = 'Nhập m² tính thùng';
    }

    if (resultBudget) {
      resultBudget.textContent = parsed.estimatedCost
        ? `~${formatVnd(parsed.estimatedCost)}`
        : `~${formatVnd(parsed.unitPrice)}/m²`;
    }

    syncActionLinks(parsed);
    resultBox.hidden = false;

    if (animate && !prefersReducedMotion()) {
      const metrics = qsa('.drv-ai-result__metric', resultBox);
      import('animejs')
        .then(({ default: anime }) => {
          anime.remove([resultBox, ...metrics]);
          anime({
            targets: resultBox,
            opacity: [0, 1],
            translateY: [8, 0],
            duration: 320,
            easing: 'cubicBezier(0.16, 1, 0.3, 1)',
            complete: () => {
              resultBox.style.opacity = '';
              resultBox.style.transform = '';
            },
          });
          if (metrics.length > 0) {
            anime({
              targets: metrics,
              opacity: [0, 1],
              translateY: [6, 0],
              delay: anime.stagger(50, { start: 45 }),
              duration: 300,
              easing: 'cubicBezier(0.16, 1, 0.3, 1)',
              complete: () => {
                for (const m of metrics) {
                  m.style.opacity = '';
                  m.style.transform = '';
                }
              },
            });
          }
        })
        .catch(() => {});
    }
  };

  if (input) {
    input.addEventListener('input', () => {
      activeChipFallbackUrl = '';
      for (const btn of qsa('[data-hero-chip]', root)) {
        btn.classList.remove('is-active');
      }

      const trimmed = input.value.trim();
      if (!trimmed) {
        if (typingTimer !== null) {
          window.clearTimeout(typingTimer);
          typingTimer = null;
        }
        setStatus('idle');
        if (nlpBox) nlpBox.hidden = true;
        if (resultBox) resultBox.hidden = true;
        return;
      }

      setStatus('typing');
      heroCanvasCtrl?.burst?.(4);
      const parsed = parseHeroPrompt(input.value);
      renderLiveTags(parsed, false);
      syncActionLinks(parsed);

      if (typingTimer !== null) {
        window.clearTimeout(typingTimer);
      }
      typingTimer = window.setTimeout(() => {
        typingTimer = null;
        setStatus(nlpBox && !nlpBox.hidden ? 'matched' : 'idle');
      }, 480);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey && form) {
        event.preventDefault();
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });
  }

  for (const chipBtn of qsa('[data-hero-chip]', root)) {
    chipBtn.addEventListener('click', () => {
      const prompt = chipBtn.getAttribute('data-prompt') || chipBtn.textContent.trim();
      activeChipFallbackUrl = chipBtn.getAttribute('data-fallback-url') || '';

      for (const btn of qsa('[data-hero-chip]', root)) {
        btn.classList.toggle('is-active', btn === chipBtn);
      }

      if (input) {
        input.value = prompt;
      }

      triggerLidarSweep();
      heroCanvasCtrl?.burstFromElement?.(chipBtn, 14);
      setStatus('matched');

      const parsed = parseHeroPrompt(prompt);
      renderLiveTags(parsed, true);
      renderPreviewSummary(parsed, true);
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      activeChipFallbackUrl = '';
      if (typingTimer !== null) {
        window.clearTimeout(typingTimer);
        typingTimer = null;
      }
      if (input) {
        input.value = '';
        input.focus();
      }
      for (const btn of qsa('[data-hero-chip]', root)) {
        btn.classList.remove('is-active');
      }
      setStatus('idle');
      if (nlpBox) nlpBox.hidden = true;
      if (resultBox) resultBox.hidden = true;
    });
  }

  if (form) {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const rawVal = input?.value?.trim() || '';
      const parsed = parseHeroPrompt(rawVal);
      const dest = resolveHeroDestination(parsed, {
        aiEnabled,
        aiUrl,
        catalogUrl,
        chipFallbackUrl: activeChipFallbackUrl,
      });
      window.location.assign(dest);
    });
  }
}

function initExplorerTabMotion() {
  const explorer = qs('#home-explorer');
  if (!explorer || explorer.dataset.motionBound === '1') {
    return;
  }
  explorer.dataset.motionBound = '1';

  explorer.addEventListener('click', (event) => {
    if (prefersReducedMotion()) {
      return;
    }
    const target = event.target instanceof Element ? event.target : null;
    const tabBtn = target?.closest('[data-drv-action="tab-switch"]');
    if (!tabBtn) {
      return;
    }
    window.requestAnimationFrame(() => {
      const panelId = tabBtn.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;
      const cardsGrid = panel ? qs('.drv-explorer-cards', panel) : null;
      if (cardsGrid && !panel.hidden) {
        import('./chunks/motion.js')
          .then((mod) => mod.staggerReveal(cardsGrid, '.drv-card', { stagger: 55, distance: 12, duration: 360 }))
          .catch(() => {});
      }
    });
  });
}

function scheduleHeroCanvasAfterLcp() {
  if (typeof window === 'undefined' || prefersReducedMotion()) {
    return;
  }

  const canvasEl = qs('[data-drv-hero-canvas]');
  if (!(canvasEl instanceof HTMLCanvasElement)) {
    return;
  }

  const launchIdle = () => {
    const idle = window.requestIdleCallback || ((cb) => window.setTimeout(cb, 180));
    idle(() => {
      import('./chunks/hero-canvas.js')
        .then((mod) => {
          heroCanvasCtrl = mod.mountHeroCanvas(canvasEl, canvasEl.closest('.drv-hero-void'));
        })
        .catch(() => {});
    });
  };

  const lcpImg = qs('img[fetchpriority="high"]');
  if (lcpImg instanceof HTMLImageElement && !lcpImg.complete) {
    lcpImg.addEventListener('load', launchIdle, { once: true });
    lcpImg.addEventListener('error', launchIdle, { once: true });
  } else {
    launchIdle();
  }
}

export function registerHomeElements() {
  if (typeof customElements === 'undefined') {
    return;
  }
  if (!customElements.get('drv-size-guide')) {
    customElements.define('drv-size-guide', SizeGuideElement);
  }
  if (!customElements.get('drv-showroom')) {
    customElements.define('drv-showroom', ShowroomElement);
  }
}

export function initHome() {
  if (typeof document === 'undefined') {
    return;
  }
  registerHomeElements();
  for (const advisorEl of qsa('[data-drv-hero-advisor]')) {
    initHeroAdvisor(advisorEl);
  }
  for (const heroEl of qsa('.drv-hero-void')) {
    initHeroSpotlightSafe(heroEl);
  }
  initExplorerTabMotion();
  scheduleHeroCanvasAfterLcp();
}

function initHeroSpotlightSafe(heroEl) {
  initHeroSpecSpotlight(heroEl);
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHome, { once: true });
  } else {
    initHome();
  }
}
