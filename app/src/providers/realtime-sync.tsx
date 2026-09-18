import { useQueryClient } from '@tanstack/react-query';
import type Echo from 'laravel-echo';
import { useEffect } from 'react';

import { apiBaseUrl } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

type DomainChange = {
  resource: 'expense' | 'settlement' | 'group' | 'group_member';
  action: 'created' | 'updated' | 'deleted' | 'restored';
  resource_id: number;
  group_id: number | null;
};

const reverbKey = process.env.EXPO_PUBLIC_REVERB_APP_KEY;
const reverbHost = process.env.EXPO_PUBLIC_REVERB_HOST;
const reverbPort = Number(process.env.EXPO_PUBLIC_REVERB_PORT ?? 443);
const reverbScheme = process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'https';

export function RealtimeSync() {
  const queryClient = useQueryClient();
  const token = useAuthStore((state) => state.token);
  const user = useAuthStore((state) => state.user);

  useEffect(() => {
    if (!token || !user || !reverbKey || !reverbHost || !Number.isFinite(reverbPort) || reverbPort <= 0) {
      return;
    }

    let cancelled = false;
    let echo: Echo<'reverb'> | null = null;

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
      });

      echo.private(`users.${user.id}`).listen('.domain.changed', (change: DomainChange) => {
        const invalidations = [
          queryClient.invalidateQueries({ queryKey: ['groups'] }),
          queryClient.invalidateQueries({ queryKey: ['expenses'] }),
          queryClient.invalidateQueries({ queryKey: ['activity'] }),
          queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
          queryClient.invalidateQueries({ queryKey: ['search'] }),
        ];

        if (change.resource === 'expense') {
          invalidations.push(queryClient.invalidateQueries({ queryKey: ['expense', change.resource_id] }));
        }

        if (change.group_id) {
          invalidations.push(queryClient.invalidateQueries({ queryKey: ['group', change.group_id] }));
        }

        if (change.resource === 'group_member') {
          invalidations.push(queryClient.invalidateQueries({ queryKey: ['placeholder-claims'] }));
          invalidations.push(queryClient.invalidateQueries({ queryKey: ['placeholders'] }));
        }

        void Promise.all(invalidations);
      });
    }).catch(() => {
      // Realtime is an enhancement; queries continue to work through regular API refreshes.
    });

    return () => {
      cancelled = true;
      echo?.leave(`users.${user.id}`);
      echo?.disconnect();
    };
  }, [queryClient, token, user]);

  return null;
}
