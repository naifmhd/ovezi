import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { CurrencyPicker } from '@/components/currency-picker';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { updateProfile } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

export default function EditProfileScreen() {
  const user = useAuthStore((state) => state.user)!;
  const token = useAuthStore((state) => state.token)!;
  const refreshUser = useAuthStore((state) => state.refreshUser);
  const [name, setName] = useState(user.name);
  const [currency, setCurrency] = useState(user.default_currency_code);
  const [formError, setFormError] = useState<string | null>(null);
  const mutation = useMutation({
    mutationFn: () => updateProfile(token, {
      name: name.trim(),
      defaultCurrencyCode: currency.trim().toUpperCase(),
    }),
    onSuccess: async () => {
      await refreshUser();
      router.back();
    },
  });

  function submit() {
    setFormError(null);
    if (!name.trim()) return setFormError('Enter your name.');
    if (currency.trim().length !== 3) return setFormError('Enter a valid three-letter currency code.');
    mutation.mutate();
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>Edit profile</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.content}>
          <FormField
            autoCapitalize="words"
            label="Name"
            onChangeText={setName}
            value={name}
          />
          <CurrencyPicker
            label="Default currency"
            onChange={setCurrency}
            value={currency}
          />
          <ThemedText style={styles.hint} themeColor="textSecondary">
            Your default currency is used for personal and 1-on-1 expense reporting. Group currencies are managed separately.
          </ThemedText>
          {formError || mutation.error ? (
            <ThemedText themeColor="danger">{formError ?? errorMessage(mutation.error)}</ThemedText>
          ) : null}
          <PrimaryButton label="Save profile" loading={mutation.isPending} onPress={submit} />
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: { height: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, gap: 15, maxWidth: 560, width: '100%', alignSelf: 'center' },
  hint: { fontSize: 13, lineHeight: 19 },
});
