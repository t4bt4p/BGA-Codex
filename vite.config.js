import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
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
            // Mobile devices on the same Wi-Fi must connect back to the host PC.
            host: '172.20.10.2',
        },
        watch: {
            usePolling: true, // 🌟 พระเอกของเราคือบรรทัดนี้ครับ สั่งให้เช็คไฟล์ตลอดเวลา
            interval: 500,    // (ตัวเลือกเสริม) รอบการเช็คทุกๆ 0.5 วินาที
        },
    },
});
