import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { AuthShell } from '@/components/auth/auth-shell';
import { FormField } from '@/components/auth/form-field';
import { FormMessage } from '@/components/auth/form-message';
import { PrimaryButton } from '@/components/auth/primary-button';
import { resetPassword } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';

function first(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value ?? '';
}

export default function ResetPasswordScreen() {
  const params = useLocalSearchParams<{ token?: string | string[]; email?: string | string[] }>();
  const token = first(params.token);
  const email = first(params.email);
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const mutation = useMutation({
    mutationFn: () => resetPassword(email, token, password, confirmation),
    onSuccess: () => router.replace('/(auth)/sign-in'),
  });
  const linkInvalid = !email || !token;

  return (
    <AuthShell
      title="Choose a new password"
      subtitle={linkInvalid ? 'This reset link is incomplete.' : `Resetting the password for ${email}.`}>
      <FormField
        autoComplete="new-password"
        label="New password"
        onChangeText={setPassword}
        placeholder="At least 8 characters"
        secureTextEntry
        value={password}
      />
      <FormField
        autoComplete="new-password"
        label="Confirm password"
        onChangeText={setConfirmation}
        placeholder="Repeat your new password"
        secureTextEntry
        value={confirmation}
      />
      {linkInvalid ? <FormMessage>Request a new password reset link.</FormMessage> : null}
      {mutation.error ? <FormMessage>{errorMessage(mutation.error)}</FormMessage> : null}
      <PrimaryButton
        disabled={linkInvalid || !password || password !== confirmation}
        label="Update password"
        loading={mutation.isPending}
        onPress={() => mutation.mutate()}
      />
    </AuthShell>
  );
}
