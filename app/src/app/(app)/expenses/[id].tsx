import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import * as ImagePicker from 'expo-image-picker';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { QueryErrorCard } from '@/components/query-error-card';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import {
  deleteExpense,
  deleteExpenseReceipt,
  fetchExpense,
  restoreExpense,
  uploadExpenseReceipt,
} from '@/lib/expenses-api';
import { formatMoney } from '@/lib/format';
import { fetchGroup } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';
import type { Expense, ExpenseSplit } from '@/types/api';

function firstParam(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

export default function ExpenseDetailScreen() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const expenseId = Number(firstParam(params.id));
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const queryClient = useQueryClient();
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deletedAt, setDeletedAt] = useState<number | null>(null);
  const [undoSeconds, setUndoSeconds] = useState(30);
  const [receiptSelectionError, setReceiptSelectionError] = useState<string | null>(null);
  const [receiptVersion, setReceiptVersion] = useState(0);
  const validExpenseId = Number.isInteger(expenseId) && expenseId > 0;
  const expenseQuery = useQuery({
    queryKey: ['expense', expenseId],
    queryFn: () => fetchExpense(token, expenseId),
    enabled: validExpenseId,
  });
  const expense = expenseQuery.data;
  const groupQuery = useQuery({
    queryKey: ['group', expense?.group_id],
    queryFn: () => fetchGroup(token, expense!.group_id!),
    enabled: Boolean(expense?.group_id),
  });
  const error = expenseQuery.error ?? groupQuery.error;
  const isGroupOwner = groupQuery.data?.members?.some(
    (member) => member.user?.id === user.id && member.role === 'owner',
  ) ?? false;
  const canManage = expense?.created_by === user.id || Boolean(expense?.group_id && isGroupOwner);
  const deleteMutation = useMutation({
    mutationFn: () => deleteExpense(token, expenseId),
    onSuccess: async () => {
      setConfirmingDelete(false);
      setDeletedAt(Date.now());
      setUndoSeconds(30);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
        queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
      ]);
    },
  });
  const restoreMutation = useMutation({
    mutationFn: () => restoreExpense(token, expenseId),
    onSuccess: async (restoredExpense) => {
      setDeletedAt(null);
      setUndoSeconds(30);
      queryClient.setQueryData(['expense', expenseId], restoredExpense);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
        queryClient.invalidateQueries({ queryKey: ['group-balances'] }),
        queryClient.invalidateQueries({ queryKey: ['dashboard-balances'] }),
      ]);
    },
  });
  const receiptDeleteMutation = useMutation({
    mutationFn: () => deleteExpenseReceipt(token, expenseId),
    onSuccess: async () => {
      setReceiptVersion(Date.now());
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['expense', expenseId] }),
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
      ]);
    },
  });
  const receiptUploadMutation = useMutation({
    mutationFn: (receipt: ImagePicker.ImagePickerAsset) => uploadExpenseReceipt(
      token,
      expenseId,
      receipt,
    ),
    onSuccess: async (updatedExpense) => {
      setReceiptSelectionError(null);
      setReceiptVersion(Date.now());
      queryClient.setQueryData(['expense', expenseId], updatedExpense);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
      ]);
    },
  });

  async function selectReceipt(source: 'camera' | 'library') {
    setReceiptSelectionError(null);
    receiptUploadMutation.reset();

    try {
      const permission = source === 'camera'
        ? await ImagePicker.requestCameraPermissionsAsync()
        : await ImagePicker.requestMediaLibraryPermissionsAsync();

      if (!permission.granted) {
        setReceiptSelectionError(
          source === 'camera'
            ? 'Camera access is needed to photograph a receipt. You can enable it in device settings.'
            : 'Photo access is needed to choose a receipt. You can enable it in device settings.',
        );
        return;
      }

      const result = source === 'camera'
        ? await ImagePicker.launchCameraAsync({
            allowsEditing: false,
            mediaTypes: ['images'],
            quality: 0.85,
          })
        : await ImagePicker.launchImageLibraryAsync({
            allowsEditing: false,
            mediaTypes: ['images'],
            quality: 0.85,
          });

      if (result.canceled) return;

      const receipt = result.assets[0];
      if (receipt.fileSize !== undefined && receipt.fileSize > 10 * 1024 * 1024) {
        setReceiptSelectionError('Receipt images must be 10 MB or smaller.');
        return;
      }

      receiptUploadMutation.mutate(receipt);
    } catch {
      setReceiptSelectionError('Ovezi couldn’t open the image picker. Please try again.');
    }
  }

  useEffect(() => {
    if (deletedAt === null) return;

    const timer = setInterval(() => {
      const seconds = Math.max(0, 30 - Math.floor((Date.now() - deletedAt) / 1000));
      setUndoSeconds(seconds);
      if (seconds === 0) {
        clearInterval(timer);
        router.back();
      }
    }, 250);

    return () => clearInterval(timer);
  }, [deletedAt]);

  const mutationError = deleteMutation.error
    ?? restoreMutation.error
    ?? receiptDeleteMutation.error
    ?? receiptUploadMutation.error;

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Expense</ThemedText>
          {expense && canManage && deletedAt === null ? (
            <Pressable
              onPress={() => router.push({
                pathname: '/(app)/expenses/create',
                params: { expenseId: expense.id },
              })}>
              <ThemedText style={styles.headerAction} themeColor="primary">Edit</ThemedText>
            </Pressable>
          ) : <View style={styles.headerSpacer} />}
        </View>
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={(
            <RefreshControl
              refreshing={expenseQuery.isRefetching || groupQuery.isRefetching}
              onRefresh={() => {
                void expenseQuery.refetch();
                if (expense?.group_id) void groupQuery.refetch();
              }}
            />
          )}
          showsVerticalScrollIndicator={false}>
          {deletedAt !== null ? (
            <ThemedView type="backgroundElement" style={styles.deletedCard}>
              <ThemedText style={styles.deletedTitle}>Expense deleted</ThemedText>
              <ThemedText style={styles.deletedCopy} themeColor="textSecondary">
                You can undo this for {undoSeconds} second{undoSeconds === 1 ? '' : 's'}.
              </ThemedText>
              {mutationError ? <ThemedText themeColor="danger">{errorMessage(mutationError)}</ThemedText> : null}
              <Pressable
                disabled={restoreMutation.isPending || undoSeconds === 0}
                onPress={() => restoreMutation.mutate()}
                style={styles.undoButton}>
                <ThemedText style={styles.undoText} themeColor="primary">
                  {restoreMutation.isPending ? 'Restoring…' : 'Undo delete'}
                </ThemedText>
              </Pressable>
            </ThemedView>
          ) : null}
          {deletedAt === null && expenseQuery.isLoading ? (
            <ThemedText style={styles.centered} themeColor="textSecondary">Loading expense…</ThemedText>
          ) : null}
          {deletedAt === null && error ? (
            <QueryErrorCard
              error={error}
              onRetry={() => {
                void expenseQuery.refetch();
                if (expense?.group_id) void groupQuery.refetch();
              }}
              retrying={expenseQuery.isRefetching || groupQuery.isRefetching}
            />
          ) : null}
          {deletedAt === null && mutationError ? (
            <ThemedText themeColor="danger">{errorMessage(mutationError)}</ThemedText>
          ) : null}
          {deletedAt === null && expense ? (
            <>
              {expense.recurring_expense_id ? (
                <Pressable
                  onPress={() => router.push({
                    pathname: '/(app)/profile/recurring-expenses/[id]',
                    params: { id: expense.recurring_expense_id! },
                  })}>
                  <ThemedView type="backgroundSelected" style={styles.recurringCard}>
                    <View style={styles.recurringCopy}>
                      <ThemedText style={styles.recurringTitle}>Recurring expense</ThemedText>
                      <ThemedText style={styles.recurringText} themeColor="textSecondary">
                        This is one occurrence. Open the schedule to change future expenses.
                      </ThemedText>
                    </View>
                    <ThemedText style={styles.recurringChevron} themeColor="primary">›</ThemedText>
                  </ThemedView>
                </Pressable>
              ) : null}
              <ExpenseContent
                currentUserId={user.id}
                canManage={canManage}
                expense={expense}
                groupName={groupQuery.data?.name}
                onDeleteReceipt={() => receiptDeleteMutation.mutate()}
                onSelectReceipt={(source) => void selectReceipt(source)}
                receiptError={receiptSelectionError}
                receiptDeleting={receiptDeleteMutation.isPending}
                receiptUploading={receiptUploadMutation.isPending}
                receiptVersion={receiptVersion}
                token={token}
              />
            </>
          ) : null}
          {deletedAt === null && expense && canManage ? (
            <>
              {confirmingDelete ? (
                <ThemedView type="backgroundElement" style={styles.confirmCard}>
                  <ThemedText style={styles.confirmTitle}>Delete this expense?</ThemedText>
                  <ThemedText style={styles.deletedCopy} themeColor="textSecondary">
                    Balances will update immediately. You will have 30 seconds to undo.
                  </ThemedText>
                  <View style={styles.confirmActions}>
                    <Pressable onPress={() => setConfirmingDelete(false)} style={styles.confirmButton}>
                      <ThemedText style={styles.confirmAction} themeColor="textSecondary">Cancel</ThemedText>
                    </Pressable>
                    <Pressable
                      disabled={deleteMutation.isPending}
                      onPress={() => deleteMutation.mutate()}
                      style={styles.confirmButton}>
                      <ThemedText style={styles.confirmAction} themeColor="danger">
                        {deleteMutation.isPending ? 'Deleting…' : 'Delete'}
                      </ThemedText>
                    </Pressable>
                  </View>
                </ThemedView>
              ) : (
                <Pressable onPress={() => setConfirmingDelete(true)} style={styles.deleteButton}>
                  <ThemedText style={styles.deleteText} themeColor="danger">Delete expense</ThemedText>
                </Pressable>
              )}
            </>
          ) : null}
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

