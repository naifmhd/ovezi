import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Pressable, StyleSheet, View } from 'react-native';

import { LendingMoney } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { GroupLabel } from '@/components/group-label';
import { ThemedView } from '@/components/themed-view';
import { activityDescription, formatRelativeDate } from '@/lib/format';
import { useTheme } from '@/hooks/use-theme';
import type { Activity } from '@/types/api';

export function ActivityRow({ activity, showGroup = true }: { activity: Activity; showGroup?: boolean }) {
  const theme = useTheme();
  const tone = activityTone(activity.event, theme);
  const expenseId = activity.subject.type === 'expense' && activity.event !== 'expense.deleted'
    ? activity.subject.id
    : null;
  const loanId = activity.subject.type === 'lending_loan' ? activity.subject.id : null;
  const groupId = expenseId === null ? activity.group_id : null;
  const canOpen = expenseId !== null || groupId !== null || loanId !== null;

  function openActivity() {
    if (loanId !== null) {
      router.push({ pathname: '/(app)/lending/loan', params: { id: loanId } });
    } else if (expenseId !== null) {
      router.push({ pathname: '/(app)/expenses/[id]', params: { id: expenseId } });
    } else if (groupId !== null) {
      router.push({ pathname: '/(app)/groups/[id]', params: { id: groupId } });
    }
  }

  return (
    <Pressable
      accessibilityRole={canOpen ? 'button' : undefined}
      disabled={!canOpen}
      onPress={openActivity}>
      <ThemedView type="backgroundElement" style={[styles.row, { backgroundColor: theme.background, borderBottomColor: theme.border, borderBottomWidth: StyleSheet.hairlineWidth }]}>
        <View style={[styles.icon, { backgroundColor: tone.background }]}>
          <SymbolView name={tone.symbol} size={18} tintColor={tone.foreground} weight="semibold" />
        </View>
        <View style={styles.copy}>
          {showGroup && activity.group_id !== null ? <GroupLabel groupId={activity.group_id} name={activity.group?.name} /> : null}
          {activity.person ? <ThemedText style={styles.date} themeColor="textSecondary">{activity.metadata?.direction === 'lent' ? 'Lent to' : 'Borrowed from'} {activity.person.name}</ThemedText> : null}
          <ThemedText style={styles.description}>{activityDescription(activity)}</ThemedText>
          {loanId !== null && typeof activity.metadata?.amount_minor === 'number' && typeof activity.metadata.currency_code === 'string' ? <>
            <ThemedText style={styles.date} themeColor="textSecondary">{activity.event.startsWith('repayment.') ? 'Repayment amount' : 'Loan amount'}{typeof activity.metadata.description === 'string' && activity.metadata.description ? ` · ${activity.metadata.description}` : ''}</ThemedText>
            <LendingMoney amount={activity.metadata.amount_minor} currency={activity.metadata.currency_code} />
          </> : null}
          <ThemedText style={styles.date} themeColor="textSecondary">
            {formatRelativeDate(activity.created_at)}
          </ThemedText>
        </View>
        {canOpen ? <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText> : null}
      </ThemedView>
    </Pressable>
  );
}

function activityTone(event: string, theme: ReturnType<typeof useTheme>) {
  if (event.startsWith('expense')) {
    return {
      background: theme.accentCoralSurface,
      foreground: theme.accentCoral,
      symbol: { ios: 'receipt.fill', android: 'receipt_long', web: 'receipt_long' } as const,
    };
  }
  if (event.startsWith('member') || event.startsWith('placeholder')) {
    return {
      background: theme.accentVioletSurface,
      foreground: theme.accentViolet,
      symbol: { ios: 'person.2.fill', android: 'group', web: 'group' } as const,
    };
  }
  if (event.startsWith('settlement') || event.startsWith('loan.') || event.startsWith('repayment.') || event.startsWith('connection.')) {
    return {
      background: theme.accentBlueSurface,
      foreground: theme.accentBlue,
      symbol: { ios: 'arrow.left.arrow.right', android: 'swap_horiz', web: 'swap_horiz' } as const,
    };
  }
  return {
    background: theme.accentAmberSurface,
    foreground: theme.accentAmber,
    symbol: { ios: 'sparkles', android: 'auto_awesome', web: 'auto_awesome' } as const,
  };
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 12, minHeight: 76, paddingVertical: 16, borderRadius: 0, alignItems: 'center' },
  icon: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' },
  copy: { flex: 1, minWidth: 0, gap: 4 },
  description: { fontSize: 15, lineHeight: 22, fontWeight: '600' },
  date: { fontSize: 12, lineHeight: 17 },
  chevron: { fontSize: 22, fontWeight: '500' },
});
