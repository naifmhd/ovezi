import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const onboardingKey = 'ovezi.onboarding-complete';

export async function readOnboardingComplete() {
  if (Platform.OS === 'web') {
    return globalThis.localStorage?.getItem(onboardingKey) === 'true';
  }

  return (await SecureStore.getItemAsync(onboardingKey)) === 'true';
}

export async function writeOnboardingComplete() {
  if (Platform.OS === 'web') {
    globalThis.localStorage?.setItem(onboardingKey, 'true');
    return;
  }

  await SecureStore.setItemAsync(onboardingKey, 'true', {
    keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  });
}