function ExpenseContent({
  canManage,
  currentUserId,
  expense,
  groupName,
  onDeleteReceipt,
  onSelectReceipt,
  receiptError,
  receiptDeleting,
  receiptUploading,
  receiptVersion,
  token,
}: {
  canManage: boolean;
  currentUserId: number;
  expense: Expense;
  groupName?: string;
  onDeleteReceipt: () => void;
  onSelectReceipt: (source: 'camera' | 'library') => void;
  receiptError: string | null;
  receiptDeleting: boolean;
  receiptUploading: boolean;
  receiptVersion: number;
  token: string;
}) {
  const ownSplit = expense.splits.find(
    (split) => (split.user_id ?? split.claimed_user_id) === currentUserId,
  );
  const paidByCurrentUser = (expense.payer.user_id ?? expense.payer.claimed_user_id) === currentUserId;
  const personalImpact = expense.expense_type === 'personal'
    ? 'Personal tracking entry · no balance impact'
    : paidByCurrentUser
      ? `You lent ${formatMoney(Math.max(0, expense.amount_minor - (ownSplit?.amount_owed_minor ?? 0)), expense.currency_code)}`
      : ownSplit
        ? `Your share is ${formatMoney(ownSplit.amount_owed_minor, expense.currency_code)}`
        : null;

  return (
    <>
      <ThemedView type="backgroundElement" style={styles.hero}>
        <ThemedText style={styles.eyebrow} themeColor="textSecondary">
          {expense.category ? expense.category.replaceAll('_', ' ') : expense.expense_type}
        </ThemedText>
        <ThemedText style={styles.description}>{expense.description}</ThemedText>
        <ThemedText style={styles.amount}>
          {formatMoney(expense.amount_minor, expense.currency_code)}
        </ThemedText>
        {personalImpact ? (
          <ThemedView type="backgroundSelected" style={styles.impactPill}>
            <ThemedText style={styles.impactText} themeColor="primary">{personalImpact}</ThemedText>
          </ThemedView>
        ) : null}
      </ThemedView>

      {expense.receipt_url || canManage ? (
        <>
          <SectionTitle title="Receipt" />
          <ThemedView type="backgroundElement" style={styles.receiptCard}>
            {expense.receipt_url ? (
              <Image
                accessibilityLabel={`Receipt for ${expense.description}`}
                cachePolicy="memory"
                contentFit="contain"
                source={{
                  uri: `${expense.receipt_url}?v=${encodeURIComponent(`${expense.updated_at}-${receiptVersion}`)}`,
                  headers: { Authorization: `Bearer ${token}` },
                }}
                style={styles.receiptImage}
                transition={180}
              />
            ) : (
              <View style={styles.receiptEmpty}>
                <ThemedText style={styles.receiptEmptyTitle}>No receipt attached</ThemedText>
                <ThemedText style={styles.receiptEmptyCopy} themeColor="textSecondary">
                  Add a photo for your records. Ovezi won’t scan or process it.
                </ThemedText>
              </View>
            )}
            {canManage ? (
              <View style={styles.receiptControls}>
                {receiptError ? (
                  <ThemedText style={styles.receiptError} themeColor="danger">{receiptError}</ThemedText>
                ) : null}
                <View style={styles.receiptActions}>
                  <Pressable
                    disabled={receiptUploading || receiptDeleting}
                    onPress={() => onSelectReceipt('camera')}
                    style={styles.receiptAction}>
                    <ThemedText style={styles.receiptActionText} themeColor="primary">
                      {receiptUploading ? 'Uploading…' : expense.receipt_url ? 'Retake photo' : 'Take photo'}
                    </ThemedText>
                  </Pressable>
                  <Pressable
                    disabled={receiptUploading || receiptDeleting}
                    onPress={() => onSelectReceipt('library')}
                    style={styles.receiptAction}>
                    <ThemedText style={styles.receiptActionText} themeColor="primary">
                      {expense.receipt_url ? 'Replace from photos' : 'Choose photo'}
                    </ThemedText>
                  </Pressable>
                </View>
                {expense.receipt_url ? (
                  <Pressable
                    disabled={receiptDeleting || receiptUploading}
                    onPress={onDeleteReceipt}
                    style={styles.receiptRemoveAction}>
                    <ThemedText style={styles.receiptActionText} themeColor="danger">
                      {receiptDeleting ? 'Removing…' : 'Remove receipt'}
                    </ThemedText>
                  </Pressable>
                ) : null}
              </View>
            ) : null}
          </ThemedView>
        </>
      ) : null}

      <SectionTitle title="Details" />
      <ThemedView type="backgroundElement" style={styles.detailCard}>
        <DetailRow label="Paid by" value={expense.payer.name ?? 'Unknown'} />
        <Divider />
        <DetailRow
          label="Date"
          value={new Date(expense.occurred_at).toLocaleDateString('en', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
          })}
        />
        <Divider />
        <DetailRow
          label="Type"
          value={expense.expense_type === 'group' ? groupName ?? 'Group expense' : expense.expense_type === 'direct' ? '1-on-1' : 'Just for me'}
        />
      </ThemedView>

      {expense.splits.length > 0 ? (
        <>
          <SectionTitle title={`Split · ${splitTypeLabel(expense.splits[0].split_type)}`} />
          <ThemedView type="backgroundElement" style={styles.detailCard}>
            {expense.splits.map((split, index) => (
              <View key={split.id}>
                {index > 0 ? <Divider /> : null}
                <View style={styles.splitRow}>
                  <View style={styles.splitCopy}>
                    <ThemedText style={styles.splitName}>
                      {(split.user_id ?? split.claimed_user_id) === currentUserId ? 'You' : split.name ?? 'Unknown'}
                    </ThemedText>
                    {splitValueLabel(split, expense) ? (
                      <ThemedText style={styles.splitValue} themeColor="textSecondary">
                        {splitValueLabel(split, expense)}
                      </ThemedText>
                    ) : null}
                  </View>
                  <ThemedText style={styles.splitAmount}>
                    {formatMoney(split.amount_owed_minor, expense.currency_code)}
                  </ThemedText>
                </View>
              </View>
            ))}
          </ThemedView>
        </>
      ) : null}

      {expense.currency_code !== expense.reporting_currency_code ? (
        <>
          <SectionTitle title="Conversion" />
          <ThemedView type="backgroundElement" style={styles.detailCard}>
            <DetailRow
              label="Reported amount"
              value={formatMoney(expense.reporting_amount_minor, expense.reporting_currency_code)}
            />
            <Divider />
            <DetailRow
              label="Captured rate"
              value={expense.exchange_rate
                ? `1 ${expense.currency_code} = ${Number(expense.exchange_rate).toLocaleString('en', { maximumFractionDigits: 12 })} ${expense.reporting_currency_code}`
                : 'Same currency'}
            />
            <Divider />
            <DetailRow label="Rate source" value={rateSourceLabel(expense.exchange_rate_source)} />
            {expense.exchange_rate_effective_date ? (
              <>
                <Divider />
                <DetailRow
                  label="Rate date"
                  value={new Date(`${expense.exchange_rate_effective_date}T00:00:00`).toLocaleDateString('en', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                  })}
                />
              </>
            ) : null}
          </ThemedView>
        </>
      ) : null}

      {expense.group_id ? (
        <Pressable
          onPress={() => router.push({ pathname: '/(app)/groups/[id]', params: { id: expense.group_id! } })}
          style={styles.groupLink}>
          <ThemedText style={styles.groupLinkText} themeColor="primary">
            View {groupName ?? 'group'}
          </ThemedText>
        </Pressable>
      ) : null}
    </>
  );
}

