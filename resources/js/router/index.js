import { createRouter, createWebHistory } from 'vue-router';
import Dashboard from '../Pages/Admin/Dashboard.vue';
import BoardgameManager from '../Pages/Admin/BoardgameManager.vue';
import UserManager from '../Pages/Admin/UserManager.vue';
import TransactionManager from '../Pages/Admin/TransactionManager.vue';
import Login from '../Pages/Auth/Login.vue';
import UserManage from '../Pages/Admin/UserManager.vue';
import RentalManager from '../Pages/Admin/RentalManager.vue';

const routes = [
    {
        path: '/login',      // แก้จาก /admin/login เป็น /login
        name: 'login',       // แก้ชื่อให้กระชับขึ้น
        component: Login
    },
    {
        path: '/admin',
        name: 'admin.dashboard',
        component: Dashboard
    },
    {
        path: '/admin/boardgames',
        name: 'admin.boardgames',
        component: BoardgameManager
    },
    {
        path: '/admin/users',
        name: 'admin.users',
        component: UserManager,
        meta: { requiresAuth: true }
    },
    {
        path: '/admin/transactions',
        name: 'admin.transactions',
        component: TransactionManager
    },
    {
        path: '/admin/rentals',
        name: 'admin.rentals',
        component: RentalManager,
        meta: { requiresAuth: true }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// เพิ่ม Route Guard (ยามเฝ้าประตู) ตรงนี้ครับ
router.beforeEach((to, from, next) => {
    // 1. เช็คว่าหน้าที่ผู้ใช้กำลังจะไป เป็นหน้าของแอดมิน (ขึ้นต้นด้วย /admin) หรือไม่
    if (to.path.startsWith('/admin')) {
        
        // 2. ตรวจสอบว่ามี Token ล็อกอินอยู่ในเครื่องหรือยัง (จำลอง)
        const isAuthenticated = localStorage.getItem('admin_token');

        if (!isAuthenticated) {
            // ถ้าไม่มี Token (ยังไม่ล็อกอิน) ให้เตะกลับไปหน้า login ทันที
            next({ name: 'login' });
        } else {
            // ถ้ามี Token แล้ว ปล่อยให้เข้าหน้าแอดมินได้เลย
            next();
        }
    } else {
        // ถ้าเป็นหน้าอื่นๆ ทั่วไป (เช่น หน้า login) ปล่อยผ่านได้เลย
        next();
    }
});

export default router;