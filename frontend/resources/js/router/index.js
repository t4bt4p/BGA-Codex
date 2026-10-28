import { createRouter, createWebHistory } from 'vue-router';

// ==========================================
// 🛡️ ฝั่งแอดมิน (Admin Components)
// ==========================================

// ==========================================
// 🚪 ฝั่งลูกค้า (User Components)
// ==========================================

const routes = [
    { path: '/account-suspended', name: 'user.suspended', component: () => import('../Pages/Auth/AccountSuspended.vue') },
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


];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(to => {
    if (to.name === 'user.suspended') return true;
    let suspension;
    try { suspension = JSON.parse(sessionStorage.getItem('account_suspension')); } catch {}
    if (suspension && !suspension.isAdmin && !['user.login', 'user.register'].includes(to.name)) {
        return { name: 'user.suspended' };
    }
    const token = localStorage.getItem('user_token');
    if (['user.profile', 'user.mygames', 'user.history'].includes(to.name) && !token) return { name: 'user.login' };
    if (['user.login', 'user.register'].includes(to.name) && token) return { name: 'user.home' };
});
export default router;
