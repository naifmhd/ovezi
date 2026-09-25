import type Pusher from 'pusher-js';

export function configureNativePusherOrigin(client: typeof Pusher, apiUrl: string): void {
  const origin = new URL(apiUrl).origin;
  const NativeWebSocket = WebSocket as unknown as {
    new (url: string, protocols: undefined, options: { headers: { Origin: string } }): ReturnType<typeof client.Runtime.createWebSocket>;
  };

  // Native sockets otherwise omit Origin or use the WebSocket host instead of our website.
  client.Runtime.createWebSocket = (url: string) => new NativeWebSocket(url, undefined, {
    headers: { Origin: origin },
  });
}

export function resolvePusherConstructor(module: unknown): typeof Pusher {
  const exports = module as { Pusher?: unknown; default?: unknown } | null;
  const defaultExport = exports?.default as { Pusher?: unknown } | null;
  const candidates = [module, exports?.Pusher, exports?.default, defaultExport?.Pusher];

  // The native bundle exposes { Pusher }, while web and Node expose the constructor directly.
  for (const candidate of candidates) {
    if (typeof candidate === 'function') return candidate as typeof Pusher;
  }

  throw new Error('The live-update client did not export a Pusher constructor.');
}
