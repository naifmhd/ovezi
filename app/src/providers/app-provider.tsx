import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { PropsWithChildren, useEffect, useState } from 'react';

import { ApiError } from '@/lib/api-client';
import { ConnectivitySync } from '@/providers/connectivity-sync';
import { PushNotificationSync } from '@/providers/push-notification-sync';
import { RealtimeSync } from '@/providers/realtime-sync';
import { useAuthStore } from '@/stores/auth-store';
import { useOnboardingStore } from '@/stores/onboarding-store';

export function AppProvider({ children }: PropsWithChildren) {
  const hydrateAuth = useAuthStore((state) => state.hydrate);
  const hydrateOnboarding = useOnboardingStore((state) => state.hydrate);
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            retry: (failureCount, error) => {
              if (error instanceof ApiError && error.status >= 400 && error.status < 500) return false;
              return failureCount < 2;
            },
            staleTime: 30_000,
          },
          mutations: { retry: false },
        },
      }),
  );

  useEffect(() => {
    void Promise.all([hydrateAuth(), hydrateOnboarding()]);
  }, [hydrateAuth, hydrateOnboarding]);

  return (
    <QueryClientProvider client={queryClient}>
      <RealtimeSync />
      <PushNotificationSync />
      {children}
      <ConnectivitySync />
    </QueryClientProvider>
  );
}
