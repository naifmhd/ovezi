import { UserAvatar } from '@/components/user-avatar';
import { MoneyAmount } from '@/components/money-amount';
import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Pressable, RefreshControl, StyleSheet, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { ExpenseRow } from '@/components/expense-row';
import { EmptyState } from '@/components/empty-state';
import { GroupCard } from '@/components/group-card';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { fetchOverallBalances } from '@/lib/balances-api';
import { fetchExpenses } from '@/lib/expenses-api';
import { formatMoney } from '@/lib/format';
import { fetchGroups } from '@/lib/groups-api';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';

export default function HomeScreen() {
  const theme = useTheme();
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const groupsQuery = useQuery({
    queryKey: ['groups', 'active'],
    queryFn: () => fetchGroups(token),
  });
  const expensesQuery = useQuery({
    queryKey: ['expenses', 'recent', 'personal'],
    queryFn: () => fetchExpenses(token, { expenseType: 'personal', perPage: 5 }),
  });
  const directExpensesQuery = useQuery({ queryKey: ['expenses', 'recent', 'direct'], queryFn: () => fetchExpenses(token, { expenseType: 'direct', perPage: 5 }) });
  const groups = groupsQuery.data?.data ?? [];
  const balancesQuery = useQuery({
    queryKey: ['dashboard-balances'],
    queryFn: () => fetchOverallBalances(token),
  });
  const balanceByGroup = new Map(
    (balancesQuery.data?.groups ?? []).map((balance) => [
      balance.group_id,
      balance.balance_minor,
    ]),
  );
  const directBalances = balancesQuery.data?.direct ?? [];
  const recentPersonalExpenses = [...(expensesQuery.data?.data ?? []), ...(directExpensesQuery.data?.data ?? [])]
    .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime() || b.id - a.id).slice(0, 5);
  const refreshing = groupsQuery.isRefetching || expensesQuery.isRefetching || directExpensesQuery.isRefetching || balancesQuery.isRefetching;
  const startingFresh = groupsQuery.isSuccess && expensesQuery.isSuccess && directExpensesQuery.isSuccess && balancesQuery.isSuccess
    && groups.length === 0 && recentPersonalExpenses.length === 0 && directBalances.length === 0
    && balancesQuery.data.totals_by_currency.length === 0;
  const firstError = groupsQuery.error ?? expensesQuery.error ?? directExpensesQuery.error ?? balancesQuery.error;

  async function refresh() {
    await Promise.all([
      groupsQuery.refetch(),
      expensesQuery.refetch(),
      directExpensesQuery.refetch(),
      balancesQuery.refetch(),
    ]);
  }

  return (
    <AppScreen
      tabScreen
      branded
      eyebrow={`Hello, ${user.name.split(' ')[0]}`}
      title="Your shared life"
      action={
        <View style={styles.headerActions}>
          <Pressable accessibilityRole="button" accessibilityLabel="Search expenses, groups and friends" style={[styles.searchButton, { backgroundColor: theme.surface }]} onPress={() => router.push('/(app)/search')}>
            <SymbolView name={{ ios: 'magnifyingglass', android: 'search', web: 'search' }} size={21} tintColor={theme.interactive} />
          </Pressable>
        </View>
      }
      scrollProps={{
        refreshControl: <RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />,
      }}>
      {!user.email_verified_at ? (
        <ThemedView style={[styles.verificationCard, { backgroundColor: theme.informationSurface }]}>
          <ThemedText style={styles.verificationTitle}>Verify your email</ThemedText>
          <ThemedText style={styles.smallCopy} themeColor="textSecondary">
            Use the secure link sent to {user.email}.
          </ThemedText>
        </ThemedView>
      ) : null}

      {firstError ? (
        <QueryErrorCard
          error={firstError}
          onRetry={() => void refresh()}
          retrying={refreshing || balancesQuery.isRefetching}
          title="Some dashboard data couldn’t refresh"
        />
      ) : null}

      {balancesQuery.isRefetchError ? <ThemedText themeColor="textSecondary">Showing last known balances. Pull down to retry.</ThemedText> : null}
      {(balancesQuery.data?.totals_by_currency ?? []).length > 0 ? (
        <ThemedView type="surfaceSubtle" style={styles.totalCard}>
          {balancesQuery.data?.totals_by_currency.map((total, index) => (
            <View key={total.currency_code} style={index === 0 ? styles.primaryTotal : styles.secondaryTotal}>
              <ThemedText style={index === 0 ? styles.totalLabel : styles.smallCopy}
                themeColor={index === 0 ? 'text' : total.balance_minor < 0 ? 'danger' : 'positive'}>
                {total.balance_minor > 0 ? 'You are owed' : total.balance_minor < 0 ? 'You owe' : 'Settled up'}
              </ThemedText>
              {index === 0
                ? <MoneyAmount minor={total.balance_minor} currency={total.currency_code} />
                : <ThemedText style={styles.secondaryAmount} themeColor={total.balance_minor < 0 ? 'danger' : 'positive'}>{formatMoney(Math.abs(total.balance_minor), total.currency_code)}</ThemedText>}
            </View>
          ))}
        </ThemedView>
      ) : null}

      <View style={styles.sectionHeading}>
        <ThemedText style={styles.sectionTitle}>Groups</ThemedText>
        <Pressable onPress={() => router.push('/(app)/(tabs)/groups')}>
          <ThemedText style={styles.seeAll} themeColor="primary">
            See all
          </ThemedText>
        </Pressable>
      </View>
      {groupsQuery.isLoading ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          Loading groups…
        </ThemedText>
      ) : null}
      {groups.slice(0, 3).map((group) => (
        <GroupCard key={group.id} balanceMinor={balanceByGroup.get(group.id)} group={group} />
      ))}
      {!groupsQuery.isLoading && !groupsQuery.error && groups.length === 0 ? (
        <EmptyState title="Good times start here" description="Create a group for a trip, a home, or your everyday plans. Add your first expense together." action={{ label: 'Create a group', onPress: () => router.push('/(app)/groups/create') }} />
      ) : null}

      {!startingFresh ? <>
      <View style={styles.sectionHeading}>
        <ThemedText style={styles.sectionTitle}>1-on-1 balances</ThemedText>
        <Pressable accessibilityRole="button" onPress={() => router.push('/(app)/friends')}><ThemedText style={styles.seeAll} themeColor="interactive">Manage friends</ThemedText></Pressable>
      </View>
      {balancesQuery.isLoading ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          Loading balances…
        </ThemedText>
      ) : null}
      {directBalances.map((balance) => (
        <Pressable
          accessibilityRole="button"
          key={`${balance.currency_code}:${balance.participant.key}`}
          onPress={() => router.push({
            pathname: '/(app)/settlements/direct',
            params: {
              participant: balance.participant.key,
              currency: balance.currency_code,
            },
          })}>
          <ThemedView
            type="backgroundElement"
            style={[
              styles.directCard,
              { borderBottomColor: theme.border, backgroundColor: 'transparent' },
            ]}>
            <UserAvatar name={balance.participant.name} size={40} />
            <View style={styles.directCopy}>
              <ThemedText style={styles.directName}>{balance.participant.name}</ThemedText>
              <ThemedText style={styles.smallCopy} themeColor="textSecondary">
                {balance.balance_minor >= 0 ? 'owes you' : 'you owe'}
              </ThemedText>
            </View>
            <View style={styles.directAmountWrap}>
              <ThemedText
                style={styles.directAmount}
                themeColor={balance.balance_minor >= 0 ? 'positive' : 'danger'}>
                {formatMoney(Math.abs(balance.balance_minor), balance.currency_code)}
              </ThemedText>
              <ThemedText style={styles.settleLabel} themeColor="interactive">Settle</ThemedText>
            </View>
          </ThemedView>
        </Pressable>
      ))}
      {!balancesQuery.isLoading && !balancesQuery.error && directBalances.length === 0 ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          You’re settled up. Add an expense to track who owes whom.
        </ThemedText>
      ) : null}

      <View style={styles.sectionHeading}>
        <ThemedText style={styles.sectionTitle}>Personal & 1-on-1</ThemedText>
        <Pressable onPress={() => router.push('/(app)/expenses')}>
          <ThemedText style={styles.seeAll} themeColor="primary">
            See all
          </ThemedText>
        </Pressable>
      </View>
      {expensesQuery.isLoading || directExpensesQuery.isLoading ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          Loading expenses…
        </ThemedText>
      ) : null}
      {recentPersonalExpenses.map((expense) => (
        <ExpenseRow
          currentUserId={user.id}
          expense={expense}
          key={expense.id}
          onPress={() => router.push({ pathname: '/(app)/expenses/[id]', params: { id: expense.id } })}
        />
      ))}
      {!expensesQuery.isLoading && !directExpensesQuery.isLoading && !expensesQuery.error && !directExpensesQuery.error && recentPersonalExpenses.length === 0 ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          No personal or 1-on-1 expenses yet.
        </ThemedText>
      ) : null}
      </> : null}


    </AppScreen>
  );
}

