<template>
    <AdminLayout>
        <template #header>จัดการผู้ใช้งาน (User Management)</template>

        <div class="fade-in-section">
            <!-- แถบค้นหา -->
            <div class="bg-white p-3 rounded-4 shadow-sm border border-light mb-4 d-flex align-items-center">
                <div class="position-relative w-100" style="max-width: 400px;">
                    <input type="text" class="form-control bga-input pe-5" v-model="searchQuery" placeholder="ค้นหาชื่อ, ชื่อผู้ใช้, หรือเบอร์โทรศัพท์...">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="right: 15px; top: 50%; transform: translateY(-50%);"></i>
                </div>
            </div>

            <!-- ตารางแสดงข้อมูลผู้ใช้งาน -->
            <div class="bg-white rounded-4 shadow-sm border border-light overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-muted fw-bold py-3 px-4">รหัส</th>
                                <th class="text-muted fw-bold py-3">ข้อมูลผู้ใช้งาน</th>
                                <th class="text-muted fw-bold py-3">เบอร์โทรศัพท์</th>
                                <th class="text-muted fw-bold py-3">กระเป๋าเงิน (Wallet)</th>
                                <th class="text-muted fw-bold py-3 text-center">สถานะ</th>
                                <th class="text-muted fw-bold py-3 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in filteredUsers" :key="user.User_id">
                                <td class="px-4 text-muted">#{{ String(user.User_id).padStart(3, '0') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                            {{ user.User_name ? user.User_name.charAt(0).toUpperCase() : 'U' }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ user.User_name }}</div>
                                            <div class="text-muted small">@{{ user.User_username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ user.User_phone || '-' }}</td>
                                
                                <!-- 📌 คอลัมน์ กระเป๋าเงิน: แสดง ID และ ยอดเงิน (ใช้ Wallet_count) -->
                                <td>
                                    <div v-if="user.wallet">
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1 mb-1 d-inline-block">
                                            <i class="fa-solid fa-wallet me-1"></i> ID: {{ user.wallet.Wallet_id }}
                                        </span>
                                        <div class="text-success fw-bold small">
                                            <i class="fa-solid fa-coins me-1"></i> {{ user.wallet.Wallet_count || 0 }} บาท
                                        </div>
                                    </div>
                                    <span v-else class="text-muted small">ไม่มีกระเป๋าเงิน</span>
                                </td>
                                
                                <td class="text-center">
                                    <span v-if="user.User_status === 1" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        ปกติ
                                    </span>
                                    <span v-else class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        ถูกระงับ
                                    </span>
                                </td>
                                
                                <!-- 📌 คอลัมน์ จัดการ: ปุ่มระงับบัญชี และ จัดการเงิน -->
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- ปุ่มระงับ/ปลดระงับบัญชี -->
                                        <button 
                                            @click="toggleStatus(user)" 
                                            :class="['btn btn-sm shadow-sm fw-bold', user.User_status === 1 ? 'btn-outline-danger' : 'btn-outline-success']"
                                        >
                                            {{ user.User_status === 1 ? 'ระงับบัญชี' : 'ปลดระงับ' }}
                                        </button>
                                        
                                        <!-- ปุ่มจัดการเงิน (โชว์เฉพาะคนที่มี Wallet) -->
                                        <button v-if="user.wallet" @click="manageWallet(user)" class="btn btn-sm btn-outline-primary shadow-sm fw-bold">
                                            <i class="fa-solid fa-money-bill-transfer"></i> จัดการเงิน
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-users-slash fs-2 mb-3 text-light"></i>
                                    <p class="mb-0">ไม่พบข้อมูลผู้ใช้งาน</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script>
import AdminLayout from '../../Layouts/AdminLayout.vue';

