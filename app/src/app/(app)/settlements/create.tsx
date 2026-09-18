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
import { currencyFractionDigits, formatMoney, parseDecimalToInteger } from '@/lib/format';
import { fetchGroup, fetchGroupBalances } from '@/lib/groups-api';
import { createSettlement, type CreateSettlementInput } from '@/lib/settlements-api';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';

const methods = [
  { value: 'cash', label: 'Cash' },
  { value: 'bank transfer', label: 'Bank transfer' },
  { value: 'other', label: 'Other' },
];

function firstParam(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

function participantFields(key: string, prefix: 'from' | 'to') {
  const [kind, rawId] = key.split(':');
  const id = Number(rawId);
  if (!Number.isInteger(id) || id < 1) return {};

  return kind === 'user'
    ? { [`${prefix}_user_id`]: id }
    : { [`${prefix}_placeholder_id`]: id };
}

export default function CreateSettlementScreen() {
  const params = useLocalSearchParams<{
    groupId?: string | string[];
    from?: string | string[];
    to?: string | string[];
    amount?: string | string[];
  }>();
  const groupId = Number(firstParam(params.groupId));
  const suggestedFrom = firstParam(params.from) ?? '';
  const suggestedTo = firstParam(params.to) ?? '';
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const queryClient = useQueryClient();
  const [fromKey, setFromKey] = useState(suggestedFrom);
  const [toKey, setToKey] = useState(suggestedTo);
  const [amount, setAmount] = useState(firstParam(params.amount) ?? '');
  const [method, setMethod] = useState('');
  const [note, setNote] = useState('');
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
  const group = groupQuery.data;
  const balances = balancesQuery.data;
  const participants = balances?.members ?? [];
  const currentUserKey = `user:${user.id}`;
  const effectiveFrom = fromKey || (
    participants.find((item) => item.participant.key === currentUserKey)?.participant.key
      ?? participants.find((item) => item.balance_minor < 0)?.participant.key
      ?? ''
  );
  const effectiveTo = toKey || (
    participants.find((item) => item.participant.key !== effectiveFrom && item.balance_minor > 0)?.participant.key
      ?? participants.find((item) => item.participant.key !== effectiveFrom)?.participant.key
      ?? ''
  );
  const senderBalance = participants.find((item) => item.participant.key === effectiveFrom)?.balance_minor ?? 0;
  const recipientBalance = participants.find((item) => item.participant.key === effectiveTo)?.balance_minor ?? 0;
  const maximumMinor = Math.max(0, Math.min(-senderBalance, recipientBalance));
  const isOwner = group?.members?.some(
    (member) => member.user?.id === user.id && member.role === 'owner',
  ) ?? false;

  const mutation = useMutation({
    mutationFn: (input: CreateSettlementInput) => createSettlement(token, groupId, input),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['group-balances', groupId] }),
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
      ]);
      router.replace({ pathname: '/(app)/groups/[id]', params: { id: groupId } });
    },
  });

  function submit() {
    setFormError(null);
    if (!group || !balances) return setFormError('The group is still loading.');
    if (!effectiveFrom || !effectiveTo || effectiveFrom === effectiveTo) {
      return setFormError('Choose two different group members.');
    }
    if (!isOwner && effectiveFrom !== currentUserKey && effectiveTo !== currentUserKey) {
      return setFormError('You can only record a payment that includes you.');
    }
    if (maximumMinor <= 0) {
      return setFormError('These members do not currently have an open balance in this direction.');
    }

    const amountMinor = parseDecimalToInteger(
      amount,
      currencyFractionDigits(group.reporting_currency_code),
    );
    if (!amountMinor || amountMinor < 1) return setFormError('Enter a valid payment amount.');
    if (amountMinor > maximumMinor) {
      return setFormError(`The payment cannot exceed ${formatMoney(maximumMinor, group.reporting_currency_code)}.`);
    }

    mutation.mutate({
      ...participantFields(effectiveFrom, 'from'),
      ...participantFields(effectiveTo, 'to'),
      amount_minor: amountMinor,
      currency_code: group.reporting_currency_code,
      ...(method ? { method } : {}),
      ...(note.trim() ? { note: note.trim() } : {}),
      occurred_at: new Date().toISOString(),
    });
  }

  const visibleError = formError
    ?? (groupQuery.error ? errorMessage(groupQuery.error) : null)
    ?? (balancesQuery.error ? errorMessage(balancesQuery.error) : null)
    ?? (mutation.error ? errorMessage(mutation.error) : null);

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="textSecondary">Cancel</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Record payment</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            <ThemedView type="backgroundSelected" style={styles.infoCard}>
              <ThemedText style={styles.infoTitle}>No money moves through Ovezi</ThemedText>
              <ThemedText style={styles.infoCopy} themeColor="textSecondary">
                Record a payment that already happened outside the app. This updates the group balance.
              </ThemedText>
            </ThemedView>

            <SectionLabel label="Who paid" />
            <View style={styles.chipWrap}>
              {participants.map((member) => (
                <ChoiceChip
                  active={effectiveFrom === member.participant.key}
                  key={member.participant.key}
                  label={member.participant.name}
                  onPress={() => {
                    setFromKey(member.participant.key);
                    if (effectiveTo === member.participant.key) setToKey('');
                  }}
                />
              ))}
            </View>

            <SectionLabel label="Who received it" />
            <View style={styles.chipWrap}>
              {participants
                .filter((member) => member.participant.key !== effectiveFrom)
                .map((member) => (
                  <ChoiceChip
                    active={effectiveTo === member.participant.key}
                    key={member.participant.key}
                    label={member.participant.name}
                    onPress={() => setToKey(member.participant.key)}
                  />
                ))}
            </View>

            <FormField
              keyboardType="decimal-pad"
              label={`Amount · ${group?.reporting_currency_code ?? ''}`}
              onChangeText={setAmount}
              placeholder="0.00"
              value={amount}
            />
            {group && maximumMinor > 0 ? (
              <ThemedText style={styles.helper} themeColor="textSecondary">
                Up to {formatMoney(maximumMinor, group.reporting_currency_code)} can be settled in this direction.
              </ThemedText>
            ) : null}

            <SectionLabel label="Method (optional)" />
            <View style={styles.chipWrap}>
              {methods.map((item) => (
                <ChoiceChip
                  active={method === item.value}
                  key={item.value}
                  label={item.label}
                  onPress={() => setMethod(method === item.value ? '' : item.value)}
                />
              ))}
            </View>

            <FormField
              autoCapitalize="sentences"
              label="Note (optional)"
              onChangeText={setNote}
              placeholder="Bank transfer reference, cash…"
              value={note}
            />

            {visibleError ? <ThemedText themeColor="danger">{visibleError}</ThemedText> : null}
            <PrimaryButton
              disabled={groupQuery.isLoading || balancesQuery.isLoading}
              label="Record payment"
              loading={mutation.isPending}
              onPress={submit}
            />
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

function SectionLabel({ label }: { label: string }) {
  return <ThemedText style={styles.sectionLabel}>{label}</ThemedText>;
}

function ChoiceChip({ active, label, onPress }: { active: boolean; label: string; onPress: () => void }) {
  const theme = useTheme();
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.chip,
        {
          backgroundColor: active ? theme.primary : theme.backgroundElement,
          borderColor: active ? theme.primary : theme.border,
        },
      ]}>
      <ThemedText style={[styles.chipLabel, active && { color: theme.primaryText }]}>{label}</ThemedText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  header: {
    height: 60,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
  },
  headerAction: { fontSize: 14, fontWeight: '700' },
  headerTitle: { fontSize: 17, lineHeight: 24, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14 },
  infoCard: { borderRadius: 18, padding: 16, gap: 3 },
  infoTitle: { fontWeight: '800' },
  infoCopy: { fontSize: 13, lineHeight: 19 },
  sectionLabel: { fontSize: 14, lineHeight: 20, fontWeight: '800', marginTop: 4 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: {
    minHeight: 40,
    borderWidth: 1,
    borderRadius: 14,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipLabel: { fontSize: 13, fontWeight: '800' },
  helper: { fontSize: 13, lineHeight: 19, marginTop: -7 },
});
