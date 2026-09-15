<template>
    <AdminLayout>
        <template #header>จัดการบอร์ดเกม (Board Games)</template>

        <div class="fade-in-section">
            <!-- Action Bar -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div class="position-relative w-100" style="max-width: 400px;">
                    <input type="text" class="form-control bga-input pe-5" v-model="searchQuery" placeholder="ค้นหาชื่อบอร์ดเกม...">
                    <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="right: 15px; top: 50%; transform: translateY(-50%);"></i>
                </div>
                <div class="d-flex gap-2 w-100" style="max-width: fit-content;">
                    <select class="form-select bga-select" v-model="selectedCategory" style="min-width: 160px;">
                        <option value="">หมวดหมู่ทั้งหมด</option>
                        <option v-for="cat in categories" :key="cat.Bg_category_id" :value="cat.Bg_category_id">
                            {{ cat.Bg_category_name }}
                        </option>
                    </select>
                    <button @click="openBoardgameModal" class="btn btn-success fw-bold d-flex align-items-center gap-2 bga-btn-success text-nowrap shadow-sm">
                        <i class="fa-solid fa-plus"></i> เพิ่มบอร์ดเกม
                    </button>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="row g-4 mb-4">
                
                <div class="col-12 col-md-6 col-xl-3" v-for="game in filteredBoardgames" :key="game.Bg_id">
                    <div class="card bga-card h-100 overflow-hidden border-0" :class="{ 'disabled-card': game.Bg_use_status === 0 }">
                        <div class="position-relative" style="height: 180px; background-color: #f1f5f9;">
                            
                            <img :src="game.Bg_Image || 'https://images.unsplash.com/photo-1610890716171-6b1bb98ffaed?auto=format&fit=crop&q=80&w=400'" 
                                 class="w-100 h-100 p-3" style="object-fit: contain;" :alt="game.Bg_name">
                            
                            <div class="position-absolute top-0 start-0 m-2">
                                <span v-if="game.Bg_use_status === 1" class="badge bg-white text-success border shadow-sm d-flex align-items-center gap-1 p-2">
                                    <div class="rounded-circle bg-success" style="width: 8px; height: 8px;"></div> พร้อมใช้งาน
                                </span>
                                <span v-else class="badge bg-dark bg-opacity-75 text-white border border-secondary shadow-sm d-flex align-items-center gap-1 p-2">
                                    <i class="fa-solid fa-lock" style="font-size: 10px;"></i> ถูกเช่าอยู่
                                </span>
                            </div>
                            
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-primary text-white shadow-sm p-2" style="font-size: 0.75rem;">
                                    {{ game.category ? game.category.Bg_category_name : 'ไม่มีหมวดหมู่' }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body d-flex flex-column p-4">
                            <div class="mb-3">
                                <h5 class="fw-bold text-dark mb-1">{{ game.Bg_name }}</h5>
                                <p class="text-muted mb-0" style="font-size: 11px; font-family: monospace;">ID: BG-{{ String(game.Bg_id).padStart(3, '0') }}</p>
                            </div>
                            
                            <div class="d-flex align-items-center gap-3 bg-light rounded-3 px-3 py-2 mb-4 text-muted" style="font-size: 12px;">
                                <span class="d-flex align-items-center gap-2 fw-medium">
                                    <i class="fa-solid fa-users"></i> {{ game.Bg_min_player }}-{{ game.Bg_max_player }} คน
                                </span>
                                <div class="vr"></div>
                                <span class="d-flex align-items-center gap-2 fw-medium">
                                    <i class="fa-solid fa-clock"></i> {{ game.Bg_playduration }} นาที
                                </span>
                            </div>
                            
                            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                                <div class="text-success fw-bold fs-5 d-flex align-items-center gap-1">
                                    {{ game.Bg_cost }} บาท
                                </div>
                                <div class="d-flex gap-2">
                                    <!-- แก้ไขได้เมื่อเกมว่าง -->
                                    <button @click="openEditModal(game)" :disabled="Number(game.Bg_use_status) === 0" class="btn btn-sm btn-secondary shadow-sm px-3 fw-bold">
                                        แก้ไข
                                    </button>
                                    <!-- 📌 ปุ่มลบ: จะถูกปิดใช้งาน (disabled) เมื่อเกมถูกเช่าอยู่ -->
                                    <button @click="deleteBoardgame(game.Bg_id)" 
                                            class="btn btn-sm btn-danger shadow-sm px-3 fw-bold"
                                            :disabled="game.Bg_use_status === 0">
                                        ลบ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-if="filteredBoardgames.length === 0" class="col-12 text-center py-5 text-muted">
                    <i class="fa-solid fa-search fs-1 mb-3 text-light"></i>
                    <p>ไม่พบข้อมูลบอร์ดเกมที่ตรงกับเงื่อนไข</p>
                </div>

            </div>

            <!-- Pagination -->
            <div v-if="filteredBoardgames.length > 0" class="d-flex justify-content-between align-items-center bg-white p-3 rounded-4 shadow-sm border border-light mt-4">
                <span class="text-muted small">แสดงทั้งหมด {{ filteredBoardgames.length }} รายการ</span>
                <div class="btn-group shadow-sm">
                    <button class="btn btn-light btn-sm text-muted disabled">ก่อนหน้า</button>
                    <button class="btn btn-success btn-sm fw-bold" style="background-color: #16a34a; border-color: #16a34a;">1</button>
                    <button class="btn btn-light btn-sm text-dark">ถัดไป</button>
                </div>
            </div>

        </div>

        <!-- Modal หลัก: สำหรับเพิ่ม/แก้ไขบอร์ดเกม -->
        <div class="modal fade" id="addBoardgameModal" tabindex="-1" aria-hidden="true" ref="addBoardgameModal">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                        <h4 class="fw-bold mb-0">
                            <i class="fa-solid fa-dice me-2 text-success"></i> 
                            {{ isEditMode ? 'แก้ไขข้อมูลบอร์ดเกม' : 'เพิ่มข้อมูลบอร์ดเกม' }}
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form @submit.prevent="submitBoardgame">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">ชื่อบอร์ดเกม (Bg_name)</label>
                                <input type="text" class="form-control bga-input" v-model="form.Bg_name" required placeholder="เช่น Catan">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">ราคาเช่า/ค่าบริการ (Bg_cost)</label>
                                <input type="text" class="form-control bga-input" v-model="form.Bg_cost" required placeholder="เช่น 50">
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold small text-muted">ผู้เล่นขั้นต่ำ</label>
                                    <input type="number" class="form-control bga-input" v-model="form.Bg_min_player" required min="1">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold small text-muted">ผู้เล่นสูงสุด</label>
                                    <input type="number" class="form-control bga-input" v-model="form.Bg_max_player" required min="1">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold small text-muted">เวลาเล่น (นาที)</label>
                                    <input type="number" class="form-control bga-input" v-model="form.Bg_playduration" required min="1">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">หมวดหมู่บอร์ดเกม</label>
                                <div class="input-group shadow-sm rounded-3">
                                    <select class="form-select bga-select border-0" v-model="form.Bg_Catetogory_id" required>
                                        <option value="" disabled>-- เลือกหมวดหมู่บอร์ดเกม --</option>
                                        <option v-for="cat in categories" :key="cat.Bg_category_id" :value="cat.Bg_category_id">
                                            {{ cat.Bg_category_name }}
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-success fw-bold px-3" @click="openCategoryModal">
                                        <i class="fa-solid fa-plus"></i> เพิ่มใหม่
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-muted">รูปภาพบอร์ดเกม (Bg_Image)</label>
                                <input type="file" class="form-control bga-input" accept="image/*" @change="handleImageUpload" ref="imageInput">
                                
                                <div v-if="imagePreview" class="mt-3">
                                    <p class="small text-muted mb-1">ตัวอย่างรูปภาพ:</p>
                                    <img :src="imagePreview" alt="Preview" class="rounded-3 shadow-sm border" style="max-height: 120px; object-fit: contain;">
                                </div>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                                <button type="submit" class="btn btn-success bga-btn-success py-2 px-4 fw-bold shadow-sm">
                                    บันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal ย่อย: สำหรับเพิ่มหมวดหมู่ใหม่ -->
        <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true" ref="categoryModal" style="z-index: 1060;">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="fw-bold mb-0">เพิ่มหมวดหมู่ใหม่</h6>
                        <button type="button" class="btn-close" @click="closeCategoryModal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" class="form-control bga-input" v-model="newCategoryName" placeholder="เช่น Party, Family">
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light btn-sm w-100 mb-2 fw-bold" @click="closeCategoryModal">ยกเลิก</button>
                        <button type="button" class="btn btn-success btn-sm w-100 fw-bold m-0" @click="storeCategory">บันทึกหมวดหมู่</button>
                    </div>
                </div>
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
    data() {
        return {
            searchQuery: '',
            selectedCategory: '',
            categories: [], 
            boardgames: [],
            newCategoryName: '', 
            boardgameModalInstance: null,
            categoryModalInstance: null,
            isEditMode: false,
            editId: null,
            
            imageFile: null,
            imagePreview: null,

            form: {
                Bg_name: '',
                Bg_cost: '',
                Bg_min_player: 1,
                Bg_max_player: 4,
                Bg_playduration: 60,
                Bg_Catetogory_id: '',
                Bg_Image: '' 
            }
        };
    },
    computed: {
        filteredBoardgames() {
            return this.boardgames.filter(game => {
                const matchName = game.Bg_name.toLowerCase().includes(this.searchQuery.toLowerCase());
                const matchCategory = this.selectedCategory === '' || game.Bg_Catetogory_id === this.selectedCategory;
                return matchName && matchCategory;
            });
        }
    },
    async mounted() {
        await this.fetchCategories();
        await this.fetchBoardgames();
        
        this.boardgameModalInstance = new window.bootstrap.Modal(this.$refs.addBoardgameModal);
        this.categoryModalInstance = new window.bootstrap.Modal(this.$refs.categoryModal);
        window.Echo.channel('boardgames')
            .listen('.boardgame.status.changed', this.applyRealtimeStatus);
    },
    beforeUnmount() {
        window.Echo.leave('boardgames');
    },
    methods: {
        applyRealtimeStatus(event) {
            const game = this.boardgames.find(item => Number(item.Bg_id) === Number(event.Bg_id));
            if (!game) {
                this.fetchBoardgames();
                return;
            }
            game.Bg_use_status = Number(event.Bg_use_status);
            game.updated_at = event.updated_at;
        },
        async fetchBoardgames() {
            try {
                const response = await window.axios.get('/api/boardgames');
                this.boardgames = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดข้อมูลบอร์ดเกมได้', error);
            }
        },

        async fetchCategories() {
            try {
                const response = await window.axios.get('/api/categories');
                this.categories = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดหมวดหมู่ได้', error);
            }
        },

        handleImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                this.imageFile = file; 
                this.imagePreview = URL.createObjectURL(file); 
            }
        },

        openBoardgameModal() {
            this.isEditMode = false;
            this.editId = null;
            
            this.imageFile = null;
            this.imagePreview = null;
            if(this.$refs.imageInput) this.$refs.imageInput.value = ''; 

            this.form = {
                Bg_name: '', Bg_cost: '', Bg_min_player: 1, 
                Bg_max_player: 4, Bg_playduration: 60, Bg_Catetogory_id: '', Bg_Image: ''
            };
            this.boardgameModalInstance.show();
        },

        openEditModal(game) {
            if (Number(game.Bg_use_status) === 0) return;
            this.isEditMode = true;
            this.editId = game.Bg_id;
            
            this.imageFile = null;
            this.imagePreview = game.Bg_Image || null;
            if(this.$refs.imageInput) this.$refs.imageInput.value = ''; 

            this.form = {
                Bg_name: String(game.Bg_name),
                Bg_cost: String(game.Bg_cost),
                Bg_min_player: game.Bg_min_player,
                Bg_max_player: game.Bg_max_player,
                Bg_playduration: game.Bg_playduration,
                Bg_Catetogory_id: game.Bg_Catetogory_id,
                Bg_Image: game.Bg_Image || ''
            };
            this.boardgameModalInstance.show();
        },
        
        openCategoryModal() {
            this.newCategoryName = ''; 
            this.categoryModalInstance.show();
        },

        closeCategoryModal() {
            this.categoryModalInstance.hide();
        },

        async storeCategory() {
            if (!this.newCategoryName.trim()) return;
            try {
                const response = await window.axios.post('/api/categories', {
                    Bg_category_name: this.newCategoryName
                });
                await this.fetchCategories();
                this.form.Bg_Catetogory_id = response.data.data.Bg_category_id;
                this.closeCategoryModal();
            } catch (error) {
                window.Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด' });
            }
        },

        async submitBoardgame() {
            try {
                let formData = new FormData();
                formData.append('Bg_name', this.form.Bg_name);
                formData.append('Bg_cost', this.form.Bg_cost);
                formData.append('Bg_min_player', this.form.Bg_min_player);
                formData.append('Bg_max_player', this.form.Bg_max_player);
                formData.append('Bg_playduration', this.form.Bg_playduration);
                formData.append('Bg_Catetogory_id', this.form.Bg_Catetogory_id);

                if (this.imageFile) {
                    formData.append('Bg_Image', this.imageFile);
                }

                if (this.isEditMode) {
                    formData.append('_method', 'PUT');
                    await window.axios.post(`/api/boardgames/${this.editId}`, formData, {
                        headers: { 'Content-Type': 'multipart/form-data' } 
                    });
                    window.Swal.fire({ icon: 'success', title: 'อัปเดตสำเร็จ!', showConfirmButton: false, timer: 1500 });
                } else {
                    await window.axios.post('/api/boardgames', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });
                    window.Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ!', showConfirmButton: false, timer: 1500 });
                }
                
                await this.fetchBoardgames();
                this.boardgameModalInstance.hide();
                
            } catch (error) {
                console.error('บันทึกไม่สำเร็จ', error);
                
                let errorMessage = 'กรุณาตรวจสอบการกรอกข้อมูลอีกครั้ง';
                if (error.response && error.response.data && error.response.data.errors) {
                    const errors = error.response.data.errors;
                    errorMessage = errors[Object.keys(errors)[0]][0]; 
                } else if (error.response && error.response.data && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                window.Swal.fire({
                    icon: 'warning', 
                    title: 'เกิดข้อผิดพลาด!', 
                    text: errorMessage,
                    confirmButtonColor: '#16a34a'
                });
            }
        },

        async deleteBoardgame(id) {
            const result = await window.Swal.fire({
                title: 'ยืนยันการลบข้อมูล?',
                text: "หากลบแล้วจะไม่สามารถกู้คืนข้อมูลนี้ได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fa-solid fa-trash me-1"></i> ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            });

            if (result.isConfirmed) {
                try {
                    await window.axios.delete(`/api/boardgames/${id}`);
                    window.Swal.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ!', text: 'เกมถูกนำออกจากรายการแล้ว', showConfirmButton: false, timer: 1500 });
                    await this.fetchBoardgames(); 
                } catch (error) {
                    window.Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: error.response?.data?.message || 'ไม่สามารถลบข้อมูลบอร์ดเกมได้' });
                }
            }
        }
    }
}
</script>

