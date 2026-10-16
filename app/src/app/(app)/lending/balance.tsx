import { useInfiniteQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FlatList, RefreshControl, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LendingAction, LendingBalanceSummary, LendingMoney, LendingRow, lendingStyles } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { NavigationRow, SegmentedControl } from '@/components/ui/screen-controls';
import { apiRequest } from '@/lib/api-client';
import { LendingBalance, LendingLoan, LendingPage, lendingNextPage, lendingStatus } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

type RepaymentAllocation = { id: number; loan_id: number; repayment_id: number; amount_minor: number; occurred_on: string; description: string | null };
type BalancePage = LendingPage<LendingLoan | RepaymentAllocation> & { summary: LendingBalance; person_name: string };

export default function BalanceExplanation() {
  const { id, currency, direction } = useLocalSearchParams<{ id: string; currency: string; direction: string }>();
  const token = useAuthStore(s => s.token)!;
  const [view, setView] = useState<'loans' | 'repayments'>('loans');
  const query = useInfiniteQuery({
    queryKey: ['lending', 'balance', id, currency, direction, view], initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => apiRequest<BalancePage>(`/lending/contacts/${id}/balance?${new URLSearchParams({ currency_code: currency ?? '', direction: direction ?? '', view, page: String(pageParam) })}`, { token, signal }),
    getNextPageParam: lendingNextPage,
  });
  const first = query.data?.pages[0];
  return <ThemedView style={{ flex: 1 }}><SafeAreaView edges={['top']} style={{ flex: 1 }}>
    <FlatList data={query.data?.pages.flatMap(page => page.data) ?? []} keyExtractor={item => `${view}:${item.id}`} contentContainerStyle={{ padding: 24, paddingBottom: 64 }}
      refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />}
      ListHeaderComponent={<View style={{ gap: 12 }}>
        <LendingAction label="Back" onPress={() => router.canGoBack() ? router.back() : router.replace('/(app)/(tabs)/lending')} />
        <ThemedText accessibilityRole="header" style={{ fontSize: 28, lineHeight: 36, fontWeight: '600' }}>How this balance adds up</ThemedText>
        {first ? <>
          <ThemedText style={lendingStyles.title}>{first.person_name} · {first.summary.currency_code}</ThemedText>
          <LendingBalanceSummary balance={first.summary} large />
          <ThemedText themeColor="textSecondary">Total {direction === 'lent' ? 'lent' : 'borrowed'} minus confirmed repayments equals the amount {direction === 'lent' ? 'still to receive' : 'still to pay'}. Pending, rejected and reversed repayments don’t reduce it. Voided loans are excluded.</ThemedText>
          <ThemedText themeColor="textSecondary">Recorded loans awaiting acknowledgement are included. Each currency and direction stays separate.</ThemedText>
        </> : null}
        <SegmentedControl label="Balance records" value={view} options={[{ value: 'loans', label: 'Loans' }, { value: 'repayments', label: 'Confirmed repayments' }]} onChange={setView} />
        {view === 'repayments' ? <ThemedText themeColor="textSecondary">Each row is the part of a confirmed repayment applied to one loan. Tap it to see that loan’s history.</ThemedText> : null}
        {query.isPending ? <ThemedText>Loading calculation…</ThemedText> : null}
        {query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} /> : null}
      </View>}
      renderItem={({ item }) => 'loan_id' in item ? <LendingRow>
        <NavigationRow label={item.description || 'Loan repayment'} detail={`Confirmed · ${item.occurred_on} · Payment #${item.repayment_id}`} onPress={() => router.push({ pathname: '/(app)/lending/loan', params: { id: item.loan_id } })} />
        <LendingMoney amount={item.amount_minor} currency={currency} />
      </LendingRow> : <LendingRow>
        <NavigationRow label={item.description || (item.direction === 'lent' ? 'Money lent' : 'Money borrowed')} detail={`${item.occurred_on} · ${lendingStatus(item)}`} onPress={() => router.push({ pathname: '/(app)/lending/loan', params: { id: item.id } })} />
        <LendingBalanceSummary balance={item} />
      </LendingRow>}
      ListEmptyComponent={!query.isPending && !query.error ? <ThemedText style={{ paddingVertical: 24 }} themeColor="textSecondary">{view === 'loans' ? 'No loans contribute to this balance.' : 'No confirmed repayments yet.'}</ThemedText> : null}
      ListFooterComponent={query.hasNextPage ? <LendingAction label="Load more records" disabled={query.isFetchingNextPage} onPress={() => void query.fetchNextPage()} /> : null}
      onEndReached={() => { if (query.hasNextPage && !query.isFetching && !query.isFetchNextPageError) void query.fetchNextPage(); }} onEndReachedThreshold={0.4} />
  </SafeAreaView></ThemedView>;
}
