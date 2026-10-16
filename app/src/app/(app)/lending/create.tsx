import { useInfiniteQuery, useMutation, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { View } from 'react-native';
import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { CurrencyPicker } from '@/components/currency-picker';
import { NativeDateField } from '@/components/native-date-field';
import { LendingAction, LendingScreen, lendingStyles, useRefreshLending } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ChoiceChip } from '@/components/ui/choice-chip';
import { NavigationRow, SearchField, SegmentedControl } from '@/components/ui/screen-controls';
import { apiRequest, errorMessage } from '@/lib/api-client';
import { currencyFractionDigits, parseDecimalToInteger } from '@/lib/format';
import { LendingContact, LendingDirection, LendingLoan, lendingGet, lendingNextPage, lendingPage, lendingWrite, localToday } from '@/lib/lending-api';
import { clearLoanDraft, LoanDraft, readLoanDraft, saveLoanDraft } from '@/lib/loan-draft';
import { useAuthStore } from '@/stores/auth-store';

type NewPerson = { name: string; email: string | null };

export default function CreateLoan() {
  const userId = useAuthStore(s => s.user!.id);
  return <DraftEntry key={userId} userId={userId} />;
}

function DraftEntry({ userId }: { userId: number }) {
  const [draft, setDraft] = useState<LoanDraft | null>(null);
  const [ready, setReady] = useState(false);
  const [decided, setDecided] = useState(false);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    let active = true;
    void readLoanDraft(userId).then(value => { if (active) { setDraft(value); setDecided(!value); setReady(true); } })
      .catch(() => { if (active) { setError('Couldn’t read the saved draft. You can continue with a new loan.'); setReady(true); } });
    return () => { active = false; };
  }, [userId]);
  if (!ready) return <LendingScreen title="Record a loan"><ThemedText>Checking saved draft…</ThemedText></LendingScreen>;
  if (!decided) return <LendingScreen title="Record a loan">
    <ThemedText style={lendingStyles.title}>{draft ? 'Continue your unfinished loan?' : 'Draft unavailable'}</ThemedText>
    <ThemedText themeColor="textSecondary">{draft ? 'Your entry is saved on this device. Review it before saving the loan.' : error}</ThemedText>
    {draft ? <PrimaryButton label="Resume draft" onPress={() => setDecided(true)} /> : null}
    <LendingAction label={draft ? 'Discard draft & start new' : 'Start a new loan'} onPress={() => {
      void clearLoanDraft(userId).then(() => { setDraft(null); setDecided(true); }).catch(() => setError('Couldn’t clear the draft. Try again.'));
    }} />
    {draft && error ? <ThemedText themeColor="danger">{error}</ThemedText> : null}
  </LendingScreen>;
  return <LoanForm initialDraft={draft} userId={userId} />;
}

