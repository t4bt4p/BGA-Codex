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
                                <th class="text-muted fw-bold py-3 text-center">ราคารวม</th>
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
                                    {{ rentalTotal(bg).toLocaleString('th-TH') }}
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
            rentals: [],
            searchQuery: ''
        };
    },
    computed: {
        filteredGames() {
            return this.boardgames.filter(bg => {
                return bg.Bg_name.toLowerCase().includes(this.searchQuery.toLowerCase());
            });
        },
    },
    mounted() {
        this.fetchData();
        window.Echo.channel('boardgames')
            .listen('.boardgame.status.changed', this.applyRealtimeStatus);
    },
    beforeUnmount() {
        window.Echo.leave('boardgames');
    },
    methods: {
        rentalTotal(bg) {
            const activeRental = this.rentals.find(rental =>
                Number(rental.Bg_id) === Number(bg.Bg_id) && rental.Rental_status === 'active'
            );
            return Number(activeRental?.Rental_cost ?? bg.Bg_cost ?? 0);
        },
        applyRealtimeStatus(event) {
            const game = this.boardgames.find(item => Number(item.Bg_id) === Number(event.Bg_id));
            if (!game) {
                this.fetchData();
                return;
            }
            game.Bg_use_status = Number(event.Bg_use_status);
            game.updated_at = event.updated_at;
            this.fetchData();
        },
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
                const [bgRes, userRes, rentalRes] = await Promise.all([
                    window.axios.get('/api/boardgames'),
                    window.axios.get('/api/users'),
                    window.axios.get('/api/rentals')
                ]);
                this.boardgames = bgRes.data;
                this.users = userRes.data.filter(u => u.User_status === 1);
                this.rentals = rentalRes.data;
            } catch (error) {
                console.error('โหลดข้อมูลผิดพลาด', error);
            }
        },

        async processRent(bg) {
            const userOptions = this.users.map(u => 
                `<option value="${Number(u.User_id)}">${this.escapeHtml(u.User_name)} (กระเป๋า: ${Number(u.wallet?.Wallet_count || 0)} บาท)</option>`
            ).join('');

            const { value: selectedUserId } = await window.Swal.fire({
                title: 'ทำรายการเช่าบอร์ดเกม',
                html: `
                    <div class="text-start mb-3 p-3 bg-light rounded-3">
                        <div>เกม: <strong>${this.escapeHtml(bg.Bg_name)}</strong></div>
                        <div class="text-danger mt-1">ค่าเช่าที่จะหัก: <strong>${Number(bg.Bg_cost)} บาท</strong></div>
                    </div>
                    <div class="text-start">
                        <label class="form-label fw-bold small text-muted">เลือกลูกค้าที่ทำรายการ</label>
                        <select id="swal-user-select" class="form-select bga-select">
                            <option value="">-- กรุณาเลือกลูกค้า --</option>
                            ${userOptions}
                        </select>
                        <label class="form-label fw-bold small text-muted mt-3">จำนวนวันที่เช่า (1–7 วัน)</label>
                        <input id="swal-rental-days" type="number" class="form-control bga-input" value="1" min="1" max="7">
                        <div class="small text-success mt-2">ยอดรวม: <strong id="swal-rental-total">${Number(bg.Bg_cost)}</strong> โทเคน</div>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'ยืนยันการเช่า',
                cancelButtonText: 'ยกเลิก',
                didOpen: () => {
                    document.getElementById('swal-rental-days').addEventListener('input', event => {
                        const days = Math.min(7, Math.max(1, Number(event.target.value) || 1));
                        document.getElementById('swal-rental-total').textContent = (Number(bg.Bg_cost) * days).toLocaleString();
                    });
                },
                preConfirm: () => {
                    const userId = document.getElementById('swal-user-select').value;
                    const rentalDays = Number(document.getElementById('swal-rental-days').value);
                    if (!userId) {
                        window.Swal.showValidationMessage('กรุณาเลือกลูกค้า');
                        return false;
                    }
                    if (!Number.isInteger(rentalDays) || rentalDays < 1 || rentalDays > 7) {
                        window.Swal.showValidationMessage('จำนวนวันต้องอยู่ระหว่าง 1–7 วัน');
                        return false;
                    }
                    return { userId, rentalDays };
                }
            });

            if (selectedUserId) {
                try {
                    await window.axios.post('/api/rentals/rent', {
                        User_id: selectedUserId.userId,
                        Bg_id: bg.Bg_id,
                        rental_days: selectedUserId.rentalDays,
                    });
                    
                    window.Swal.fire({ icon: 'success', title: 'เช่าสำเร็จ!', timer: 1500, showConfirmButton: false });
                    this.fetchData(); // ดึงข้อมูลใหม่
                } catch (error) {
                    window.Swal.fire({ icon: 'error', title: 'เช่าไม่สำเร็จ', text: error.response?.data?.message || 'เกิดข้อผิดพลาด' });
                }
            }
        },

        escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        },

        async processReturn(bg) {
            const rental = this.rentals.find(r => Number(r.Bg_id) === Number(bg.Bg_id) && r.Rental_status === 'active');
            if (!rental) {
                await window.Swal.fire({ icon: 'error', title: 'ไม่พบรายการเช่าที่ค้างอยู่', text: 'กรุณารีเฟรชข้อมูลแล้วลองอีกครั้ง' });
                await this.fetchData();
                return;
            }

            const { value: returnData } = await window.Swal.fire({
                title: 'รับคืนบอร์ดเกม',
                html: `
                    <div class="text-start mb-3 p-3 bg-light rounded-3">
                        <div>เกมที่รับคืน: <strong>${this.escapeHtml(bg.Bg_name)}</strong></div>
                    </div>
                    <div class="text-start mb-3">
                        <div>ผู้ทำรายการคืน: <strong>Admin</strong></div>
                        <div>ผู้เช่าเดิม: ${this.escapeHtml(rental.user?.User_name || '-')}</div>
                    </div>
                    <div class="alert alert-warning small mb-0">แอดมินสามารถรับคืนเกมและบันทึกผู้ทำรายการคืนได้</div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'ยืนยันรับคืน',
                cancelButtonText: 'ยกเลิก',
                preConfirm: () => ({ rental_id: rental.Rental_id })
            });

            if (returnData) {
                try {
                    await window.axios.post('/api/rentals/return', {
                        rental_id: returnData.rental_id
                    });
                    
                    const title = returnData ? 'Force คืนเกมสำเร็จ!' : 'รับคืนสำเร็จ!';
                    window.Swal.fire({ icon: 'success', title, text: 'สถานะเกมถูกเปลี่ยนเป็นพร้อมให้เช่าแล้ว', timer: 1800, showConfirmButton: false });
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
