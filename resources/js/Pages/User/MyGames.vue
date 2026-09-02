<template>
    <div class="d-flex flex-column fade-in p-3">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-3 mt-1">
            <h5 class="fw-bold text-dark mb-0">เกมที่กำลังเช่า</h5>
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-1.5 rounded-pill border border-success border-opacity-25 shadow-sm">
                {{ activeRentals.length }} รายการ
            </span>
        </div>

        <!-- รายการเกมที่กำลังเช่า -->
        <div class="d-flex flex-column gap-3 pb-4">
            
            <div v-for="rent in activeRentals" :key="rent.Rental_id" class="bg-white rounded-4 p-3 shadow-sm border border-light d-flex flex-column gap-3">
                <div class="d-flex gap-3">
                    <img :src="rent.boardgame?.Bg_Image || 'https://images.unsplash.com/photo-1611891487122-207579d67d98?auto=format&fit=crop&q=80&w=200'"
                         class="rounded-3 object-fit-cover shadow-sm border border-light" style="width: 80px; height: 96px;">
                    
                    <div class="flex-grow-1 pt-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="fw-bold text-dark mb-0" style="font-size: 15px;">{{ rent.boardgame?.Bg_name || 'ชื่อเกม' }}</h6>
                            <span class="badge px-2 py-1 border" :class="rent.is_overdue ? 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25' : 'bg-success bg-opacity-10 text-success border-success border-opacity-25'" style="font-size: 9px;">
                                {{ rent.is_overdue ? `เกินกำหนด ${rent.overdue_days} วัน` : 'สถานะปกติ' }}
                            </span>
                        </div>
                        <p class="text-muted mb-1" style="font-size: 11px;">วันที่เช่า: {{ formatDate(rent.rented_at) }}</p>
                        <p class="text-muted mb-2" style="font-size: 11px;">กำหนดคืน: {{ formatDateTime(rent.due_at) }} ({{ rent.rental_days }} วัน)</p>
                        
                        <div class="bg-light px-2 py-1.5 rounded-3 border d-inline-block">
                            <span class="fw-bold" :class="rent.is_overdue ? 'text-danger' : 'text-secondary'" style="font-size: 11px;">
                                <i class="fa-solid fa-clock me-1" :class="rent.is_overdue ? 'text-danger' : 'text-success'"></i>
                                {{ rent.is_overdue ? `ค่าปรับสะสม ${rent.accrued_late_fee} โทเคน` : `กำหนดคืน ${formatDate(rent.due_at)}` }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ปุ่มคืนเกม (จะยิงไปหา Controller คุณ) -->
                <button @click="returnGame(rent)" :disabled="!canAffordFine(rent)" class="btn w-100 fw-bold rounded-3 py-2 d-flex justify-content-center align-items-center gap-2 transition-transform" :class="canAffordFine(rent) ? 'btn-outline-success' : 'btn-outline-danger'" style="font-size: 14px; border-width: 2px;">
                    <i class="fa-solid fa-box-open"></i> คืนบอร์ดเกม
                </button>
                <small v-if="!canAffordFine(rent)" class="text-danger text-center fw-bold">ยอดโทเคนไม่พอชำระค่าปรับ กรุณาเติมโทเคนก่อนคืน</small>
            </div>

            <!-- กรณีคืนเกมหมดแล้ว หรือยังไม่ได้เช่า -->
            <div v-if="activeRentals.length === 0" class="text-center py-5 mt-4">
                <div class="w-100 d-flex justify-content-center mb-3">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center shadow-sm border border-white" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-box-open text-muted opacity-25" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
                <h6 class="text-dark fw-bold mb-1">ยังไม่มีเกมที่เช่าอยู่</h6>
                <p class="text-muted small mb-4">ไปหาบอร์ดเกมสนุกๆ เล่นกันเถอะ!</p>
                <router-link :to="{ name: 'user.home' }" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size: 14px;">
                    ค้นหาเกมที่น่าสนใจ
                </router-link>
            </div>
            
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    props: ['userProfile'], // รับค่า userProfile จาก UserLayout
    data() {
        return {
            activeRentals: []
        }
    },
    mounted() {
        this.fetchMyRentals();
    },
    methods: {
        async fetchMyRentals() {
            try {
                const response = await axios.get('/api/rentals/active');
                this.activeRentals = response.data;
            } catch (error) {
                console.error("ดึงข้อมูลไม่สำเร็จ", error);
            }
        },
        formatDate(dateString) {
            if (!dateString) return 'วันนี้';
            const date = new Date(dateString);
            return date.toLocaleDateString('th-TH', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        formatDateTime(dateString) {
            if (!dateString) return '-';
            return new Date(dateString).toLocaleString('th-TH', {
                year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
        },
        canAffordFine(rent) {
            return Number(this.userProfile?.wallet?.Wallet_count || 0) >= Number(rent.accrued_late_fee || 0);
        },
        returnGame(rent) {
            if (!this.canAffordFine(rent)) return;
            const feeText = Number(rent.accrued_late_fee || 0) > 0
                ? `ระบบจะหักค่าปรับ ${Number(rent.accrued_late_fee).toLocaleString()} โทเคน`
                : 'รายการนี้ไม่มีค่าปรับ';
            window.Swal.fire({
                title: 'คืนบอร์ดเกม?',
                text: `คืนเกม ${rent.boardgame?.Bg_name}: ${feeText}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-check"></i> ยืนยันการคืนเกม',
                cancelButtonText: 'ยกเลิก',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        // 🚀 ยิง API พร้อม Payload ที่ Controller ของคุณต้องการ
                        const response = await axios.post(`/api/rentals/return`, {
                            rental_id: rent.Rental_id
                        });
                        return response.data;
                    } catch (error) {
                        window.Swal.showValidationMessage(
                            error.response?.data?.message || 'เกิดข้อผิดพลาดในการคืนเกม'
                        );
                    }
                },
                allowOutsideClick: () => !window.Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    window.Swal.fire({ 
                        icon: 'success', 
                        title: 'คืนเกมสำเร็จ!', 
                        text: 'ระบบได้บันทึกการส่งคืนและลงข้อมูลใน Blockchain เรียบร้อยแล้ว', 
                        showConfirmButton: false, 
                        timer: 2000 
                    });
                    this.fetchMyRentals(); // โหลดรายการเกมใหม่ (เกมที่คืนจะหายไปจากหน้านี้)
                    this.$emit('refresh-wallet');
                }
            });
        }
    }
}
</script>

<style scoped>
.fade-in { animation: fadeIn 0.3s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.transition-transform:active { transform: scale(0.97); }
</style>
