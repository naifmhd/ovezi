import { HeaderAction } from '@/components/ui/header-action';
import { FormField } from '@/components/auth/form-field';
import { NativeDateField } from '@/components/native-date-field';
import { Disclosure } from '@/components/ui/disclosure';
import { useInfiniteQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ExpenseRow } from '@/components/expense-row';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { fetchExpenses } from '@/lib/expenses-api';
import { selectionHaptic } from '@/lib/haptics';
import { useAuthStore } from '@/stores/auth-store';
import type { ExpenseType } from '@/types/api';

type ExpenseFilter = 'all' | ExpenseType;

const filters: { value: ExpenseFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'group', label: 'Groups' },
  { value: 'direct', label: '1-on-1' },
  { value: 'personal', label: 'Just for me' },
];

export default function ExpensesScreen() {
  const params = useLocalSearchParams<{ groupId?: string; groupName?: string }>();
  const groupId = Number(params.groupId) > 0 ? Number(params.groupId) : undefined;
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [from, setFrom] = useState('');
  const [until, setUntil] = useState('');
  useEffect(() => { const timer = setTimeout(() => setDebouncedSearch(search.trim()), 280); return () => clearTimeout(timer); }, [search]);
  const rangeInvalid = Boolean(from && until && until < from);
  const boundary = (value: string, nextDay = false) => {
    if (!value) return undefined;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day + (nextDay ? 1 : 0)).toISOString();
  };
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const theme = useTheme();
  const [filter, setFilter] = useState<ExpenseFilter>('all');
  const query = useInfiniteQuery({
    queryKey: ['expenses', 'ledger', groupId, filter, debouncedSearch, from, until],
    enabled: !rangeInvalid,
    initialPageParam: 1,
    queryFn: ({ pageParam }) => fetchExpenses(token, {
      ...(filter === 'all' ? {} : { expenseType: filter }),
      groupId, search: debouncedSearch, from: boundary(from), before: boundary(until, true),
      page: pageParam,
      perPage: 30,
    }),
    getNextPageParam: (lastPage) => lastPage.meta.current_page < lastPage.meta.last_page
      ? lastPage.meta.current_page + 1
      : undefined,
  });
  const expenses = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction hitSlop={10} onPress={() => router.back()} style={styles.headerTouch}>
            <ThemedText style={styles.headerAction} themeColor="interactive">‹ Back</ThemedText>
          </HeaderAction>
          <ThemedText style={styles.headerTitle}>{groupId ? 'Group expenses' : 'Expenses'}</ThemedText>
          <Pressable hitSlop={10} onPress={() => router.push({ pathname: '/(app)/expenses/create', params: groupId ? { groupId } : {} })} style={styles.headerTouch}>
            <ThemedText style={styles.headerAction} themeColor="interactive">+ Add</ThemedText>
          </Pressable>
        </View>

        <FlatList
          contentContainerStyle={styles.content}
          data={expenses}
          keyExtractor={(expense) => String(expense.id)}
          ListHeaderComponent={(
            <View style={styles.listHeader}>
              {params.groupName ? <ThemedText>{params.groupName}</ThemedText> : null}
              <FormField label="Search expenses" placeholder="Description or category" value={search} onChangeText={setSearch} maxLength={120} />
              <Disclosure title={from || until ? `Dates · ${from || 'Any'} to ${until || 'Any'}` : 'Filter by date'}>
                <NativeDateField label="From date" emptyLabel="Any start date" optional value={from} onChange={setFrom} />
                <NativeDateField label="Through date" emptyLabel="Any end date" optional value={until} onChange={setUntil} />
                <AnimatedPressable style={styles.headerTouch} onPress={() => { setFrom(''); setUntil(''); }}><ThemedText themeColor="interactive">Clear dates</ThemedText></AnimatedPressable>
              </Disclosure>
              {rangeInvalid ? <ThemedText themeColor="danger">The end date must be on or after the start date.</ThemedText> : null}
              {!groupId ? <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                <View style={styles.filterRow}>
                  {filters.map((item) => {
                    const selected = filter === item.value;
                    return (
                      <AnimatedPressable
                        accessibilityRole="button"
                        accessibilityState={{ selected }}
                        key={item.value}
                        onPress={() => {
                          selectionHaptic();
                          setFilter(item.value);
                        }}
                        style={[
                          styles.filter,
                          {
                            backgroundColor: selected ? theme.primary : theme.surface,
                            borderColor: selected ? theme.primary : theme.border,
                          },
                        ]}>
                        <ThemedText style={[styles.filterLabel, selected && { color: theme.primaryText }]}>
                          {item.label}
                        </ThemedText>
                      </AnimatedPressable>
                    );
                  })}
                </View>
              </ScrollView> : null}
              {query.error ? (
                <QueryErrorCard
                  error={query.error}
                  onRetry={() => void query.refetch()}
                  retrying={query.isRefetching}
                />
              ) : null}
            </View>
          )}
          ListEmptyComponent={!rangeInvalid && !query.isLoading && !query.error ? (
            <ThemedView type="backgroundElement" style={styles.emptyCard}>
              <ThemedText style={styles.emptyTitle}>No expenses here yet</ThemedText>
              <ThemedText style={styles.emptyCopy} themeColor="textSecondary">
                Add an expense or choose another filter.
              </ThemedText>
              <Pressable onPress={() => router.push({ pathname: '/(app)/expenses/create', params: groupId ? { groupId } : {} })}>
                <ThemedText style={styles.emptyAction} themeColor="interactive">Add expense</ThemedText>
              </Pressable>
            </ThemedView>
          ) : null}
          ListFooterComponent={query.isLoading || query.isFetchingNextPage ? (
            <ActivityIndicator color={theme.interactive} style={styles.loading} />
          ) : query.hasNextPage ? <AnimatedPressable style={styles.headerTouch} onPress={() => void query.fetchNextPage()}><ThemedText themeColor="interactive">Load more expenses</ThemedText></AnimatedPressable> : <View style={styles.footerSpace} />}
          onEndReached={() => {
            if (query.hasNextPage && !query.isFetching && !query.isFetchNextPageError && !rangeInvalid) void query.fetchNextPage();
          }}
          onEndReachedThreshold={0.4}
          refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />}
          renderItem={({ item }) => (
            <ExpenseRow
              currentUserId={user.id}
              expense={item}
              onPress={() => router.push({ pathname: '/(app)/expenses/[id]', params: { id: item.id } })}
            />
          )}
          showsVerticalScrollIndicator={false}
        />
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: {
    minHeight: 60,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
  },
  headerTouch: { minWidth: 48, minHeight: 48, justifyContent: 'center' },
  headerAction: { fontSize: 14, fontWeight: '600' },
  headerTitle: { fontSize: 17, fontWeight: '600' },
  content: {
    padding: Spacing.four,
    paddingBottom: 120,
    gap: 11,
    maxWidth: 720,
    width: '100%',
    alignSelf: 'center',
  },
  listHeader: { gap: 12, marginBottom: 2 },
  filterRow: { flexDirection: 'row', gap: 8, paddingEnd: 8 },
  filter: {
    minHeight: 48,
    paddingVertical: 10,
    borderRadius: Radius.pill,
    borderWidth: 1,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filterLabel: { fontSize: 13, fontWeight: '600' },
  emptyCard: { borderRadius: Radius.card, padding: 22, gap: 7, marginTop: 24 },
  emptyTitle: { fontSize: 18, lineHeight: 25, fontWeight: '600' },
  emptyCopy: { fontSize: 14, lineHeight: 20 },
  emptyAction: { fontSize: 14, fontWeight: '600', marginTop: 5 },
  loading: { paddingVertical: 32 },
  footerSpace: { height: 24 },
});
