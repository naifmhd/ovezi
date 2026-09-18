import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { FormMessage } from '@/components/auth/form-message';
import { PrimaryButton } from '@/components/auth/primary-button';
import { CurrencyPicker } from '@/components/currency-picker';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { errorMessage } from '@/lib/api-client';
import { createGroup } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export default function CreateGroupScreen() {
  const queryClient = useQueryClient();
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const [name, setName] = useState('');
  const [currency, setCurrency] = useState(user.default_currency_code);
  const mutation = useMutation({
    mutationFn: () =>
      createGroup(token, { name, reportingCurrencyCode: currency.trim().toUpperCase() }),
    onSuccess: async (group) => {
      await queryClient.invalidateQueries({ queryKey: ['groups'] });
      router.replace({ pathname: '/(app)/groups/[id]', params: { id: group.id.toString() } });
    },
  });

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView style={styles.safeArea}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.keyboard}>
          <View style={styles.header}>
            <Pressable onPress={() => router.back()}>
              <ThemedText style={styles.cancel} themeColor="primary">
                Cancel
              </ThemedText>
            </Pressable>
            <ThemedText style={styles.headerTitle}>New group</ThemedText>
            <View style={styles.headerSpacer} />
          </View>

          <View style={styles.content}>
            <View style={styles.heading}>
              <ThemedText style={styles.title}>What are you splitting?</ThemedText>
              <ThemedText themeColor="textSecondary">
                You can invite people and add placeholders after creating the group.
              </ThemedText>
            </View>
            <FormField
              autoCapitalize="words"
              autoFocus
              label="Group name"
              onChangeText={setName}
              placeholder="Malé weekend"
              value={name}
            />
            <CurrencyPicker
              label="Group currency"
              onChange={setCurrency}
              value={currency}
            />
            <ThemedText style={styles.hint} themeColor="textSecondary">
              Balances are reported in this currency. The group owner can configure conversion rates.
            </ThemedText>
            {mutation.error ? <FormMessage>{errorMessage(mutation.error)}</FormMessage> : null}
            <PrimaryButton
              disabled={!name.trim() || currency.trim().length !== 3}
              label="Create group"
              loading={mutation.isPending}
              onPress={() => mutation.mutate()}
            />
          </View>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  keyboard: { flex: 1 },
  header: {
    height: 60,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
  },
  cancel: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 16, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, gap: Spacing.three, maxWidth: 520, width: '100%', alignSelf: 'center' },
  heading: { gap: 8, marginBottom: Spacing.three },
  title: { fontSize: 29, lineHeight: 37, fontWeight: '800', letterSpacing: -0.7 },
  hint: { fontSize: 13, lineHeight: 19 },
});
