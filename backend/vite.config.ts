import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import symfonyPlugin from 'vite-plugin-symfony';
import {fileURLToPath, URL} from 'node:url';

export default defineConfig({
  plugins: [react(), tailwindcss(), symfonyPlugin()],
  resolve: {
    alias: {'@': fileURLToPath(new URL('./assets/react', import.meta.url))},
  },
  build: {
    outDir: 'public/build',
    manifest: true,
    sourcemap: true,
    rollupOptions: {
      input: {app: './assets/react/app/main.tsx'},
    },
  },
});
