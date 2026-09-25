import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { registerPushToken, revokePushToken } from '@/lib/notifications-api';

const storedPushTokenKey = 'ovezi.expo-push-token';
const pushDisabledKey = 'ovezi.push-disabled';
let deviceOperation: Promise<unknown> = Promise.resolve();

function serializeDeviceOperation<T>(operation: () => Promise<T>): Promise<T> {
  const pending = deviceOperation.then(operation, operation);
  deviceOperation = pending.catch(() => {});
  return pending;
}

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldPlaySound: false,
    shouldSetBadge: false,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

function projectId() {
  return process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim()
    || Constants.expoConfig?.extra?.eas?.projectId
    || Constants.easConfig?.projectId;
}

export async function notificationPermissionState() {
  if (Platform.OS === 'web') return { status: 'unsupported' as const, canAskAgain: false };

  const permission = await Notifications.getPermissionsAsync();
  const allowed = permission.granted || permission.ios?.status === Notifications.IosAuthorizationStatus.PROVISIONAL;
  return { status: allowed ? 'granted' as const : permission.status, canAskAgain: permission.canAskAgain };
}

export async function hasRegisteredPushDevice() {
  if (Platform.OS === 'web') return false;

  return (await SecureStore.getItemAsync(pushDisabledKey)) !== 'true'
    && (await SecureStore.getItemAsync(storedPushTokenKey)) !== null;
}

export function syncPushToken(apiToken: string, requestPermission = false) {
  return serializeDeviceOperation(() => registerDevice(apiToken, requestPermission));
}

async function registerDevice(apiToken: string, requestPermission: boolean) {
  if (Platform.OS === 'web') return null;
  if (!requestPermission && await SecureStore.getItemAsync(pushDisabledKey) === 'true') return null;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'Ovezi activity',
      importance: Notifications.AndroidImportance.DEFAULT,
      lightColor: '#20D9A1',
    });
  }

  let permission = await notificationPermissionState();

  if (permission.status !== 'granted' && requestPermission && permission.canAskAgain) {
    await Notifications.requestPermissionsAsync();
    permission = await notificationPermissionState();
  }

  if (permission.status !== 'granted') {
    await unregisterDevice(apiToken);
    return null;
  }

  const easProjectId = projectId();

  if (typeof easProjectId !== 'string' || !easProjectId) {
    throw new Error('Notifications are not configured in this app build. Please update Ovezi or contact support.');
  }

  const expoPushToken = (await Notifications.getExpoPushTokenAsync({ projectId: easProjectId })).data;
  const platform = Platform.OS === 'ios' ? 'ios' : 'android';
  const deviceName = `${platform}:${Device.modelName ?? 'device'}`;

  await registerPushToken(apiToken, { expoPushToken, platform, deviceName });
  await SecureStore.setItemAsync(storedPushTokenKey, expoPushToken);
  if (requestPermission) await SecureStore.deleteItemAsync(pushDisabledKey);

  return expoPushToken;
}

export function disablePushDevice(apiToken: string) {
  return serializeDeviceOperation(async () => {
    if (Platform.OS === 'web') return;
    await unregisterDevice(apiToken);
    await SecureStore.setItemAsync(pushDisabledKey, 'true');
  });
}

export function unregisterPushDevice(apiToken: string) {
  return serializeDeviceOperation(() => unregisterDevice(apiToken));
}

async function unregisterDevice(apiToken: string) {
  if (Platform.OS === 'web') return;

  const expoPushToken = await SecureStore.getItemAsync(storedPushTokenKey);

  if (!expoPushToken) return;

  await revokePushToken(apiToken, expoPushToken);
  await SecureStore.deleteItemAsync(storedPushTokenKey);
}
