import * as Haptics from 'expo-haptics';
import { Platform } from 'react-native';

function safely(run: () => Promise<void>) {
  void run().catch(() => {
    // Haptics are optional and may be unavailable in low-power or accessibility modes.
  });
}

export function selectionHaptic() {
  safely(() => Haptics.selectionAsync());
}

export function successHaptic() {
  safely(() => Platform.OS === 'android'
    ? Haptics.performAndroidHapticsAsync(Haptics.AndroidHaptics.Confirm)
    : Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success));
}

export function warningHaptic() {
  safely(() => Platform.OS === 'android'
    ? Haptics.performAndroidHapticsAsync(Haptics.AndroidHaptics.Reject)
    : Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning));
}
