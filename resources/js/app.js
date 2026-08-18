import './bootstrap'; 
import 'bootstrap/dist/css/bootstrap.min.css'; // โหลด CSS ของ Bootstrap
import 'bootstrap'; // โหลด JS ของ Bootstrap

import { createApp } from 'vue';
import App from './App.vue';
import router from './router'; // นำเข้า Router ที่เราเพิ่งสร้าง

createApp(App).use(router).mount('#app');