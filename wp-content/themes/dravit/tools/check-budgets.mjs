/**
 * check-budgets.mjs — Kiểm tra kích thước gzip mỗi Vite entry so với ngân sách.
 *
 * Chạy:  node tools/check-budgets.mjs
 * Thoát: exit code 1 nếu bất kỳ entry FAIL.
 */

import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

const __dirname = dirname(fileURLToPath(import.meta.url));
const THEME_DIR = resolve(__dirname, '..');
const DIST_DIR = resolve(THEME_DIR, 'assets/dist');
const MANIFEST_PATH = resolve(DIST_DIR, '.vite/manifest.json');

/** Ngân sách gzip bytes theo entry key (basename không phần mở rộng). */
const BUDGETS = {
  'core':    8_192,   // 8 KB
  'blog':    10_240,  // 10 KB
  'home':    12_288,  // 12 KB
  'catalog': 15_360,  // 15 KB
  'product': 18_432,  // 18 KB
};

/** Mọi file critical-*.css ≤ 14 KB gzip. */
const CRITICAL_CSS_BUDGET = 14_336;

// ── helpers ──────────────────────────────────────────────────────────────────

function gzipSize(filePath) {
  const raw = readFileSync(filePath);
  return { raw: raw.length, gzip: gzipSync(raw, { level: 9 }).length };
}

function fmt(bytes) {
  return (bytes / 1024).toFixed(2);
}

function pad(str, len) {
  return str.length >= len ? str : str + ' '.repeat(len - str.length);
}

// ── main ─────────────────────────────────────────────────────────────────────

if (!existsSync(MANIFEST_PATH)) {
  console.error(`\n❌ Manifest not found: ${MANIFEST_PATH}`);
  console.error('   Run "npm run build" first.\n');
  process.exit(1);
}

const manifest = JSON.parse(readFileSync(MANIFEST_PATH, 'utf-8'));

const rows = [];
let failed = false;

// 1) Kiểm tra JS entries theo BUDGETS
for (const [key, budget] of Object.entries(BUDGETS)) {
  // Tìm entry trong manifest — dạng "assets/src/js/{key}.js"
  const entryKey = Object.keys(manifest).find((k) => {
    const base = k.replace(/^assets\/src\/js\//, '').replace(/\.js$/, '');
    return base === key;
  });

  if (!entryKey) {
    rows.push({ file: `${key}.js`, raw: '-', gzip: '-', budget: fmt(budget), status: 'SKIP (not in manifest)' });
    continue;
  }

  const outputFile = manifest[entryKey]?.file;
  if (!outputFile) {
    rows.push({ file: `${key}.js`, raw: '-', gzip: '-', budget: fmt(budget), status: 'SKIP (no file)' });
    continue;
  }

  const absPath = resolve(DIST_DIR, outputFile);
  if (!existsSync(absPath)) {
    rows.push({ file: `${key}.js`, raw: '-', gzip: '-', budget: fmt(budget), status: 'SKIP (missing)' });
    continue;
  }

  const { raw, gzip } = gzipSize(absPath);
  const pass = gzip <= budget;
  if (!pass) failed = true;

  rows.push({
    file: outputFile,
    raw: fmt(raw),
    gzip: fmt(gzip),
    budget: fmt(budget),
    status: pass ? '✅ PASS' : '❌ FAIL',
  });
}

// 2) Critical CSS: PHẢI nằm trong dist (đã gộp @import qua Vite) và ≤ 14 KB gzip. File nguồn chỉ chứa @import nên không đo.
const CRITICAL_KEYS = ['home', 'catalog', 'product', 'blog', 'commerce', 'page'];
const seenCritical = new Set();

for (const key of CRITICAL_KEYS) {
  const record = manifest[`assets/src/css/critical/critical-${key}.css`];
  const outputFile = record?.file;
  const absPath = outputFile ? resolve(DIST_DIR, outputFile) : '';

  if (!outputFile || !existsSync(absPath)) {
    failed = true;
    rows.push({ file: `critical-${key} (thiếu trong dist)`, raw: '-', gzip: '-', budget: fmt(CRITICAL_CSS_BUDGET), status: '❌ FAIL' });
    continue;
  }

  const text = readFileSync(absPath, 'utf-8');
  const issues = [];
  if (/@import/.test(text)) issues.push('còn @import');
  if (text.length < 2_000) issues.push('quá nhỏ, nghi thiếu nội dung');

  const { raw, gzip } = gzipSize(absPath);
  const pass = gzip <= CRITICAL_CSS_BUDGET && issues.length === 0;
  if (!pass) failed = true;

  if (seenCritical.has(outputFile)) continue;
  seenCritical.add(outputFile);

  rows.push({
    file: outputFile + (issues.length ? ` (${issues.join(', ')})` : ''),
    raw: fmt(raw),
    gzip: fmt(gzip),
    budget: fmt(CRITICAL_CSS_BUDGET),
    status: pass ? '✅ PASS' : '❌ FAIL',
  });
}

// ── output ───────────────────────────────────────────────────────────────────

console.log('\n📊 Bundle Budget Check\n');
console.log(
  `${pad('File', 48)} | ${pad('Raw KB', 10)} | ${pad('Gzip KB', 10)} | ${pad('Budget KB', 10)} | Status`
);
console.log('-'.repeat(100));

for (const r of rows) {
  console.log(
    `${pad(r.file, 48)} | ${pad(r.raw, 10)} | ${pad(r.gzip, 10)} | ${pad(r.budget, 10)} | ${r.status}`
  );
}

console.log('');

if (failed) {
  console.error('❌ Budget check FAILED — one or more entries exceed the limit.\n');
  process.exit(1);
} else {
  console.log('✅ All entries within budget.\n');
}
