import { fetchOverallBalances } from '@/lib/balances-api';
import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, RefreshControl, StyleSheet, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { EmptyState } from '@/components/empty-state';
import { GroupCard } from '@/components/group-card';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { fetchGroups } from '@/lib/groups-api';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';

export default function GroupsScreen() {
  const token = useAuthStore((state) => state.token)!;
  const theme = useTheme();
  const [status, setStatus] = useState<'active' | 'archived'>('active');
  const query = useQuery({
    queryKey: ['groups', status],
    queryFn: () => fetchGroups(token, status),
  });
  const balancesQuery = useQuery({ queryKey: ['dashboard-balances'], queryFn: () => fetchOverallBalances(token) });
  const groupBalances = new Map(balancesQuery.data?.groups.map((item) => [item.group_id, item.balance_minor]));
  const groups = query.data?.data ?? [];

  return (
    <AppScreen branded
      title="Groups"
      action={
        <Pressable accessibilityRole="button" style={[styles.newButton, { backgroundColor: theme.surfaceSubtle }]} onPress={() => router.push('/(app)/groups/create')}>
          <ThemedText style={styles.action} themeColor="primary">
            + New
          </ThemedText>
        </Pressable>
      }
      scrollProps={{
        refreshControl: (
          <RefreshControl refreshing={query.isRefetching} onRefresh={() => { void query.refetch(); void balancesQuery.refetch(); }} />
        ),
      }}>
      {query.error ? (
        <QueryErrorCard
          error={query.error}
          onRetry={() => void query.refetch()}
          retrying={query.isRefetching}
        />
      ) : null}
      <View style={styles.filterRow}>
        {(['active', 'archived'] as const).map((item) => {
          const selected = status === item;
          return (
            <Pressable
              accessibilityRole="button"
              accessibilityState={{ selected }}
              key={item}
              onPress={() => setStatus(item)}
              style={[
                styles.filter,
                {
                  backgroundColor: selected ? theme.primary : theme.backgroundElement,
                  borderColor: selected ? theme.primary : theme.border,
                },
              ]}>
              <ThemedText style={[styles.filterLabel, selected && { color: theme.primaryText }]}>
                {item === 'active' ? 'Active' : 'Archived'}
              </ThemedText>
            </Pressable>
          );
        })}
      </View>
      {query.isLoading ? (
        <ThemedText style={styles.loading} themeColor="textSecondary">
          Loading groups…
        </ThemedText>
      ) : null}
      {groups.map((group) => (
        <GroupCard group={group} balanceMinor={balancesQuery.isError ? undefined : groupBalances.get(group.id)} key={group.id} />
      ))}
      {!query.isLoading && !query.error && groups.length === 0 ? (
        <EmptyState title={status === 'active' ? 'Bring your people together' : 'No archived groups'} description={status === 'active' ? 'One place for the trip, the apartment, and everything you share.' : 'Archived groups stay here with their full history.'} action={status === 'active' ? { label: 'Create a group', onPress: () => router.push('/(app)/groups/create') } : undefined} />
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  newButton: { minHeight: 48, paddingHorizontal: 16, borderRadius: 24, alignItems: 'center', justifyContent: 'center' },
  action: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  empty: { padding: 22, borderRadius: 22, gap: 7, marginTop: 30 },
  emptyTitle: { fontSize: 20, lineHeight: 28, fontWeight: '600' },
  copy: { fontSize: 14, lineHeight: 21, marginBottom: 8 },
  loading: { textAlign: 'center', marginTop: 60 },
  filterRow: { flexDirection: 'row', gap: 8 },
  filter: { minHeight: 48, paddingVertical: 10, paddingHorizontal: 16, borderRadius: 14, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  filterLabel: { fontSize: 13, fontWeight: '600', textTransform: 'capitalize' },
});
