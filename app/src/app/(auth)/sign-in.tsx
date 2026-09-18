import { useMutation } from '@tanstack/react-query';
import { Link } from 'expo-router';
import { useState } from 'react';
import { Platform, Pressable, StyleSheet, View } from 'react-native';

import { AuthShell } from '@/components/auth/auth-shell';
import { FormField } from '@/components/auth/form-field';
import { FormMessage } from '@/components/auth/form-message';
import { PrimaryButton } from '@/components/auth/primary-button';
import { SocialSignIn } from '@/components/auth/social-sign-in';
import { ThemedText } from '@/components/themed-text';
import { login } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

export default function SignInScreen() {
  const setSession = useAuthStore((state) => state.setSession);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [socialError, setSocialError] = useState('');
  const mutation = useMutation({
    mutationFn: () => login(email, password),
    onSuccess: (session) => setSession(session),
  });

  return (
    <AuthShell
      title="Welcome back"
      subtitle="Sign in to see what you owe and what you're owed."
      footer={
        <ThemedText themeColor="textSecondary">
          New to Ovezi?{' '}
          <Link href="/(auth)/sign-up" asChild>
            <ThemedText style={styles.link} themeColor="primary">
              Create an account
            </ThemedText>
          </Link>
        </ThemedText>
      }>
      <FormField
        autoComplete="email"
        keyboardType="email-address"
        label="Email"
        onChangeText={setEmail}
        placeholder="you@example.com"
        value={email}
      />
      <FormField
        autoComplete="current-password"
        label="Password"
        onChangeText={setPassword}
        placeholder="Your password"
        secureTextEntry
        value={password}
      />
      <Link href="/(auth)/forgot-password" asChild>
        <Pressable style={styles.forgotLink}>
          <ThemedText style={styles.link} themeColor="primary">
            Forgot password?
          </ThemedText>
        </Pressable>
      </Link>
      {mutation.error ? <FormMessage>{errorMessage(mutation.error)}</FormMessage> : null}
      {socialError ? <FormMessage>{socialError}</FormMessage> : null}
      <PrimaryButton
        disabled={!email || !password}
        label="Sign in"
        loading={mutation.isPending}
        onPress={() => mutation.mutate()}
      />
      {Platform.OS !== 'web' ? (
        <>
          <View style={styles.divider}>
            <View style={styles.line} />
            <ThemedText themeColor="textSecondary" style={styles.or}>
              or
            </ThemedText>
            <View style={styles.line} />
          </View>
          <SocialSignIn onError={setSocialError} />
        </>
      ) : null}
    </AuthShell>
  );
}

const styles = StyleSheet.create({
  forgotLink: { alignSelf: 'flex-end' },
  link: { fontWeight: '800' },
  divider: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  line: { flex: 1, height: StyleSheet.hairlineWidth, backgroundColor: '#9AA3B5' },
  or: { fontSize: 13 },
});
