<template>
    <div class="user-app">
        <!-- Mobile Container Wrapper -->
        <div class="user-shell bg-light position-relative overflow-hidden">

            <!-- Header (Top Bar) -->
            <header
                class="user-header bg-white d-flex align-items-center justify-content-between px-3 py-3 border-bottom"
                style="z-index: 1030;">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-success rounded text-white d-flex align-items-center justify-content-center"
                        style="width: 28px; height: 28px;">
                        <i class="fa-solid fa-dice small"></i>
                    </div>
                    <span class="fw-bold fs-5 mb-0 text-dark" style="letter-spacing: -0.5px;">BGA<span
                            class="text-success">Vault</span></span>
                </div>

                <div class="desktop-header-copy d-none d-lg-flex flex-column">
                    <span>พื้นที่สมาชิก</span>
                    <strong>{{ pageTitle }}</strong>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <button v-if="userProfile" class="desktop-action btn btn-success rounded-pill px-4" @click="openTopupModal">เติมโทเคน</button>
                    <router-link v-else class="desktop-action btn btn-success rounded-pill px-4" :to="{ name: 'user.login' }">เข้าสู่ระบบ</router-link>
                    <div class="topbar-wallet badge rounded-pill bg-success bg-opacity-10 text-success border border-success d-flex align-items-center gap-2 px-3 py-1">
                        <!-- 🎯 ยอดเงินจะเปลี่ยนตรงนี้ทันทีแบบ Real-time -->
                        <i class="fa-solid fa-coins" aria-hidden="true"></i>
                        <span class="fw-bold fs-6">{{ Number(userProfile?.wallet?.Wallet_count || 0).toLocaleString() }}</span>
                        <small>โทเคน</small>
                        <div class="bg-success rounded-circle" style="width: 8px; height: 8px;"></div>
                    </div>
                    <router-link class="desktop-profile-link" :to="{ name: 'user.profile' }" aria-label="บัญชีของฉัน">
                        <span class="desktop-profile-name d-none d-xl-flex" v-if="userProfile">
                            <strong>{{ userProfile.User_name }}</strong><small>บัญชีของฉัน</small>
                        </span>
                        <img src="https://i.pravatar.cc/150?img=11" alt="รูปโปรไฟล์"
                            class="rounded-circle border border-2 border-white shadow-sm"
                            style="width: 36px; height: 36px; object-fit: cover;">
                    </router-link>
                </div>
            </header>

            <aside class="user-sidebar">
                <p class="sidebar-label">พื้นที่ของคุณ</p>
                <nav aria-label="เมนูผู้ใช้งาน">
                    <router-link :to="{ name: 'user.home' }" exact-active-class="selected"><i class="fa-solid fa-house"></i> ค้นหาบอร์ดเกม</router-link>
                    <router-link :to="{ name: 'user.mygames' }" exact-active-class="selected"><i class="fa-solid fa-box-open"></i> เกมของฉัน</router-link>
                    <router-link :to="{ name: 'user.history' }" exact-active-class="selected"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติธุรกรรม</router-link>
                    <router-link :to="{ name: 'user.profile' }" exact-active-class="selected"><i class="fa-solid fa-user"></i> บัญชีของฉัน</router-link>
                </nav>
                <div class="sidebar-wallet">
                    <i class="fa-solid fa-wallet text-success mb-3" aria-hidden="true"></i>
                    <p>{{ userProfile ? 'โทเคนพร้อมใช้' : 'พร้อมเริ่มเกมถัดไปหรือยัง?' }}</p>
                    <strong v-if="userProfile">{{ Number(userProfile.wallet?.Wallet_count || 0).toLocaleString() }} <small>โทเคน</small></strong>
                    <p v-else class="small">สมัครสมาชิกเพื่อเช่าบอร์ดเกมและจัดการกระเป๋าเงินของคุณ</p>
                    <button v-if="userProfile" class="btn btn-success w-100 mt-3" @click="openTopupModal">เติมโทเคน</button>
                    <router-link v-else :to="{ name: 'user.register' }" class="btn btn-success w-100 mt-2">สมัครสมาชิก</router-link>
                </div>
            </aside>

            <!-- 🎯 Main Content Area (หน้า Home หรือ Profile จะมาโผล่ตรงนี้) -->
            <main class="user-main overflow-auto position-relative">
                <div class="user-content">
                <router-view :userProfile="userProfile" @refresh-wallet="fetchUserProfile"></router-view>
                </div>
            </main>

           <!-- Bottom Navigation -->
            <nav aria-label="เมนูมือถือ" class="user-bottom-nav position-absolute bottom-0 w-100 bg-white border-top d-flex justify-content-around align-items-center pt-2 px-2 shadow-lg"
                style="z-index: 1040; padding-bottom: 24px;">

                <!-- 1. หน้าแรก -->
                <router-link :to="{ name: 'user.home' }"
                    class="btn btn-link text-decoration-none d-flex flex-column align-items-center gap-1 p-2 transition-colors"
                    style="width: 64px;"
                    :class="$route.name === 'user.home' ? 'text-success' : 'text-muted'">
                    <i class="fa-solid fa-house fs-5"></i>
                    <span class="fw-bold" style="font-size: 10px;">หน้าแรก</span>
                </router-link>

                <!-- 2. เกมของฉัน -->
                <router-link :to="{ name: 'user.mygames' }"
                    class="btn btn-link text-decoration-none d-flex flex-column align-items-center gap-1 p-2 transition-colors"
                    style="width: 64px;"
                    :class="$route.name === 'user.mygames' ? 'text-success' : 'text-muted'">
                    <i class="fa-solid fa-box-open fs-5"></i>
                    <span class="fw-bold" style="font-size: 10px;">เกมของฉัน</span>
                </router-link>

                <!-- 3. ปุ่ม QR Code ตรงกลาง (Gradient) -->
                <div class="position-relative" style="top: -28px;">
                    <button @click="openTopupModal" aria-label="เติมโทเคน"
                        class="btn rounded-circle shadow-lg d-flex align-items-center justify-content-center transition-transform"
                        style="width: 64px; height: 64px; background: linear-gradient(to top right, #22c55e, #16a34a); border: 4px solid white;">
                        <i class="fa-solid fa-qrcode text-white fs-3"></i>
                    </button>
                </div>

                <!-- 4. ประวัติ -->
                <router-link :to="{ name: 'user.history' }"
                    class="btn btn-link text-decoration-none d-flex flex-column align-items-center gap-1 p-2 transition-colors"
                    style="width: 64px;"
                    :class="$route.name === 'user.history' ? 'text-success' : 'text-muted'">
                    <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                    <span class="fw-bold" style="font-size: 10px;">ประวัติ</span>
                </router-link>

                <!-- 5. ฉัน (Profile) -->
                <router-link :to="{ name: 'user.profile' }"
                    class="btn btn-link text-decoration-none d-flex flex-column align-items-center gap-1 p-2 transition-colors"
                    style="width: 64px;"
                    :class="$route.name === 'user.profile' ? 'text-success' : 'text-muted'">
                    <i class="fa-solid fa-user fs-5"></i>
                    <span class="fw-bold" style="font-size: 10px;">ฉัน</span>
                </router-link>

            </nav>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import QRCode from 'qrcode';

