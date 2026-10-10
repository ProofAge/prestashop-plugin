// Behaviour tests for views/js/gate.js. Run: node --test tests/js
const { test } = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../views/js/gate.js'), 'utf8');

/** Loads gate.js into a fake browser; status responses are served from `statuses` in order. */
function load(statuses) {
  const button = { disabled: false, hidden: false, closest: () => button };
  const status = { textContent: '' };
  const fetches = [];
  const timers = [];
  let clickHandler = null;
  const sdk = {
    handlers: {},
    init() {},
    start() {},
    onComplete(fn) { this.handlers.complete = fn; },
    onClose(fn) { this.handlers.close = fn; },
    onError(fn) { this.handlers.error = fn; },
  };
  const json = (body) => Promise.resolve({ status: 200, json: () => Promise.resolve(body) });
  const window = {
    KycService: sdk,
    location: { assign() {} },
    proofageGate: {
      sessionUrl: '/session', statusUrl: '/status', token: 't', launchMode: 'modal', sdkUrl: '/sdk.js',
      publicKey: 'pk', language: 'en', backUrl: '/back', returnMode: false, cookieMissing: false,
      texts: { starting: 'starting', inProgress: 'inProgress', checking: 'checking', review: 'review',
        stillPending: 'stillPending', approved: 'approved', declined: 'declined', retry: 'retry',
        reset: 'reset', rateLimited: 'rateLimited', error: 'error', insecure: 'insecure' },
    },
  };
  const context = {
    window,
    document: {
      querySelectorAll: (selector) => (selector === '[data-proofage-status]' ? [status] : [button]),
      addEventListener: (type, fn) => { if (type === 'click') { clickHandler = fn; } },
      createElement: () => ({}),
      head: { appendChild() {} },
    },
    fetch: (url) => {
      fetches.push(url);
      if (url === '/session') {
        return json({ verification_id: 'v1', url: 'https://idv.proofage.net/v/x', launch_mode: 'modal' });
      }
      return json(statuses.shift() || { state: 'pending', status: 'started' });
    },
    URLSearchParams,
    setTimeout: (fn) => { timers.push(fn); },
    Promise,
  };
  vm.runInNewContext(source, context);

  return {
    button, status, fetches, timers, sdk,
    click: () => clickHandler({ target: button, preventDefault() {} }),
    statusFetches: () => fetches.filter((u) => u.indexOf('/status') === 0).length,
  };
}

const flush = () => new Promise((resolve) => setImmediate(resolve));

test('closing the verification window without finishing lets the visitor reopen it at once', async () => {
  const page = load([{ state: 'pending', status: 'started' }]);
  page.click();
  await flush();
  assert.ok(page.sdk.handlers.close, 'SDK opened');

  page.sdk.handlers.close();
  await flush();

  assert.strictEqual(page.statusFetches(), 1, 'one status check after closing');
  assert.strictEqual(page.timers.length, 0, 'no background polling after closing');
  assert.strictEqual(page.button.disabled, false, 'button usable again');
  assert.strictEqual(page.status.textContent, 'stillPending');
});

test('closing after an approval still redirects', async () => {
  const page = load([{ state: 'approved', redirect: '/product' }]);
  page.click();
  await flush();
  page.sdk.handlers.close();
  await flush();
  assert.strictEqual(page.status.textContent, 'approved');
});

test('completing the flow keeps polling until a decision', async () => {
  const page = load([{ state: 'pending', status: 'review' }, { state: 'approved', redirect: '/product' }]);
  page.click();
  await flush();
  page.sdk.handlers.complete();
  await flush();

  assert.strictEqual(page.statusFetches(), 1);
  assert.strictEqual(page.timers.length, 1, 'keeps polling while pending');
  assert.strictEqual(page.button.disabled, true, 'button stays disabled while waiting for the outcome');

  page.timers.shift()();
  await flush();
  assert.strictEqual(page.status.textContent, 'approved');
});

test('a completion reported right after the close still waits for the outcome', async () => {
  const page = load([{ state: 'pending', status: 'submitted' }, { state: 'approved', redirect: '/product' }]);
  page.click();
  await flush();
  page.sdk.handlers.close();
  page.sdk.handlers.complete();
  await flush();

  assert.strictEqual(page.timers.length, 1, 'completion upgrades the single check to full polling');
  page.timers.shift()();
  await flush();
  assert.strictEqual(page.status.textContent, 'approved');
});
