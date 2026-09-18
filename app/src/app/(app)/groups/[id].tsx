import { useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ActivityRow } from '@/components/activity-row';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { fetchActivity } from '@/lib/activity-api';
import { errorMessage } from '@/lib/api-client';
import { formatMoney } from '@/lib/format';
import { fetchGroup, fetchGroupBalances } from '@/lib/groups-api';
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
  const group = groupQuery.data;
  const balances = balancesQuery.data;
  const currentBalance = balances?.members.find(
    (member) => member.participant.user_id === user.id,
  )?.balance_minor;
  const participantNames = new Map(
    (balances?.members ?? []).map((member) => [member.participant.key, member.participant.name]),
  );
  const error = groupQuery.error ?? balancesQuery.error ?? activityQuery.error;
  const refreshing =
    groupQuery.isRefetching || balancesQuery.isRefetching || activityQuery.isRefetching;

  async function refresh() {
    await Promise.all([groupQuery.refetch(), balancesQuery.refetch(), activityQuery.refetch()]);
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
          <View style={styles.headerSpacer} />
        </View>

        <View style={styles.scrollFrame}>
          <AppGroupContent
            activity={activityQuery.data?.data ?? []}
            balance={currentBalance}
            currency={group?.reporting_currency_code}
            error={error}
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
  balance: number | undefined;
  settlements: { from: string; to: string; amount_minor: number }[];
  participantNames: Map<string, string>;
  activity: Awaited<ReturnType<typeof fetchActivity>>['data'];
  error: Error | null;
  refreshing: boolean;
  onRefresh: () => Promise<void>;
};

function AppGroupContent({
  group,
  currency,
  balance,
  settlements,
  participantNames,
  activity,
  error,
  refreshing,
  onRefresh,
}: ContentProps) {
  return (
    <ScrollView
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void onRefresh()} />}
      showsVerticalScrollIndicator={false}>
      {error ? <ThemedText themeColor="danger">{errorMessage(error)}</ThemedText> : null}
      {!group && !error ? (
        <ThemedText style={styles.empty} themeColor="textSecondary">
          Loading group…
        </ThemedText>
      ) : null}
      {group && currency ? (
        <>
          <ThemedView type="backgroundElement" style={styles.balanceCard}>
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
                {settlements.map((settlement, index) => (
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
                      <ThemedText style={styles.settlementAmount} themeColor="primary">
                        {formatMoney(settlement.amount_minor, currency)}
                      </ThemedText>
                    </View>
                  </View>
                ))}
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
  content: { padding: Spacing.four, paddingBottom: 80, gap: 12 },
  balanceCard: { borderRadius: 24, padding: 22, alignItems: 'center', marginBottom: 10 },
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
  settlementAmount: { fontSize: 15, fontWeight: '800' },
  empty: { textAlign: 'center', paddingVertical: 24 },
});
