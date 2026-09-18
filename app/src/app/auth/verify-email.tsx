import { useMutation } from '@tanstack/react-query';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect } from 'react';

import { AuthShell } from '@/components/auth/auth-shell';
import { FormMessage } from '@/components/auth/form-message';
import { PrimaryButton } from '@/components/auth/primary-button';
import { verifyEmail } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

function first(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value ?? '';
}

export default function VerifyEmailScreen() {
  const params = useLocalSearchParams<{ verification_url?: string | string[] }>();
  const verificationUrl = first(params.verification_url);
  const refreshUser = useAuthStore((state) => state.refreshUser);
  const token = useAuthStore((state) => state.token);
  const mutation = useMutation({
    mutationFn: () => verifyEmail(verificationUrl),
    onSuccess: () => refreshUser(),
  });

  useEffect(() => {
    if (verificationUrl) {
      mutation.mutate();
    }
    // Run once for the signed link delivered with this route.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [verificationUrl]);

  return (
    <AuthShell
      title="Verify your email"
      subtitle="We're confirming your secure verification link.">
      {!verificationUrl ? <FormMessage>This verification link is incomplete.</FormMessage> : null}
      {mutation.isPending ? <FormMessage success>Verifying your email…</FormMessage> : null}
      {mutation.isSuccess ? <FormMessage success>Your email is verified.</FormMessage> : null}
      {mutation.error ? <FormMessage>{errorMessage(mutation.error)}</FormMessage> : null}
      <PrimaryButton
        label={token ? 'Continue to Ovezi' : 'Continue to sign in'}
        onPress={() => router.replace(token ? '/(app)' : '/(auth)/sign-in')}
      />
    </AuthShell>
  );
}
