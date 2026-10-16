import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Platform, View } from 'react-native';
import { PrimaryButton } from '@/components/auth/primary-button';
import { NativeDateField } from '@/components/native-date-field';
import { LendingAction } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { apiRequest, errorMessage } from '@/lib/api-client';
import { lendingGet, LendingLoan } from '@/lib/lending-api';
import { syncPushToken } from '@/lib/push-notifications';
import { useAuthStore } from '@/stores/auth-store';

type Reminder = { remind_at: string; sent_at: string | null } | null;
function tomorrow() {
  const date = new Date(); date.setDate(date.getDate() + 1);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
export function LendingReminder({ loan }: { loan: LendingLoan }) {
  const token = useAuthStore(s => s.token)!;
  const client = useQueryClient();
  const [editing, setEditing] = useState(false);
  const [date, setDate] = useState(tomorrow);
  const key = ['lending', 'reminder', loan.id];
  const reminder = useQuery({ queryKey: key, queryFn: ({ signal }) => lendingGet<Reminder>(token, `loans/${loan.id}/reminder`, signal), enabled: !loan.voided_at && loan.outstanding_minor > 0 });
  const save = useMutation({ mutationFn: async (cancel: boolean) => {
    if (!cancel && Platform.OS !== 'web' && !(await syncPushToken(token, true))) throw new Error('Allow notifications in your phone settings to receive this reminder.');
    const when = new Date(`${date}T09:00:00`);
    if (!cancel && (Number.isNaN(when.getTime()) || when.getTime() <= Date.now())) throw new Error('Choose a future date. Reminders arrive at 9:00 AM.');
    return apiRequest<{ data: Reminder }>(`/lending/loans/${loan.id}/reminder`, { token, method: cancel ? 'DELETE' : 'PUT', ...(cancel ? {} : { body: { remind_at: when.toISOString() } }) });
  }, onSuccess: result => { client.setQueryData(key, result.data); setEditing(false); } });
  if (loan.voided_at || loan.outstanding_minor === 0) return null;
  return <View style={{ gap: 10, marginVertical: 12 }}>
    {editing || reminder.data ? <><ThemedText style={{ fontWeight: '600' }}>Remind me</ThemedText>
    <ThemedText themeColor="textSecondary">A private notification to your phone. No message is sent to the other person. Settled or voided loans are skipped.</ThemedText></> : null}
    {reminder.data ? <ThemedText>{reminder.data.sent_at ? 'Reminder processed' : 'Scheduled'} · {new Date(reminder.data.remind_at).toLocaleString()}</ThemedText> : null}
    {reminder.isLoading ? <ThemedText>Checking reminder…</ThemedText> : null}
    {reminder.error ? <><ThemedText themeColor="danger">{errorMessage(reminder.error)}</ThemedText><LendingAction label="Retry reminder" onPress={() => void reminder.refetch()} /></> : null}
    {editing ? <>
      <NativeDateField label="Remind me on" value={date} onChange={setDate} minimumDate={new Date()} />
      <ThemedText themeColor="textSecondary">At 9:00 AM in this device’s time zone ({Intl.DateTimeFormat().resolvedOptions().timeZone}).</ThemedText>
      <PrimaryButton label={save.isError ? 'Retry saving reminder' : 'Save reminder'} loadingLabel="Saving reminder…" loading={save.isPending} onPress={() => save.mutate(false)} />
      <LendingAction label="Cancel" disabled={save.isPending} onPress={() => { setEditing(false); save.reset(); }} />
    </> : !reminder.isLoading && !reminder.error ? <LendingAction label={reminder.data ? 'Change reminder' : 'Set a reminder'} disabled={save.isPending} onPress={() => { save.reset(); setEditing(true); }} /> : null}
    {reminder.data && !editing ? <LendingAction label={save.isPending ? 'Removing…' : 'Remove reminder'} disabled={save.isPending} onPress={() => save.mutate(true)} /> : null}
    {save.isSuccess ? <ThemedText themeColor="positive" accessibilityLiveRegion="polite">{save.data.data ? 'Reminder saved' : 'Reminder removed'}</ThemedText> : null}
    {save.error ? <><ThemedText themeColor="danger" accessibilityLiveRegion="polite">{errorMessage(save.error)}</ThemedText><LendingAction label="Notification settings" onPress={() => router.push('/(app)/profile/notifications')} /></> : null}
  </View>;
}
