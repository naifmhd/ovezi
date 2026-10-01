import { NativeTabs } from 'expo-router/unstable-native-tabs';
import { Platform } from 'react-native';

import { useTheme } from '@/hooks/use-theme';

export default function TabsLayout() {
  const theme = useTheme();
  return (
    <NativeTabs
      tintColor={theme.interactive}
      iconColor={{ default: theme.textSecondary, selected: theme.interactive }}
      labelStyle={{ default: { color: theme.textSecondary }, selected: { color: theme.interactive } }}
      backgroundColor={Platform.OS === 'android' ? theme.surface : undefined}
      indicatorColor={theme.surfaceSubtle}
      labelVisibilityMode="labeled"
      backBehavior="history"
      minimizeBehavior="never">
      <NativeTabs.Trigger name="index" disableAutomaticContentInsets={Platform.OS === 'ios'}>
        <NativeTabs.Trigger.Label>Home</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'house', selected: 'house.fill' }} md="home" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="groups" disableAutomaticContentInsets={Platform.OS === 'ios'}>
        <NativeTabs.Trigger.Label>Groups</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'person.2', selected: 'person.2.fill' }} md="groups" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="activity" disableAutomaticContentInsets={Platform.OS === 'ios'}>
        <NativeTabs.Trigger.Label>Activity</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'clock', selected: 'clock.fill' }} md="history" />
      </NativeTabs.Trigger>
      <NativeTabs.Trigger name="profile" disableAutomaticContentInsets={Platform.OS === 'ios'}>
        <NativeTabs.Trigger.Label>Profile</NativeTabs.Trigger.Label>
        <NativeTabs.Trigger.Icon sf={{ default: 'person.crop.circle', selected: 'person.crop.circle.fill' }} md="account_circle" />
      </NativeTabs.Trigger>
    </NativeTabs>
  );
}
