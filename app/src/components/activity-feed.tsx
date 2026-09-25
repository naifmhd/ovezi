import { useInfiniteQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ActivityRow } from '@/components/activity-row';
import { BrandLockup } from '@/components/brand-mark';
import { EmptyState } from '@/components/empty-state';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { fetchActivity } from '@/lib/activity-api';
import { useAuthStore } from '@/stores/auth-store';
import { useTheme } from '@/hooks/use-theme';

export function ActivityFeed({ groupId }: { groupId?: number }) {
  const token = useAuthStore((state) => state.token)!;
  const theme = useTheme();
  const query = useInfiniteQuery({
    queryKey: ['activity', 'history', groupId ?? 'global'], initialPageParam: 1,
    queryFn: ({ pageParam }) => fetchActivity(token, groupId, 30, pageParam),
    getNextPageParam: (page) => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined,
  });
  return <ThemedView style={styles.flex}><SafeAreaView edges={['top']} style={styles.flex}>
    <View style={styles.header}>
      {groupId ? <AnimatedPressable style={styles.back} onPress={() => router.back()}><ThemedText themeColor="interactive">‹ Back</ThemedText></AnimatedPressable> : <BrandLockup />}
      <ThemedText accessibilityRole="header" style={styles.title}>{groupId ? 'Group activity' : 'Activity'}</ThemedText>
    </View>
    <FlatList data={query.data?.pages.flatMap((page) => page.data) ?? []} keyExtractor={(item) => String(item.id)}
      contentContainerStyle={styles.content} renderItem={({ item }) => <ActivityRow activity={item} />}
      refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />}
      ListHeaderComponent={query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} retrying={query.isFetching} /> : null}
      ListEmptyComponent={!query.isPending && !query.error ? <EmptyState title="Every shared moment, in one place" description="Expenses, updates, and settlements will appear here." /> : null}
      ListFooterComponent={query.isFetching ? <ActivityIndicator color={theme.interactive} /> : query.hasNextPage ? <AnimatedPressable style={styles.back} onPress={() => void query.fetchNextPage()}><ThemedText themeColor="interactive">Load more activity</ThemedText></AnimatedPressable> : null}
      onEndReached={() => { if (query.hasNextPage && !query.isFetching && !query.isFetchNextPageError) void query.fetchNextPage(); }} onEndReachedThreshold={0.4} />
  </SafeAreaView></ThemedView>;
}
const styles = StyleSheet.create({ flex: { flex: 1 }, header: { paddingHorizontal: 24, paddingVertical: 12, gap: 12 }, back: { minHeight: 48, justifyContent: 'center' }, title: { fontSize: 28, lineHeight: 36, fontWeight: '600' }, content: { padding: 24, paddingBottom: 120, gap: 12 } });
