import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { formatMoney } from '@/lib/format';
import {
  addGroupPlaceholder,
  fetchGroup,
  fetchGroupBalances,
  removeGroupMember,
  transferGroupOwnership,
} from '@/lib/groups-api';
import { createPlaceholder, fetchPlaceholders } from '@/lib/placeholders-api';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';

function firstParam(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

export default function GroupMembersScreen() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const groupId = Number(firstParam(params.id));
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const theme = useTheme();
  const queryClient = useQueryClient();
  const [showGuestForm, setShowGuestForm] = useState(false);
  const [guestName, setGuestName] = useState('');
  const [contactType, setContactType] = useState<'email' | 'phone'>('email');
  const [contactValue, setContactValue] = useState('');
  const [removeTarget, setRemoveTarget] = useState<number | null>(null);
  const [transferTarget, setTransferTarget] = useState<number | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const validGroupId = Number.isInteger(groupId) && groupId > 0;

  const groupQuery = useQuery({
    queryKey: ['group', groupId],
    queryFn: () => fetchGroup(token, groupId),
    enabled: validGroupId,
  });
  const balancesQuery = useQuery({
    queryKey: ['group-balances', groupId],
    queryFn: () => fetchGroupBalances(token, groupId),
    enabled: validGroupId,
  });
  const placeholdersQuery = useQuery({
    queryKey: ['placeholders'],
    queryFn: () => fetchPlaceholders(token),
  });
  const group = groupQuery.data;
  const balances = balancesQuery.data;
  const isOwner = group?.members?.some(
    (member) => member.user?.id === user.id && member.role === 'owner',
  ) ?? false;
  const memberPlaceholderIds = new Set(
    (group?.members ?? []).flatMap((member) => member.placeholder ? [member.placeholder.id] : []),
  );
  const availablePlaceholders = (placeholdersQuery.data?.data ?? []).filter(
    (placeholder) => !placeholder.is_claimed && !memberPlaceholderIds.has(placeholder.id),
  );
  const balanceByParticipant = new Map(
    (balances?.members ?? []).map((member) => [member.participant.key, member.balance_minor]),
  );

  async function refresh() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['group', groupId] }),
      queryClient.invalidateQueries({ queryKey: ['group-balances', groupId] }),
      queryClient.invalidateQueries({ queryKey: ['groups'] }),
      queryClient.invalidateQueries({ queryKey: ['activity'] }),
    ]);
  }

  const addMutation = useMutation({
    mutationFn: (placeholderId: number) => addGroupPlaceholder(token, groupId, placeholderId),
    onSuccess: async () => {
      await refresh();
      setFormError(null);
    },
  });
  const createMutation = useMutation({
    mutationFn: async () => {
      const placeholder = await createPlaceholder(token, {
        name: guestName.trim(),
        contactType,
        contactValue: contactValue.trim(),
      });
      await addGroupPlaceholder(token, groupId, placeholder.id);
    },
    onSuccess: async () => {
      await Promise.all([
        refresh(),
        queryClient.invalidateQueries({ queryKey: ['placeholders'] }),
      ]);
      setGuestName('');
      setContactValue('');
      setShowGuestForm(false);
      setFormError(null);
    },
  });
  const removeMutation = useMutation({
    mutationFn: (memberId: number) => removeGroupMember(token, groupId, memberId),
    onSuccess: async () => {
      await refresh();
      setRemoveTarget(null);
    },
  });
  const transferMutation = useMutation({
    mutationFn: (userId: number) => transferGroupOwnership(token, groupId, userId),
    onSuccess: async () => {
      await refresh();
      router.replace({ pathname: '/(app)/groups/[id]', params: { id: groupId } });
    },
  });

  function saveGuest() {
    setFormError(null);
    if (!guestName.trim()) return setFormError('Enter a name for this person.');
    if (!contactValue.trim()) {
      return setFormError(`Enter their ${contactType === 'email' ? 'email address' : 'phone number'}.`);
    }
    createMutation.mutate();
  }

  const visibleError = formError
    ?? (groupQuery.error ? errorMessage(groupQuery.error) : null)
    ?? (balancesQuery.error ? errorMessage(balancesQuery.error) : null)
    ?? (placeholdersQuery.error ? errorMessage(placeholdersQuery.error) : null)
    ?? (addMutation.error ? errorMessage(addMutation.error) : null)
    ?? (createMutation.error ? errorMessage(createMutation.error) : null)
    ?? (removeMutation.error ? errorMessage(removeMutation.error) : null)
    ?? (transferMutation.error ? errorMessage(transferMutation.error) : null);

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Manage members</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            {groupQuery.isLoading ? (
              <ThemedText style={styles.centered} themeColor="textSecondary">Loading members…</ThemedText>
            ) : null}
            {group && !isOwner ? (
              <ThemedText themeColor="danger">Only the group owner can manage members.</ThemedText>
            ) : null}
            {group && isOwner ? (
              <>
                <SectionTitle title={`Current members · ${group.members?.length ?? 0}`} />
                <ThemedView type="backgroundElement" style={styles.memberList}>
                  {(group.members ?? []).map((member, index) => {
                    const name = member.user?.name ?? member.placeholder?.name ?? 'Unknown member';
                    const participantKey = member.user
                      ? `user:${member.user.id}`
                      : `placeholder:${member.placeholder?.id}`;
                    const balance = balanceByParticipant.get(participantKey) ?? 0;
                    const canRemove = member.role !== 'owner' && balance === 0 && !group.is_archived;

                    return (
                      <View key={member.id}>
                        {index > 0 ? <View style={[styles.divider, { backgroundColor: theme.border }]} /> : null}
                        <View style={styles.memberRow}>
                          <ThemedView type="backgroundSelected" style={styles.avatar}>
                            <ThemedText style={styles.initial} themeColor="primary">{name.slice(0, 1)}</ThemedText>
                          </ThemedView>
                          <View style={styles.memberCopy}>
                            <ThemedText style={styles.memberName}>{name}</ThemedText>
                            <ThemedText style={styles.memberMeta} themeColor="textSecondary">
                              {member.role === 'owner'
                                ? 'Owner'
                                : balance === 0
                                  ? member.placeholder ? 'Placeholder · settled' : 'Member · settled'
                                  : `${balance > 0 ? 'Owed' : 'Owes'} ${formatMoney(Math.abs(balance), group.reporting_currency_code)}`}
                            </ThemedText>
                          </View>
                          {member.role !== 'owner' ? (
                            <View style={styles.memberActions}>
                              {member.user && !group.is_archived ? (
                                <Pressable onPress={() => setTransferTarget(member.user!.id)}>
                                  <ThemedText style={styles.smallAction} themeColor="primary">Make owner</ThemedText>
                                </Pressable>
                              ) : null}
                              <Pressable
                                disabled={!canRemove}
                                onPress={() => setRemoveTarget(member.id)}>
                                <ThemedText
                                  style={[styles.smallAction, !canRemove && styles.disabled]}
                                  themeColor={canRemove ? 'danger' : 'textSecondary'}>
                                  {balance === 0 ? 'Remove' : 'Settle first'}
                                </ThemedText>
                              </Pressable>
                            </View>
                          ) : null}
                        </View>
                        {removeTarget === member.id ? (
                          <ConfirmRow
                            confirmLabel="Remove member"
                            loading={removeMutation.isPending}
                            message={`Remove ${name}? Their history will be preserved.`}
                            onCancel={() => setRemoveTarget(null)}
                            onConfirm={() => removeMutation.mutate(member.id)}
                          />
                        ) : null}
                        {member.user && transferTarget === member.user.id ? (
                          <ConfirmRow
                            confirmLabel="Transfer ownership"
                            loading={transferMutation.isPending}
                            message={`Make ${name} the owner? You will become a regular member.`}
                            onCancel={() => setTransferTarget(null)}
                            onConfirm={() => transferMutation.mutate(member.user!.id)}
                          />
                        ) : null}
                      </View>
                    );
                  })}
                </ThemedView>

                {!group.is_archived ? (
                  <>
                    <SectionTitle title="Add a placeholder" />
                    <ThemedText style={styles.copy} themeColor="textSecondary">
                      Placeholders can join expenses without an account and claim their history later.
                    </ThemedText>
                    {availablePlaceholders.length > 0 ? (
                      <View style={styles.placeholderGrid}>
                        {availablePlaceholders.map((placeholder) => (
                          <Pressable
                            disabled={addMutation.isPending}
                            key={placeholder.id}
                            onPress={() => addMutation.mutate(placeholder.id)}
                            style={[styles.placeholderChip, { borderColor: theme.border, backgroundColor: theme.backgroundElement }]}>
                            <ThemedText style={styles.placeholderName}>+ {placeholder.name}</ThemedText>
                          </Pressable>
                        ))}
                      </View>
                    ) : null}
                    <Pressable onPress={() => setShowGuestForm((visible) => !visible)}>
                      <ThemedText style={styles.createGuestLink} themeColor="primary">
                        {showGuestForm ? 'Cancel new placeholder' : '+ Create new placeholder'}
                      </ThemedText>
                    </Pressable>
                    {showGuestForm ? (
                      <ThemedView type="backgroundElement" style={styles.guestCard}>
                        <FormField
                          autoCapitalize="words"
                          label="Name"
                          onChangeText={setGuestName}
                          placeholder="Sarah"
                          value={guestName}
                        />
                        <View style={styles.typeRow}>
                          {(['email', 'phone'] as const).map((type) => (
                            <Pressable
                              key={type}
                              onPress={() => setContactType(type)}
                              style={[
                                styles.typeChip,
                                {
                                  borderColor: contactType === type ? theme.primary : theme.border,
                                  backgroundColor: contactType === type ? theme.primary : theme.background,
                                },
                              ]}>
                              <ThemedText style={[styles.typeLabel, contactType === type && { color: theme.primaryText }]}>
                                {type === 'email' ? 'Email' : 'Phone'}
                              </ThemedText>
                            </Pressable>
                          ))}
                        </View>
                        <FormField
                          autoCapitalize="none"
                          keyboardType={contactType === 'email' ? 'email-address' : 'phone-pad'}
                          label={contactType === 'email' ? 'Email address' : 'International phone'}
                          onChangeText={setContactValue}
                          placeholder={contactType === 'email' ? 'sarah@example.com' : '+960 700-0000'}
                          value={contactValue}
                        />
                        <PrimaryButton
                          label="Create and add"
                          loading={createMutation.isPending}
                          onPress={saveGuest}
                        />
                      </ThemedView>
                    ) : null}
                  </>
                ) : (
                  <ThemedText style={styles.copy} themeColor="textSecondary">
                    Reopen this group before changing its members.
                  </ThemedText>
                )}
              </>
            ) : null}
            {visibleError ? <ThemedText themeColor="danger">{visibleError}</ThemedText> : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

function SectionTitle({ title }: { title: string }) {
  return <ThemedText style={styles.sectionTitle}>{title}</ThemedText>;
}

function ConfirmRow({
  confirmLabel,
  loading,
  message,
  onCancel,
  onConfirm,
}: {
  confirmLabel: string;
  loading: boolean;
  message: string;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <ThemedView type="backgroundSelected" style={styles.confirmRow}>
      <ThemedText style={styles.confirmMessage}>{message}</ThemedText>
      <View style={styles.confirmActions}>
        <Pressable disabled={loading} onPress={onCancel}>
          <ThemedText style={styles.confirmLink} themeColor="textSecondary">Cancel</ThemedText>
        </Pressable>
        <Pressable disabled={loading} onPress={onConfirm}>
          <ThemedText style={styles.confirmLink} themeColor="danger">
            {loading ? 'Working…' : confirmLabel}
          </ThemedText>
        </Pressable>
      </View>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  headerAction: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14, maxWidth: 680, width: '100%', alignSelf: 'center' },
  centered: { textAlign: 'center', paddingVertical: 40 },
  sectionTitle: { fontSize: 18, lineHeight: 25, fontWeight: '800', marginTop: 8 },
  copy: { fontSize: 13, lineHeight: 19 },
  memberList: { borderRadius: 20, paddingHorizontal: 15 },
  divider: { height: StyleSheet.hairlineWidth },
  memberRow: { minHeight: 72, flexDirection: 'row', alignItems: 'center', gap: 11 },
  avatar: { width: 40, height: 40, borderRadius: 15, alignItems: 'center', justifyContent: 'center' },
  initial: { fontWeight: '900' },
  memberCopy: { flex: 1, gap: 2 },
  memberName: { fontSize: 14, fontWeight: '800' },
  memberMeta: { fontSize: 12, lineHeight: 17 },
  memberActions: { alignItems: 'flex-end', gap: 5 },
  smallAction: { fontSize: 12, fontWeight: '800' },
  disabled: { opacity: 0.55 },
  confirmRow: { marginHorizontal: -5, marginBottom: 10, borderRadius: 14, padding: 12, gap: 8 },
  confirmMessage: { fontSize: 13, lineHeight: 19, fontWeight: '700' },
  confirmActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 20 },
  confirmLink: { fontSize: 12, fontWeight: '800' },
  placeholderGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  placeholderChip: { minHeight: 40, borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, alignItems: 'center', justifyContent: 'center' },
  placeholderName: { fontSize: 13, fontWeight: '800' },
  createGuestLink: { fontSize: 14, fontWeight: '800', paddingVertical: 4 },
  guestCard: { borderRadius: 20, padding: 16, gap: 14 },
  typeRow: { flexDirection: 'row', gap: 8 },
  typeChip: { height: 40, paddingHorizontal: 16, borderRadius: 14, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  typeLabel: { fontSize: 13, fontWeight: '800', textTransform: 'capitalize' },
});