function splitTypeLabel(type: ExpenseSplit['split_type']) {
  return type === 'percentage' ? 'Percentage' : `${type.slice(0, 1).toUpperCase()}${type.slice(1)}`;
}

function splitValueLabel(split: ExpenseSplit, expense: Expense) {
  if (split.split_type === 'equal' || split.split_value === null) return null;
  if (split.split_type === 'exact') {
    return `Entered as ${formatMoney(Number(split.split_value), expense.currency_code)}`;
  }
  if (split.split_type === 'percentage') {
    return `${Number(split.split_value) / 100}%`;
  }
  return `${Number(split.split_value)} share${Number(split.split_value) === 1 ? '' : 's'}`;
}

function rateSourceLabel(source: string) {
  const labels: Record<string, string> = {
    expense: 'Expense-specific rate',
    group: 'Group override',
    provider: 'Default provider rate',
    same_currency: 'Same currency',
  };
  return labels[source] ?? source.replaceAll('_', ' ');
}

function SectionTitle({ title }: { title: string }) {
  return <ThemedText style={styles.sectionTitle}>{title}</ThemedText>;
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.detailRow}>
      <ThemedText style={styles.detailLabel} themeColor="textSecondary">{label}</ThemedText>
      <ThemedText style={styles.detailValue}>{value}</ThemedText>
    </View>
  );
}

