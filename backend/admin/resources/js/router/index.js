import { createRouter, createWebHistory } from 'vue-router';
const router = createRouter({history: createWebHistory(), routes: [
{path: '/', redirect: '/admin'},
{path: '/admin/account-suspended', name: 'admin.suspended', component: () => import('../Pages/Auth/AccountSuspended.vue')},
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
]});
router.beforeEach(to => {
    if (to.name === 'admin.suspended') return true;
    let suspension;
    try { suspension = JSON.parse(sessionStorage.getItem('account_suspension')); } catch {}
    if (suspension?.isAdmin && to.name !== 'admin.login') return {name: 'admin.suspended'};
    const token = localStorage.getItem('admin_token');
    if (!token && to.name !== 'admin.login') return {name: 'admin.login'};
    if (token && to.name === 'admin.login') return {name: 'admin.dashboard'};
});
export default router;
