import { describe, expect, it } from 'vitest';
import { computeLightboxLayout } from '../../assets/src/js/chunks/lightbox.js';

/**
 * Step 7 · Lightbox PhotoSwipe 5 (D-28) khoác giao diện modal mockup 03: khung 920px, stage 16:10, 6 thumbnail.
 */
describe('computeLightboxLayout', () => {
  it('desktop 1546×861: khung 920px căn giữa, stage nằm trong khung, vừa viewport', () => {
    const l = computeLightboxLayout(1546, 861, 6);
    expect(l.mobile).toBe(false);
    expect(l.panel.width).toBe(920);
    expect(l.panel.left).toBe(Math.round((1546 - 920) / 2));
    expect(l.panel.top).toBeGreaterThanOrEqual(0);
    expect(l.panel.top + l.panel.height).toBeLessThanOrEqual(861);

    expect(l.stage.left).toBe(l.panel.left + 24);
    expect(l.stage.width).toBe(920 - 48);
    expect(l.stage.top).toBe(l.panel.top + l.headH + 24);
    expect(l.stage.top + l.stage.height).toBeLessThan(l.panel.top + l.panel.height);
    expect(l.thumbH).toBeGreaterThan(0);
  });

  it('màn rộng & cao: stage giữ tỉ lệ 16:10 như mockup', () => {
    const l = computeLightboxLayout(1920, 1400, 6);
    expect(l.stage.height).toBe(Math.round(l.stage.width * (10 / 16)));
  });

  it('màn thấp: thu stage để toàn bộ modal vẫn nằm trong viewport', () => {
    const l = computeLightboxLayout(1440, 640, 6);
    expect(l.panel.top + l.panel.height).toBeLessThanOrEqual(640);
    expect(l.stage.height).toBeLessThan(Math.round(l.stage.width * (10 / 16)));
  });

  it('mobile < 640px: khung full-width, stage vuông, padding 12px', () => {
    const l = computeLightboxLayout(390, 844, 6);
    expect(l.mobile).toBe(true);
    expect(l.panel.width).toBe(390);
    expect(l.stage.left).toBe(12);
    expect(l.stage.height).toBe(l.stage.width);
  });

  it('chỉ 1 ảnh: không chừa chỗ cho dải thumbnail', () => {
    const one = computeLightboxLayout(1546, 1400, 1);
    const six = computeLightboxLayout(1546, 1400, 6);
    expect(one.thumbH).toBe(0);
    expect(one.panel.height).toBeLessThan(six.panel.height);
  });
});
