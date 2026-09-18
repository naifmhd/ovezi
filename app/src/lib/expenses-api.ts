import { apiRequest } from '@/lib/api-client';
import type { Expense, ExpenseType, PaginatedResponse, SplitType } from '@/types/api';

type DataResponse<T> = { data: T };

export type ExpenseParticipantInput = {
  user_id?: number;
  placeholder_id?: number;
  value?: number;
};

export type CreateExpenseInput = {
  expense_type: 'group' | 'direct' | 'personal';
  group_id?: number;
  payer_user_id?: number;
  payer_placeholder_id?: number;
  amount_minor: number;
  currency_code: string;
  description: string;
  category?: string;
  occurred_at: string;
  split_type?: SplitType;
  participants?: ExpenseParticipantInput[];
  expense_rate?: string;
  confirmed_duplicate?: boolean;
};

export type UpdateExpenseInput = CreateExpenseInput & {
  recalculate_rate?: boolean;
};

export function fetchExpenses(
  token: string,
  options: { groupId?: number; expenseType?: ExpenseType; perPage?: number } = {},
) {
  const params = new URLSearchParams();
  if (options.groupId) params.set('group_id', String(options.groupId));
  if (options.expenseType) params.set('expense_type', options.expenseType);
  params.set('per_page', String(options.perPage ?? 20));

  return apiRequest<PaginatedResponse<Expense>>(`/expenses?${params.toString()}`, { token });
}

export async function createExpense(token: string, input: CreateExpenseInput) {
  const response = await apiRequest<DataResponse<Expense>>('/expenses', {
    method: 'POST',
    token,
    body: input,
  });
  return response.data;
}

export async function updateExpense(token: string, expenseId: number, input: UpdateExpenseInput) {
  const response = await apiRequest<DataResponse<Expense>>(`/expenses/${expenseId}`, {
    method: 'PUT',
    token,
    body: input,
  });
  return response.data;
}

export async function fetchExpense(token: string, expenseId: number) {
  const response = await apiRequest<DataResponse<Expense>>(`/expenses/${expenseId}`, { token });
  return response.data;
}

export function deleteExpense(token: string, expenseId: number) {
  return apiRequest<void>(`/expenses/${expenseId}`, {
    method: 'DELETE',
    token,
  });
}

export async function restoreExpense(token: string, expenseId: number) {
  const response = await apiRequest<DataResponse<Expense>>(`/expenses/${expenseId}/restore`, {
    method: 'POST',
    token,
  });
  return response.data;
}
