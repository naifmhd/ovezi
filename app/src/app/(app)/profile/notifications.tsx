import { HeaderAction } from '@/components/ui/header-action';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { Linking, Platform, Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import {
  fetchNotificationPreferences,
  setGroupNotificationsMuted,
  updateNotificationPreferences,
} from '@/lib/notifications-api';
import {
  hasRegisteredPushDevice,
  notificationPermissionStatus,
  syncPushToken,
  unregisterPushDevice,
} from '@/lib/push-notifications';
import { errorMessage } from '@/lib/api-client';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';
import type { NotificationPreferences } from '@/types/api';

type ToggleKey = 'expense_created' | 'payment_received' | 'settle_up_reminders';

export default function NotificationSettingsScreen() {
  const token = useAuthStore((state) => state.token)!;
  const queryClient = useQueryClient();
  const [permission, setPermission] = useState<string>('loading');
  const [deviceRegistered, setDeviceRegistered] = useState(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const preferencesQuery = useQuery({
    queryKey: ['notification-preferences'],
    queryFn: () => fetchNotificationPreferences(token),
  });

  useEffect(() => {
    void Promise.all([notificationPermissionStatus(), hasRegisteredPushDevice()]).then(
      ([nextPermission, registered]) => {
        setPermission(nextPermission);
        setDeviceRegistered(registered);
      },
    );
  }, []);

  const permissionMutation = useMutation({
    mutationFn: async (enabled: boolean) => {
      if (enabled) {
        const pushToken = await syncPushToken(token, true);

        if (!pushToken) throw new Error('Notification permission was not granted.');
      } else {
        await unregisterPushDevice(token);
      }

      return enabled;
    },
    onSuccess: async (enabled) => {
      setPermission(await notificationPermissionStatus());
      setDeviceRegistered(enabled);
      setStatusMessage(enabled ? 'This device is ready for notifications.' : 'This device was disabled.');
    },
  });
  const preferenceMutation = useMutation({
    mutationFn: (input: Partial<Pick<NotificationPreferences, ToggleKey>>) =>
      updateNotificationPreferences(token, input),
    onSuccess: (preferences) => {
      queryClient.setQueryData(['notification-preferences'], preferences);
    },
  });
  const groupMutation = useMutation({
    mutationFn: ({ groupId, muted }: { groupId: number; muted: boolean }) =>
      setGroupNotificationsMuted(token, groupId, muted),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notification-preferences'] }),
  });
  const mutationError = permissionMutation.error ?? preferenceMutation.error ?? groupMutation.error;
  const deviceEnabled = deviceRegistered;

  function togglePreference(key: ToggleKey, value: boolean) {
    preferenceMutation.mutate({ [key]: value });
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </HeaderAction>
          <ThemedText style={styles.headerTitle}>Notifications</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          {preferencesQuery.error ? (
            <QueryErrorCard
              error={preferencesQuery.error}
              onRetry={() => preferencesQuery.refetch()}
              retrying={preferencesQuery.isFetching}
            />
          ) : null}

          <SectionTitle title="This device" />
          <ThemedView type="backgroundElement" style={styles.card}>
            <SettingRow
              label="Push notifications"
              subtitle={Platform.OS === 'web'
                ? 'Available in the iOS and Android apps'
                : deviceEnabled
                  ? 'Registered to receive Ovezi alerts'
                  : permission === 'denied'
                    ? 'Permission is blocked in device settings'
                    : 'Enable alerts from Ovezi'}
              value={deviceEnabled}
              disabled={permission === 'loading' || permission === 'unsupported' || permissionMutation.isPending}
              onChange={(value) => permissionMutation.mutate(value)}
            />
            {permission === 'denied' ? (
              <Pressable onPress={() => Linking.openSettings()} style={styles.settingsLink}>
                <ThemedText style={styles.action} themeColor="primary">Open device settings</ThemedText>
              </Pressable>
            ) : null}
          </ThemedView>

          {statusMessage ? (
            <ThemedView type="backgroundSelected" style={styles.messageCard}>
              <ThemedText style={styles.message}>{statusMessage}</ThemedText>
            </ThemedView>
          ) : null}
          {mutationError ? <ThemedText themeColor="danger">{errorMessage(mutationError)}</ThemedText> : null}

          <SectionTitle title="Activity" />
          <ThemedView type="backgroundElement" style={styles.card}>
            <SettingRow
              label="New expenses"
              subtitle="When someone adds an expense involving you"
              value={preferencesQuery.data?.expense_created ?? true}
              disabled={!preferencesQuery.data || preferenceMutation.isPending}
              onChange={(value) => togglePreference('expense_created', value)}
            />
            <Divider />
            <SettingRow
              label="Payments received"
              subtitle="When a settlement to you is recorded"
              value={preferencesQuery.data?.payment_received ?? true}
              disabled={!preferencesQuery.data || preferenceMutation.isPending}
              onChange={(value) => togglePreference('payment_received', value)}
            />

          </ThemedView>

          <SectionTitle title="Groups" />
          <ThemedText style={styles.hint} themeColor="textSecondary">
            Muting a group stops its expense and settlement alerts without changing your global choices.
          </ThemedText>
          <ThemedView type="backgroundElement" style={styles.card}>
            {(preferencesQuery.data?.groups ?? []).map((group, index, groups) => (
              <View key={group.id}>
                <SettingRow
                  label={group.name}
                  subtitle={group.muted ? 'Muted' : 'Notifications allowed'}
                  value={!group.muted}
                  disabled={groupMutation.isPending}
                  onChange={(enabled) => groupMutation.mutate({ groupId: group.id, muted: !enabled })}
                />
                {index < groups.length - 1 ? <Divider /> : null}
              </View>
            ))}
            {preferencesQuery.data?.groups.length === 0 ? (
              <ThemedText style={styles.empty} themeColor="textSecondary">No groups yet.</ThemedText>
            ) : null}
          </ThemedView>
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

function SectionTitle({ title }: { title: string }) {
  return <ThemedText style={styles.sectionTitle}>{title}</ThemedText>;
}

function Divider() {
  return <View style={styles.divider} />;
}

function SettingRow({
  disabled,
  label,
  onChange,
  subtitle,
  value,
}: {
  disabled: boolean;
  label: string;
  onChange: (value: boolean) => void;
  subtitle: string;
  value: boolean;
}) {
  const theme = useTheme();
  return (
    <View style={[styles.row, disabled && styles.disabled]}>
      <View style={styles.rowCopy}>
        <ThemedText style={styles.rowLabel}>{label}</ThemedText>
        <ThemedText style={styles.rowSubtitle} themeColor="textSecondary">{subtitle}</ThemedText>
      </View>
      <Switch
        accessibilityLabel={label}
        accessibilityHint={subtitle}
        disabled={disabled}
        ios_backgroundColor="#7A8498"
        onValueChange={onChange}
        thumbColor={value ? theme.primary : theme.surface}
        trackColor={{ false: theme.border, true: theme.interactive }}
        value={value}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: { minHeight: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '600' },
  headerTitle: { fontSize: 17, fontWeight: '600' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 13, maxWidth: 620, width: '100%', alignSelf: 'center' },
  sectionTitle: { fontSize: 18, lineHeight: 25, fontWeight: '600', marginTop: 8 },
  card: { borderRadius: 20, paddingHorizontal: 16 },
  row: { minHeight: 72, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowCopy: { flex: 1, gap: 2 },
  rowLabel: { fontSize: 14, fontWeight: '600' },
  rowSubtitle: { fontSize: 12, lineHeight: 17 },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.3 },
  disabled: { opacity: 0.55 },
  settingsLink: { minHeight: 44, justifyContent: 'center', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: '#7A8498' },
  action: { fontSize: 13, fontWeight: '600' },
  messageCard: { borderRadius: 17, padding: 14 },
  message: { fontSize: 13, lineHeight: 19, fontWeight: '600' },
  hint: { fontSize: 13, lineHeight: 19 },
  empty: { paddingVertical: 22, textAlign: 'center' },
});
