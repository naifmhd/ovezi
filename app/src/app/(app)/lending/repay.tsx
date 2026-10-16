import { useMutation, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { NativeDateField } from '@/components/native-date-field';
import { LendingAction, LendingMoney, LendingScreen, useRefreshLending } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { errorMessage } from '@/lib/api-client';
import { currencyFractionDigits, minorAmountInput, parseDecimalToInteger } from '@/lib/format';
import { LendingContact, LendingLoan, lendingGet, lendingWrite } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function RepayLoan() {
  const { id, loanId, currency = 'MVR', direction = 'lent' } = useLocalSearchParams<{ id: string; loanId?: string; currency?: string; direction?: string }>();
  const token = useAuthStore(s => s.token)!;
  const refresh = useRefreshLending();
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [date, setDate] = useState((() => { const now = new Date(); return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`; })());
  const [confirm, setConfirm] = useState(false);
  const person = useQuery({ queryKey: ['lending', 'contact', id], queryFn: () => lendingGet<LendingContact>(token, `contacts/${id}`) });
  const loan = useQuery({ queryKey: ['lending', 'loan', loanId], queryFn: () => lendingGet<LendingLoan>(token, `loans/${loanId}`), enabled: !!loanId });
  const outstanding = loanId ? Math.max(0, (loan.data?.outstanding_minor ?? 0) - (loan.data?.pending_minor ?? 0)) : person.data?.balances.find(b => b.currency_code === currency && b.direction === direction)?.outstanding_minor ?? 0;
  const save = useMutation({ mutationFn: async () => {
    const minor = parseDecimalToInteger(amount, currencyFractionDigits(currency));
    if (!minor || minor < 0) throw new Error('Enter a positive repayment amount.');
    return lendingWrite(token, `contacts/${id}/repayments`, { amount_minor: minor, currency_code: currency, direction, occurred_on: date, note: note.trim() || null, ...(loanId ? { loan_id: Number(loanId) } : {}) });
  }, onSuccess: async () => { await refresh(); } });
  if (save.isSuccess) return <LendingScreen title="Repayment saved">
    <ThemedText themeColor="positive" accessibilityLiveRegion="polite">Your repayment record is saved.</ThemedText>
    <ThemedText themeColor="textSecondary">Shared repayments may still need the lender to confirm receipt. The balance changes after confirmation.</ThemedText>
    <PrimaryButton label="View updated records" onPress={() => router.replace({ pathname: '/(app)/lending/[id]', params: { id } })} />
  </LendingScreen>;
  return <LendingScreen title="Record repayment">
    <ThemedText>{person.data?.name ?? 'Loading person…'} · {direction === 'lent' ? 'Money you lent' : 'Money you borrowed'}</ThemedText>
    <ThemedText themeColor="textSecondary">{loanId ? 'Available to repay' : 'Outstanding in this currency'}</ThemedText><LendingMoney amount={outstanding} currency={currency} large />
    <FormField label={`Repayment amount (${currency})`} value={amount} onChangeText={value => { setAmount(value); setConfirm(false); }} keyboardType="decimal-pad" maxLength={18} />
    <LendingAction label={`Settle ${loanId ? 'this loan' : `this currency (${currency})`}`} disabled={!outstanding} onPress={() => { setAmount(minorAmountInput(outstanding, currency)); setConfirm(false); }} />
    <NativeDateField label="Payment date" value={date} onChange={value => { setDate(value); setConfirm(false); }} />
    <FormField label="Payment note (visible with shared loans)" value={note} onChangeText={value => { setNote(value); setConfirm(false); }} maxLength={500} />
    <ThemedText themeColor="textSecondary">{loanId ? 'This repayment applies to the selected loan.' : 'Applied to the oldest loans in this currency first. Pending repayments already reserve part of the balance.'} Borrower-reported payments on shared loans wait for lender confirmation.</ThemedText>
    <ThemedText themeColor="textSecondary">Record money already paid elsewhere. Ovezi does not transfer money.</ThemedText>
    {save.error || person.error || loan.error ? <ThemedText themeColor="danger">{errorMessage(save.error ?? person.error ?? loan.error)}</ThemedText> : null}
    {confirm ? <><ThemedText>Record {amount} {currency} with {person.data?.name}?</ThemedText><PrimaryButton label={save.isError ? "Retry saving repayment" : "Confirm repayment record"} loadingLabel="Saving repayment…" loading={save.isPending} onPress={() => save.mutate()} /><LendingAction label="Keep editing" disabled={save.isPending} onPress={() => setConfirm(false)} /></> : <PrimaryButton label="Review repayment" disabled={!person.data || !amount || (!!loanId && !loan.data)} onPress={() => setConfirm(true)} />}
  </LendingScreen>;
}
