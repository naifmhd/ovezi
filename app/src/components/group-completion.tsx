import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { View } from 'react-native';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { errorMessage } from '@/lib/api-client';
import { isGroupSettled } from '@/lib/group-completion';
import { fetchGroupBalances, setGroupArchived } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export function GroupCompletion({ groupId, isOwner, archived }: { groupId: number; isOwner: boolean; archived: boolean }) {
  const token = useAuthStore(s => s.token)!;
  const client = useQueryClient();
  const [confirm, setConfirm] = useState(false);
  const archive = useMutation({
    mutationFn: async () => {
      if (!isGroupSettled(await fetchGroupBalances(token, groupId))) {
        void client.invalidateQueries({ queryKey: ['group-balances', groupId] });
        throw new Error('The group balance has changed. Review the latest balances before archiving.');
      }
      return setGroupArchived(token, groupId, true);
    },
    onSuccess: async () => {
      setConfirm(false);
      await Promise.all([
        client.invalidateQueries({ queryKey: ['group', groupId] }),
        client.invalidateQueries({ queryKey: ['groups'] }),
        client.invalidateQueries({ queryKey: ['activity'] }),
        client.invalidateQueries({ queryKey: ['dashboard-balances'] }),
      ]);
    },
  });
  return <View style={{ gap: 8 }}>
    <ThemedText accessibilityRole="header" style={{ fontSize: 20, lineHeight: 28, fontWeight: '600' }}>{archived ? 'Group archived' : 'Everyone is settled'}</ThemedText>
    <ThemedText themeColor="textSecondary">{archived ? 'Your expenses and payment history are still available. Find this group under Archived in Groups.' : 'Every member’s balance is zero. Your expenses and payment history stay available.'}</ThemedText>
    {isOwner && !archived ? confirm ? <>
      <ThemedText>Archive this group for everyone? You can reopen it from Group settings when you need it again.</ThemedText>
      <PrimaryButton label="Confirm archive" loading={archive.isPending} onPress={() => archive.mutate()} />
      <LendingAction label="Keep group active" disabled={archive.isPending} onPress={() => setConfirm(false)} />
    </> : <LendingAction variant="secondary" label="Archive settled group" onPress={() => setConfirm(true)} /> : null}
    {archive.error ? <ThemedText themeColor="danger">{errorMessage(archive.error)}</ThemedText> : null}
  </View>;
}
