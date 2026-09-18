import { Pressable, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { formatMoney } from '@/lib/format';
import type { Expense } from '@/types/api';

type ExpenseRowProps = {
  expense: Expense;
  payerName?: string;
  currentUserId?: number;
  onPress?: () => void;
};

export function ExpenseRow({ expense, payerName, currentUserId, onPress }: ExpenseRowProps) {
  const initial = expense.description.trim().slice(0, 1).toUpperCase() || '$';
  const directSummary = directExpenseSummary(expense, currentUserId);

  return (
    <Pressable disabled={!onPress} onPress={onPress}>
      <ThemedView type="backgroundElement" style={styles.card}>
        <ThemedView type="backgroundSelected" style={styles.icon}>
          <ThemedText style={styles.initial} themeColor="primary">
            {initial}
          </ThemedText>
        </ThemedView>
        <View style={styles.copy}>
          <ThemedText numberOfLines={1} style={styles.title}>
            {expense.description}
          </ThemedText>
          <ThemedText style={styles.meta} themeColor="textSecondary">
            {directSummary ?? (payerName || expense.payer.name ? `${payerName ?? expense.payer.name} paid · ` : '')}
            {directSummary ? ' · ' : ''}
            {new Date(expense.occurred_at).toLocaleDateString('en', {
              month: 'short',
              day: 'numeric',
            })}
          </ThemedText>
        </View>
        <ThemedText style={styles.amount}>
          {formatMoney(expense.amount_minor, expense.currency_code)}
        </ThemedText>
        {onPress ? <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText> : null}
      </ThemedView>
    </Pressable>
  );
}

function directExpenseSummary(expense: Expense, currentUserId?: number) {
  if (expense.expense_type !== 'direct' || currentUserId === undefined) return null;

  const ownSplit = expense.splits.find(
    (split) => (split.user_id ?? split.claimed_user_id) === currentUserId,
  );
  const otherSplit = expense.splits.find(
    (split) => (split.user_id ?? split.claimed_user_id) !== currentUserId,
  );
  if (!ownSplit || !otherSplit) return null;

  if ((expense.payer.user_id ?? expense.payer.claimed_user_id) === currentUserId) {
    return `${otherSplit.name ?? 'They'} owe you ${formatMoney(otherSplit.amount_owed_minor, expense.currency_code)}`;
  }

  return `You owe ${expense.payer.name ?? 'them'} ${formatMoney(ownSplit.amount_owed_minor, expense.currency_code)}`;
}

const styles = StyleSheet.create({
  card: {
    minHeight: 72,
    borderRadius: 20,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  icon: { width: 42, height: 42, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  initial: { fontSize: 17, fontWeight: '900' },
  copy: { flex: 1, gap: 2 },
  title: { fontSize: 14, lineHeight: 20, fontWeight: '800' },
  meta: { fontSize: 12, lineHeight: 17 },
  amount: { fontSize: 14, fontWeight: '800' },
  chevron: { fontSize: 22, fontWeight: '500' },
});
