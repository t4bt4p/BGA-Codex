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
                
                <!-- ถ้ารหัสผิด ให้แสดงแจ้งเตือนตัวแดงๆ แทรกไว้ตรงนี้ได้เลยครับ -->
                <div v-if="errorMessage" class="alert alert-danger small p-2 mb-3 text-center">
                    {{ errorMessage }}
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">รหัสผ่าน</label>
                    <input type="password" class="form-control p-2" v-model="form.password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold py-2 rounded-3">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>
</template>

<script>
// นำเข้า axios เพื่อใช้ยิง API
import axios from 'axios';

export default {
    data() {
        return {
            form: {
                username: '', // เปลี่ยนจาก email เป็น username ให้ตรงกับหลังบ้าน
                password: ''
            },
            errorMessage: '' // เอาไว้แสดงข้อความถ้ารหัสผิด
        }
    },
    methods: {
        async handleLogin() {
            try {
                // ล้างข้อความแจ้งเตือนเก่าก่อน
                this.errorMessage = '';

                // 1. ส่งข้อมูล username และ password ไปที่ API /api/login
                const response = await axios.post('/api/login', {
                    username: this.form.username,
                    password: this.form.password
                });

                // 2. ถ้าสำเร็จ API จะตอบกลับมาพร้อม Token เราก็เก็บลง localStorage
                if (response.data.status === 'success') {
                    localStorage.setItem('admin_token', response.data.token);
                    
                    // 3. พาผู้ใช้วาร์ปไปหน้า Dashboard แอดมิน
                    this.$router.push({ name: 'admin.dashboard' });
                }
            } catch (error) {
                // 4. ถ้า API ตอบกลับมาเป็น Error (เช่น รหัสผิด 401)
                if (error.response && error.response.status === 401) {
                    this.errorMessage = 'ชื่อผู้ใช้ หรือ รหัสผ่าน ไม่ถูกต้องครับ';
                } else {
                    this.errorMessage = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์';
                }
                console.error(error);
            }
        }
    }
}
</script>