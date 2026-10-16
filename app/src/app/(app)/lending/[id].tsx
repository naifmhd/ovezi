import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { RefreshControl, View } from 'react-native';
import { AppScreen } from '@/components/app-screen';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction, LendingBalanceSummary, LendingMoney, LendingRow, lendingStyles, useRefreshLending } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { NavigationRow } from '@/components/ui/screen-controls';
import { LendingContact, LendingLoan, lendingGet, lendingNextPage, lendingPage, lendingStatus } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function PersonDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const token = useAuthStore(s => s.token)!;
  const sharingEnabled = useAuthStore(s => s.user?.features?.lending_sharing) !== false;
  const refresh = useRefreshLending();
  const person = useQuery({ queryKey: ['lending', 'contact', id], queryFn: ({ signal }) => lendingGet<LendingContact>(token, `contacts/${id}`, signal) });
  const loans = useInfiniteQuery({ queryKey: ['lending', 'loans', id], initialPageParam: 1, queryFn: ({ pageParam, signal }) => lendingPage<LendingLoan>(token, `contacts/${id}/loans`, pageParam, signal), getNextPageParam: lendingNextPage });
  return <AppScreen title={person.data?.name ?? 'Lending records'} backFallback="/(app)/(tabs)/lending" scrollProps={{ refreshControl: <RefreshControl refreshing={person.isRefetching} onRefresh={() => void refresh()} /> }}>
    {person.isLoading ? <ThemedText>Loading person…</ThemedText> : null}
    {person.error ? <QueryErrorCard error={person.error} onRetry={() => void person.refetch()} /> : null}
    {person.data ? <>
      {person.data.balances.map(balance => <LendingRow key={`${balance.direction}-${balance.currency_code}`}>
        <AnimatedPressable accessibilityRole="button" accessibilityLabel={`Explain ${balance.currency_code} ${balance.direction === 'lent' ? 'to receive' : 'to pay'} balance`} onPress={() => router.push({ pathname: '/(app)/lending/balance', params: { id, currency: balance.currency_code, direction: balance.direction } })}>
          <LendingBalanceSummary balance={balance} large />
          <ThemedText themeColor="interactive">View calculation ›</ThemedText>
        </AnimatedPressable>
        {balance.outstanding_minor > 0 ? <LendingAction variant="secondary" label={`Record ${balance.currency_code} repayment`} onPress={() => router.push({ pathname: '/(app)/lending/repay', params: { id, currency: balance.currency_code, direction: balance.direction } })} /> : <ThemedText themeColor="positive">Settled</ThemedText>}
      </LendingRow>)}
      {!person.data.balances.length ? <ThemedText themeColor="textSecondary">No money recorded yet. Add your first loan below.</ThemedText> : <ThemedText themeColor="textSecondary">Balances include recorded loans awaiting acknowledgement. Each currency is settled separately.</ThemedText>}
      {person.data.is_owner || (sharingEnabled && person.data.is_connected) ? <PrimaryButton label="Record a loan" onPress={() => router.push({ pathname: '/(app)/lending/create', params: { id } })} /> : null}
      <View style={{ marginTop: 12 }}>
        <NavigationRow label="Export statement" detail="PDF or CSV · choose activity dates" icon={{ ios: 'square.and.arrow.up', android: 'ios_share', web: 'ios_share' }} onPress={() => router.push({ pathname: '/(app)/lending/statement', params: { id } })} />
        <NavigationRow label="Person settings" detail="Name, email and connection" icon={{ ios: 'person.crop.circle', android: 'account_circle', web: 'account_circle' }} onPress={() => router.push({ pathname: '/(app)/lending/person', params: { id } })} />
        {sharingEnabled && person.data.is_owner && !person.data.disconnected_at ? <NavigationRow label="Connect & share" detail="Choose which loans this person can see" icon={{ ios: 'person.2', android: 'group', web: 'group' }} onPress={() => router.push({ pathname: '/(app)/lending/share', params: { id } })} /> : null}
      </View>
      <ThemedText accessibilityRole="header" style={[lendingStyles.title, { marginTop: 20 }]}>Loans & history</ThemedText>
      {(loans.data?.pages.flatMap(p => p.data) ?? []).map(loan => <AnimatedPressable key={loan.id} accessibilityRole="button" onPress={() => router.push({ pathname: '/(app)/lending/loan', params: { id: loan.id } })}>
        <LendingRow><ThemedText style={lendingStyles.title}>{loan.description || (loan.direction === 'lent' ? 'Money lent' : 'Money borrowed')}</ThemedText>
          <ThemedText themeColor="textSecondary">{loan.direction === 'lent' ? 'Lent' : 'Borrowed'}</ThemedText>
          <LendingMoney amount={loan.amount_minor} currency={loan.currency_code} /><ThemedText themeColor="textSecondary">{loan.occurred_on} · {lendingStatus(loan)}</ThemedText>
          {loan.due_on ? <ThemedText themeColor="textSecondary">Due {loan.due_on}</ThemedText> : null}
        </LendingRow></AnimatedPressable>)}
      {loans.error ? <QueryErrorCard error={loans.error} onRetry={() => void loans.refetch()} /> : null}
      {loans.hasNextPage ? <LendingAction label={loans.isFetchingNextPage ? 'Loading…' : 'Load older loans'} disabled={loans.isFetchingNextPage} onPress={() => void loans.fetchNextPage()} /> : null}
    </> : null}
  </AppScreen>;
}
