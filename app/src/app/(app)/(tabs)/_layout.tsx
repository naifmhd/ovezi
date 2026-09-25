import { ThemedText } from '@/components/themed-text';
import { router, Tabs } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Platform, StyleSheet, View, type ColorValue } from 'react-native';

import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { PlatformMaterial } from '@/components/ui/platform-material';
import { useTheme } from '@/hooks/use-theme';
import { selectionHaptic } from '@/lib/haptics';

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
        tabBarActiveTintColor: theme.interactive,
        tabBarInactiveTintColor: theme.textSecondary,
        tabBarStyle: {
          position: 'absolute',
          backgroundColor: 'transparent',
          borderTopWidth: 0,
          elevation: 0,
          height: Platform.OS === 'ios' ? 86 : 76,
          paddingTop: 8,
        },
        tabBarBackground: () => <PlatformMaterial style={StyleSheet.absoluteFill} />,
        tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
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
        name="add"
        options={{
          title: 'Add',
          tabBarButton: () => <AddExpenseTabButton />,
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

function AddExpenseTabButton() {
  const theme = useTheme();

  return (
    <View style={styles.addSlot}>
      <AnimatedPressable
        accessibilityLabel="Add expense"
        accessibilityRole="button"
        onPress={() => {
          selectionHaptic();
          router.push('/(app)/expenses/create');
        }}
        pressedScale={0.95}
        style={[styles.addButton, { backgroundColor: theme.primary }]}>
        <SymbolView
          name={{ ios: 'plus', android: 'add', web: 'add' }}
          size={27}
          tintColor={theme.primaryText}
          weight="bold"
        />
      </AnimatedPressable>
      <ThemedAddLabel />
    </View>
  );
}

function ThemedAddLabel() {
  const theme = useTheme();
  return <ThemedText style={{ color: theme.interactive, fontSize: 11, lineHeight: 16, fontWeight: '600', marginTop: 3 }}>Add</ThemedText>;
}

const styles = StyleSheet.create({
  addSlot: { flex: 1, alignItems: 'center', justifyContent: 'flex-start' },
  addButton: {
    width: 54,
    height: 54,
    borderRadius: 27,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: -18,
    elevation: 7,
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 7 },
    shadowOpacity: 0.2,
    shadowRadius: 14,
  },
  addLabelDot: { width: 4, height: 4, borderRadius: 2, marginTop: 5 },
});
