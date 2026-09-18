import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const tokenKey = 'ovezi.auth-token';

export async function readToken() {
  if (Platform.OS === 'web') {
    return globalThis.sessionStorage?.getItem(tokenKey) ?? null;
  }

  return SecureStore.getItemAsync(tokenKey);
}

export async function writeToken(token: string) {
  if (Platform.OS === 'web') {
    globalThis.sessionStorage?.setItem(tokenKey, token);
    return;
  }

  await SecureStore.setItemAsync(tokenKey, token, {
    keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
  });
}

export async function deleteToken() {
  if (Platform.OS === 'web') {
    globalThis.sessionStorage?.removeItem(tokenKey);
    return;
  }

  await SecureStore.deleteItemAsync(tokenKey);
}
