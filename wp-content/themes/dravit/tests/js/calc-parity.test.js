import { describe, expect, it } from 'vitest';
import {
  calcBoqSlot,
  calcCartLine,
  calcFromBoxes,
  calcFromM2,
  calcFromPieces,
  calcVolumeDiscount,
  quickAddPieces,
  resolveVehicleHint,
} from '../../assets/src/js/utils/calc.js';
import vectors from '../fixtures/calc-vectors.json';

describe('Step 2 Client <-> Server Calc Parity (calc-vectors.json)', () => {
  it('matches all calcFromM2 vectors 1:1 with PHP', () => {
    for (const c of vectors.fromM2) {
      const actual = calcFromM2(c.input);
      for (const [key, expectedVal] of Object.entries(c.expected)) {
        if (typeof expectedVal === 'number' && !Number.isInteger(expectedVal)) {
          expect(actual[key], `${c.id} -> ${key}`).toBeCloseTo(expectedVal, 6);
        } else {
          expect(actual[key], `${c.id} -> ${key}`).toBe(expectedVal);
        }
      }
    }
  });

  it('matches all calcFromBoxes vectors 1:1 with PHP', () => {
    for (const c of vectors.fromBoxes) {
      const actual = calcFromBoxes(c.input);
      for (const [key, expectedVal] of Object.entries(c.expected)) {
        if (typeof expectedVal === 'number' && !Number.isInteger(expectedVal)) {
          expect(actual[key], `${c.id} -> ${key}`).toBeCloseTo(expectedVal, 6);
        } else {
          expect(actual[key], `${c.id} -> ${key}`).toBe(expectedVal);
        }
      }
    }
  });

  it('matches all calcFromPieces & calcCartLine vectors 1:1 with PHP', () => {
    for (const c of vectors.fromPieces) {
      const actual = calcFromPieces(c.input);
      for (const [key, expectedVal] of Object.entries(c.expected)) {
        if (typeof expectedVal === 'number' && !Number.isInteger(expectedVal)) {
          expect(actual[key], `${c.id} -> ${key}`).toBeCloseTo(expectedVal, 6);
        } else {
          expect(actual[key], `${c.id} -> ${key}`).toBe(expectedVal);
        }
      }
    }

    for (const c of vectors.cartLine) {
      const actual = calcCartLine(c.input);
      for (const [key, expectedVal] of Object.entries(c.expected)) {
        if (typeof expectedVal === 'number' && !Number.isInteger(expectedVal)) {
          expect(actual[key], `${c.id} -> ${key}`).toBeCloseTo(expectedVal, 6);
        } else {
          expect(actual[key], `${c.id} -> ${key}`).toBe(expectedVal);
        }
      }
    }
  });

  it('matches BOQ slot, volume discount, vehicle hint & quickAdd vectors 1:1 with PHP', () => {
    for (const c of vectors.boqSlot) {
      const actual = calcBoqSlot(c.input);
      for (const [key, expectedVal] of Object.entries(c.expected)) {
        if (typeof expectedVal === 'number' && !Number.isInteger(expectedVal)) {
          expect(actual[key]).toBeCloseTo(expectedVal, 6);
        } else {
          expect(actual[key]).toBe(expectedVal);
        }
      }
    }

    for (const v of vectors.volumeDiscount) {
      const disc = calcVolumeDiscount(v.subtotal, v.m2);
      expect(disc.rate).toBe(v.rate);
      expect(disc.discountVnd).toBe(v.discountVnd);
      expect(disc.grandVnd).toBe(v.grandVnd);
    }

    for (const vh of vectors.vehicleHint) {
      expect(resolveVehicleHint(vh.kg)).toBe(vh.expected);
    }

    for (const qa of vectors.quickAdd) {
      expect(quickAddPieces(qa.input)).toBe(qa.expected);
    }
  });
});
