import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
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
import { CurrencyPicker } from '@/components/currency-picker';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { ApiError, errorMessage } from '@/lib/api-client';
import {
  createExpense,
  fetchExpense,
  type CreateExpenseInput,
  type ExpenseParticipantInput,
  type UpdateExpenseInput,
  updateExpense,
} from '@/lib/expenses-api';
import { currencyFractionDigits, minorAmountInput, parseDecimalToInteger } from '@/lib/format';
import { fetchFriends } from '@/lib/friends-api';
import { fetchGroup, fetchGroups } from '@/lib/groups-api';
import { useTheme } from '@/hooks/use-theme';
import { createPlaceholder, fetchPlaceholders } from '@/lib/placeholders-api';
import { useAuthStore } from '@/stores/auth-store';
import {
  type ExpenseDraftParticipant,
  useExpenseDraftStore,
} from '@/stores/expense-draft-store';
import type { GroupMember, SplitType } from '@/types/api';

type ParticipantDraft = ExpenseDraftParticipant;

type Destination = 'personal' | 'direct' | 'group';

const splitOptions: { value: SplitType; label: string }[] = [
  { value: 'equal', label: 'Equal' },
  { value: 'exact', label: 'Exact' },
  { value: 'percentage', label: '%' },
  { value: 'shares', label: 'Shares' },
];

const categories = ['Food', 'Transport', 'Home', 'Travel', 'Other'];

