import { draftStorage } from '@/lib/draft-storage';
import type { LendingDirection } from '@/lib/lending-api';

export type LoanDraft = {
  contactId?: string;
  newPerson: { name: string; email: string | null } | null;
  direction: LendingDirection;
  currency: string;
  amount: string;
  description: string;
  note: string;
  date: string;
  due: string;
  shared: boolean;
};

const pending = new Map<number, Promise<void>>();
const key = (userId: number) => `loan-draft-${userId}`;

// Serialize writes and clears: a slow save must never resurrect a discarded draft.
function enqueue(userId: number, operation: () => Promise<void>) {
  const next = (pending.get(userId) ?? Promise.resolve()).catch(() => {}).then(operation);
  pending.set(userId, next);
  void next.finally(() => { if (pending.get(userId) === next) pending.delete(userId); }).catch(() => {});
  return next;
}

export function saveLoanDraft(userId: number, draft: LoanDraft) {
  return enqueue(userId, async () => {
    const value = JSON.stringify(draft);
    await draftStorage.setItem(key(userId), value);
    if (await draftStorage.getItem(key(userId)) !== value) throw new Error('Draft storage unavailable');
  });
}
export function clearLoanDraft(userId: number) {
  return enqueue(userId, async () => {
    await draftStorage.removeItem(key(userId));
    if (await draftStorage.getItem(key(userId))) throw new Error('Draft could not be cleared');
  });
}
export async function readLoanDraft(userId: number): Promise<LoanDraft | null> {
  await pending.get(userId)?.catch(() => {});
  const raw = await draftStorage.getItem(key(userId));
  if (!raw) return null;
  try {
    const value = JSON.parse(raw);
    if (!value || !['lent', 'borrowed'].includes(value.direction) || typeof value.shared !== 'boolean') return null;
    if (!['currency', 'amount', 'description', 'note', 'date', 'due'].every(field => typeof value[field] === 'string')) return null;
    if (!/^[A-Z]{3}$/.test(value.currency) || !/^\d{4}-\d{2}-\d{2}$/.test(value.date)) return null;
    if (value.contactId !== undefined && (typeof value.contactId !== 'string' || !/^\d+$/.test(value.contactId))) return null;
    if (value.newPerson !== null && (!value.newPerson || typeof value.newPerson.name !== 'string' || !(value.newPerson.email === null || typeof value.newPerson.email === 'string'))) return null;
    if (!value.contactId && !value.newPerson) return null;
    return value;
  } catch { return null; }
}
