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
                                <th class="text-muted fw-bold py-3 text-end">จำนวนเงิน (โทเคน)</th>
                                <th class="text-muted fw-bold py-3 text-nowrap">วันที่ยืม - วันที่ต้องคืน</th>
                                <th class="text-muted fw-bold py-3 text-center">Polygon Amoy</th>
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
                                    <span v-if="ts.T_type === 'topup_credit'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-arrow-trend-up me-1"></i> เติมเงิน
                                    </span>
                                    <span v-else-if="['rental_debit', 'late_fee_debit', 'admin_debit'].includes(ts.T_type)" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-arrow-trend-down me-1"></i> {{ ts.T_type === 'rental_debit' ? 'เช่าเกม' : (ts.T_type === 'late_fee_debit' ? 'ค่าปรับ' : 'หักโทเคน') }}
                                    </span>
                                    <span v-else-if="ts.T_type === 'return_event'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-rotate-left me-1"></i> คืนเกม
                                    </span>
                                    <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                        {{ ts.T_type }}
                                    </span>
                                </td>
                                
                                <!-- จำนวนเงิน -->
                                <td class="text-end fw-bold">
                                    <span :class="['topup_credit', 'return_event'].includes(ts.T_type) ? 'text-success' : 'text-danger'">
                                        {{ ts.T_type === 'topup_credit' ? '+' : (ts.T_type === 'return_event' ? '' : '-') }}{{ ts.T_type === 'return_event' ? 'คืนแล้ว' : ts.T_cost }}
                                    </span>
                                </td>

                                <td class="small text-nowrap">
                                    <template v-if="ts.rental">
                                        <div class="fw-semibold text-dark">{{ formatDate(ts.rental.rented_at) }}</div>
                                        <div class="text-muted"><i class="fa-solid fa-arrow-down-long me-1"></i>{{ formatDate(ts.rental.due_at) }}</div>
                                    </template>
                                    <span v-else class="text-muted">-</span>
                                </td>

                                <td class="text-center small">
                                    <a v-if="ts.Chain_status === 'confirmed'" :href="ts.chain_explorer_url" target="_blank" rel="noopener" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-decoration-none">
                                        ยืนยันแล้ว <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                    </a>
                                    <span v-else-if="['pending', 'processing'].includes(ts.Chain_status)" class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">กำลังยืนยัน</span>
                                    <button v-else-if="ts.Chain_status === 'failed'" type="button" class="btn btn-sm btn-outline-danger" @click="retryPolygon(ts)">ลองใหม่</button>
                                    <span v-else class="text-muted">ยังไม่เปิดใช้</span>
                                </td>

                                <!-- อ้างอิงบอร์ดเกม -->
                                <td class="text-center text-muted small">
                                    <span v-if="ts.boardgame">{{ ts.boardgame.Bg_name }}</span>
                                    <span v-else>-</span>
                                </td>
                            </tr>
                            <tr v-if="transactions.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">
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
            transactions: [],
            polygonPoller: null
        };
    },
    mounted() {
        this.fetchTransactions();
        this.polygonPoller = window.setInterval(() => {
            if (this.transactions.some(transaction => ['pending', 'processing'].includes(transaction.Chain_status))) {
                this.fetchTransactions();
            }
        }, 5000);
    },
    beforeUnmount() {
        window.clearInterval(this.polygonPoller);
    },
    methods: {
        formatDate(value) {
            if (!value) return '-';
            return new Date(value).toLocaleDateString('th-TH', {
                day: '2-digit', month: 'short', year: 'numeric'
            });
        },
        async fetchTransactions() {
            try {
                const response = await window.axios.get('/api/transactions');
                this.transactions = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดประวัติธุรกรรมได้', error);
            }
        },
        async retryPolygon(transaction) {
            try {
                await window.axios.post(`/api/transactions/${transaction.Ts_id}/polygon/retry`);
                transaction.Chain_status = 'pending';
            } catch (error) {
                window.Swal.fire({ icon: 'error', title: 'ส่งรายการไม่สำเร็จ', text: error.response?.data?.message || 'กรุณาลองใหม่' });
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
