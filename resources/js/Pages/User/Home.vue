<template>
    <div class="d-flex flex-column fade-in">
        <!-- Search & Filters -->
        <div class="bg-white px-3 py-3 shadow-sm sticky-top border-bottom" style="top: 0; z-index: 1020;">
            <div class="position-relative">
                <input type="text" v-model="searchQuery" class="form-control rounded-pill bg-light border-0 ps-5 py-2" placeholder="ค้นหาบอร์ดเกม..." style="font-size: 14px;">
                <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%);"></i>
            </div>
        </div>

        <div class="px-3 mt-4 mb-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">เกมทั้งหมด</h6>
            <small class="text-success fw-bold">แสดง {{ filteredGames.length }} รายการ</small>
        </div>

        <!-- รายการบอร์ดเกมจาก API -->
        <div class="px-3 pb-4 d-flex flex-column gap-3">
            <div v-for="game in filteredGames" :key="game.Bg_id" class="card border-0 shadow-sm rounded-4 overflow-hidden bga-card" :class="{'opacity-75': game.Bg_use_status === 0}">
                
                <div class="bg-light position-relative d-flex align-items-center justify-content-center p-3" style="height: 180px;">
                    <img :src="game.Bg_Image || 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffaed?auto=format&fit=crop&q=80&w=800'" class="h-100 w-100 object-fit-contain" style="mix-blend-mode: multiply;">
                    
                    <div v-if="game.Bg_use_status === 0" class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.4); backdrop-filter: blur(2px);">
                        <span class="badge bg-white text-dark shadow-lg rounded-pill px-3 py-2 border fw-bold d-flex align-items-center gap-2">
                            <i class="fa-solid fa-lock text-danger"></i> ถูกเช่าหมดแล้ว
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="badge bg-success bg-opacity-10 text-success mb-2 border border-success border-opacity-25">{{ game.category?.Bg_category_name || 'ทั่วไป' }}</div>
                            <h6 class="fw-bold mb-0 text-dark">{{ game.Bg_name }}</h6>
                        </div>
                        <div class="text-end bg-success bg-opacity-10 px-2 py-1 rounded-3 border border-success border-opacity-25">
                            <div class="fw-bold text-success mb-0 d-flex align-items-center gap-1" style="font-size: 1.1rem; line-height: 1;">
                                {{ game.Bg_cost }} <i class="fa-solid fa-coins text-warning" style="font-size: 12px;"></i>
                            </div>
                            <div class="text-success" style="font-size: 9px; font-weight: 600;">โทเคน/ครั้ง</div>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 bg-light rounded-3 px-3 py-2 mb-3 text-muted" style="font-size: 12px;">
                        <span class="fw-medium"><i class="fa-solid fa-users me-1 opacity-50"></i> {{ game.Bg_min_player }}-{{ game.Bg_max_player }} คน</span>
                        <div class="vr"></div>
                        <span class="fw-medium"><i class="fa-solid fa-clock me-1 opacity-50"></i> {{ game.Bg_playduration }} นาที</span>
                    </div>
                    
                    <button v-if="game.Bg_use_status === 1 && userProfile" @click="openRentModal(game)" class="btn btn-success w-100 fw-bold rounded-3 shadow-sm py-2 d-flex justify-content-center align-items-center gap-2" style="font-size: 14px;">
                        <i class="fa-solid fa-dice"></i> เช่าเกมนี้เลย
                    </button>
                    <router-link v-else-if="game.Bg_use_status === 1" :to="{ name: 'user.login' }" class="btn btn-outline-success w-100 fw-bold rounded-3 py-2">เข้าสู่ระบบเพื่อเช่า</router-link>
                    <button v-else disabled class="btn btn-secondary bg-opacity-25 text-secondary border-0 w-100 fw-bold rounded-3 py-2 d-flex justify-content-center align-items-center gap-2" style="font-size: 14px;">
                        สินค้าไม่ว่าง
                    </button>
                </div>
            </div>

            <!-- กรณีไม่พบข้อมูล -->
            <div v-if="filteredGames.length === 0" class="text-center py-5">
                <i class="fa-solid fa-box-open text-muted opacity-25 mb-3" style="font-size: 3rem;"></i>
                <p class="text-muted small fw-bold">ไม่พบบอร์ดเกมที่คุณค้นหา</p>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    props: ['userProfile'], 
    data() {
        return {
            boardgames: [],
            searchQuery: ''
        }
    },
    computed: {
        filteredGames() {
            return this.boardgames.filter(game => 
                game.Bg_name.toLowerCase().includes(this.searchQuery.toLowerCase())
            );
        }
    },
    async mounted() {
        await this.fetchBoardgames();
    },
    methods: {
        async fetchBoardgames() {
            try {
                const response = await axios.get('/api/boardgames');
                this.boardgames = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดข้อมูลบอร์ดเกมได้', error);
            }
        },
        openRentModal(game) {
            window.Swal.fire({
                title: `เช่า ${game.Bg_name}?`,
                text: `ระบบจะหักเงิน ${game.Bg_cost} บาท จากกระเป๋าเงินของคุณ`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-check"></i> ยืนยันการเช่า',
                cancelButtonText: 'ยกเลิก',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        const response = await axios.post('/api/rentals/rent', { Bg_id: game.Bg_id });
                        return response.data;
                    } catch (error) {
                        window.Swal.showValidationMessage(
                            error.response?.data?.message || 'เกิดข้อผิดพลาดในการทำรายการ'
                        );
                    }
                },
                allowOutsideClick: () => !window.Swal.isLoading()
            }).then(async (result) => {
                if (result.isConfirmed) {
                    window.Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: 'เช่าเกมเรียบร้อยแล้ว', showConfirmButton: false, timer: 2000 });
                    await this.fetchBoardgames();
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
.bga-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.bga-card:active:not(.opacity-75) { transform: scale(0.98); }
</style>
