import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import {
  createExpense,
  type CreateExpenseInput,
  type ExpenseParticipantInput,
} from '@/lib/expenses-api';
import { currencyFractionDigits, parseDecimalToInteger } from '@/lib/format';
import { fetchGroup, fetchGroups } from '@/lib/groups-api';
import { useTheme } from '@/hooks/use-theme';
import { createPlaceholder, fetchPlaceholders } from '@/lib/placeholders-api';
import { useAuthStore } from '@/stores/auth-store';
import type { GroupMember, SplitType } from '@/types/api';

type ParticipantDraft = {
  key: string;
  name: string;
  userId?: number;
  placeholderId?: number;
  selected: boolean;
  value: string;
};

type Destination = 'personal' | 'direct' | 'group';

const splitOptions: { value: SplitType; label: string }[] = [
  { value: 'equal', label: 'Equal' },
  { value: 'exact', label: 'Exact' },
  { value: 'percentage', label: '%' },
  { value: 'shares', label: 'Shares' },
];

const categories = ['Food', 'Transport', 'Home', 'Travel', 'Other'];

function memberDraft(member: GroupMember): ParticipantDraft {
  if (member.user) {
    return {
      key: `user:${member.user.id}`,
      name: member.user.name,
      userId: member.user.id,
      selected: true,
      value: '',
    };
  }

  return {
    key: `placeholder:${member.placeholder!.id}`,
    name: member.placeholder!.name,
    placeholderId: member.placeholder!.id,
    selected: true,
    value: '',
  };
}

