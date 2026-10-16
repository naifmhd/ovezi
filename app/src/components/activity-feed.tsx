import { useInfiniteQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { SearchField, ViewFilter } from '@/components/ui/screen-controls';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ActivityRow } from '@/components/activity-row';
import { NativeDateField } from '@/components/native-date-field';
import { Typography } from '@/constants/theme';
import { EmptyState } from '@/components/empty-state';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { ActivitySource, fetchActivity } from '@/lib/activity-api';
import { useAuthStore } from '@/stores/auth-store';
import { useTheme } from '@/hooks/use-theme';

export function ActivityFeed({ groupId }: { groupId?: number }) {
  const token = useAuthStore((state) => state.token)!;
  const theme = useTheme();
  const [source, setSource] = useState<ActivitySource>('all');
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [datesOpen, setDatesOpen] = useState(false);
  const invalidDates = !!from && !!to && from > to;
  useEffect(() => { const timer = setTimeout(() => setQ(search.trim()), 280); return () => clearTimeout(timer); }, [search]);
  const query = useInfiniteQuery({
    queryKey: ['activity', 'history', groupId ?? 'global', source, q, from, to], initialPageParam: 1, enabled: !invalidDates,
    queryFn: ({ pageParam, signal }) => fetchActivity(token, groupId, 30, pageParam, source, signal, { q, from, to }),
    getNextPageParam: (page) => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined,
  });
  return <ThemedView style={styles.flex}><SafeAreaView edges={['top']} style={styles.flex}>
    <FlatList data={query.data?.pages.flatMap((page) => page.data) ?? []} keyExtractor={(item) => item.timeline_id ?? `activity:${item.id}`}
      ListHeaderComponent={<View style={styles.header}>
      <AnimatedPressable accessibilityRole="button" style={styles.back} onPress={() => router.canGoBack() ? router.back() : router.replace('/(app)/(tabs)')}><ThemedText themeColor="interactive">Back</ThemedText></AnimatedPressable>
      <ThemedText accessibilityRole="header" style={styles.title}>{groupId ? 'Group activity' : 'All activity'}</ThemedText>
      {!groupId ? <ViewFilter label="Activity filter" value={source} options={[{ value: 'all', label: 'Everything' }, { value: 'groups', label: 'Groups' }, { value: 'lending', label: 'Loans & repayments' }, { value: 'personal', label: 'Personal & previous 1-on-1' }]} onChange={setSource} /> : null}
      {!groupId ? <>
        <SearchField label="Search activity" placeholder="Person, group or description" value={search} onChange={setSearch} />
        <AnimatedPressable accessibilityRole="button" accessibilityState={{ expanded: datesOpen }} style={styles.back} onPress={() => setDatesOpen(!datesOpen)}><ThemedText themeColor="interactive">{datesOpen ? 'Hide dates' : from || to ? `${from || 'Beginning'} – ${to || 'Today'}` : 'Filter by date'}</ThemedText></AnimatedPressable>
        {datesOpen ? <View style={{ gap: 12 }}><NativeDateField label="From date" value={from} onChange={setFrom} optional emptyLabel="Beginning" /><NativeDateField label="To date" value={to} onChange={setTo} optional emptyLabel="Today" /></View> : null}
        {invalidDates ? <ThemedText themeColor="danger">Choose a to date on or after the from date.</ThemedText> : null}
        {search || from || to || source !== 'all' ? <AnimatedPressable accessibilityRole="button" style={styles.back} onPress={() => { setSearch(''); setQ(''); setFrom(''); setTo(''); setSource('all'); setDatesOpen(false); }}><ThemedText themeColor="interactive">Clear filters</ThemedText></AnimatedPressable> : null}
      </> : null}
      {query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} retrying={query.isFetching} /> : null}
    </View>}
      contentContainerStyle={[styles.content, { paddingBottom: 48 }]} renderItem={({ item }) => <ActivityRow activity={item} showGroup={!groupId} />}
      refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />}
      ListEmptyComponent={!query.isPending && !query.error ? <EmptyState title={q || from || to ? "No matching activity" : "No activity here yet"} description={q || from || to ? "Try another search or a wider date range." : groupId ? "Group expenses, updates, and settlements will appear here." : "Your accessible expenses, loan records, repayments, and updates will appear here."} /> : null}
      ListFooterComponent={query.isFetching ? <ActivityIndicator color={theme.interactive} /> : query.hasNextPage ? <AnimatedPressable accessibilityRole="button" style={styles.back} onPress={() => void query.fetchNextPage()}><ThemedText themeColor="interactive">Load more activity</ThemedText></AnimatedPressable> : null}
      onEndReached={() => { if (query.hasNextPage && !query.isFetching && !query.isFetchNextPageError) void query.fetchNextPage(); }} onEndReachedThreshold={0.4} />
  </SafeAreaView></ThemedView>;
}
const styles = StyleSheet.create({ flex: { flex: 1 }, header: { paddingVertical: 12, gap: 12 }, back: { minHeight: 48, justifyContent: 'center' }, title: { ...Typography.heading }, content: { padding: 24, paddingBottom: 120, gap: 0 } });
