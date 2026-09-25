import { HeaderAction } from '@/components/ui/header-action';
import { ActionSheet } from '@/components/ui/action-sheet';
import { MoneyAmount } from '@/components/money-amount';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ActivityRow } from '@/components/activity-row';
import { ExpenseRow } from '@/components/expense-row';
import { GroupAvatar } from '@/components/group-avatar';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { fetchActivity } from '@/lib/activity-api';
import { fetchExpenses } from '@/lib/expenses-api';
import { formatMoney, minorAmountInput } from '@/lib/format';
import { fetchGroup, fetchGroupBalances, fetchGroupHistoryCsv } from '@/lib/groups-api';
import { selectionHaptic } from '@/lib/haptics';
import { shareCsv, shareGroupHistoryPdf } from '@/lib/share-text-file';
import { useAuthStore } from '@/stores/auth-store';

export default function GroupDetailScreen() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const idValue = Array.isArray(params.id) ? params.id[0] : params.id;
  const groupId = Number(idValue);
  const validId = Number.isInteger(groupId) && groupId > 0;
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const theme = useTheme();
  const [menuOpen, setMenuOpen] = useState(false);
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
    queryFn: () => fetchActivity(token, groupId, 5),
    enabled: validId,
  });
  const expensesQuery = useQuery({
    queryKey: ['expenses', 'group', groupId],
    queryFn: () => fetchExpenses(token, { groupId, perPage: 5 }),
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

  function openGroupActions() { selectionHaptic(); setMenuOpen(true); }

  return (
    <ThemedView style={styles.screen}>
      <ActionSheet title="Group actions" visible={menuOpen} onClose={() => setMenuOpen(false)} actions={[
        ...(isOwner ? [{ label: 'Group settings', onPress: () => router.push({ pathname: '/(app)/groups/[id]/settings', params: { id: groupId } }) }] : []),
        { label: 'Export CSV', onPress: () => exportMutation.mutate('csv') },
        { label: 'Export PDF', onPress: () => exportMutation.mutate('pdf') },
      ]} />
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction hitSlop={8} onPress={() => router.back()} style={styles.headerTouch}>
            <ThemedText style={styles.back} themeColor="primary">
              ‹ Back
            </ThemedText>
          </HeaderAction>
          <ThemedText numberOfLines={1} style={styles.headerTitle}>
            Group details
          </ThemedText>
          <AnimatedPressable
            accessibilityLabel="Group actions"
            accessibilityRole="button"
            disabled={!group || exportMutation.isPending}
            onPress={openGroupActions}
            style={styles.headerMenu}>
            <SymbolView
              name={{ ios: 'ellipsis', android: 'more_horiz', web: 'more_horiz' }}
              size={22}
              tintColor={theme.interactive}
              weight="bold"
            />
          </AnimatedPressable>
        </View>

        <View style={styles.scrollFrame}>
          <AppGroupContent
            activity={activityQuery.data?.data ?? []}
            balance={currentBalance}
            balancePending={balancesQuery.isPending}
            balanceStale={balancesQuery.isError || balancesQuery.isRefetching}
            currency={group?.reporting_currency_code}
            currentUserId={user.id}
            error={error}
            expenses={expensesQuery.data?.data ?? []}
            group={group}
            participantNames={participantNames}
            refreshing={refreshing}
            settlements={balances?.suggested_settlements ?? []}
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
  balancePending: boolean;
  balanceStale: boolean;
  settlements: { from: string; to: string; amount_minor: number }[];
  participantNames: Map<string, string>;
  activity: Awaited<ReturnType<typeof fetchActivity>>['data'];
  expenses: Awaited<ReturnType<typeof fetchExpenses>>['data'];
  error: Error | null;
  refreshing: boolean;
  onRefresh: () => Promise<void>;
};

function AppGroupContent({
  group,
  currency,
  currentUserId,
  balance,
  balancePending,
  balanceStale,
  settlements,
  participantNames,
  activity,
  expenses,
  error,
  refreshing,
  onRefresh,
}: ContentProps) {
  const theme = useTheme();
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
          <View style={styles.groupIdentity}>
            <GroupAvatar group={group} size={48} />
            <ThemedText accessibilityRole="header" style={styles.groupName}>{group.name}</ThemedText>
            <Pressable accessibilityRole="button" onPress={() => router.push({ pathname: '/(app)/groups/[id]/members', params: { id: group.id } })} style={styles.membersLink}>
              <ThemedText style={styles.groupMeta} themeColor="textSecondary">{group.members?.length ?? 0} people · Balances in {currency}</ThemedText>
              <ThemedText style={styles.groupMeta} themeColor="interactive">View members ›</ThemedText>
            </Pressable>
          </View>
          <ThemedView type={balance === undefined ? 'surface' : balance < 0 ? 'dangerSurface' : 'positiveSurface'} style={styles.balanceCard}>
            <View style={styles.balanceCopy}>
              <ThemedText style={styles.balanceLabel} themeColor="textSecondary">
                {balance === undefined ? balancePending ? 'Loading balance…' : 'Balance unavailable' : balance > 0 ? 'You are owed' : balance < 0 ? 'You owe' : 'All settled up'}
              </ThemedText>
              {balance !== undefined ? <MoneyAmount minor={balance} currency={currency} tone={balance > 0 ? 'positive' : balance < 0 ? 'danger' : 'text'} /> : null}
              {balance !== undefined && balanceStale ? <ThemedText themeColor="textSecondary">Last known balance · {balancePending ? 'Refreshing' : 'may be out of date'}</ThemedText> : null}
            </View>
          </ThemedView>

          <View style={styles.actionRow}>
            {!group.is_archived ? (
              <AnimatedPressable
                accessibilityLabel="Add expense"
                onPress={() =>
                  router.push({ pathname: '/(app)/expenses/create', params: { groupId: group.id } })
                }
                style={[styles.actionButton, { backgroundColor: theme.primary }]}>
                <SymbolView name={{ ios: 'plus', android: 'add', web: 'add' }} size={18} tintColor={theme.primaryText} weight="bold" />
                <ThemedText style={[styles.actionLabel, { color: theme.primaryText }]}>Add expense</ThemedText>
              </AnimatedPressable>
            ) : null}
            {settlements.length > 0 ? (
              <AnimatedPressable
                accessibilityLabel="Settle up"
                onPress={() =>
                  router.push({ pathname: '/(app)/settlements/create', params: { groupId: group.id } })
                }
                style={[styles.actionButton, styles.settleButton, { backgroundColor: theme.surfaceSubtle, borderColor: theme.border }]}>
                <SymbolView name={{ ios: 'checkmark.circle', android: 'check_circle', web: 'check_circle' }} size={18} tintColor={theme.interactive} weight="semibold" />
                <ThemedText style={styles.actionLabel} themeColor="interactive">Settle up</ThemedText>
              </AnimatedPressable>
            ) : null}
          </View>

          <View style={styles.sectionHeader}>
            <SectionTitle title="Recent expenses" inline />
            <AnimatedPressable accessibilityLabel="View all group expenses" style={styles.headerTouch} onPress={() => router.push({ pathname: '/(app)/expenses', params: { groupId: group.id, groupName: group.name } })}><ThemedText themeColor="interactive">View all</ThemedText></AnimatedPressable>
          </View>
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
                        <ThemedText style={styles.settlementName}>
                          {participantNames.get(settlement.from) ?? 'Member'} pays{'\n'}
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
                            hitSlop={10}
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

          <View style={styles.sectionHeader}>
            <SectionTitle title="Recent activity" inline />
            <AnimatedPressable accessibilityLabel="View all group activity" style={styles.headerTouch} onPress={() => router.push({ pathname: '/(app)/groups/[id]/activity', params: { id: group.id } })}><ThemedText themeColor="interactive">View all</ThemedText></AnimatedPressable>
          </View>
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

function SectionTitle({ title, inline = false }: { title: string; inline?: boolean }) {
  return <ThemedText style={[styles.sectionTitle, inline && styles.inlineSectionTitle]}>{title}</ThemedText>;
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  scrollFrame: { flex: 1 },
  header: {
    minHeight: 56,
    paddingVertical: 8,
    gap: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
  },
  headerTouch: { minWidth: 64, minHeight: 48, justifyContent: 'center' },
  back: { fontSize: 15, fontWeight: '600' },
  headerTitle: { flex: 1, textAlign: 'center', fontSize: 17, fontWeight: '600' },
  headerMenu: { width: 64, minHeight: 48, alignItems: 'flex-end', justifyContent: 'center' },
  content: { paddingHorizontal: Spacing.four, paddingTop: 14, paddingBottom: 80, gap: 8 },
  balanceCard: {
    borderRadius: Radius.card,
    minHeight: 96,
    padding: 22,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 13,
    marginBottom: 6,
  },
  groupIdentity: { gap: 12, marginBottom: 14 },
  groupName: { fontSize: 28, lineHeight: 35, letterSpacing: -0.7, fontWeight: '600' },
  groupMeta: { fontSize: 13, lineHeight: 20 },
  membersLink: { minHeight: 48, justifyContent: 'center', gap: 3 },
  balanceCopy: { flex: 1, minWidth: 0, gap: 1 },
  balanceLabel: { fontSize: 12, lineHeight: 17, fontWeight: '600' },
  balanceStatus: { borderRadius: Radius.pill, paddingHorizontal: 10, paddingVertical: 6 },
  balanceStatusText: { fontSize: 11, lineHeight: 15, fontWeight: '600' },
  actionRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  actionButton: {
    flex: 1,
    flexBasis: 140,
    minHeight: 48,
    paddingVertical: 12,
    borderRadius: Radius.control,
    flexDirection: 'row',
    gap: 7,
    alignItems: 'center',
    justifyContent: 'center',
  },
  settleButton: { borderWidth: StyleSheet.hairlineWidth },
  actionLabel: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  balanceAmount: { fontSize: 30, lineHeight: 39, fontVariant: ['tabular-nums'], fontWeight: '500', letterSpacing: -0.5 },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8, marginTop: 10 },
  inlineSectionTitle: { marginTop: 0, marginBottom: 0, flex: 1 },
  sectionTitle: { fontSize: 16, lineHeight: 22, fontWeight: '600', marginTop: 18, marginBottom: 3 },
  listCard: { borderRadius: Radius.card, paddingHorizontal: 14 },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.3 },
  memberRow: { minHeight: 66, flexDirection: 'row', alignItems: 'center', gap: 11 },
  memberAvatar: { width: 38, height: 38, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  memberInitial: { fontWeight: '600' },
  memberName: { flex: 1, fontSize: 14, lineHeight: 20, fontWeight: '600' },
  role: { fontSize: 12, lineHeight: 17, textTransform: 'capitalize' },
  settlementRow: {
    minHeight: 72,
    paddingVertical: 16,
    alignItems: 'stretch',
    gap: 12,
  },
  settlementCopy: { flex: 1, minWidth: 0, justifyContent: 'center', gap: 2 },
  settlementName: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  settlementAction: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  settlementAmount: { fontSize: 15, lineHeight: 21, fontWeight: '600' },
  recordLink: { fontSize: 12, lineHeight: 18, fontWeight: '600', paddingVertical: 15, paddingHorizontal: 12 },
  empty: { textAlign: 'center', paddingVertical: 24 },
});
