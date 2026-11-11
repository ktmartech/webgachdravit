// @ts-check
/**
 * DRAVIT Calc Core — pure pricing / quantity maths (no DOM, no globals).
 *
 * Single source of truth for the numbers that become real money:
 *   - how many tiles / boxes an area needs (waste, rounding up to whole boxes)
 *   - weight, price and volume discount tiers
 *
 * Used by unit-calc.js (PDP / cart) and quote-sheet.js (BOQ quote). Loads as a classic script
 * (window.DravitCalc) and also as a CommonJS module so it can be unit-tested in Node:
 *   node --test "tests/*.test.mjs"
 */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    /** @type {any} */ (root).DravitCalc = api;
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /** Cutting waste added to tile orders by default (5%) */
  const WASTE_RATE = 0.05;

  /** Float-noise guard so that exact multiples never round up an extra unit */
  const EPSILON = 1e-9;

  /**
   * @typedef {Object} VolumeTier
   * @property {number} min minimum total m² (inclusive) to qualify
   * @property {number} rate discount percentage
   */

  /** Volume discount ladder, highest threshold first */
  /** @type {ReadonlyArray<Readonly<VolumeTier>>} */
  const VOLUME_TIERS = Object.freeze([
    Object.freeze({ min: 100, rate: 8 }),
    Object.freeze({ min: 30, rate: 5 }),
    Object.freeze({ min: 0, rate: 0 }),
  ]);

  /**
   * Area of one tile in m².
   * @param {number} widthMm
   * @param {number} heightMm
   * @returns {number}
   */
  function pieceAreaM2(widthMm, heightMm) {
    return (widthMm * heightMm) / 1_000_000;
  }

  /**
   * Price of one tile, rounded to whole VND.
   * @param {number} priceM2 VND per m²
   * @param {number} areaPiece m² per tile
   * @returns {number}
   */
  function pricePerPiece(priceM2, areaPiece) {
    return Math.round(priceM2 * areaPiece);
  }

  /**
   * How many tiles cover an area.
   * @param {Object} p
   * @param {number} p.areaM2 net area to cover
   * @param {boolean} p.useWaste add the standard cutting waste
   * @param {boolean} p.roundToBox round the tile count up to whole boxes
   * @param {number} p.areaPiece m² per tile
   * @param {number} p.piecesPerBox tiles per box
   * @returns {{ pieces: number, needM2: number }} pieces to order and the waste-inclusive area
   */
  function tilesForArea({ areaM2, useWaste, roundToBox, areaPiece, piecesPerBox }) {
    const needM2 = areaM2 * (useWaste ? 1 + WASTE_RATE : 1);
    let pieces = Math.max(1, Math.ceil(needM2 / areaPiece - EPSILON));
    if (roundToBox) {
      pieces = Math.ceil(pieces / piecesPerBox) * piecesPerBox;
    }
    return { pieces, needM2 };
  }

  /**
   * Whole boxes needed for an area (BOQ quote: waste is a free percentage, 0 area -> 0 boxes).
   * @param {Object} p
   * @param {number} p.areaM2 net area
   * @param {number} p.waste waste as a fraction, e.g. 0.05
   * @param {number} p.areaPiece m² per tile
   * @param {number} p.piecesPerBox tiles per box
   * @returns {number}
   */
  function boxesForArea({ areaM2, waste, areaPiece, piecesPerBox }) {
    const need = areaM2 * (1 + waste);
    return need > 0 ? Math.ceil(need / areaPiece / piecesPerBox - EPSILON) : 0;
  }

  /**
   * Split a tile count into whole boxes and loose pieces, with the Vietnamese label.
   * @param {number} pieces
   * @param {number} piecesPerBox
   * @returns {{ fullBoxes: number, loosePieces: number, boxText: string }}
   */
  function boxBreakdown(pieces, piecesPerBox) {
    const fullBoxes = Math.floor(pieces / piecesPerBox);
    const loosePieces = pieces % piecesPerBox;
    let boxText;
    if (loosePieces === 0) {
      boxText = `${fullBoxes} thùng`;
    } else if (fullBoxes > 0) {
      boxText = `${fullBoxes} thùng + ${loosePieces} viên lẻ`;
    } else {
      boxText = `${loosePieces} viên lẻ`;
    }
    return { fullBoxes, loosePieces, boxText };
  }

  /**
   * Area / weight / price for a tile count.
   * @param {Object} p
   * @param {number} p.pieces
   * @param {number} p.areaPiece m² per tile
   * @param {number} p.weightPerPiece kg per tile
   * @param {number} p.pricePiece VND per tile
   * @returns {{ actualM2: number, totalKg: number, totalVnd: number }}
   */
  function pieceTotals({ pieces, areaPiece, weightPerPiece, pricePiece }) {
    return {
      actualM2: pieces * areaPiece,
      totalKg: Math.round(pieces * weightPerPiece),
      totalVnd: pieces * pricePiece,
    };
  }

  /**
   * Discount tier for a total area.
   * @param {number} totalM2
   * @returns {Readonly<VolumeTier>}
   */
  function volumeTier(totalM2) {
    return VOLUME_TIERS.find((t) => totalM2 >= t.min) || VOLUME_TIERS[VOLUME_TIERS.length - 1];
  }

  /**
   * Next tier above the current area (undefined when already at the top).
   * @param {number} totalM2
   * @returns {Readonly<VolumeTier>|undefined}
   */
  function nextVolumeTier(totalM2) {
    return [...VOLUME_TIERS].reverse().find((t) => t.min > totalM2);
  }

  /**
   * Apply the volume discount to a subtotal.
   * @param {number} subtotal VND
   * @param {number} totalM2 total tile area deciding the tier
   * @returns {{ tier: Readonly<VolumeTier>, rate: number, discount: number, grand: number }}
   */
  function volumeDiscount(subtotal, totalM2) {
    const tier = volumeTier(totalM2);
    const discount = Math.round((subtotal * tier.rate) / 100);
    return { tier, rate: tier.rate, discount, grand: subtotal - discount };
  }

  return {
    WASTE_RATE,
    EPSILON,
    VOLUME_TIERS,
    pieceAreaM2,
    pricePerPiece,
    tilesForArea,
    boxesForArea,
    boxBreakdown,
    pieceTotals,
    volumeTier,
    nextVolumeTier,
    volumeDiscount,
  };
});
