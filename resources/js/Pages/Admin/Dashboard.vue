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
                                    <h2 class="kpi-value mb-0 text-dark">42</h2>
                                    <span class="kpi-trend text-success mb-1 d-flex align-items-center">
                                        <i class="fa-solid fa-arrow-trend-up me-1"></i> +12%
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
                                    <h2 class="kpi-value mb-0" style="color: #16a34a;">18.5k</h2>
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
                                    <h2 class="kpi-value mb-0 text-dark">128</h2>
                                    <span class="text-muted fw-bold mb-1" style="font-size: 12px;">/ 350 กล่อง</span>
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
                                    <h2 class="kpi-value mb-0 text-danger">5</h2>
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
            <div style="height: 50vh;"></div> 

        </div>
    </AdminLayout>
</template>

<script>
import AdminLayout from '../../Layouts/AdminLayout.vue';

export default {
    components: {
        AdminLayout
    }
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