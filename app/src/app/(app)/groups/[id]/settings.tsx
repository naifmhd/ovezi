import * as Linking from 'expo-linking';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { createGroupInvite, fetchGroupInvites, revokeGroupInvite } from '@/lib/group-invites-api';
import { deleteGroupRate, fetchGroupRates, saveGroupRate } from '@/lib/group-rates-api';
import { fetchGroup, setGroupArchived, updateGroup } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

function firstParam(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

export default function GroupSettingsScreen() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const groupId = Number(firstParam(params.id));
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const queryClient = useQueryClient();
  const [name, setName] = useState<string | null>(null);
  const [currency, setCurrency] = useState<string | null>(null);
  const [targetEmail, setTargetEmail] = useState('');
  const [rateCurrency, setRateCurrency] = useState('');
  const [rate, setRate] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const [confirmingArchive, setConfirmingArchive] = useState(false);
  const validGroupId = Number.isInteger(groupId) && groupId > 0;

  const groupQuery = useQuery({
    queryKey: ['group', groupId],
    queryFn: () => fetchGroup(token, groupId),
    enabled: validGroupId,
  });
  const group = groupQuery.data;
  const isOwner = group?.members?.some(
    (member) => member.user?.id === user.id && member.role === 'owner',
  ) ?? false;
  const invitesQuery = useQuery({
    queryKey: ['group-invites', groupId],
    queryFn: () => fetchGroupInvites(token, groupId),
    enabled: validGroupId && isOwner,
  });
  const activeInvites = (invitesQuery.data?.data ?? []).filter(
    (invite) => !invite.is_expired && !invite.is_revoked && !invite.accepted_at,
  );
  const ratesQuery = useQuery({
    queryKey: ['group-rates', groupId],
    queryFn: () => fetchGroupRates(token, groupId),
    enabled: validGroupId && isOwner,
  });
  const effectiveName = name ?? group?.name ?? '';
  const effectiveCurrency = currency ?? group?.reporting_currency_code ?? '';

  async function refreshGroupData() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['group', groupId] }),
      queryClient.invalidateQueries({ queryKey: ['groups'] }),
      queryClient.invalidateQueries({ queryKey: ['activity'] }),
    ]);
  }

  const updateMutation = useMutation({
    mutationFn: () => updateGroup(token, groupId, {
      name: effectiveName.trim(),
      reportingCurrencyCode: effectiveCurrency.trim().toUpperCase(),
    }),
    onSuccess: async () => {
      await refreshGroupData();
      router.back();
    },
  });
  const archiveMutation = useMutation({
    mutationFn: (archived: boolean) => setGroupArchived(token, groupId, archived),
    onSuccess: async () => {
      await refreshGroupData();
      setConfirmingArchive(false);
    },
  });
  const inviteMutation = useMutation({
    mutationFn: () => createGroupInvite(token, groupId, targetEmail.trim() || undefined),
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['group-invites', groupId] });
      setTargetEmail('');
      const inviteUrl = Linking.createURL('group-invites/accept', {
        queryParams: { token: response.meta.token },
      });
      await Share.share({
        title: `Join ${group?.name ?? 'my Ovezi group'}`,
        message: `Join ${group?.name ?? 'my group'} on Ovezi: ${inviteUrl}`,
        url: inviteUrl,
      });
    },
  });
  const revokeMutation = useMutation({
    mutationFn: (inviteId: number) => revokeGroupInvite(token, groupId, inviteId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['group-invites', groupId] }),
  });
  const rateMutation = useMutation({
    mutationFn: () => saveGroupRate(
      token,
      groupId,
      rateCurrency.trim().toUpperCase(),
      rate.trim(),
    ),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['group-rates', groupId] });
      setRateCurrency('');
      setRate('');
      setFormError(null);
    },
  });
  const deleteRateMutation = useMutation({
    mutationFn: (currencyCode: string) => deleteGroupRate(token, groupId, currencyCode),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['group-rates', groupId] }),
  });

  const visibleError = formError
    ?? (groupQuery.error ? errorMessage(groupQuery.error) : null)
    ?? (invitesQuery.error ? errorMessage(invitesQuery.error) : null)
    ?? (updateMutation.error ? errorMessage(updateMutation.error) : null)
    ?? (archiveMutation.error ? errorMessage(archiveMutation.error) : null)
    ?? (inviteMutation.error ? errorMessage(inviteMutation.error) : null)
    ?? (revokeMutation.error ? errorMessage(revokeMutation.error) : null)
    ?? (ratesQuery.error ? errorMessage(ratesQuery.error) : null)
    ?? (rateMutation.error ? errorMessage(rateMutation.error) : null)
    ?? (deleteRateMutation.error ? errorMessage(deleteRateMutation.error) : null);

  function saveSettings() {
    setFormError(null);
    if (!effectiveName.trim()) return setFormError('Enter a group name.');
    if (effectiveCurrency.trim().length !== 3) {
      return setFormError('Enter a valid three-letter currency code.');
    }
    updateMutation.mutate();
  }

  function saveRate() {
    setFormError(null);
    const baseCurrency = rateCurrency.trim().toUpperCase();
    if (baseCurrency.length !== 3) return setFormError('Enter a valid base currency code.');
    if (baseCurrency === group?.reporting_currency_code) {
      return setFormError('The override currency must differ from the group currency.');
    }
    if (!/^\d{1,12}(?:\.\d{1,12})?$/.test(rate.trim()) || Number(rate) <= 0) {
      return setFormError('Enter a positive conversion rate with up to 12 decimal places.');
    }
    rateMutation.mutate();
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Group settings</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            {!group && groupQuery.isLoading ? (
              <ThemedText style={styles.centered} themeColor="textSecondary">Loading settings…</ThemedText>
            ) : null}
            {group && !isOwner ? (
              <ThemedView type="backgroundElement" style={styles.card}>
                <ThemedText style={styles.cardTitle}>Owner access required</ThemedText>
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Only the group owner can change settings or manage invite links.
                </ThemedText>
              </ThemedView>
            ) : null}
            {group && isOwner ? (
              <>
                <SectionTitle title="Details" />
                <FormField
                  autoCapitalize="words"
                  editable={!group.is_archived}
                  label="Group name"
                  onChangeText={setName}
                  value={effectiveName}
                />
                <FormField
                  autoCapitalize="characters"
                  editable={!group.is_archived}
                  label="Reporting currency"
                  maxLength={3}
                  onChangeText={setCurrency}
                  value={effectiveCurrency}
                />
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Existing expenses keep their captured conversion rate when this currency changes.
                </ThemedText>
                {!group.is_archived ? (
                  <PrimaryButton
                    label="Save changes"
                    loading={updateMutation.isPending}
                    onPress={saveSettings}
                  />
                ) : null}

                <SectionTitle title="Currency overrides" />
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  These owner-managed rates take priority over the cached daily default. Enter how much one unit of the base currency is worth in {group.reporting_currency_code}.
                </ThemedText>
                {(ratesQuery.data?.data ?? []).map((item) => (
                  <ThemedView key={item.id} type="backgroundElement" style={styles.rateRow}>
                    <View style={styles.inviteCopy}>
                      <ThemedText style={styles.inviteTitle}>
                        1 {item.base_currency_code} = {Number(item.rate).toLocaleString('en', { maximumFractionDigits: 12 })} {item.quote_currency_code}
                      </ThemedText>
                      <ThemedText style={styles.inviteMeta} themeColor="textSecondary">Group override</ThemedText>
                    </View>
                    {!group.is_archived ? (
                      <Pressable
                        disabled={deleteRateMutation.isPending}
                        onPress={() => deleteRateMutation.mutate(item.base_currency_code)}>
                        <ThemedText style={styles.revoke} themeColor="danger">Remove</ThemedText>
                      </Pressable>
                    ) : null}
                  </ThemedView>
                ))}
                {!group.is_archived ? (
                  <ThemedView type="backgroundElement" style={styles.rateForm}>
                    <View style={styles.rateInputs}>
                      <View style={styles.rateCurrencyField}>
                        <FormField
                          autoCapitalize="characters"
                          label="Base"
                          maxLength={3}
                          onChangeText={setRateCurrency}
                          placeholder="USD"
                          value={rateCurrency}
                        />
                      </View>
                      <View style={styles.rateValueField}>
                        <FormField
                          keyboardType="decimal-pad"
                          label={`Rate in ${group.reporting_currency_code}`}
                          onChangeText={setRate}
                          placeholder="15.42"
                          value={rate}
                        />
                      </View>
                    </View>
                    <PrimaryButton
                      label="Save override"
                      loading={rateMutation.isPending}
                      onPress={saveRate}
                    />
                  </ThemedView>
                ) : null}

                <SectionTitle title="Invite people" />
                <Pressable
                  onPress={() => router.push({
                    pathname: '/(app)/groups/[id]/members',
                    params: { id: group.id },
                  })}
                  style={styles.manageMembersAction}>
                  <View>
                    <ThemedText style={styles.cardTitle}>Manage members</ThemedText>
                    <ThemedText style={styles.copy} themeColor="textSecondary">
                      Add placeholders, remove settled members, or transfer ownership.
                    </ThemedText>
                  </View>
                  <ThemedText style={styles.chevron} themeColor="primary">›</ThemedText>
                </Pressable>
                {group.is_archived ? (
                  <ThemedText style={styles.copy} themeColor="textSecondary">
                    Reopen this group before creating a new invitation.
                  </ThemedText>
                ) : (
                  <>
                    <FormField
                      autoCapitalize="none"
                      keyboardType="email-address"
                      label="Email restriction (optional)"
                      onChangeText={setTargetEmail}
                      placeholder="friend@example.com"
                      value={targetEmail}
                    />
                    <ThemedText style={styles.copy} themeColor="textSecondary">
                      Leave this blank for a link anyone can accept. Add an email to restrict it to that verified account.
                    </ThemedText>
                    <PrimaryButton
                      label="Create and share invite"
                      loading={inviteMutation.isPending}
                      onPress={() => inviteMutation.mutate()}
                    />
                  </>
                )}

                {activeInvites.length > 0 ? (
                  <ThemedView type="backgroundElement" style={styles.inviteList}>
                    {activeInvites.map((invite, index) => (
                      <View key={invite.id}>
                        {index > 0 ? <View style={styles.divider} /> : null}
                        <View style={styles.inviteRow}>
                          <View style={styles.inviteCopy}>
                            <ThemedText style={styles.inviteTitle}>
                              {invite.invited_email ?? 'Open invite link'}
                            </ThemedText>
                            <ThemedText style={styles.inviteMeta} themeColor="textSecondary">
                              Expires {new Date(invite.expires_at).toLocaleDateString('en', { month: 'short', day: 'numeric' })}
                            </ThemedText>
                          </View>
                          <Pressable
                            disabled={revokeMutation.isPending}
                            onPress={() => revokeMutation.mutate(invite.id)}>
                            <ThemedText style={styles.revoke} themeColor="danger">Revoke</ThemedText>
                          </Pressable>
                        </View>
                      </View>
                    ))}
                  </ThemedView>
                ) : null}

                <SectionTitle title="Group status" />
                <ThemedView type="backgroundElement" style={styles.card}>
                  <ThemedText style={styles.cardTitle}>
                    {group.is_archived ? 'This group is archived' : 'Archive this group'}
                  </ThemedText>
                  <ThemedText style={styles.copy} themeColor="textSecondary">
                    {group.is_archived
                      ? 'Reopen it to add expenses, change settings, or create invitations.'
                      : 'History and outstanding balances are preserved. Payments can still be recorded.'}
                  </ThemedText>
                  {group.is_archived ? (
                    <PrimaryButton
                      label="Reopen group"
                      loading={archiveMutation.isPending}
                      onPress={() => archiveMutation.mutate(false)}
                    />
                  ) : confirmingArchive ? (
                    <View style={styles.confirmActions}>
                      <Pressable onPress={() => setConfirmingArchive(false)} style={styles.secondaryAction}>
                        <ThemedText style={styles.secondaryLabel}>Cancel</ThemedText>
                      </Pressable>
                      <Pressable
                        disabled={archiveMutation.isPending}
                        onPress={() => archiveMutation.mutate(true)}
                        style={styles.dangerAction}>
                        <ThemedText style={styles.dangerLabel}>
                          {archiveMutation.isPending ? 'Archiving…' : 'Confirm archive'}
                        </ThemedText>
                      </Pressable>
                    </View>
                  ) : (
                    <Pressable onPress={() => setConfirmingArchive(true)} style={styles.archiveAction}>
                      <ThemedText style={styles.archiveLabel} themeColor="danger">Archive group</ThemedText>
                    </Pressable>
                  )}
                </ThemedView>
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

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  headerAction: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14, maxWidth: 620, width: '100%', alignSelf: 'center' },
  centered: { textAlign: 'center', paddingVertical: 40 },
  sectionTitle: { fontSize: 18, lineHeight: 25, fontWeight: '800', marginTop: 8 },
  copy: { fontSize: 13, lineHeight: 19 },
  card: { borderRadius: 20, padding: 17, gap: 10 },
  cardTitle: { fontSize: 15, fontWeight: '800' },
  inviteList: { borderRadius: 20, paddingHorizontal: 16 },
  inviteRow: { minHeight: 67, flexDirection: 'row', alignItems: 'center', gap: 12 },
  inviteCopy: { flex: 1, gap: 2 },
  inviteTitle: { fontSize: 14, fontWeight: '700' },
  inviteMeta: { fontSize: 12 },
  revoke: { fontSize: 13, fontWeight: '800' },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.3 },
  confirmActions: { flexDirection: 'row', gap: 10, marginTop: 4 },
  secondaryAction: { flex: 1, height: 46, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#DDE5E2' },
  secondaryLabel: { color: '#0A1128', fontWeight: '800' },
  dangerAction: { flex: 1, height: 46, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#C63E4E' },
  dangerLabel: { color: '#FFFFFF', fontWeight: '800' },
  archiveAction: { height: 46, borderRadius: 14, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#C63E4E', marginTop: 4 },
  archiveLabel: { fontWeight: '800' },
  manageMembersAction: { minHeight: 70, borderRadius: 18, padding: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: '#DDFBF0', gap: 12 },
  chevron: { fontSize: 28, lineHeight: 30, fontWeight: '500' },
  rateRow: { minHeight: 64, borderRadius: 18, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rateForm: { borderRadius: 20, padding: 16, gap: 14 },
  rateInputs: { flexDirection: 'row', gap: 10 },
  rateCurrencyField: { width: 105 },
  rateValueField: { flex: 1 },
});
