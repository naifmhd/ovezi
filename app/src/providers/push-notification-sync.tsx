import * as Notifications from 'expo-notifications';
import { Href, router } from 'expo-router';
import { useEffect } from 'react';
import { AppState, Platform } from 'react-native';

import { syncPushToken } from '@/lib/push-notifications';
import { useAuthStore } from '@/stores/auth-store';

function openNotification(response: Notifications.NotificationResponse | null) {
  const path = response?.notification.request.content.data?.path;

  if (typeof path === 'string' && path.startsWith('/(app)/')) {
    router.push(path as Href);
  }
}

export function PushNotificationSync() {
  const token = useAuthStore((state) => state.token);

  useEffect(() => {
    if (!token || Platform.OS === 'web') return;

    const sync = () => void syncPushToken(token).catch(() => {
      // Permission prompts and actionable errors are handled from notification settings.
    });
    sync();
    const appStateSubscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') sync();
    });

    const responseSubscription = Notifications.addNotificationResponseReceivedListener(openNotification);
    void Notifications.getLastNotificationResponseAsync().then(openNotification);

    return () => {
      responseSubscription.remove();
      appStateSubscription.remove();
    };
  }, [token]);

  return null;
}
