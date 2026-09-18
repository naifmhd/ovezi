import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ExpenseRow } from '@/components/expense-row';
import { GroupCard } from '@/components/group-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { searchOvezi } from '@/lib/search-api';
import { useAuthStore } from '@/stores/auth-store';

export default function SearchScreen() {
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const [queryInput, setQueryInput] = useState('');
  const [submittedQuery, setSubmittedQuery] = useState('');
  const query = useQuery({
    queryKey: ['search', submittedQuery],
    queryFn: () => searchOvezi(token, submittedQuery),
    enabled: submittedQuery.length >= 2,
  });
  const results = query.data;
  const resultCount = (results?.groups.length ?? 0)
    + (results?.expenses.length ?? 0)
    + (results?.friends.length ?? 0);

  function submit() {
    const normalized = queryInput.trim();
    if (normalized.length >= 2) setSubmittedQuery(normalized);
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Search</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            <View style={styles.searchRow}>
              <View style={styles.searchField}>
                <FormField
                  autoCapitalize="none"
                  autoFocus
                  label="Search Ovezi"
                  onChangeText={setQueryInput}
                  onSubmitEditing={submit}
                  placeholder="Expenses, groups, or friends"
                  returnKeyType="search"
                  value={queryInput}
                />
              </View>
              <View style={styles.searchButton}>
                <PrimaryButton
                  disabled={queryInput.trim().length < 2}
                  label="Search"
                  loading={query.isFetching}
                  onPress={submit}
                />
              </View>
            </View>

            {query.error ? <ThemedText themeColor="danger">{errorMessage(query.error)}</ThemedText> : null}
            {!submittedQuery ? (
              <ThemedView type="backgroundSelected" style={styles.tipCard}>
                <ThemedText style={styles.tipTitle}>Find anything quickly</ThemedText>
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Search descriptions and categories, group names, or accepted friends by name and email.
                </ThemedText>
              </ThemedView>
            ) : null}

            {results?.groups.length ? <SectionTitle title="Groups" /> : null}
            {results?.groups.map((group) => <GroupCard group={group} key={group.id} />)}

            {results?.expenses.length ? <SectionTitle title="Expenses" /> : null}
            {results?.expenses.map((expense) => (
              <ExpenseRow
                currentUserId={user.id}
                expense={expense}
                key={expense.id}
                onPress={() => router.push({ pathname: '/(app)/expenses/[id]', params: { id: expense.id } })}
              />
            ))}

            {results?.friends.length ? <SectionTitle title="Friends" /> : null}
            {results?.friends.map((friend) => (
              <ThemedView key={friend.id} type="backgroundElement" style={styles.friendRow}>
                <ThemedView type="backgroundSelected" style={styles.avatar}>
                  <ThemedText style={styles.initial} themeColor="primary">
                    {friend.name.slice(0, 1).toUpperCase()}
                  </ThemedText>
                </ThemedView>
                <View style={styles.friendCopy}>
                  <ThemedText style={styles.friendName}>{friend.name}</ThemedText>
                  <ThemedText style={styles.email} themeColor="textSecondary">{friend.email}</ThemedText>
                </View>
                <Pressable
                  onPress={() => router.push({
                    pathname: '/(app)/expenses/create',
                    params: { friendId: friend.id },
                  })}
                  style={styles.friendAction}>
                  <ThemedText style={styles.friendActionText} themeColor="primary">Add expense</ThemedText>
                </Pressable>
              </ThemedView>
            ))}

            {submittedQuery && !query.isFetching && results && resultCount === 0 ? (
              <ThemedView type="backgroundElement" style={styles.emptyCard}>
                <ThemedText style={styles.tipTitle}>No matches</ThemedText>
                <ThemedText style={styles.copy} themeColor="textSecondary">
                  Try another description, group name, category, friend name, or email.
                </ThemedText>
              </ThemedView>
            ) : null}
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
  back: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 11, maxWidth: 720, width: '100%', alignSelf: 'center' },
  searchRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 10 },
  searchField: { flex: 1 },
  searchButton: { width: 108 },
  tipCard: { borderRadius: 20, padding: 18, gap: 5, marginTop: 6 },
  tipTitle: { fontSize: 17, lineHeight: 23, fontWeight: '900' },
  copy: { fontSize: 13, lineHeight: 19 },
  sectionTitle: { fontSize: 18, lineHeight: 25, fontWeight: '900', marginTop: 9 },
  friendRow: { minHeight: 72, borderRadius: 20, padding: 13, flexDirection: 'row', alignItems: 'center', gap: 11 },
  avatar: { width: 44, height: 44, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  initial: { fontSize: 18, fontWeight: '900' },
  friendCopy: { flex: 1 },
  friendName: { fontSize: 14, fontWeight: '900' },
  email: { fontSize: 12 },
  friendAction: { minHeight: 40, justifyContent: 'center', paddingHorizontal: 4 },
  friendActionText: { fontSize: 12, fontWeight: '900' },
  emptyCard: { borderRadius: 20, padding: 20, gap: 5, marginTop: 12 },
});
