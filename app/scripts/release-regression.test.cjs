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

test('appearance Auto follows the device and explicit choices override it', () => {
  const applied = [];
  const appearance = load('appearance', {
    'expo-secure-store': {},
    'react-native': { Platform: { OS: 'ios' }, Appearance: { setColorScheme: (value) => applied.push(value) } },
  });
  assert.equal(appearance.resolveColorScheme('auto', 'dark'), 'dark');
  assert.equal(appearance.resolveColorScheme('auto', 'light'), 'light');
  assert.equal(appearance.resolveColorScheme('auto', 'unspecified'), 'light');
  assert.equal(appearance.resolveColorScheme('light', 'dark'), 'light');
  assert.equal(appearance.resolveColorScheme('dark', 'light'), 'dark');
  ['dark', 'light', 'auto'].forEach(appearance.applyNativeAppearance);
  assert.deepEqual(applied, ['dark', 'light', 'unspecified']);
});

test('appearance preference restores from storage and rejects invalid saved values', async () => {
  let saved = null;
  const appearance = load('appearance', {
    'expo-secure-store': { getItemAsync: async () => saved, setItemAsync: async (_key, value) => { saved = value; } },
    'react-native': { Platform: { OS: 'android' } },
  });
  assert.equal(await appearance.readAppearance(), 'auto');
  await appearance.writeAppearance('dark');
  assert.equal(await appearance.readAppearance(), 'dark');
  await appearance.writeAppearance('auto');
  assert.equal(await appearance.readAppearance(), 'auto');
  saved = 'invalid';
  assert.equal(await appearance.readAppearance(), 'auto');
});

test('unavailable appearance storage does not block startup or an immediate theme change', async () => {
  const applied = [];
  const { useAppearanceStore: store } = load('../stores/appearance-store', {
    zustand: require('zustand'),
    '@/lib/appearance': {
      readAppearance: async () => { throw Error('storage unavailable'); },
      writeAppearance: async () => { throw Error('storage unavailable'); },
      applyNativeAppearance: (value) => applied.push(value),
    },
  });
  await store.getState().hydrate();
  assert.equal(store.getState().hydrated, true);
  assert.equal(store.getState().preference, 'auto');
  const save = store.getState().setPreference('dark');
  assert.equal(store.getState().preference, 'dark');
  await save;
  assert.equal(store.getState().saving, false);
  assert.match(store.getState().error, /could not be saved/);
  assert.deepEqual(applied, ['auto', 'dark']);
});

test('live updates resolve the installed native Pusher bundle through Metro interop', () => {
  const nativeModule = { exports: {} };
  const nativeBundle = fs.readFileSync(require.resolve('pusher-js/dist/react-native/pusher.js'), 'utf8');
  vm.runInNewContext(nativeBundle, {
    module: nativeModule,
    require: (name) => {
      assert.equal(name, '@react-native-community/netinfo');
      return { fetch: async () => ({ type: 'wifi' }), addEventListener: () => () => {} };
    },
    setTimeout, clearTimeout, setInterval, clearInterval,
  });
  const { resolvePusherConstructor } = load('pusher-client');
  const nativePusher = nativeModule.exports.Pusher;
  assert.equal(typeof nativePusher, 'function');
  assert.equal(resolvePusherConstructor(nativeModule.exports), nativePusher);
  assert.equal(resolvePusherConstructor({ default: nativeModule.exports }), nativePusher);
  assert.equal(typeof nativePusher.prototype.connect, 'function');
});

test('live updates retain the installed web client export and reject invalid modules', () => {
  const { resolvePusherConstructor } = load('pusher-client');
  const webModule = { exports: {} };
  vm.runInNewContext(fs.readFileSync(require.resolve('pusher-js/dist/web/pusher.js'), 'utf8'), {
    module: webModule, exports: webModule.exports, self: {},
    setTimeout, clearTimeout, setInterval, clearInterval,
  });
  const WebPusher = webModule.exports;
  assert.equal(typeof WebPusher, 'function');
  assert.equal(resolvePusherConstructor({ default: WebPusher }), WebPusher);
  assert.equal(resolvePusherConstructor(WebPusher), WebPusher);
  assert.throws(() => resolvePusherConstructor({ default: {} }), /Pusher constructor/);
});

test('native sockets use the API website origin while retaining the Reverb destination', () => {
  const calls = [];
  class NativeWebSocket {
    constructor(...args) { calls.push(args); }
  }
  const { configureNativePusherOrigin } = load('pusher-client', {}, { WebSocket: NativeWebSocket });
  const client = { Runtime: {} };
  configureNativePusherOrigin(client, 'https://ovezi.ninesixty.mv/api/v1');
  const socket = client.Runtime.createWebSocket('wss://reverb.example.test/app/public-key');
  assert(socket instanceof NativeWebSocket);
  assert.equal(calls[0][0], 'wss://reverb.example.test/app/public-key');
  assert.equal(calls[0][1], undefined);
  assert.deepEqual(JSON.parse(JSON.stringify(calls[0][2])), {
    headers: { Origin: 'https://ovezi.ninesixty.mv' },
  });
});

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
