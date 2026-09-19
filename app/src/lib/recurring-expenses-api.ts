import { apiRequest } from '@/lib/api-client';
import type { CreateExpenseInput } from '@/lib/expenses-api';
import type {
  Expense,
  PaginatedResponse,
  RecurrenceFrequency,
  RecurringExpense,
} from '@/types/api';

type DataResponse<T> = { data: T };

export type RecurringExpenseInput = CreateExpenseInput & {
  frequency: RecurrenceFrequency;
  ends_on?: string;
};

export async function createRecurringExpense(token: string, input: RecurringExpenseInput) {
  const response = await apiRequest<DataResponse<{
    expense: Expense;
    recurring_expense: RecurringExpense;
  }>>('/recurring-expenses', {
    method: 'POST',
    token,
    body: input,
  });
  return response.data;
}

export function fetchRecurringExpenses(token: string) {
  return apiRequest<PaginatedResponse<RecurringExpense>>('/recurring-expenses', { token });
}

export async function fetchRecurringExpense(token: string, recurringExpenseId: number) {
  const response = await apiRequest<DataResponse<RecurringExpense>>(
    `/recurring-expenses/${recurringExpenseId}`,
    { token },
  );
  return response.data;
}

export async function updateRecurringExpense(
  token: string,
  recurringExpenseId: number,
  input: RecurringExpenseInput,
) {
  const response = await apiRequest<DataResponse<RecurringExpense>>(
    `/recurring-expenses/${recurringExpenseId}`,
    { method: 'PATCH', token, body: input },
  );
  return response.data;
}

export async function pauseRecurringExpense(token: string, recurringExpenseId: number) {
  const response = await apiRequest<DataResponse<RecurringExpense>>(
    `/recurring-expenses/${recurringExpenseId}/pause`,
    { method: 'PUT', token },
  );
  return response.data;
}

export async function resumeRecurringExpense(token: string, recurringExpenseId: number) {
  const response = await apiRequest<DataResponse<RecurringExpense>>(
    `/recurring-expenses/${recurringExpenseId}/pause`,
    { method: 'DELETE', token },
  );
  return response.data;
}

export function cancelRecurringExpense(token: string, recurringExpenseId: number) {
  return apiRequest<void>(`/recurring-expenses/${recurringExpenseId}`, {
    method: 'DELETE',
    token,
  });
}
