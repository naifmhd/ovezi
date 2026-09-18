import { useMutation } from '@tanstack/react-query';
import { Link } from 'expo-router';
import { useState } from 'react';
import { StyleSheet } from 'react-native';

import { AuthShell } from '@/components/auth/auth-shell';
import { FormField } from '@/components/auth/form-field';
import { FormMessage } from '@/components/auth/form-message';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ThemedText } from '@/components/themed-text';
import { forgotPassword } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';

export default function ForgotPasswordScreen() {
  const [email, setEmail] = useState('');
  const mutation = useMutation({ mutationFn: () => forgotPassword(email) });

  return (
    <AuthShell
      title="Reset your password"
      subtitle="Enter your email and we'll send you a secure reset link."
      footer={
        <Link href="/(auth)/sign-in" asChild>
          <ThemedText style={styles.link} themeColor="primary">
            Back to sign in
          </ThemedText>
        </Link>
      }>
      <FormField
        autoComplete="email"
        keyboardType="email-address"
        label="Email"
        onChangeText={setEmail}
        onSubmitEditing={() => mutation.mutate()}
        placeholder="you@example.com"
        returnKeyType="send"
        value={email}
      />
      {mutation.data ? <FormMessage success>{mutation.data.message}</FormMessage> : null}
      {mutation.error ? <FormMessage>{errorMessage(mutation.error)}</FormMessage> : null}
      <PrimaryButton
        disabled={!email}
        label="Send reset link"
        loading={mutation.isPending}
        onPress={() => mutation.mutate()}
      />
    </AuthShell>
  );
}

const styles = StyleSheet.create({ link: { fontWeight: '800' } });
