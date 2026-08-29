<template>
    <div class="d-flex justify-content-center align-items-center vh-100 bg-light">
        <div class="card border-0 shadow-sm rounded-4 p-4" style="width: 100%; max-width: 400px;">
            <div class="text-center mb-4">
                <div class="bg-success text-white rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 60px; height: 60px;">
                    <i class="fa-solid fa-lock fs-3"></i>
                </div>
                <h4 class="fw-bold">เข้าสู่ระบบแอดมิน</h4>
                <p class="text-muted small">ระบบจัดการบอร์ดเกม (Admin Panel)</p>
            </div>

            <form @submit.prevent="handleLogin">
                <div class="mb-3">
                    <label class="form-label small fw-bold">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" class="form-control p-2" v-model="form.username" placeholder="กรอกชื่อผู้ใช้แอดมิน" required>
                </div>
                
                <!-- ถ้ารหัสผิด ให้แสดงแจ้งเตือนตัวแดงๆ แทรกไว้ตรงนี้ -->
                <div v-if="errorMessage" class="alert alert-danger small p-2 mb-3 text-center border-0 rounded-3">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ errorMessage }}
                </div>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold">รหัสผ่าน</label>
                    <input type="password" class="form-control p-2" v-model="form.password" placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="btn btn-success w-100 fw-bold py-2 rounded-3 shadow-sm">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            form: {
                username: '',
                password: ''
            },
            errorMessage: '' 
        }
    },
    methods: {
        async handleLogin() {
            try {
                // ล้างข้อความแจ้งเตือนเก่าก่อน
                this.errorMessage = '';

                // 1. ส่งข้อมูลไปที่ API แอดมิน
                const response = await axios.post('/api/admin/login', {
                    username: this.form.username,
                    password: this.form.password
                });

                // 2. ถ้าสำเร็จ เก็บ Token แล้ววาร์ปไปหน้า Dashboard
                if (response.data.status === 'success') {
                    localStorage.setItem('admin_token', response.data.token);
                    localStorage.removeItem('user_token');
                    
                    window.Swal.fire({
                        icon: 'success',
                        title: 'ยินดีต้อนรับแอดมิน',
                        showConfirmButton: false,
                        timer: 1500
                    });

                    this.$router.push({ name: 'admin.dashboard' });
                }
            } catch (error) {
                // 3. ดักจับ Error 422 และ 401 เพื่อโชว์ข้อความตัวแดง (โดยไม่โดนเตะไปหน้าอื่น)
                if (error.response && (error.response.status === 401 || error.response.status === 422)) {
                    this.errorMessage = error.response.data.message || 'ชื่อผู้ใช้ หรือ รหัสผ่าน ไม่ถูกต้องครับ';
                } else {
                    this.errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์';
                }
                console.error(error);
            }
        }
    }
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');
* { font-family: 'Sarabun', sans-serif; }
</style>
