import { Stack } from 'expo-router';
import { Platform } from 'react-native';

export default function AppLayout() {
  return (
    <Stack screenOptions={{
      headerShown: false,
      animation: Platform.OS === 'ios' ? 'default' : 'fade_from_bottom',
      animationDuration: 240,
    }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="groups/create" options={{ presentation: Platform.OS === 'ios' ? 'formSheet' : 'modal' }} />
      <Stack.Screen name="groups/[id]" />
      <Stack.Screen name="groups/[id]/activity" />
      <Stack.Screen name="groups/[id]/settings" />
      <Stack.Screen name="groups/[id]/members" />
      <Stack.Screen name="expenses/create" options={{ presentation: Platform.OS === 'ios' ? 'formSheet' : 'modal' }} />
      <Stack.Screen name="expenses/index" />
      <Stack.Screen name="expenses/[id]" />
      <Stack.Screen name="settlements/create" options={{ presentation: Platform.OS === 'ios' ? 'formSheet' : 'modal' }} />
      <Stack.Screen name="settlements/direct" options={{ presentation: Platform.OS === 'ios' ? 'formSheet' : 'modal' }} />
      <Stack.Screen name="profile/edit" />
      <Stack.Screen name="profile/security" />
      <Stack.Screen name="profile/notifications" />
      <Stack.Screen name="profile/placeholder-claims" />
      <Stack.Screen name="profile/recurring-expenses" />
      <Stack.Screen name="profile/recurring-expenses/[id]" />
      <Stack.Screen name="profile/about" />
      <Stack.Screen name="profile/privacy" />
      <Stack.Screen name="profile/terms" />
      <Stack.Screen name="friends/index" />
      <Stack.Screen name="search/index" />
    </Stack>
  );
}
