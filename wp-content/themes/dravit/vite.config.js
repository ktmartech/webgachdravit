import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const entries = {
  global: resolve(__dirname, 'assets/src/css/global.css'),
  'pages/home': resolve(__dirname, 'assets/src/css/pages/home.css'),
  'pages/catalog': resolve(__dirname, 'assets/src/css/pages/catalog.css'),
  'pages/ui-kit': resolve(__dirname, 'assets/src/css/pages/ui-kit.css'),
  'pages/ai-finder': resolve(__dirname, 'assets/src/css/pages/ai-finder.css'),
  core: resolve(__dirname, 'assets/src/js/core.js'),
  home: resolve(__dirname, 'assets/src/js/home.js'),
  catalog: resolve(__dirname, 'assets/src/js/catalog.js'),
  search: resolve(__dirname, 'assets/src/js/search.js'),
  'chunks/motion': resolve(__dirname, 'assets/src/js/chunks/motion.js'),
  'chunks/quote': resolve(__dirname, 'assets/src/js/chunks/quote.js'),
  'chunks/ai-finder': resolve(__dirname, 'assets/src/js/chunks/ai-finder.js'),
};

/**
 * LightningCSS minifier converts CSS hex escapes (e.g. `\e693` in Phosphor icons)
 * into raw 3-byte UTF-8 PUA characters without emitting `@charset "UTF-8";`.
 * If Nginx/Apache serves `text/css` without `charset=utf-8`, browsers decode those
 * bytes as Windows-1252 (`îš“`). Re-escaping non-ASCII code points to 6-digit ASCII
 * CSS escapes + prepending `@charset "UTF-8";` makes every CSS bundle encoding-proof.
 */
function dravitCssSafeUnicode() {
  const escapeNonAscii = (cssText) => {
    let out = '';
    for (const ch of cssText) {
      const cp = ch.codePointAt(0);
      if (cp !== undefined && cp > 0x7f) {
        out += `\\${cp.toString(16).padStart(6, '0')}`;
      } else {
        out += ch;
      }
    }
    return out.startsWith('@charset') ? out : `@charset "UTF-8";${out}`;
  };

  return {
    name: 'dravit-css-safe-unicode',
    generateBundle(_options, bundle) {
      for (const item of Object.values(bundle)) {
        if (item.type !== 'asset' || !item.fileName.endsWith('.css')) {
          continue;
        }
        const raw =
          typeof item.source === 'string'
            ? item.source
            : new TextDecoder('utf-8').decode(item.source);
        item.source = escapeNonAscii(raw);
      }
    },
  };
}

export default defineConfig({
  base: './',
  publicDir: false,
  plugins: [dravitCssSafeUnicode()],
  css: {
    transformer: 'lightningcss',
    lightningcss: {
      targets: {
        chrome: 112 << 16,
        safari: (16 << 16) | (4 << 8),
        firefox: 115 << 16,
      },
    },
  },
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: '.vite/manifest.json',
    cssMinify: 'lightningcss',
    target: 'es2022',
    rollupOptions: {
      input: entries,
      output: {
        entryFileNames: 'js/[name]-[hash].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.names?.[0] ?? assetInfo.name ?? '';
          if (name.endsWith('.css')) {
            return 'css/[name]-[hash][extname]';
          }
          if (name.endsWith('.woff2')) {
            return 'fonts/[name][extname]';
          }
          return 'media/[name]-[hash][extname]';
        },
      },
    },
  },
  test: {
    environment: 'jsdom',
    include: ['tests/js/**/*.test.js'],
    globals: false,
  },
});
