/**
 * Lazy-loaded Void AI Motion Engine (`assets/src/js/chunks/hero-canvas.js`).
 * Ported cleanly from `docs/layout/assets/js/hero-ai.js` (ES2022 module, `D-28`):
 * - Layer 1: Isometric 3D Tile Slabs + LiDAR sweep + technical HUD coordinates
 * - Layer 2: Neural Silk Ribbons + stream photons converging into `[data-drv-hero-advisor]`
 * - Layer 3: Synapse Constellation nodes + glowing hero orbs + pointer parallax
 * - Layer 4: Data Ingestion particle bursts + expanding pulse rings on chip/prompt interaction
 * - Automatically pauses `requestAnimationFrame` when scrolled out of viewport or tab hidden.
 */

const SPEC_LABELS = [
  '800×800mm',
  'H₂O ≤ 0,5%',
  'PORCELAIN BIa',
  'ANTI-SLIP R10',
  '600×1200mm',
  '+5% HAO HỤT',
  'R11 OUTDOOR',
  'MARBLE VEIN',
];

function rafThrottle(fn) {
  let queued = false;
  return () => {
    if (queued) {
      return;
    }
    queued = true;
    window.requestAnimationFrame(() => {
      queued = false;
      fn();
    });
  };
}

export function mountHeroCanvas(canvasEl, wrapperEl) {
  const noop = () => {};
  noop.burst = () => {};
  noop.burstFromElement = () => {};

  if (!(canvasEl instanceof HTMLCanvasElement)) {
    return noop;
  }

  const ctx = canvasEl.getContext('2d');
  if (!ctx) {
    return noop;
  }

  const host = wrapperEl instanceof HTMLElement ? wrapperEl : canvasEl.parentElement;
  if (!host) {
    return noop;
  }

  const promptCard = host.querySelector('[data-drv-hero-advisor]');

  let width = 0;
  let height = 0;
  let dpr = 1;
  let time = 0;
  let lastFrameTime = performance.now();
  let dtScale = 1;
  let rafId = 0;
  let isVisible = true;
  let destroyed = false;

  const pointer = {
    x: 0,
    y: 0,
    targetX: 0,
    targetY: 0,
    active: false,
  };

  const aiCoreTarget = { x: 0, y: 0 };

  let nodes = [];
  let tileSlabs = [];
  let streamPhotons = [];
  const ingestionParticles = [];
  const pulseRings = [];

  const updateCardCenter = () => {
    if (!(promptCard instanceof HTMLElement)) {
      aiCoreTarget.x = width * 0.72;
      aiCoreTarget.y = height * 0.52;
      return;
    }
    const cRect = promptCard.getBoundingClientRect();
    const sRect = host.getBoundingClientRect();
    aiCoreTarget.x = cRect.left - sRect.left + cRect.width * 0.5;
    aiCoreTarget.y = cRect.top - sRect.top + cRect.height * 0.5;
  };

  const initSceneEntities = () => {
    const isCompact = width < 768;
    const nodeCount = isCompact ? 20 : 38;
    nodes = Array.from({ length: nodeCount }, (_, i) => {
      const isHeroOrb = i < 3;
      return {
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.28,
        vy: (Math.random() - 0.5) * 0.28,
        radius: isHeroOrb ? 3.1 : 1.2 + Math.random() * 1.4,
        isHeroOrb,
        phase: Math.random() * Math.PI * 2,
      };
    });

    if (nodes.length > 0) {
      nodes[0].x = width * 0.82;
      nodes[0].y = height * 0.16;
      nodes[0].vx = 0.08;
      nodes[0].vy = 0.05;
    }

    const slabCount = isCompact ? 5 : 8;
    tileSlabs = Array.from({ length: slabCount }, (_, i) => {
      const depth = 0.45 + Math.random() * 0.72;
      const isRectangular = Math.random() > 0.52;
      return {
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (0.12 + Math.random() * 0.16) * (Math.random() > 0.5 ? 1 : -1),
        vy: -0.08 - Math.random() * 0.13,
        w: (isRectangular ? 74 : 52) * depth,
        h: (isRectangular ? 40 : 52) * depth,
        depth,
        rotation: -0.34 + (Math.random() - 0.5) * 0.24,
        scanProgress: Math.random() * 1.8 - 0.4,
        scanSpeed: 0.00075 + Math.random() * 0.0006,
        isScanning: i % 3 === 0,
        label: SPEC_LABELS[i % SPEC_LABELS.length],
        floatPhase: Math.random() * Math.PI * 2,
      };
    });

    const photonCount = isCompact ? 7 : 14;
    streamPhotons = Array.from({ length: photonCount }, (_, i) => ({
      progress: Math.random(),
      speed: 0.0006 + Math.random() * 0.00085,
      ribbonIndex: i % 3,
      offset: (Math.random() - 0.5) * 24,
      size: 1.6 + Math.random() * 1.5,
    }));
  };

  const resize = () => {
    dpr = Math.min(window.devicePixelRatio || 1, 1.75);
    const rect = host.getBoundingClientRect();
    width = Math.max(1, Math.round(rect.width || window.innerWidth));
    height = Math.max(1, Math.round(rect.height || window.innerHeight));

    canvasEl.width = Math.floor(width * dpr);
    canvasEl.height = Math.floor(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    if (!pointer.active) {
      pointer.x = width * 0.5;
      pointer.targetX = width * 0.5;
      pointer.y = height * 0.5;
      pointer.targetY = height * 0.5;
    }

    updateCardCenter();
    initSceneEntities();
  };

  const getRibbonY = (x, ribbonIndex, t, strandOffset) => {
    const nx = x / Math.max(width, 1);
    const baseAnchorY = height * (0.36 + ribbonIndex * 0.15);
    const targetY = aiCoreTarget.y || height * 0.52;
    const convergence = Math.pow(nx, 1.35);
    const blendedBaseY = baseAnchorY * (1 - convergence * 0.55) + targetY * (convergence * 0.55);

    const wave1 = Math.sin(nx * 4.2 - t * 0.35 + ribbonIndex * 1.4 + strandOffset * 0.12) * (36 - convergence * 16);
    const wave2 = Math.cos(nx * 7.5 + t * 0.22 - ribbonIndex * 0.9) * 15;
    const parallaxY = ((pointer.y - height * 0.5) / Math.max(height, 1)) * 20 * (ribbonIndex - 1);

    return blendedBaseY + wave1 + wave2 + strandOffset + parallaxY;
  };

  const drawIsometricTileSlabs = (t) => {
    const parallaxX = (pointer.x - width * 0.5) * 0.022;
    const parallaxY = (pointer.y - height * 0.5) * 0.022;

    for (const slab of tileSlabs) {
      slab.x += slab.vx * dtScale;
      slab.y += (slab.vy + Math.sin(t * 0.95 + slab.floatPhase) * 0.1) * dtScale;

      if (slab.x < -120) slab.x = width + 110;
      if (slab.x > width + 120) slab.x = -110;
      if (slab.y < -120) slab.y = height + 110;
      if (slab.y > height + 120) slab.y = -110;

      const drawX = slab.x + parallaxX * slab.depth;
      const drawY = slab.y + parallaxY * slab.depth;

      ctx.save();
      ctx.translate(drawX, drawY);
      ctx.rotate(slab.rotation);
      ctx.transform(1, 0.28, -0.55, 0.72, 0, 0);

      const hw = slab.w;
      const hh = slab.h;
      const baseAlpha = 0.05 + slab.depth * 0.065;

      ctx.beginPath();
      if (typeof ctx.roundRect === 'function') {
        ctx.roundRect(-hw, -hh, hw * 2, hh * 2, 4);
      } else {
        ctx.rect(-hw, -hh, hw * 2, hh * 2);
      }
      ctx.fillStyle = `rgba(20, 184, 166, ${baseAlpha * 0.38})`;
      ctx.fill();
      ctx.lineWidth = 1;
      ctx.strokeStyle = `rgba(45, 212, 191, ${baseAlpha * 1.7})`;
      ctx.stroke();

      ctx.beginPath();
      ctx.moveTo(-hw, hh);
      ctx.lineTo(-hw + 4, hh + 5);
      ctx.lineTo(hw + 4, hh + 5);
      ctx.lineTo(hw, hh);
      ctx.strokeStyle = `rgba(45, 212, 191, ${baseAlpha * 0.9})`;
      ctx.stroke();

      if (slab.isScanning) {
        slab.scanProgress += slab.scanSpeed * dtScale;
        if (slab.scanProgress > 1.65) {
          slab.scanProgress = -0.45;
        }

        if (slab.scanProgress >= 0 && slab.scanProgress <= 1) {
          const scanY = -hh + slab.scanProgress * (hh * 2);
          const scanAlpha = Math.sin(slab.scanProgress * Math.PI) * 0.5;

          const trailHeight = Math.min(18, scanY + hh);
          if (trailHeight > 1) {
            const trailGrad = ctx.createLinearGradient(0, scanY - trailHeight, 0, scanY);
            trailGrad.addColorStop(0, 'rgba(45, 212, 191, 0)');
            trailGrad.addColorStop(1, `rgba(45, 212, 191, ${scanAlpha * 0.25})`);
            ctx.fillStyle = trailGrad;
            ctx.fillRect(-hw + 2, scanY - trailHeight, (hw - 2) * 2, trailHeight);
          }

          ctx.beginPath();
          ctx.moveTo(-hw + 2, scanY);
          ctx.lineTo(hw - 2, scanY);
          ctx.strokeStyle = `rgba(153, 246, 228, ${scanAlpha})`;
          ctx.lineWidth = 1.2;
          ctx.stroke();
        }
      }

      ctx.restore();

      if (slab.isScanning && slab.scanProgress > 0.12 && slab.scanProgress < 0.88) {
        const tagAlpha = Math.sin(((slab.scanProgress - 0.12) / 0.76) * Math.PI) * 0.42;
        ctx.font = '500 10px ui-monospace, "Cascadia Code", monospace';
        ctx.fillStyle = `rgba(153, 246, 228, ${tagAlpha})`;
        ctx.fillText(slab.label, drawX + slab.w * 0.55, drawY - slab.h * 0.45);
      }
    }
  };

  const drawNeuralSilkRibbons = (t) => {
    const strandsPerRibbon = width < 768 ? 4 : 7;
    const stepX = width < 768 ? 28 : 20;

    for (let r = 0; r < 3; r += 1) {
      for (let s = 0; s < strandsPerRibbon; s += 1) {
        const strandOffset = (s - strandsPerRibbon / 2) * 6.5;
        const alpha = 0.028 + (1 - Math.abs(s - strandsPerRibbon / 2) / strandsPerRibbon) * 0.055;

        ctx.beginPath();
        for (let x = 0; x <= width + stepX; x += stepX) {
          const y = getRibbonY(x, r, t, strandOffset);
          if (x === 0) {
            ctx.moveTo(x, y);
          } else {
            ctx.lineTo(x, y);
          }
        }

        ctx.strokeStyle =
          r === 1 ? `rgba(45, 212, 191, ${alpha * 1.25})` : `rgba(20, 184, 166, ${alpha})`;
        ctx.lineWidth = 1;
        ctx.stroke();
      }
    }

    for (const p of streamPhotons) {
      p.progress += p.speed * dtScale;
      if (p.progress > 1) {
        p.progress = 0;
      }

      const x = p.progress * width;
      const y = getRibbonY(x, p.ribbonIndex, t, p.offset);
      const tailX = Math.max(0, (p.progress - 0.035) * width);
      const tailY = getRibbonY(tailX, p.ribbonIndex, t, p.offset);

      const grad = ctx.createLinearGradient(tailX, tailY, x, y);
      grad.addColorStop(0, 'rgba(45, 212, 191, 0)');
      grad.addColorStop(1, 'rgba(153, 246, 228, 0.78)');

      ctx.beginPath();
      ctx.moveTo(tailX, tailY);
      ctx.lineTo(x, y);
      ctx.strokeStyle = grad;
      ctx.lineWidth = p.size;
      ctx.lineCap = 'round';
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(x, y, p.size * 0.95, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(204, 251, 241, 0.95)';
      ctx.fill();
    }
  };

  const drawSynapseConstellation = (t) => {
    const maxLinkDist = width < 768 ? 110 : 148;

    for (let i = 0; i < nodes.length; i += 1) {
      const n = nodes[i];
      n.x += n.vx * dtScale;
      n.y += n.vy * dtScale;

      if (n.x < 0 || n.x > width) n.vx *= -1;
      if (n.y < 0 || n.y > height) n.vy *= -1;

      for (let j = i + 1; j < nodes.length; j += 1) {
        const m = nodes[j];
        const dist = Math.hypot(n.x - m.x, n.y - m.y);
        if (dist < maxLinkDist) {
          const linkAlpha = Math.pow(1 - dist / maxLinkDist, 2) * 0.16;
          ctx.beginPath();
          ctx.moveTo(n.x, n.y);
          ctx.lineTo(m.x, m.y);
          ctx.strokeStyle = `rgba(45, 212, 191, ${linkAlpha})`;
          ctx.lineWidth = 0.8;
          ctx.stroke();
        }
      }

      if (pointer.active) {
        const pDist = Math.hypot(n.x - pointer.x, n.y - pointer.y);
        if (pDist < 170) {
          const pAlpha = (1 - pDist / 170) * 0.22;
          ctx.beginPath();
          ctx.moveTo(n.x, n.y);
          ctx.lineTo(pointer.x, pointer.y);
          ctx.strokeStyle = `rgba(94, 234, 212, ${pAlpha})`;
          ctx.lineWidth = 0.9;
          ctx.stroke();
        }
      }

      const pulse = 0.65 + 0.35 * Math.sin(t * 2.2 + n.phase);
      if (n.isHeroOrb) {
        const halo = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, 18 * pulse);
        halo.addColorStop(0, 'rgba(52, 211, 153, 0.85)');
        halo.addColorStop(0.35, 'rgba(20, 184, 166, 0.28)');
        halo.addColorStop(1, 'rgba(20, 184, 166, 0)');
        ctx.beginPath();
        ctx.arc(n.x, n.y, 18 * pulse, 0, Math.PI * 2);
        ctx.fillStyle = halo;
        ctx.fill();

        ctx.beginPath();
        ctx.arc(n.x, n.y, 2.8, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(167, 243, 208, 0.96)';
        ctx.fill();
      } else {
        ctx.beginPath();
        ctx.arc(n.x, n.y, n.radius, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(94, 234, 212, ${0.25 + pulse * 0.3})`;
        ctx.fill();
      }
    }
  };

  const drawIngestionEffects = () => {
    for (let i = ingestionParticles.length - 1; i >= 0; i -= 1) {
      const p = ingestionParticles[i];
      p.progress += p.speed * 0.65 * dtScale;

      if (p.progress >= 1) {
        ingestionParticles.splice(i, 1);
        continue;
      }

      const u = 1 - p.progress;
      const tt = p.progress;
      const x = u * u * p.startX + 2 * u * tt * p.ctrlX + tt * tt * aiCoreTarget.x;
      const y = u * u * p.startY + 2 * u * tt * p.ctrlY + tt * tt * aiCoreTarget.y;

      ctx.beginPath();
      ctx.arc(x, y, p.size * (1 - tt * 0.35), 0, Math.PI * 2);
      ctx.fillStyle = `rgba(110, 231, 183, ${1 - tt * 0.5})`;
      ctx.shadowColor = 'rgba(45, 212, 191, 0.9)';
      ctx.shadowBlur = 8;
      ctx.fill();
      ctx.shadowBlur = 0;
    }

    for (let i = pulseRings.length - 1; i >= 0; i -= 1) {
      const ring = pulseRings[i];
      ring.radius += 2.1 * dtScale;
      ring.alpha *= Math.pow(0.982, dtScale);

      if (ring.radius >= ring.maxRadius || ring.alpha < 0.01) {
        pulseRings.splice(i, 1);
        continue;
      }

      ctx.beginPath();
      ctx.arc(ring.x, ring.y, ring.radius, 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(45, 212, 191, ${ring.alpha})`;
      ctx.lineWidth = 1.2;
      ctx.stroke();
    }
  };

  const spawnBurst = (count = 14, originX = null, originY = null) => {
    if (destroyed) {
      return;
    }
    updateCardCenter();
    for (let i = 0; i < count; i += 1) {
      const startX =
        originX !== null ? originX + (Math.random() - 0.5) * 80 : Math.random() * (width * 0.55);
      const startY =
        originY !== null
          ? originY + (Math.random() - 0.5) * 50
          : height * (0.2 + Math.random() * 0.6);

      ingestionParticles.push({
        startX,
        startY,
        ctrlX: (startX + aiCoreTarget.x) * 0.5 + (Math.random() - 0.5) * 180,
        ctrlY: (startY + aiCoreTarget.y) * 0.5 + (Math.random() - 0.5) * 180,
        progress: 0,
        speed: 0.018 + Math.random() * 0.022,
        size: 1.8 + Math.random() * 2,
      });
    }

    pulseRings.push({
      x: aiCoreTarget.x,
      y: aiCoreTarget.y,
      radius: 28,
      maxRadius: 320,
      alpha: 0.42,
    });
  };

  const burstFromElement = (sourceEl, count = 14) => {
    if (!(sourceEl instanceof HTMLElement)) {
      spawnBurst(count);
      return;
    }
    const chipRect = sourceEl.getBoundingClientRect();
    const heroRect = host.getBoundingClientRect();
    spawnBurst(
      count,
      chipRect.left - heroRect.left + chipRect.width * 0.5,
      chipRect.top - heroRect.top + chipRect.height * 0.5,
    );
  };

  const drawFrame = (now) => {
    rafId = 0;
    if (destroyed || !isVisible || document.hidden) {
      return;
    }

    const currentNow = typeof now === 'number' ? now : performance.now();
    const elapsedMs = Math.max(1, currentNow - lastFrameTime);
    lastFrameTime = currentNow;
    dtScale = Math.min(elapsedMs / 16.667, 2.5);

    time += 0.005 * dtScale;
    pointer.x += (pointer.targetX - pointer.x) * (0.05 * dtScale);
    pointer.y += (pointer.targetY - pointer.y) * (0.05 * dtScale);

    ctx.clearRect(0, 0, width, height);

    drawIsometricTileSlabs(time);
    drawNeuralSilkRibbons(time);
    drawSynapseConstellation(time);
    drawIngestionEffects();

    rafId = window.requestAnimationFrame(drawFrame);
  };

  const startLoop = () => {
    if (!destroyed && isVisible && !document.hidden && rafId === 0) {
      lastFrameTime = performance.now();
      rafId = window.requestAnimationFrame(drawFrame);
    }
  };

  const stopLoop = () => {
    if (rafId !== 0) {
      window.cancelAnimationFrame(rafId);
      rafId = 0;
    }
  };

  const onMouseMove = (event) => {
    pointer.active = true;
    const rect = host.getBoundingClientRect();
    pointer.targetX = event.clientX - rect.left;
    pointer.targetY = event.clientY - rect.top;
  };

  const onTouchMove = (event) => {
    if (event.touches && event.touches.length > 0) {
      pointer.active = true;
      const rect = host.getBoundingClientRect();
      pointer.targetX = event.touches[0].clientX - rect.left;
      pointer.targetY = event.touches[0].clientY - rect.top;
    }
  };

  const throttledResize = rafThrottle(resize);
  const throttledScroll = rafThrottle(() => {
    if (isVisible) {
      updateCardCenter();
    }
  });

  resize();

  let io = null;
  if (typeof IntersectionObserver !== 'undefined') {
    io = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        isVisible = Boolean(entry?.isIntersecting);
        if (isVisible) {
          updateCardCenter();
          startLoop();
        } else {
          stopLoop();
        }
      },
      { threshold: 0.05 },
    );
    io.observe(host);
  }

  const onVisibility = () => {
    if (document.hidden) {
      stopLoop();
    } else {
      startLoop();
    }
  };

  host.addEventListener('mousemove', onMouseMove, { passive: true });
  host.addEventListener('touchmove', onTouchMove, { passive: true });
  window.addEventListener('resize', throttledResize, { passive: true });
  window.addEventListener('scroll', throttledScroll, { passive: true });
  document.addEventListener('visibilitychange', onVisibility);
  startLoop();

  const cleanup = () => {
    destroyed = true;
    stopLoop();
    io?.disconnect();
    host.removeEventListener('mousemove', onMouseMove);
    host.removeEventListener('touchmove', onTouchMove);
    window.removeEventListener('resize', throttledResize);
    window.removeEventListener('scroll', throttledScroll);
    document.removeEventListener('visibilitychange', onVisibility);
  };

  cleanup.burst = spawnBurst;
  cleanup.burstFromElement = burstFromElement;
  return cleanup;
}
