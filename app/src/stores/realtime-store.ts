import { create } from 'zustand';

export type RealtimeStatus =
  | 'disabled'
  | 'connecting'
  | 'connected'
  | 'reconnecting'
  | 'offline'
  | 'error';

type RealtimeState = {
  status: RealtimeStatus;
  lastConnectedAt: string | null;
  setStatus: (status: RealtimeStatus) => void;
};

export const useRealtimeStore = create<RealtimeState>((set) => ({
  status: 'disabled',
  lastConnectedAt: null,
  setStatus: (status) => set({
    status,
    ...(status === 'connected' ? { lastConnectedAt: new Date().toISOString() } : {}),
  }),
}));
