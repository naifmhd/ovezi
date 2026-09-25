import { HeaderAction } from '@/components/ui/header-action';
import { ActionSheet } from '@/components/ui/action-sheet';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { fetchOverallBalances } from '@/lib/balances-api';
import { formatMoney } from '@/lib/format';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { acceptFriend, fetchFriends, removeFriend, requestFriend } from '@/lib/friends-api';
import { useAuthStore } from '@/stores/auth-store';
import type { Friendship } from '@/types/api';

export default function FriendsScreen() {
  const token = useAuthStore((state) => state.token)!;
  const queryClient = useQueryClient();
  const [showAddFriend, setShowAddFriend] = useState(false);
  const balancesQuery = useQuery({ queryKey: ['dashboard-balances'], queryFn: () => fetchOverallBalances(token) });
  const [email, setEmail] = useState('');
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const friendsQuery = useQuery({
    queryKey: ['friends'],
    queryFn: () => fetchFriends(token),
  });
  const requestMutation = useMutation({
    mutationFn: () => requestFriend(token, email.trim().toLowerCase()),
    onSuccess: async (friendship) => {
      setEmail('');
      setSuccessMessage(`Friend request sent to ${friendship.friend.name}.`);
      await queryClient.invalidateQueries({ queryKey: ['friends'] });
    },
  });
  const acceptMutation = useMutation({
    mutationFn: (friendshipId: number) => acceptFriend(token, friendshipId),
    onSuccess: async (friendship) => {
      setSuccessMessage(`${friendship.friend.name} is now your friend.`);
      await queryClient.invalidateQueries({ queryKey: ['friends'] });
    },
  });
  const removeMutation = useMutation({
    mutationFn: (friendshipId: number) => removeFriend(token, friendshipId),
    onSuccess: async () => {
      setSuccessMessage(null);
      await queryClient.invalidateQueries({ queryKey: ['friends'] });
    },
  });
  const friendships = friendsQuery.data ?? [];
  const incoming = friendships.filter((item) => item.status === 'pending' && item.direction === 'incoming');
  const outgoing = friendships.filter((item) => item.status === 'pending' && item.direction === 'outgoing');
  const accepted = friendships.filter((item) => item.status === 'accepted');
  const mutationError = requestMutation.error ?? acceptMutation.error ?? removeMutation.error;

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </HeaderAction>
          <ThemedText style={styles.headerTitle}>Friends</ThemedText>
          <AnimatedPressable style={{ minHeight: 48, justifyContent: 'center' }} onPress={() => setShowAddFriend(!showAddFriend)} accessibilityState={{ expanded: showAddFriend }}><ThemedText themeColor="interactive">+ Friend</ThemedText></AnimatedPressable>
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            refreshControl={(
              <RefreshControl
                refreshing={friendsQuery.isRefetching}
                onRefresh={() => void friendsQuery.refetch()}
              />
            )}
            showsVerticalScrollIndicator={false}>

            {successMessage ? <ThemedText style={styles.success} themeColor="primary">{successMessage}</ThemedText> : null}
            {friendsQuery.error ? (
              <QueryErrorCard
                error={friendsQuery.error}
                onRetry={() => void friendsQuery.refetch()}
                retrying={friendsQuery.isRefetching}
              />
            ) : null}
            {mutationError ? <ThemedText themeColor="danger">{errorMessage(mutationError)}</ThemedText> : null}
            {friendsQuery.isLoading ? (
              <ThemedText style={styles.centered} themeColor="textSecondary">Loading friends…</ThemedText>
            ) : null}

            {incoming.length > 0 ? <SectionTitle title="Requests to you" /> : null}
            {incoming.map((friendship) => (
              <FriendRow
                actionLabel="Accept"
                friendship={friendship}
                key={friendship.id}
                loading={acceptMutation.isPending}
                onAction={() => acceptMutation.mutate(friendship.id)}
                onRemove={() => removeMutation.mutate(friendship.id)}
                removeLabel="Decline"
              />
            ))}

            {accepted.length > 0 ? <SectionTitle title="Your friends" /> : null}
            {accepted.map((friendship) => (
              <FriendRow
                friendship={friendship}
                key={friendship.id}
                onRemove={() => removeMutation.mutate(friendship.id)}
                removeLabel="Remove"
                actionLabel="Add expense"
                onAction={() => router.push({ pathname: '/(app)/expenses/create', params: { friendId: friendship.friend.id } })}
                balances={balancesQuery.isError ? [] : (balancesQuery.data?.direct ?? []).filter((balance) => balance.participant.key === `user:${friendship.friend.id}`).map((balance) => ({ currency: balance.currency_code, minor: balance.balance_minor }))}
              />
            ))}

            {outgoing.length > 0 ? <SectionTitle title="Sent requests" /> : null}
            {outgoing.map((friendship) => (
              <FriendRow
                friendship={friendship}
                key={friendship.id}
                onRemove={() => removeMutation.mutate(friendship.id)}
                removeLabel="Cancel"
              />
            ))}

            {showAddFriend || (!friendsQuery.isPending && friendships.length === 0) ? <>
            <ThemedView type="backgroundElement" style={styles.addCard}>
              <ThemedText style={styles.cardTitle}>Add a friend</ThemedText>
              <ThemedText style={styles.copy} themeColor="textSecondary">
                Enter the exact email address connected to their Ovezi account.
              </ThemedText>
              <FormField
                autoCapitalize="none"
                autoComplete="email"
                keyboardType="email-address"
                label="Email"
                onChangeText={setEmail}
                placeholder="friend@example.com"
                value={email}
              />
              <PrimaryButton
                disabled={!email.trim()}
                label="Send friend request"
                loading={requestMutation.isPending}
                onPress={() => requestMutation.mutate()}
              />
            </ThemedView>
            </> : null}
            {!friendsQuery.isLoading && !friendsQuery.error && friendships.length === 0 ? (
              <ThemedView type="backgroundSelected" style={styles.emptyCard}>
                <ThemedText style={styles.cardTitle}>No friends yet</ThemedText>
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Add someone by email, then use their account directly in 1-on-1 expenses.
                </ThemedText>
              </ThemedView>
            ) : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

function FriendRow({
  actionLabel,
  friendship,
  loading = false,
  onAction,
  onRemove,
  removeLabel,
  balances = [],
}: {
  actionLabel?: string;
  friendship: Friendship;
  loading?: boolean;
  onAction?: () => void;
  onRemove: () => void;
  removeLabel: string;
  balances?: { currency: string; minor: number }[];
}) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [confirmRemove, setConfirmRemove] = useState(false);
  return (
    <ThemedView type="backgroundElement" style={styles.friendRow}>
      <ActionSheet title={confirmRemove ? `Remove ${friendship.friend.name}?` : friendship.friend.name} visible={menuOpen || confirmRemove} onClose={() => { setMenuOpen(false); setConfirmRemove(false); }} actions={confirmRemove ? [{ label: 'Remove friend', destructive: true, onPress: onRemove }] : [{ label: 'Remove friend…', destructive: true, onPress: () => setConfirmRemove(true) }]} />
      <ThemedView type="backgroundSelected" style={styles.avatar}>
        <ThemedText style={styles.initial} themeColor="primary">
          {friendship.friend.name.slice(0, 1).toUpperCase()}
        </ThemedText>
      </ThemedView>
      <View style={styles.friendCopy}>
        <ThemedText style={styles.friendName}>{friendship.friend.name}</ThemedText>
        <ThemedText style={styles.email} themeColor="textSecondary">{friendship.friend.email}</ThemedText>
        {balances.map((balance) => <AnimatedPressable key={balance.currency} style={{ minHeight: 48, justifyContent: 'center' }} onPress={() => router.push({ pathname: '/(app)/settlements/direct', params: { participant: `user:${friendship.friend.id}`, currency: balance.currency } })}>
          <ThemedText style={styles.email} themeColor={balance.minor >= 0 ? 'positive' : 'danger'}>{balance.minor > 0 ? 'You are owed' : balance.minor < 0 ? 'You owe' : 'Settled'} · {formatMoney(Math.abs(balance.minor), balance.currency)}</ThemedText>
        </AnimatedPressable>)}
      </View>
      <View style={styles.rowActions}>
        {onAction && actionLabel ? (
          <Pressable disabled={loading} onPress={onAction} style={styles.rowButton}>
            <ThemedText style={styles.rowAction} themeColor="primary">
              {loading ? 'Saving…' : actionLabel}
            </ThemedText>
          </Pressable>
        ) : null}
        <Pressable accessibilityRole="button" accessibilityLabel={removeLabel === 'Remove' ? `More options for ${friendship.friend.name}` : removeLabel} onPress={removeLabel === 'Remove' ? () => setMenuOpen(true) : onRemove} style={styles.rowButton}>
          <ThemedText style={styles.rowAction} themeColor={removeLabel === 'Remove' ? 'textSecondary' : 'danger'}>{removeLabel === 'Remove' ? 'More' : removeLabel}</ThemedText>
        </Pressable>
      </View>
    </ThemedView>
  );
}

function SectionTitle({ title }: { title: string }) {
  return <ThemedText style={styles.sectionTitle}>{title}</ThemedText>;
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  header: { minHeight: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '600' },
  headerTitle: { fontSize: 17, fontWeight: '600' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 12, maxWidth: 680, width: '100%', alignSelf: 'center' },
  addCard: { borderRadius: 22, padding: 18, gap: 12 },
  cardTitle: { fontSize: 17, lineHeight: 23, fontWeight: '600' },
  copy: { fontSize: 13, lineHeight: 19 },
  success: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  centered: { textAlign: 'center', paddingVertical: 30 },
  sectionTitle: { fontSize: 17, lineHeight: 23, fontWeight: '600', marginTop: 8 },
  friendRow: { minHeight: 76, borderRadius: 20, padding: 13, flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 11 },
  avatar: { width: 44, height: 44, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  initial: { fontSize: 18, fontWeight: '600' },
  friendCopy: { flex: 1 },
  friendName: { fontSize: 14, fontWeight: '600' },
  email: { fontSize: 12 },
  rowActions: { flexBasis: '100%', flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: 20 },
  rowButton: { minHeight: 48, justifyContent: 'center', paddingHorizontal: 5 },
  rowAction: { fontSize: 12, fontWeight: '600' },
  emptyCard: { borderRadius: 20, padding: 18, gap: 5 },
});
