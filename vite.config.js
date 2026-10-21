import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    // An IIFE build would otherwise inject the CSS from the script; @imageResizerStyles links it as a file.
    cssCodeSplit: false,
    rollupOptions: {
      input: {
        'image-resizer': 'resources/js/image-resizer.js',
      },
      output: {
        // The script is loaded as a classic <script>, where top-level const/let of an ES bundle land in the page's
        // global scope: its minified names (`p`, `m`, …) then collide with any other classic script that declares the
        // same ("Identifier 'p' has already been declared" broke filament-breezy.js). An IIFE keeps them private.
        format: 'iife',
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/image-resizer[extname]';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
}); 