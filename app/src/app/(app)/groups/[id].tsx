import { useMutation, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ActivityRow } from '@/components/activity-row';
import { ExpenseRow } from '@/components/expense-row';
import { GroupAvatar } from '@/components/group-avatar';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { fetchActivity } from '@/lib/activity-api';
import { fetchExpenses } from '@/lib/expenses-api';
import { formatMoney, minorAmountInput } from '@/lib/format';
import { fetchGroup, fetchGroupBalances, fetchGroupHistoryCsv } from '@/lib/groups-api';
import { shareCsv, shareGroupHistoryPdf } from '@/lib/share-text-file';
import { useAuthStore } from '@/stores/auth-store';

export default function GroupDetailScreen() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const idValue = Array.isArray(params.id) ? params.id[0] : params.id;
  const groupId = Number(idValue);
  const validId = Number.isInteger(groupId) && groupId > 0;
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const groupQuery = useQuery({
    queryKey: ['group', groupId],
    queryFn: () => fetchGroup(token, groupId),
    enabled: validId,
  });
  const balancesQuery = useQuery({
    queryKey: ['group-balances', groupId],
    queryFn: () => fetchGroupBalances(token, groupId),
    enabled: validId,
  });
  const activityQuery = useQuery({
    queryKey: ['activity', 'group', groupId],
    queryFn: () => fetchActivity(token, groupId, 20),
    enabled: validId,
  });
  const expensesQuery = useQuery({
    queryKey: ['expenses', 'group', groupId],
    queryFn: () => fetchExpenses(token, { groupId, perPage: 20 }),
    enabled: validId,
  });
  const group = groupQuery.data;
  const exportMutation = useMutation({
    mutationFn: async (format: 'csv' | 'pdf') => {
      const groupName = group?.name ?? 'Ovezi group';
      if (format === 'pdf') {
        await shareGroupHistoryPdf(token, groupId, groupName);
        return;
      }

      await shareCsv(await fetchGroupHistoryCsv(token, groupId), groupName);
    },
  });
  const isOwner = group?.members?.some(
    (member) => member.user?.id === user.id && member.role === 'owner',
  ) ?? false;
  const balances = balancesQuery.data;
  const currentBalance = balances?.members.find(
    (member) => member.participant.user_id === user.id,
  )?.balance_minor;
  const participantNames = new Map(
    (balances?.members ?? []).map((member) => [member.participant.key, member.participant.name]),
  );
  const error = groupQuery.error
    ?? balancesQuery.error
    ?? activityQuery.error
    ?? expensesQuery.error
    ?? exportMutation.error;
  const refreshing =
    groupQuery.isRefetching || balancesQuery.isRefetching || activityQuery.isRefetching || expensesQuery.isRefetching;

  async function refresh() {
    await Promise.all([
      groupQuery.refetch(),
      balancesQuery.refetch(),
      activityQuery.refetch(),
      expensesQuery.refetch(),
    ]);
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">
              ‹ Back
            </ThemedText>
          </Pressable>
          <ThemedText numberOfLines={1} style={styles.headerTitle}>
            {group?.name ?? 'Group'}
          </ThemedText>
          {isOwner ? (
            <Pressable
              onPress={() => router.push({
                pathname: '/(app)/groups/[id]/settings',
                params: { id: groupId },
              })}>
              <ThemedText style={styles.settingsLink} themeColor="primary">Settings</ThemedText>
            </Pressable>
          ) : (
            <View style={styles.headerSpacer} />
          )}
        </View>

        <View style={styles.scrollFrame}>
          <AppGroupContent
            activity={activityQuery.data?.data ?? []}
            balance={currentBalance}
            currency={group?.reporting_currency_code}
            currentUserId={user.id}
            error={error}
            expenses={expensesQuery.data?.data ?? []}
            group={group}
            participantNames={participantNames}
            refreshing={refreshing}
            settlements={balances?.suggested_settlements ?? []}
            exporting={exportMutation.isPending ? (exportMutation.variables ?? 'csv') : null}
            onExport={(format) => exportMutation.mutate(format)}
            onRefresh={refresh}
          />
        </View>
      </SafeAreaView>
    </ThemedView>
  );
}

type ContentProps = {
  group: Awaited<ReturnType<typeof fetchGroup>> | undefined;
  currency: string | undefined;
  currentUserId: number;
  balance: number | undefined;
  settlements: { from: string; to: string; amount_minor: number }[];
  participantNames: Map<string, string>;
  activity: Awaited<ReturnType<typeof fetchActivity>>['data'];
  expenses: Awaited<ReturnType<typeof fetchExpenses>>['data'];
  error: Error | null;
  refreshing: boolean;
  exporting: 'csv' | 'pdf' | null;
  onExport: (format: 'csv' | 'pdf') => void;
  onRefresh: () => Promise<void>;
};

