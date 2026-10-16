import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { PrimaryButton } from '@/components/auth/primary-button';
import { LendingAction } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { errorMessage } from '@/lib/api-client';
import { deleteGroup, hideGroup } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export function GroupCleanupControls({ groupId, isOwner }: { groupId: number; isOwner: boolean }) {
  const token = useAuthStore(s => s.token)!;
  const client = useQueryClient();
  const [confirm, setConfirm] = useState(false);
  const mutation = useMutation({ mutationFn: (action: 'hide' | 'delete') => action === 'hide' ? hideGroup(token, groupId, true) : deleteGroup(token, groupId), onSuccess: async () => {
    await client.invalidateQueries(); router.replace('/(app)/(tabs)/groups');
  } });
  return <View style={{ gap: 8, marginTop: 16 }}>
    <LendingAction label="Hide group for me" disabled={mutation.isPending} onPress={() => mutation.mutate('hide')} />
    <ThemedText themeColor="textSecondary">Hidden groups remain available in the Hidden filter. Other members are unaffected.</ThemedText>
    {isOwner ? <>
      <LendingAction label="Delete group" danger disabled={mutation.isPending} onPress={() => setConfirm(true)} />
      {confirm ? <><ThemedText>Delete this group for everyone? Every member must be settled and recurring expenses must be cancelled. Its history is retained, and you can restore it from Deleted groups.</ThemedText><PrimaryButton label="Confirm group deletion" loading={mutation.isPending} onPress={() => mutation.mutate('delete')} /><LendingAction label="Cancel" onPress={() => setConfirm(false)} /></> : null}
    </> : null}
    {mutation.error ? <ThemedText themeColor="danger">{errorMessage(mutation.error)}</ThemedText> : null}
  </View>;
}
