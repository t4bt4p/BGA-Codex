import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/bootstrap.js', import.meta.url), 'utf8')
    .replace(/^import .*;$/gm, '');

function setup() {
    let rejectResponse, acceptResponse;
    const events = [], dialogs = [], removed = [];
    const axios = {
        defaults: { headers: { common: {} } },
        interceptors: { request: { use() {} }, response: { use(ok, fail) { acceptResponse = ok; rejectResponse = fail; } } },
    };
    const window = { location: { pathname: '/' }, setTimeout,
        dispatchEvent(event) { events.push(event.type); } };
    const storage = { getItem() { return 'test-token'; }, removeItem(key) { removed.push(key); } };
    const Swal = { fire: async options => { dialogs.push(options); return { isConfirmed: true }; } };
    new Function('axios', 'bootstrap', 'Swal', 'window', 'localStorage', 'CustomEvent', source)(
        axios, {}, Swal, window, storage, class { constructor(type) { this.type = type; } },
    );
    return { rejectResponse, acceptResponse, events, dialogs, removed };
}

test('wallet correction opens error popup, refreshes balance, and preserves login', async () => {
    const { rejectResponse, events, dialogs, removed } = setup();
    const error = { response: { status: 409, data: {
        code: 'wallet_balance_corrected', message: 'ปรับยอดแล้ว', balance: 380,
    } } };
    await assert.rejects(rejectResponse(error), e => e === error);
    assert.equal(error.walletCorrectionHandled, true);
    assert.equal((await error.walletCorrectionAcknowledged).isConfirmed, true);
    assert.deepEqual(events, ['wallet-corrected']);
    assert.equal(dialogs[0].title, 'พบข้อผิดพลาด');
    assert.match(dialogs[0].text, /380/);
    assert.deepEqual(removed, []);
});


test('transaction recovery popup does not invent a corrected wallet balance', async () => {
    const { rejectResponse, dialogs, removed } = setup();
    const error = { response: { status: 409, data: {
        code: 'transaction_data_restored', message: 'กู้ข้อมูลแล้ว',
    } } };
    await assert.rejects(rejectResponse(error), e => e === error);
    await error.walletCorrectionAcknowledged;
    assert.equal(dialogs[0].title, 'พบข้อผิดพลาด');
    assert.equal(dialogs[0].text, 'กู้ข้อมูลแล้ว');
    assert.deepEqual(removed, []);
});

test('history recovery reports correction without discarding the corrected response', () => {
    const { acceptResponse, dialogs } = setup();
    const response = { headers: { 'x-transactions-restored': '1' }, data: [{ T_cost: 100 }] };
    assert.equal(acceptResponse(response), response);
    assert.equal(dialogs[0].title, 'พบข้อผิดพลาด');
    acceptResponse({ headers: { 'x-transactions-restored': '0' }, data: [] });
    assert.equal(dialogs.length, 1);
});
