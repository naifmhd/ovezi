import { useSyncExternalStore } from 'react';
import { resolveColorScheme } from '@/lib/appearance';
import { useAppearanceStore } from '@/stores/appearance-store';

function subscribe(onChange: () => void) {
  const query = window.matchMedia('(prefers-color-scheme: dark)');
  query.addEventListener('change', onChange);
  return () => query.removeEventListener('change', onChange);
}

const getSnapshot = () => window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
const getServerSnapshot = () => 'light';

/**
 * To support static rendering, this value needs to be re-calculated on the client side for web
 */
export function useColorScheme() {
  const colorScheme = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
  const preference = useAppearanceStore((state) => state.preference);

  return resolveColorScheme(preference, colorScheme);
}
