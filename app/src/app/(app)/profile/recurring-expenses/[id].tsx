import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { CurrencyPicker } from '@/components/currency-picker';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { currencyFractionDigits, minorAmountInput, parseDecimalToInteger } from '@/lib/format';
import {
  fetchRecurringExpense,
  type RecurringExpenseInput,
  updateRecurringExpense,
} from '@/lib/recurring-expenses-api';
import { useAuthStore } from '@/stores/auth-store';
import type { RecurrenceFrequency } from '@/types/api';

export default function EditRecurringExpenseScreen() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const rawId = Array.isArray(params.id) ? params.id[0] : params.id;
  const recurringExpenseId = Number(rawId);
  const token = useAuthStore((state) => state.token)!;
  const queryClient = useQueryClient();
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState('USD');
  const [category, setCategory] = useState('');
  const [frequency, setFrequency] = useState<RecurrenceFrequency>('monthly');
  const [endsOn, setEndsOn] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const [initialized, setInitialized] = useState(false);
  const scheduleQuery = useQuery({
    queryKey: ['recurring-expenses', recurringExpenseId],
    queryFn: () => fetchRecurringExpense(token, recurringExpenseId),
    enabled: Number.isInteger(recurringExpenseId) && recurringExpenseId > 0,
  });
  const schedule = scheduleQuery.data;

  useEffect(() => {
    if (!schedule || initialized) return;

    const timer = setTimeout(() => {
      setDescription(schedule.description);
      setAmount(minorAmountInput(schedule.amount_minor, schedule.currency_code));
      setCurrency(schedule.currency_code);
      setCategory(schedule.category ?? '');
      setFrequency(schedule.frequency);
      setEndsOn(schedule.ends_on?.slice(0, 10) ?? '');
      setInitialized(true);
    }, 0);

    return () => clearTimeout(timer);
  }, [initialized, schedule]);

  const mutation = useMutation({
    mutationFn: (input: RecurringExpenseInput) => (
      updateRecurringExpense(token, recurringExpenseId, input)
    ),
    onSuccess: async (updated) => {
      queryClient.setQueryData(['recurring-expenses', recurringExpenseId], updated);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['recurring-expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
      ]);
      router.back();
    },
  });

  function submit() {
    if (!schedule) return;
    setFormError(null);
    const currencyCode = currency.trim().toUpperCase();
    const amountMinor = parseDecimalToInteger(amount, currencyFractionDigits(currencyCode));
    if (!description.trim()) return setFormError('Enter what the expense is for.');
    if (!amountMinor || amountMinor < 1) return setFormError('Enter a valid amount.');
    if (currencyCode.length !== 3) return setFormError('Choose a valid currency.');
    if (endsOn && !/^\d{4}-\d{2}-\d{2}$/.test(endsOn)) {
      return setFormError('Enter the end date in YYYY-MM-DD format.');
    }
    if (endsOn && endsOn <= schedule.start_on.slice(0, 10)) {
      return setFormError('The end date must be after the first expense date.');
    }

    mutation.mutate({
      expense_type: schedule.expense_type,
      ...(schedule.group_id ? { group_id: schedule.group_id } : {}),
      ...(schedule.payer.user_id ? { payer_user_id: schedule.payer.user_id } : {}),
      ...(schedule.payer.placeholder_id
        ? { payer_placeholder_id: schedule.payer.placeholder_id }
        : {}),
      amount_minor: amountMinor,
      currency_code: currencyCode,
      description: description.trim(),
      ...(category.trim() ? { category: category.trim().toLowerCase() } : {}),
      occurred_at: `${schedule.start_on.slice(0, 10)}T12:00:00.000Z`,
      ...(schedule.split_type ? { split_type: schedule.split_type } : {}),
      ...(schedule.expense_type !== 'personal'
        ? {
            participants: schedule.splits.map((split) => ({
              ...(split.user_id ? { user_id: split.user_id } : {}),
              ...(split.placeholder_id ? { placeholder_id: split.placeholder_id } : {}),
              ...(split.split_value === null ? {} : { value: Number(split.split_value) }),
            })),
          }
        : {}),
      frequency,
      ...(endsOn ? { ends_on: endsOn } : {}),
    });
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Edit future expenses</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {scheduleQuery.error ? (
              <QueryErrorCard
                error={scheduleQuery.error}
                onRetry={() => void scheduleQuery.refetch()}
                retrying={scheduleQuery.isRefetching}
              />
            ) : null}
            {scheduleQuery.isLoading ? (
              <ThemedText style={styles.centered} themeColor="textSecondary">Loading schedule…</ThemedText>
            ) : null}
            {schedule && !schedule.can_manage ? (
              <ThemedView type="backgroundElement" style={styles.infoCard}>
                <ThemedText style={styles.title}>View only</ThemedText>
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Only the schedule creator or a group owner can edit it.
                </ThemedText>
              </ThemedView>
            ) : null}
            {schedule?.can_manage ? (
              <>
                <ThemedView type="backgroundSelected" style={styles.infoCard}>
                  <ThemedText style={styles.title}>Future occurrences only</ThemedText>
                  <ThemedText style={styles.copy} themeColor="textSecondary">
                    Past expenses remain unchanged. Participants, payer, type, and group stay fixed.
                  </ThemedText>
                </ThemedView>
                <FormField
                  autoCapitalize="sentences"
                  label="Description"
                  onChangeText={setDescription}
                  value={description}
                />
                <FormField
                  keyboardType="decimal-pad"
                  label="Amount"
                  onChangeText={setAmount}
                  value={amount}
                />
                <CurrencyPicker label="Currency" onChange={setCurrency} value={currency} />
                <FormField
                  autoCapitalize="words"
                  label="Category (optional)"
                  onChangeText={setCategory}
                  value={category}
                />
                <ThemedText style={styles.sectionLabel}>Repeat</ThemedText>
                <View style={styles.chips}>
                  {(['weekly', 'monthly', 'yearly'] as RecurrenceFrequency[]).map((item) => (
                    <ChoiceChip
                      active={frequency === item}
                      key={item}
                      label={item.charAt(0).toUpperCase() + item.slice(1)}
                      onPress={() => setFrequency(item)}
                    />
                  ))}
                </View>
                <FormField
                  autoCapitalize="none"
                  keyboardType="numbers-and-punctuation"
                  label="Repeat until (optional)"
                  maxLength={10}
                  onChangeText={setEndsOn}
                  placeholder="YYYY-MM-DD"
                  value={endsOn}
                />
                {formError ? <ThemedText themeColor="danger">{formError}</ThemedText> : null}
                {mutation.error ? (
                  <ThemedText themeColor="danger">{errorMessage(mutation.error)}</ThemedText>
                ) : null}
                <PrimaryButton
                  label="Save future schedule"
                  loading={mutation.isPending}
                  onPress={submit}
                />
              </>
            ) : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

function ChoiceChip({ active, label, onPress }: { active: boolean; label: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress}>
      <ThemedView type={active ? 'backgroundSelected' : 'backgroundElement'} style={styles.chip}>
        <ThemedText style={styles.chipText} themeColor={active ? 'primary' : 'text'}>{label}</ThemedText>
      </ThemedView>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14, maxWidth: 680, width: '100%', alignSelf: 'center' },
  centered: { textAlign: 'center', paddingVertical: 40 },
  infoCard: { borderRadius: 20, padding: 17, gap: 5 },
  title: { fontSize: 15, lineHeight: 21, fontWeight: '900' },
  copy: { fontSize: 13, lineHeight: 19 },
  sectionLabel: { fontSize: 14, lineHeight: 20, fontWeight: '800' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { minHeight: 42, borderRadius: 14, paddingHorizontal: 14, alignItems: 'center', justifyContent: 'center' },
  chipText: { fontSize: 13, fontWeight: '900' },
});
