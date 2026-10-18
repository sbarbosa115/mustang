import {defineConfig, mergeConfig} from 'vitest/config';
import viteConfig from './vite.config.ts';

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      globals: true,
      setupFiles: ['./assets/react/shared/test/setup.ts'],
      include: ['assets/react/**/*.test.{ts,tsx}'],
      css: false,
    },
  }),
);