const styles = StyleSheet.create({
  searchButton: { width: 48, height: 48, borderRadius: 24, alignItems: 'center', justifyContent: 'center' },
  primaryTotal: { gap: 12 },
  secondaryTotal: { width: '100%', paddingTop: 12, flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8, backgroundColor: 'transparent' },
  secondaryAmount: { fontSize: 14, lineHeight: 21, fontWeight: '500', fontVariant: ['tabular-nums'] },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  verificationCard: { padding: 16, borderRadius: 18, gap: 3 },
  verificationTitle: { fontWeight: '600' },
  totalGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  totalCard: { width: '100%', borderRadius: 20, padding: 22, gap: 8 },
  totalHeader: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  totalLabel: { fontSize: 15, lineHeight: 22, fontWeight: '500' },
  totalAmount: { fontSize: 34, lineHeight: 43, fontWeight: '500', fontVariant: ['tabular-nums'] },
  smallCopy: { fontSize: 14, lineHeight: 20 },
  sectionHeading: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 22,
  },
  sectionTitle: { fontSize: 17, lineHeight: 25, fontWeight: '600' },
  seeAll: { fontSize: 13, fontWeight: '500', paddingVertical: 14 },
  emptyCard: { borderRadius: 22, padding: 20, gap: 6 },
  emptyTitle: { fontSize: 17, lineHeight: 24, fontWeight: '600' },
  createLink: { marginTop: 8, fontWeight: '600' },
  emptyActivity: { textAlign: 'center', paddingVertical: 28 },
  directCard: {
    minHeight: 76,
    borderRadius: 0,
    paddingHorizontal: 0,
    paddingVertical: 13,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  directCopy: { flex: 1, minWidth: 0, gap: 2 },
  directName: { fontSize: 16, lineHeight: 22, fontWeight: '600' },
  directAmountWrap: { maxWidth: '45%', alignItems: 'flex-end', gap: 2 },
  directAmount: { fontSize: 15, lineHeight: 21, fontWeight: '600' },
  settleLabel: { fontSize: 12, lineHeight: 17, fontWeight: '600' },
});
