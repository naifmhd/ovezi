import type { Expense } from '@/types/api';
import { formatMoney } from '@/lib/format';

export function expenseNetForUser(expense: Expense, userId: number): number {
  const own = expense.splits.filter((split) => (split.user_id ?? split.claimed_user_id) === userId);
  const hasContributions = expense.splits.some((split) => split.amount_paid_minor != null);
  const paid = hasContributions ? own.reduce((sum, split) => sum + (split.amount_paid_minor ?? 0), 0)
    : (expense.payer.user_id ?? expense.payer.claimed_user_id) === userId ? expense.amount_minor : 0;
  return paid - own.reduce((sum, split) => sum + split.amount_owed_minor, 0);
}

export function expensePayerLabel(expense: Expense): string {
  const payers = expense.splits.filter((split) => (split.amount_paid_minor ?? 0) > 0);
  return payers.length > 0 ? payers.map((split) => `${split.name ?? 'Unknown'} · ${formatMoney(split.amount_paid_minor!, expense.currency_code)}`).join(', ')
    : expense.payer.name ?? 'Unknown';
}

export function directExpenseSummary(expense: Expense, currentUserId?: number): string | null {
  if (expense.expense_type !== 'direct' || currentUserId === undefined) return null;
  const net = expenseNetForUser(expense, currentUserId);
  if (net === 0) return 'No balance change for you';
  const other = expense.splits.find((split) => (split.user_id ?? split.claimed_user_id) !== currentUserId);
  const name = other?.name ?? expense.payer.name ?? 'Your friend';
  return net > 0 ? `${name} owes you ${formatMoney(net, expense.currency_code)}` : `You owe ${name} ${formatMoney(-net, expense.currency_code)}`;
}
