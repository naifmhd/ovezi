import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Pressable, RefreshControl, StyleSheet, View } from 'react-native';

import { ActivityRow } from '@/components/activity-row';
import { AppScreen } from '@/components/app-screen';
import { ExpenseRow } from '@/components/expense-row';
import { GroupCard } from '@/components/group-card';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { fetchActivity } from '@/lib/activity-api';
import { fetchExpenses } from '@/lib/expenses-api';
import { fetchGroupBalances, fetchGroups } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export default function HomeScreen() {
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const groupsQuery = useQuery({
    queryKey: ['groups', 'active'],
    queryFn: () => fetchGroups(token),
  });
  const activityQuery = useQuery({
    queryKey: ['activity', 'global', 5],
    queryFn: () => fetchActivity(token, undefined, 5),
  });
  const expensesQuery = useQuery({
    queryKey: ['expenses', 'recent'],
    queryFn: () => fetchExpenses(token, { perPage: 20 }),
  });
  const groups = groupsQuery.data?.data ?? [];
  const balancesQuery = useQuery({
    queryKey: ['dashboard-balances', groups.map((group) => group.id)],
    enabled: groups.length > 0,
    queryFn: async () =>
      Promise.all(groups.map((group) => fetchGroupBalances(token, group.id))),
  });
  const balanceByGroup = new Map(
    (balancesQuery.data ?? []).map((balances) => [
      balances.group_id,
      balances.members.find((member) => member.participant.user_id === user.id)?.balance_minor ?? 0,
    ]),
  );
  const recentPersonalExpenses = (expensesQuery.data?.data ?? [])
    .filter((expense) => expense.expense_type !== 'group')
    .slice(0, 5);
  const refreshing = groupsQuery.isRefetching || activityQuery.isRefetching || expensesQuery.isRefetching;
  const firstError = groupsQuery.error ?? activityQuery.error ?? expensesQuery.error ?? balancesQuery.error;

  async function refresh() {
    await Promise.all([
      groupsQuery.refetch(),
      activityQuery.refetch(),
      expensesQuery.refetch(),
      balancesQuery.refetch(),
    ]);
  }

  return (
    <AppScreen
      eyebrow={`Hello, ${user.name.split(' ')[0]}`}
      title="Your balance"
      action={
        <View style={styles.headerActions}>
          <Pressable onPress={() => router.push('/(app)/search')}>
            <ThemedText style={styles.addAction} themeColor="primary">Search</ThemedText>
          </Pressable>
          <Pressable onPress={() => router.push('/(app)/expenses/create')}>
            <ThemedText style={styles.addAction} themeColor="primary">+ Expense</ThemedText>
          </Pressable>
        </View>
      }
      scrollProps={{
        refreshControl: <RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />,
      }}>
      {!user.email_verified_at ? (
        <ThemedView type="backgroundSelected" style={styles.verificationCard}>
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
        <ThemedView type="backgroundElement" style={styles.emptyCard}>
          <ThemedText style={styles.emptyTitle}>Create your first group</ThemedText>
          <ThemedText style={styles.smallCopy} themeColor="textSecondary">
            Trips, homes, events—keep every shared expense in one place.
          </ThemedText>
          <Pressable onPress={() => router.push('/(app)/groups/create')}>
            <ThemedText style={styles.createLink} themeColor="primary">
              Create group
            </ThemedText>
          </Pressable>
        </ThemedView>
      ) : null}

      <View style={styles.sectionHeading}>
        <ThemedText style={styles.sectionTitle}>Personal & 1-on-1</ThemedText>
        <Pressable onPress={() => router.push('/(app)/expenses')}>
          <ThemedText style={styles.seeAll} themeColor="primary">
            See all
          </ThemedText>
        </Pressable>
      </View>
      {expensesQuery.isLoading ? (
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
      {!expensesQuery.isLoading && !expensesQuery.error && recentPersonalExpenses.length === 0 ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          No personal or 1-on-1 expenses yet.
        </ThemedText>
      ) : null}

      <View style={styles.sectionHeading}>
        <ThemedText style={styles.sectionTitle}>Recent activity</ThemedText>
        <Pressable onPress={() => router.push('/(app)/(tabs)/activity')}>
          <ThemedText style={styles.seeAll} themeColor="primary">
            See all
          </ThemedText>
        </Pressable>
      </View>
      {activityQuery.isLoading ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          Loading activity…
        </ThemedText>
      ) : null}
      {(activityQuery.data?.data ?? []).map((activity) => (
        <ActivityRow activity={activity} key={activity.id} />
      ))}
      {!activityQuery.isLoading && !activityQuery.error && activityQuery.data?.data.length === 0 ? (
        <ThemedText style={styles.emptyActivity} themeColor="textSecondary">
          Nothing has happened yet.
        </ThemedText>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  addAction: { fontSize: 14, fontWeight: '800' },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  verificationCard: { padding: 16, borderRadius: 18, gap: 3 },
  verificationTitle: { fontWeight: '800' },
  smallCopy: { fontSize: 14, lineHeight: 20 },
  sectionHeading: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
  },
  sectionTitle: { fontSize: 18, lineHeight: 25, fontWeight: '800' },
  seeAll: { fontSize: 13, fontWeight: '800' },
  emptyCard: { borderRadius: 22, padding: 20, gap: 6 },
  emptyTitle: { fontSize: 17, lineHeight: 24, fontWeight: '800' },
  createLink: { marginTop: 8, fontWeight: '800' },
  emptyActivity: { textAlign: 'center', paddingVertical: 28 },
});
