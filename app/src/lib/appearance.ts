import * as SecureStore from 'expo-secure-store';
import { Appearance, Platform } from 'react-native';

export type AppearancePreference = 'auto' | 'light' | 'dark';
export const appearanceLabels = { auto: 'Auto', light: 'Light', dark: 'Dark' };
const appearanceKey = 'ovezi.appearance';

export function resolveColorScheme(preference: AppearancePreference, systemScheme: string | null | undefined): 'light' | 'dark' {
  return preference === 'auto' ? (systemScheme === 'dark' ? 'dark' : 'light') : preference;
}

export async function readAppearance(): Promise<AppearancePreference> {
  const value = Platform.OS === 'web'
    ? globalThis.localStorage?.getItem(appearanceKey)
    : await SecureStore.getItemAsync(appearanceKey);
  return value === 'light' || value === 'dark' ? value : 'auto';
}

export async function writeAppearance(preference: AppearancePreference): Promise<void> {
  if (Platform.OS === 'web') {
    globalThis.localStorage.setItem(appearanceKey, preference);
  } else {
    await SecureStore.setItemAsync(appearanceKey, preference, {
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    });
  }
}

export function applyNativeAppearance(preference: AppearancePreference): void {
  if (Platform.OS !== 'web') {
    Appearance.setColorScheme(preference === 'auto' ? 'unspecified' : preference);
  }
}
