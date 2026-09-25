import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect } from 'react';
import { useColorScheme } from 'react-native';

import { AuthShell } from '@/components/auth/auth-shell';
import { PrimaryButton } from '@/components/auth/primary-button';
import { FormMessage } from '@/components/auth/form-message';
import { AppProvider } from '@/providers/app-provider';
import { initializeSentry, withSentry } from '@/lib/sentry';
import { useAuthStore } from '@/stores/auth-store';
import { useOnboardingStore } from '@/stores/onboarding-store';

void SplashScreen.preventAutoHideAsync();
initializeSentry();

function RootNavigator() {
  const authStatus = useAuthStore((state) => state.status);
  const onboardingStatus = useOnboardingStore((state) => state.status);
  const token = useAuthStore((state) => state.token);
  const hydrationError = useAuthStore((state) => state.hydrationError);
  const hydrate = useAuthStore((state) => state.hydrate);

  useEffect(() => {
    if (authStatus !== 'hydrating' && onboardingStatus !== 'hydrating') {
      void SplashScreen.hideAsync();
    }
  }, [authStatus, onboardingStatus]);

  if (authStatus === 'hydrating' || onboardingStatus === 'hydrating') {
    return null;
  }

  if (authStatus === 'unavailable') {
    return (
      <AuthShell title="Let’s reconnect" subtitle="Your session is saved. We need a connection to securely open your account.">
        {hydrationError ? <FormMessage>{hydrationError}</FormMessage> : null}
        <PrimaryButton label="Retry connection" onPress={() => void hydrate()} />
      </AuthShell>
    );
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="onboarding" />
      <Stack.Screen name="auth" />
      <Stack.Screen name="group-invites/accept" />
      <Stack.Protected guard={!token}>
        <Stack.Screen name="(auth)" />
      </Stack.Protected>
      <Stack.Protected guard={Boolean(token)}>
        <Stack.Screen name="(app)" />
      </Stack.Protected>
    </Stack>
  );
}

function RootLayout() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <AppProvider>
        <RootNavigator />
      </AppProvider>
    </ThemeProvider>
  );
}

export default withSentry(RootLayout);
