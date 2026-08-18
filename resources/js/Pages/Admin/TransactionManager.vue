<template>
    <AdminLayout>
        <template #header>ประวัติธุรกรรม (Transaction History)</template>

        <div class="fade-in-section">
            <!-- 🛡️ เพิ่มปุ่ม Verify Blockchain ไว้ด้านบนขวาของตาราง -->
            <div class="d-flex justify-content-end mt-3 mb-2">
                <button @click="verifyBlockchain" class="btn btn-dark shadow-sm fw-bold px-4 rounded-pill">
                    <i class="fa-solid fa-shield-halved me-2 text-info"></i> ตรวจสอบความถูกต้องของเชน
                </button>
            </div>

            <div class="bg-white rounded-4 shadow-sm border border-light overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-muted fw-bold py-3 px-4">วัน-เวลา</th>
                                <th class="text-muted fw-bold py-3">ผู้ใช้งาน</th>
                                <th class="text-muted fw-bold py-3">ประเภทรายการ</th>
                                <th class="text-muted fw-bold py-3 text-end">จำนวนเงิน (บาท)</th>
                                <th class="text-muted fw-bold py-3 text-center">อ้างอิงบอร์ดเกม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ts in transactions" :key="ts.Ts_id">
                                <!-- วันเวลา -->
                                <td class="px-4 text-muted small">
                                    {{ new Date(ts.created_at).toLocaleString('th-TH') }}
                                </td>
                                
                                <!-- ข้อมูลผู้ใช้งาน -->
                                <td>
                                    <div class="d-flex align-items-center gap-2" v-if="ts.user">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px; font-size: 0.9rem;">
                                            {{ ts.user.User_name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small">{{ ts.user.User_name }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">@{{ ts.user.User_username }}</div>
                                        </div>
                                    </div>
                                    <span v-else class="text-muted small">ไม่พบข้อมูลผู้ใช้</span>
                                </td>
                                
                                <!-- ประเภทรายการ -->
                                <td>
                                    <span v-if="ts.T_type.includes('Topup')" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-arrow-trend-up me-1"></i> เติมเงิน
                                    </span>
                                    <span v-else-if="ts.T_type.includes('Deduct') || ts.T_type === 'เช่าเกม'" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-arrow-trend-down me-1"></i> {{ ts.T_type === 'เช่าเกม' ? 'เช่าเกม' : 'หักเงิน' }}
                                    </span>
                                    <span v-else-if="ts.T_type === 'คืนเกม'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-rotate-left me-1"></i> คืนเกม
                                    </span>
                                    <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                        {{ ts.T_type }}
                                    </span>
                                </td>
                                
                                <!-- จำนวนเงิน -->
                                <td class="text-end fw-bold">
                                    <span :class="ts.T_type.includes('Topup') || ts.T_type === 'คืนเกม' ? 'text-success' : 'text-danger'">
                                        {{ ts.T_type.includes('Topup') ? '+' : (ts.T_type === 'คืนเกม' ? '' : '-') }}{{ ts.T_cost }}
                                    </span>
                                </td>

                                <!-- อ้างอิงบอร์ดเกม -->
                                <td class="text-center text-muted small">
                                    <span v-if="ts.boardgame">{{ ts.boardgame.Bg_name }}</span>
                                    <span v-else>-</span>
                                </td>
                            </tr>
                            <tr v-if="transactions.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fs-2 mb-3 text-light"></i>
                                    <p class="mb-0">ยังไม่มีประวัติการทำธุรกรรม</p>
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
            transactions: []
        };
    },
    mounted() {
        this.fetchTransactions();
    },
    methods: {
        async fetchTransactions() {
            try {
                const response = await window.axios.get('/api/transactions');
                this.transactions = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดประวัติธุรกรรมได้', error);
            }
        },
        
        // 🔗 เพิ่มฟังก์ชันตรวจสอบ Blockchain และรีเฟรชตารางอัตโนมัติ
        async verifyBlockchain() {
            window.Swal.fire({
                title: 'กำลังตรวจสอบเครือข่าย...',
                html: 'ระบบกำลังดึงข้อมูลจากโหนดทั้งหมดมาคำนวณ Hash ใหม่<br>โปรดรอสักครู่',
                allowOutsideClick: false,
                didOpen: () => { window.Swal.showLoading(); }
            });

            try {
                const response = await window.axios.get('/api/blockchain/verify');
                const data = response.data;

                let nodesHtml = '<div class="text-start mt-3">';
                data.nodes.forEach(node => {
                    const icon = node.status 
                        ? '<i class="fa-solid fa-circle-check text-success me-2"></i>' 
                        : '<i class="fa-solid fa-circle-xmark text-danger me-2"></i>';
                    const textClass = node.status ? 'text-success' : 'text-danger fw-bold';
                    
                    nodesHtml += `
                        <div class="p-2 border rounded mb-2 bg-light shadow-sm">
                            <div class="fw-bold">${icon} ${node.node}</div>
                            <div class="small ${textClass} ms-4">${node.message}</div>
                        </div>
                    `;
                });
                nodesHtml += '</div>';

                const isSuccess = data.success;
                
                // แจ้งเตือนเสร็จแล้วดักจับการกดปุ่ม "รับทราบ"
                window.Swal.fire({
                    icon: isSuccess ? 'success' : 'warning',
                    title: isSuccess ? 'เครือข่ายปลอดภัย' : 'พบข้อผิดพลาดในเครือข่าย!',
                    html: `<strong class="${isSuccess ? 'text-success' : 'text-danger'} fs-5">${data.message}</strong>` + nodesHtml,
                    confirmButtonText: 'รับทราบ',
                    width: '500px'
                }).then((result) => {
                    // 🔄 ถ้าระบบจัดการ Restore DB แล้ว พอแอดมินกดปิดป๊อปอัป ให้รีเฟรชข้อมูลตารางทันที
                    if (result.isConfirmed) {
                        this.fetchTransactions();
                    }
                });

            } catch (error) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'การเชื่อมต่อขัดข้อง',
                    text: 'ไม่สามารถติดต่อเครือข่ายโหนดเพื่อตรวจสอบระบบได้'
                });
            }
        }
    }
}
</script>

<style scoped>
.fade-in-section {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>