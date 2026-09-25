import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { apiRequest, apiTextRequest } from '@/lib/api-client';
import { useExpenseDraftStore } from '@/stores/expense-draft-store';
import type { AuthSession, User } from '@/types/api';

type DataResponse<T> = { data: T };

export const deviceName = `${Platform.OS}:${Device.modelName ?? 'device'}`;

export async function login(email: string, password: string) {
  const response = await apiRequest<DataResponse<AuthSession>>('/auth/login', {
    method: 'POST',
    body: { email, password, device_name: deviceName },
  });
  return response.data;
}

export async function register(
  name: string,
  email: string,
  password: string,
  passwordConfirmation: string,
) {
  const response = await apiRequest<DataResponse<AuthSession>>('/auth/register', {
    method: 'POST',
    body: {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
      device_name: deviceName,
    },
  });
  return response.data;
}

export async function socialLogin(
  provider: 'google' | 'apple',
  idToken: string,
  options: { nonce?: string; name?: string; authorization_code?: string } = {},
) {
  const response = await apiRequest<DataResponse<AuthSession>>('/auth/social', {
    method: 'POST',
    body: {
      provider,
      id_token: idToken,
      device_name: deviceName,
      ...options,
    },
  });
  return response.data;
}

export async function fetchMe(token: string) {
  const response = await apiRequest<DataResponse<User>>('/me', { token });
  return response.data;
}

export function forgotPassword(email: string) {
  return apiRequest<{ message: string }>('/auth/forgot-password', {
    method: 'POST',
    body: { email },
  });
}

export function resetPassword(
  email: string,
  token: string,
  password: string,
  passwordConfirmation: string,
) {
  return apiRequest<void>('/auth/reset-password', {
    method: 'POST',
    body: { email, token, password, password_confirmation: passwordConfirmation },
  });
}

export function verifyEmail(verificationUrl: string) {
  return apiRequest<void>(verificationUrl);
}

export function sendVerificationEmail(token: string) {
  return apiRequest<{ message: string }>('/auth/email/verification-notification', {
    method: 'POST',
    token,
  });
}

export function logout(token: string) {
  return apiRequest<void>('/auth/session', { method: 'DELETE', token });
}

export async function updateProfile(
  token: string,
  input: { name: string; defaultCurrencyCode: string },
) {
  const response = await apiRequest<DataResponse<User>>('/me', {
    method: 'PATCH',
    token,
    body: { name: input.name, default_currency_code: input.defaultCurrencyCode },
  });
  return response.data;
}

export function updatePassword(
  token: string,
  input: { currentPassword: string; password: string; passwordConfirmation: string },
) {
  return apiRequest<void>('/auth/password', {
    method: 'PUT',
    token,
    body: {
      current_password: input.currentPassword,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
    },
  });
}

export async function updateEmail(
  token: string,
  input: { currentPassword: string; email: string; emailConfirmation: string },
) {
  const response = await apiRequest<DataResponse<User>>('/auth/email', {
    method: 'PUT',
    token,
    body: {
      current_password: input.currentPassword,
      email: input.email,
      email_confirmation: input.emailConfirmation,
    },
  });
  return response.data;
}

export function logoutAllSessions(token: string) {
  return apiRequest<void>('/auth/sessions', { method: 'DELETE', token });
}

export function disconnectSocialAccount(token: string, provider: 'google' | 'apple') {
  return apiRequest<void>(`/auth/social-accounts/${provider}`, { method: 'DELETE', token });
}

export type DeletionCredentials = { provider: 'google' | 'apple'; id_token: string; nonce?: string; authorization_code?: string };

export async function deleteAccount(token: string, credentials: string | DeletionCredentials) {
  await apiRequest<void>('/me', {
    method: 'DELETE',
    token,
    body: typeof credentials === 'string' ? { current_password: credentials } : credentials,
  });
  useExpenseDraftStore.getState().clearDraft();
  await useExpenseDraftStore.persist.clearStorage();
}

export function exportPersonalData(token: string) {
  return apiTextRequest('/me/export', token, 'application/json');
}
