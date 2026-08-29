import { createRouter, createWebHistory } from 'vue-router';

// ==========================================
// 🛡️ ฝั่งแอดมิน (Admin Components)
// ==========================================
import AdminLogin from '../Pages/Auth/Login.vue'; 
import Dashboard from '../Pages/Admin/Dashboard.vue';
import BoardgameManager from '../Pages/Admin/BoardgameManager.vue';
import UserManager from '../Pages/Admin/UserManager.vue';
import TransactionManager from '../Pages/Admin/TransactionManager.vue';
import RentalManager from '../Pages/Admin/RentalManager.vue';

// ==========================================
// 🚪 ฝั่งลูกค้า (User Components)
// ==========================================
import UserLayout from '../Layouts/UserLayout.vue'; 
import UserHome from '../Pages/User/Home.vue';
import UserMyGames from '../Pages/User/MyGames.vue'; // 🎯 หน้าเกมของฉัน
import UserHistory from '../Pages/User/History.vue'; // 🎯 หน้าประวัติธุรกรรม
import UserProfile from '../Pages/User/Profile.vue'; 
import UserLogin from '../Pages/User/Login.vue';
import UserRegister from '../Pages/User/Register.vue';

const routes = [
    {
        path: '/home',
        redirect: '/'
    },
    
    // ------------------------------------------
    // 📱 เส้นทางฝั่งลูกค้า (มี Layout ครอบเป็นแอปมือถือ)
    // ------------------------------------------
    {
        path: '/',
        component: UserLayout, 
        children: [
            {
                path: '', 
                name: 'user.home',
                component: UserHome
            },
            {
                path: 'mygames', 
                name: 'user.mygames',
                component: UserMyGames
            },
            {
                path: 'history', 
                name: 'user.history',
                component: UserHistory
            },
            {
                path: 'profile', 
                name: 'user.profile',
                component: UserProfile
            }
        ]
    },

    // ------------------------------------------
    // 🔐 เส้นทางเข้าสู่ระบบ/สมัครสมาชิกของลูกค้า (ไม่มี Layout)
    // ------------------------------------------
    {
        path: '/login',
        name: 'user.login',
        component: UserLogin
    },
    {
        path: '/register',
        name: 'user.register',
        component: UserRegister
    },

    // ------------------------------------------
    // 💻 เส้นทางฝั่งแอดมิน (Admin Routes)
    // ------------------------------------------
    {
        path: '/admin/login',
        name: 'admin.login',
        component: AdminLogin
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
        component: UserManager
    },
    {
        path: '/admin/transactions',
        name: 'admin.transactions',
        component: TransactionManager
    },
    {
        path: '/admin/rentals',
        name: 'admin.rentals',
        component: RentalManager
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// ==========================================
// 🛑 Route Guard (ยามเฝ้าประตูระดับสูง)
// ==========================================
router.beforeEach((to, from, next) => {
    const isAdminRoute = to.path.startsWith('/admin');
    const isAdminAuthenticated = localStorage.getItem('admin_token');
    const isUserAuthenticated = localStorage.getItem('user_token');

    // 🛡️ 1. ตรวจสอบการเข้าถึงฝั่งแอดมิน
    if (isAdminRoute) {
        if (to.name !== 'admin.login' && !isAdminAuthenticated) {
            // ถ้าไม่ใช่หน้าล็อกอิน และยังไม่ได้ล็อกอินแอดมิน -> เด้งไปหน้าล็อกอินแอดมิน
            next({ name: 'admin.login' });
        } else if (to.name === 'admin.login' && isAdminAuthenticated) {
            // ถ้าล็อกอินแล้ว จะเข้าหน้าล็อกอินอีกทำไม -> เด้งไป Dashboard เลย
            next({ name: 'admin.dashboard' });
        } else {
            next(); // ผ่านได้
        }
    } 
    // 🚪 2. ตรวจสอบการเข้าถึงฝั่งลูกค้า (User)
    else {
        // รายชื่อหน้าที่ลูกค้าต้องล็อกอินก่อนถึงจะดูได้
        const protectedUserRoutes = ['user.profile', 'user.mygames', 'user.history'];
        
        if (protectedUserRoutes.includes(to.name) && !isUserAuthenticated) {
            // ถ้าจะเข้าหน้าที่มีการป้องกัน แต่ยังไม่มี Token -> เด้งไปหน้าล็อกอิน
            next({ name: 'user.login' });
        } else if ((to.name === 'user.login' || to.name === 'user.register') && isUserAuthenticated) {
            // ถ้าล็อกอินแล้ว จะกลับมาหน้า Login/Register อีก -> เด้งไปหน้า Home เลย
            next({ name: 'user.home' });
        } else {
            next(); // ผ่านได้
        }
    }
});

export default router;
