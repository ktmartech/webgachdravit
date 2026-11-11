/**
 * DRAVIT <drv-size-guide> — Light DOM Web Component
 * Sơ đồ tỷ lệ khổ gạch + chọn theo phòng + lọc bề mặt theo khu vực + CTA AI tư vấn.
 */
(() => {
  'use strict';

  const SIZES = {
    '15x90': { w: 15, h: 90, rate: '7,41 viên/m²', box: '8 viên = 1,08 m²', grout: '2 – 3 mm' },
    '30x60': { w: 30, h: 60, rate: '5,56 viên/m²', box: '6 viên = 1,08 m²', grout: '2 – 3 mm' },
    '60x60': { w: 60, h: 60, rate: '2,78 viên/m²', box: '4 viên = 1,44 m²', grout: '1,5 – 2 mm' },
    '80x80': { w: 80, h: 80, rate: '1,56 viên/m²', box: '3 viên = 1,92 m²', grout: '1,5 – 2 mm' },
    '60x120': { w: 60, h: 120, rate: '1,39 viên/m²', box: '2 viên = 1,44 m²', grout: '1,5 – 2 mm' },
  };

  // Chiều dài ron trên 1 m² ~ 1/w + 1/h
  const groutLen = (k) => 100 / SIZES[k].w + 100 / SIZES[k].h;

  class DrvSizeGuide extends HTMLElement {
    connectedCallback() {
      if (this._ready) return;
      this._ready = true;
      this.size = '80x80';
      this.room = 'Phòng trên 20 m²';
      this._bind();
      this._render(false);
      this._initScaleEntrance();
    }

    _$(s) { return this.querySelector(s); }
    _out(k, v) { this.querySelectorAll(`[data-sg-out="${k}"]`).forEach((e) => { e.textContent = v; }); }

    _canAnimate() {
      return (
        typeof window.anime === 'function' &&
        !(window.Dravit && window.Dravit.motion && window.Dravit.motion.prefersReducedMotion())
      );
    }

    _initScaleEntrance() {
      const canvas = this._$('.drv-scale-canvas');
      if (!canvas || !this._canAnimate() || !('IntersectionObserver' in window)) return;
      const tiles = Array.from(canvas.querySelectorAll('.drv-scale-item'));
      if (!tiles.length) return;

      tiles.forEach((el) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(14px) scale(0.94)';
      });

      const obs = new IntersectionObserver(
        (entries, observer) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            window.anime.remove(tiles);
            window.anime({
              targets: tiles,
              opacity: [0, 1],
              translateY: [14, 0],
              scale: [0.94, 1],
              delay: window.anime.stagger(60, { start: 80 }),
              duration: 440,
              easing: 'easeOutExpo',
              complete() {
                tiles.forEach((el) => {
                  el.style.opacity = '';
                  el.style.transform = '';
                });
              },
            });
          });
        },
        { threshold: 0.15 }
      );
      obs.observe(canvas);
    }

    _render(animate = true) {
      const s = SIZES[this.size];
      this.querySelectorAll('[data-sg-size]').forEach((b) => b.classList.toggle('is-active', b.dataset.sgSize === this.size));
      this.querySelectorAll('[data-sg-room]').forEach((b) => b.classList.toggle('is-active', b.dataset.sgRoom === this.room));
      this._$('[data-sg-spec="rate"]').textContent = s.rate;
      this._$('[data-sg-spec="box"]').textContent = s.box;
      this._$('[data-sg-spec="grout"]').textContent = s.grout;
      this._out('size', `${s.w}×${s.h} cm`);
      this._out('size-short', this.size);
      this._out('room', this.room);

      const floor = this._$('[data-sg-floor]');
      floor.style.setProperty('--_tw', s.w);
      floor.style.setProperty('--_th', s.h);
      const cut = Math.round((1 - groutLen(this.size) / groutLen('60x60')) * 100);
      this._$('[data-sg-grout-note]').textContent = cut === 0 ? 'chuẩn tham chiếu 60x60'
        : cut > 0 ? `giảm ${cut}% so với 60x60` : `tăng ${-cut}% so với 60x60`;

      const cat = this._$('[data-sg-catalog]');
      if (cat) cat.href = `02-catalog-collection.html?kich-thuoc=${this.size}`;
      const ai = this._$('[data-sg-ai]');
      if (ai) ai.href = `01.1-ai-finder.html?kich-thuoc=${this.size}`;

      if (animate && this._canAnimate()) {
        const specCells = Array.from(this.querySelectorAll('.drv-sg-spec dd'));
        if (specCells.length) {
          window.anime.remove(specCells);
          window.anime({
            targets: specCells,
            opacity: [0.35, 1],
            translateY: [4, 0],
            delay: window.anime.stagger(35),
            duration: 260,
            easing: 'easeOutExpo',
            complete() {
              specCells.forEach((el) => {
                el.style.opacity = '';
                el.style.transform = '';
              });
            },
          });
        }
      }
    }

    _mode(mode) {
      this.querySelectorAll('[data-sg-mode]').forEach((b) => {
        const on = b.dataset.sgMode === mode;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', String(on));
      });
      this.querySelectorAll('[data-sg-view]').forEach((v) => {
        const active = v.dataset.sgView === mode;
        v.hidden = !active;
        if (active && this._canAnimate()) {
          window.anime.remove(v);
          window.anime({
            targets: v,
            opacity: [0, 1],
            translateY: [8, 0],
            duration: 300,
            easing: 'easeOutExpo',
            complete() {
              v.style.opacity = '';
              v.style.transform = '';
            },
          });
        }
      });
    }

    _zone(zone) {
      this.querySelectorAll('[data-sg-zone]').forEach((b) => {
        const on = b.dataset.sgZone === zone;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', String(on));
      });
      const visibleItems = [];
      this.querySelectorAll('[data-sg-surface]').forEach((li) => {
        const hide = zone !== 'all' && !li.dataset.sgSurface.split(' ').includes(zone);
        li.hidden = hide;
        if (!hide) visibleItems.push(li);
      });
      if (visibleItems.length && this._canAnimate()) {
        window.anime.remove(visibleItems);
        window.anime({
          targets: visibleItems,
          opacity: [0, 1],
          translateY: [10, 0],
          delay: window.anime.stagger(45),
          duration: 320,
          easing: 'easeOutExpo',
          complete() {
            visibleItems.forEach((el) => {
              el.style.opacity = '';
              el.style.transform = '';
            });
          },
        });
      }
    }

    _bind() {
      this.addEventListener('click', (e) => {
        const t = (sel) => e.target.closest(sel);
        let el;
        if ((el = t('[data-sg-size]'))) { this.size = el.dataset.sgSize; this._render(); return; }
        if ((el = t('[data-sg-room]'))) { this.room = el.dataset.sgRoom; this.size = el.dataset.sgPick; this._render(); return; }
        if ((el = t('[data-sg-mode]'))) { this._mode(el.dataset.sgMode); return; }
        if ((el = t('[data-sg-zone]'))) { this._zone(el.dataset.sgZone); return; }
        if (t('[data-sg-ai]')) {
          const fab = document.getElementById('floatingLauncherBtn');
          if (fab) { e.preventDefault(); fab.click(); }
        }
      });
      const ref = this._$('[data-sg-ref]');
      const ghost = this._$('[data-sg-ghost]');
      if (ref && ghost) ref.addEventListener('change', () => { ghost.hidden = !ref.checked; });
    }
  }

  if (!customElements.get('drv-size-guide')) customElements.define('drv-size-guide', DrvSizeGuide);
})();
