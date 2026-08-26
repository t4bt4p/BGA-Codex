<template>
    <div class="d-flex" style="min-height: 100vh; background-color: #f8fafc; font-family: 'Sarabun', 'Roboto', sans-serif;">
        
        <!-- Sidebar -->
        <aside class="bg-white border-end d-flex flex-column shadow-sm flex-shrink-0" style="width: 256px; border-color: #f1f5f9; z-index: 20;">
            
            <!-- Logo -->
            <div class="d-flex align-items-center px-4 border-bottom" style="height: 80px; border-color: #f8fafc !important;">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-success p-2 rounded shadow-sm d-flex align-items-center justify-content-center" style="background-color: #16a34a !important;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Meeple_blue.svg/512px-Meeple_blue.svg.png" alt="Meeple" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                    </div>
                    <span class="fs-5 fw-bold text-dark mt-1" style="letter-spacing: -0.5px;">BGA<span style="color: #16a34a;">Vault</span></span>
                </div>
            </div>

            <!-- Menu -->
            <nav class="flex-grow-1 px-3 py-4 overflow-auto custom-scroll">
                <p class="px-3 fw-bold text-muted text-uppercase mb-2" style="font-size: 10px; letter-spacing: 1px;">Main Menu</p>
                
                <ul class="nav flex-column gap-2 mb-4 bga-menu">
                    <li class="nav-item">
                        <router-link :to="{ name: 'admin.dashboard' }" class="nav-link d-flex align-items-center gap-3 px-3 py-3 rounded-3 fw-semibold text-secondary" active-class="active-menu">
                            <i class="fa-solid fa-chart-pie menu-icon" style="width: 20px; text-align: center;"></i> 
                            แดชบอร์ดสรุปผล
                        </router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'admin.boardgames' }" class="nav-link d-flex align-items-center gap-3 px-3 py-3 rounded-3 fw-semibold text-secondary" active-class="active-menu">
                            <i class="fa-solid fa-chart-pie menu-icon" style="width: 20px; text-align: center;"></i> 
                            จัดการบอร์ดเกม
                        </router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'admin.users' }" class="nav-link d-flex align-items-center gap-3 px-3 py-3 rounded-3 fw-semibold text-secondary" active-class="active-menu">
                            <i class="fa-solid fa-users menu-icon" style="width: 20px; text-align: center;"></i> 
                            จัดการผู้ใช้งาน
                        </router-link>
                    </li>
                    <li class="nav-item">
                        <router-link :to="{ name: 'admin.rentals' }" class="nav-link d-flex align-items-center gap-3 px-3 py-3 rounded-3 fw-semibold text-secondary" active-class="active-menu">
                            <i class="fa-solid fa-boxes-packing menu-icon" style="width: 20px; text-align: center;"></i> 
                            จัดการเช่า-คืน
                        </router-link>
                    </li>
                    
                </ul>
                
                <p class="px-3 fw-bold text-muted text-uppercase mb-2 mt-4" style="font-size: 10px; letter-spacing: 1px;">Reports & Logs</p>
                
                <ul class="nav flex-column bga-menu">
                    <li class="nav-item">
                        <router-link :to="{ name: 'admin.transactions' }" class="nav-link d-flex align-items-center gap-3 px-3 py-3 rounded-3 fw-semibold text-secondary" active-class="active-menu">
                            <i class="fa-solid fa-file-lines menu-icon" style="width: 20px; text-align: center;"></i> 
                            ประวัติธุรกรรม
                        </router-link>
                    </li>
                </ul>
            </nav>

            <!-- Logout -->
            <div class="p-3 border-top" style="border-color: #f8fafc !important;">
                <button @click="handleLogout" class="btn btn-light w-100 d-flex align-items-center gap-3 justify-content-center text-muted fw-semibold rounded-3 py-2 border-0 logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ
                </button>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <main class="flex-grow-1 d-flex flex-column position-relative overflow-hidden">
            
            <!-- Header -->
            <header class="d-flex align-items-center justify-content-between px-4 border-bottom bg-white bg-opacity-75" style="height: 80px; border-color: #f1f5f9 !important; backdrop-filter: blur(8px); z-index: 10;">
                <div class="d-flex align-items-center gap-3">
                    <h1 class="h4 fw-bold text-dark mb-0">
                        <slot name="header">แดชบอร์ดสรุปผล</slot>
                    </h1>
                    <span class="badge text-success border border-success border-opacity-25 d-none d-md-inline-block px-2 py-1" style="background-color: #f0fdf4;">อัปเดตล่าสุด: วันนี้ 10:45 น.</span>
                </div>

                <div class="d-flex align-items-center gap-4">
                    <div class="position-relative">
                        <i class="fa-solid fa-bell fs-5 text-muted notification-icon" style="cursor: pointer;"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                    </div>
                    
                    <div class="vr bg-secondary opacity-25" style="height: 30px;"></div>
                    
                    <div class="d-flex align-items-center gap-3" style="cursor: pointer;">
                        <div class="text-end d-none d-sm-block">
                            <p class="mb-0 fw-bold text-dark" style="font-size: 14px; line-height: 1;">{{ userData ? userData.User_name : '' }}</p>
                            <p class="mb-0 text-muted" style="font-size: 11px;">{{ userData && userData.User_status === 1 ? 'Admin' : 'User' }}</p>
                        </div>
                        <img src="https://i.pravatar.cc/150?img=33" alt="Admin" class="rounded-circle border border-success border-opacity-25 object-fit-cover" style="width: 40px; height: 40px; background-color: #f0fdf4;">
                    </div>
                </div>
            </header>

            <!-- Scrollable Content -->
            <div class="flex-grow-1 p-4 overflow-auto custom-scroll position-relative">
                <slot></slot>
                <!-- Spacing -->
                <div style="height: 50px;"></div> 
            </div>
        </main>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    name: 'AdminLayout',
    data() {
        return {
            userData: null // สร้างตัวแปรมารับข้อมูลแอดมิน
        }
    },
    async mounted() {
        // เมื่อเปิดหน้านี้ขึ้นมา ให้ไปดึงข้อมูลผู้ใช้ทันที
        await this.fetchUserProfile();
    },
    methods: {
        async fetchUserProfile() {
            try {
                // ยิง API ไปดึงข้อมูล (ไม่ต้องแนบ Token เองแล้ว เพราะ Interceptor จัดการให้!)
                const response = await axios.get('/api/user');
                this.userData = response.data;
            } catch (error) {
                console.error('ไม่สามารถดึงข้อมูลผู้ใช้ได้', error);
            }
        },
        async handleLogout() {
            try {
                // ยิง API Logout (ไม่ต้องแนบ Token เองเช่นกัน)
                await axios.post('/api/logout');
            } catch (error) {
                console.error(error);
            } finally {
                localStorage.removeItem('admin_token');
                this.$router.push({ name: 'login' });
            }
        }
    }
}
</script>

<style scoped>
/* การตกแต่ง Sidebar Menu */
.bga-menu .nav-link {
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.bga-menu .nav-link:hover {
    background-color: #f8fafc;
    color: #334155 !important;
}
.bga-menu .active-menu {
    background-color: #f0fdf4 !important; /* bg-green-50 */
    color: #15803d !important; /* text-green-700 */
    border-color: #dcfce7 !important; /* border-green-100 */
}
.bga-menu .active-menu .menu-icon {
    color: #16a34a; /* text-green-600 */
}
.logout-btn:hover {
    background-color: #fef2f2 !important; /* bg-red-50 */
    color: #dc2626 !important; /* text-red-600 */
}
.notification-icon:hover {
    color: #16a34a !important;
}

/* Custom Scrollbar เล็กๆ แบบในต้นฉบับ */
.custom-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>