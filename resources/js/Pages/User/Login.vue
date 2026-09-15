<template>
    <div class="user-auth">
        <!-- Mobile Container Wrapper -->
        <div class="user-auth-card bg-white position-relative d-flex flex-column shadow-lg">
            
            <!-- Header Graphic (พื้นหลังสีเขียว) -->
            <div class="auth-visual position-relative d-flex flex-column align-items-center justify-content-center shadow-sm" style="height: 220px; background: linear-gradient(135deg, #16a34a 0%, #14532d 100%); border-radius: 0 0 40px 40px; overflow: hidden;">
                <i class="fa-solid fa-dice-d20 position-absolute text-white opacity-10" style="font-size: 8rem; right: -20px; bottom: -20px; transform: rotate(12deg);"></i>
                <i class="fa-solid fa-chess-knight position-absolute text-white opacity-10" style="font-size: 5rem; left: -10px; top: 10px; transform: rotate(-12deg);"></i>
                
                <div class="bg-white p-2 rounded-4 shadow mb-2 position-relative" style="transform: rotate(-3deg); z-index: 10;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/Meeple_blue.svg/512px-Meeple_blue.svg.png" style="width: 35px; height: 35px; filter: brightness(0) sepia(1) hue-rotate(90deg) saturate(500%);">
                </div>
                <h2 class="fw-bold text-white mb-0 position-relative" style="z-index: 10; letter-spacing: 1px;">BGA<span style="color: #86efac;">Vault</span></h2>
                <p class="text-white-50 small mb-0 position-relative fw-medium" style="z-index: 10; font-size: 11px;">E-Wallet for Board Game Rentals</p>
                <p class="auth-copy">เกมสนุก ๆ และช่วงเวลาดี ๆ<br>เริ่มต้นด้วยบอร์ดเกมที่คุณเลือก</p>
            </div>

            <!-- Auth Tabs (แท็บสลับหน้า) -->
            <div class="auth-tabs d-flex mt-4 bg-light rounded-4 p-1 shadow-sm">
                <button class="btn bg-white text-success fw-bold flex-grow-1 rounded-3 shadow-sm py-2 border-0" style="font-size: 14px;">เข้าสู่ระบบ</button>
                <!-- 🎯 จุดที่แก้ไข: เปลี่ยนเป็น user.register -->
                <router-link :to="{ name: 'user.register' }" class="btn text-muted fw-bold flex-grow-1 rounded-3 py-2 text-decoration-none border-0" style="font-size: 14px;">สมัครสมาชิก</router-link>
            </div>

            <!-- Form -->
            <div class="p-4 d-flex flex-column gap-3 fade-in pb-5">
                <form @submit.prevent="handleLogin">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">ชื่อผู้ใช้งาน</label>
                        <div class="position-relative">
                            <input type="text" v-model="form.username" class="form-control bg-light border-0 rounded-4 px-4 py-3" placeholder="Username" required style="font-size: 14px;">
                            <i class="fa-solid fa-user position-absolute text-muted" style="right: 16px; top: 18px;"></i>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">รหัสผ่าน</label>
                        <div class="position-relative">
                            <input :type="showPassword ? 'text' : 'password'" v-model="form.password" class="form-control bg-light border-0 rounded-4 px-4 py-3 pe-5" placeholder="••••••••" required style="font-size: 14px;">
                            <button type="button" class="password-toggle" :aria-label="showPassword ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'" @click="showPassword = !showPassword">
                                <i :class="showPassword ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="errorMessage" class="alert alert-danger small p-2 text-center rounded-3 mb-3 border-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ errorMessage }}
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold rounded-4 py-3 shadow-sm" :disabled="isSubmitting" style="font-size: 14px;">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ isSubmitting ? 'กำลังเข้าสู่ระบบ...' : 'เข้าสู่ระบบ' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            form: { username: '', password: '' },
            errorMessage: '',
            showPassword: false,
            isSubmitting: false
        }
    },
    methods: {
        async handleLogin() {
            try {
                this.errorMessage = '';
                this.isSubmitting = true;
                const response = await axios.post('/api/login', this.form);

                if (response.data.status === 'success') {
                    // เก็บ Token 
                    localStorage.setItem('user_token', response.data.token);
                    sessionStorage.removeItem('account_suspension');
                    localStorage.removeItem('admin_token');

                    window.Swal.fire({
                        icon: 'success',
                        title: 'เข้าสู่ระบบสำเร็จ!',
                        showConfirmButton: false,
                        timer: 1500
                    });

                    // 🎯 ถ้ารหัสผ่านถูก ให้วาร์ปไปหน้าร้านค้า Mobile UI ทันที
                    this.$router.push({ name: 'user.home' });
                }
            } catch (error) {
                if (error.response?.data?.code === 'account_suspended') return;
                this.errorMessage = 'ชื่อผู้ใช้ หรือ รหัสผ่าน ไม่ถูกต้อง';
            } finally {
                this.isSubmitting = false;
            }
        }
    }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');
.hide-scroll::-webkit-scrollbar { display: none; }
.hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
.fade-in { animation: fadeIn 0.3s ease-out forwards; }
.password-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: #6c757d; width: 32px; height: 32px; display: grid; place-items: center; cursor: pointer; }
.password-toggle:focus-visible { outline: 2px solid #198754; outline-offset: 2px; border-radius: 8px; }
.auth-tabs { width: calc(100% - 32px); max-width: 340px; min-height: 46px; margin-inline: auto; align-self: center; flex: 0 0 auto; }
.auth-tabs .btn { min-height: 38px; padding: 8px 12px !important; line-height: 1.2; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
