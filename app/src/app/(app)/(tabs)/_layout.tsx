import { Tabs } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import type { ColorValue } from 'react-native';

import { useTheme } from '@/hooks/use-theme';

type TabIconProps = {
  color: ColorValue;
  name: Parameters<typeof SymbolView>[0]['name'];
};

function TabIcon({ color, name }: TabIconProps) {
  return <SymbolView name={name} size={23} tintColor={color} weight="semibold" />;
}

export default function TabsLayout() {
  const theme = useTheme();

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: theme.primary,
        tabBarInactiveTintColor: theme.textSecondary,
        tabBarStyle: {
          backgroundColor: theme.backgroundElement,
          borderTopColor: theme.border,
          height: 82,
          paddingTop: 7,
        },
        tabBarLabelStyle: { fontSize: 11, fontWeight: '700' },
      }}>
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ color }) => (
            <TabIcon color={color} name={{ ios: 'house.fill', android: 'home', web: 'home' }} />
          ),
        }}
      />
      <Tabs.Screen
        name="groups"
        options={{
          title: 'Groups',
          tabBarIcon: ({ color }) => (
            <TabIcon color={color} name={{ ios: 'person.2.fill', android: 'groups', web: 'groups' }} />
          ),
        }}
      />
      <Tabs.Screen
        name="activity"
        options={{
          title: 'Activity',
          tabBarIcon: ({ color }) => (
            <TabIcon color={color} name={{ ios: 'clock.fill', android: 'history', web: 'history' }} />
          ),
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
          tabBarIcon: ({ color }) => (
            <TabIcon
              color={color}
              name={{ ios: 'person.crop.circle.fill', android: 'account_circle', web: 'account_circle' }}
            />
          ),
        }}
      />
    </Tabs>
  );
}
