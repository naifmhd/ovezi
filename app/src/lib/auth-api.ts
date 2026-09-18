import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { apiRequest } from '@/lib/api-client';
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
  options: { nonce?: string; name?: string } = {},
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