function dateInputValue(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function occurrenceISOString(value: string) {
  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!match) return null;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const date = new Date(year, month - 1, day, 12);
  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null;
  }

  return date.toISOString();
}

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
  const params = useLocalSearchParams<{
    expenseId?: string | string[];
    friendId?: string | string[];
    groupId?: string | string[];
  }>();
  const rawGroupId = Array.isArray(params.groupId) ? params.groupId[0] : params.groupId;
  const rawExpenseId = Array.isArray(params.expenseId) ? params.expenseId[0] : params.expenseId;
  const rawFriendId = Array.isArray(params.friendId) ? params.friendId[0] : params.friendId;
  const initialGroupId = Number(rawGroupId);
  const expenseId = Number(rawExpenseId);
  const initialFriendId = Number(rawFriendId);
  const hasInitialGroup = Number.isInteger(initialGroupId) && initialGroupId > 0;
  const isEditing = Number.isInteger(expenseId) && expenseId > 0;
  const hasInitialFriend = Number.isInteger(initialFriendId) && initialFriendId > 0;
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const savedDraft = useExpenseDraftStore((state) => state.draft);
  const draftHydrated = useExpenseDraftStore((state) => state.hydrated);
  const saveDraft = useExpenseDraftStore((state) => state.saveDraft);
  const clearDraft = useExpenseDraftStore((state) => state.clearDraft);
  const theme = useTheme();
  const queryClient = useQueryClient();
  const [destination, setDestination] = useState<Destination>(
    hasInitialGroup ? 'group' : hasInitialFriend ? 'direct' : 'personal',
  );
  const [selectedGroupId, setSelectedGroupId] = useState<number | null>(
    hasInitialGroup ? initialGroupId : null,
  );
  const [selectedPlaceholderId, setSelectedPlaceholderId] = useState<number | null>(null);
  const [selectedFriendId, setSelectedFriendId] = useState<number | null>(
    hasInitialFriend ? initialFriendId : null,
  );
  const [showGuestForm, setShowGuestForm] = useState(false);
  const [guestName, setGuestName] = useState('');
  const [guestContactType, setGuestContactType] = useState<'email' | 'phone'>('email');
  const [guestContactValue, setGuestContactValue] = useState('');
  const [description, setDescription] = useState('');
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState(user.default_currency_code);
  const [category, setCategory] = useState('');
  const [occurredOn, setOccurredOn] = useState(dateInputValue(new Date()));
  const [splitType, setSplitType] = useState<SplitType>('equal');
  const [participantOverrides, setParticipantOverrides] = useState<ParticipantDraft[] | null>(null);
  const [payerKey, setPayerKey] = useState('');
  const [currencyTouched, setCurrencyTouched] = useState(false);
  const [expenseRate, setExpenseRate] = useState('');
  const [recalculateRate, setRecalculateRate] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [duplicateInput, setDuplicateInput] = useState<CreateExpenseInput | null>(null);
  const [draftDecisionMade, setDraftDecisionMade] = useState(false);
  const initializedExpenseId = useRef<number | null>(null);

  const expenseQuery = useQuery({
    queryKey: ['expense', expenseId],
    queryFn: () => fetchExpense(token, expenseId),
    enabled: isEditing,
  });
  const editingExpense = expenseQuery.data;

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
  const friendsQuery = useQuery({
    queryKey: ['friends'],
    queryFn: () => fetchFriends(token),
  });
  const group = groupQuery.data;
  const allPlaceholders = placeholdersQuery.data?.data ?? [];
  const placeholders = allPlaceholders.filter((item) => !item.is_claimed);
  const selectedPlaceholder = allPlaceholders.find((item) => item.id === selectedPlaceholderId);
  const friends = (friendsQuery.data ?? []).filter((item) => item.status === 'accepted');
  const selectedFriend = friends.find((item) => item.friend.id === selectedFriendId)?.friend;
  let defaultParticipants: ParticipantDraft[] = [];
  if (destination === 'group') defaultParticipants = (group?.members ?? []).map(memberDraft);
  if (destination === 'direct' && (selectedPlaceholder || selectedFriend)) {
    defaultParticipants = [
      {
        key: `user:${user.id}`,
        name: user.name,
        userId: user.id,
        selected: true,
        value: '',
      },
      selectedFriend
        ? {
            key: `user:${selectedFriend.id}`,
            name: selectedFriend.name,
            userId: selectedFriend.id,
            selected: true,
            value: '',
          }
        : {
            key: `placeholder:${selectedPlaceholder!.id}`,
            name: selectedPlaceholder!.name,
            placeholderId: selectedPlaceholder!.id,
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
  const reportingCurrency = editingExpense?.reporting_currency_code
    ?? group?.reporting_currency_code
    ?? user.default_currency_code;
  const rateNeeded = Boolean(
    destination !== 'personal'
      && effectiveCurrency.trim().toUpperCase() !== reportingCurrency,
  );

  useEffect(() => {
    if (!editingExpense || initializedExpenseId.current === editingExpense.id) return;

    initializedExpenseId.current = editingExpense.id;
    setDestination(editingExpense.expense_type);
    setSelectedGroupId(editingExpense.group_id);
    setDescription(editingExpense.description);
    setAmount(minorAmountInput(editingExpense.amount_minor, editingExpense.currency_code));
    setCurrency(editingExpense.currency_code);
    setCurrencyTouched(true);
    setCategory(
      categories.find((item) => item.toLowerCase() === editingExpense.category) ?? '',
    );
    setOccurredOn(dateInputValue(new Date(editingExpense.occurred_at)));
    const existingSplitType = editingExpense.splits[0]?.split_type ?? 'equal';
    setSplitType(existingSplitType);
    setParticipantOverrides(editingExpense.splits.map((split) => ({
      key: split.user_id ? `user:${split.user_id}` : `placeholder:${split.placeholder_id}`,
      name: split.name ?? 'Unknown',
      ...(split.user_id ? { userId: split.user_id } : {}),
      ...(split.placeholder_id ? { placeholderId: split.placeholder_id } : {}),
      selected: true,
      value: split.split_value === null
        ? ''
        : existingSplitType === 'exact'
          ? minorAmountInput(Number(split.split_value), editingExpense.currency_code)
          : existingSplitType === 'percentage'
            ? String(Number(split.split_value) / 100)
            : String(Number(split.split_value)),
    })));
    setPayerKey(
      editingExpense.payer.user_id
        ? `user:${editingExpense.payer.user_id}`
        : `placeholder:${editingExpense.payer.placeholder_id}`,
    );
    if (editingExpense.expense_type === 'direct') {
      const otherUser = editingExpense.splits.find(
        (split) => split.user_id !== null && split.user_id !== user.id,
      );
      const otherPlaceholder = editingExpense.splits.find((split) => split.placeholder_id !== null);
      setSelectedFriendId(otherUser?.user_id ?? null);
      setSelectedPlaceholderId(otherPlaceholder?.placeholder_id ?? null);
    }
  }, [editingExpense]);

  useEffect(() => {
    if (!draftHydrated || draftDecisionMade) return;

    if (isEditing || !savedDraft) {
      setDraftDecisionMade(true);
      return;
    }

    if (savedDraft.userId !== user.id) {
      clearDraft();
      setDraftDecisionMade(true);
    }
  }, [clearDraft, draftDecisionMade, draftHydrated, isEditing, savedDraft, user.id]);

  useEffect(() => {
    if (isEditing || !draftHydrated || !draftDecisionMade) return;

    const meaningful = Boolean(
      description.trim()
        || amount.trim()
        || category
        || selectedGroupId
        || selectedPlaceholderId
        || selectedFriendId
        || participantOverrides,
    );
    const timer = setTimeout(() => {
      if (!meaningful) {
        clearDraft();
        return;
      }

      saveDraft({
        userId: user.id,
        destination,
        selectedGroupId,
        selectedPlaceholderId,
        selectedFriendId,
        description,
        amount,
        currency: effectiveCurrency,
        category,
        occurredOn,
        splitType,
        participants: participantOverrides,
        payerKey,
        expenseRate,
        recalculateRate,
        savedAt: new Date().toISOString(),
      });
    }, 400);

    return () => clearTimeout(timer);
  }, [
    amount,
    category,
    clearDraft,
    description,
    destination,
    draftDecisionMade,
    draftHydrated,
    effectiveCurrency,
    expenseRate,
    isEditing,
    occurredOn,
    participantOverrides,
    payerKey,
    recalculateRate,
    saveDraft,
    selectedFriendId,
    selectedGroupId,
    selectedPlaceholderId,
    splitType,
    user.id,
  ]);

  function resumeDraft() {
    if (!savedDraft || savedDraft.userId !== user.id) return;

    setDestination(savedDraft.destination);
    setSelectedGroupId(savedDraft.selectedGroupId);
    setSelectedPlaceholderId(savedDraft.selectedPlaceholderId);
    setSelectedFriendId(savedDraft.selectedFriendId);
    setDescription(savedDraft.description);
    setAmount(savedDraft.amount);
    setCurrency(savedDraft.currency);
    setCurrencyTouched(true);
    setCategory(savedDraft.category);
    setOccurredOn(savedDraft.occurredOn);
    setSplitType(savedDraft.splitType);
    setParticipantOverrides(savedDraft.participants);
    setPayerKey(savedDraft.payerKey);
    setExpenseRate(savedDraft.expenseRate);
    setRecalculateRate(savedDraft.recalculateRate);
    setDraftDecisionMade(true);
  }

  function discardDraft() {
    clearDraft();
    setDraftDecisionMade(true);
  }

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
    mutationFn: (input: UpdateExpenseInput) => isEditing
      ? updateExpense(token, expenseId, input)
      : createExpense(token, input),
    onSuccess: async (expense) => {
      if (!isEditing) clearDraft();
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
        queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
      ]);
      if (isEditing) {
        queryClient.setQueryData(['expense', expense.id], expense);
        router.replace({ pathname: '/(app)/expenses/[id]', params: { id: expense.id } });
      } else if (expense.group_id) {
        router.replace({ pathname: '/(app)/groups/[id]', params: { id: expense.group_id } });
      } else {
        router.back();
      }
    },
    onError: (error, input) => {
      if (!isEditing && error instanceof ApiError && error.status === 409 && error.errors.duplicate) {
        setDuplicateInput(input as CreateExpenseInput);
      }
    },
  });

  function selectDestination(nextDestination: Destination, groupId: number | null = null) {
    setDestination(nextDestination);
    setSelectedGroupId(groupId);
    setSelectedPlaceholderId(null);
    setSelectedFriendId(null);
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
    setSelectedFriendId(null);
    setParticipantOverrides(null);
    setPayerKey('');
    setFormError(null);
  }

  function selectFriend(friendId: number) {
    setSelectedFriendId(friendId);
    setSelectedPlaceholderId(null);
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
    setDuplicateInput(null);
    const currencyCode = effectiveCurrency.trim().toUpperCase();
    const fractionDigits = currencyFractionDigits(currencyCode);
    const amountMinor = parseDecimalToInteger(amount, fractionDigits);
    const occurredAt = occurrenceISOString(occurredOn);
    if (!description.trim()) return setFormError('Enter what the expense was for.');
    if (currencyCode.length !== 3) return setFormError('Enter a valid three-letter currency code.');
    if (!amountMinor || amountMinor < 1) return setFormError('Enter a valid amount.');
    if (!occurredAt) return setFormError('Enter a valid date in YYYY-MM-DD format.');

    if (destination === 'personal') {
      mutation.mutate({
        expense_type: 'personal',
        payer_user_id: user.id,
        amount_minor: amountMinor,
        currency_code: currencyCode,
        description: description.trim(),
        ...(category ? { category: category.toLowerCase() } : {}),
        occurred_at: occurredAt,
        ...(isEditing ? { recalculate_rate: recalculateRate } : {}),
      });
      return;
    }

    if (destination === 'group' && !group) return setFormError('The group is still loading.');
    if (destination === 'direct' && !selectedPlaceholder && !selectedFriend) {
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
      occurred_at: occurredAt,
      split_type: splitType,
      participants: participantInput,
      ...(rateNeeded && expenseRate.trim() ? { expense_rate: expenseRate.trim() } : {}),
      ...(isEditing ? {
        recalculate_rate: recalculateRate
          || effectiveCurrency.trim().toUpperCase() !== editingExpense?.currency_code,
      } : {}),
    });
  }

  const visibleError = formError
    ?? (placeholderMutation.error ? errorMessage(placeholderMutation.error) : null)
    ?? (friendsQuery.error ? errorMessage(friendsQuery.error) : null)
    ?? (mutation.error && !duplicateInput ? errorMessage(mutation.error) : null);

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="textSecondary">Cancel</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>{isEditing ? 'Edit expense' : 'Add expense'}</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            {!isEditing && draftHydrated && savedDraft?.userId === user.id && !draftDecisionMade ? (
              <ThemedView type="backgroundSelected" style={styles.draftCard}>
                <View style={styles.draftCopy}>
                  <ThemedText style={styles.infoTitle}>Resume saved expense?</ThemedText>
                  <ThemedText style={styles.infoCopy} themeColor="textSecondary">
                    Saved {new Date(savedDraft.savedAt).toLocaleString('en', {
                      month: 'short',
                      day: 'numeric',
                      hour: 'numeric',
                      minute: '2-digit',
                    })}
                  </ThemedText>
                </View>
                <View style={styles.draftActions}>
                  <Pressable onPress={discardDraft} style={styles.draftAction}>
                    <ThemedText style={styles.helper} themeColor="textSecondary">Discard</ThemedText>
                  </Pressable>
                  <Pressable onPress={resumeDraft} style={styles.draftAction}>
                    <ThemedText style={styles.helper} themeColor="primary">Resume</ThemedText>
                  </Pressable>
                </View>
              </ThemedView>
            ) : null}
            {duplicateInput ? (
              <ThemedView type="backgroundSelected" style={styles.duplicateCard}>
                <View style={styles.draftCopy}>
                  <ThemedText style={styles.infoTitle}>Possible duplicate</ThemedText>
                  <ThemedText style={styles.infoCopy} themeColor="textSecondary">
                    You already added an expense with the same date, amount, currency, description,
                    payer, and participants.
                  </ThemedText>
                </View>
                <View style={styles.duplicateActions}>
                  <Pressable
                    onPress={() => {
                      setDuplicateInput(null);
                      mutation.reset();
                    }}
                    style={styles.duplicateAction}>
                    <ThemedText style={styles.helper} themeColor="textSecondary">Cancel</ThemedText>
                  </Pressable>
                  <Pressable
                    onPress={() => {
                      const input = duplicateInput;
                      setDuplicateInput(null);
                      mutation.mutate({ ...input, confirmed_duplicate: true });
                    }}
                    style={styles.duplicateAction}>
                    <ThemedText style={styles.helper} themeColor="primary">Add anyway</ThemedText>
                  </Pressable>
                </View>
              </ThemedView>
            ) : null}
            {isEditing && expenseQuery.isLoading ? (
              <ThemedText themeColor="textSecondary">Loading expense…</ThemedText>
            ) : null}
            <SectionLabel label="Where" />
            {isEditing ? (
              <ThemedView type="backgroundSelected" style={styles.infoCard}>
                <ThemedText style={styles.infoTitle}>
                  {destination === 'group'
                    ? group?.name ?? 'Group expense'
                    : destination === 'direct' ? '1-on-1' : 'Just for me'}
                </ThemedText>
                <ThemedText style={styles.infoCopy} themeColor="textSecondary">
                  Expense type and group stay fixed when editing.
                </ThemedText>
              </ThemedView>
            ) : (
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
            )}

            <FormField
              autoCapitalize="sentences"
              label="Description"
              onChangeText={setDescription}
              placeholder="Dinner, taxi, groceries…"
              value={description}
            />
            <FormField
              keyboardType="decimal-pad"
              label="Amount"
              onChangeText={setAmount}
              placeholder="0.00"
              value={amount}
            />
            <CurrencyPicker
              label="Currency"
              onChange={(value) => {
                setCurrencyTouched(true);
                setCurrency(value);
              }}
              value={effectiveCurrency}
            />

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

            <FormField
              autoCapitalize="none"
              keyboardType="numbers-and-punctuation"
              label="Date"
              maxLength={10}
              onChangeText={setOccurredOn}
              placeholder="YYYY-MM-DD"
              value={occurredOn}
            />

            {destination === 'direct' ? (
              <>
                <SectionLabel label="With" />
                {placeholdersQuery.isLoading || friendsQuery.isLoading ? (
                  <ThemedText themeColor="textSecondary">Loading people…</ThemedText>
                ) : null}
                <View style={styles.chipWrap}>
                  {friends.map((friendship) => (
                    <ChoiceChip
                      active={selectedFriendId === friendship.friend.id}
                      key={`friend:${friendship.friend.id}`}
                      label={friendship.friend.name}
                      onPress={() => selectFriend(friendship.friend.id)}
                    />
                  ))}
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
                {!placeholdersQuery.isLoading && !friendsQuery.isLoading && friends.length === 0 && placeholders.length === 0 && !showGuestForm ? (
                  <ThemedText style={styles.helper} themeColor="textSecondary">
                    Add a friend from Profile, or create a placeholder for someone who does not have an account yet.
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
                  <>
                    <FormField
                      keyboardType="decimal-pad"
                      label={`Rate · 1 ${effectiveCurrency.trim().toUpperCase()} in ${reportingCurrency} (optional)`}
                      onChangeText={(value) => {
                        setExpenseRate(value);
                        if (isEditing && value.trim()) setRecalculateRate(true);
                      }}
                      placeholder={isEditing ? 'Keep captured rate' : 'Use saved/default rate'}
                      value={expenseRate}
                    />
                    {isEditing ? (
                      <ChoiceChip
                        active={recalculateRate}
                        label="Recalculate conversion rate"
                        onPress={() => setRecalculateRate((current) => !current)}
                      />
                    ) : null}
                  </>
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
              disabled={(destination === 'group' && groupQuery.isLoading) || (isEditing && expenseQuery.isLoading)}
              label={isEditing ? 'Save changes' : 'Save expense'}
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
  draftCard: { borderRadius: 18, padding: 15, flexDirection: 'row', alignItems: 'center', gap: 12 },
  draftCopy: { flex: 1, gap: 2 },
  draftActions: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  draftAction: { minHeight: 40, justifyContent: 'center', paddingHorizontal: 9 },
  duplicateCard: { borderRadius: 18, padding: 15, gap: 12 },
  duplicateActions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: 4 },
  duplicateAction: { minHeight: 40, justifyContent: 'center', paddingHorizontal: 9 },
});
