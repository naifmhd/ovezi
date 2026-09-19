import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { formatMoney } from '@/lib/format';
import {
  cancelRecurringExpense,
  fetchRecurringExpenses,
  pauseRecurringExpense,
  resumeRecurringExpense,
} from '@/lib/recurring-expenses-api';
import { useAuthStore } from '@/stores/auth-store';
import type { RecurringExpense } from '@/types/api';

type ScheduleAction = { id: number; action: 'pause' | 'resume' | 'cancel' };

export default function RecurringExpensesScreen() {
  const token = useAuthStore((state) => state.token)!;
  const queryClient = useQueryClient();
  const schedulesQuery = useQuery({
    queryKey: ['recurring-expenses'],
    queryFn: () => fetchRecurringExpenses(token),
  });
  const actionMutation = useMutation({
    mutationFn: async ({ id, action }: ScheduleAction) => {
      if (action === 'pause') await pauseRecurringExpense(token, id);
      if (action === 'resume') await resumeRecurringExpense(token, id);
      if (action === 'cancel') await cancelRecurringExpense(token, id);
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['recurring-expenses'] }),
        queryClient.invalidateQueries({ queryKey: ['activity'] }),
      ]);
    },
  });
  const schedules = schedulesQuery.data?.data ?? [];

  function confirmCancel(schedule: RecurringExpense) {
    Alert.alert(
      'Cancel recurring expense?',
      `No new expenses will be created for ${schedule.description}. Existing expenses stay unchanged.`,
      [
        { text: 'Keep', style: 'cancel' },
        {
          text: 'Cancel schedule',
          style: 'destructive',
          onPress: () => actionMutation.mutate({ id: schedule.id, action: 'cancel' }),
        },
      ],
    );
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Recurring expenses</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={(
            <RefreshControl
              refreshing={schedulesQuery.isRefetching}
              onRefresh={() => void schedulesQuery.refetch()}
            />
          )}
          showsVerticalScrollIndicator={false}>
          <ThemedView type="backgroundSelected" style={styles.infoCard}>
            <ThemedText style={styles.cardTitle}>Future expenses stay predictable</ThemedText>
            <ThemedText style={styles.copy} themeColor="textSecondary">
              Editing a schedule changes future occurrences only. Expenses already created keep their original details and exchange rate.
            </ThemedText>
          </ThemedView>

          {schedulesQuery.error ? (
            <QueryErrorCard
              error={schedulesQuery.error}
              onRetry={() => void schedulesQuery.refetch()}
              retrying={schedulesQuery.isRefetching}
            />
          ) : null}
          {actionMutation.error ? (
            <ThemedText themeColor="danger">{errorMessage(actionMutation.error)}</ThemedText>
          ) : null}
          {schedulesQuery.isLoading ? (
            <ThemedText style={styles.centered} themeColor="textSecondary">Loading schedules…</ThemedText>
          ) : null}

          {schedules.map((schedule) => (
            <ThemedView key={schedule.id} type="backgroundElement" style={styles.scheduleCard}>
              <View style={styles.headingRow}>
                <View style={styles.headingCopy}>
                  <ThemedText style={styles.cardTitle}>{schedule.description}</ThemedText>
                  <ThemedText style={styles.amount}>
                    {formatMoney(schedule.amount_minor, schedule.currency_code)}
                  </ThemedText>
                </View>
                <ThemedView type="backgroundSelected" style={styles.statusPill}>
                  <ThemedText
                    style={styles.statusText}
                    themeColor={schedule.status === 'active' ? 'primary' : 'textSecondary'}>
                    {capitalize(schedule.status)}
                  </ThemedText>
                </ThemedView>
              </View>
              <ThemedText style={styles.copy} themeColor="textSecondary">
                {capitalize(schedule.frequency)} · {schedule.expense_type === 'personal'
                  ? 'Just for me'
                  : schedule.expense_type === 'direct' ? '1-on-1' : 'Group'}
              </ThemedText>
              <ThemedText style={styles.copy} themeColor="textSecondary">
                {schedule.next_occurrence_on
                  ? `Next: ${formatDate(schedule.next_occurrence_on)}`
                  : schedule.status === 'completed' ? 'Schedule completed' : 'No future occurrence'}
                {schedule.ends_on ? ` · Ends ${formatDate(schedule.ends_on)}` : ''}
              </ThemedText>
              {schedule.can_manage && schedule.status !== 'canceled' && schedule.status !== 'completed' ? (
                <View style={styles.actions}>
                  <Pressable
                    disabled={actionMutation.isPending}
                    onPress={() => actionMutation.mutate({
                      id: schedule.id,
                      action: schedule.status === 'paused' ? 'resume' : 'pause',
                    })}
                    style={styles.action}>
                    <ThemedText style={styles.actionText} themeColor="primary">
                      {schedule.status === 'paused' ? 'Resume' : 'Pause'}
                    </ThemedText>
                  </Pressable>
                  <Pressable
                    onPress={() => router.push({
                      pathname: '/(app)/profile/recurring-expenses/[id]',
                      params: { id: schedule.id },
                    })}
                    style={styles.action}>
                    <ThemedText style={styles.actionText} themeColor="primary">Edit future</ThemedText>
                  </Pressable>
                  <Pressable onPress={() => confirmCancel(schedule)} style={styles.action}>
                    <ThemedText style={styles.actionText} themeColor="danger">Cancel</ThemedText>
                  </Pressable>
                </View>
              ) : null}
            </ThemedView>
          ))}

          {!schedulesQuery.isLoading && !schedulesQuery.error && schedules.length === 0 ? (
            <ThemedView type="backgroundElement" style={styles.emptyCard}>
              <ThemedText style={styles.cardTitle}>No recurring expenses yet</ThemedText>
              <ThemedText style={styles.copy} themeColor="textSecondary">
                Choose a repeat interval when adding an expense to create one.
              </ThemedText>
              <Pressable onPress={() => router.push('/(app)/expenses/create')} style={styles.emptyAction}>
                <ThemedText style={styles.actionText} themeColor="primary">Add expense</ThemedText>
              </Pressable>
            </ThemedView>
          ) : null}
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

function capitalize(value: string) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function formatDate(value: string) {
  return new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 14, maxWidth: 680, width: '100%', alignSelf: 'center' },
  infoCard: { borderRadius: 20, padding: 17, gap: 5 },
  copy: { fontSize: 13, lineHeight: 19 },
  centered: { textAlign: 'center', paddingVertical: 40 },
  scheduleCard: { borderRadius: 22, padding: 17, gap: 6 },
  headingRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  headingCopy: { flex: 1, gap: 2 },
  cardTitle: { fontSize: 16, lineHeight: 22, fontWeight: '900' },
  amount: { fontSize: 19, lineHeight: 25, fontWeight: '900' },
  statusPill: { borderRadius: 12, paddingHorizontal: 10, paddingVertical: 7 },
  statusText: { fontSize: 11, fontWeight: '900' },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 2, marginTop: 7 },
  action: { minHeight: 42, justifyContent: 'center', paddingHorizontal: 10 },
  actionText: { fontSize: 13, fontWeight: '900' },
  emptyCard: { borderRadius: 22, padding: 20, gap: 6 },
  emptyAction: { minHeight: 44, justifyContent: 'center', alignSelf: 'flex-start' },
});
