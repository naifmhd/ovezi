import { SymbolView } from 'expo-symbols';
import { StyleSheet, View, useWindowDimensions } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';
import { directExpenseSummary, expensePayerLabel } from '@/lib/expense-impact';
import { formatMoney } from '@/lib/format';
import type { Expense } from '@/types/api';

type ExpenseRowProps = {
  expense: Expense;
  payerName?: string;
  currentUserId?: number;
  onPress?: () => void;
};

export function ExpenseRow({ expense, payerName, currentUserId, onPress }: ExpenseRowProps) {
  const theme = useTheme();
  const { width, fontScale } = useWindowDimensions();
  const stacked = width < 360 || fontScale > 1.2;
  const directSummary = directExpenseSummary(expense, currentUserId);
  const symbol = categorySymbol(expense.category);
  const tone = categoryTone(theme);

  return (
    <AnimatedPressable accessibilityRole={onPress ? "button" : undefined} disabled={!onPress} onPress={onPress}>
      <ThemedView type="backgroundElement" style={[styles.card, { borderBottomColor: theme.border }]}>
        <View style={[styles.icon, { backgroundColor: tone.background }]}>
          <SymbolView name={symbol} size={19} tintColor={tone.foreground} weight="semibold" />
        </View>
        <View style={[styles.content, stacked && styles.contentStacked]}>
        <View style={styles.copy}>
          <ThemedText style={styles.title}>
            {expense.description}
          </ThemedText>
          <ThemedText style={styles.meta} themeColor="textSecondary">
            {directSummary ?? `${expense.splits.filter((split) => (split.amount_paid_minor ?? 0) > 0).length > 1 ? expensePayerLabel(expense) : payerName ?? expense.payer.name ?? 'Unknown'} paid · `}
            {directSummary ? ' · ' : ''}
            {new Date(expense.occurred_at).toLocaleDateString(undefined, {
              month: 'short',
              day: 'numeric',
            })}
          </ThemedText>
        </View>
        <ThemedText style={[styles.amount, stacked && styles.amountStacked]}>
          {formatMoney(expense.amount_minor, expense.currency_code)}
        </ThemedText>
        </View>
        {onPress ? <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText> : null}
      </ThemedView>
    </AnimatedPressable>
  );
}

function categoryTone(theme: ReturnType<typeof useTheme>) {
  return { background: theme.surfaceSubtle, foreground: theme.interactive };
}

function categorySymbol(category: string | null): Parameters<typeof SymbolView>[0]['name'] {
  const normalized = category?.toLowerCase() ?? '';
  if (/food|dinner|lunch|cafe|restaurant|grocer/.test(normalized)) {
    return { ios: 'fork.knife', android: 'restaurant', web: 'restaurant' };
  }
  if (/transport|taxi|car|fuel/.test(normalized)) {
    return { ios: 'car.fill', android: 'directions_car', web: 'directions_car' };
  }
  if (/home|house|rent|utility/.test(normalized)) {
    return { ios: 'house.fill', android: 'home', web: 'home' };
  }
  if (/travel|flight|hotel/.test(normalized)) {
    return { ios: 'airplane', android: 'flight', web: 'flight' };
  }
  if (/shop|purchase/.test(normalized)) {
    return { ios: 'bag.fill', android: 'shopping_bag', web: 'shopping_bag' };
  }
  return { ios: 'receipt.fill', android: 'receipt_long', web: 'receipt_long' };
}

const styles = StyleSheet.create({
  card: {
    minHeight: 76,
    backgroundColor: 'transparent',
    borderBottomWidth: StyleSheet.hairlineWidth,
    paddingVertical: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  icon: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' },
  content: { flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 12 },
  contentStacked: { flexDirection: 'column', alignItems: 'stretch' },
  copy: { flex: 1, minWidth: 0, gap: 3 },
  title: { fontSize: 15, lineHeight: 22, fontWeight: '600' },
  meta: { fontSize: 12, lineHeight: 17 },
  amount: { maxWidth: '44%', fontSize: 14, lineHeight: 21, fontWeight: '500', fontVariant: ['tabular-nums'], textAlign: 'right' },
  amountStacked: { maxWidth: '100%', textAlign: 'left' },
  chevron: { fontSize: 22, fontWeight: '500' },
});
