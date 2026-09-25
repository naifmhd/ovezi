import { useColorScheme as useSystemColorScheme } from 'react-native';

import { resolveColorScheme } from '@/lib/appearance';
import { useAppearanceStore } from '@/stores/appearance-store';

export function useColorScheme() {
  const systemScheme = useSystemColorScheme();
  const preference = useAppearanceStore((state) => state.preference);
  return resolveColorScheme(preference, systemScheme);
}
