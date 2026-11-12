import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const entries = {
  global: resolve(__dirname, 'assets/src/css/global.css'),
  'pages/ui-kit': resolve(__dirname, 'assets/src/css/pages/ui-kit.css'),
  core: resolve(__dirname, 'assets/src/js/core.js'),
  'chunks/motion': resolve(__dirname, 'assets/src/js/chunks/motion.js'),
  'chunks/quote': resolve(__dirname, 'assets/src/js/chunks/quote.js'),
  'chunks/ai-finder': resolve(__dirname, 'assets/src/js/chunks/ai-finder.js'),
};

export default defineConfig({
  base: './',
  publicDir: false,
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
