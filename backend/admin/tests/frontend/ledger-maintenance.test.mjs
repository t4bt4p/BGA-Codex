import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const sfc = readFileSync(new URL('../../resources/js/Pages/Admin/TransactionManager.vue', import.meta.url), 'utf8');
const script = sfc.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/^import AdminLayout.*$/m, '')
    .replace('export default', 'return');
const component = new Function('AdminLayout', script)({});
const healthy = { success: true, consensus: true, missing_transaction_ids: [], mismatched_transaction_ids: [], orphaned_transaction_ids: [], nodes: [1, 2, 3].map(node => ({ node, status: true, fingerprint: 'majority' })), message: 'ครบถ้วน' };

function instance(summary, { confirmed = true, error = null, fresh = summary } = {}) {
    const vm = component.data();
    for (const [key, fn] of Object.entries(component.computed)) Object.defineProperty(vm, key, { get: () => fn.call(vm) });
    for (const [key, fn] of Object.entries(component.methods)) vm[key] = fn.bind(vm);
    vm.blockchainSummary = structuredClone(summary);
    const calls = { posts: [], dialogs: [], refreshes: 0 };
    let current = structuredClone(fresh);
    vm.fetchBlockchainSummary = async () => (vm.blockchainSummary = structuredClone(current));
    vm.fetchTransactions = async () => { calls.refreshes++; };
    globalThis.window = {
        Swal: { fire: async options => { calls.dialogs.push(options); return { isConfirmed: confirmed }; }, showLoading() {} },
        axios: { post: async url => { calls.posts.push(url); if (error) throw error; current = healthy; return { data: structuredClone(healthy) }; } }
    };
    return { vm, calls };
}

test('repair is enabled for a corrupt or divergent copy only when a majority exists', () => {
    const { vm } = instance(healthy);
    assert.equal(vm.canRepairLedger, false);
    vm.blockchainSummary.nodes[2].status = false;
    assert.equal(vm.canRepairLedger, true);
    vm.blockchainSummary.nodes[2] = { status: true, fingerprint: 'divergent' };
    assert.equal(vm.canRepairLedger, true);
    vm.blockchainSummary.consensus = false;
    assert.equal(vm.canRepairLedger, false);
});

test('reconcile requires missing entries and blocks conflicts or no majority', () => {
    const { vm } = instance({ ...healthy, missing_transaction_ids: [42] });
    assert.equal(vm.canReconcileLedger, true);
    vm.blockchainSummary.mismatched_transaction_ids = [3];
    assert.equal(vm.canReconcileLedger, false);
    vm.blockchainSummary.mismatched_transaction_ids = [];
    vm.blockchainSummary.orphaned_transaction_ids = [7];
    assert.equal(vm.canReconcileLedger, false);
    vm.blockchainSummary.orphaned_transaction_ids = [];
    vm.blockchainSummary.consensus = false;
    assert.equal(vm.canReconcileLedger, false);
});

test('confirmed maintenance uses the matching endpoint and refreshes the resulting summary', async () => {
    for (const action of ['repair', 'reconcile']) {
        const dirty = { ...healthy, missing_transaction_ids: [42], nodes: [...healthy.nodes.slice(0, 2), { status: false }] };
        const { vm, calls } = instance(dirty);
        await vm.maintainLedger(action);
        assert.deepEqual(calls.posts, [`/api/blockchain/${action}`]);
        assert.equal(vm.blockchainSummary.success, true);
        assert.equal(vm.isMaintainingLedger, false);
        assert.ok(calls.refreshes > 0);
    }
});

test('cancelled confirmation does not modify the ledger', async () => {
    const { vm, calls } = instance({ ...healthy, missing_transaction_ids: [42] }, { confirmed: false });
    await vm.maintainLedger('reconcile');
    assert.deepEqual(calls.posts, []);
    assert.equal(vm.isMaintainingLedger, false);
});

test('fresh conflicting state stops maintenance even if the old button was enabled', async () => {
    const { vm, calls } = instance({ ...healthy, missing_transaction_ids: [42] }, { fresh: { ...healthy, missing_transaction_ids: [42], mismatched_transaction_ids: [42] } });
    await vm.maintainLedger('reconcile');
    assert.deepEqual(calls.posts, []);
    assert.match(calls.dialogs[0].text, /ขัดแย้ง/);
});

test('backend rejection is shown and controls unlock after failure', async () => {
    const { vm, calls } = instance({ ...healthy, missing_transaction_ids: [42] }, { error: { response: { data: { message: 'ไม่สามารถกู้ธุรกรรมได้' } } } });
    await vm.maintainLedger('reconcile');
    assert.ok(calls.dialogs.some(dialog => dialog.text === 'ไม่สามารถกู้ธุรกรรมได้'));
    assert.equal(vm.isMaintainingLedger, false);
    assert.equal(vm.isRefreshing, false);
});

test('busy state prevents duplicate maintenance and polling', async () => {
    const { vm, calls } = instance({ ...healthy, missing_transaction_ids: [42] });
    vm.isMaintainingLedger = true;
    await vm.maintainLedger('reconcile');
    await vm.refreshData();
    assert.deepEqual(calls.posts, []);
    assert.equal(calls.refreshes, 0);
});
