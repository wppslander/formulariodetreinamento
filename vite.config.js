import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig(({ command }) => ({
  root: '.', // Set root to project root
  base: command === 'serve' ? '/' : '/assets/',
  build: {
    outDir: 'public/assets', // Adjusted relative to new root
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/js/main.js'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173'
  }
}));
