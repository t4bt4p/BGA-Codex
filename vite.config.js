import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // --- เพิ่ม Block server ด้านล่างนี้เข้าไป ---
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true, // 🌟 พระเอกของเราคือบรรทัดนี้ครับ สั่งให้เช็คไฟล์ตลอดเวลา
            interval: 500,    // (ตัวเลือกเสริม) รอบการเช็คทุกๆ 0.5 วินาที
        },
    },
});