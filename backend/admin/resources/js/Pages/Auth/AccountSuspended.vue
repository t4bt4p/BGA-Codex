<template>
    <main class="suspension-page">
        <section class="suspension-card" aria-labelledby="suspension-title">
            <div class="suspension-icon" aria-hidden="true"><i class="fa-solid fa-user-lock"></i></div>
            <p class="brand">BGA Vault</p>
            <h1 id="suspension-title">{{ message }}</h1>
            <p v-if="overdue">กรุณาติดต่อผู้ดูแลระบบเพื่อคืนบอร์ดเกมและขอเปิดใช้งานบัญชีอีกครั้ง</p>
            <p v-else>กรุณาติดต่อผู้ดูแลระบบเพื่อตรวจสอบและขอเปิดใช้งานบัญชีอีกครั้ง</p>
            <router-link :to="isAdmin ? '/admin/login' : '/login'" class="btn btn-success rounded-pill px-4 py-3">
                กลับไปเข้าสู่ระบบ
            </router-link>
        </section>
    </main>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
let stored = null;
try { stored = JSON.parse(sessionStorage.getItem('account_suspension')); } catch { /* Show a generic message. */ }
const isAdmin = computed(() => route.path.startsWith('/admin'));
const overdue = stored?.isAdmin === isAdmin.value && stored?.reason === 'overdue';
const message = overdue
    ? 'บัญชีของคุณถูกระงับเนื่องจากบอร์ดเกมเกินกำหนด'
    : 'บัญชีของคุณถูกระงับ';
</script>

<style scoped>
.suspension-page { min-height: 100dvh; display: grid; place-items: center; padding: 24px; background: #f0fdf4; }
.suspension-card { width: 100%; max-width: 480px; padding: 36px 24px; text-align: center; background: white; border: 1px solid #dcfce7; border-radius: 28px; box-shadow: 0 12px 40px #14532d12; }
.suspension-icon { display: grid; place-items: center; width: 80px; height: 80px; margin: 0 auto 24px; border-radius: 50%; background: #fff1f2; color: #be123c; font-size: 30px; }
.brand { color: #15803d; font-weight: 700; }
h1 { font-size: clamp(22px, 5vw, 28px); line-height: 1.6; color: #881337; overflow-wrap: anywhere; }
p { line-height: 1.8; color: #64748b; margin-bottom: 24px; }
</style>
