import * as FileSystem from 'expo-file-system/legacy';
import { Platform } from 'react-native';
import type { StateStorage } from 'zustand/middleware';

const webKeyPrefix = 'ovezi.';
const nativeDirectory = FileSystem.documentDirectory;

function nativeFile(key: string) {
  return nativeDirectory ? `${nativeDirectory}${key}.json` : null;
}

export const draftStorage: StateStorage = {
  getItem: async (key) => {
    if (Platform.OS === 'web') return globalThis.localStorage?.getItem(`${webKeyPrefix}${key}`) ?? null;

    const file = nativeFile(key);
    if (!file) return null;

    try {
      return await FileSystem.readAsStringAsync(file);
    } catch {
      return null;
    }
  },
  setItem: async (key, value) => {
    if (Platform.OS === 'web') {
      globalThis.localStorage?.setItem(`${webKeyPrefix}${key}`, value);
      return;
    }

    const file = nativeFile(key);
    if (file) await FileSystem.writeAsStringAsync(file, value);
  },
  removeItem: async (key) => {
    if (Platform.OS === 'web') {
      globalThis.localStorage?.removeItem(`${webKeyPrefix}${key}`);
      return;
    }

    const file = nativeFile(key);
    if (!file) return;

    try {
      await FileSystem.deleteAsync(file, { idempotent: true });
    } catch {
      // A missing or unavailable draft file is already effectively removed.
    }
  },
};
