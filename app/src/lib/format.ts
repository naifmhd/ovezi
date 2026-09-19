import type { Activity } from '@/types/api';

export function formatMoney(minorAmount: number, currencyCode: string) {
  const formatter = new Intl.NumberFormat('en', {
    style: 'currency',
    currency: currencyCode,
    currencyDisplay: 'narrowSymbol',
  });
  const digits = formatter.resolvedOptions().maximumFractionDigits ?? 2;

  return formatter.format(minorAmount / 10 ** digits);
}

export function currencyFractionDigits(currencyCode: string) {
  try {
    return new Intl.NumberFormat('en', {
      style: 'currency',
      currency: currencyCode,
    }).resolvedOptions().maximumFractionDigits ?? 2;
  } catch {
    return 2;
  }
}

export function parseDecimalToInteger(value: string, fractionDigits: number) {
  const normalized = value.trim().replaceAll(',', '');
  const match = normalized.match(/^(\d+)(?:\.(\d*))?$/);
  if (!match || (match[2]?.length ?? 0) > fractionDigits) return null;

  const whole = Number(match[1]);
  const fraction = (match[2] ?? '').padEnd(fractionDigits, '0');
  const result = whole * 10 ** fractionDigits + Number(fraction || 0);

  return Number.isSafeInteger(result) ? result : null;
}

export function minorAmountInput(minorAmount: number, currencyCode: string) {
  const digits = currencyFractionDigits(currencyCode);
  const divisor = 10 ** digits;
  const whole = Math.floor(Math.abs(minorAmount) / divisor);
  const fraction = String(Math.abs(minorAmount) % divisor).padStart(digits, '0');

  return `${minorAmount < 0 ? '-' : ''}${whole}${digits > 0 ? `.${fraction}` : ''}`;
}

const activityLabels: Record<string, string> = {
  'group.created': 'created the group',
  'group.updated': 'updated the group',
  'group.archived': 'archived the group',
  'group.reopened': 'reopened the group',
  'group.ownership_transferred': 'transferred group ownership',
  'expense.created': 'added an expense',
  'expense.updated': 'updated an expense',
  'expense.deleted': 'deleted an expense',
  'expense.restored': 'restored an expense',
  'recurring_expense.created': 'created a recurring expense',
  'recurring_expense.updated': 'updated a recurring expense',
  'recurring_expense.paused': 'paused a recurring expense',
  'recurring_expense.resumed': 'resumed a recurring expense',
  'recurring_expense.canceled': 'canceled a recurring expense',
  'placeholder.claimed': 'claimed their previous history',
  'settlement.created': 'recorded a settlement',
  'member.joined': 'joined the group',
  'member.rejoined': 'rejoined the group',
  'member.removed': 'removed a member',
  'currency_rate.created': 'added a currency rate',
  'currency_rate.updated': 'updated a currency rate',
  'currency_rate.deleted': 'removed a currency rate',
};

export function activityDescription(activity: Activity) {
  const actor = activity.actor?.name ?? 'Someone';
  const action = activityLabels[activity.event] ?? activity.event.replaceAll('.', ' ');
  return `${actor} ${action}`;
}

export function formatRelativeDate(value: string) {
  const date = new Date(value);
  const elapsedMinutes = Math.max(0, Math.round((Date.now() - date.getTime()) / 60_000));

  if (elapsedMinutes < 1) return 'Just now';
  if (elapsedMinutes < 60) return `${elapsedMinutes}m ago`;

  const hours = Math.round(elapsedMinutes / 60);
  if (hours < 24) return `${hours}h ago`;

  return date.toLocaleDateString('en', { month: 'short', day: 'numeric' });
}