export default {
    name: 'UserLayout',
    data() {
        return {
            userProfile: null,
            accountCheckTimer: null
        }
    },
    computed: {
        pageTitle() {
            return {
                'user.home': 'ค้นหาบอร์ดเกม',
                'user.mygames': 'เกมของฉัน',
                'user.history': 'ประวัติธุรกรรม',
                'user.profile': 'บัญชีของฉัน',
            }[this.$route.name] || 'BGA Vault';
        },
    },
    async mounted() {
        await this.fetchUserProfile();
        // Wallet changes are refreshed after payments; use a slower health check to avoid needless API traffic.
        this.accountCheckTimer = window.setInterval(() => this.fetchUserProfile(), 60000);
    },
    beforeUnmount() {
        window.clearInterval(this.accountCheckTimer);
    },
    methods: {
        async fetchUserProfile() {
            if (!localStorage.getItem('user_token')) return;
            try {
                const response = await axios.get('/api/user');
                this.userProfile = response.data;
            } catch (error) {
                if (error.response?.data?.code === 'account_suspended') return;
                if (error.response?.status !== 401) return;
                localStorage.removeItem('user_token');
                // หน้าแรกดูรายการเกมได้โดยไม่ต้องล็อกอิน; หน้าอื่นให้ route guard จัดการ
                if (this.$route.name !== 'user.home') {
                    this.$router.push({ name: 'user.login' });
                }
            }
        },

        // สร้างคำขอเติมเงินและแสดง QR ตามยอดที่เลือก
        openTopupModal() {
            if (!localStorage.getItem('user_token')) {
                this.$router.push({ name: 'user.login' });
                return;
            }
            const amounts = [20, 50, 100, 500, 1000];
            let topupPoller = null;
            window.Swal.fire({
                title: 'เติมโทเคน',
                width: '390px',
                padding: '1rem',
                html: `
                    <div class="text-start rounded-3 bg-light px-3 py-2 mb-3 d-flex justify-content-between">
                        <span class="text-muted">ยอดคงเหลือปัจจุบัน</span><strong>${(this.userProfile?.wallet?.Wallet_count || 0).toLocaleString()} <span class="text-success">◉</span></strong>
                    </div>
                    <p class="text-start fw-bold mb-2">ยอดคงเหลือปัจจุบัน</p>
                    <div class="topup-grid mb-3">
                        ${amounts.map((amount, i) => `
                            <label class="topup-option ${i === 2 ? 'selected' : ''}" data-amount="${amount}">
                                <input type="radio" name="topup_amount" value="${amount}" ${i === 2 ? 'checked' : ''}>
                                <strong>+ ${amount.toLocaleString()}</strong><small>฿${amount.toLocaleString()}</small>
                            </label>
                        `).join('')}
                    </div>
                    <button id="create-topup-qr" type="button" class="btn btn-success w-100 mb-3">สร้าง QR สำหรับชำระเงิน</button>
                    <div id="topup-qr-panel" class="qr-panel" style="display:none">
                        <p class="text-muted small mb-2">สแกน QR Code เพื่อชำระเงิน <strong id="qr-amount" class="text-success">100</strong> บาท</p>
                        <img id="topup-qr" alt="PromptPay QR" style="width:180px;height:180px;background:#fff;padding:10px;border-radius:16px;">
                        <button id="download-topup-qr" type="button" class="btn btn-link text-success fw-bold text-decoration-none mt-2">⇩ บันทึกรูปภาพ</button>
                    </div>
                `,
                showConfirmButton: false,
                showCloseButton: true,
                showCancelButton: false,
                didOpen: () => {
                    const createCharge = async (amount) => {
                        const createButton = document.getElementById('create-topup-qr');
                        if (!createButton || createButton.disabled) return;
                        createButton.disabled = true;
                        createButton.dataset.label = createButton.textContent;
                        createButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>กำลังสร้าง QR...';
                        try {
                            const response = await axios.post('/api/topups', { amount });
                            const scannable = response.data?.charge?.source?.scannable_code;
                            const code = response.data?.charge?.qr_data || (typeof scannable === 'string' ? scannable : scannable?.image?.download_uri);
                            const qr = document.getElementById('topup-qr');
                            if (!code) throw new Error('Opn ไม่ได้ส่งภาพ QR กลับมา');
                            qr.src = code.startsWith('data:') ? code : code;
                            document.getElementById('topup-qr-panel').style.display = 'block';
                            window.Swal.getHtmlContainer()?.querySelector('#topup-reference')?.replaceChildren(document.createTextNode(response.data.topup.Reference));

                            const syncTopup = async () => {
                                try {
                                    const sync = await axios.post(`/api/topups/${response.data.topup.Topup_id}/sync`);
                                    if (sync.data.status === 'approved') {
                                        clearInterval(topupPoller);
                                        topupPoller = null;
                                        await this.fetchUserProfile();
                                        window.Swal.fire({
                                            icon: 'success',
                                            title: 'เติมโทเคนสำเร็จ',
                                            text: `เพิ่ม ${amount.toLocaleString()} โทเคนเข้ากระเป๋าแล้ว`,
                                            timer: 2200,
                                            showConfirmButton: false,
                                        });
                                        return true;
                                    }
                                } catch (error) {
                                    console.warn('ยังไม่สามารถยืนยันรายการเติมเงินได้', error);
                                }

                                return false;
                            };
                            clearInterval(topupPoller);
                            const approved = await syncTopup();
                            if (!approved && !topupPoller) {
                                topupPoller = setInterval(syncTopup, 3000);
                            }
                        } catch (error) {
                            window.Swal.showValidationMessage(error.response?.data?.message || error.message || 'ไม่สามารถสร้างรายการชำระเงินได้');
                        } finally {
                            if (createButton.isConnected) {
                                createButton.disabled = false;
                                createButton.textContent = createButton.dataset.label || 'สร้าง QR สำหรับชำระเงิน';
                            }
                        }
                    };
                    const render = async (amount) => {
                        document.querySelectorAll('.topup-option').forEach(el => el.classList.toggle('selected', Number(el.dataset.amount) === amount));
                        document.getElementById('qr-amount').textContent = amount.toLocaleString();
                    };
                    document.querySelectorAll('input[name="topup_amount"]').forEach(input => input.addEventListener('change', e => render(Number(e.target.value))));
                    document.getElementById('create-topup-qr').onclick = () => {
                        const selected = document.querySelector('input[name="topup_amount"]:checked');
                        if (selected) createCharge(Number(selected.value));
                    };
                    document.getElementById('download-topup-qr').onclick = () => { const a = document.createElement('a'); a.href = document.getElementById('topup-qr').src; a.download = 'promptpay-topup.png'; a.click(); };
                    render(100);
                },
                willClose: () => {
                    clearInterval(topupPoller);
                },
                allowOutsideClick: true,
                allowEscapeKey: true
            });
        }
        ,
        promptPayPayload(amount) {
            const raw = String(import.meta.env.VITE_PROMPTPAY_ID || '').replace(/\D/g, '');
            const id = raw.startsWith('0') ? `0066${raw.slice(1)}` : raw;
            const merchant = `0016A00000067701011101${String(id.length).padStart(2, '0')}${id}`;
            const value = Number(amount).toFixed(2);
            const field = `0002010102122937${merchant}5802TH5303764` + `54${String(value.length).padStart(2, '0')}${value}6304`;
            let crc = 0xffff; for (const ch of field) { crc ^= ch.charCodeAt(0) << 8; for (let i=0;i<8;i++) crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff; }
            return field + crc.toString(16).toUpperCase().padStart(4, '0');
        }
    }
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');

.hide-scroll::-webkit-scrollbar {
    display: none;
}

.hide-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    touch-action: pan-y;
}

.topup-option:has(input:checked) {
    color: #15803d;
    border-color: #16a34a !important;
    background: #f0fdf4;
}

.topup-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.topup-option { min-height: 92px; border: 1px solid #cbd5e1; border-radius: 18px; padding: 12px 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; background: #fff; touch-action: manipulation; user-select: none; -webkit-tap-highlight-color: transparent; }
.topup-option input { display: none; }
.topup-option strong { font-size: 20px; color: #111827; }
.topup-option small { color: #a3a3a3; font-weight: 600; }
.topup-option.selected, .topup-option:has(input:checked) { background: #05c968; border-color: #05c968 !important; color: #fff; }
.topup-option.selected strong, .topup-option:has(input:checked) strong, .topup-option.selected small, .topup-option:has(input:checked) small { color: #fff; }
.qr-panel { border: 1px solid #cbd5e1; border-radius: 24px; padding: 18px 10px 12px; text-align: center; }
.qr-panel #topup-qr { display: block; margin: 0 auto; }
</style>
