import { useMutation, useQuery } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction, LendingScreen, useRefreshLending } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { errorMessage } from '@/lib/api-client';
import { LendingContact, lendingGet, lendingWrite, updateLendingContact } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function PersonForm() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const token = useAuthStore(s => s.token)!;
  const refresh = useRefreshLending();
  const [editedName, setName] = useState<string | null>(null);
  const [editedEmail, setEmail] = useState<string | null>(null);
  const [confirm, setConfirm] = useState(false);
  const query = useQuery({ queryKey: ['lending', 'contact', id], queryFn: () => lendingGet<LendingContact>(token, `contacts/${id}`), enabled: !!id });
  const name = editedName ?? query.data?.name ?? '';
  const email = editedEmail ?? query.data?.email ?? '';
  const save = useMutation({ mutationFn: async () => {
    if (!name.trim()) throw new Error('Enter the person’s name.');
    if (id) { await updateLendingContact(token, Number(id), { name: name.trim(), ...(!query.data?.is_connected && !query.data?.disconnected_at ? { email: email.trim() || null } : {}) }); return Number(id); }
    return (await lendingWrite<{ data: LendingContact }>(token, 'contacts', { name: name.trim(), email: email.trim() || null })).data.id;
  }, onSuccess: async personId => { await refresh(); router.replace({ pathname: '/(app)/lending/[id]', params: { id: personId } }); } });
  const disconnect = useMutation({ mutationFn: () => lendingWrite(token, `contacts/${id}/disconnect`), onSuccess: async () => { await refresh(); router.back(); } });
  return <LendingScreen title={id ? 'Person settings' : 'Add a person'}>
    {query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} /> : null}
    {(!id || query.data?.is_owner) ? <>
      <FormField label="Name" value={name} onChangeText={setName} autoCapitalize="words" maxLength={120} />
      <FormField label="Email (optional)" value={email} onChangeText={setEmail} keyboardType="email-address" autoComplete="email" maxLength={254} editable={!query.data?.is_connected && !query.data?.disconnected_at} />
      <ThemedText themeColor="textSecondary">An email helps you connect later. Loans stay private until you choose to share them. Changing an unconnected email cancels its pending invitation.</ThemedText>
      <PrimaryButton label={id ? 'Save person' : 'Add person'} loading={save.isPending} disabled={!!id && !query.data} onPress={() => save.mutate()} />
    </> : null}
    {query.data?.is_connected ? <>
      <ThemedText>Disconnecting stops future sharing. Both people keep access to existing shared loans and repayments.</ThemedText>
      {confirm ? <><PrimaryButton label="Confirm disconnect" loading={disconnect.isPending} onPress={() => disconnect.mutate()} /><LendingAction label="Cancel" onPress={() => setConfirm(false)} /></> : <LendingAction label="Disconnect" danger onPress={() => setConfirm(true)} />}
    </> : null}
    {save.error || disconnect.error ? <ThemedText themeColor="danger">{errorMessage(save.error ?? disconnect.error)}</ThemedText> : null}
  </LendingScreen>;
}
