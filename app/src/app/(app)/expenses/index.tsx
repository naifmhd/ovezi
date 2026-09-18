import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ExpenseRow } from '@/components/expense-row';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { fetchExpenses } from '@/lib/expenses-api';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';
import type { ExpenseType } from '@/types/api';

type ExpenseFilter = 'all' | ExpenseType;

const filters: { value: ExpenseFilter; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'group', label: 'Groups' },
  { value: 'direct', label: '1-on-1' },
  { value: 'personal', label: 'Just for me' },
];

export default function ExpensesScreen() {
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const theme = useTheme();
  const [filter, setFilter] = useState<ExpenseFilter>('all');
  const query = useQuery({
    queryKey: ['expenses', 'ledger', filter],
    queryFn: () => fetchExpenses(token, {
      ...(filter === 'all' ? {} : { expenseType: filter }),
      perPage: 100,
    }),
  });
  const expenses = query.data?.data ?? [];

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.headerAction} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Expenses</ThemedText>
          <Pressable onPress={() => router.push('/(app)/expenses/create')}>
            <ThemedText style={styles.headerAction} themeColor="primary">+ Add</ThemedText>
          </Pressable>
        </View>
        <ScrollView
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />}
          showsVerticalScrollIndicator={false}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            <View style={styles.filterRow}>
              {filters.map((item) => {
                const selected = filter === item.value;
                return (
                  <Pressable
                    key={item.value}
                    onPress={() => setFilter(item.value)}
                    style={[
                      styles.filter,
                      {
                        backgroundColor: selected ? theme.primary : theme.backgroundElement,
                        borderColor: selected ? theme.primary : theme.border,
                      },
                    ]}>
                    <ThemedText style={[styles.filterLabel, selected && { color: theme.primaryText }]}>
                      {item.label}
                    </ThemedText>
                  </Pressable>
                );
              })}
            </View>
          </ScrollView>

          {query.error ? <ThemedText themeColor="danger">{errorMessage(query.error)}</ThemedText> : null}
          {query.isLoading ? (
            <ThemedText style={styles.centered} themeColor="textSecondary">Loading expenses…</ThemedText>
          ) : null}
          {expenses.map((expense) => (
            <ExpenseRow
              currentUserId={user.id}
              expense={expense}
              key={expense.id}
              onPress={() => router.push({ pathname: '/(app)/expenses/[id]', params: { id: expense.id } })}
            />
          ))}
          {!query.isLoading && expenses.length === 0 ? (
            <ThemedView type="backgroundElement" style={styles.emptyCard}>
              <ThemedText style={styles.emptyTitle}>No expenses here yet</ThemedText>
              <ThemedText style={styles.emptyCopy} themeColor="textSecondary">
                Add an expense or choose another filter.
              </ThemedText>
              <Pressable onPress={() => router.push('/(app)/expenses/create')}>
                <ThemedText style={styles.emptyAction} themeColor="primary">Add expense</ThemedText>
              </Pressable>
            </ThemedView>
          ) : null}
          {query.data?.meta && query.data.meta.total > expenses.length ? (
            <ThemedText style={styles.limitNote} themeColor="textSecondary">
              Showing the latest {expenses.length} of {query.data.meta.total} expenses.
            </ThemedText>
          ) : null}
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  headerAction: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 11, maxWidth: 720, width: '100%', alignSelf: 'center' },
  filterRow: { flexDirection: 'row', gap: 8, paddingRight: 8, marginBottom: 4 },
  filter: { height: 40, borderRadius: 14, borderWidth: 1, paddingHorizontal: 15, alignItems: 'center', justifyContent: 'center' },
  filterLabel: { fontSize: 13, fontWeight: '800' },
  centered: { textAlign: 'center', paddingVertical: 50 },
  emptyCard: { borderRadius: 22, padding: 22, gap: 7, marginTop: 24 },
  emptyTitle: { fontSize: 18, lineHeight: 25, fontWeight: '800' },
  emptyCopy: { fontSize: 14, lineHeight: 20 },
  emptyAction: { fontSize: 14, fontWeight: '800', marginTop: 5 },
  limitNote: { fontSize: 12, textAlign: 'center', paddingVertical: 12 },
});
