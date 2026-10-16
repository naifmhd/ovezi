import { useMutation, useQuery } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { PrimaryButton } from '@/components/auth/primary-button';
import { NativeDateField } from '@/components/native-date-field';
import { LendingScreen } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { SegmentedControl } from '@/components/ui/screen-controls';
import { errorMessage } from '@/lib/api-client';
import { LendingContact, lendingGet } from '@/lib/lending-api';
import { shareLendingStatement } from '@/lib/share-text-file';
import { useAuthStore } from '@/stores/auth-store';

export default function PersonStatement() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const token = useAuthStore(s => s.token)!;
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [format, setFormat] = useState<'pdf' | 'csv'>('pdf');
  const person = useQuery({ queryKey: ['lending', 'contact', id], queryFn: ({ signal }) => lendingGet<LendingContact>(token, `contacts/${id}`, signal) });
  const download = useMutation({ mutationFn: async () => {
    if (from && to && from > to) throw new Error('The end date must be on or after the start date.');
    await shareLendingStatement(token, id, person.data!.name, format, from, to);
  } });
  return <LendingScreen title="Person statement">
    <ThemedText>{person.data?.name ?? 'Loading person…'}</ThemedText>
    <ThemedText themeColor="textSecondary">Export loans and repayments, with each currency kept separate. Private notes and email addresses are excluded.</ThemedText>
    <NativeDateField label="Activity from" value={from} onChange={value => { setFrom(value); download.reset(); }} optional emptyLabel="All dates" />
    <NativeDateField label="Activity through" value={to} onChange={value => { setTo(value); download.reset(); }} optional emptyLabel="All dates" />
    <ThemedText themeColor="textSecondary">Balances always show the current total across all dates. The dates above filter only the activity history.</ThemedText>
    <SegmentedControl label="Statement format" value={format} onChange={value => { setFormat(value); download.reset(); }} options={[{ value: 'pdf', label: 'PDF document' }, { value: 'csv', label: 'CSV spreadsheet' }]} />
    {person.error ? <QueryErrorCard error={person.error} onRetry={() => void person.refetch()} /> : null}
    {download.error ? <ThemedText themeColor="danger" accessibilityLiveRegion="polite">{errorMessage(download.error)}</ThemedText> : null}
    {download.isSuccess ? <ThemedText themeColor="positive" accessibilityLiveRegion="polite">Statement prepared. You can export it again anytime.</ThemedText> : null}
    <PrimaryButton label={download.isError ? 'Retry export' : `Export ${format.toUpperCase()}`} loadingLabel="Preparing statement…" loading={download.isPending} disabled={!person.data} onPress={() => download.mutate()} />
  </LendingScreen>;
}
