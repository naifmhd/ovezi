import { SymbolView } from 'expo-symbols';
import { StyleSheet, View, useWindowDimensions } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';
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
  const tone = categoryTone(expense.category, theme);

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
            {directSummary ?? (payerName || expense.payer.name ? `${payerName ?? expense.payer.name} paid · ` : '')}
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

function categoryTone(category: string | null, theme: ReturnType<typeof useTheme>) {
  const normalized = category?.toLowerCase() ?? '';
  if (/food|dinner|lunch|cafe|restaurant|grocer/.test(normalized)) {
    return { background: theme.accentCoralSurface, foreground: theme.accentCoral };
  }
  if (/transport|taxi|car|fuel|travel|flight|hotel/.test(normalized)) {
    return { background: theme.accentBlueSurface, foreground: theme.accentBlue };
  }
  if (/home|house|rent|utility/.test(normalized)) {
    return { background: theme.accentVioletSurface, foreground: theme.accentViolet };
  }
  if (/shop|purchase/.test(normalized)) {
    return { background: theme.accentAmberSurface, foreground: theme.accentAmber };
  }
  return { background: theme.backgroundSelected, foreground: theme.interactive };
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
    minHeight: 64,
    backgroundColor: 'transparent',
    borderBottomWidth: StyleSheet.hairlineWidth,
    paddingVertical: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  icon: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  content: { flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 12 },
  contentStacked: { flexDirection: 'column', alignItems: 'stretch' },
  copy: { flex: 1, minWidth: 0, gap: 3 },
  title: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  meta: { fontSize: 12, lineHeight: 17 },
  amount: { maxWidth: '44%', fontSize: 14, lineHeight: 21, fontWeight: '500', fontVariant: ['tabular-nums'], textAlign: 'right' },
  amountStacked: { maxWidth: '100%', textAlign: 'left' },
  chevron: { fontSize: 22, fontWeight: '500' },
});
