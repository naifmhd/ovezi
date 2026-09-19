import { apiRequest } from '@/lib/api-client';
import type { NotificationPreferences } from '@/types/api';

type DataResponse<T> = { data: T };

export async function fetchNotificationPreferences(token: string) {
  const response = await apiRequest<DataResponse<NotificationPreferences>>('/notification-preferences', {
    token,
  });

  return response.data;
}

export async function updateNotificationPreferences(
  token: string,
  input: Partial<Pick<NotificationPreferences, 'expense_created' | 'payment_received' | 'settle_up_reminders'>>,
) {
  const response = await apiRequest<DataResponse<NotificationPreferences>>('/notification-preferences', {
    method: 'PATCH',
    token,
    body: input,
  });

  return response.data;
}

export function setGroupNotificationsMuted(token: string, groupId: number, muted: boolean) {
  return apiRequest<DataResponse<{ group_id: number; muted: boolean }>>(
    `/groups/${groupId}/notification-mute`,
    { method: muted ? 'PUT' : 'DELETE', token },
  );
}

export function registerPushToken(
  token: string,
  input: { expoPushToken: string; platform: 'ios' | 'android'; deviceName: string },
) {
  return apiRequest('/push-tokens', {
    method: 'POST',
    token,
    body: {
      expo_push_token: input.expoPushToken,
      platform: input.platform,
      device_name: input.deviceName,
    },
  });
}

export function revokePushToken(token: string, expoPushToken: string) {
  return apiRequest<void>('/push-tokens', {
    method: 'DELETE',
    token,
    body: { expo_push_token: expoPushToken },
  });
}
