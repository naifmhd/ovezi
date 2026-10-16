import { useInfiniteQuery, useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { View } from 'react-native';
import { LendingAction, LendingMoney, LendingRow, LendingScreen, useRefreshLending } from '@/components/lending-ui';
import { NavigationRow } from '@/components/ui/screen-controls';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { AttentionKind, AttentionLoan, attentionLabels, LendingInvitation, lendingNextPage, lendingPage, lendingWrite, localToday } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';
import { errorMessage } from '@/lib/api-client';

export default function AttentionScreen() {
  const params = useLocalSearchParams<{ kind: string }>();
  const kind: AttentionKind = Object.hasOwn(attentionLabels, params.kind ?? '') ? params.kind as AttentionKind : 'overdue';
  const token = useAuthStore(s => s.token)!;
  const refresh = useRefreshLending();
  const today = localToday();
  const loans = useInfiniteQuery({ queryKey: ['lending', 'attention', kind, today], initialPageParam: 1, enabled: kind !== 'invitations', queryFn: ({ pageParam, signal }) => lendingPage<AttentionLoan>(token, `attention?kind=${kind}&today=${today}`, pageParam, signal), getNextPageParam: lendingNextPage });
  const invitations = useInfiniteQuery({ queryKey: ['lending', 'invitations'], initialPageParam: 1, enabled: kind === 'invitations', queryFn: ({ pageParam, signal }) => lendingPage<LendingInvitation>(token, 'invitations', pageParam, signal), getNextPageParam: lendingNextPage });
  const respond = useMutation({ mutationFn: ({ id, action }: { id: number; action: string }) => lendingWrite(token, `invitations/${id}/actions`, { action }), onSuccess: refresh });
  const active = kind === 'invitations' ? invitations : loans;
  const empty = active.data?.pages.every(p => p.data.length === 0);
  return <LendingScreen title={attentionLabels[kind]}>
    {kind === 'due_soon' ? <ThemedText themeColor="textSecondary">Outstanding loans due today or in the next 7 days.</ThemedText> : null}
    {active.isLoading ? <ThemedText>Loading records…</ThemedText> : null}
    {active.error ? <QueryErrorCard error={active.error} onRetry={() => void active.refetch()} /> : empty ? <ThemedText themeColor="textSecondary">You’re up to date here.</ThemedText> : null}
    {kind === 'invitations' ? invitations.data?.pages.flatMap(p => p.data).map(invite => <LendingRow key={invite.id}>
      <ThemedText>{invite.sender_name} wants to connect</ThemedText>
      <ThemedText themeColor="textSecondary">Review {invite.loan_count} shared loan{invite.loan_count === 1 ? '' : 's'}. Accepting a connection does not acknowledge a debt.</ThemedText>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}><LendingAction variant="secondary" label="Accept connection" disabled={respond.isPending} onPress={() => respond.mutate({ id: invite.id, action: 'accept' })} /><LendingAction label="Decline" disabled={respond.isPending} onPress={() => respond.mutate({ id: invite.id, action: 'decline' })} /></View>
    </LendingRow>) : loans.data?.pages.flatMap(p => p.data).map(loan => <LendingRow key={loan.id}>
      <NavigationRow label={loan.person_name} detail={loan.description || (loan.direction === 'lent' ? 'Money you lent' : 'Money you borrowed')} onPress={() => router.push({ pathname: '/(app)/lending/loan', params: { id: loan.id } })} />
      <ThemedText themeColor="textSecondary">{loan.direction === 'lent' ? 'Still to receive' : 'Still to pay'}</ThemedText><LendingMoney amount={loan.outstanding_minor} currency={loan.currency_code} />
      {loan.due_on ? <ThemedText themeColor="textSecondary">Due {loan.due_on}</ThemedText> : null}
    </LendingRow>)}
    {respond.error ? <ThemedText themeColor="danger">{errorMessage(respond.error)}</ThemedText> : null}
    {active.hasNextPage ? <LendingAction label="Load more" disabled={active.isFetchingNextPage} onPress={() => void active.fetchNextPage()} /> : null}
  </LendingScreen>;
}
