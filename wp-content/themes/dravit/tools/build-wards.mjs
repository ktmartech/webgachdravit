#!/usr/bin/env node
/**
 * Sinh `assets/data/wards/{PROVINCE_KEY}.json` (docs/15 §2.7, docs/04 §5.2, O-06).
 *
 * Nguồn: danh mục đơn vị hành chính 2 cấp hiệu lực 01/07/2025 (NQ 202/2025/QH15 · 34 tỉnh/thành, 3.321 xã/phường/đặc khu),
 * mã theo Tổng cục Thống kê, lấy từ API công khai https://provinces.open-api.vn/api/v2/?depth=2
 * và lưu bản chụp tại `tools/data/dvhc-2025-07-01.json` để build lặp lại không cần mạng.
 *
 * Khóa tỉnh (`HCM`, `HN`, ...) giữ đúng `config/provinces-vn.php` (là giá trị `billing_state` của WooCommerce);
 * mã chính thức GSO lưu ở `official_code` + mã từng phường/xã (`code`) lưu vào order meta `_drv_ward_code`.
 *
 * Dùng: node tools/build-wards.mjs [đường-dẫn-nguồn.json]
 */
import { mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const THEME_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const SOURCE = resolve(process.argv[2] || `${THEME_DIR}/tools/data/dvhc-2025-07-01.json`);
const PROVINCES_PHP = `${THEME_DIR}/config/provinces-vn.php`;
const OUT_DIR = `${THEME_DIR}/assets/data/wards`;
const SOURCE_LABEL = 'NQ 202/2025/QH15 · Mã TCTK · provinces.open-api.vn/api/v2';
const EFFECTIVE_DATE = '2025-07-01';

const strip = (s) =>
  String(s)
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase()
    .replace(/^(thanh pho|tp\.?|tinh)\s+/u, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

function readProvinceKeys() {
  const php = readFileSync(PROVINCES_PHP, 'utf8');
  const map = new Map();
  for (const m of php.matchAll(/'([A-Z]{2,4})'\s*=>\s*'([^']+)'/g)) {
    map.set(m[1], m[2]);
  }
  if (map.size !== 34) {
    throw new Error(`config/provinces-vn.php phải có đúng 34 tỉnh, đang có ${map.size}`);
  }
  return map;
}

const TYPE_MAP = { 'phường': 'phuong', 'xã': 'xa', 'đặc khu': 'dac-khu' };

function main() {
  const source = JSON.parse(readFileSync(SOURCE, 'utf8'));
  if (!Array.isArray(source) || source.length !== 34) {
    throw new Error(`Nguồn phải có 34 tỉnh/thành, đang có ${Array.isArray(source) ? source.length : 'N/A'}`);
  }

  const keys = readProvinceKeys();
  const byName = new Map(source.map((p) => [strip(p.name), p]));

  mkdirSync(OUT_DIR, { recursive: true });
  for (const f of readdirSync(OUT_DIR)) {
    if (f.endsWith('.json')) {
      rmSync(`${OUT_DIR}/${f}`);
    }
  }

  let totalWards = 0;
  const index = [];
  for (const [key, label] of keys) {
    const prov = byName.get(strip(label));
    if (!prov) {
      throw new Error(`Không khớp được tỉnh "${label}" (${key}) với nguồn dữ liệu`);
    }
    const wards = prov.wards
      .map((w) => ({
        code: String(w.code).padStart(5, '0'),
        name: String(w.name).trim(),
        type: TYPE_MAP[w.division_type] || 'xa',
      }))
      .sort((a, b) => a.name.localeCompare(b.name, 'vi'));

    const codes = new Set(wards.map((w) => w.code));
    if (codes.size !== wards.length) {
      throw new Error(`Trùng mã phường/xã trong ${label}`);
    }

    totalWards += wards.length;
    index.push({ key, official_code: String(prov.code).padStart(2, '0'), count: wards.length });
    const payload = {
      province_code: key,
      official_code: String(prov.code).padStart(2, '0'),
      province_name: label,
      updated_at: EFFECTIVE_DATE,
      source: SOURCE_LABEL,
      wards,
    };
    writeFileSync(`${OUT_DIR}/${key}.json`, JSON.stringify(payload), 'utf8');
  }

  writeFileSync(
    `${OUT_DIR}/index.json`,
    JSON.stringify({ updated_at: EFFECTIVE_DATE, source: SOURCE_LABEL, total_wards: totalWards, provinces: index }),
    'utf8',
  );
  console.log(`Wrote ${keys.size} province files · ${totalWards} wards → assets/data/wards/`);
}

main();
