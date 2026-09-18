import { Stack } from 'expo-router';

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="groups/create" options={{ presentation: 'modal' }} />
      <Stack.Screen name="groups/[id]" />
      <Stack.Screen name="groups/[id]/settings" />
      <Stack.Screen name="groups/[id]/members" />
      <Stack.Screen name="expenses/create" options={{ presentation: 'modal' }} />
      <Stack.Screen name="expenses/index" />
      <Stack.Screen name="expenses/[id]" />
      <Stack.Screen name="settlements/create" options={{ presentation: 'modal' }} />
      <Stack.Screen name="profile/edit" />
      <Stack.Screen name="profile/security" />
      <Stack.Screen name="profile/placeholder-claims" />
      <Stack.Screen name="friends/index" />
      <Stack.Screen name="search/index" />
    </Stack>
  );
}
