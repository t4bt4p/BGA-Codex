<template>
    <AdminLayout>
        <template #header>ประวัติธุรกรรม (Transaction History)</template>

        <div class="fade-in-section">
            <div class="row g-3 mt-1 mb-3">
                <div class="col-md-4"><div class="bg-white rounded-4 shadow-sm border p-3 h-100">
                    <div class="text-muted small">Private Blockchain</div>
                    <div class="fw-bold fs-5" :class="blockchainSummary?.success ? 'text-success' : 'text-danger'">{{ blockchainSummary?.success ? 'ข้อมูลครบถ้วน' : 'ต้องตรวจสอบ' }}</div>
                </div></div>
                <div class="col-md-4"><div class="bg-white rounded-4 shadow-sm border p-3 h-100">
                    <div class="text-muted small">จำนวนบล็อกธุรกรรม</div>
                    <div class="fw-bold fs-5">{{ blockchainSummary?.transaction_block_count ?? '—' }}</div>
                </div></div>
                <div class="col-md-4"><div class="bg-white rounded-4 shadow-sm border p-3 h-100">
                    <div class="text-muted small">บล็อกล่าสุด</div>
                    <div class="fw-bold">{{ latestBlockLabel }}</div>
                    <div class="text-muted small text-truncate">{{ latestBlockHash }}</div>
                </div></div>
            </div>
            <div v-if="blockchainSummary" class="mb-2 small" role="status">
                <div v-if="!blockchainSummary.consensus" class="alert alert-warning mb-2">
                    ยังไม่พบสำเนาที่ถูกต้องและตรงกันอย่างน้อย 2 ชุด กรุณาตรวจสอบหลักฐานก่อนซ่อมหรือเติมรายการ
                </div>
                <div v-else-if="hasLedgerConflicts" class="alert alert-danger mb-2">
                    พบข้อมูลธุรกรรมขัดแย้งกัน กรุณาตรวจสอบข้อมูลก่อนเติมหลักฐานที่ตกหล่น
                </div>
                <div v-if="missingTransactionCount" class="text-warning-emphasis">
                    ธุรกรรมที่ยังไม่มีหลักฐาน: {{ missingTransactionCount }} รายการ
                </div>
            </div>
            <div class="d-flex justify-content-end flex-wrap gap-2 mt-3 mb-2">
                <button type="button" @click="verifyBlockchain" :disabled="ledgerBusy" class="btn btn-dark shadow-sm fw-bold px-4 rounded-pill">
                    <i class="fa-solid fa-shield-halved me-2 text-info"></i> ตรวจสอบความถูกต้องของเชน
                </button>
            </div>

            <div class="bg-white rounded-4 shadow-sm border border-light overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-muted fw-bold py-3 px-4">วัน-เวลา</th>
                                <th class="text-muted fw-bold py-3">ผู้ใช้งาน</th>
                                <th class="text-muted fw-bold py-3">ประเภทรายการ</th>
                                <th class="text-muted fw-bold py-3 text-end">จำนวนเงิน (โทเคน)</th>
                                <th class="text-muted fw-bold py-3 text-nowrap">วันที่ยืม - วันที่ต้องคืน</th>
                                <th class="text-muted fw-bold py-3 text-center">Polygon Amoy</th>
                                <th class="text-muted fw-bold py-3 text-center">อ้างอิงบอร์ดเกม</th>
                                <th class="text-muted fw-bold py-3 text-center">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ts in transactions" :key="ts.Ts_id">
                                <!-- วันเวลา -->
                                <td class="px-4 text-muted small">
                                    {{ new Date(ts.created_at).toLocaleString('th-TH') }}
                                </td>

                                <!-- ข้อมูลผู้ใช้งาน -->
                                <td>
                                    <div class="d-flex align-items-center gap-2" v-if="ts.user">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px; font-size: 0.9rem;">
                                            {{ ts.user.User_name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small">{{ ts.user.User_name }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">@{{ ts.user.User_username }}</div>
                                        </div>
                                    </div>
                                    <span v-else class="text-muted small">ไม่พบข้อมูลผู้ใช้</span>
                                </td>

                                <!-- ประเภทรายการ -->
                                <td>
                                    <span v-if="ts.T_type === 'topup_credit'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-arrow-trend-up me-1"></i> เติมเงิน
                                    </span>
                                    <span v-else-if="['rental_debit', 'admin_debit'].includes(ts.T_type)" class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-arrow-trend-down me-1"></i> {{ ts.T_type === 'rental_debit' ? 'เช่าเกม' : 'หักโทเคน' }}
                                    </span>
                                    <span v-else-if="ts.T_type === 'return_event'" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fa-solid fa-rotate-left me-1"></i> คืนเกม
                                    </span>
                                    <span v-else class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">
                                        {{ ts.T_type }}
                                    </span>
                                </td>

                                <!-- จำนวนเงิน -->
                                <td class="text-end fw-bold">
                                    <span :class="['topup_credit', 'return_event'].includes(ts.T_type) ? 'text-success' : 'text-danger'">
                                        {{ ts.T_type === 'topup_credit' ? '+' : (ts.T_type === 'return_event' ? '' : '-') }}{{ ts.T_type === 'return_event' ? 'คืนแล้ว' : ts.T_cost }}
                                    </span>
                                </td>

                                <td class="small text-nowrap">
                                    <template v-if="ts.rental">
                                        <div class="fw-semibold text-dark">{{ formatDate(ts.rental.rented_at) }}</div>
                                        <div class="text-muted"><i class="fa-solid fa-arrow-down-long me-1"></i>{{ formatDate(ts.rental.due_at) }}</div>
                                    </template>
                                    <span v-else class="text-muted">-</span>
                                </td>

                                <td class="text-center small">
                                    <a v-if="ts.Chain_status === 'confirmed'" :href="ts.chain_explorer_url" target="_blank" rel="noopener" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 text-decoration-none">
                                        ยืนยันแล้ว <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                    </a>
                                    <span v-else-if="['pending', 'processing'].includes(ts.Chain_status)" class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">กำลังยืนยัน</span>
                                    <button v-else-if="ts.Chain_status === 'failed'" type="button" class="btn btn-sm btn-outline-danger" :disabled="ledgerBusy" @click="retryPolygon(ts)">ลองใหม่</button>
                                    <span v-else class="text-muted">ยังไม่เปิดใช้</span>
                                </td>

                                <!-- อ้างอิงบอร์ดเกม -->
                                <td class="text-center text-muted small">
                                    <span v-if="ts.boardgame">{{ ts.boardgame.Bg_name }}</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary text-nowrap" @click="openDetails(ts, 'transaction')">ดูรายละเอียด</button></td>
                            </tr>
                            <tr v-if="transactions.length === 0">
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fs-2 mb-3 text-light"></i>
                                    <p class="mb-0">ยังไม่มีประวัติการทำธุรกรรม</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <Teleport to="body">
            <dialog ref="detailsDialog" class="transaction-details" aria-labelledby="details-title" @close="clearDetails" @cancel="clearDetails">
                <div class="details-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="details-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                        <div><div class="details-eyebrow">ประวัติธุรกรรม</div><h2 id="details-title" class="h5 mb-0 fw-bold">รายละเอียดรายการ</h2></div>
                    </div>
                    <button type="button" class="btn-close" aria-label="ปิดรายละเอียด" @click="$refs.detailsDialog.close()"></button>
                </div>
                <div class="details-content">
                    <div class="details-amount" v-if="selectedTopup || selectedTransaction">
                        <span class="details-amount-label">{{ selectedTopup ? 'ยอดเติมเงิน' : transactionType(selectedTransaction.T_type) }}</span>
                        <div class="details-amount-value">{{ selectedTopup ? Number(selectedTopup.Amount).toLocaleString('th-TH') : (selectedTransaction.T_type === 'return_event' ? 'คืนเกมแล้ว' : Number(selectedTransaction.T_cost).toLocaleString('th-TH')) }} <small v-if="selectedTopup || selectedTransaction.T_type !== 'return_event'">{{ selectedTopup ? 'บาท' : 'โทเคน' }}</small></div>
                    </div>
                <section v-if="selectedTopup" class="details-section" aria-label="รายละเอียดเติมเงิน" aria-live="polite">
                    <h3 class="h6">รายละเอียดเติมเงิน #{{ selectedTopup.Topup_id }}</h3>
                    <dl class="mb-2">
                        <dt>ผู้ใช้งาน</dt><dd>{{ selectedTopup.user?.User_name }}</dd>
                        <dt>จำนวนเงิน</dt><dd>{{ selectedTopup.Amount }} บาท</dd>
                        <dt>สถานะ</dt><dd>{{ topupStatus(selectedTopup.Status) }}</dd>
                        <dt>เลขอ้างอิง</dt><dd class="text-break">{{ selectedTopup.Reference }}</dd>
                        <dt>วันเวลาที่ทำรายการ</dt><dd>{{ new Date(selectedTopup.created_at).toLocaleString('th-TH') }}</dd>
                        <dt>วันเวลาที่อนุมัติ</dt><dd>{{ selectedTopup.approved_at ? new Date(selectedTopup.approved_at).toLocaleString('th-TH') : 'ยังไม่อนุมัติ' }}</dd>
                    </dl>

                </section>
                <section v-if="selectedTransaction" class="details-section" aria-label="รายละเอียดธุรกรรม">
                    <h3 class="h6">ธุรกรรม #{{ selectedTransaction.Ts_id }}</h3>
                    <dl>
                        <dt>ผู้ใช้งาน</dt><dd>{{ selectedTransaction.user?.User_name ?? 'ไม่พบผู้ใช้' }} (@{{ selectedTransaction.user?.User_username ?? '-' }})</dd>
                        <dt>ประเภทรายการ</dt><dd>{{ transactionType(selectedTransaction.T_type) }}</dd>
                        <dt>จำนวนเงิน</dt><dd>{{ selectedTransaction.T_cost }} โทเคน</dd>
                        <dt>วันเวลาที่ทำรายการ</dt><dd>{{ formatDateTime(selectedTransaction.created_at) }}</dd>
                        <dt>บอร์ดเกม</dt><dd>{{ selectedTransaction.boardgame?.Bg_name ?? '-' }}</dd>
                        <template v-if="selectedTransaction.rental">
                            <dt>เลขที่การเช่า</dt><dd>#{{ selectedTransaction.rental.Rental_id }}</dd>
                            <dt>จำนวนวันที่เช่า</dt><dd>{{ selectedTransaction.rental.rental_days }} วัน</dd>
                            <dt>วันที่ยืม</dt><dd>{{ formatDateTime(selectedTransaction.rental.rented_at) }}</dd>
                            <dt>กำหนดคืน</dt><dd>{{ formatDateTime(selectedTransaction.rental.due_at) }}</dd>
                            <dt>วันที่คืนจริง</dt><dd>{{ formatDateTime(selectedTransaction.rental.returned_at) }}</dd>
                            <dt>ผู้ทำรายการคืน</dt><dd>{{ selectedTransaction.rental.returned_by_name ?? '-' }}</dd>
                        </template>
                        <dt>สถานะ Polygon</dt><dd>{{ polygonStatus(selectedTransaction.Chain_status) }}</dd>
                        <dt>เลขอ้างอิง Polygon</dt><dd class="text-break">{{ selectedTransaction.Chain_tx_hash ?? '-' }}</dd>
                    </dl>
                </section>
                </div>
                <div class="details-footer"><button type="button" class="btn details-close" @click="$refs.detailsDialog.close()">ปิดรายละเอียด</button></div>
            </dialog>
        </Teleport>
    </AdminLayout>
</template>

<script>
import AdminLayout from '../../Layouts/AdminLayout.vue';

export default {
    components: { AdminLayout },
    data() {
        return {
            transactions: [],
            topups: [],
            selectedTopup: null,
            selectedTransaction: null,
            topupError: '',
            blockchainSummary: null,
            dataPoller: null,
            visibilityHandler: null,
            isRefreshing: false,
            isMaintainingLedger: false,
            isVerifyingLedger: false
        };
    },
    computed: {
        ledgerBusy() {
            return this.isRefreshing || this.isMaintainingLedger || this.isVerifyingLedger;
        },
        missingTransactionCount() {
            return this.blockchainSummary?.missing_transaction_ids?.length ?? 0;
        },
        hasLedgerConflicts() {
            return Boolean(this.blockchainSummary?.mismatched_transaction_ids?.length || this.blockchainSummary?.orphaned_transaction_ids?.length);
        },
        canRepairLedger() {
            if (!this.blockchainSummary?.consensus) return false;
            const nodes = this.blockchainSummary.nodes ?? [];
            return nodes.some(node => !node.status)
                || new Set(nodes.filter(node => node.status).map(node => node.fingerprint)).size > 1;
        },
        canReconcileLedger() {
            return Boolean(this.blockchainSummary?.consensus && this.missingTransactionCount > 0 && !this.hasLedgerConflicts);
        },
        latestBlockLabel() {
            const block = this.blockchainSummary?.latest_block;
            if (!block || !this.blockchainSummary?.consensus) return '—';
            return `#${block.index} · ${new Date(block.timestamp).toLocaleString('th-TH')}`;
        },
        latestBlockHash() {
            const hash = this.blockchainSummary?.latest_block?.hash;
            return hash ? `${hash.slice(0, 10)}…${hash.slice(-8)}` : '—';
        }
    },
    mounted() {
        this.refreshData();
        // Keep the latest node JSON/index in sync after a transaction is created.
        // Use arrow callbacks so Vue's component context is preserved.
        this.dataPoller = window.setInterval(() => this.refreshData(), 3000);
        this.visibilityHandler = () => this.refreshWhenVisible();
        document.addEventListener('visibilitychange', this.visibilityHandler);
    },
    beforeUnmount() {
        window.clearInterval(this.dataPoller);
        document.removeEventListener('visibilitychange', this.visibilityHandler);
    },
    methods: {
        async openDetails(record, type) {
            this.selectedTopup = type === 'topup' ? record : null;
            this.selectedTransaction = type === 'transaction' ? record : null;
            await this.$nextTick();
            if (!this.$refs.detailsDialog.open) this.$refs.detailsDialog.showModal();
        },
        clearDetails() {
            this.selectedTopup = null;
            this.selectedTransaction = null;
        },
        transactionType(type) {
            return { topup_credit: 'เติมเงิน', rental_debit: 'เช่าเกม', return_event: 'คืนเกม', admin_debit: 'หักโทเคน' }[type] ?? type;
        },
        polygonStatus(status) {
            return { confirmed: 'ยืนยันแล้ว', pending: 'รอยืนยัน', processing: 'กำลังยืนยัน', failed: 'ยืนยันไม่สำเร็จ' }[status] ?? 'ยังไม่ส่งไป Polygon';
        },
        formatDateTime(value) {
            return value ? new Date(value).toLocaleString('th-TH') : '-';
        },
        topupStatus(status) {
            return { approved: 'สำเร็จ', pending: 'รอชำระเงิน', failed: 'ชำระเงินไม่สำเร็จ', rejected: 'ไม่อนุมัติ' }[status] ?? status;
        },
        async fetchTopups() {
            try {
                const response = await window.axios.get('/api/topups');
                this.topups = response.data;
                this.topupError = '';
                if (this.selectedTopup) this.selectedTopup = this.topups.find(item => item.Topup_id === this.selectedTopup.Topup_id) ?? null;
            } catch {
                this.topupError = 'ไม่สามารถโหลดรายการเติมเงินได้ กรุณาลองใหม่';
            }
        },
        formatDate(value) {
            if (!value) return '-';
            return new Date(value).toLocaleDateString('th-TH', {
                day: '2-digit', month: 'short', year: 'numeric'
            });
        },
        async fetchTransactions() {
            try {
                const response = await window.axios.get('/api/transactions');
                this.transactions = response.data;
            } catch (error) {
                console.error('ไม่สามารถโหลดประวัติธุรกรรมได้', error);
            }
        },
        async fetchBlockchainSummary() {
            const response = await window.axios.get('/api/blockchain/verify');
            this.blockchainSummary = response.data;
            return response.data;
        },
        async refreshData() {
            if (this.ledgerBusy) return;
            this.isRefreshing = true;
            try {
                await Promise.all([this.fetchTransactions(), this.fetchBlockchainSummary(), this.fetchTopups()]);
            } catch (error) {
                console.error('ไม่สามารถอัปเดตข้อมูลล่าสุดได้', error);
            } finally {
                this.isRefreshing = false;
            }
        },
        refreshWhenVisible() {
            if (document.visibilityState === 'visible') this.refreshData();
        },
        async retryPolygon(transaction) {
            if (this.ledgerBusy) return;
            try {
                await window.axios.post(`/api/transactions/${transaction.Ts_id}/polygon/retry`);
                transaction.Chain_status = 'pending';
            } catch (error) {
                window.Swal.fire({ icon: 'error', title: 'ส่งรายการไม่สำเร็จ', text: error.response?.data?.message || 'กรุณาลองใหม่' });
            }
        },

        async maintainLedger(action) {
            if (this.ledgerBusy || !['repair', 'reconcile'].includes(action)) return;
            this.isMaintainingLedger = true;
            const repair = action === 'repair';
            const title = repair ? 'ซ่อมสำเนาหลักฐาน' : 'เติมหลักฐานที่ตกหล่น';
            try {
                // Check again because the summary may have changed since the button was enabled.
                await this.fetchBlockchainSummary();
                if (!(repair ? this.canRepairLedger : this.canReconcileLedger)) {
                    await window.Swal.fire({
                        icon: 'warning', title: 'ยังดำเนินการไม่ได้',
                        text: !this.blockchainSummary?.consensus
                            ? 'ไม่พบสำเนาอ้างอิงที่ถูกต้องและตรงกันอย่างน้อย 2 ชุด'
                            : (!repair && this.hasLedgerConflicts
                                ? 'พบข้อมูลธุรกรรมขัดแย้งกัน กรุณาตรวจสอบก่อนเติมหลักฐาน'
                                : (repair ? 'สำเนาหลักฐานตรงกันแล้ว ไม่จำเป็นต้องซ่อม' : 'ไม่พบหลักฐานที่ตกหล่น'))
                    });
                    return;
                }
                const result = await window.Swal.fire({
                    icon: 'question', title,
                    text: repair
                        ? 'คัดลอกหลักฐานจากสำเนาอ้างอิงที่ตรวจผ่านไปยังทั้ง 3 สำเนา ต้องการดำเนินการหรือไม่?'
                        : `เพิ่มหลักฐานของธุรกรรมที่ตกหล่น ${this.missingTransactionCount} รายการ โดยอ้างอิงข้อมูลในระบบ ต้องการดำเนินการหรือไม่?`,
                    showCancelButton: true, confirmButtonText: 'ดำเนินการ', cancelButtonText: 'ยกเลิก'
                });
                if (!result.isConfirmed) return;
                window.Swal.fire({
                    title: 'กำลัง' + title, allowOutsideClick: false, allowEscapeKey: false,
                    didOpen: () => window.Swal.showLoading()
                });
                const response = await window.axios.post(`/api/blockchain/${action}`);
                this.blockchainSummary = response.data;
                await this.fetchTransactions();
                await window.Swal.fire({
                    icon: response.data.success ? 'success' : 'warning',
                    title: title + 'เสร็จแล้ว', text: response.data.message
                });
            } catch (error) {
                await window.Swal.fire({
                    icon: 'error', title: title + 'ไม่สำเร็จ',
                    text: error.response?.data?.message || 'ไม่สามารถดำเนินการได้ กรุณาตรวจสอบและลองใหม่'
                });
            } finally {
                this.isMaintainingLedger = false;
                await this.refreshData();
            }
        },

        // 🔗 เพิ่มฟังก์ชันตรวจสอบ Blockchain และรีเฟรชตารางอัตโนมัติ
        async verifyBlockchain() {
            if (this.ledgerBusy) return;
            this.isVerifyingLedger = true;
            window.Swal.fire({
                title: 'กำลังตรวจสอบเครือข่าย...',
                html: 'ระบบกำลังดึงข้อมูลจากโหนดทั้งหมดมาคำนวณ Hash ใหม่<br>โปรดรอสักครู่',
                allowOutsideClick: false,
                didOpen: () => { window.Swal.showLoading(); }
            });

            try {
                const data = await this.fetchBlockchainSummary();

                let nodesHtml = '<div class="text-start mt-3">';
                data.nodes.forEach(node => {
                    const icon = node.status
                        ? '<i class="fa-solid fa-circle-check text-success me-2"></i>'
                        : '<i class="fa-solid fa-circle-xmark text-danger me-2"></i>';
                    const textClass = node.status ? 'text-success' : 'text-danger fw-bold';

                    nodesHtml += `
                        <div class="p-2 border rounded mb-2 bg-light shadow-sm">
                            <div class="fw-bold">${icon} ${node.node}</div>
                            <div class="small ${textClass} ms-4">${node.message}</div>
                        </div>
                    `;
                });
                if (data.missing_transaction_ids?.length) {
                    nodesHtml += `<div class="alert alert-warning py-2">ธุรกรรมที่ตกหล่น: ${data.missing_transaction_ids.join(', ')}</div>`;
                }
                if (data.mismatched_transaction_ids?.length) {
                    nodesHtml += `<div class="alert alert-danger py-2">ข้อมูลธุรกรรมไม่ตรงกับบล็อกเดิม: ${data.mismatched_transaction_ids.map(Number).join(', ')}</div>`;
                }
                if (data.orphaned_transaction_ids?.length) {
                    nodesHtml += `<div class="alert alert-danger py-2">ธุรกรรมที่หายจากฐานข้อมูล: ${data.orphaned_transaction_ids.map(Number).join(', ')}</div>`;
                }
                nodesHtml += '</div>';
                const actions = [
                    this.canRepairLedger ? ['repair', 'ซ่อมสำเนาหลักฐาน', 'btn-outline-warning', 'fa-wrench'] : null,
                    this.canReconcileLedger ? ['reconcile', 'เติมหลักฐานที่ตกหล่น', 'btn-outline-primary', 'fa-file-circle-plus'] : null
                ].filter(Boolean);
                if (actions.length) {
                    nodesHtml += '<div class="d-flex flex-wrap justify-content-center gap-2 border-top pt-3 mt-3">';
                    for (const [action, label, style, icon] of actions) {
                        nodesHtml += `<button type="button" data-ledger-action="${action}" class="btn ${style} rounded-pill fw-bold"><i class="fa-solid ${icon} me-2" aria-hidden="true"></i>${label}</button>`;
                    }
                    nodesHtml += '</div>';
                }

                const isSuccess = data.success;

                // Verification is finished before the result dialog accepts maintenance actions.
                this.isVerifyingLedger = false;
                window.Swal.fire({
                    icon: isSuccess ? 'success' : 'warning',
                    title: isSuccess ? 'ตรวจสอบข้อมูลผ่าน' : 'พบข้อผิดพลาดในเครือข่าย!',
                    html: `<strong class="${isSuccess ? 'text-success' : 'text-danger'} fs-5">${data.message}</strong>` + nodesHtml,
                    confirmButtonText: 'ปิด',
                    confirmButtonColor: '#16834c',
                    width: '560px',
                    didOpen: () => {
                        window.Swal.getHtmlContainer()?.querySelectorAll('[data-ledger-action]').forEach(button => {
                            button.addEventListener('click', () => {
                                if (this.ledgerBusy) return;
                                const action = button.dataset.ledgerAction;
                                window.Swal.close();
                                this.maintainLedger(action);
                            });
                        });
                    }
                }).then((result) => {
                    // 🔄 ถ้าระบบจัดการ Restore DB แล้ว พอแอดมินกดปิดป๊อปอัป ให้รีเฟรชข้อมูลตารางทันที
                    if (result.isConfirmed) {
                        this.fetchTransactions();
                    }
                });

            } catch (error) {
                window.Swal.fire({
                    icon: 'error',
                    title: 'การเชื่อมต่อขัดข้อง',
                    text: 'ไม่สามารถติดต่อเครือข่ายโหนดเพื่อตรวจสอบระบบได้'
                });
            } finally {
                this.isVerifyingLedger = false;
            }
        }
    }
}
</script>

<style scoped>
.transaction-details {
    position: fixed; inset: 0; margin: auto; width: min(620px, calc(100vw - 32px));
    max-height: min(88dvh, 900px); padding: 0; border: 1px solid #e2ebe5; border-radius: 24px;
    background: #fff; color: #18332a; box-shadow: 0 28px 90px #0b261c40;
    overflow: hidden;
}
.transaction-details[open] { display: flex; flex-direction: column; }
.transaction-details::backdrop { background: #10251d80; backdrop-filter: blur(5px); }
.details-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 24px 28px; border-bottom: 1px solid #e8eee9; flex-shrink: 0; }
.details-icon { display: grid; place-items: center; width: 48px; height: 48px; border-radius: 16px; background: #e9f7ee; color: #16834c; font-size: 22px; }
.details-eyebrow { font-size: 12px; color: #75847b; margin-bottom: 4px; }
.details-content { padding: 24px 28px; overflow-y: auto; min-height: 0; }
.details-amount { padding: 20px 24px; margin-bottom: 24px; border: 1px solid #d9efdf; border-radius: 18px; background: linear-gradient(120deg, #f0faf3, #f9fcfa); text-align: center; }
.details-amount-label { color: #5e7868; font-size: 13px; }
.details-amount-value { color: #16834c; font-weight: 750; font-size: 34px; margin-top: 6px; line-height: 1.4; }
.details-amount-value small { font-size: 14px; font-weight: 500; }
.details-section h3 { font-size: 13px; font-weight: 700; color: #54715f; margin-bottom: 16px; }
.details-section dl { display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 0 20px; margin: 0; font-size: 14px; }
.details-section dt, .details-section dd { padding: 11px 0; margin: 0; border-bottom: 1px solid #f0f3f1; line-height: 1.6; }
.details-section dt { font-weight: 500; color: #7b877f; }
.details-section dd { font-weight: 600; overflow-wrap: anywhere; }
.details-footer { padding: 16px 28px; border-top: 1px solid #e8eee9; background: #fafcfb; display: flex; justify-content: flex-end; flex-shrink: 0; }
.details-close { background: #16834c; color: #fff; border-radius: 12px; padding: 10px 28px; font-weight: 600; }
.details-close:hover, .details-close:focus-visible { background: #106538; color: #fff; }
@media (max-width: 480px) {
    .transaction-details { border-radius: 20px; max-height: 90dvh; }
    .details-header, .details-content { padding: 18px; }
    .details-section dl { grid-template-columns: 110px minmax(0, 1fr); column-gap: 12px; font-size: 13px; }
    .details-amount-value { font-size: 28px; }
    .details-footer { padding: 14px 18px; }
    .details-close { width: 100%; }
}

.fade-in-section {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