function AppGroupContent({
  group,
  currency,
  currentUserId,
  balance,
  settlements,
  participantNames,
  activity,
  expenses,
  error,
  refreshing,
  exporting,
  onExport,
  onRefresh,
}: ContentProps) {
  return (
    <ScrollView
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void onRefresh()} />}
      showsVerticalScrollIndicator={false}>
      {error ? (
        <QueryErrorCard
          error={error}
          onRetry={() => void onRefresh()}
          retrying={refreshing}
          title="Some group data couldn’t refresh"
        />
      ) : null}
      {!group && !error ? (
        <ThemedText style={styles.empty} themeColor="textSecondary">
          Loading group…
        </ThemedText>
      ) : null}
      {group && currency ? (
        <>
          <ThemedView type="backgroundElement" style={styles.balanceCard}>
            <GroupAvatar group={group} size={72} />
            <ThemedText themeColor="textSecondary">Your group balance</ThemedText>
            <ThemedText
              style={styles.balanceAmount}
              themeColor={balance && balance !== 0 ? (balance > 0 ? 'primary' : 'danger') : 'text'}>
              {formatMoney(Math.abs(balance ?? 0), currency)}
            </ThemedText>
            <ThemedText themeColor="textSecondary">
              {(balance ?? 0) > 0 ? 'You are owed' : (balance ?? 0) < 0 ? 'You owe' : 'All settled up'}
            </ThemedText>
          </ThemedView>

          <View style={styles.actionRow}>
            {!group.is_archived ? (
              <Pressable
                onPress={() =>
                  router.push({ pathname: '/(app)/expenses/create', params: { groupId: group.id } })
                }
                style={[styles.actionButton, styles.addExpenseButton]}>
                <ThemedText style={styles.addExpenseLabel}>+ Expense</ThemedText>
              </Pressable>
            ) : null}
            {settlements.length > 0 ? (
              <Pressable
                onPress={() =>
                  router.push({ pathname: '/(app)/settlements/create', params: { groupId: group.id } })
                }
                style={[styles.actionButton, styles.settleButton]}>
                <ThemedText style={styles.settleLabel}>Settle up</ThemedText>
              </Pressable>
            ) : null}
          </View>
          <View style={styles.exportRow}>
            <Pressable
              disabled={exporting !== null}
              onPress={() => onExport('csv')}
              style={styles.exportAction}>
              <ThemedText style={styles.exportLabel} themeColor="primary">
                {exporting === 'csv' ? 'Preparing CSV…' : '↓ Export CSV'}
              </ThemedText>
            </Pressable>
            <Pressable
              disabled={exporting !== null}
              onPress={() => onExport('pdf')}
              style={styles.exportAction}>
              <ThemedText style={styles.exportLabel} themeColor="primary">
                {exporting === 'pdf' ? 'Preparing PDF…' : '↓ Export PDF'}
              </ThemedText>
            </Pressable>
          </View>

          <SectionTitle title="Expenses" />
          {expenses.map((expense) => {
            const payerKey = expense.payer.user_id
              ? `user:${expense.payer.user_id}`
              : `placeholder:${expense.payer.placeholder_id}`;
            return (
              <ExpenseRow
                currentUserId={currentUserId}
                expense={expense}
                key={expense.id}
                onPress={() => router.push({ pathname: '/(app)/expenses/[id]', params: { id: expense.id } })}
                payerName={participantNames.get(payerKey)}
              />
            );
          })}
          {expenses.length === 0 ? (
            <ThemedText style={styles.empty} themeColor="textSecondary">
              No expenses yet. Add the first one when this group spends together.
            </ThemedText>
          ) : null}

          <SectionTitle title={`Members · ${group.members?.length ?? 0}`} />
          <ThemedView type="backgroundElement" style={styles.listCard}>
            {(group.members ?? []).map((member, index) => (
              <View key={member.id}>
                {index > 0 ? <View style={styles.divider} /> : null}
                <View style={styles.memberRow}>
                  <ThemedView type="backgroundSelected" style={styles.memberAvatar}>
                    <ThemedText style={styles.memberInitial} themeColor="primary">
                      {(member.user?.name ?? member.placeholder?.name ?? '?').slice(0, 1)}
                    </ThemedText>
                  </ThemedView>
                  <ThemedText style={styles.memberName}>
                    {member.user?.name ?? member.placeholder?.name ?? 'Unknown member'}
                  </ThemedText>
                  <ThemedText style={styles.role} themeColor="textSecondary">
                    {member.role}
                  </ThemedText>
                </View>
              </View>
            ))}
          </ThemedView>

          {settlements.length > 0 ? (
            <>
              <SectionTitle title="Suggested settlements" />
              <ThemedView type="backgroundElement" style={styles.listCard}>
                {settlements.map((settlement, index) => {
                  const currentUserKey = `user:${currentUserId}`;
                  const canRecord = group.members?.some(
                    (member) => member.user?.id === currentUserId && member.role === 'owner',
                  ) || settlement.from === currentUserKey || settlement.to === currentUserKey;

                  return (
                  <View key={`${settlement.from}-${settlement.to}`}>
                    {index > 0 ? <View style={styles.divider} /> : null}
                    <View style={styles.settlementRow}>
                      <View style={styles.settlementCopy}>
                        <ThemedText style={styles.memberName}>
                          {participantNames.get(settlement.from) ?? 'Member'} →{' '}
                          {participantNames.get(settlement.to) ?? 'Member'}
                        </ThemedText>
                        <ThemedText style={styles.role} themeColor="textSecondary">
                          Suggested payment
                        </ThemedText>
                      </View>
                      <View style={styles.settlementAction}>
                        <ThemedText style={styles.settlementAmount} themeColor="primary">
                          {formatMoney(settlement.amount_minor, currency)}
                        </ThemedText>
                        {canRecord ? (
                          <Pressable
                            onPress={() => router.push({
                              pathname: '/(app)/settlements/create',
                              params: {
                                groupId: group.id,
                                from: settlement.from,
                                to: settlement.to,
                                amount: minorAmountInput(settlement.amount_minor, currency),
                              },
                            })}>
                            <ThemedText style={styles.recordLink} themeColor="primary">
                              Record
                            </ThemedText>
                          </Pressable>
                        ) : null}
                      </View>
                    </View>
                  </View>
                  );
                })}
              </ThemedView>
            </>
          ) : null}

          <SectionTitle title="Recent activity" />
          {activity.map((item) => (
            <ActivityRow activity={item} key={item.id} />
          ))}
          {activity.length === 0 ? (
            <ThemedText style={styles.empty} themeColor="textSecondary">
              No group activity yet.
            </ThemedText>
          ) : null}
        </>
      ) : null}
    </ScrollView>
  );
}