<style scoped>
.bga-card {
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s ease-in-out, transform 0.2s ease;
}

.bga-card:hover:not(.disabled-card) {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    transform: translateY(-4px);
}

/* 📌 CSS สำหรับทำให้เนื้อหาของการ์ดเกมที่ถูกเช่าอยู่ดูซีดลง แต่ยังกดปุ่มได้ */
.disabled-card {
    background-color: #f8f9fa !important;
}

/* 📌 ดรอปสีของรูปภาพ, ป้าย, และข้อความต่างๆ ลง 85% */
.disabled-card img, 
.disabled-card .badge, 
.disabled-card h5,
.disabled-card p.text-muted,
.disabled-card .d-flex.align-items-center.text-muted {
    opacity: 0.6;
    filter: grayscale(85%);
    transition: all 0.3s ease;
}

.bga-input, .bga-select {
    border-radius: 0.75rem;
    padding-top: 0.6rem;
    padding-bottom: 0.6rem;
    border-color: #e2e8f0;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.bga-input:focus, .bga-select:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 0.25rem rgba(22, 163, 74, 0.25);
}
.bga-btn-success {
    background-color: #16a34a;
    border-color: #16a34a;
    border-radius: 0.75rem;
    transition: background-color 0.2s, transform 0.1s;
}
.bga-btn-success:hover {
    background-color: #15803d;
}
.bga-btn-success:active {
    transform: scale(0.98);
}
.fade-in-section {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
    transform: scale(0.95);
}
.modal.show .modal-dialog {
    transform: scale(1);
}
</style>