export default function CreateExpenseScreen() {
  const params = useLocalSearchParams<{ groupId?: string | string[] }>();
  const rawGroupId = Array.isArray(params.groupId) ? params.groupId[0] : params.groupId;
  const initialGroupId = Number(rawGroupId);
  const hasInitialGroup = Number.isInteger(initialGroupId) && initialGroupId > 0;
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const theme = useTheme();
  const queryClient = useQueryClient();
  const [destination, setDestination] = useState<Destination>(hasInitialGroup ? 'group' : 'personal');
  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(
    hasInitialGroup ? initialGroupId : null,
  );
  const [selectedPlaceholderId, setSelectedPlaceholderId] = useState<number | null>(null);
  const [showGuestForm, setShowGuestForm] = useState(false);
  const [guestName, setGuestName] = useState('');
  const [guestContactType, setGuestContactType] = useState<'email' | 'phone'>('email');
  const [guestContactValue, setGuestContactValue] = useState('');
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState(user.default_currency_code);
  const [category, setCategory] = useState('');
  const [splitType, setSplitType] = useState<SplitType>('equal');
  const [participantOverrides, setParticipantOverrides] = useState<ParticipantDraft[] | null>(null);
  const [payerKey, setPayerKey] = useState('');
  const [currencyTouched, setCurrencyTouched] = useState(false);
  const [expenseRate, setExpenseRate] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  const groupsQuery = useQuery({
    queryKey: ['groups', 'active'],
    queryFn: () => fetchGroups(token),
  });
  const groupQuery = useQuery({
    queryKey: ['group', selectedGroupId],
    queryFn: () => fetchGroup(token, selectedGroupId!),
    enabled: destination === 'group' && selectedGroupId !== null,
  });
  const placeholdersQuery = useQuery({
    queryKey: ['placeholders'],
    queryFn: () => fetchPlaceholders(token),
  });
  const group = groupQuery.data;
  const placeholders = (placeholdersQuery.data?.data ?? []).filter((item) => !item.is_claimed);
  const selectedPlaceholder = placeholders.find((item) => item.id === selectedPlaceholderId);
  let defaultParticipants: ParticipantDraft[] = [];
  if (destination === 'group') defaultParticipants = (group?.members ?? []).map(memberDraft);
  if (destination === 'direct' && selectedPlaceholder) {
    defaultParticipants = [
      {
        key: `user:${user.id}`,
        name: user.name,
        userId: user.id,
        selected: true,
        value: '',
      },
      {
        key: `placeholder:${selectedPlaceholder.id}`,
        name: selectedPlaceholder.name,
        placeholderId: selectedPlaceholder.id,
        selected: true,
        value: '',
      },
    ];
  }
  const participants = participantOverrides ?? defaultParticipants;
  const selectedParticipants = participants.filter((participant) => participant.selected);
  const effectivePayerKey = payerKey || (
    participants.find((participant) => participant.userId === user.id)?.key
      ?? participants[0]?.key
      ?? ''
  );
  const effectiveCurrency = !currencyTouched && destination === 'group' && group
    ? group.reporting_currency_code
    : currency;
  const reportingCurrency = group?.reporting_currency_code ?? user.default_currency_code;
  const rateNeeded = Boolean(
    destination !== 'personal'
      && effectiveCurrency.trim().toUpperCase() !== reportingCurrency,
  );

  const placeholderMutation = useMutation({
    mutationFn: () => createPlaceholder(token, {
      name: guestName.trim(),
      contactType: guestContactType,
      contactValue: guestContactValue.trim(),
    }),
    onSuccess: async (placeholder) => {
      await queryClient.invalidateQueries({ queryKey: ['placeholders'] });
      setSelectedPlaceholderId(placeholder.id);
      setParticipantOverrides(null);
      setShowGuestForm(false);
      setGuestName('');
      setGuestContactValue('');
      setFormError(null);
    },
  });

  const mutation = useMutation({
    mutationFn: (input: CreateExpenseInput) => createExpense(token, input),
    onSuccess: async (expense) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
        queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
      ]);
      if (expense.group_id) {
        router.replace({ pathname: '/(app)/groups/[id]', params: { id: expense.group_id } });
      } else {
        router.back();
      }
    },
  });

  function selectDestination(nextDestination: Destination, groupId: number | null = null) {
    setDestination(nextDestination);
    setSelectedGroupId(groupId);
    setSelectedPlaceholderId(null);
    setParticipantOverrides(null);
    setSplitType('equal');
    setPayerKey('');
    setCurrency(user.default_currency_code);
    setCurrencyTouched(false);
    setExpenseRate('');
    setShowGuestForm(false);
    setFormError(null);
  }

  function selectPlaceholder(placeholderId: number) {
    setSelectedPlaceholderId(placeholderId);
    setParticipantOverrides(null);
    setPayerKey('');
    setFormError(null);
  }

  function saveGuest() {
    setFormError(null);
    if (!guestName.trim()) return setFormError('Enter a name for this person.');
    if (!guestContactValue.trim()) {
      return setFormError(`Enter their ${guestContactType === 'email' ? 'email address' : 'phone number'}.`);
    }
    placeholderMutation.mutate();
  }

  function toggleParticipant(key: string) {
    const next = participants.map((participant) =>
      participant.key === key
        ? { ...participant, selected: !participant.selected, value: '' }
        : participant,
    );
    const selected = next.filter((participant) => participant.selected);
    if (!selected.some((participant) => participant.key === effectivePayerKey)) {
      setPayerKey(selected[0]?.key ?? '');
    }
    setParticipantOverrides(next);
  }

  function updateParticipantValue(key: string, value: string) {
    setParticipantOverrides((current) =>
      (current ?? defaultParticipants).map((participant) =>
        participant.key === key ? { ...participant, value } : participant,
      ),
    );
  }

  function buildParticipants(fractionDigits: number): ExpenseParticipantInput[] | null {
    const result: ExpenseParticipantInput[] = [];

    for (const participant of selectedParticipants) {
      let value: number | undefined;
      if (splitType === 'exact') value = parseDecimalToInteger(participant.value, fractionDigits) ?? undefined;
      if (splitType === 'percentage') value = parseDecimalToInteger(participant.value, 2) ?? undefined;
      if (splitType === 'shares') {
        const parsed = Number(participant.value);
        value = Number.isInteger(parsed) && parsed > 0 ? parsed : undefined;
      }
      if (splitType !== 'equal' && value === undefined) return null;

      result.push({
        ...(participant.userId ? { user_id: participant.userId } : {}),
        ...(participant.placeholderId ? { placeholder_id: participant.placeholderId } : {}),
        ...(value === undefined ? {} : { value }),
      });
    }

    return result;
  }

  function submit() {
    setFormError(null);
    const currencyCode = effectiveCurrency.trim().toUpperCase();
    const fractionDigits = currencyFractionDigits(currencyCode);
    const amountMinor = parseDecimalToInteger(amount, fractionDigits);
    if (!description.trim()) return setFormError('Enter what the expense was for.');
    if (currencyCode.length !== 3) return setFormError('Enter a valid three-letter currency code.');
    if (!amountMinor || amountMinor < 1) return setFormError('Enter a valid amount.');

    if (destination === 'personal') {
      mutation.mutate({
        expense_type: 'personal',
        payer_user_id: user.id,
        amount_minor: amountMinor,
        currency_code: currencyCode,
        description: description.trim(),
        ...(category ? { category: category.toLowerCase() } : {}),
        occurred_at: new Date().toISOString(),
      });
      return;
    }

    if (destination === 'group' && !group) return setFormError('The group is still loading.');
    if (destination === 'direct' && !selectedPlaceholder) {
      return setFormError('Choose or add the person sharing this expense.');
    }
    if (selectedParticipants.length === 0) return setFormError('Select at least one participant.');
    const payer = selectedParticipants.find((participant) => participant.key === effectivePayerKey);
    if (!payer) return setFormError('Select a payer included in the split.');
    const participantInput = buildParticipants(fractionDigits);
    if (!participantInput) return setFormError(`Enter a valid value for every ${splitType} split.`);
    const valueTotal = participantInput.reduce((total, participant) => total + (participant.value ?? 0), 0);
    if (splitType === 'exact' && valueTotal !== amountMinor) {
      return setFormError('Exact split amounts must equal the expense total.');
    }
    if (splitType === 'percentage' && valueTotal !== 10_000) {
      return setFormError('Percentage splits must total 100%.');
    }

    mutation.mutate({
      expense_type: destination,
      ...(destination === 'group' && group ? { group_id: group.id } : {}),
      ...(payer.userId ? { payer_user_id: payer.userId } : {}),
      ...(payer.placeholderId ? { payer_placeholder_id: payer.placeholderId } : {}),
      amount_minor: amountMinor,
      currency_code: currencyCode,
      description: description.trim(),
      ...(category ? { category: category.toLowerCase() } : {}),
      occurred_at: new Date().toISOString(),
      split_type: splitType,
      participants: participantInput,
      ...(rateNeeded && expenseRate.trim() ? { expense_rate: expenseRate.trim() } : {}),
    });
  }

  const visibleError = formError
    ?? (placeholderMutation.error ? errorMessage(placeholderMutation.error) : null)
    ?? (mutation.error ? errorMessage(mutation.error) : null);

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="textSecondary">Cancel</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Add expense</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            <SectionLabel label="Where" />
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              <View style={styles.chipRow}>
                <ChoiceChip
                  active={destination === 'personal'}
                  label="Just for me"
                  onPress={() => selectDestination('personal')}
                />
                <ChoiceChip
                  active={destination === 'direct'}
                  label="1-on-1"
                  onPress={() => selectDestination('direct')}
                />
                {(groupsQuery.data?.data ?? []).map((item) => (
                  <ChoiceChip
                    active={destination === 'group' && selectedGroupId === item.id}
                    key={item.id}
                    label={item.name}
                    onPress={() => selectDestination('group', item.id)}
                  />
                ))}
              </View>
            </ScrollView>

            <FormField
              autoCapitalize="sentences"
              label="Description"
              onChangeText={setDescription}
              placeholder="Dinner, taxi, groceries…"
              value={description}
            />
            <View style={styles.amountRow}>
              <View style={styles.amountField}>
                <FormField
                  keyboardType="decimal-pad"
                  label="Amount"
                  onChangeText={setAmount}
                  placeholder="0.00"
                  value={amount}
                />
              </View>
              <View style={styles.currencyField}>
                <FormField
                  autoCapitalize="characters"
                  label="Currency"
                  maxLength={3}
                  onChangeText={(value) => {
                    setCurrencyTouched(true);
                    setCurrency(value);
                  }}
                  value={effectiveCurrency}
                />
              </View>
            </View>

            <SectionLabel label="Category (optional)" />
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              <View style={styles.chipRow}>
                {categories.map((item) => (
                  <ChoiceChip
                    active={category === item}
                    key={item}
                    label={item}
                    onPress={() => setCategory(category === item ? '' : item)}
                  />
                ))}
              </View>
            </ScrollView>

            {destination === 'direct' ? (
              <>
                <SectionLabel label="With" />
                {placeholdersQuery.isLoading ? (
                  <ThemedText themeColor="textSecondary">Loading people…</ThemedText>
                ) : null}
                <View style={styles.chipWrap}>
                  {placeholders.map((placeholder) => (
                    <ChoiceChip
                      active={selectedPlaceholderId === placeholder.id}
                      key={placeholder.id}
                      label={placeholder.name}
                      onPress={() => selectPlaceholder(placeholder.id)}
                    />
                  ))}
                  <ChoiceChip
                    active={showGuestForm}
                    label="+ Add someone"
                    onPress={() => setShowGuestForm((visible) => !visible)}
                  />
                </View>
                {!placeholdersQuery.isLoading && placeholders.length === 0 && !showGuestForm ? (
                  <ThemedText style={styles.helper} themeColor="textSecondary">
                    Add someone with an email or international phone number. They do not need an account yet.
                  </ThemedText>
                ) : null}
                {showGuestForm ? (
                  <ThemedView type="backgroundElement" style={styles.guestCard}>
                    <ThemedText style={styles.infoTitle}>Add someone</ThemedText>
                    <FormField
                      autoCapitalize="words"
                      label="Name"
                      onChangeText={setGuestName}
                      placeholder="Sarah"
                      value={guestName}
                    />
                    <View style={styles.segmentRow}>
                      <ChoiceChip
                        active={guestContactType === 'email'}
                        label="Email"
                        onPress={() => setGuestContactType('email')}
                      />
                      <ChoiceChip
                        active={guestContactType === 'phone'}
                        label="Phone"
                        onPress={() => setGuestContactType('phone')}
                      />
                    </View>
                    <FormField
                      autoCapitalize="none"
                      keyboardType={guestContactType === 'email' ? 'email-address' : 'phone-pad'}
                      label={guestContactType === 'email' ? 'Email address' : 'Phone number'}
                      onChangeText={setGuestContactValue}
                      placeholder={guestContactType === 'email' ? 'sarah@example.com' : '+960 700-0000'}
                      value={guestContactValue}
                    />
                    <PrimaryButton
                      label="Add person"
                      loading={placeholderMutation.isPending}
                      onPress={saveGuest}
                    />
                  </ThemedView>
                ) : null}
              </>
            ) : null}

            {destination !== 'personal' ? (
              <>
                <SectionLabel label="Paid by" />
                {destination === 'group' && groupQuery.isLoading ? (
                  <ThemedText themeColor="textSecondary">Loading members…</ThemedText>
                ) : null}
                <View style={styles.chipWrap}>
                  {selectedParticipants.map((participant) => (
                    <ChoiceChip
                      active={effectivePayerKey === participant.key}
                      key={participant.key}
                      label={participant.name}
                      onPress={() => setPayerKey(participant.key)}
                    />
                  ))}
                </View>

                <SectionLabel label="Split" />
                <View style={styles.segmentRow}>
                  {splitOptions.map((option) => (
                    <ChoiceChip
                      active={splitType === option.value}
                      key={option.value}
                      label={option.label}
                      onPress={() => {
                        setSplitType(option.value);
                        setParticipantOverrides((current) =>
                          (current ?? defaultParticipants).map((item) => ({ ...item, value: '' })),
                        );
                      }}
                    />
                  ))}
                </View>

                <ThemedView type="backgroundElement" style={styles.participantCard}>
                  {participants.map((participant, index) => (
                    <View key={participant.key}>
                      {index > 0 ? <View style={[styles.divider, { backgroundColor: theme.border }]} /> : null}
                      <View style={styles.participantRow}>
                        {destination === 'group' ? (
                          <Pressable
                            accessibilityRole="checkbox"
                            accessibilityState={{ checked: participant.selected }}
                            onPress={() => toggleParticipant(participant.key)}
                            style={[
                              styles.checkbox,
                              { borderColor: participant.selected ? theme.primary : theme.border },
                              participant.selected && { backgroundColor: theme.primary },
                            ]}>
                            {participant.selected ? <ThemedText style={styles.check}>✓</ThemedText> : null}
                          </Pressable>
                        ) : (
                          <View style={[styles.checkbox, { borderColor: theme.primary, backgroundColor: theme.primary }]}>
                            <ThemedText style={styles.check}>✓</ThemedText>
                          </View>
                        )}
                        <ThemedText style={styles.participantName}>{participant.name}</ThemedText>
                        {participant.selected && splitType !== 'equal' ? (
                          <View style={styles.valueWrap}>
                            <TextInput
                              keyboardType="decimal-pad"
                              onChangeText={(value) => updateParticipantValue(participant.key, value)}
                              placeholder={splitType === 'percentage' ? '0.00' : splitType === 'shares' ? '1' : '0.00'}
                              placeholderTextColor={theme.textSecondary}
                              selectionColor={theme.primary}
                              style={[
                                styles.valueInput,
                                { color: theme.text, borderColor: theme.border },
                              ]}
                              value={participant.value}
                            />
                            {splitType === 'percentage' ? (
                              <ThemedText themeColor="textSecondary">%</ThemedText>
                            ) : null}
                          </View>
                        ) : null}
                      </View>
                    </View>
                  ))}
                </ThemedView>

                {rateNeeded ? (
                  <FormField
                    keyboardType="decimal-pad"
                    label={`Rate · 1 ${effectiveCurrency.trim().toUpperCase()} in ${reportingCurrency} (optional)`}
                    onChangeText={setExpenseRate}
                    placeholder="Use saved/default rate"
                    value={expenseRate}
                  />
                ) : null}
              </>
            ) : (
              <ThemedView type="backgroundSelected" style={styles.infoCard}>
                <ThemedText style={styles.infoTitle}>Personal tracking only</ThemedText>
                <ThemedText style={styles.infoCopy} themeColor="textSecondary">
                  This entry will not create a debt or affect any Ovezi balance.
                </ThemedText>
              </ThemedView>
            )}

            {visibleError ? <ThemedText themeColor="danger">{visibleError}</ThemedText> : null}
            <PrimaryButton
              disabled={destination === 'group' && groupQuery.isLoading}
              label="Save expense"
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
        { backgroundColor: active ? theme.primary : theme.backgroundElement, borderColor: active ? theme.primary : theme.border },
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
  sectionLabel: { fontSize: 14, lineHeight: 20, fontWeight: '800', marginTop: 4 },
  chipRow: { flexDirection: 'row', gap: 8, paddingRight: 8 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  segmentRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { minHeight: 40, borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, alignItems: 'center', justifyContent: 'center' },
  chipLabel: { fontSize: 13, fontWeight: '800' },
  amountRow: { flexDirection: 'row', gap: 10 },
  amountField: { flex: 1 },
  currencyField: { width: 106 },
  participantCard: { borderRadius: 20, paddingHorizontal: 15 },
  participantRow: { minHeight: 64, flexDirection: 'row', alignItems: 'center', gap: 11 },
  participantName: { flex: 1, fontSize: 14, fontWeight: '700' },
  checkbox: { width: 24, height: 24, borderRadius: 8, borderWidth: 1.5, alignItems: 'center', justifyContent: 'center' },
  check: { color: '#061A14', fontSize: 14, fontWeight: '900' },
  divider: { height: StyleSheet.hairlineWidth },
  valueWrap: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  valueInput: { width: 82, height: 40, borderWidth: 1, borderRadius: 12, paddingHorizontal: 10, textAlign: 'right' },
  infoCard: { borderRadius: 18, padding: 16, gap: 3 },
  guestCard: { borderRadius: 20, padding: 16, gap: 14 },
  infoTitle: { fontWeight: '800' },
  infoCopy: { fontSize: 13, lineHeight: 19 },
  helper: { fontSize: 13, lineHeight: 19 },
});
