/**
 * DRAVIT Hero AI Motion Engine & Interactive Consultation (`hero-ai.js`)
 * - Canvas AI Motion Engine (4 layers): Neural Silk Waves, 3D Isometric Tile Slabs + LiDAR, Synapse Constellation, Data Ingestion Bursts.
 * - Vietnamese NLP entity extractor (Space, Area, Tile Size, Waste +5%, Budget).
 * - Sequential Pipeline Spotlight on technical specs.
 * - IntersectionObserver GPU pause when scrolled out of viewport.
 */

(function () {
  'use strict';

  function initHeroAI() {
    const heroSection = document.getElementById('home-hero');
    const canvas = document.getElementById('aiMotionCanvas');
    if (!heroSection || !canvas) return;

    const ctx = canvas.getContext('2d');
    const aiPromptCard = document.getElementById('aiPromptCard');
    const form = document.getElementById('drv-hero-ai-form');
    const input = document.getElementById('drv-hero-ai-input');
    const areaHiddenInput = document.getElementById('drv-ai-area-input');
    const statusDotWrapper = document.getElementById('statusDotWrapper');
    const statusEqualizer = document.getElementById('statusEqualizer');
    const statusText = document.getElementById('statusText');
    const extractedEntitiesBox = document.getElementById('extractedEntitiesBox');
    const entityBadgesList = document.getElementById('entityBadgesList');
    const clearPromptBtn = document.getElementById('clearPromptBtn');
    const promptChips = document.querySelectorAll('.drv-ai-prompt-chip');
    const submitBtn = document.getElementById('submitConsultBtn');
    const ctaBtnLabel = document.getElementById('ctaBtnLabel');
    const cardScanOverlay = document.getElementById('cardScanOverlay');
    const aiResultPanel = document.getElementById('aiResultPanel');
    const closeResultBtn = document.getElementById('closeResultBtn');
    const resSpec = document.getElementById('resSpec');
    const resBoxes = document.getElementById('resBoxes');
    const resBudget = document.getElementById('resBudget');
    const specCards = document.querySelectorAll('#specCardsContainer .drv-spec-card');

    let width = 0;
    let height = 0;
    let dpr = 1;
    let time = 0;
    let lastFrameTime = performance.now();
    let dtScale = 1;
    let animFrameId = null;
    let isIntersecting = true;

    const pointer = {
      x: 0,
      y: 0,
      targetX: 0,
      targetY: 0,
      active: false
    };

    const aiCoreTarget = { x: 0, y: 0 };

    let nodes = [];
    let tileSlabs = [];
    let streamPhotons = [];
    let ingestionParticles = [];
    let pulseRings = [];

    const SPEC_LABELS = [
      '800×800mm',
      'H₂O ≤ 0.5%',
      'PORCELAIN',
      'ANTI-SLIP R10',
      '600×1200mm',
      '+5% HAO HỤT',
      'R11 OUTDOOR',
      'MARBLE VEIN'
    ];

    function updateCanvasMetrics() {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      const rect = heroSection.getBoundingClientRect();
      width = rect.width || window.innerWidth;
      height = rect.height || window.innerHeight;

      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

      if (!pointer.active) {
        pointer.x = pointer.targetX = width * 0.5;
        pointer.y = pointer.targetY = height * 0.5;
      }

      updateAICardCenter();
      initSceneEntities();
    }

    function updateAICardCenter() {
      if (!aiPromptCard) return;
      const cRect = aiPromptCard.getBoundingClientRect();
      const sRect = heroSection.getBoundingClientRect();
      aiCoreTarget.x = (cRect.left - sRect.left) + cRect.width * 0.5;
      aiCoreTarget.y = (cRect.top - sRect.top) + cRect.height * 0.5;
    }

    function initSceneEntities() {
      const nodeCount = width < 768 ? 22 : 40;
      nodes = [];
      for (let i = 0; i < nodeCount; i++) {
        const isHeroOrb = i < 3;
        nodes.push({
          x: Math.random() * width,
          y: Math.random() * height,
          vx: (Math.random() - 0.5) * 0.3,
          vy: (Math.random() - 0.5) * 0.3,
          radius: isHeroOrb ? 3.2 : 1.2 + Math.random() * 1.5,
          isHeroOrb,
          phase: Math.random() * Math.PI * 2
        });
      }

      if (nodes.length > 0) {
        nodes[0].x = width * 0.82;
        nodes[0].y = height * 0.16;
        nodes[0].vx = 0.08;
        nodes[0].vy = 0.05;
      }

      const slabCount = width < 768 ? 5 : 8;
      tileSlabs = [];
      for (let i = 0; i < slabCount; i++) {
        const depth = 0.45 + Math.random() * 0.75;
        const isRectangular = Math.random() > 0.55;
        tileSlabs.push({
          x: Math.random() * width,
          y: Math.random() * height,
          vx: (0.12 + Math.random() * 0.18) * (Math.random() > 0.5 ? 1 : -1),
          vy: -0.08 - Math.random() * 0.14,
          w: (isRectangular ? 76 : 54) * depth,
          h: (isRectangular ? 42 : 54) * depth,
          depth,
          rotation: -0.35 + (Math.random() - 0.5) * 0.25,
          scanProgress: Math.random() * 1.8 - 0.4,
          scanSpeed: 0.00075 + Math.random() * 0.00065,
          isScanning: i % 3 === 0,
          label: SPEC_LABELS[i % SPEC_LABELS.length],
          floatPhase: Math.random() * Math.PI * 2
        });
      }

      const photonCount = width < 768 ? 7 : 14;
      streamPhotons = [];
      for (let i = 0; i < photonCount; i++) {
        streamPhotons.push({
          progress: Math.random(),
          speed: 0.0006 + Math.random() * 0.0009,
          ribbonIndex: i % 3,
          offset: (Math.random() - 0.5) * 24,
          size: 1.6 + Math.random() * 1.6
        });
      }
    }

    function getRibbonY(x, ribbonIndex, t, strandOffset) {
      const nx = x / Math.max(width, 1);
      const baseAnchorY = height * (0.36 + ribbonIndex * 0.15);
      const targetY = aiCoreTarget.y || height * 0.52;
      const convergence = Math.pow(nx, 1.35);
      const blendedBaseY = baseAnchorY * (1 - convergence * 0.55) + targetY * (convergence * 0.55);

      const wave1 = Math.sin(nx * 4.2 - t * 0.35 + ribbonIndex * 1.4 + strandOffset * 0.12) * (36 - convergence * 16);
      const wave2 = Math.cos(nx * 7.5 + t * 0.22 - ribbonIndex * 0.9) * 15;
      const parallaxY = ((pointer.y - height * 0.5) / Math.max(height, 1)) * 20 * (ribbonIndex - 1);

      return blendedBaseY + wave1 + wave2 + strandOffset + parallaxY;
    }

    function drawNeuralSilkRibbons(t) {
      const ribbonCount = 3;
      const strandsPerRibbon = width < 768 ? 4 : 7;
      const stepX = width < 768 ? 28 : 20;

      for (let r = 0; r < ribbonCount; r++) {
        for (let s = 0; s < strandsPerRibbon; s++) {
          const strandOffset = (s - strandsPerRibbon / 2) * 6.5;
          const alpha = (0.028 + (1 - Math.abs(s - strandsPerRibbon / 2) / strandsPerRibbon) * 0.055);

          ctx.beginPath();
          for (let x = 0; x <= width + stepX; x += stepX) {
            const y = getRibbonY(x, r, t, strandOffset);
            if (x === 0) {
              ctx.moveTo(x, y);
            } else {
              ctx.lineTo(x, y);
            }
          }

          ctx.strokeStyle = r === 1
            ? `rgba(45, 212, 191, ${alpha * 1.25})`
            : `rgba(20, 184, 166, ${alpha})`;
          ctx.lineWidth = 1;
          ctx.stroke();
        }
      }

      for (let i = 0; i < streamPhotons.length; i++) {
        const p = streamPhotons[i];
        p.progress += p.speed * dtScale;
        if (p.progress > 1) p.progress = 0;

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
        ctx.fillStyle = '#ccfbf1';
        ctx.fill();
      }
    }

    function drawIsometricTileSlabs(t) {
      const parallaxX = (pointer.x - width * 0.5) * 0.022;
      const parallaxY = (pointer.y - height * 0.5) * 0.022;

      for (let i = 0; i < tileSlabs.length; i++) {
        const slab = tileSlabs[i];
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
          if (slab.scanProgress > 1.65) slab.scanProgress = -0.45;

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
          ctx.font = '500 10px "JetBrains Mono", monospace';
          ctx.fillStyle = `rgba(153, 246, 228, ${tagAlpha})`;
          ctx.fillText(slab.label, drawX + slab.w * 0.55, drawY - slab.h * 0.45);
        }
      }
    }

    function drawSynapseConstellation(t) {
      const maxLinkDist = width < 768 ? 110 : 150;

      for (let i = 0; i < nodes.length; i++) {
        const n = nodes[i];
        n.x += n.vx;
        n.y += n.vy;

        if (n.x < 0 || n.x > width) n.vx *= -1;
        if (n.y < 0 || n.y > height) n.vy *= -1;

        for (let j = i + 1; j < nodes.length; j++) {
          const m = nodes[j];
          const dx = n.x - m.x;
          const dy = n.y - m.y;
          const dist = Math.hypot(dx, dy);

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
          const pdx = n.x - pointer.x;
          const pdy = n.y - pointer.y;
          const pDist = Math.hypot(pdx, pdy);
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
          ctx.fillStyle = '#a7f3d0';
          ctx.fill();
        } else {
          ctx.beginPath();
          ctx.arc(n.x, n.y, n.radius, 0, Math.PI * 2);
          ctx.fillStyle = `rgba(94, 234, 212, ${0.25 + pulse * 0.3})`;
          ctx.fill();
        }
      }
    }

    function spawnAIIngestionBurst(count = 16, originX = null, originY = null) {
      updateAICardCenter();
      for (let i = 0; i < count; i++) {
        const startX = originX !== null ? originX + (Math.random() - 0.5) * 80 : Math.random() * (width * 0.55);
        const startY = originY !== null ? originY + (Math.random() - 0.5) * 50 : height * (0.2 + Math.random() * 0.6);

        ingestionParticles.push({
          startX,
          startY,
          ctrlX: (startX + aiCoreTarget.x) * 0.5 + (Math.random() - 0.5) * 180,
          ctrlY: (startY + aiCoreTarget.y) * 0.5 + (Math.random() - 0.5) * 180,
          progress: 0,
          speed: 0.018 + Math.random() * 0.022,
          size: 1.8 + Math.random() * 2
        });
      }

      pulseRings.push({
        x: aiCoreTarget.x,
        y: aiCoreTarget.y,
        radius: 28,
        maxRadius: 320,
        alpha: 0.42
      });
    }

    function drawIngestionEffects() {
      for (let i = ingestionParticles.length - 1; i >= 0; i--) {
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
        ctx.shadowColor = '#2dd4bf';
        ctx.shadowBlur = 8;
        ctx.fill();
        ctx.shadowBlur = 0;
      }

      for (let i = pulseRings.length - 1; i >= 0; i--) {
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
    }

    function animateLoop(now) {
      if (!isIntersecting) return;

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

      animFrameId = requestAnimationFrame(animateLoop);
    }

    heroSection.addEventListener('mousemove', function (e) {
      pointer.active = true;
      const rect = heroSection.getBoundingClientRect();
      pointer.targetX = e.clientX - rect.left;
      pointer.targetY = e.clientY - rect.top;
    }, { passive: true });

    heroSection.addEventListener('touchmove', function (e) {
      if (e.touches && e.touches.length > 0) {
        pointer.active = true;
        const rect = heroSection.getBoundingClientRect();
        pointer.targetX = e.touches[0].clientX - rect.left;
        pointer.targetY = e.touches[0].clientY - rect.top;
      }
    }, { passive: true });

    /** Coalesce bursts of events into at most one call per animation frame */
    function rafThrottle(fn) {
      let queued = false;
      return function () {
        if (queued) return;
        queued = true;
        requestAnimationFrame(function () {
          queued = false;
          fn();
        });
      };
    }

    window.addEventListener('resize', rafThrottle(updateCanvasMetrics), { passive: true });
    window.addEventListener('scroll', rafThrottle(function () {
      if (isIntersecting) updateAICardCenter();
    }), { passive: true });

    // NLP Entity Extractor
    function parseVietnameseTilePrompt(text) {
      const clean = text.trim();
      if (!clean) return null;

      const lower = clean.toLowerCase();
      const badges = [];

      let space = 'Đa năng';
      let specRec = 'Porcelain · R9';
      let tileSize = '800×800mm';
      let m2PerBox = 1.92;

      if (lower.includes('nhà tắm') || lower.includes('vệ sinh') || lower.includes('wc') || lower.includes('ướt')) {
        space = 'Nhà tắm';
        specRec = 'Porcelain · R11';
        tileSize = '300×600mm';
        m2PerBox = 1.44;
      } else if (lower.includes('phòng ngủ') || lower.includes('giả gỗ') || lower.includes('vân gỗ')) {
        space = 'Phòng ngủ';
        specRec = 'Porcelain · R9 Matt';
        tileSize = '200×1000mm';
        m2PerBox = 1.2;
      } else if (lower.includes('sảnh') || lower.includes('biệt thự') || lower.includes('nung kết') || lower.includes('khổ lớn')) {
        space = 'Sảnh / Khổ lớn';
        specRec = 'Sintered · ≤0.1%';
        tileSize = '800×1600mm';
        m2PerBox = 2.56;
      } else if (lower.includes('ngoài trời') || lower.includes('sân vườn') || lower.includes('ban công')) {
        space = 'Ngoài trời';
        specRec = 'Granite · R11';
        tileSize = '600×600mm';
        m2PerBox = 1.44;
      } else if (lower.includes('phòng khách') || lower.includes('bếp')) {
        space = 'Phòng khách';
        specRec = 'Porcelain · ≤0.5%';
        tileSize = '800×800mm';
        m2PerBox = 1.92;
      }
      badges.push(`<i class="ph-bold ph-house-line" aria-hidden="true"></i><span>${space}</span>`);

      const areaMatch = lower.match(/(\d+(?:[.,]\d+)?)\s*m(?:2|²)/i);
      const areaVal = areaMatch ? parseFloat(areaMatch[1].replace(',', '.')) : 50;
      if (areaMatch) {
        badges.push(`<i class="ph-bold ph-ruler" aria-hidden="true"></i><span>Diện tích: ${areaVal}m²</span>`);
      }
      if (areaHiddenInput) {
        areaHiddenInput.value = String(Math.round(areaVal));
      }

      badges.push(`<i class="ph-bold ph-squares-four" aria-hidden="true"></i><span>Khổ: ${tileSize}</span>`);

      let unitPrice = 350000;
      const priceMatch = lower.match(/(\d{3})[.,]?(\d{3})\s*(?:đ|vnđ|d)/i);
      const shortKMatch = lower.match(/(\d{2,4})\s*k/i);
      if (priceMatch) {
        unitPrice = parseInt(priceMatch[1] + priceMatch[2], 10);
        badges.push(`<i class="ph-bold ph-tag" aria-hidden="true"></i><span>Giá: ${(unitPrice / 1000).toFixed(0)}k/m²</span>`);
      } else if (shortKMatch) {
        unitPrice = parseInt(shortKMatch[1], 10) * 1000;
        badges.push(`<i class="ph-bold ph-tag" aria-hidden="true"></i><span>Giá: ${shortKMatch[1]}k/m²</span>`);
      }

      const totalAreaWithWaste = areaVal * 1.05;
      const boxesNeeded = Math.max(1, Math.ceil(totalAreaWithWaste / m2PerBox));
      const estimatedCost = Math.round((boxesNeeded * m2PerBox * unitPrice) / 10000) * 10000;

      return {
        badges,
        specRec,
        boxesText: `${boxesNeeded} thùng (${tileSize.replace('mm', '')})`,
        budgetText: `~${estimatedCost.toLocaleString('vi-VN')}đ`,
        estimatedCost
      };
    }

    let typingTimer = null;
    let typewriterInterval = null;
    let lastEstimatedCost = 0;

    function canAnimateUI() {
      return (
        typeof window.anime === 'function' &&
        !(window.Dravit && window.Dravit.motion && window.Dravit.motion.prefersReducedMotion())
      );
    }

    function refreshAIExtractionUI(isTypingNow = false) {
      if (!input) return;
      const val = input.value;
      if (!val.trim()) {
        if (statusDotWrapper) statusDotWrapper.style.display = 'flex';
        if (statusEqualizer) statusEqualizer.style.display = 'none';
        if (statusText) statusText.textContent = 'Sẵn sàng phân tích';
        if (extractedEntitiesBox) extractedEntitiesBox.style.display = 'none';
        return;
      }

      if (isTypingNow) {
        if (statusDotWrapper) statusDotWrapper.style.display = 'none';
        if (statusEqualizer) statusEqualizer.style.display = 'inline-flex';
        if (statusText) statusText.textContent = 'AI đang nhận diện...';
      } else {
        if (statusDotWrapper) statusDotWrapper.style.display = 'flex';
        if (statusEqualizer) statusEqualizer.style.display = 'none';
        if (statusText) statusText.textContent = 'Đã khớp thông số kỹ thuật';
      }

      const parsed = parseVietnameseTilePrompt(val);
      if (parsed) {
        const wasHidden = extractedEntitiesBox && extractedEntitiesBox.style.display === 'none';
        if (extractedEntitiesBox) extractedEntitiesBox.style.display = 'block';
        if (entityBadgesList) {
          entityBadgesList.innerHTML = parsed.badges
            .map(b => `<span class="drv-ai-entity-tag">${b}</span>`)
            .join('');
          if ((wasHidden || !isTypingNow) && canAnimateUI()) {
            const tags = entityBadgesList.querySelectorAll('.drv-ai-entity-tag');
            window.anime.remove(tags);
            window.anime({
              targets: tags,
              opacity: [0, 1],
              translateY: [6, 0],
              scale: [0.96, 1],
              delay: window.anime.stagger(40),
              duration: 280,
              easing: 'easeOutExpo',
              complete() {
                tags.forEach(t => {
                  t.style.opacity = '';
                  t.style.transform = '';
                });
              }
            });
          }
        }
        if (resSpec) resSpec.textContent = parsed.specRec;
        if (resBoxes) resBoxes.textContent = parsed.boxesText;
        if (resBudget) resBudget.textContent = parsed.budgetText;
        lastEstimatedCost = parsed.estimatedCost || 0;
      }
    }

    function triggerCardLidarSweep() {
      if (!cardScanOverlay) return;
      cardScanOverlay.classList.remove('is-scanning');
      void cardScanOverlay.offsetWidth;
      cardScanOverlay.classList.add('is-scanning');
    }

    if (input) {
      input.addEventListener('input', function () {
        refreshAIExtractionUI(true);
        spawnAIIngestionBurst(4);

        clearTimeout(typingTimer);
        typingTimer = setTimeout(function () {
          refreshAIExtractionUI(false);
        }, 550);
      });

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (submitBtn) submitBtn.click();
        }
      });
    }

    promptChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        const sampleText = chip.getAttribute('data-prompt') || chip.textContent.trim();
        const chipRect = chip.getBoundingClientRect();
        const heroRect = heroSection.getBoundingClientRect();

        spawnAIIngestionBurst(
          14,
          (chipRect.left - heroRect.left) + chipRect.width / 2,
          (chipRect.top - heroRect.top) + chipRect.height / 2
        );
        triggerCardLidarSweep();

        clearInterval(typewriterInterval);
        if (input) input.value = '';
        if (aiResultPanel) aiResultPanel.style.display = 'none';

        let idx = 0;
        refreshAIExtractionUI(true);

        typewriterInterval = setInterval(function () {
          idx += 3;
          if (input) input.value = sampleText.slice(0, idx);
          if (idx >= sampleText.length) {
            clearInterval(typewriterInterval);
            refreshAIExtractionUI(false);
          }
        }, 16);
      });
    });

    if (clearPromptBtn) {
      clearPromptBtn.addEventListener('click', function () {
        clearInterval(typewriterInterval);
        if (input) {
          input.value = '';
          input.focus();
        }
        if (aiResultPanel) aiResultPanel.style.display = 'none';
        refreshAIExtractionUI(false);
      });
    }

    if (submitBtn) {
      submitBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (input && !input.value.trim()) {
          input.value = 'Phòng khách 50m², thích gạch vân đá sáng dễ lau chùi, tầm giá khoảng 350.000đ/m².';
        }
        refreshAIExtractionUI(true);
        triggerCardLidarSweep();
        spawnAIIngestionBurst(24);

        if (ctaBtnLabel) ctaBtnLabel.textContent = 'Đang tính dự toán...';
        setTimeout(function () {
          refreshAIExtractionUI(false);
          if (ctaBtnLabel) ctaBtnLabel.textContent = 'Nhận tư vấn ngay';
          if (aiResultPanel) {
            aiResultPanel.style.display = 'block';
            if (canAnimateUI()) {
              const items = aiResultPanel.querySelectorAll('.drv-ai-result-item');
              window.anime.remove([aiResultPanel, ...items]);
              window.anime({
                targets: aiResultPanel,
                opacity: [0, 1],
                translateY: [10, 0],
                duration: 360,
                easing: 'easeOutExpo',
                complete() {
                  aiResultPanel.style.opacity = '';
                  aiResultPanel.style.transform = '';
                }
              });
              if (items.length) {
                window.anime({
                  targets: items,
                  opacity: [0, 1],
                  translateY: [8, 0],
                  delay: window.anime.stagger(55, { start: 60 }),
                  duration: 340,
                  easing: 'easeOutExpo',
                  complete() {
                    items.forEach(el => {
                      el.style.opacity = '';
                      el.style.transform = '';
                    });
                  }
                });
              }
              if (
                resBudget &&
                lastEstimatedCost > 0 &&
                window.Dravit &&
                window.Dravit.motion &&
                typeof window.Dravit.motion.animateNumber === 'function'
              ) {
                window.Dravit.motion.animateNumber(
                  resBudget,
                  Math.round(lastEstimatedCost * 0.65),
                  lastEstimatedCost,
                  v => `~${Math.round(v).toLocaleString('vi-VN')}đ`,
                  420
                );
              }
            }
          }
        }, 850);
      });
    }

    if (closeResultBtn && aiResultPanel) {
      closeResultBtn.addEventListener('click', function () {
        aiResultPanel.style.display = 'none';
      });
    }

    // 3 Spec Cards Sequential Spotlight Pipeline
    let activePipelineIndex = 0;
    let pausePipeline = false;

    specCards.forEach(function (card) {
      card.addEventListener('mouseenter', function () {
        pausePipeline = true;
        specCards.forEach(c => c.classList.remove('is-active'));
      });
      card.addEventListener('mouseleave', function () {
        pausePipeline = false;
      });
    });

    const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    let pipelineTimer = null;

    function pipelineTick() {
      if (pausePipeline || specCards.length === 0) return;
      specCards.forEach(c => c.classList.remove('is-active'));
      specCards[activePipelineIndex].classList.add('is-active');
      activePipelineIndex = (activePipelineIndex + 1) % specCards.length;
    }

    /** Run the spotlight loop only while the hero is on screen, the tab is visible and motion is allowed */
    function syncPipeline() {
      const shouldRun = specCards.length > 0 && isIntersecting && !document.hidden && !reducedMotionQuery.matches;
      if (shouldRun && pipelineTimer === null) {
        pipelineTimer = setInterval(pipelineTick, 2800);
      } else if (!shouldRun && pipelineTimer !== null) {
        clearInterval(pipelineTimer);
        pipelineTimer = null;
      }
    }

    if (specCards.length > 0) {
      specCards[0].classList.add('is-active');
      document.addEventListener('visibilitychange', syncPipeline);
      if (reducedMotionQuery.addEventListener) reducedMotionQuery.addEventListener('change', syncPipeline);
    }

    // IntersectionObserver to pause canvas animation loop when hero is off-screen
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          isIntersecting = entry.isIntersecting;
          syncPipeline();
          if (isIntersecting) {
            updateAICardCenter();
            lastFrameTime = performance.now();
            animateLoop();
          } else {
            if (animFrameId) cancelAnimationFrame(animFrameId);
          }
        });
      }, { threshold: 0.05 });
      observer.observe(heroSection);
    }

    updateCanvasMetrics();
    animateLoop();
    syncPipeline();

    const openFullAiChatBtn = document.getElementById('openFullAiChatBtn');
    if (openFullAiChatBtn) {
      openFullAiChatBtn.addEventListener('click', function () {
        const q = (input && input.value.trim()) || 'Phòng khách 50m², thích gạch vân đá Marble sáng bóng dễ lau chùi';
        if (window.Dravit && window.Dravit.aiFinder && typeof window.Dravit.aiFinder.ask === 'function') {
          window.Dravit.aiFinder.ask(q);
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeroAI);
  } else {
    initHeroAI();
  }
})();
