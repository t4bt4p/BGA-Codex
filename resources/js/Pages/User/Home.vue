<template>
    <div class="d-flex flex-column fade-in">
        <section class="desktop-welcome">
            <div><p>เลือกเกม แล้วชวนเพื่อนมาเล่น</p><h1>เกมถัดไปของคุณ<br>เริ่มต้นที่นี่</h1><span>ค้นหาบอร์ดเกมที่ใช่ เลือกวันเช่า และชำระด้วยโทเคนในกระเป๋าของคุณ</span></div>
            <div class="welcome-dice" aria-hidden="true"><span v-for="dot in 6" :key="dot"></span></div>
        </section>
        <!-- Search & Filters -->
        <div class="bg-white px-3 py-3 sticky-top border-bottom search-panel" style="top: 0; z-index: 1020;">
            <div class="position-relative search-box">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" v-model="searchQuery" class="form-control search-input" placeholder="ค้นหาบอร์ดเกม...">
                <button type="button" class="filter-toggle" :class="{ active: showAdvancedFilters }" aria-label="เปิดตัวกรอง" @click="showAdvancedFilters = !showAdvancedFilters">
                    <i class="fa-solid fa-sliders"></i>
                </button>
            </div>
            <div class="category-scroll mt-3">
                <button type="button" class="category-pill" :class="{ active: categoryFilter === '' }" @click="categoryFilter = ''">ทั้งหมด</button>
                <button v-for="category in categories" :key="category.id" type="button" class="category-pill" :class="{ active: categoryFilter === category.id }" :aria-pressed="categoryFilter === category.id" @click="categoryFilter = category.id">{{ category.name }}</button>
            </div>
            <div v-if="showAdvancedFilters" class="advanced-filters mt-2">
                <div>
                    <select v-model.number="playerFilter" class="form-select form-select-sm bg-light border-0" aria-label="กรองตามจำนวนผู้เล่น">
                        <option :value="0">ทุกจำนวนผู้เล่น</option>
                        <option v-for="count in playerOptions" :key="count" :value="count">{{ count }} คน</option>
                    </select>
                </div>
                <div>
                    <select v-model="durationFilter" class="form-select form-select-sm bg-light border-0" aria-label="กรองตามระยะเวลาเล่น">
                        <option value="">ทุกระยะเวลา</option>
                        <option value="short">ไม่เกิน 30 นาที</option>
                        <option value="medium">31–60 นาที</option>
                        <option value="long">มากกว่า 60 นาที</option>
                    </select>
                </div>
                <div class="clear-filter-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" @click="clearFilters">ล้างตัวกรอง</button>
                </div>
            </div>
        </div>

        <div class="px-3 mt-4 mb-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">เกมทั้งหมด</h6>
            <small class="text-success fw-bold">แสดง {{ filteredGames.length }} รายการ</small>
        </div>

        <!-- รายการบอร์ดเกมจาก API -->
        <div class="game-grid px-3 pb-4">
            <div v-for="game in filteredGames" :key="game.Bg_id" class="card border-0 shadow-sm rounded-4 overflow-hidden bga-card" :class="{'opacity-75': game.Bg_use_status === 0}">
                
                <div class="bg-light position-relative d-flex align-items-center justify-content-center p-3" style="height: 180px;">
                    <img :src="game.Bg_Image || 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffaed?auto=format&fit=crop&q=80&w=800'" class="h-100 w-100 object-fit-contain" style="mix-blend-mode: multiply;">
                    
                    <div v-if="game.Bg_use_status === 0" class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.4); backdrop-filter: blur(2px);">
                        <span class="badge bg-white text-dark shadow-lg rounded-pill px-3 py-2 border fw-bold d-flex align-items-center gap-2">
                            <i class="fa-solid fa-lock text-danger"></i> ถูกเช่าหมดแล้ว
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-3 d-flex flex-column">
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
            searchQuery: '',
            categoryFilter: '',
            playerFilter: 0,
            durationFilter: '',
            showAdvancedFilters: false
        }
    },
    computed: {
        filteredGames() {
            const search = this.searchQuery.trim().toLocaleLowerCase('th');
            return this.boardgames.filter(game => {
                const matchesName = String(game.Bg_name).toLocaleLowerCase('th').includes(search);
                const categoryName = String(game.category?.Bg_category_name || '').trim().toLowerCase();
                const minPlayers = Number(game.Bg_min_player);
                const maxPlayers = Number(game.Bg_max_player);
                const matchesCategory = !this.categoryFilter
                    || (this.categoryFilter === 'party' && ['ปาร์ตี้', 'ปาตี้', 'party', 'party game', 'party games'].includes(categoryName))
                    || (this.categoryFilter === 'strategy' && ['กลยุทธ์', 'strategy', 'strategy game', 'strategy games'].includes(categoryName))
                    || (this.categoryFilter === 'two' && minPlayers <= 2 && maxPlayers >= 2)
                    || (this.categoryFilter === 'group' && maxPlayers >= 6);
                const players = Number(this.playerFilter);
                const matchesPlayers = !players || (players >= Number(game.Bg_min_player) && players <= Number(game.Bg_max_player));
                const duration = Number(game.Bg_playduration);
                const matchesDuration = !this.durationFilter
                    || (this.durationFilter === 'short' && duration <= 30)
                    || (this.durationFilter === 'medium' && duration >= 31 && duration <= 60)
                    || (this.durationFilter === 'long' && duration > 60);
                return matchesName && matchesCategory && matchesPlayers && matchesDuration;
            });
        },
        categories() {
            return [
                { id: 'party', name: 'ปาร์ตี้' },
                { id: 'strategy', name: 'กลยุทธ์' },
                { id: 'two', name: '2 คน' },
                { id: 'group', name: 'หลายคน' },
            ];
        },
        playerOptions() {
            const maximum = Math.max(0, ...this.boardgames.map(game => Number(game.Bg_max_player) || 0));
            return Array.from({ length: maximum }, (_, index) => index + 1);
        }
    },
    async mounted() {
        await this.fetchBoardgames();
        window.Echo.channel('boardgames')
            .listen('.boardgame.status.changed', this.applyRealtimeStatus);
    },
    beforeUnmount() {
        window.Echo.leave('boardgames');
    },
    methods: {
        applyRealtimeStatus(event) {
            const game = this.boardgames.find(item => Number(item.Bg_id) === Number(event.Bg_id));
            if (game) game.Bg_use_status = Number(event.Bg_use_status);
        },
        clearFilters() {
            this.searchQuery = '';
            this.categoryFilter = '';
            this.playerFilter = 0;
            this.durationFilter = '';
        },
        async fetchBoardgames() {
            try {
                const response = await axios.get('/api/boardgames');
                this.boardgames = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดข้อมูลบอร์ดเกมได้', error);
            }
        },
        openRentModal(game) {
            const balance = Number(this.userProfile?.wallet?.Wallet_count || 0);
            let selectedDays = 1;
            const dayOptions = Array.from({ length: 7 }, (_, index) => {
                const days = index + 1;
                const due = new Date();
                due.setDate(due.getDate() + days);
                const weekday = due.toLocaleDateString('th-TH', { weekday: 'short' });
                const date = due.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
                return `<button type="button" class="rent-day-option ${days === 1 ? 'selected' : ''}" data-days="${days}" aria-pressed="${days === 1}">
                    <strong>${weekday}</strong><span>${date}</span><small>${days} วัน</small>
                </button>`;
            }).join('');
            window.Swal.fire({
                title: 'เช่าบอร์ดเกม',
                width: '520px',
                heightAuto: false,
                customClass: { container: 'rent-modal-container', popup: 'rent-modal', htmlContainer: 'rent-modal-content', actions: 'rent-modal-actions' },
                html: `
                    <div class="text-start bg-light rounded-4 p-3 mb-3">
                        <strong>${this.escapeHtml(game.Bg_name)}</strong>
                        <div class="small text-muted mt-1">${Number(game.Bg_cost).toLocaleString()} โทเคน / วัน</div>
                    </div>
                    <p class="text-start fw-bold mb-2">เลือกวันที่คืนเกม</p>
                    <div class="rent-day-list mb-3">${dayOptions}</div>
                    <div class="text-start border-top pt-3">
                        <div class="d-flex justify-content-between mb-2"><span>ค่าเช่าบอร์ดเกม</span><strong><span id="rent-total">${game.Bg_cost}</span> โทเคน</strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>ยอดโทเคนปัจจุบัน</span><strong>${balance.toLocaleString()} โทเคน</strong></div>
                        <div class="d-flex justify-content-between border-top pt-2"><span>ยอดโทเคนคงเหลือ</span><strong id="rent-remaining">${(balance - Number(game.Bg_cost)).toLocaleString()} โทเคน</strong></div>
                    </div>`,
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-check"></i> ยืนยันการเช่า',
                cancelButtonText: 'ยกเลิก',
                showLoaderOnConfirm: true,
                didOpen: () => {
                    const dayList = document.querySelector('.rent-day-list');
                    let touchStartX = 0;
                    let touchStartY = 0;
                    let initialScrollLeft = 0;
                    let gestureDirection = null;
                    let suppressClick = false;

                    dayList.addEventListener('touchstart', event => {
                        const touch = event.touches[0];
                        touchStartX = touch.clientX;
                        touchStartY = touch.clientY;
                        initialScrollLeft = dayList.scrollLeft;
                        gestureDirection = null;
                        suppressClick = false;
                    }, { passive: true });

                    dayList.addEventListener('touchmove', event => {
                        const touch = event.touches[0];
                        const deltaX = touchStartX - touch.clientX;
                        const deltaY = touchStartY - touch.clientY;
                        if (!gestureDirection && (Math.abs(deltaX) > 6 || Math.abs(deltaY) > 6)) {
                            gestureDirection = Math.abs(deltaX) > Math.abs(deltaY) ? 'horizontal' : 'vertical';
                        }
                        if (gestureDirection === 'horizontal') {
                            event.preventDefault();
                            event.stopPropagation();
                            suppressClick = true;
                            dayList.scrollLeft = initialScrollLeft + deltaX;
                        }
                    }, { passive: false });

                    dayList.addEventListener('touchend', () => {
                        gestureDirection = null;
                        setTimeout(() => { suppressClick = false; }, 80);
                    }, { passive: true });

                    const updateSummary = days => {
                        const total = Number(game.Bg_cost) * days;
                        document.getElementById('rent-total').textContent = total.toLocaleString();
                        const remaining = document.getElementById('rent-remaining');
                        remaining.textContent = `${(balance - total).toLocaleString()} โทเคน`;
                        remaining.classList.toggle('text-danger', balance < total);
                        document.querySelectorAll('.rent-day-option').forEach(option => {
                            const active = Number(option.dataset.days) === days;
                            option.classList.toggle('selected', active);
                            option.setAttribute('aria-pressed', String(active));
                        });
                    };
                    document.querySelectorAll('.rent-day-option').forEach(option => option.addEventListener('click', event => {
                        if (suppressClick) {
                            event.preventDefault();
                            return;
                        }
                        selectedDays = Number(option.dataset.days);
                        updateSummary(selectedDays);
                    }));
                    updateSummary(1);
                },
                preConfirm: async () => {
                    try {
                        const rentalDays = selectedDays;
                        if (balance < Number(game.Bg_cost) * rentalDays) {
                            window.Swal.showValidationMessage('ยอดโทเคนไม่เพียงพอสำหรับจำนวนวันที่เลือก');
                            return false;
                        }
                        const response = await axios.post('/api/rentals/rent', { Bg_id: game.Bg_id, rental_days: rentalDays });
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
        },
        escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        }
    }
}
</script>

<style scoped>
.search-panel { width: 100%; min-width: 0; overflow: hidden; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05); }
.search-box { width: 100%; min-width: 0; height: 48px; }
.search-input {
    display: block; width: 100%; max-width: 100%; box-sizing: border-box;
    height: 48px; padding: 0 52px 0 48px; border: 1px solid #dedede;
    border-radius: 7px; background: #fff; font-size: 14px; font-weight: 600;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}
.search-input:focus { border-color: #00ce6a; box-shadow: 0 0 0 3px rgba(0, 206, 106, 0.12); }
.search-icon {
    position: absolute; left: 13px; top: 50%; z-index: 2;
    transform: translateY(-50%); color: #555; font-size: 23px;
}
.filter-toggle {
    position: absolute; right: 5px; top: 4px; width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center; padding: 0;
    border: 0; background: transparent; color: #00ce6a; font-size: 24px;
}
.filter-toggle.active { color: #009e52; }
.category-scroll {
    display: flex; width: 100%; max-width: 100%; min-width: 0;
    gap: 10px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
    -webkit-overflow-scrolling: touch; touch-action: pan-x;
}
.category-scroll::-webkit-scrollbar { display: none; }
.category-pill {
    flex: 0 0 auto; min-width: 92px; padding: 8px 18px; border: 0;
    border-radius: 999px; background: #f0eeee; color: #b7b7b7;
    font-size: 14px; font-weight: 700; white-space: nowrap;
}
.category-pill.active { background: #06cd6c; color: #fff; }
.advanced-filters {
    display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 8px; width: 100%; min-width: 0; padding-top: 4px;
}
.advanced-filters > * { min-width: 0; }
.advanced-filters .form-select { width: 100%; min-width: 0; }
.clear-filter-wrap { grid-column: 1 / -1; }
.fade-in { animation: fadeIn 0.3s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.bga-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.bga-card:active:not(.opacity-75) { transform: scale(0.98); }
:global(.rent-modal-container) { padding:8px !important; overflow-y:auto !important; align-items:flex-start !important; -webkit-overflow-scrolling:touch; }
:global(.rent-modal) { display:flex !important; flex-direction:column; width:min(520px, 100%) !important; max-height:calc(100dvh - 16px); min-height:0; margin:auto !important; overflow:hidden; }
:global(.rent-modal .swal2-title) { flex:0 0 auto; }
:global(.rent-modal-content) { flex:1 1 auto; min-height:0; overflow-x:hidden !important; overflow-y:auto !important; overscroll-behavior-y:contain; touch-action:auto; -webkit-overflow-scrolling:touch; padding-bottom:16px !important; }
:global(.rent-modal-actions) { flex:0 0 auto; width:100%; padding:8px 20px 12px; gap:10px; background:#fff; }
:global(.rent-modal-actions button) { min-height:48px; flex:1; margin:0 !important; touch-action:manipulation; }
:global(.rent-day-list) { display:flex; width:100%; max-width:100%; gap:10px; overflow-x:auto !important; overflow-y:hidden; padding:2px 2px 12px; touch-action:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
:global(.rent-day-list::-webkit-scrollbar) { display:none; }
:global(.rent-day-option) { appearance:none; -webkit-appearance:none; flex:0 0 96px; background:#fff; color:#111827; min-height:104px; padding:12px 8px; border:1px solid #cbd5e1; border-radius:18px; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; touch-action:auto; user-select:none; -webkit-user-select:none; -webkit-tap-highlight-color:transparent; }
:global(.rent-day-option strong) { font-size:16px; }
:global(.rent-day-option span) { font-weight:700; }
:global(.rent-day-option small) { color:#94a3b8; }
:global(.rent-day-option.selected) { border-color:#16a34a; background:#dcfce7; color:#16a34a; }
@media (orientation: portrait), (max-width: 600px) {
    :global(.rent-modal-content) {
        padding-left:12px !important;
        padding-right:12px !important;
    }
}
</style>
