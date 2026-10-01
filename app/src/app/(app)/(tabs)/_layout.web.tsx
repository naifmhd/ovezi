import { Tabs } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { StyleSheet } from 'react-native';

import { useTheme } from '@/hooks/use-theme';

export default function WebTabsLayout() {
  const theme = useTheme();
  return <Tabs screenOptions={{
    headerShown: false,
    tabBarActiveTintColor: theme.interactive,
    tabBarInactiveTintColor: theme.textSecondary,
    tabBarStyle: { position: 'absolute', height: 76, paddingTop: 8, paddingBottom: 12, backgroundColor: theme.surface, borderTopColor: theme.border, borderTopWidth: StyleSheet.hairlineWidth },
    tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
  }}>
    <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: ({ color }) => <SymbolView name={{ web: 'home' }} tintColor={color} size={23} /> }} />
    <Tabs.Screen name="groups" options={{ title: 'Groups', tabBarIcon: ({ color }) => <SymbolView name={{ web: 'groups' }} tintColor={color} size={23} /> }} />
    <Tabs.Screen name="add" options={{ href: null }} />
    <Tabs.Screen name="activity" options={{ title: 'Activity', tabBarIcon: ({ color }) => <SymbolView name={{ web: 'history' }} tintColor={color} size={23} /> }} />
    <Tabs.Screen name="profile" options={{ title: 'Profile', tabBarIcon: ({ color }) => <SymbolView name={{ web: 'account_circle' }} tintColor={color} size={23} /> }} />
  </Tabs>;
}
