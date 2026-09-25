import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import * as SystemUI from 'expo-system-ui';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { AuthShell } from '@/components/auth/auth-shell';
import { PrimaryButton } from '@/components/auth/primary-button';
import { FormMessage } from '@/components/auth/form-message';
import { AppProvider } from '@/providers/app-provider';
import { initializeSentry, withSentry } from '@/lib/sentry';
import { useAuthStore } from '@/stores/auth-store';
import { useOnboardingStore } from '@/stores/onboarding-store';
import { useAppearanceStore } from '@/stores/appearance-store';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { useTheme } from '@/hooks/use-theme';

void SplashScreen.preventAutoHideAsync();
initializeSentry();

function RootNavigator() {
  const authStatus = useAuthStore((state) => state.status);
  const onboardingStatus = useOnboardingStore((state) => state.status);
  const appearanceHydrated = useAppearanceStore((state) => state.hydrated);
  const token = useAuthStore((state) => state.token);
  const hydrationError = useAuthStore((state) => state.hydrationError);
  const hydrate = useAuthStore((state) => state.hydrate);

  useEffect(() => {
    if (authStatus !== 'hydrating' && onboardingStatus !== 'hydrating' && appearanceHydrated) {
      void SplashScreen.hideAsync();
    }
  }, [authStatus, onboardingStatus, appearanceHydrated]);

  if (authStatus === 'hydrating' || onboardingStatus === 'hydrating' || !appearanceHydrated) {
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
  const theme = useTheme();
  const navigationTheme = colorScheme === 'dark' ? DarkTheme : DefaultTheme;

  useEffect(() => {
    void SystemUI.setBackgroundColorAsync(theme.background).catch(() => {});
    if (Platform.OS === 'web') document.documentElement.style.colorScheme = colorScheme;
  }, [colorScheme, theme.background]);

  return (
    <ThemeProvider value={{ ...navigationTheme, colors: { ...navigationTheme.colors, background: theme.background, card: theme.surface, text: theme.text, border: theme.border, primary: theme.interactive } }}>
      <StatusBar style={colorScheme === 'dark' ? 'light' : 'dark'} />
      <AppProvider>
        <RootNavigator />
      </AppProvider>
    </ThemeProvider>
  );
}

export default withSentry(RootLayout);
