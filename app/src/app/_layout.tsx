import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect } from 'react';
import { useColorScheme } from 'react-native';

import { AppProvider } from '@/providers/app-provider';
import { useAuthStore } from '@/stores/auth-store';

void SplashScreen.preventAutoHideAsync();

function RootNavigator() {
  const status = useAuthStore((state) => state.status);
  const token = useAuthStore((state) => state.token);

  useEffect(() => {
    if (status !== 'hydrating') {
      void SplashScreen.hideAsync();
    }
  }, [status]);

  if (status === 'hydrating') {
    return null;
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
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
