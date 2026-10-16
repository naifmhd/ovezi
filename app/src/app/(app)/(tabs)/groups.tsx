import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BrandLockup } from '@/components/brand-mark';
import { SearchField, ViewFilter } from '@/components/ui/screen-controls';
import { SymbolView } from 'expo-symbols';
import { EmptyState } from '@/components/empty-state';
import { PinButton } from '@/components/pin-button';
import { GroupCard } from '@/components/group-card';
import { QueryErrorCard } from '@/components/query-error-card';
import { TabExpenseAction, tabContentBottomPadding } from '@/components/tab-expense-action';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Spacing, Typography } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { fetchOverallBalances } from '@/lib/balances-api';
import { hideGroup, restoreGroup, fetchGroupsPage } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export default function GroupsScreen() {
  const token = useAuthStore((state) => state.token)!;
  const theme = useTheme();
  const client = useQueryClient();
  const cleanup = useMutation({ mutationFn: ({ id, restore }: { id: number; restore: boolean }) => restore ? restoreGroup(token, id) : hideGroup(token, id, false), onSuccess: () => client.invalidateQueries({ queryKey: ['groups'] }) });

  const [status, setStatus] = useState<'active' | 'archived' | 'hidden' | 'deleted'>('active');
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 280);
    return () => clearTimeout(timer);
  }, [search]);
  const query = useInfiniteQuery({
    queryKey: ['groups', 'list', status, debouncedSearch],
    initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => fetchGroupsPage(token, status, debouncedSearch, pageParam, signal),
    getNextPageParam: (page) => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined,
  });
  const balancesQuery = useQuery({ queryKey: ['dashboard-balances'], queryFn: () => fetchOverallBalances(token) });
  const groupBalances = new Map(balancesQuery.data?.groups.map((item) => [item.group_id, item.balance_minor]));
  const groups = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <ThemedView style={styles.flex}>
      <SafeAreaView edges={['top']} style={styles.flex}>
        <View style={styles.brandBar}>
          <BrandLockup size={24} />
          <View style={styles.headerActions}>
            <Pressable accessibilityRole="button" accessibilityLabel="Activity" style={styles.iconButton} onPress={() => router.push('/(app)/profile/activity')}>
              <SymbolView name={{ ios: 'clock.arrow.circlepath', android: 'history', web: 'history' }} size={24} tintColor={theme.textSecondary} />
            </Pressable>
            <Pressable accessibilityRole="button" accessibilityLabel="Create a group" style={styles.newButton} onPress={() => router.push('/(app)/groups/create')}>
              <SymbolView name={{ ios: 'plus', android: 'add', web: 'add' }} size={20} tintColor={theme.interactive} />
              <ThemedText style={styles.action} themeColor="interactive">New group</ThemedText>
            </Pressable>
          </View>
        </View>
        <FlatList
          data={groups}
          keyExtractor={(group) => String(group.id)}
          renderItem={({ item }) => <View>
            {status === 'deleted' ? <View style={{ paddingVertical: 18 }}><ThemedText style={styles.action}>{item.name}</ThemedText><ThemedText themeColor="textSecondary">Deleted for everyone. Restore to Archived groups.</ThemedText></View> : <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}><View style={{ flex: 1 }}><GroupCard group={item} balanceMinor={balancesQuery.isError ? undefined : groupBalances.get(item.id)} /></View><PinButton kind="groups" id={item.id} name={item.name} pinned={item.is_pinned} /></View>}
            {status === 'deleted' || status === 'hidden' ? <AnimatedPressable accessibilityRole="button" disabled={cleanup.isPending} style={styles.loadMore} onPress={() => cleanup.mutate({ id: item.id, restore: status === 'deleted' })}><ThemedText themeColor="interactive">{status === 'deleted' ? 'Restore group' : 'Show group again'}</ThemedText></AnimatedPressable> : null}
          </View>}
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          initialNumToRender={12}
          refreshControl={<RefreshControl refreshing={query.isRefetching || balancesQuery.isRefetching} onRefresh={() => { void query.refetch(); void balancesQuery.refetch(); }} />}
          ListHeaderComponent={(
            <View style={styles.listHeader}>
              <ThemedText accessibilityRole="header" style={styles.title}>Groups</ThemedText>
              <SearchField label="Find a group" placeholder="Search group names" value={search} onChange={setSearch} />
              <ViewFilter label="Show groups" value={status} options={[{ value: 'active', label: 'Active' }, { value: 'archived', label: 'Archived' }, { value: 'hidden', label: 'Hidden' }, { value: 'deleted', label: 'Deleted' }]} onChange={setStatus} />
              {cleanup.error ? <QueryErrorCard error={cleanup.error} onRetry={() => cleanup.reset()} /> : null}
              {query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} retrying={query.isRefetching} /> : null}
              {balancesQuery.error ? <QueryErrorCard title="Group balances couldn’t load" error={balancesQuery.error} onRetry={() => void balancesQuery.refetch()} retrying={balancesQuery.isRefetching} /> : null}
              {query.isLoading ? <ThemedText themeColor="textSecondary">Loading groups…</ThemedText> : null}
            </View>
          )}
          ListEmptyComponent={!query.isLoading && !query.error ? (
            <EmptyState
              title={debouncedSearch ? 'No matching groups' : status === 'active' ? 'Bring your people together' : `No ${status} groups`}
              description={debouncedSearch ? 'Try another name or check the other group filter.' : status === 'active' ? 'One place for the trip, the apartment, and everything you share.' : status === 'deleted' ? 'Groups you own can be restored here. History is retained until a permanent deletion policy is introduced.' : status === 'hidden' ? 'Groups you hide are kept here, only for you.' : 'Archived groups stay here with their full history.'}
              action={!debouncedSearch && status === 'active' ? { label: 'Create a group', onPress: () => router.push('/(app)/groups/create') } : undefined}
            />
          ) : null}
          ListFooterComponent={query.isFetchingNextPage ? <ActivityIndicator color={theme.interactive} /> : query.hasNextPage ? (
            <AnimatedPressable accessibilityRole="button" style={styles.loadMore} onPress={() => void query.fetchNextPage()}><ThemedText themeColor="interactive">Load more groups</ThemedText></AnimatedPressable>
          ) : null}
          onEndReached={() => { if (query.hasNextPage && !query.isFetching && !query.isFetchNextPageError) void query.fetchNextPage(); }}
          onEndReachedThreshold={0.4}
        />
        <TabExpenseAction />
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  brandBar: { paddingHorizontal: Spacing.four, paddingTop: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 16, minHeight: 56 },
  newButton: { minHeight: 48, flexDirection: 'row', gap: 4, alignItems: 'center', justifyContent: 'center' },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  iconButton: { minHeight: 48, minWidth: 48, alignItems: 'center', justifyContent: 'center' },
  action: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  content: { flexGrow: 1, paddingHorizontal: Spacing.four, paddingBottom: tabContentBottomPadding },
  listHeader: { gap: 12, paddingBottom: 12 },
  title: { ...Typography.heading, paddingVertical: Spacing.three },
  loadMore: { minHeight: 48, alignItems: 'center', justifyContent: 'center' },
});
