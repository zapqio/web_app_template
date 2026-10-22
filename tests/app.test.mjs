import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../public/assets/app.js', import.meta.url), 'utf8');
const runId = '11111111-1111-1111-1111-111111111111';
const json = (value, status = 200) => new Response(JSON.stringify(value), { status });

function page(handler, search = '') {
    const nodes = new Map();
    function node(selector) {
        if (!nodes.has(selector)) {
            nodes.set(selector, { textContent: '', hidden: true, disabled: false, events: {},
                addEventListener(name, callback) { this.events[name] = callback; } });
        }

        return nodes.get(selector);
    }

    const form = node('#pipeline');
    form.elements = { method: { value: 'demo' }, input: { value: '{ "number": 9007199254740993 }' },
        idempotencyKey: { value: 'initial-key' }, __RequestVerificationToken: { value: 'csrf-value' } };
    form.querySelector = node;
    const calls = [];
    let sequence = 0;
    const context = vm.createContext({
        document: { querySelector: node }, URL, URLSearchParams, TypeError,
        location: { href: 'https://zapq.example/firma/ui/demo/' + search, search },
        history: { replaceState(_state, _title, url) { context.location.href = String(url); } },
        crypto: { randomUUID: () => 'new-key-' + ++sequence },
        FormData: class extends Map {
            constructor(form) { super(Object.entries(form.elements).map(([name, field]) => [name, field.value])); }
        },
        fetch: async (path, options) => { calls.push({ path, options }); return handler(path, options); },
        setTimeout() { throw new Error('Unexpected polling timer'); },
        setInterval() { throw new Error('Unexpected polling timer'); }
    });
    vm.runInContext(source, context);
    return { node, form, calls, context, submit: () => form.events.submit({ preventDefault() {} }) };
}

test('PHP form submits CSRF and exact JSON; result is read locally without timers', async () => {
    const ui = page(path => path === 'run.php' ? json({ runId }) : json({
        webhookReceived: true, status: 'Ok', lastOutput: '<script>not HTML</script>',
        receivedAt: '2026-10-22T10:00:00Z', webhook: { data: { runId, status: 'ok' } }
    }));
    await ui.submit();
    assert.deepEqual(ui.calls.map(c => c.path), ['run.php', 'status.php?runId=' + runId]);
    const request = ui.calls[0].options;
    assert.equal(request.method, 'POST');
    assert.equal(request.body.get('input'), '{ "number": 9007199254740993 }');
    assert.equal(request.body.get('__RequestVerificationToken'), 'csrf-value');
    assert.equal(request.body.get('idempotencyKey'), 'initial-key');
    assert.equal(request.credentials, 'same-origin');
    assert.equal(request.redirect, 'error');
    assert.equal(ui.node('#run-status').textContent, 'Ok');
    assert.equal(ui.node('#output').textContent, '<script>not HTML</script>');
    assert.equal(ui.node('#result-error').textContent, '');
    assert.equal(ui.node('#result').hidden, false);
    assert.ok(ui.context.location.href.endsWith('?runId=' + runId));
});

test('waiting is updated only when the user reads the received webhook again', async () => {
    let delivered = false;
    const ui = page(path => path === 'run.php' ? json({ runId }) : json({ webhookReceived: delivered, status: 'Ok' }));
    await ui.submit();
    assert.equal(ui.node('#run-status').textContent, 'Oczekiwanie na webhook');
    delivered = true;
    await ui.node('#refresh').events.click();
    assert.equal(ui.node('#run-status').textContent, 'Ok');
    assert.equal(ui.calls.length, 3);
    assert.equal(ui.calls.filter(c => c.path === 'run.php').length, 1);
});

test('a lost start response retains the idempotency key for manual retry', async () => {
    let lost = true;
    const ui = page(path => {
        if (lost) {
            lost = false;
            throw new TypeError('Connection lost');
        }

        return path === 'run.php' ? json({ runId }) : json({ webhookReceived: false });
    });
    await ui.submit();
    assert.equal(ui.form.elements.idempotencyKey.value, 'initial-key');
    assert.match(ui.node('#message').textContent, /Nie wiadomo/);
    await ui.submit();
    assert.equal(ui.calls[0].options.body.get('idempotencyKey'), ui.calls[1].options.body.get('idempotencyKey'));
    assert.notEqual(ui.form.elements.idempotencyKey.value, 'initial-key');
});

test('invalid JSON is not submitted and an idempotency conflict creates a new key', async () => {
    const ui = page(() => json({}, 409));
    ui.form.elements.input.value = '{';
    await ui.submit();
    assert.equal(ui.calls.length, 0);
    ui.form.elements.input.value = '{}';
    await ui.submit();
    assert.equal(ui.calls.length, 1);
    assert.notEqual(ui.form.elements.idempotencyKey.value, 'initial-key');
    assert.match(ui.node('#message').textContent, /Wygenerowano nowy/);
});

test('opening a saved run URL only reads its local result', async () => {
    const ui = page(() => json({ webhookReceived: true, status: 'Cancelled' }), '?runId=' + runId);
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(ui.calls.map(c => c.path), ['status.php?runId=' + runId]);
    assert.equal(ui.node('#run-status').textContent, 'Cancelled');
});
