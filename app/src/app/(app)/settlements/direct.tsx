import { HeaderAction } from '@/components/ui/header-action';
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
import { fetchOverallBalances } from '@/lib/balances-api';
import { currencyFractionDigits, formatMoney, parseDecimalToInteger } from '@/lib/format';
import { createDirectSettlement, type CreateSettlementInput } from '@/lib/settlements-api';
import { useAuthStore } from '@/stores/auth-store';

const methods = [
  { value: 'cash', label: 'Cash' },
  { value: 'bank transfer', label: 'Bank transfer' },
  { value: 'other', label: 'Other' },
];

function firstParam(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

export default function CreateDirectSettlementScreen() {
  const params = useLocalSearchParams<{
    participant?: string | string[];
    currency?: string | string[];
  }>();
  const participantKey = firstParam(params.participant) ?? '';
  const currencyCode = firstParam(params.currency) ?? '';
  const token = useAuthStore((state) => state.token)!;
  const [occurredAt] = useState(() => new Date().toISOString());
  const user = useAuthStore((state) => state.user)!;
  const queryClient = useQueryClient();
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('');
  const [note, setNote] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const balancesQuery = useQuery({
    queryKey: ['dashboard-balances'],
    queryFn: () => fetchOverallBalances(token),
  });
  const balance = balancesQuery.data?.direct.find(
    (entry) => entry.participant.key === participantKey && entry.currency_code === currencyCode,
  );
  const maximumMinor = Math.abs(balance?.balance_minor ?? 0);

  const mutation = useMutation({
    mutationFn: (input: CreateSettlementInput) => createDirectSettlement(token, input),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
      ]);
      router.replace('/(app)/(tabs)');
    },
  });

  function submit() {
    setFormError(null);
    if (!balance) return setFormError('This balance is no longer open.');

    const amountMinor = parseDecimalToInteger(amount, currencyFractionDigits(currencyCode));
    if (!amountMinor || amountMinor < 1) return setFormError('Enter a valid payment amount.');
    if (amountMinor > maximumMinor) {
      return setFormError(`The payment cannot exceed ${formatMoney(maximumMinor, currencyCode)}.`);
    }

    const counterpart = balance.participant.user_id !== null
      ? { user_id: balance.participant.user_id }
      : { placeholder_id: balance.participant.placeholder_id! };
    const direction = balance.balance_minor < 0
      ? { from_user_id: user.id, ...prefixParticipant(counterpart, 'to') }
      : { ...prefixParticipant(counterpart, 'from'), to_user_id: user.id };

    mutation.mutate({
      ...direction,
      amount_minor: amountMinor,
      currency_code: currencyCode,
      reporting_currency_code: currencyCode,
      ...(method ? { method } : {}),
      ...(note.trim() ? { note: note.trim() } : {}),
      occurred_at: occurredAt,
    });
  }

  const visibleError = formError
    ?? (balancesQuery.error ? errorMessage(balancesQuery.error) : null)
    ?? (mutation.error ? errorMessage(mutation.error) : null);

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="textSecondary">Cancel</ThemedText>
          </HeaderAction>
          <ThemedText style={styles.headerTitle}>Settle 1-on-1</ThemedText>
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
                Record a payment that already happened outside the app.
              </ThemedText>
            </ThemedView>

            {balance ? (
              <ThemedView type="backgroundElement" style={styles.balanceCard}>
                <View style={styles.balanceCopy}>
                  <ThemedText style={styles.personName}>{balance.participant.name}</ThemedText>
                  <ThemedText style={styles.infoCopy} themeColor="textSecondary">
                    {balance.balance_minor < 0 ? 'You owe' : 'Owes you'}
                  </ThemedText>
                </View>
                <ThemedText
                  style={styles.balanceAmount}
                  themeColor={balance.balance_minor < 0 ? 'danger' : 'positive'}>
                  {formatMoney(maximumMinor, currencyCode)}
                </ThemedText>
              </ThemedView>
            ) : null}

            <FormField
              keyboardType="decimal-pad"
              label={`Amount · ${currencyCode}`}
              onChangeText={setAmount}
              placeholder="0.00"
              value={amount}
            />
            {balance ? (
              <ThemedText style={styles.helper} themeColor="textSecondary">
                Up to {formatMoney(maximumMinor, currencyCode)} can be settled.
              </ThemedText>
            ) : null}

            <ThemedText style={styles.sectionLabel}>Method (optional)</ThemedText>
            <View style={styles.chipWrap}>
              {methods.map((item) => (
                <Pressable
                  key={item.value}
                  onPress={() => setMethod(method === item.value ? '' : item.value)}
                  style={({ pressed }) => [styles.chip, pressed && styles.pressed]}>
                  <ThemedView
                    type={method === item.value ? 'backgroundSelected' : 'backgroundElement'}
                    style={styles.chipInner}>
                    <ThemedText style={styles.chipLabel}>{item.label}</ThemedText>
                  </ThemedView>
                </Pressable>
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
              disabled={balancesQuery.isLoading || !balance}
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

function prefixParticipant(
  participant: { user_id: number } | { placeholder_id: number },
  prefix: 'from' | 'to',
) {
  return 'user_id' in participant
    ? { [`${prefix}_user_id`]: participant.user_id }
    : { [`${prefix}_placeholder_id`]: participant.placeholder_id };
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
  headerAction: { fontSize: 14, fontWeight: '600' },
  headerTitle: { fontSize: 17, lineHeight: 24, fontWeight: '600' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14 },
  infoCard: { borderRadius: 18, padding: 16, gap: 3 },
  infoTitle: { fontWeight: '600' },
  infoCopy: { fontSize: 13, lineHeight: 19 },
  balanceCard: {
    borderRadius: 20,
    padding: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  balanceCopy: { flex: 1, gap: 2 },
  personName: { fontSize: 18, lineHeight: 25, fontWeight: '600' },
  balanceAmount: { fontSize: 18, lineHeight: 25, fontWeight: '600' },
  helper: { fontSize: 13, lineHeight: 19, marginTop: -7 },
  sectionLabel: { fontSize: 14, lineHeight: 20, fontWeight: '600', marginTop: 4 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { borderRadius: 14 },
  chipInner: {
    minHeight: 40,
    borderRadius: 14,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  chipLabel: { fontSize: 13, fontWeight: '600' },
  pressed: { opacity: 0.75 },
});
