import { useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { RefreshControl, StyleSheet, View } from 'react-native';
import { ReleaseGuideNotice } from '@/components/release-guide-notice';
import { AppScreen } from '@/components/app-screen';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction, LendingMoney } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { NavigationRow } from '@/components/ui/screen-controls';
import { useTheme } from '@/hooks/use-theme';
import { fetchOverallBalances } from '@/lib/balances-api';
import { AttentionKind, attentionLabels, LendingOverview, lendingGet, localToday } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function HomeScreen() {
  const userId = useAuthStore(s => s.user!.id);
  const token = useAuthStore(s => s.token)!;
  const theme = useTheme();
  const client = useQueryClient();
  const [today, setToday] = useState(localToday);
  useFocusEffect(useCallback(() => { setToday(localToday()); }, []));
  const overview = useQuery({ queryKey: ['lending', 'overview', today], queryFn: ({ signal }) => lendingGet<LendingOverview>(token, `overview?today=${today}`, signal) });
  const groups = useQuery({ queryKey: ['dashboard-balances'], queryFn: () => fetchOverallBalances(token) });
  const groupTotals = new Map<string, { receive: number; pay: number }>();
  groups.data?.groups.forEach(group => {
    const amounts = groupTotals.get(group.currency_code) ?? { receive: 0, pay: 0 };
    if (group.balance_minor > 0) amounts.receive += group.balance_minor;
    else amounts.pay -= group.balance_minor;
    groupTotals.set(group.currency_code, amounts);
  });
  const currencies = [...new Set(overview.data?.balances.map(b => b.currency_code) ?? [])];
  const attention = (Object.keys(attentionLabels) as AttentionKind[]).filter(kind => (overview.data?.attention_counts[kind] ?? 0) > 0);
  const refresh = () => { void client.invalidateQueries({ queryKey: ['lending'] }); void groups.refetch(); };
  return <AppScreen title="Home" branded tabScreen showExpenseAction={false}
    action={<LendingAction label="All activity" onPress={() => router.push('/(app)/profile/activity')} />}
    scrollProps={{ refreshControl: <RefreshControl refreshing={overview.isRefetching || groups.isRefetching} onRefresh={refresh} /> }}>
    <ReleaseGuideNotice key={userId} userId={userId} />
    <ThemedText themeColor="textSecondary">Your money, with the next step in sight.</ThemedText>
    <View style={styles.section}>
      <ThemedText accessibilityRole="header" style={styles.heading}>Needs your attention</ThemedText>
      {overview.isLoading ? <ThemedText themeColor="textSecondary">Checking your records…</ThemedText> : null}
      {overview.error ? <QueryErrorCard title="Lending overview couldn’t load" error={overview.error} onRetry={() => void overview.refetch()} /> : null}
      {overview.data && !overview.error ? attention.length ? attention.map(kind => <NavigationRow key={kind} label={attentionLabels[kind]} detail={`${overview.data.attention_counts[kind]} ${kind === 'invitations' ? 'invitation' : 'loan'}${overview.data.attention_counts[kind] === 1 ? '' : 's'} ${kind === 'due_soon' ? 'due today or in the next 7 days' : 'to review'}`}
        onPress={() => router.push({ pathname: '/(app)/lending/attention', params: { kind } })} />) : <ThemedText themeColor="textSecondary">Nothing needs your attention right now.</ThemedText> : null}
    </View>
    <View style={[styles.summary, { backgroundColor: theme.surfaceSubtle }]}>
      <ThemedText accessibilityRole="header" style={styles.heading}>Lending</ThemedText>
      {overview.isLoading ? <ThemedText>Loading balances…</ThemedText> : null}
      {overview.error ? <ThemedText>Balances unavailable. Retry the lending overview above.</ThemedText> : currencies.length ? currencies.map(currency => <CurrencyTotals key={currency} currency={currency}
        receive={overview.data!.balances.find(b => b.currency_code === currency && b.direction === 'lent')?.outstanding_minor ?? 0}
        pay={overview.data!.balances.find(b => b.currency_code === currency && b.direction === 'borrowed')?.outstanding_minor ?? 0} />) : overview.data ? <ThemedText>No loans recorded yet. Start with a person and an amount.</ThemedText> : null}
      <ThemedText themeColor="textSecondary" style={styles.note}>Recorded loans, including those awaiting acknowledgement. Each currency stays separate; repayments reduce balances only when confirmed.</ThemedText>
      <PrimaryButton label="Record a loan" onPress={() => router.push('/(app)/lending/create')} />
      <LendingAction label="View lending records" onPress={() => router.push('/(app)/(tabs)/lending')} />
    </View>
    <View style={styles.section}>
      <ThemedText accessibilityRole="header" style={styles.heading}>Group balances</ThemedText>
      {groups.isLoading ? <ThemedText>Loading group balances…</ThemedText> : null}
      {groups.error ? <QueryErrorCard title="Group balances couldn’t load" error={groups.error} onRetry={() => void groups.refetch()} /> : [...groupTotals].sort(([a], [b]) => a.localeCompare(b)).map(([currency, amounts]) => <CurrencyTotals key={currency} currency={currency} receive={amounts.receive} pay={amounts.pay} />)}
      {groups.data && !groups.error && !groupTotals.size ? <ThemedText themeColor="textSecondary">Create a group to start sharing expenses.</ThemedText> : null}
      <ThemedText themeColor="textSecondary" style={styles.note}>Shown in each group’s reporting currency.</ThemedText>
      <NavigationRow label="Open groups" onPress={() => router.push('/(app)/(tabs)/groups')} />
      {groups.data?.direct.some(balance => balance.balance_minor !== 0) ? <NavigationRow label="Previous 1-on-1 balances" detail="Your earlier records are still available" onPress={() => router.push('/(app)/profile/history')} /> : null}
    </View>
  </AppScreen>;
}
function CurrencyTotals({ currency, receive, pay }: { currency: string; receive: number; pay: number }) {
  const theme = useTheme();
  return <View style={[styles.currency, { borderBottomColor: theme.border }]}>
    <View style={styles.amount}><ThemedText themeColor="textSecondary" style={styles.note}>To receive</ThemedText><LendingMoney amount={receive} currency={currency} /></View>
    <View style={styles.amount}><ThemedText themeColor="textSecondary" style={styles.note}>To pay</ThemedText><LendingMoney amount={pay} currency={currency} /></View>
  </View>;
}
const styles = StyleSheet.create({
  section: { gap: 8, marginTop: 16 }, heading: { fontSize: 20, lineHeight: 28, fontWeight: '600' },
  summary: { marginTop: 20, padding: 18, borderRadius: 16, gap: 12 },
  currency: { flexDirection: 'row', flexWrap: 'wrap', gap: 16, paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth },
  amount: { flexGrow: 1, flexBasis: 120, gap: 4 }, note: { fontSize: 13, lineHeight: 20 },
});
