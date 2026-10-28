<template>
    <div class="user-auth">
        <!-- Mobile Container Wrapper -->
        <div class="user-auth-card bg-white position-relative d-flex flex-column shadow-lg">
            
            <!-- Header Graphic -->
            <div class="auth-visual position-relative d-flex flex-column align-items-center justify-content-center shadow-sm" style="height: 160px; background: linear-gradient(135deg, #16a34a 0%, #14532d 100%); border-radius: 0 0 40px 40px; overflow: hidden;">
                <i class="fa-solid fa-chess-knight position-absolute text-white opacity-10" style="font-size: 5rem; left: -10px; top: 10px; transform: rotate(-12deg);"></i>
                <h2 class="fw-bold text-white mb-0 position-relative mt-3" style="z-index: 10; letter-spacing: 1px;">BGA<span style="color: #86efac;">Vault</span></h2>
                <p class="auth-copy">สร้างบัญชีของคุณ<br>แล้วค้นพบบอร์ดเกมโปรดเกมถัดไป</p>
            </div>

            <!-- Auth Tabs (แท็บสลับหน้า) -->
            <div class="auth-tabs d-flex mt-4 bg-light rounded-4 p-1 shadow-sm">
                <router-link :to="{ name: 'user.login' }" class="btn text-muted fw-bold flex-grow-1 rounded-3 py-2 text-decoration-none border-0" style="font-size: 14px;">เข้าสู่ระบบ</router-link>
                <button class="btn bg-white text-success fw-bold flex-grow-1 rounded-3 shadow-sm py-2 border-0" style="font-size: 14px;">สมัครสมาชิก</button>
            </div>

            <!-- Form -->
            <div class="p-4 d-flex flex-column gap-3 fade-in pb-5">
                <form @submit.prevent="handleRegister">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">ชื่อ-นามสกุล</label>
                        <input type="text" v-model="form.User_name" class="form-control bg-light border-0 rounded-4 px-4 py-3" placeholder="ชื่อ นามสกุล" required style="font-size: 14px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">ชื่อผู้ใช้งาน (Username)</label>
                        <input type="text" v-model="form.username" class="form-control bg-light border-0 rounded-4 px-4 py-3" placeholder="ใช้สำหรับเข้าสู่ระบบ" required style="font-size: 14px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">เบอร์โทรศัพท์</label>
                        <input type="tel" v-model="form.User_phone" class="form-control bg-light border-0 rounded-4 px-4 py-3" placeholder="099-999-9999" required style="font-size: 14px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">รหัสผ่าน</label>
                        <input type="password" v-model="form.password" class="form-control bg-light border-0 rounded-4 px-4 py-3" placeholder="ตั้งรหัสผ่านอย่างน้อย 6 ตัวอักษร" required minlength="6" style="font-size: 14px;">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted ms-1 mb-1">ยืนยันรหัสผ่าน</label>
                        <input type="password" v-model="form.password_confirmation" class="form-control bg-light border-0 rounded-4 px-4 py-3" placeholder="กรอกรหัสผ่านอีกครั้งให้ตรงกัน" required minlength="6" style="font-size: 14px;">
                    </div>

                    <div v-if="errorMessage" class="alert alert-danger small p-2 text-center rounded-3 mb-3 border-0">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ errorMessage }}
                    </div>

                    <button type="submit" class="btn btn-dark w-100 fw-bold rounded-4 py-3 shadow-sm" style="font-size: 14px;">
                        สร้างบัญชีผู้ใช้ใหม่
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
            form: { User_name: '', username: '', password: '', password_confirmation: '', User_phone: '' },
            errorMessage: ''
        }
    },
    methods: {
        async handleRegister() {
            try {
                this.errorMessage = '';

                if (this.form.password !== this.form.password_confirmation) {
                    this.errorMessage = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน!';
                    return; 
                }

                const response = await axios.post('/api/register', this.form);

                if (response.data.status === 'success') {
                    localStorage.setItem('user_token', response.data.token);
                    sessionStorage.removeItem('account_suspension');
                    localStorage.removeItem('admin_token');

                    window.Swal.fire({
                        icon: 'success',
                        title: 'สร้างบัญชีสำเร็จ!',
                        text: 'ระบบได้เปิดกระเป๋าเงินให้คุณเรียบร้อยแล้ว',
                        showConfirmButton: false,
                        timer: 2000
                    });

                    this.$router.push({ name: 'user.home' });
                }
            } catch (error) {
                if (error.response?.data?.errors) {
                    const errors = error.response.data.errors;
                    this.errorMessage = errors[Object.keys(errors)[0]][0]; 
                } else {
                    this.errorMessage = 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง';
                }
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
.auth-tabs { width: calc(100% - 32px); max-width: 340px; min-height: 46px; margin-inline: auto; align-self: center; flex: 0 0 auto; }
.auth-tabs .btn { min-height: 38px; padding: 8px 12px !important; line-height: 1.2; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
