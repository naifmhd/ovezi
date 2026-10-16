import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const dismissed = new Set<number>();
const key = (userId: number) => `ovezi.navigation-guide-1.2.${userId}`;

export async function isReleaseGuideDismissed(userId: number) {
  if (dismissed.has(userId)) return true;
  try {
    const value = Platform.OS === 'web'
      ? globalThis.localStorage?.getItem(key(userId))
      : await SecureStore.getItemAsync(key(userId));
    if (value === 'true') dismissed.add(userId);
  } catch { /* The guide remains optional when storage is unavailable. */ }
  return dismissed.has(userId);
}

export async function dismissReleaseGuide(userId: number) {
  dismissed.add(userId);
  try {
    if (Platform.OS === 'web') globalThis.localStorage?.setItem(key(userId), 'true');
    else await SecureStore.setItemAsync(key(userId), 'true', { keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY });
  } catch { /* Respect dismissal for this session even if persistence fails. */ }
}