function LoanForm({ initialDraft, userId }: { initialDraft: LoanDraft | null; userId: number }) {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const token = useAuthStore(s => s.token)!;
  const sharingEnabled = useAuthStore(s => s.user?.features?.lending_sharing) !== false;
  const defaultCurrency = useAuthStore(s => s.user?.default_currency_code) ?? 'MVR';
  const refresh = useRefreshLending();
  const [contactId, setContactId] = useState(initialDraft?.contactId ?? (initialDraft ? undefined : id));
  const [newPerson, setNewPerson] = useState<NewPerson | null>(initialDraft?.newPerson ?? null);
  const [choosing, setChoosing] = useState(!initialDraft && !id);
  const person = useQuery({ queryKey: ['lending', 'contact', contactId], enabled: !!contactId, queryFn: ({ signal }) => lendingGet<LendingContact>(token, `contacts/${contactId}`, signal) });
  const [direction, setDirection] = useState<LendingDirection>(initialDraft?.direction ?? 'lent');
  const [currency, setCurrency] = useState(initialDraft?.currency ?? defaultCurrency);
  const initializedCurrency = useRef<string | null>(initialDraft?.contactId ?? null);
  useEffect(() => {
    if (contactId && person.data && initializedCurrency.current !== contactId) {
      initializedCurrency.current = contactId;
      setCurrency(person.data.last_currency_code ?? defaultCurrency);
    }
  }, [contactId, person.data, defaultCurrency]);
  const [amount, setAmount] = useState(initialDraft?.amount ?? '');
  const [description, setDescription] = useState(initialDraft?.description ?? '');
  const [note, setNote] = useState(initialDraft?.note ?? '');
  const [date, setDate] = useState(initialDraft?.date ?? localToday());
  const [due, setDue] = useState(initialDraft?.due ?? '');
  const [details, setDetails] = useState(false);
  const [shared, setShared] = useState(initialDraft?.shared ?? false);
  const completed = useRef(false);
  const [savedLoanId, setSavedLoanId] = useState<number | null>(null);
  const [cleanupError, setCleanupError] = useState<string | null>(null);
  async function finishSavedLoan(loanId: number) {
    try {
      await clearLoanDraft(userId);
      await refresh();
      router.replace({ pathname: '/(app)/lending/loan', params: { id: loanId } });
    } catch { setCleanupError('Your loan is saved. Couldn’t clear the local draft. Retry to finish.'); }
  }
  const [draftStatus, setDraftStatus] = useState('');
  useEffect(() => {
    if (completed.current || (!contactId && !newPerson)) return;
    let active = true;
    setDraftStatus('Saving draft…');
    void saveLoanDraft(userId, { contactId, newPerson, direction, currency, amount, description, note, date, due, shared })
      .then(() => { if (active) setDraftStatus('Draft saved on this device'); })
      .catch(() => { if (active) setDraftStatus('Draft couldn’t be saved on this device. Keep this screen open.'); });
    return () => { active = false; };
  }, [userId, contactId, newPerson, direction, currency, amount, description, note, date, due, shared]);
  const [duplicates, setDuplicates] = useState<{ signature: string; loans: LendingLoan[]; total: number } | null>(null);
  const willShare = !newPerson && (shared || person.data?.is_owner === false);
  const save = useMutation({ mutationFn: async (confirmedSignature: string | undefined) => {
    const minor = parseDecimalToInteger(amount, currencyFractionDigits(currency));
    if (!minor || minor < 0) throw new Error('Enter a positive amount with the correct currency precision.');
    const input = {
      ...(newPerson ? { person: newPerson } : { contact_id: Number(contactId) }),
      direction, amount_minor: minor, currency_code: currency, occurred_on: date, due_on: due || null,
      description: description.trim() || null, private_note: note.trim() || null, shared: willShare,
    };
    const signature = JSON.stringify(input);
    if (confirmedSignature !== signature) {
      setDuplicates(null);
      const matches = await apiRequest<{ data: LendingLoan[]; total: number }>('/lending/loans/duplicates', { token, method: 'POST', body: { ...input, private_note: undefined } });
      if (matches.total > 0) { setDuplicates({ signature, loans: matches.data, total: matches.total }); return null; }
    }
    return lendingWrite<{ data: LendingLoan }>(token, 'loans', input);
  }, onSuccess: async result => { if (!result) return; completed.current = true; setSavedLoanId(result.data.id); await finishSavedLoan(result.data.id); } });
  function choose(next: string | NewPerson) {
    setShared(false); setChoosing(false); setDuplicates(null); save.reset();
    if (typeof next === 'string') { setContactId(next); setNewPerson(null); }
    else { setContactId(undefined); setNewPerson(next); setCurrency(defaultCurrency); initializedCurrency.current = null; }
  }
  if (savedLoanId) return <LendingScreen title="Loan saved"><ThemedText>{cleanupError ?? 'Finishing up…'}</ThemedText>{cleanupError ? <LendingAction label="Finish saving" onPress={() => void finishSavedLoan(savedLoanId)} /> : null}</LendingScreen>;
  if (choosing) return <LendingScreen title="Record a loan"><PersonPicker onChoose={choose} /></LendingScreen>;
  if (!newPerson && person.isLoading) return <LendingScreen title="Record a loan"><ThemedText>Loading person…</ThemedText></LendingScreen>;
  return <LendingScreen title="Record a loan">
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
      <ThemedText style={[lendingStyles.title, { flex: 1 }]}>{newPerson?.name ?? person.data?.name}</ThemedText>
      <LendingAction label="Change person" disabled={save.isPending} onPress={() => setChoosing(true)} />
    </View>
    {person.error && !newPerson ? <QueryErrorCard error={person.error} onRetry={() => void person.refetch()} /> : null}
    <SegmentedControl label="Loan direction" value={direction} options={[{ value: 'lent', label: 'I lent money' }, { value: 'borrowed', label: 'I borrowed money' }]} onChange={setDirection} />
    <FormField label={`Amount (${currency})`} value={amount} onChangeText={setAmount} keyboardType="decimal-pad" maxLength={18} placeholder="0.00" />
    <CurrencyPicker label="Loan currency" value={currency} onChange={setCurrency} />
    <ThemedText themeColor="textSecondary">This loan will be repaid in {currency}.</ThemedText>
    <FormField label="Description (optional, shared with the loan)" value={description} onChangeText={setDescription} maxLength={500} />
    <LendingAction label={details ? 'Hide additional details' : 'Add dates or a private note'} onPress={() => setDetails(!details)} />
    {details ? <>
      <NativeDateField label="Loan date" value={date} onChange={setDate} />
      <NativeDateField label="Due date (optional)" value={due} onChange={setDue} optional />
      <FormField label="Private note (only you)" value={note} onChangeText={setNote} multiline maxLength={2000} />
    </> : <ThemedText themeColor="textSecondary">Loan date: {date}{due ? ` · Due: ${due}` : ''}{note ? ' · Private note added' : ''}</ThemedText>}
    {sharingEnabled && !newPerson && person.data?.is_connected && person.data.is_owner ? <View style={lendingStyles.actions}><ChoiceChip label="Keep private" active={!shared} onPress={() => setShared(false)} /><ChoiceChip label="Share with this person" active={shared} onPress={() => setShared(true)} /></View> : null}
    <ThemedText themeColor="textSecondary">{willShare ? 'They will see this loan and its repayment history, and can acknowledge or dispute it. Your private note stays private.' : 'Only you can see this record. You can choose to share it later.'}</ThemedText>
    {duplicates ? <View style={{ gap: 8, paddingVertical: 12 }}>
      <ThemedText accessibilityRole="header" accessibilityLiveRegion="polite" style={lendingStyles.title}>A similar loan is already recorded</ThemedText>
      <ThemedText themeColor="textSecondary">Found {duplicates.total} loan{duplicates.total === 1 ? '' : 's'} with matching person details, amount, currency and direction within one day. Review before adding another.</ThemedText>
      {duplicates.total > 5 ? <ThemedText themeColor="textSecondary">Showing the five most recent matches.</ThemedText> : null}
      {duplicates.loans.map(loan => <NavigationRow key={loan.id} label={loan.description || 'View existing loan'} detail={`${loan.occurred_on} · ${loan.currency_code}`} onPress={() => router.push({ pathname: '/(app)/lending/loan', params: { id: loan.id } })} />)}
      <LendingAction label="This is a separate loan — save it" disabled={save.isPending} onPress={() => save.mutate(duplicates.signature)} />
      <LendingAction label="Review my entry" disabled={save.isPending} onPress={() => setDuplicates(null)} />
    </View> : null}
    {save.error ? <ThemedText themeColor="danger">{errorMessage(save.error)}</ThemedText> : null}
    {draftStatus ? <ThemedText themeColor="textSecondary">{draftStatus}</ThemedText> : null}
    {!duplicates ? <PrimaryButton label={willShare ? 'Save & share loan' : 'Save private loan'} loading={save.isPending} disabled={(!newPerson && !person.data) || (willShare && !sharingEnabled)} onPress={() => save.mutate(undefined)} /> : null}
  </LendingScreen>;
}

