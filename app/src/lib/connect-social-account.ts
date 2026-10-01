import { connectSocialAccount, type SocialIdentityCredentials } from '@/lib/auth-api';
import { useAuthStore } from '@/stores/auth-store';

type ConnectionSession = { token: string; userId: number; sessionVersion: number };

export async function connectCurrentSocialAccount(
  session: ConnectionSession,
  credentials: SocialIdentityCredentials,
  password?: string,
) {
  const isCurrent = () => {
    const state = useAuthStore.getState();
    return state.token === session.token && state.user?.id === session.userId
      && state.sessionVersion === session.sessionVersion;
  };
  if (!isCurrent()) throw new Error('Your session changed. Please reopen Connected accounts and try again.');
  const user = await connectSocialAccount(session.token, credentials, password);
  if (!isCurrent()) return false;
  // Linking updates only the current profile; it never replaces the session token.
  useAuthStore.setState({ user });
  return true;
}