function Divider() {
  return <View style={styles.divider} />;
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  headerAction: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 12, maxWidth: 680, width: '100%', alignSelf: 'center' },
  centered: { textAlign: 'center', paddingVertical: 50 },
  recurringCard: { borderRadius: 20, padding: 16, flexDirection: 'row', alignItems: 'center', gap: 12 },
  recurringCopy: { flex: 1, gap: 2 },
  recurringTitle: { fontSize: 14, fontWeight: '900' },
  recurringText: { fontSize: 12, lineHeight: 18 },
  recurringChevron: { fontSize: 25, fontWeight: '600' },
  hero: { borderRadius: 24, padding: 22, alignItems: 'center', gap: 4 },
  eyebrow: { fontSize: 12, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.8 },
  description: { fontSize: 20, lineHeight: 28, fontWeight: '800', textAlign: 'center' },
  amount: { fontSize: 36, lineHeight: 44, fontWeight: '900', marginVertical: 4 },
  impactPill: { borderRadius: 14, paddingHorizontal: 13, paddingVertical: 8, marginTop: 4 },
  impactText: { fontSize: 13, fontWeight: '800' },
  receiptCard: { borderRadius: 20, overflow: 'hidden' },
  receiptImage: { width: '100%', aspectRatio: 4 / 3 },
  receiptEmpty: { minHeight: 146, alignItems: 'center', justifyContent: 'center', gap: 5, padding: 20 },
  receiptEmptyTitle: { fontSize: 15, fontWeight: '800' },
  receiptEmptyCopy: { fontSize: 13, lineHeight: 18, textAlign: 'center' },
  receiptControls: { padding: 10, gap: 4 },
  receiptActions: { flexDirection: 'row', gap: 8 },
  receiptAction: { flex: 1, minHeight: 48, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
  receiptRemoveAction: { minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  receiptActionText: { fontSize: 14, fontWeight: '800' },
  receiptError: { fontSize: 13, lineHeight: 18, textAlign: 'center', padding: 6 },
  sectionTitle: { fontSize: 17, lineHeight: 24, fontWeight: '800', marginTop: 10 },
  detailCard: { borderRadius: 20, paddingHorizontal: 16 },
  detailRow: { minHeight: 58, flexDirection: 'row', alignItems: 'center', gap: 16 },
  detailLabel: { fontSize: 13, flex: 1 },
  detailValue: { fontSize: 13, fontWeight: '700', textAlign: 'right', flex: 2 },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.3 },
  splitRow: { minHeight: 64, flexDirection: 'row', alignItems: 'center', gap: 12 },
  splitCopy: { flex: 1, gap: 2 },
  splitName: { fontSize: 14, fontWeight: '800' },
  splitValue: { fontSize: 12 },
  splitAmount: { fontSize: 14, fontWeight: '800' },
  groupLink: { minHeight: 48, alignItems: 'center', justifyContent: 'center', marginTop: 6 },
  groupLinkText: { fontSize: 14, fontWeight: '800' },
  deleteButton: { minHeight: 48, alignItems: 'center', justifyContent: 'center', marginTop: 10 },
  deleteText: { fontSize: 14, fontWeight: '800' },
  confirmCard: { borderRadius: 20, padding: 18, gap: 8, marginTop: 8 },
  confirmTitle: { fontSize: 17, lineHeight: 23, fontWeight: '800' },
  confirmActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 12, marginTop: 6 },
  confirmButton: { minHeight: 42, justifyContent: 'center', paddingHorizontal: 12 },
  confirmAction: { fontSize: 14, fontWeight: '800' },
  deletedCard: { borderRadius: 24, padding: 22, gap: 9, marginTop: 20 },
  deletedTitle: { fontSize: 21, lineHeight: 28, fontWeight: '900' },
  deletedCopy: { fontSize: 14, lineHeight: 20 },
  undoButton: { minHeight: 48, alignItems: 'center', justifyContent: 'center', marginTop: 5 },
  undoText: { fontSize: 15, fontWeight: '900' },
});
