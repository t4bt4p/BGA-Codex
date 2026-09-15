import { createRouter, createWebHistory } from 'vue-router';

// ==========================================
// 🛡️ ฝั่งแอดมิน (Admin Components)
// ==========================================

// ==========================================
// 🚪 ฝั่งลูกค้า (User Components)
// ==========================================

const routes = [
    { path: '/account-suspended', name: 'user.suspended', component: () => import('../Pages/Auth/AccountSuspended.vue') },
    { path: '/admin/account-suspended', name: 'admin.suspended', component: () => import('../Pages/Auth/AccountSuspended.vue') },
    {
        path: '/home',
        redirect: '/'
    },
    
    // ------------------------------------------
    // 📱 เส้นทางฝั่งลูกค้า (มี Layout ครอบเป็นแอปมือถือ)
    // ------------------------------------------
    {
        path: '/',
        component: () => import('../Layouts/UserLayout.vue'),
        children: [
            {
                path: '', 
                name: 'user.home',
                component: () => import('../Pages/User/Home.vue')
            },
            {
                path: 'mygames', 
                name: 'user.mygames',
                component: () => import('../Pages/User/MyGames.vue')
            },
            {
                path: 'history', 
                name: 'user.history',
                component: () => import('../Pages/User/History.vue')
            },
            {
                path: 'profile', 
                name: 'user.profile',
                component: () => import('../Pages/User/Profile.vue')
            }
        ]
    },

    // ------------------------------------------
    // 🔐 เส้นทางเข้าสู่ระบบ/สมัครสมาชิกของลูกค้า (ไม่มี Layout)
    // ------------------------------------------
    {
        path: '/login',
        name: 'user.login',
        component: () => import('../Pages/User/Login.vue')
    },
    {
        path: '/register',
        name: 'user.register',
        component: () => import('../Pages/User/Register.vue')
    },

    // ------------------------------------------
    // 💻 เส้นทางฝั่งแอดมิน (Admin Routes)
    // ------------------------------------------
    {
        path: '/admin/login',
        name: 'admin.login',
        component: () => import('../Pages/Auth/Login.vue')
    },
    {
        path: '/admin',
        name: 'admin.dashboard',
        component: () => import('../Pages/Admin/Dashboard.vue')
    },
    {
        path: '/admin/boardgames',
        name: 'admin.boardgames',
        component: () => import('../Pages/Admin/BoardgameManager.vue')
    },
    {
        path: '/admin/users',
        name: 'admin.users',
        component: () => import('../Pages/Admin/UserManager.vue')
    },
    {
        path: '/admin/transactions',
        name: 'admin.transactions',
        component: () => import('../Pages/Admin/TransactionManager.vue')
    },
    {
        path: '/admin/rentals',
        name: 'admin.rentals',
        component: () => import('../Pages/Admin/RentalManager.vue')
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// ==========================================
// 🛑 Route Guard (ยามเฝ้าประตูระดับสูง)
// ==========================================
router.beforeEach((to) => {
    const isAdminRoute = to.path.startsWith('/admin');
    const isAdminAuthenticated = localStorage.getItem('admin_token');
    const isUserAuthenticated = localStorage.getItem('user_token');
    if (to.name === 'user.suspended' || to.name === 'admin.suspended') return true;
    let suspension = null;
    try { suspension = JSON.parse(sessionStorage.getItem('account_suspension')); } catch { /* Ignore invalid storage. */ }
    if (suspension && suspension.isAdmin === isAdminRoute
        && !['user.login', 'user.register', 'admin.login'].includes(to.name)) {
        return { name: isAdminRoute ? 'admin.suspended' : 'user.suspended' };
    }

    // 🛡️ 1. ตรวจสอบการเข้าถึงฝั่งแอดมิน
    if (isAdminRoute) {
        if (to.name !== 'admin.login' && !isAdminAuthenticated) {
            // ถ้าไม่ใช่หน้าล็อกอิน และยังไม่ได้ล็อกอินแอดมิน -> เด้งไปหน้าล็อกอินแอดมิน
            return { name: 'admin.login' };
        } else if (to.name === 'admin.login' && isAdminAuthenticated) {
            // ถ้าล็อกอินแล้ว จะเข้าหน้าล็อกอินอีกทำไม -> เด้งไป Dashboard เลย
            return { name: 'admin.dashboard' };
        } else {
            return true;
        }
    } 
    // 🚪 2. ตรวจสอบการเข้าถึงฝั่งลูกค้า (User)
    else {
        // รายชื่อหน้าที่ลูกค้าต้องล็อกอินก่อนถึงจะดูได้
        const protectedUserRoutes = ['user.profile', 'user.mygames', 'user.history'];
        
        if (protectedUserRoutes.includes(to.name) && !isUserAuthenticated) {
            // ถ้าจะเข้าหน้าที่มีการป้องกัน แต่ยังไม่มี Token -> เด้งไปหน้าล็อกอิน
            return { name: 'user.login' };
        } else if ((to.name === 'user.login' || to.name === 'user.register') && isUserAuthenticated) {
            // ถ้าล็อกอินแล้ว จะกลับมาหน้า Login/Register อีก -> เด้งไปหน้า Home เลย
            return { name: 'user.home' };
        } else {
            return true;
        }
    }
});

export default router;
