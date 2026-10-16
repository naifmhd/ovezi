import { apiRequest } from '@/lib/api-client';
import { financialRequest } from '@/lib/financial-request';

export type LendingDirection = 'lent' | 'borrowed';
export type LendingBalance = { currency_code: string; direction: LendingDirection; amount_minor: number; repaid_minor: number; outstanding_minor: number };
export type LendingContact = { id: number; is_pinned?: boolean; next_due_on?: string | null; name: string; email: string | null; last_currency_code?: string | null; is_owner: boolean; is_connected: boolean; invitation_status: string | null; disconnected_at: string | null; balances: LendingBalance[] };
export type LendingLoan = { id: number; contact_id: number; direction: LendingDirection; amount_minor: number; repaid_minor: number; outstanding_minor: number; pending_minor: number; currency_code: string; description: string | null; private_note: string | null; occurred_on: string; due_on: string | null; sharing: 'private' | 'invited' | 'shared'; acknowledgement: 'private' | 'pending' | 'acknowledged' | 'disputed'; voided_at: string | null; void_requested_by: number | null; is_creator: boolean; can_confirm: boolean };
export type LendingInvitation = { id: number; sender_name: string; loan_count: number; expires_at: string };
export type LendingEvent = { id: number; event: string; actor_name: string; created_at: string; details: { amount_minor?: number }; repayment: null | { id: number; amount_minor: number; currency_code: string; occurred_on: string; note: string | null; status: 'pending' | 'confirmed' | 'rejected' | 'reversed'; recorded_by: number; confirmed_by: number | null } };
export type LendingPage<T> = { data: T[]; meta?: { current_page: number; last_page: number }; current_page?: number; last_page?: number };
export function lendingNextPage(page: LendingPage<unknown>) {
  const current = page.meta?.current_page ?? page.current_page ?? 1;
  const last = page.meta?.last_page ?? page.last_page ?? 1;
  return current < last ? current + 1 : undefined;
}
export function lendingPage<T>(token: string, path: string, page = 1, signal?: AbortSignal) {
  return apiRequest<LendingPage<T>>(`/lending/${path}${path.includes('?') ? '&' : '?'}page=${page}`, { token, signal });
}
export async function lendingGet<T>(token: string, path: string, signal?: AbortSignal) {
  return (await apiRequest<{ data: T }>(`/lending/${path}`, { token, signal })).data;
}
export function lendingWrite<T = { message: string }>(token: string, path: string, body: unknown = {}) {
  return financialRequest<T>(`/lending/${path}`, token, body);
}
export function updateLendingContact(token: string, id: number, body: { name: string; email?: string | null }) {
  return apiRequest(`/lending/contacts/${id}`, { token, method: 'PATCH', body });
}
export function lendingStatus(loan: LendingLoan) {
  if (loan.voided_at) return 'Voided';
  if (loan.void_requested_by) return 'Correction awaiting agreement';
  if (loan.acknowledgement === 'disputed') return 'Disputed';
  if (loan.sharing === 'invited') return 'Invitation pending';
  if (loan.acknowledgement === 'pending') return 'Awaiting acknowledgement';
  return loan.outstanding_minor === 0 ? 'Settled' : loan.sharing === 'private' ? 'Private' : 'Shared';
}
export { lendingEventLabels } from '@/lib/format';

export const attentionLabels = {
  repayments: 'Confirm repayments', invitations: 'Connection invitations',
  acknowledgements: 'Review shared loans', corrections: 'Review corrections',
  disputed: 'Disputed loans', overdue: 'Overdue loans', due_soon: 'Due soon',
} as const;
export type AttentionKind = keyof typeof attentionLabels;
export type LendingOverview = { balances: LendingBalance[]; attention_counts: Record<AttentionKind, number> };
export type AttentionLoan = LendingLoan & { person_name: string };
export function localToday() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}
