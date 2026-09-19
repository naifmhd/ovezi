import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { registerPushToken, revokePushToken } from '@/lib/notifications-api';

const storedPushTokenKey = 'ovezi.expo-push-token';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldPlaySound: false,
    shouldSetBadge: false,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

function projectId() {
  return Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
}

export async function notificationPermissionStatus() {
  if (Platform.OS === 'web') return 'unsupported' as const;

  return (await Notifications.getPermissionsAsync()).status;
}

export async function hasRegisteredPushDevice() {
  if (Platform.OS === 'web') return false;

  return (await SecureStore.getItemAsync(storedPushTokenKey)) !== null;
}

export async function syncPushToken(apiToken: string, requestPermission = false) {
  if (Platform.OS === 'web') return null;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'Ovezi activity',
      importance: Notifications.AndroidImportance.DEFAULT,
      lightColor: '#00F5A0',
    });
  }

  let permission = await Notifications.getPermissionsAsync();

  if (permission.status !== 'granted' && requestPermission) {
    permission = await Notifications.requestPermissionsAsync();
  }

  if (permission.status !== 'granted') return null;

  const easProjectId = projectId();

  if (typeof easProjectId !== 'string' || !easProjectId) {
    throw new Error('Set EXPO_PUBLIC_EAS_PROJECT_ID before enabling push notifications.');
  }

  const expoPushToken = (await Notifications.getExpoPushTokenAsync({ projectId: easProjectId })).data;
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';
  const deviceName = `${platform}:${Device.modelName ?? 'device'}`;

  await registerPushToken(apiToken, { expoPushToken, platform, deviceName });
  await SecureStore.setItemAsync(storedPushTokenKey, expoPushToken);

  return expoPushToken;
}

export async function unregisterPushDevice(apiToken: string) {
  if (Platform.OS === 'web') return;

  const expoPushToken = await SecureStore.getItemAsync(storedPushTokenKey);

  if (!expoPushToken) return;

  await revokePushToken(apiToken, expoPushToken);
  await SecureStore.deleteItemAsync(storedPushTokenKey);
}
