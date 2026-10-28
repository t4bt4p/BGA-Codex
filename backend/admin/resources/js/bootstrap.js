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
import './echo';
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
    response => {
        if (Number(response.headers?.['x-transactions-restored']) > 0) {
            Swal.fire({ icon: 'error', title: 'พบข้อผิดพลาด',
                text: 'ระบบกู้ประวัติธุรกรรมจากหลักฐานที่ตรวจสอบแล้ว ข้อมูลที่แสดงเป็นข้อมูลหลังการกู้คืน',
                confirmButtonText: 'รับทราบ' });
        }
        return response;
    },
    error => {
        if (['wallet_balance_corrected', 'transaction_data_restored'].includes(error.response?.data?.code)) {
            error.walletCorrectionHandled = true;
            window.dispatchEvent(new CustomEvent('wallet-corrected'));
            error.walletCorrectionAcknowledged = new Promise(resolve => window.setTimeout(() => Swal.fire({
                icon: 'error',
                title: 'พบข้อผิดพลาด',
                text: error.response.data.message + (error.response.data.code === 'wallet_balance_corrected'
                    ? ` ยอดที่ถูกต้อง ${Number(error.response.data.balance).toLocaleString()} โทเคน` : ''),
                confirmButtonText: 'รับทราบ',
            }).then(resolve), 0));
        }
        if (error.response?.data?.code === 'account_suspended' && !window.accountSuspensionShown) {
            window.accountSuspensionShown = true;
            const isAdmin = window.location.pathname.startsWith('/admin');
            localStorage.removeItem(isAdmin ? 'admin_token' : 'user_token');
            const reason = error.response.data.reason === 'overdue' ? 'overdue' : 'disabled';
            sessionStorage.setItem('account_suspension', JSON.stringify({ reason, isAdmin }));
            Swal.close();
            window.location.replace(isAdmin ? '/admin/account-suspended' : '/account-suspended');
        }
        if (error.response && error.response.status === 401 && !window.accountSuspensionShown) {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('user_token');
            if (window.location.pathname !== '/admin/login') {
                window.location.href = '/admin/login'; // บังคับรีไดเรกต์ไปหน้าล็อกอิน
            }
        }
        return Promise.reject(error);
    }
);
