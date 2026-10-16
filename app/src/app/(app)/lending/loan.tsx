import { LendingReminder } from '@/components/lending-reminder';
import { ActionSheet } from '@/components/ui/action-sheet';
import { useInfiniteQuery, useMutation, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction, LendingBalanceSummary, LendingMoney, LendingRow, LendingScreen, lendingStyles, useRefreshLending } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { errorMessage } from '@/lib/api-client';
import { LendingEvent, LendingLoan, lendingEventLabels, lendingGet, lendingNextPage, lendingPage, lendingStatus, lendingWrite } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function LoanDetail() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const token = useAuthStore(s => s.token)!;
  const userId = useAuthStore(s => s.user?.id);
  const refresh = useRefreshLending();
  const [confirm, setConfirm] = useState<{ path: string; action: string; label: string } | null>(null);
  const query = useQuery({ queryKey: ['lending', 'loan', id], queryFn: ({ signal }) => lendingGet<LendingLoan>(token, `loans/${id}`, signal) });
  const history = useInfiniteQuery({ queryKey: ['lending', 'history', id], initialPageParam: 1, queryFn: ({ pageParam, signal }) => lendingPage<LendingEvent>(token, `loans/${id}/history`, pageParam, signal), getNextPageParam: lendingNextPage });
  const change = useMutation({ mutationFn: ({ path, action }: { path: string; action: string }) => lendingWrite(token, path, { action }), onSuccess: async () => { setConfirm(null); await refresh(); } });
  const loan = query.data;
  const seen = new Set<number>();
  return <LendingScreen title="Loan details">
    {query.isLoading ? <ThemedText>Loading loan…</ThemedText> : null}
    {query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} /> : null}
    {loan ? <>
      {saved === 'loan' ? <ThemedText themeColor="positive" accessibilityLiveRegion="polite">Loan saved</ThemedText> : null}
      <ThemedText style={lendingStyles.title}>{loan.description || (loan.direction === 'lent' ? 'Money you lent' : 'Money you borrowed')}</ThemedText>
      <ThemedText themeColor="textSecondary">{lendingStatus(loan)} · {loan.occurred_on}</ThemedText>
      {loan.voided_at ? <><ThemedText themeColor="textSecondary">Original amount · Voided</ThemedText><LendingMoney amount={loan.amount_minor} currency={loan.currency_code} large /></> : <LendingBalanceSummary balance={loan} large />}
      {loan.pending_minor > 0 ? <><ThemedText themeColor="textSecondary">Reported, awaiting receipt confirmation</ThemedText><LendingMoney amount={loan.pending_minor} currency={loan.currency_code} /></> : null}
      {loan.due_on ? <ThemedText>Due {loan.due_on}</ThemedText> : null}
      {loan.private_note ? <LendingRow><ThemedText themeColor="textSecondary">Your private note</ThemedText><ThemedText>{loan.private_note}</ThemedText></LendingRow> : null}
      {!loan.voided_at && loan.outstanding_minor > loan.pending_minor && !loan.void_requested_by ? <PrimaryButton label={loan.can_confirm ? 'Record repayment received' : 'Report repayment'} onPress={() => router.push({ pathname: '/(app)/lending/repay', params: { id: loan.contact_id, loanId: loan.id, currency: loan.currency_code, direction: loan.direction } })} /> : null}
      {!loan.voided_at && !loan.is_creator && loan.sharing === 'shared' ? <View style={lendingStyles.actions}>
        {loan.acknowledgement !== 'acknowledged' ? <LendingAction label="Acknowledge loan" onPress={() => setConfirm({ path: `loans/${id}/actions`, action: 'acknowledge', label: 'Acknowledge these loan details' })} /> : null}
        {loan.acknowledgement !== 'disputed' ? <LendingAction label="Dispute loan" onPress={() => setConfirm({ path: `loans/${id}/actions`, action: 'dispute', label: 'Flag these loan details as disputed' })} /> : null}
      </View> : null}
      {!loan.voided_at && !loan.void_requested_by && loan.is_creator && loan.repaid_minor === 0 && loan.pending_minor === 0 ? <LendingAction label="Correct by voiding this loan" danger onPress={() => setConfirm({ path: `loans/${id}/actions`, action: 'void', label: loan.sharing === 'shared' ? 'Request agreement to void this loan. Add a replacement after agreement.' : 'Void this loan and retain its history. You can then add a corrected loan.' })} /> : null}
      {loan.void_requested_by && loan.void_requested_by !== userId ? <View style={lendingStyles.actions}><LendingAction label="Agree to void" danger onPress={() => setConfirm({ path: `loans/${id}/actions`, action: 'confirm_void', label: 'Agree to void this loan' })} /><LendingAction label="Decline correction" onPress={() => setConfirm({ path: `loans/${id}/actions`, action: 'reject_void', label: 'Decline this correction' })} /></View> : null}
      <LendingReminder loan={loan} />
      <ActionSheet title={confirm ? `${confirm.label}?` : 'Confirm change'} visible={!!confirm && !change.isPending} onClose={() => setConfirm(null)} actions={confirm ? [{ label: 'Confirm', destructive: ['void', 'confirm_void', 'reverse'].includes(confirm.action), onPress: () => change.mutate(confirm) }] : []} />
      {change.isSuccess ? <ThemedText themeColor="positive" accessibilityLiveRegion="polite">Change saved</ThemedText> : null}
      {change.isPending ? <ThemedText accessibilityLiveRegion="polite">Saving change…</ThemedText> : null}
      {change.error ? <ThemedText themeColor="danger">{errorMessage(change.error)}</ThemedText> : null}
      <ThemedText accessibilityRole="header" style={[lendingStyles.title, { marginTop: 20 }]}>History</ThemedText>
      {(history.data?.pages.flatMap(p => p.data) ?? []).map(event => {
        const payment = event.repayment;
        const showActions = payment && !seen.has(payment.id);
        if (payment) seen.add(payment.id);
        return <LendingRow key={event.id}><ThemedText>{event.actor_name} {lendingEventLabels[event.event] ?? 'updated this record'}</ThemedText>
          <ThemedText themeColor="textSecondary">{new Date(event.created_at).toLocaleString()}</ThemedText>
          {payment ? <><LendingMoney amount={payment.amount_minor} currency={payment.currency_code} /><ThemedText themeColor="textSecondary">Payment date: {payment.occurred_on.slice(0, 10)} · {payment.status}</ThemedText>{payment.note ? <ThemedText>{payment.note}</ThemedText> : null}</> : null}
          {showActions && payment ? <View style={lendingStyles.actions}>
            {payment.status === 'pending' && loan.can_confirm ? <LendingAction label="Confirm received" onPress={() => setConfirm({ path: `repayments/${payment.id}/actions`, action: 'confirm', label: 'Confirm you received this repayment' })} /> : null}
            {payment.status === 'pending' && (loan.can_confirm || payment.recorded_by === userId) ? <LendingAction label={loan.can_confirm ? 'Reject repayment' : 'Withdraw report'} onPress={() => setConfirm({ path: `repayments/${payment.id}/actions`, action: 'reject', label: 'Reject or withdraw this repayment report' })} /> : null}
            {payment.status === 'confirmed' && loan.can_confirm ? <LendingAction label="Reverse repayment" danger onPress={() => setConfirm({ path: `repayments/${payment.id}/actions`, action: 'reverse', label: 'Reverse this repayment and restore the outstanding amount' })} /> : null}
          </View> : null}
        </LendingRow>;
      })}
      {history.error ? <QueryErrorCard error={history.error} onRetry={() => void history.refetch()} /> : null}
      {history.hasNextPage ? <LendingAction label="Load older history" disabled={history.isFetchingNextPage} onPress={() => void history.fetchNextPage()} /> : null}
    </> : null}
  </LendingScreen>;
}
