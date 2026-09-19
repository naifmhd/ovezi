import { Redirect } from 'expo-router';

import { useAuthStore } from '@/stores/auth-store';
import { useOnboardingStore } from '@/stores/onboarding-store';

export default function IndexScreen() {
  const token = useAuthStore((state) => state.token);
  const onboardingComplete = useOnboardingStore((state) => state.completed);

  if (token) return <Redirect href="/(app)" />;

  return <Redirect href={onboardingComplete ? '/(auth)/sign-in' : '/onboarding'} />;
}
