import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect } from 'react';
import { useColorScheme } from 'react-native';

import { AppProvider } from '@/providers/app-provider';
import { useAuthStore } from '@/stores/auth-store';
import { useOnboardingStore } from '@/stores/onboarding-store';

void SplashScreen.preventAutoHideAsync();

function RootNavigator() {
  const authStatus = useAuthStore((state) => state.status);
  const onboardingStatus = useOnboardingStore((state) => state.status);
  const token = useAuthStore((state) => state.token);

  useEffect(() => {
    if (authStatus !== 'hydrating' && onboardingStatus !== 'hydrating') {
      void SplashScreen.hideAsync();
    }
  }, [authStatus, onboardingStatus]);

  if (authStatus === 'hydrating' || onboardingStatus === 'hydrating') {
    return null;
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

export default function RootLayout() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <AppProvider>
        <RootNavigator />
      </AppProvider>
    </ThemeProvider>
  );
}
