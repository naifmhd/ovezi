import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';

import { draftStorage } from '@/lib/draft-storage';
import type { ExpenseType, SplitType } from '@/types/api';

export type ExpenseDraftParticipant = {
  key: string;
  name: string;
  userId?: number;
  placeholderId?: number;
  selected: boolean;
  value: string;
};

export type ExpenseDraft = {
  userId: number;
  destination: ExpenseType;
  selectedGroupId: number | null;
  selectedPlaceholderId: number | null;
  selectedFriendId: number | null;
  description: string;
  amount: string;
  currency: string;
  category: string;
  occurredOn: string;
  splitType: SplitType;
  participants: ExpenseDraftParticipant[] | null;
  payerKey: string;
  expenseRate: string;
  recalculateRate: boolean;
  savedAt: string;
};

type ExpenseDraftState = {
  draft: ExpenseDraft | null;
  hydrated: boolean;
  saveDraft: (draft: ExpenseDraft) => void;
  clearDraft: () => void;
  markHydrated: () => void;
};

export const useExpenseDraftStore = create<ExpenseDraftState>()(
  persist(
    (set) => ({
      draft: null,
      hydrated: false,
      saveDraft: (draft) => set({ draft }),
      clearDraft: () => set({ draft: null }),
      markHydrated: () => set({ hydrated: true }),
    }),
    {
      name: 'expense-draft',
      storage: createJSONStorage(() => draftStorage),
      partialize: (state) => ({ draft: state.draft }),
      onRehydrateStorage: () => (state) => state?.markHydrated(),
    },
  ),
);
