import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Platform, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';
import { selectionHaptic } from '@/lib/haptics';

/** A task action, separate from the four navigation destinations. */
export function TabExpenseAction() {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const bottom = Platform.OS === 'android' ? 16 : Platform.OS === 'ios' ? insets.bottom + 64 : 96;
  return (
    <View pointerEvents="box-none" style={[styles.position, { bottom }]}>
      <AnimatedPressable accessibilityRole="button" accessibilityLabel="Add expense"
        onPress={() => { selectionHaptic(); router.push('/(app)/expenses/create'); }}
        style={[styles.button, { backgroundColor: theme.primary }]}>
        <SymbolView name={{ ios: 'plus', android: 'add', web: 'add' }} size={24} tintColor={theme.primaryText} />
      </AnimatedPressable>
    </View>
  );
}

export const tabContentBottomPadding = Platform.OS === 'android' ? 104 : 200;

const styles = StyleSheet.create({
  position: { position: 'absolute', end: 24, start: 24, alignItems: 'flex-end' },
  button: {
    width: 56, height: 56,
    alignItems: 'center', justifyContent: 'center',
    borderRadius: Platform.OS === 'android' ? 16 : 28,
    elevation: 3, shadowColor: '#0A1128', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 10,
  },
});
