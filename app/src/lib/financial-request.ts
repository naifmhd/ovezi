import * as Crypto from 'expo-crypto';

import { ApiError, apiRequest } from '@/lib/api-client';
import { draftStorage } from '@/lib/draft-storage';
import { useAuthStore } from '@/stores/auth-store';

// Only a random submission ID is stored, never the credential or financial payload.
const inFlight = new Map<string, Promise<unknown>>();

export async function financialRequest<T>(path: string, token: string, body: unknown): Promise<T> {
  const session = useAuthStore.getState();
  if (!session.user || session.token !== token) throw new Error('Sign in again before saving.');
  const fingerprint = await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, `${session.user.id}|${path}|${JSON.stringify(body)}`);
  const existing = inFlight.get(fingerprint);
  if (existing) return existing as Promise<T>;
  const pending = submit<T>(path, token, body, `submission-${fingerprint}`);
  inFlight.set(fingerprint, pending);
  try { return await pending; } finally { inFlight.delete(fingerprint); }
}

async function submit<T>(path: string, token: string, body: unknown, storageKey: string): Promise<T> {
  const key = await draftStorage.getItem(storageKey) || Crypto.randomUUID();
  // Persist before transmission so a process restart can safely retry the same body.
  await draftStorage.setItem(storageKey, key);
  try {
    const result = await apiRequest<T>(path, { method: 'POST', token, body, headers: { 'Idempotency-Key': key } });
    await draftStorage.removeItem(storageKey);
    return result;
  } catch (error) {
    if (error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 410) await draftStorage.removeItem(storageKey);
    throw error;
  }
}
