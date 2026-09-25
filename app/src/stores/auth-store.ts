import { create } from 'zustand';

import { ApiError, errorMessage } from '@/lib/api-client';
import { fetchMe, logout as requestLogout } from '@/lib/auth-api';
import { unregisterPushDevice } from '@/lib/push-notifications';
import { deleteToken, readToken, writeToken } from '@/lib/session-storage';
import type { AuthSession, User } from '@/types/api';

type AuthState = {
  status: 'hydrating' | 'ready' | 'unavailable';
  hydrationError: string | null;
  sessionVersion: number;
  token: string | null;
  user: User | null;
  hydrate: () => Promise<void>;
  setSession: (session: AuthSession) => Promise<void>;
  refreshUser: () => Promise<void>;
  logout: () => Promise<void>;
};

let authOperation = 0;

export const useAuthStore = create<AuthState>((set, get) => ({
  status: 'hydrating',
  hydrationError: null,
  sessionVersion: 0,
  token: null,
  user: null,

  hydrate: async () => {
    const operation = ++authOperation;
    set({ status: 'hydrating' });
    try {
      const token = await readToken();
      if (operation !== authOperation) return;
      if (!token) {
        set({ status: 'ready', token: null, user: null, hydrationError: null });
        return;
      }
      const user = await fetchMe(token);
      if (operation !== authOperation) return;
      set({ status: 'ready', token, user, hydrationError: null });
    } catch (error) {
      if (operation !== authOperation) return;
      if (error instanceof ApiError && error.status === 401) {
        try { await deleteToken(); } catch { /* Keep the failed credential out of memory. */ }
        if (operation !== authOperation) return;
        set({ status: 'ready', token: null, user: null, hydrationError: null });
      } else {
        // Keep the stored credential, but do not expose account content until
        // the server has verified the identity on a successful retry.
        set({ status: 'unavailable', token: null, user: null, hydrationError: errorMessage(error) });
      }
    }
  },

  setSession: async ({ token, user }) => {
    const operation = ++authOperation;
    await writeToken(token);
    if (operation !== authOperation) return;
    set((state) => ({ status: 'ready', token, user, hydrationError: null, sessionVersion: state.sessionVersion + 1 }));
  },

  refreshUser: async () => {
    const token = get().token;

    if (token) {
      const user = await fetchMe(token);
      if (get().token === token) set({ user });
    }
  },

  logout: async () => {
    const token = get().token;

    ++authOperation;
    set((state) => ({ token: null, user: null, status: 'ready', hydrationError: null, sessionVersion: state.sessionVersion + 1 }));
    await deleteToken();

    if (token) {
      try {
        await unregisterPushDevice(token);
      } catch {
        // Token cleanup is best-effort when the device is offline.
      }

      try {
        await requestLogout(token);
      } catch {
        // The local session is already cleared; an expired server token needs no recovery.
      }
    }
  },
}));
