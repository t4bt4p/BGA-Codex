// นำเข้า Axios (ปกติน่าจะมีอยู่แล้วในไฟล์นี้)
import axios from 'axios';
window.axios = axios;

// นำเข้า Bootstrap JS
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// นำเข้า SweetAlert2
import Swal from 'sweetalert2';
window.Swal = Swal;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// ดึง Token จาก localStorage
const token = localStorage.getItem('admin_token');

// 1. ก่อนที่ Axios จะยิง API ทุกครั้ง ให้มันหยิบ Token แปะไปด้วยอัตโนมัติ
axios.interceptors.request.use(config => {
    // หาดูว่ามี token ของใครล็อกอินอยู่บ้าง
    const token = window.location.pathname.startsWith('/admin')
        ? localStorage.getItem('admin_token')
        : localStorage.getItem('user_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// 2. ถ้า API ตอบกลับมาว่า 401 (Token หมดอายุ หรือไม่มีสิทธิ์) ให้เตะกลับหน้า Login ทันที
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('user_token');
            if (window.location.pathname !== '/login') {
                window.location.href = '/login'; // บังคับรีไดเรกต์ไปหน้าล็อกอิน
            }
        }
        return Promise.reject(error);
    }
);
