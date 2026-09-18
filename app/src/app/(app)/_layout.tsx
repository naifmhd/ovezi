import { Stack } from 'expo-router';

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="groups/create" options={{ presentation: 'modal' }} />
      <Stack.Screen name="groups/[id]" />
      <Stack.Screen name="expenses/create" options={{ presentation: 'modal' }} />
      <Stack.Screen name="settlements/create" options={{ presentation: 'modal' }} />
    </Stack>
  );
}
