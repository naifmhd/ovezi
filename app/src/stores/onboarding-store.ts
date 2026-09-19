import { create } from 'zustand';

import { readOnboardingComplete, writeOnboardingComplete } from '@/lib/onboarding-storage';

type OnboardingState = {
  status: 'hydrating' | 'ready';
  completed: boolean;
  hydrate: () => Promise<void>;
  complete: () => Promise<void>;
};

export const useOnboardingStore = create<OnboardingState>((set) => ({
  status: 'hydrating',
  completed: false,

  hydrate: async () => {
    try {
      set({ completed: await readOnboardingComplete(), status: 'ready' });
    } catch {
      set({ completed: false, status: 'ready' });
    }
  },

  complete: async () => {
    try {
      await writeOnboardingComplete();
    } catch {
      // Do not trap someone in onboarding when device storage is unavailable.
    }

    set({ completed: true, status: 'ready' });
  },
}));
