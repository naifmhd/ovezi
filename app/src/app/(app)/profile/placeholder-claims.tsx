import { HeaderAction } from '@/components/ui/header-action';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { PrimaryButton } from '@/components/auth/primary-button';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { claimPlaceholder, fetchPlaceholderClaims } from '@/lib/placeholder-claims-api';
import { useAuthStore } from '@/stores/auth-store';
import type { PlaceholderClaim } from '@/types/api';

export default function PlaceholderClaimsScreen() {
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const queryClient = useQueryClient();
  const [selected, setSelected] = useState<PlaceholderClaim | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const claimsQuery = useQuery({
    queryKey: ['placeholder-claims'],
    queryFn: () => fetchPlaceholderClaims(token),
    enabled: Boolean(user.email_verified_at),
  });
  const mutation = useMutation({
    mutationFn: (placeholderId: number) => claimPlaceholder(token, placeholderId),
    onSuccess: async (claim) => {
      setSelected(null);
      setSuccessMessage(`${claim.name}'s history is now connected to your account.`);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['placeholder-claims'] }),
        queryClient.invalidateQueries({ queryKey: ['placeholders'] }),
        queryClient.invalidateQueries({ queryKey: ['groups'] }),
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
        queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
      ]);
    },
  });
  const matches = claimsQuery.data ?? [];

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </HeaderAction>
          <ThemedText style={styles.headerTitle}>Claim previous history</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={(
            <RefreshControl
              enabled={Boolean(user.email_verified_at)}
              refreshing={claimsQuery.isRefetching}
              onRefresh={() => void claimsQuery.refetch()}
            />
          )}
          showsVerticalScrollIndicator={false}>
          <ThemedView type="backgroundSelected" style={styles.infoCard}>
            <ThemedText style={styles.infoTitle}>Matched securely by verified email</ThemedText>
            <ThemedText style={styles.copy} themeColor="textSecondary">
              Ovezi only shows placeholders created with {user.email}. Each history is claimed separately so you stay in control.
            </ThemedText>
          </ThemedView>

          {!user.email_verified_at ? (
            <ThemedView type="backgroundElement" style={styles.emptyCard}>
              <ThemedText style={styles.cardTitle}>Verify your email first</ThemedText>
              <ThemedText style={styles.copy} themeColor="textSecondary">
                Email verification is required before Ovezi can reveal or connect matching history.
              </ThemedText>
            </ThemedView>
          ) : null}

          {successMessage ? (
            <ThemedText style={styles.success} themeColor="primary">{successMessage}</ThemedText>
          ) : null}
          {claimsQuery.error ? (
            <QueryErrorCard
              error={claimsQuery.error}
              onRetry={() => void claimsQuery.refetch()}
              retrying={claimsQuery.isRefetching}
            />
          ) : null}
          {mutation.error ? <ThemedText themeColor="danger">{errorMessage(mutation.error)}</ThemedText> : null}
          {claimsQuery.isLoading ? (
            <ThemedText style={styles.centered} themeColor="textSecondary">
              Looking for matching history…
            </ThemedText>
          ) : null}

          {matches.map((match) => (
            <ThemedView key={match.id} type="backgroundElement" style={styles.matchCard}>
              <View style={styles.matchHeading}>
                <View style={styles.matchCopy}>
                  <ThemedText style={styles.cardTitle}>{match.name}</ThemedText>
                  <ThemedText style={styles.meta} themeColor="textSecondary">
                    Added by {match.created_by?.name ?? 'a deleted account'}
                  </ThemedText>
                </View>
                <ThemedView type="backgroundSelected" style={styles.countPill}>
                  <ThemedText style={styles.countText} themeColor="primary">
                    {match.expense_count} expense{match.expense_count === 1 ? '' : 's'}
                  </ThemedText>
                </ThemedView>
              </View>
              {match.groups.length > 0 ? (
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Groups: {match.groups.map((group) => group.name ?? 'Deleted group').join(', ')}
                </ThemedText>
              ) : (
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  1-on-1 history outside a group
                </ThemedText>
              )}
              {selected?.id === match.id ? (
                <ThemedView type="backgroundSelected" style={styles.confirmCard}>
                  <ThemedText style={styles.confirmTitle}>Connect this history?</ThemedText>
                  <ThemedText style={styles.copy} themeColor="textSecondary">
                    Old expenses remain unchanged for audit history, while balances and access move under your account.
                  </ThemedText>
                  <View style={styles.actions}>
                    <Pressable onPress={() => setSelected(null)} style={styles.secondaryAction}>
                      <ThemedText style={styles.actionText} themeColor="textSecondary">Cancel</ThemedText>
                    </Pressable>
                    <View style={styles.primaryAction}>
                      <PrimaryButton
                        label="Confirm claim"
                        loading={mutation.isPending}
                        onPress={() => mutation.mutate(match.id)}
                      />
                    </View>
                  </View>
                </ThemedView>
              ) : (
                <Pressable onPress={() => setSelected(match)} style={styles.claimAction}>
                  <ThemedText style={styles.actionText} themeColor="primary">Review and claim</ThemedText>
                </Pressable>
              )}
            </ThemedView>
          ))}

          {user.email_verified_at && !claimsQuery.isLoading && !claimsQuery.error && matches.length === 0 ? (
            <ThemedView type="backgroundElement" style={styles.emptyCard}>
              <ThemedText style={styles.cardTitle}>No matching history</ThemedText>
              <ThemedText style={styles.copy} themeColor="textSecondary">
                Nothing unclaimed currently matches your verified email. Phone-based matching will be added with phone verification.
              </ThemedText>
            </ThemedView>
          ) : null}
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: { minHeight: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '600' },
  headerTitle: { fontSize: 17, fontWeight: '600' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14, maxWidth: 680, width: '100%', alignSelf: 'center' },
  infoCard: { borderRadius: 20, padding: 17, gap: 5 },
  infoTitle: { fontSize: 15, fontWeight: '600' },
  copy: { fontSize: 13, lineHeight: 19 },
  centered: { textAlign: 'center', paddingVertical: 40 },
  success: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  matchCard: { borderRadius: 22, padding: 17, gap: 11 },
  matchHeading: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  matchCopy: { flex: 1, gap: 2 },
  cardTitle: { fontSize: 16, lineHeight: 22, fontWeight: '600' },
  meta: { fontSize: 12, lineHeight: 17 },
  countPill: { borderRadius: 12, paddingHorizontal: 10, paddingVertical: 7 },
  countText: { fontSize: 11, fontWeight: '600' },
  claimAction: { minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  actionText: { fontSize: 14, fontWeight: '600' },
  confirmCard: { borderRadius: 16, padding: 14, gap: 6 },
  confirmTitle: { fontSize: 14, fontWeight: '600' },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 5 },
  secondaryAction: { minHeight: 48, justifyContent: 'center', paddingHorizontal: 12 },
  primaryAction: { flex: 1 },
  emptyCard: { borderRadius: 22, padding: 20, gap: 6 },
});
