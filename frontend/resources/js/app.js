import '../css/app.css';
import './bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css'; // โหลด CSS ของ Bootstrap
import '@fortawesome/fontawesome-free/css/all.min.css';
import '../css/user-responsive.css';
import 'bootstrap'; // โหลด JS ของ Bootstrap

import { createApp } from 'vue';
import App from './App.vue';
import router from './router'; // นำเข้า Router ที่เราเพิ่งสร้าง

async function mountApp() {
    if (!window.location.pathname.startsWith('/admin')) {
        try {
            const response = await fetch('/api/ui-access', { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                document.getElementById('app').textContent = response.status === 403
                    ? 'รองรับเฉพาะโทรศัพท์มือถือ' : 'ไม่สามารถเชื่อมต่อระบบได้ กรุณาลองใหม่';
                return;
            }
        } catch {
            document.getElementById('app').textContent = 'ไม่สามารถเชื่อมต่อระบบได้ กรุณาลองใหม่';
            return;
        }
    }
    createApp(App).use(router).mount('#app');
}
mountApp();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .catch(error => console.warn('PWA service worker registration failed', error));
    });
}
