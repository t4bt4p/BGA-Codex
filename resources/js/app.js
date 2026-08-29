import './bootstrap'; 
import 'bootstrap/dist/css/bootstrap.min.css'; // โหลด CSS ของ Bootstrap
import 'bootstrap'; // โหลด JS ของ Bootstrap

import { createApp } from 'vue';
import App from './App.vue';
import router from './router'; // นำเข้า Router ที่เราเพิ่งสร้าง

createApp(App).use(router).mount('#app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .catch(error => console.warn('PWA service worker registration failed', error));
    });
}
