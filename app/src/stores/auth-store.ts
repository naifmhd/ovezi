import { create } from 'zustand';

import { fetchMe, logout as requestLogout } from '@/lib/auth-api';
import { deleteToken, readToken, writeToken } from '@/lib/session-storage';
import type { AuthSession, User } from '@/types/api';

type AuthState = {
  status: 'hydrating' | 'ready';
  token: string | null;
  user: User | null;
  hydrate: () => Promise<void>;
  setSession: (session: AuthSession) => Promise<void>;
  refreshUser: () => Promise<void>;
  logout: () => Promise<void>;
};

export const useAuthStore = create<AuthState>((set, get) => ({
  status: 'hydrating',
  token: null,
  user: null,

  hydrate: async () => {
    try {
      const token = await readToken();

      if (!token) {
        set({ status: 'ready' });
        return;
      }

      const user = await fetchMe(token);
      set({ status: 'ready', token, user });
    } catch {
      try {
        await deleteToken();
      } catch {
        // Continue into a signed-out state even if the device store is unavailable.
      }
      set({ status: 'ready', token: null, user: null });
    }
  },

  setSession: async ({ token, user }) => {
    await writeToken(token);
    set({ status: 'ready', token, user });
  },

  refreshUser: async () => {
    const token = get().token;

    if (token) {
      set({ user: await fetchMe(token) });
    }
  },

  logout: async () => {
    const token = get().token;

    set({ token: null, user: null, status: 'ready' });
    await deleteToken();

    if (token) {
      try {
        await requestLogout(token);
      } catch {
        // The local session is already cleared; an expired server token needs no recovery.
      }
    }
  },
}));
