import type { GroupBalances } from '@/types/api';

export function isGroupSettled(balances: GroupBalances | undefined) {
  return !!balances && balances.members.length > 0
    && balances.members.every(member => member.balance_minor === 0)
    && balances.suggested_settlements.length === 0;
}
