import NetInfo from '@react-native-community/netinfo';
import { useQueryClient } from '@tanstack/react-query';
import type Echo from 'laravel-echo';
import { useEffect } from 'react';
import { AppState } from 'react-native';

import { apiBaseUrl } from '@/lib/api-client';
import { addRealtimeBreadcrumb } from '@/lib/sentry';
import { useAuthStore } from '@/stores/auth-store';
import { useRealtimeStore, type RealtimeStatus } from '@/stores/realtime-store';

type DomainChange = {
  version?: number;
  event_id?: string;
  resource: 'expense' | 'settlement' | 'group' | 'group_member' | string;
  action: 'created' | 'updated' | 'deleted' | 'restored' | string;
  resource_id: number;
  group_id: number | null;
  occurred_at?: string;
};

type PusherConnection = {
  state: string;
  bind: (event: string, callback: (payload?: unknown) => void) => void;
  unbind: (event: string) => void;
};

type EchoWithConnection = Echo<'reverb'> & {
  connector: {
    pusher: {
      connect: () => void;
      disconnect: () => void;
      connection: PusherConnection;
    };
  };
};

const reverbKey = process.env.EXPO_PUBLIC_REVERB_APP_KEY;
const reverbHost = process.env.EXPO_PUBLIC_REVERB_HOST;
const reverbPort = Number(process.env.EXPO_PUBLIC_REVERB_PORT ?? 443);
const reverbScheme = process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'https';
const EVENT_COALESCE_MS = 180;

function statusForConnection(state: string): RealtimeStatus {
  if (state === 'connected') return 'connected';
  if (state === 'connecting') return 'connecting';
  if (state === 'unavailable' || state === 'failed') return 'error';
  return 'reconnecting';
}

export function RealtimeSync() {
  const queryClient = useQueryClient();
  const token = useAuthStore((state) => state.token);
  const userId = useAuthStore((state) => state.user?.id ?? null);
  const setStatus = useRealtimeStore((state) => state.setStatus);

  useEffect(() => {
    if (!token || !userId) {
      setStatus('disabled');
      return;
    }
    if (!reverbKey || !reverbHost || !Number.isFinite(reverbPort) || reverbPort <= 0) {
      setStatus('disabled');
      addRealtimeBreadcrumb('Realtime disabled: configuration is incomplete', 'warning');
      return;
    }

    let cancelled = false;
    let echo: EchoWithConnection | null = null;
    let flushTimer: ReturnType<typeof setTimeout> | null = null;
    let hasConnected = false;
    const pendingChanges = new Map<string, DomainChange>();

    function invalidateChange(change: DomainChange) {
      const invalidations: Promise<unknown>[] = [
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
      ];

      if (change.resource === 'expense') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['expenses'] }),
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['expense', change.resource_id] }),
        );
      } else if (change.resource === 'settlement') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
        );
      } else if (change.resource === 'group') {
        invalidations.push(queryClient.invalidateQueries({ queryKey: ['groups'] }));
      } else if (change.resource === 'group_member') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['groups'] }),
          queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['placeholder-claims'] }),
          queryClient.invalidateQueries({ queryKey: ['placeholders'] }),
        );
      } else if (change.resource === 'friendship') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['friends'] }),
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
        );
      } else if (change.resource === 'group_invite') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['group-invites'] }),
          queryClient.invalidateQueries({ queryKey: ['groups'] }),
        );
      } else if (change.resource === 'placeholder') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['placeholder-claims'] }),
          queryClient.invalidateQueries({ queryKey: ['placeholders'] }),
          queryClient.invalidateQueries({ queryKey: ['friends'] }),
          queryClient.invalidateQueries({ queryKey: ['groups'] }),
          queryClient.invalidateQueries({ queryKey: ['expenses'] }),
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
        );
      } else if (change.resource === 'group_currency_rate') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['group-rates'] }),
          queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        );
      } else if (change.resource === 'recurring_expense') {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['recurring-expenses'] }),
        );
      } else {
        invalidations.push(
          queryClient.invalidateQueries({ queryKey: ['groups'] }),
          queryClient.invalidateQueries({ queryKey: ['expenses'] }),
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
        );
      }

      if (change.group_id) {
        invalidations.push(queryClient.invalidateQueries({ queryKey: ['group', change.group_id] }));
      }
      invalidations.push(queryClient.invalidateQueries({ queryKey: ['search'] }));
      void Promise.all(invalidations);
    }

    function scheduleChange(change: DomainChange) {
      const key = change.event_id ?? `${change.resource}:${change.resource_id}:${change.action}`;
      pendingChanges.set(key, change);
      if (flushTimer) return;
      flushTimer = setTimeout(() => {
        flushTimer = null;
        const changes = [...pendingChanges.values()];
        pendingChanges.clear();
        changes.forEach(invalidateChange);
      }, EVENT_COALESCE_MS);
    }

    setStatus('connecting');
    addRealtimeBreadcrumb('Connecting to Reverb');

    const netInfoSubscription = NetInfo.addEventListener((network) => {
      const online = network.isConnected !== false && network.isInternetReachable !== false;
      if (!online) {
        setStatus('offline');
        return;
      }
      if (echo && echo.connector.pusher.connection.state !== 'connected') {
        setStatus('reconnecting');
        echo.connector.pusher.connect();
      }
    });

    const appStateSubscription = AppState.addEventListener('change', (nextState) => {
      if (nextState !== 'active' || !echo) return;
      if (echo.connector.pusher.connection.state !== 'connected') {
        setStatus('reconnecting');
        echo.connector.pusher.connect();
      } else {
        void queryClient.invalidateQueries({ type: 'active' });
      }
    });

    void Promise.all([import('laravel-echo'), import('pusher-js')]).then(([echoModule, pusherModule]) => {
      if (cancelled) return;

      const EchoClient = echoModule.default;
      echo = new EchoClient<'reverb'>({
        broadcaster: 'reverb',
        Pusher: pusherModule.default,
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${apiBaseUrl}/broadcasting/auth`,
        auth: {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
          },
        },
      }) as EchoWithConnection;

      const connection = echo.connector.pusher.connection;
      connection.bind('state_change', (payload: unknown) => {
        const nextState = typeof payload === 'object' && payload !== null && 'current' in payload
          ? String((payload as { current: unknown }).current)
          : connection.state;
        const nextStatus = statusForConnection(nextState);
        setStatus(nextStatus);
        addRealtimeBreadcrumb(`Reverb state: ${nextStatus}`, nextStatus === 'error' ? 'error' : 'info');
        if (nextStatus === 'connected') {
          if (hasConnected) void queryClient.invalidateQueries({ type: 'active' });
          hasConnected = true;
        }
      });
      connection.bind('error', () => {
        setStatus('error');
        addRealtimeBreadcrumb('Reverb connection error', 'error');
      });

      echo.private(`users.${userId}`).listen('.domain.changed', scheduleChange);
    }).catch(() => {
      if (cancelled) return;
      setStatus('error');
      addRealtimeBreadcrumb('Reverb client initialization failed', 'error');
    });

    return () => {
      cancelled = true;
      netInfoSubscription();
      appStateSubscription.remove();
      if (flushTimer) clearTimeout(flushTimer);
      pendingChanges.clear();
      if (echo) {
        echo.connector.pusher.connection.unbind('state_change');
        echo.connector.pusher.connection.unbind('error');
        echo.leave(`users.${userId}`);
        echo.disconnect();
      }
      setStatus('disabled');
      addRealtimeBreadcrumb('Disconnected from Reverb');
    };
  }, [queryClient, setStatus, token, userId]);

  return null;
}