function PersonPicker({ onChoose }: { onChoose: (person: string | NewPerson) => void }) {
  const token = useAuthStore(s => s.token)!;
  const sharingEnabled = useAuthStore(s => s.user?.features?.lending_sharing) !== false;
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [adding, setAdding] = useState(false);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  useEffect(() => { const timer = setTimeout(() => setQ(search.trim()), 280); return () => clearTimeout(timer); }, [search]);
  const people = useInfiniteQuery({ queryKey: ['lending', 'choose-person', q], initialPageParam: 1, enabled: !adding,
    queryFn: ({ pageParam, signal }) => lendingPage<LendingContact>(token, `contacts?status=all&q=${encodeURIComponent(q)}`, pageParam, signal), getNextPageParam: lendingNextPage });
  return adding ? <>
    <ThemedText style={lendingStyles.title}>New person</ThemedText>
    <FormField label="Person’s name" value={name} onChangeText={setName} autoCapitalize="words" maxLength={120} />
    <FormField label="Email (optional)" value={email} onChangeText={setEmail} keyboardType="email-address" maxLength={254} />
    <ThemedText themeColor="textSecondary">They don’t need an account. Adding an email keeps this record private.</ThemedText>
    <PrimaryButton label="Continue with this person" disabled={!name.trim()} onPress={() => onChoose({ name: name.trim(), email: email.trim() || null })} />
    <LendingAction label="Choose an existing person" onPress={() => setAdding(false)} />
  </> : <>
    <ThemedText style={lendingStyles.title}>Who is this loan with?</ThemedText>
    <SearchField label="Find a person" placeholder="Search people" value={search} onChange={setSearch} />
    <NavigationRow label="Add a new person" detail="A name is all you need" onPress={() => { setName(search.trim()); setAdding(true); }} />
    {people.isLoading ? <ThemedText>Loading people…</ThemedText> : null}
    {people.error ? <QueryErrorCard error={people.error} onRetry={() => void people.refetch()} /> : null}
    {people.data?.pages.flatMap(p => p.data).filter(person => person.is_owner || (sharingEnabled && person.is_connected)).map(person => <NavigationRow key={person.id} label={person.name} detail={person.is_owner ? 'Personal tracker' : 'Shared connection'} onPress={() => onChoose(String(person.id))} />)}
    {people.data?.pages.every(p => !p.data.length) ? <ThemedText themeColor="textSecondary">{q ? 'No matching people. Add a new person above.' : 'Your people will appear here after your first loan.'}</ThemedText> : null}
    {people.hasNextPage ? <LendingAction label="Load more people" disabled={people.isFetchingNextPage} onPress={() => void people.fetchNextPage()} /> : null}
  </>;
}
