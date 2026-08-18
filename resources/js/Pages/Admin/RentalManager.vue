<template>
    <AdminLayout>
        <template #header>ระบบจัดการการเช่า-คืน (Rental Management)</template>

        <div class="fade-in-section">
            <!-- แถบค้นหา -->
            <div class="bg-white p-3 rounded-4 shadow-sm border border-light mb-4 d-flex align-items-center">
                <div class="position-relative w-100" style="max-width: 400px;">
                    <input type="text" class="form-control bga-input pe-5" v-model="searchQuery" placeholder="ค้นหาชื่อบอร์ดเกม...">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="right: 15px; top: 50%; transform: translateY(-50%);"></i>
                </div>
            </div>

            <!-- ตารางแสดงบอร์ดเกม -->
            <div class="bg-white rounded-4 shadow-sm border border-light overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-muted fw-bold py-3 px-4" style="width: 80px;">รหัส</th>
                                <th class="text-muted fw-bold py-3">บอร์ดเกม</th>
                                <th class="text-muted fw-bold py-3 text-center">ค่าเช่า</th>
                                <th class="text-muted fw-bold py-3 text-center">สถานะ</th>
                                <!-- 📌 คอลัมน์ใหม่: เวลาทำรายการล่าสุด -->
                                <th class="text-muted fw-bold py-3 text-center">เวลาทำรายการล่าสุด</th>
                                <th class="text-muted fw-bold py-3 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="bg in filteredGames" :key="bg.Bg_id">
                                <td class="px-4 text-muted fw-bold">#{{ String(bg.Bg_id).padStart(3, '0') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <!-- รูปบอร์ดเกม -->
                                        <div class="bg-light rounded-3 overflow-hidden d-flex align-items-center justify-content-center border" style="width: 50px; height: 50px;">
                                            <img v-if="bg.Bg_Image" :src="bg.Bg_Image" alt="Boardgame" class="w-100 h-100 object-fit-cover">
                                            <i v-else class="fa-solid fa-dice text-muted fs-4"></i>
                                        </div>
                                        <div class="fw-bold text-dark">{{ bg.Bg_name }}</div>
                                    </div>
                                </td>
                                <td class="text-center text-success fw-bold">
                                    {{ bg.Bg_cost }} บาท
                                </td>
                                
                                <!-- 📌 สถานะ -->
                                <td class="text-center">
                                    <span v-if="bg.Bg_use_status === 1" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">
                                        ว่างพร้อมเช่า
                                    </span>
                                    <span v-else class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2">
                                        ถูกยืม
                                    </span>
                                </td>

                                <!-- 📌 ข้อมูลเวลา (แยกคอลัมน์แล้ว) -->
                                <td class="text-center text-muted small">
                                    <span v-if="bg.updated_at">
                                        <i class="fa-regular fa-clock me-1"></i> {{ formatThaiDate(bg.updated_at) }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                
                                <!-- ปุ่มจัดการ -->
                                <td class="text-center">
                                    <button 
                                        v-if="bg.Bg_use_status === 1" 
                                        @click="processRent(bg)" 
                                        class="btn btn-sm btn-primary shadow-sm fw-bold px-4 py-1 rounded-2"
                                    >
                                        ทำรายการเช่า
                                    </button>
                                    
                                    <button 
                                        v-else 
                                        @click="processReturn(bg)" 
                                        class="btn btn-sm btn-warning shadow-sm fw-bold px-4 py-1 rounded-2"
                                    >
                                        รับคืนสินค้า
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredGames.length === 0">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fs-2 mb-3 text-light"></i>
                                    <p class="mb-0">ไม่พบบอร์ดเกม</p>
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
    components: { AdminLayout },
    data() {
        return {
            boardgames: [],
            users: [],
            searchQuery: ''
        };
    },
    computed: {
        filteredGames() {
            return this.boardgames.filter(bg => {
                return bg.Bg_name.toLowerCase().includes(this.searchQuery.toLowerCase());
            });
        }
    },
    mounted() {
        this.fetchData();
    },
    methods: {
        // ฟังก์ชันแปลงเวลาเป็นภาษาไทย
        formatThaiDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('th-TH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }) + ' น.';
        },

        async fetchData() {
            try {
                const [bgRes, userRes] = await Promise.all([
                    window.axios.get('/api/boardgames'),
                    window.axios.get('/api/users')
                ]);
                this.boardgames = bgRes.data;
                this.users = userRes.data.filter(u => u.User_status === 1);
            } catch (error) {
                console.error('โหลดข้อมูลผิดพลาด', error);
            }
        },

        async processRent(bg) {
            const userOptions = this.users.map(u => 
                `<option value="${u.User_id}">${u.User_name} (กระเป๋า: ${u.wallet ? u.wallet.Wallet_count : 0} บาท)</option>`
            ).join('');

            const { value: selectedUserId } = await window.Swal.fire({
                title: 'ทำรายการเช่าบอร์ดเกม',
                html: `
                    <div class="text-start mb-3 p-3 bg-light rounded-3">
                        <div>เกม: <strong>${bg.Bg_name}</strong></div>
                        <div class="text-danger mt-1">ค่าเช่าที่จะหัก: <strong>${bg.Bg_cost} บาท</strong></div>
                    </div>
                    <div class="text-start">
                        <label class="form-label fw-bold small text-muted">เลือกลูกค้าที่ทำรายการ</label>
                        <select id="swal-user-select" class="form-select bga-select">
                            <option value="">-- กรุณาเลือกลูกค้า --</option>
                            ${userOptions}
                        </select>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'ยืนยันการเช่า',
                cancelButtonText: 'ยกเลิก',
                preConfirm: () => {
                    const userId = document.getElementById('swal-user-select').value;
                    if (!userId) {
                        window.Swal.showValidationMessage('กรุณาเลือกลูกค้า');
                        return false;
                    }
                    return userId;
                }
            });

            if (selectedUserId) {
                try {
                    await window.axios.post('/api/rentals/rent', {
                        User_id: selectedUserId,
                        Bg_id: bg.Bg_id
                    });
                    
                    window.Swal.fire({ icon: 'success', title: 'เช่าสำเร็จ!', timer: 1500, showConfirmButton: false });
                    this.fetchData(); // ดึงข้อมูลใหม่
                } catch (error) {
                    window.Swal.fire({ icon: 'error', title: 'เช่าไม่สำเร็จ', text: error.response?.data?.message || 'เกิดข้อผิดพลาด' });
                }
            }
        },

        async processReturn(bg) {
            const userOptions = this.users.map(u => `<option value="${u.User_id}">${u.User_name}</option>`).join('');

            const { value: returnData } = await window.Swal.fire({
                title: 'รับคืนบอร์ดเกม',
                html: `
                    <div class="text-start mb-3 p-3 bg-light rounded-3">
                        <div>เกมที่รับคืน: <strong>${bg.Bg_name}</strong></div>
                    </div>
                    <div class="text-start mb-3">
                        <label class="form-label fw-bold small text-muted">เลือกลูกค้าที่นำมาคืน</label>
                        <select id="swal-return-user" class="form-select bga-select">
                            <option value="">-- กรุณาเลือกลูกค้า --</option>
                            ${userOptions}
                        </select>
                    </div>
                    <div class="text-start">
                        <label class="form-label fw-bold small text-muted">ค่าปรับ (บาท) *ใส่ 0 ถ้าไม่มี</label>
                        <input type="number" id="swal-late-fee" class="form-control bga-input" value="0" min="0">
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'ยืนยันรับคืน',
                cancelButtonText: 'ยกเลิก',
                preConfirm: () => {
                    const userId = document.getElementById('swal-return-user').value;
                    const lateFee = document.getElementById('swal-late-fee').value;
                    
                    if (!userId) {
                        window.Swal.showValidationMessage('กรุณาเลือกลูกค้า');
                        return false;
                    }
                    return { User_id: userId, late_fee: lateFee };
                }
            });

            if (returnData) {
                try {
                    await window.axios.post('/api/rentals/return', {
                        User_id: returnData.User_id,
                        Bg_id: bg.Bg_id,
                        late_fee: returnData.late_fee
                    });
                    
                    window.Swal.fire({ icon: 'success', title: 'รับคืนสำเร็จ!', timer: 1500, showConfirmButton: false });
                    this.fetchData(); // ดึงข้อมูลใหม่
                } catch (error) {
                    window.Swal.fire({ icon: 'error', title: 'ทำรายการไม่สำเร็จ', text: error.response?.data?.message || 'เกิดข้อผิดพลาด' });
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