function SectionTitle({ title }: { title: string }) {
  return <ThemedText style={styles.sectionTitle}>{title}</ThemedText>;
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  scrollFrame: { flex: 1 },
  header: {
    height: 60,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
  },
  back: { fontSize: 14, fontWeight: '800' },
  headerTitle: { flex: 1, textAlign: 'center', fontSize: 16, fontWeight: '800' },
  headerSpacer: { width: 48 },
  settingsLink: { fontSize: 13, fontWeight: '800' },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 12 },
  balanceCard: { borderRadius: 24, padding: 22, alignItems: 'center', marginBottom: 10 },
  actionRow: { flexDirection: 'row', gap: 10 },
  actionButton: {
    flex: 1,
    height: 52,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addExpenseButton: { backgroundColor: '#00F5A0' },
  addExpenseLabel: { color: '#061A14', fontSize: 15, fontWeight: '900' },
  settleButton: { backgroundColor: '#DDFBF0' },
  settleLabel: { color: '#0A6B4E', fontSize: 15, fontWeight: '900' },
  exportRow: { flexDirection: 'row', justifyContent: 'center', gap: 20 },
  exportAction: { minHeight: 42, alignItems: 'center', justifyContent: 'center' },
  exportLabel: { fontSize: 13, fontWeight: '800' },
  balanceAmount: { fontSize: 34, lineHeight: 43, fontWeight: '800', marginVertical: 3 },
  sectionTitle: { fontSize: 17, lineHeight: 24, fontWeight: '800', marginTop: 14 },
  listCard: { borderRadius: 20, paddingHorizontal: 16 },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.3 },
  memberRow: { minHeight: 66, flexDirection: 'row', alignItems: 'center', gap: 11 },
  memberAvatar: { width: 38, height: 38, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  memberInitial: { fontWeight: '800' },
  memberName: { flex: 1, fontSize: 14, lineHeight: 20, fontWeight: '700' },
  role: { fontSize: 12, lineHeight: 17, textTransform: 'capitalize' },
  settlementRow: { minHeight: 68, flexDirection: 'row', alignItems: 'center', gap: 12 },
  settlementCopy: { flex: 1 },
  settlementAction: { alignItems: 'flex-end', gap: 3 },
  settlementAmount: { fontSize: 15, fontWeight: '800' },
  recordLink: { fontSize: 12, fontWeight: '800' },
  empty: { textAlign: 'center', paddingVertical: 24 },
});
