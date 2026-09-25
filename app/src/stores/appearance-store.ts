import { create } from 'zustand';

import { applyNativeAppearance, readAppearance, writeAppearance, type AppearancePreference } from '@/lib/appearance';

type AppearanceState = {
  preference: AppearancePreference;
  hydrated: boolean;
  saving: boolean;
  error: string | null;
  hydrate: () => Promise<void>;
  setPreference: (preference: AppearancePreference) => Promise<void>;
};

export const useAppearanceStore = create<AppearanceState>((set, get) => ({
  preference: 'auto',
  hydrated: false,
  saving: false,
  error: null,
  hydrate: async () => {
    if (get().hydrated) return;
    let preference: AppearancePreference = 'auto';
    try { preference = await readAppearance(); } catch { /* Use the device theme if storage is unavailable. */ }
    applyNativeAppearance(preference);
    set({ preference, hydrated: true });
  },
  setPreference: async (preference) => {
    if (get().saving) return;
    applyNativeAppearance(preference);
    set({ preference, saving: true, error: null });
    try {
      await writeAppearance(preference);
    } catch {
      set({ error: 'Appearance changed, but could not be saved. Choose it again to retry.' });
    } finally {
      set({ saving: false });
    }
  },
}));
