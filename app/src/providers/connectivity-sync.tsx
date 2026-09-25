import NetInfo from '@react-native-community/netinfo';
import { focusManager, onlineManager } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { AppState, Platform, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useTheme } from '@/hooks/use-theme';

import { useRealtimeStore } from '@/stores/realtime-store';

export function ConnectivitySync() {
  const insets = useSafeAreaInsets();
  const theme = useTheme();
  const [isOffline, setIsOffline] = useState(false);
  const realtimeStatus = useRealtimeStore((state) => state.status);
  const [warnedRealtimeStatus, setWarnedRealtimeStatus] = useState<string | null>(null);
  const showRealtimeWarning = warnedRealtimeStatus === realtimeStatus
    && (realtimeStatus === 'error' || realtimeStatus === 'reconnecting');

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state) => {
      const isOnline = state.isConnected !== false && state.isInternetReachable !== false;

      onlineManager.setOnline(isOnline);
      setIsOffline(!isOnline);
    });

    return () => {
      unsubscribe();
    };
  }, []);

  useEffect(() => {
    if (realtimeStatus !== 'error' && realtimeStatus !== 'reconnecting') return;
    const timer = setTimeout(() => setWarnedRealtimeStatus(realtimeStatus), 8_000);
    return () => clearTimeout(timer);
  }, [realtimeStatus]);

  useEffect(() => {
    if (Platform.OS === 'web') return;

    focusManager.setFocused(AppState.currentState === 'active');
    const subscription = AppState.addEventListener('change', (status) => {
      focusManager.setFocused(status === 'active');
    });

    return () => {
      subscription.remove();
      focusManager.setFocused(undefined);
    };
  }, []);

  if (!isOffline && !showRealtimeWarning) return null;

  return (
    <View
      accessibilityLiveRegion="polite"
      accessibilityRole="alert"
      pointerEvents="none"
      style={[styles.banner, { marginBottom: Math.max(insets.bottom, 8), backgroundColor: theme.warningSurface }]}>
      <View style={[styles.dot, showRealtimeWarning && !isOffline && styles.warningDot]} />
      <Text style={[styles.copy, { color: theme.warning }]}>
        {isOffline
          ? 'You’re offline. New changes need a connection.'
          : realtimeStatus === 'error'
            ? 'Live updates are unavailable. Your saved data is safe.'
            : 'Live updates are reconnecting. Your saved data is safe.'}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    marginHorizontal: 16,
    marginTop: 8,
    elevation: 8,
    minHeight: 44,
    borderRadius: 16,
    paddingHorizontal: 16,
    paddingVertical: 11,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    backgroundColor: '#172033',
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 5 },
    shadowOpacity: 0.2,
    shadowRadius: 12,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#20D9A1',
  },
  warningDot: { backgroundColor: '#FFB86B' },
  copy: {
    flex: 1,
    fontSize: 13,
    lineHeight: 18,
    fontWeight: '600',
    textAlign: 'center',
  },
});
