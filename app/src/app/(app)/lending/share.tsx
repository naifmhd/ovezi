import { useInfiniteQuery, useMutation, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction, LendingMoney, LendingRow, LendingScreen, useRefreshLending } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { errorMessage } from '@/lib/api-client';
import { LendingContact, LendingLoan, lendingGet, lendingNextPage, lendingPage, lendingWrite } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function ShareLoans() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const token = useAuthStore(s => s.token)!;
  const verified = useAuthStore(s => s.user?.email_verified_at);
  const refresh = useRefreshLending();
  const [selected, setSelected] = useState<number[]>([]);
  const [review, setReview] = useState(false);
  const person = useQuery({ queryKey: ['lending', 'contact', id], queryFn: () => lendingGet<LendingContact>(token, `contacts/${id}`) });
  const loans = useInfiniteQuery({ queryKey: ['lending', 'loans', id], initialPageParam: 1, queryFn: ({ pageParam, signal }) => lendingPage<LendingLoan>(token, `contacts/${id}/loans`, pageParam, signal), getNextPageParam: lendingNextPage });
  const share = useMutation({ mutationFn: () => lendingWrite(token, `contacts/${id}/share`, { loan_ids: selected }), onSuccess: async () => { await refresh(); router.back(); } });
  const invitation = useMutation({ mutationFn: (action: string) => lendingWrite(token, `invitations/${id}/actions`, { action }), onSuccess: refresh });
  const available = (loans.data?.pages.flatMap(p => p.data) ?? []).filter(loan => loan.sharing === 'private' && !loan.voided_at);
  return <LendingScreen title={review ? 'Review sharing' : 'Connect & share'}>
    <ThemedText>{person.data?.name} · {person.data?.email ?? 'No email added'}</ThemedText>
    {!verified ? <LendingAction label="Verify your email in Profile" onPress={() => router.push('/(app)/(tabs)/profile')} /> : null}
    {!person.data?.email ? <LendingAction label="Add their email" onPress={() => router.push({ pathname: '/(app)/lending/person', params: { id } })} /> : null}
    {person.data?.invitation_status === 'pending' || person.data?.invitation_status === 'expired' ? <LendingRow><ThemedText>{person.data.invitation_status === 'expired' ? 'The invitation expired.' : 'Waiting for them to verify their email and accept.'}</ThemedText><LendingAction label="Resend invitation" disabled={invitation.isPending} onPress={() => invitation.mutate('resend')} /><LendingAction label="Revoke invitation" disabled={invitation.isPending} onPress={() => invitation.mutate('revoke')} /></LendingRow> : null}
    <ThemedText themeColor="textSecondary">{review ? 'The selected loans below, including their descriptions and repayment history, will become visible to this person. Private notes stay private.' : 'Choose the loans to share. Unselected loans stay private. An invitation alone does not acknowledge a debt.'}</ThemedText>
    {available.filter(loan => !review || selected.includes(loan.id)).map(loan => <AnimatedPressable key={loan.id} accessibilityRole="checkbox" accessibilityState={{ checked: selected.includes(loan.id), disabled: review }} disabled={review} onPress={() => setSelected(current => current.includes(loan.id) ? current.filter(value => value !== loan.id) : [...current, loan.id])}>
      <LendingRow><ThemedText style={{ fontWeight: '600' }}>{selected.includes(loan.id) ? 'Selected · ' : ''}{loan.description || (loan.direction === 'lent' ? 'Money lent' : 'Money borrowed')}</ThemedText><LendingMoney amount={loan.amount_minor} currency={loan.currency_code} /><ThemedText themeColor="textSecondary">{loan.occurred_on} · {loan.direction === 'lent' ? 'You lent' : 'You borrowed'}</ThemedText><ThemedText themeColor="textSecondary">Outstanding</ThemedText><LendingMoney amount={loan.outstanding_minor} currency={loan.currency_code} /></LendingRow>
    </AnimatedPressable>)}
    {!available.length && !loans.isLoading ? <ThemedText>No private loans on this page.</ThemedText> : null}
    {!review && loans.hasNextPage ? <LendingAction label="Load more loans" disabled={loans.isFetchingNextPage} onPress={() => void loans.fetchNextPage()} /> : null}
    {share.error || invitation.error || person.error || loans.error ? <ThemedText themeColor="danger">{errorMessage(share.error ?? invitation.error ?? person.error ?? loans.error)}</ThemedText> : null}
    <View style={{ gap: 8 }}>{review ? <><PrimaryButton label={person.data?.is_connected ? 'Share selected loans' : 'Send invitation & share selected loans'} loading={share.isPending} onPress={() => share.mutate()} /><LendingAction label="Change selection" onPress={() => setReview(false)} /></> : <PrimaryButton label={`Review ${selected.length} selected loan${selected.length === 1 ? '' : 's'}`} disabled={!selected.length || selected.length > 100 || !person.data?.email || !verified} onPress={() => setReview(true)} />}</View>
  </LendingScreen>;
}
