<template>
    <AdminLayout>
        <!-- ส่งชื่อหน้าต่างไปแทนที่ <slot name="header"> ใน AdminLayout -->
        <template #header>แดชบอร์ดสรุปผล (Dashboard)</template>
        
        <div class="fade-in-section">
            <!-- KPI Cards Row -->
            <div class="row g-4 mb-4">
                
                <!-- Card 1: ยอดเช่าวันนี้ -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card bga-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <p class="kpi-label mb-1">ยอดเช่าวันนี้</p>
                                <div class="d-flex align-items-end gap-2">
                        <h2 class="kpi-value mb-0 text-dark">{{ report.rentals_today }}</h2>
                                    <span class="kpi-trend text-success mb-1 d-flex align-items-center">วันนี้
                                    </span>
                                </div>
                            </div>
                            <div class="kpi-icon-box text-primary" style="background-color: #eff6ff;">
                                <i class="fa-solid fa-dice"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: รายได้โทเคนเดือนนี้ -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card bga-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <p class="kpi-label mb-1">รายได้โทเคนเดือนนี้</p>
                                <div class="d-flex align-items-end gap-2">
                                    <h2 class="kpi-value mb-0" style="color: #16a34a;">{{ report.revenue_month.toLocaleString() }}</h2>
                                    <i class="fa-solid fa-coins text-warning mb-2" style="font-size: 14px;"></i>
                                </div>
                            </div>
                            <div class="kpi-icon-box text-success border border-success border-opacity-25" style="background-color: #f0fdf4;">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: บอร์ดเกมที่กำลังถูกเช่า -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card bga-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <p class="kpi-label mb-1">บอร์ดเกมที่กำลังถูกเช่า</p>
                                <div class="d-flex align-items-end gap-2">
                                    <h2 class="kpi-value mb-0 text-dark">{{ report.active_rentals }}</h2>
                                    <span class="text-muted fw-bold mb-1" style="font-size: 12px;">รายการ</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box text-warning" style="background-color: #fff7ed;">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: ผู้ใช้ที่เลยกำหนดคืน -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card bga-card h-100">
                        <div class="card-body p-4 d-flex align-items-center justify-content-between">
                            <div>
                                <p class="kpi-label mb-1">ผู้ใช้ที่เลยกำหนดคืน</p>
                                <div class="d-flex align-items-end gap-2">
                                    <h2 class="kpi-value mb-0 text-danger">{{ report.overdue_rentals }}</h2>
                                    <span class="text-danger fw-bold mb-1" style="font-size: 12px;">คน</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box text-danger border border-danger border-opacity-25 pulse-animation" style="background-color: #fef2f2;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- พื้นที่สำหรับกราฟและตาราง (เตรียมไว้สำหรับสเต็ปถัดไป) -->
            <div class="card bga-card p-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold mb-0">ยอดเช่ารายวัน</h5><span class="text-muted small">ย้อนหลัง 7 วัน</span></div>
                <div class="chart-wrap" v-if="report.daily_rentals.length">
                    <div v-for="day in report.daily_rentals" :key="day.label" class="chart-col"><span class="chart-value">{{ day.count }}</span><div class="chart-bar" :style="{height: barHeight(day.count) + '%'}"></div><small>{{ day.label }}</small></div>
                </div>
            </div>
            <div class="card bga-card p-4 mt-4">
                <h5 class="fw-bold mb-3">เกมยอดนิยม</h5>
                <div v-if="report.popular_games.length" class="table-responsive">
                    <table class="table align-middle mb-0"><thead><tr><th>เกม</th><th class="text-end">จำนวนครั้งที่เช่า</th></tr></thead>
                    <tbody><tr v-for="item in report.popular_games" :key="item.Bg_id"><td>{{ item.boardgame?.Bg_name || 'ไม่พบข้อมูล' }}</td><td class="text-end fw-bold text-success">{{ item.rental_count }}</td></tr></tbody></table>
                </div><p v-else class="text-muted mb-0">ยังไม่มีข้อมูลการเช่า</p>
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
    data() { return { report: { rentals_today: 0, revenue_month: 0, active_rentals: 0, overdue_rentals: 0, popular_games: [], daily_rentals: [] } }; },
    mounted() { this.fetchReport(); },
    methods: { async fetchReport() { try { const response = await window.axios.get('/api/reports/dashboard'); this.report = response.data; } catch (error) { console.error('โหลดรายงานไม่สำเร็จ', error); } }, barHeight(value) { const max=Math.max(...this.report.daily_rentals.map(item=>item.count),1); return Math.max((value/max)*100, value ? 8 : 2); } }
}
</script>

<style scoped>
/* การตั้งค่าโครงสร้างการ์ด */
.bga-card {
    border-radius: 1rem;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s ease-in-out;
}
.bga-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* การจัดการตัวอักษร */
.kpi-label {
    font-size: 0.75rem; /* 12px */
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.kpi-value {
    font-size: 1.875rem; /* 30px */
    font-weight: 900;
    line-height: 1;
}
.kpi-trend {
    font-size: 0.75rem;
    font-weight: 700;
}
.chart-wrap { height: 220px; display:flex; align-items:flex-end; gap:clamp(8px,3vw,28px); padding:20px 12px 0; border-bottom:1px solid #e2e8f0; }
.chart-col { flex:1; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; gap:7px; color:#64748b; font-size:11px; }
.chart-bar { width:min(42px,70%); min-height:4px; border-radius:8px 8px 0 0; background:linear-gradient(180deg,#22c55e,#15803d); transition:height .4s ease; }
.chart-value { font-weight:700; color:#15803d; min-height:16px; }

/* ไอคอนวงกลม */
.kpi-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

/* แอนิเมชันเมื่อโหลดหน้า (Fade in) */
.fade-in-section {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* แอนิเมชันกระพริบแจ้งเตือน (Pulse) สำหรับกล่องสีแดง */
.pulse-animation {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>
