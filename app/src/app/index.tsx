import { Redirect } from 'expo-router';

import { useAuthStore } from '@/stores/auth-store';

export default function IndexScreen() {
  const token = useAuthStore((state) => state.token);

  return <Redirect href={token ? '/(app)' : '/(auth)/sign-in'} />;
}
