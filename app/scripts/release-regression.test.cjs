const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const ts = require('typescript');

function load(name, mocks = {}, globals = {}) {
  const code = ts.transpileModule(fs.readFileSync(path.join(__dirname, '../src/lib', name + '.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, esModuleInterop: true },
  }).outputText;
  const module = { exports: {} };
  vm.runInNewContext(code, { module, exports: module.exports, require: (id) => {
    if (id in mocks) return mocks[id];
    throw new Error('Unexpected import: ' + id);
  }, URL, Headers, AbortController, setTimeout, clearTimeout, process: { env: { EXPO_PUBLIC_API_URL: 'https://api.example.test/api/v1' } }, ...globals });
  return module.exports;
}

test('request timeout covers a response whose body stalls', async () => {
  const api = load('api-client', {}, {
    setTimeout: (fn) => setTimeout(fn, 5),
    fetch: async (_url, { signal }) => ({ ok: true, status: 200, json: () => new Promise((_, reject) => signal.addEventListener('abort', () => reject(new Error('aborted')))) }),
  });
  await assert.rejects(api.apiRequest('/expenses'), /connection took too long/);
});

test('credentials are never sent to a different origin', async () => {
  let calls = 0;
  const api = load('api-client', {}, { fetch: async () => { calls++; } });
  await assert.rejects(api.apiRequest('https://evil.example/me', { token: 'secret' }), /does not belong/);
  assert.equal(calls, 0);
});

test('caller cancellation is propagated without calling it a timeout', async () => {
  const controller = new AbortController();
  const api = load('api-client', {}, { fetch: (_url, { signal }) => new Promise((_, reject) => signal.addEventListener('abort', () => reject(new Error('cancelled')))) });
  const pending = api.apiRequest('/expenses', { signal: controller.signal });
  controller.abort();
  await assert.rejects(pending, /cancelled/);
});

function financialHarness() {
  const storage = new Map();
  const session = { user: { id: 12 }, token: 'session-a' };
  const calls = [];
  let fail = true;
  class ApiError extends Error { constructor(status) { super('API error'); this.status = status; } }
  const mocks = {
    'expo-crypto': { CryptoDigestAlgorithm: { SHA256: 'sha256' }, digestStringAsync: async (_algorithm, value) => crypto.createHash('sha256').update(value).digest('hex'), randomUUID: crypto.randomUUID },
    '@/lib/draft-storage': { draftStorage: { getItem: async (key) => storage.get(key), setItem: async (key, value) => storage.set(key, value), removeItem: async (key) => storage.delete(key) } },
    '@/stores/auth-store': { useAuthStore: { getState: () => session } },
    '@/lib/api-client': { ApiError, apiRequest: async (_path, options) => { calls.push(options); if (fail) throw new Error('connection lost'); return { id: 9 }; } },
  };
  return { storage, calls, session, mocks, succeed: () => { fail = false; }, api: () => load('financial-request', mocks) };
}

test('a restart and renewed session safely retry the same account submission', async () => {
  const h = financialHarness();
  await assert.rejects(h.api().financialRequest('/expenses', 'session-a', { amount_minor: 1250 }), /connection lost/);
  assert.equal(h.storage.size, 1);
  h.session.token = 'session-b';
  h.succeed();
  await h.api().financialRequest('/expenses', 'session-b', { amount_minor: 1250 });
  assert.equal(h.calls[0].headers['Idempotency-Key'], h.calls[1].headers['Idempotency-Key']);
  assert.equal(h.storage.size, 0);
  for (const value of h.storage.values()) assert(!value.includes('session'));
});

test('a new successful intent gets a new key and concurrent taps share one request', async () => {
  const h = financialHarness(); h.succeed(); const api = h.api();
  await Promise.all([api.financialRequest('/expenses', 'session-a', { amount_minor: 1250 }), api.financialRequest('/expenses', 'session-a', { amount_minor: 1250 })]);
  assert.equal(h.calls.length, 1);
  await api.financialRequest('/expenses', 'session-a', { amount_minor: 1250 });
  assert.notEqual(h.calls[0].headers['Idempotency-Key'], h.calls[1].headers['Idempotency-Key']);
});

test('financial saves refuse a stale account session', async () => {
  const h = financialHarness();
  await assert.rejects(h.api().financialRequest('/expenses', 'other-session', {}), /Sign in again/);
  assert.equal(h.calls.length, 0);
});


test('patched URI decoding stays compatible with Expo routing and handles hostile input', () => {
  const query = require('query-string');
  assert.equal(query.parse('name=Ahmed%20Umar&currency=MVR').name, 'Ahmed Umar');
  assert.equal(query.stringify({name:'Ahmed Umar'}), 'name=Ahmed%20Umar');
  const started = performance.now();
  query.parse('value=' + '%E0%A4%A'.repeat(10000));
  assert(performance.now() - started < 1000, 'Malformed URI decoding must remain bounded');
  const xcode = require('xcode');
  const project = xcode.project('/tmp/unused.xcodeproj');
  project.hash = { project: { objects: {} } };
  assert.match(project.generateUuid(), /^[A-F0-9]{24}$/);
});


test('expired replay receipts remain protected from another submission', async () => {
  const h = financialHarness();
  h.mocks['@/lib/api-client'].apiRequest = async () => { throw new h.mocks['@/lib/api-client'].ApiError(410); };
  await assert.rejects(h.api().financialRequest('/settlements', 'session-a', { amount_minor: 1250 }));
  const original = [...h.storage.values()][0];
  await assert.rejects(h.api().financialRequest('/settlements', 'session-a', { amount_minor: 1250 }));
  assert.equal([...h.storage.values()][0], original);
});
