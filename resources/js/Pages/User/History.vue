<template>
    <div class="d-flex flex-column fade-in p-3">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-3 mt-1">
            <h5 class="fw-bold text-dark mb-0">ประวัติธุรกรรม</h5>
            <span
                class="badge bg-success bg-opacity-10 text-success px-2 py-1 rounded-3 border border-success border-opacity-25 shadow-sm d-flex align-items-center gap-1"
                style="font-size: 10px;">
                <i class="fa-solid fa-link"></i> Secured by Blockchain
            </span>
        </div>

        <!-- ตัวกรอง (Filters) -->
        <div class="d-flex gap-2 overflow-auto hide-scroll pb-2 mb-2">
            <button @click="filter = 'all'" :class="filter === 'all' ? 'btn-dark' : 'btn-white text-muted border'"
                class="btn rounded-pill fw-bold shadow-sm px-3 py-1"
                style="font-size: 12px; white-space: nowrap;">ทั้งหมด</button>
            <button @click="filter = 'topup'" :class="filter === 'topup' ? 'btn-dark' : 'btn-white text-muted border'"
                class="btn rounded-pill fw-bold shadow-sm px-3 py-1"
                style="font-size: 12px; white-space: nowrap;">เติมโทเคน</button>
            <button @click="filter = 'rent'" :class="filter === 'rent' ? 'btn-dark' : 'btn-white text-muted border'"
                class="btn rounded-pill fw-bold shadow-sm px-3 py-1" style="font-size: 12px; white-space: nowrap;">เช่า
                / คืนเกม</button>
        </div>

        <!-- รายการธุรกรรม -->
        <div class="d-flex flex-column gap-2 pb-5">
            <div v-for="tx in filteredTransactions" :key="tx.Ts_id"
                class="bg-white p-3 rounded-4 shadow-sm border border-light d-flex align-items-center justify-content-between">

                <div class="d-flex align-items-center gap-3">
                    <!-- ไอคอนตามประเภทธุรกรรม -->
                    <div v-if="tx.T_type === 'topup_credit'"
                        class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center text-success border border-success border-opacity-25"
                        style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-qrcode fs-5"></i>
                    </div>
                    <div v-else-if="tx.T_type === 'rental_debit'"
                        class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center text-warning border border-warning border-opacity-25"
                        style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-dice fs-5"></i>
                    </div>
                    <div v-else-if="tx.T_type === 'return_event'"
                        class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center text-success border border-success border-opacity-25"
                        style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-rotate-left fs-5"></i>
                    </div>
                    <div v-else
                        class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center text-danger border border-danger border-opacity-25"
                        style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-arrow-right-arrow-left fs-6"></i>
                    </div>

                    <!-- รายละเอียด -->
                    <div>
                        <p class="fw-bold text-dark mb-0" style="font-size: 14px; line-height: 1.2;">
                            {{ typeLabel(tx.T_type) }} <span v-if="tx.boardgame"> {{ tx.boardgame.Bg_name }}</span>
                        </p>
                        <p class="text-muted mb-0" style="font-size: 10px; font-family: monospace;">
                            Tx: 0x{{ generateHash(tx.Ts_id) }} • {{ formatDate(tx.created_at) }}
                        </p>
                    </div>
                </div>

                <!-- ยอดเงิน -->
                <div class="text-end">
                    <span v-if="tx.T_type === 'topup_credit'" class="fw-bold text-success" style="font-size: 15px;">
                        +{{ tx.T_cost }} <i class="fa-solid fa-coins text-warning" style="font-size: 11px;"></i>
                    </span>
                    <span v-else-if="tx.T_type === 'return_event'" class="fw-bold text-success" style="font-size: 13px;">
                      คืนแล้ว
                    </span>
                    <span v-else class="fw-bold text-danger" style="font-size: 15px;">
                        -{{ tx.T_cost }} <i class="fa-solid fa-coins text-warning" style="font-size: 11px;"></i>
                    </span>
                </div>

            </div>

            <!-- กรณีไม่มีประวัติ -->
            <div v-if="filteredTransactions.length === 0" class="text-center py-5 text-muted">
                <i class="fa-solid fa-receipt fs-1 mb-2 opacity-50"></i>
                <h6 class="fw-bold mb-0">ยังไม่มีประวัติธุรกรรม</h6>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            transactions: [],
            filter: 'all' // all, topup, rent
        }
    },
    mounted() {
        this.fetchTransactions();
    },
    computed: {
        filteredTransactions() {
            if (this.filter === 'topup') {
                return this.transactions.filter(tx => tx.T_type === 'topup_credit');
            }
            if (this.filter === 'rent') {
                return this.transactions.filter(tx => ['rental_debit', 'return_event', 'late_fee_debit'].includes(tx.T_type));
            }
            return this.transactions;
        }
    },
    methods: {
        async fetchTransactions() {
            try {
                // 🚀 เรียก API ดึงประวัติธุรกรรมของฉัน
                const response = await axios.get('/api/transactions');
                this.transactions = response.data;
            } catch (error) {
                console.error("โหลดประวัติไม่สำเร็จ", error);
            }
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('th-TH', { month: 'short', day: 'numeric' });
        },
        generateHash(id) {
            // สร้าง Hash จำลองสั้นๆ ให้ดูเท่เหมือน Blockchain
            const str = "0000" + id * 87654321;
            return str.substring(str.length - 6) + "...";
        },
        typeLabel(type) {
            return {
                topup_credit: 'เติมโทเคน',
                rental_debit: 'เช่าเกม',
                return_event: 'คืนเกม',
                late_fee_debit: 'ค่าปรับคืนล่าช้า',
                admin_debit: 'หักโทเคนโดยผู้ดูแล'
            }[type] || type;
        }
    }
}
</script>

<style scoped>
.fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hide-scroll::-webkit-scrollbar {
    display: none;
}

.hide-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