export default {
    components: {
        AdminLayout
    },
    data() {
        return {
            users: [],
            searchQuery: ''
        };
    },
    computed: {
        filteredUsers() {
            return this.users.filter(user => {
                const search = this.searchQuery.toLowerCase();
                const matchName = (user.User_name || '').toLowerCase().includes(search);
                const matchUsername = (user.User_username || '').toLowerCase().includes(search);
                const matchPhone = (user.User_phone || '').toLowerCase().includes(search);
                
                return matchName || matchUsername || matchPhone;
            });
        }
    },
    mounted() {
        this.fetchUsers();
    },
    methods: {
        async fetchUsers() {
            try {
                const response = await window.axios.get('/api/users');
                this.users = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดข้อมูลผู้ใช้งานได้', error);
                window.Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถดึงข้อมูลผู้ใช้งานได้'
                });
            }
        },

        async toggleStatus(user) {
            const actionText = user.User_status === 1 ? 'ระงับการใช้งาน' : 'ปลดระงับการใช้งาน';
            const newStatus = user.User_status === 1 ? 0 : 1;
            const confirmColor = user.User_status === 1 ? '#dc3545' : '#16a34a';

            const result = await window.Swal.fire({
                title: `ยืนยันการ${actionText}?`,
                text: `คุณต้องการ${actionText}บัญชีของ ${user.User_name} ใช่หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `ใช่, ${actionText}!`,
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            });

            if (result.isConfirmed) {
                try {
                    await window.axios.put(`/api/users/${user.User_id}/status`, {
                        User_status: newStatus
                    });
                    
                    window.Swal.fire({
                        icon: 'success',
                        title: 'อัปเดตสำเร็จ!',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    await this.fetchUsers(); 
                } catch (error) {
                    console.error('อัปเดตสถานะไม่สำเร็จ', error);
                    window.Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด!',
                        text: 'ไม่สามารถอัปเดตสถานะผู้ใช้งานได้'
                    });
                }
            }
        },

        // 📌 ฟังก์ชันจัดการกระเป๋าเงินด้วย Pop-up แบบ Custom HTML
        async manageWallet(user) {
            // 📌 เปลี่ยนจาก Wallet_balance เป็น Wallet_count
            const currentBalance = user.wallet.Wallet_count || 0;

            const { value: formValues } = await window.Swal.fire({
                title: 'จัดการกระเป๋าเงิน',
                html: `
                    <div class="text-start mb-3 p-3 bg-light rounded-3">
                        <div class="small text-muted mb-1">ผู้ใช้งาน: <strong>${user.User_name}</strong></div>
                        <div class="text-dark">ยอดเงินปัจจุบัน: <strong class="text-success fs-5">${currentBalance}</strong> บาท</div>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold small text-muted">เลือกประเภทรายการ</label>
                        <select id="swal-action" class="form-select bga-select">
                            <option value="add">🟢 เติมเงินเข้ากระเป๋า (+)</option>
                            <option value="deduct">🔴 หักเงินออกจากกระเป๋า (-)</option>
                        </select>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold small text-muted">จำนวนเงิน (บาท)</label>
                        <input id="swal-amount" type="number" class="form-control bga-input" placeholder="ระบุตัวเลข..." min="1">
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยืนยันทำรายการ',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                preConfirm: () => {
                    const action = document.getElementById('swal-action').value;
                    const amount = document.getElementById('swal-amount').value;
                    
                    if (!amount || amount <= 0) {
                        window.Swal.showValidationMessage('กรุณาระบุจำนวนเงินให้ถูกต้อง');
                        return false;
                    }
                    if (action === 'deduct' && amount > currentBalance) {
                        window.Swal.showValidationMessage('ยอดเงินคงเหลือไม่พอให้หัก');
                        return false;
                    }
                    return { action, amount };
                }
            });

            // ถ้าแอดมินกดยืนยัน และกรอกข้อมูลผ่านเงื่อนไข
            if (formValues) {
                try {
                    await window.axios.put(`/api/users/${user.User_id}/wallet`, formValues);
                    
                    window.Swal.fire({
                        icon: 'success',
                        title: 'อัปเดตยอดเงินสำเร็จ!',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    await this.fetchUsers(); // รีเฟรชตารางเพื่อโชว์ยอดเงินล่าสุด
                } catch (error) {
                    console.error('ไม่สามารถอัปเดตเงินได้', error);
                    window.Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: error.response?.data?.message || 'ไม่สามารถทำรายการได้'
                    });
                }
            }
        }
    }
}
</script>

<style scoped>
.bga-input, .bga-select {
    border-radius: 0.75rem;
    padding-top: 0.6rem;
    padding-bottom: 0.6rem;
    border-color: #e2e8f0;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.bga-input:focus, .bga-select:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 0.25rem rgba(22, 163, 74, 0.25);
}
.fade-in-section {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>