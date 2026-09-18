import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { StyleSheet } from 'react-native';

import { PrimaryButton } from '@/components/auth/primary-button';
import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { errorMessage } from '@/lib/api-client';
import { acceptGroupInvite } from '@/lib/group-invites-api';
import { useAuthStore } from '@/stores/auth-store';

export default function AcceptGroupInviteScreen() {
  const params = useLocalSearchParams<{ token?: string | string[] }>();
  const inviteToken = Array.isArray(params.token) ? params.token[0] : params.token;
  const token = useAuthStore((state) => state.token);
  const queryClient = useQueryClient();
  const redirect = `/group-invites/accept?token=${encodeURIComponent(inviteToken ?? '')}`;
  const mutation = useMutation({
    mutationFn: () => acceptGroupInvite(token!, inviteToken!),
    onSuccess: async (membership) => {
      await queryClient.invalidateQueries({ queryKey: ['groups'] });
      router.replace({ pathname: '/(app)/groups/[id]', params: { id: membership.group_id } });
    },
  });

  return (
    <AppScreen title="Group invitation">
      <ThemedView type="backgroundElement" style={styles.card}>
        <ThemedText style={styles.title}>Join this Ovezi group?</ThemedText>
        <ThemedText style={styles.copy} themeColor="textSecondary">
          Once you join, you can view the group history, shared expenses, and your balance.
        </ThemedText>
        {!inviteToken ? (
          <ThemedText themeColor="danger">This invitation link is incomplete.</ThemedText>
        ) : null}
        {mutation.error ? <ThemedText themeColor="danger">{errorMessage(mutation.error)}</ThemedText> : null}
        {token ? (
          <PrimaryButton
            disabled={!inviteToken}
            label="Join group"
            loading={mutation.isPending}
            onPress={() => mutation.mutate()}
          />
        ) : (
          <>
            <PrimaryButton
              disabled={!inviteToken}
              label="Sign in to join"
              onPress={() => router.push({ pathname: '/(auth)/sign-in', params: { redirect } })}
            />
            <ThemedText
              onPress={() => router.push({ pathname: '/(auth)/sign-up', params: { redirect } })}
              style={styles.createAccount}
              themeColor="primary">
              Create an account
            </ThemedText>
          </>
        )}
        <ThemedText onPress={() => router.replace('/')} style={styles.notNow} themeColor="textSecondary">
          Not now
        </ThemedText>
      </ThemedView>
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  card: { marginTop: 36, borderRadius: 24, padding: 22, gap: 14 },
  title: { fontSize: 22, lineHeight: 30, fontWeight: '800' },
  copy: { fontSize: 14, lineHeight: 21 },
  createAccount: { textAlign: 'center', fontSize: 14, fontWeight: '800', paddingVertical: 4 },
  notNow: { textAlign: 'center', fontSize: 14, fontWeight: '700', paddingVertical: 4 },
});
