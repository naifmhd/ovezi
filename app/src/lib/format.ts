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

const activityLabels: Record<string, string> = {
  'group.created': 'created the group',
  'group.updated': 'updated the group',
  'group.archived': 'archived the group',
  'group.reopened': 'reopened the group',
  'group.ownership_transferred': 'transferred group ownership',
  'expense.created': 'added an expense',
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
