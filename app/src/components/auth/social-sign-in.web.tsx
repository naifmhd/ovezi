import type { DeletionCredentials } from '@/lib/auth-api';

export function SocialSignIn(_props: { onError: (message: string) => void; onSuccess?: () => void; onIdentity?: (credentials: DeletionCredentials) => Promise<void>; providers?: ('google' | 'apple')[] }) {
  return null;
}
