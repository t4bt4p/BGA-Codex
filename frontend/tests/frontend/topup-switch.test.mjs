import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/Layouts/UserLayout.vue', import.meta.url), 'utf8');
const method = source.slice(source.indexOf('        openTopupModal() {'), source.indexOf('        promptPayPayload(amount)'));
const flush = () => new Promise(resolve => setImmediate(resolve));
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; };

function setup() {
    const elements = new Map();
    for (const id of ['create-topup-qr', 'topup-qr-panel', 'topup-qr', 'qr-amount', 'download-topup-qr', 'topup-reference', 'topup-charge-id', 'topup-test-details']) {
        elements.set(id, { style: {}, dataset: {}, textContent: '', isConnected: true,
            removeAttribute(name) { delete this[name]; } });
    }
    const radios = [20, 50, 100, 500, 1000].map(value => ({ value, addEventListener(_, fn) { this.change = fn; } }));
    let selected = radios[2], modal;
    const calls = [], dialogs = [], timers = new Map();
    const document = {
        getElementById: id => elements.get(id),
        querySelectorAll: selector => selector === '.topup-option' ? [] : radios,
        querySelector: () => selected,
    };
    const window = { Swal: {
        fire(options) { dialogs.push(options); if (options.didOpen) { modal = options; options.didOpen(); } },
        resetValidationMessage() {}, showValidationMessage() {}, getHtmlContainer() { return null; },
    } };
    const axios = { post(url, data) { const pending = deferred(); calls.push({ url, data, ...pending }); return pending.promise; } };
    const layout = new Function('window', 'document', 'axios', 'localStorage', 'setInterval', 'clearInterval',
        `return ({${method}});`)(window, document, axios, { getItem: () => 'token' },
        callback => { const id = Symbol(); timers.set(id, callback); return id; }, id => timers.delete(id));
    layout.fetchUserProfile = async () => {};
    layout.openTopupModal();
    return { calls, dialogs, timers, elements,
        select(amount) { selected = radios.find(r => r.value === amount); selected.change({ target: selected }); },
        create() { elements.get('create-topup-qr').onclick(); }, close() { modal.willClose(); },
    };
}
const charge = (id, amount) => ({ data: { charge: { livemode: false, qr_data: `data:image/png;base64,QR${amount}` }, topup: { Topup_id: id, Reference: `REF${id}`, Provider_charge_id: `chrg_test_${id}` } } });

test('switching from unpaid 20 to 50 hides the old QR and credits the new charge', async () => {
    const ui = setup();
    ui.select(20); ui.create(); ui.calls[0].resolve(charge(1, 20)); await flush();
    ui.calls[1].resolve({ data: { status: 'pending' } }); await flush();
    assert.equal(ui.timers.size, 1);
    ui.select(50);
    assert.equal(ui.elements.get('topup-qr-panel').style.display, 'none');
    assert.equal(ui.elements.get('topup-qr').src, undefined);
    assert.equal(ui.timers.size, 0);
    ui.create();
    assert.deepEqual(ui.calls[2].data, { amount: 50 });
    ui.calls[2].resolve(charge(2, 50)); await flush();
    assert.equal(ui.elements.get('topup-qr').src, 'data:image/png;base64,QR50');
    assert.equal(ui.calls[3].url, '/api/topups/2/sync');
    ui.calls[3].resolve({ data: { status: 'approved' } }); await flush();
    assert.match(ui.dialogs.at(-1).text, /50/);
    assert.equal(ui.timers.size, 0);
});

test('a late QR response for 20 cannot replace the QR for 50', async () => {
    const ui = setup();
    ui.select(20); ui.create(); ui.select(50); ui.create();
    ui.calls[1].resolve(charge(2, 50)); await flush();
    ui.calls[0].resolve(charge(1, 20)); await flush();
    assert.equal(ui.elements.get('topup-qr').src, 'data:image/png;base64,QR50');
    assert.equal(ui.calls.length, 3);
    ui.close(); ui.calls[2].resolve({ data: { status: 'pending' } }); await flush();
    assert.equal(ui.timers.size, 0);
});

test('a late sync response for the old charge cannot close the new payment', async () => {
    const ui = setup();
    ui.select(20); ui.create(); ui.calls[0].resolve(charge(1, 20)); await flush();
    ui.select(50); ui.create();
    ui.calls[1].resolve({ data: { status: 'approved' } }); await flush();
    assert.equal(ui.dialogs.length, 1);
    ui.calls[2].resolve(charge(2, 50)); await flush();
    assert.equal(ui.calls[3].url, '/api/topups/2/sync');
    ui.close();
});

test('100 to 500 with identical sandbox QR images still tracks the new charge and reference', async () => {
    const ui = setup();
    ui.create();
    const old = charge(100, 100);
    ui.calls[0].resolve(old); await flush();
    ui.calls[1].resolve({ data: { status: 'pending' } }); await flush();
    ui.select(500); ui.create();
    const current = charge(500, 500);
    current.data.charge.qr_data = old.data.charge.qr_data;
    ui.calls[2].resolve(current); await flush();
    assert.deepEqual(ui.calls[2].data, { amount: 500 });
    assert.equal(ui.elements.get('qr-amount').textContent, '500');
    assert.equal(ui.elements.get('topup-reference').textContent, 'REF500');
    assert.equal(ui.elements.get('topup-charge-id').textContent, 'chrg_test_500');
    assert.equal(ui.elements.get('topup-test-details').style.display, 'block');
    assert.equal(ui.calls[3].url, '/api/topups/500/sync');
    ui.calls[3].resolve({ data: { status: 'approved' } }); await flush();
    assert.match(ui.dialogs.at(-1).text, /500/);
});

test('live payments hide sandbox instructions', async () => {
    const ui = setup(); ui.create();
    const live = charge(1, 100); live.data.charge.livemode = true;
    ui.calls[0].resolve(live); await flush();
    assert.equal(ui.elements.get('topup-test-details').style.display, 'none');
    ui.close();
});
