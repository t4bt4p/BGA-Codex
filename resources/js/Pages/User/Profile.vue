<template>
    <div class="p-3 fade-in d-flex flex-column gap-3">
        <h6 class="fw-bold mb-1 text-dark">บัญชีของฉัน</h6>
        
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center position-relative">
            <div class="bg-success bg-opacity-25" style="height: 80px;"></div>
            <div class="position-relative mt-n5 mb-3 d-flex justify-content-center">
                <img src="https://i.pravatar.cc/150?img=11" class="rounded-circle border border-4 border-white shadow-sm" style="width: 90px; height: 90px; object-fit: cover;">
            </div>
            <div class="card-body pt-0 pb-4">
                <h5 class="fw-bold mb-2 text-dark">{{ userProfile?.User_name || 'กำลังโหลด...' }}</h5>
                <span class="badge bg-light text-muted border border-light px-3 py-2 rounded-pill fw-medium">
                    ID: {{ userProfile?.User_id }} • @{{ userProfile?.User_username }}
                </span>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="list-group list-group-flush">
                <button @click="editProfile" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 border-0">
                    <span class="fw-bold text-dark" style="font-size: 14px;"><i class="fa-solid fa-user-pen text-success me-3"></i>แก้ไขชื่อและเบอร์โทรศัพท์</span>
                    <i class="fa-solid fa-chevron-right text-muted opacity-25"></i>
                </button>
                <button @click="handleLogout" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3 border-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </div>
                        <span class="fw-bold text-danger" style="font-size: 14px;">ออกจากระบบ</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-muted opacity-25" style="font-size: 12px;"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    props: ['userProfile'], // รับข้อมูลผู้ใช้มาจาก Layout
    methods: {
        async editProfile() {
            const { value } = await window.Swal.fire({
                title: 'แก้ไขข้อมูลส่วนตัว',
                html: '<input id="profile-name" class="swal2-input" placeholder="ชื่อ-นามสกุล"><input id="profile-phone" class="swal2-input" placeholder="เบอร์โทรศัพท์">',
                didOpen: () => {
                    document.getElementById('profile-name').value = this.userProfile?.User_name || '';
                    document.getElementById('profile-phone').value = this.userProfile?.User_phone || '';
                },
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก',
                preConfirm: () => ({
                    User_name: document.getElementById('profile-name').value.trim(),
                    User_phone: document.getElementById('profile-phone').value.trim() || null,
                }),
            });
            if (!value) return;
            try {
                await axios.put('/api/user/profile', value);
                this.$emit('refresh-wallet');
                window.Swal.fire({ icon: 'success', title: 'บันทึกข้อมูลแล้ว', timer: 1500, showConfirmButton: false });
            } catch (error) {
                window.Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: error.response?.data?.message || 'กรุณาตรวจสอบข้อมูล' });
            }
        },
        async handleLogout() {
            try {
                await axios.post('/api/logout');
            } catch (error) {
                console.error(error);
            } finally {
                localStorage.removeItem('user_token');
                this.$router.push({ name: 'user.login' });
            }
        }
    }
}
</script>

<style scoped>
.fade-in { animation: fadeIn 0.3s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.mt-n5 { margin-top: -3rem !important; }
</style>
