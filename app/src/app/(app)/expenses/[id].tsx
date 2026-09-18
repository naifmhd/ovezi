import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { deleteExpense, fetchExpense, restoreExpense } from '@/lib/expenses-api';
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

  const mutationError = deleteMutation.error ?? restoreMutation.error;

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
          {deletedAt === null && error ? <ThemedText themeColor="danger">{errorMessage(error)}</ThemedText> : null}
          {deletedAt === null && mutationError ? (
            <ThemedText themeColor="danger">{errorMessage(mutationError)}</ThemedText>
          ) : null}
          {deletedAt === null && expense ? (
            <ExpenseContent
              currentUserId={user.id}
              expense={expense}
              groupName={groupQuery.data?.name}
            />
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
  currentUserId,
  expense,
  groupName,
}: {
  currentUserId: number;
  expense: Expense;
  groupName?: string;
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
  hero: { borderRadius: 24, padding: 22, alignItems: 'center', gap: 4 },
  eyebrow: { fontSize: 12, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.8 },
  description: { fontSize: 20, lineHeight: 28, fontWeight: '800', textAlign: 'center' },
  amount: { fontSize: 36, lineHeight: 44, fontWeight: '900', marginVertical: 4 },
  impactPill: { borderRadius: 14, paddingHorizontal: 13, paddingVertical: 8, marginTop: 4 },
  impactText: { fontSize: 13, fontWeight: '800' },
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
