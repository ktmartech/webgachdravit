import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Dravit, initCore, registerAction, resetActionsForTests } from '../../assets/src/js/core.js';
import { formatDecimal, formatM2, formatVnd } from '../../assets/src/js/utils/format.js';

describe('Step 0 JS Harness & Core Action Registry', () => {
  beforeEach(() => {
    resetActionsForTests();
    document.body.innerHTML = '';
    initCore(document);
  });

  it('formats Vietnamese currency, decimals, and m² per Dravit coding standards', () => {
    expect(formatVnd(385000)).toBe('385.000đ');
    expect(formatVnd(246400.4)).toBe('246.400đ');
    expect(formatDecimal(1.92)).toBe('1,92');
    expect(formatDecimal(8.5)).toBe('8,5');
    expect(formatM2(30.72)).toBe('30,72 m²');
  });

  it('dispatches delegated data-drv-action clicks on dynamically inserted DOM elements', () => {
    const spy = vi.fn();
    registerAction('quote-open', spy);

    document.body.innerHTML = `
      <div class="wrapper">
        <button type="button" data-drv-action="quote-open" data-sku="DRV-8801M">
          <span>Báo giá công trình</span>
        </button>
      </div>
    `;

    const innerSpan = document.querySelector('button span');
    innerSpan.click();

    expect(spy).toHaveBeenCalledTimes(1);
    const [triggerEl] = spy.mock.calls[0];
    expect(triggerEl.dataset.sku).toBe('DRV-8801M');
  });

  it('exposes frozen Dravit global namespace on window', () => {
    expect(window.Dravit).toBe(Dravit);
    expect(Object.isFrozen(Dravit)).toBe(true);
  });
});

