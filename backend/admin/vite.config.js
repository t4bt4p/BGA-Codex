import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig(({ command }) => ({
  base: command === 'build' ? '/admin-ui/' : '/',
  build: { outDir: '../public/admin-ui', emptyOutDir: true },
  plugins: [vue(), tailwindcss()],
  server: { host: '0.0.0.0', port: 5174, proxy: Object.fromEntries(
    ['/api', '/storage', '/broadcasting', '/sanctum'].map(path => [path, {
      target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:8000', changeOrigin: true,
    }])
  ) },
}));
