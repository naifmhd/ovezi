import { useMutation } from '@tanstack/react-query';
import { Link } from 'expo-router';
import { useState } from 'react';
import { StyleSheet } from 'react-native';

import { AuthShell } from '@/components/auth/auth-shell';
import { FormField } from '@/components/auth/form-field';
import { FormMessage } from '@/components/auth/form-message';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ThemedText } from '@/components/themed-text';
import { register } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

export default function SignUpScreen() {
  const setSession = useAuthStore((state) => state.setSession);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const mutation = useMutation({
    mutationFn: () => register(name, email, password, confirmation),
    onSuccess: (session) => setSession(session),
  });

  return (
    <AuthShell
      title="Create your account"
      subtitle="Unlimited groups, expenses, and settlements. No ads."
      footer={
        <ThemedText themeColor="textSecondary">
          Already have an account?{' '}
          <Link href="/(auth)/sign-in" asChild>
            <ThemedText style={styles.link} themeColor="primary">
              Sign in
            </ThemedText>
          </Link>
        </ThemedText>
      }>
      <FormField
        autoCapitalize="words"
        autoComplete="name"
        label="Name"
        onChangeText={setName}
        placeholder="Your name"
        value={name}
      />
      <FormField
        autoComplete="email"
        keyboardType="email-address"
        label="Email"
        onChangeText={setEmail}
        placeholder="you@example.com"
        value={email}
      />
      <FormField
        autoComplete="new-password"
        label="Password"
        onChangeText={setPassword}
        placeholder="At least 8 characters"
        secureTextEntry
        value={password}
      />
      <FormField
        autoComplete="new-password"
        label="Confirm password"
        onChangeText={setConfirmation}
        placeholder="Repeat your password"
        secureTextEntry
        value={confirmation}
      />
      {mutation.error ? <FormMessage>{errorMessage(mutation.error)}</FormMessage> : null}
      <PrimaryButton
        disabled={!name || !email || !password || password !== confirmation}
        label="Create account"
        loading={mutation.isPending}
        onPress={() => mutation.mutate()}
      />
    </AuthShell>
  );
}

const styles = StyleSheet.create({ link: { fontWeight: '800' } });
