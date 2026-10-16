import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Platform, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Typography } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { selectionHaptic } from '@/lib/haptics';

/** A task action, separate from the four navigation destinations. */
export function TabExpenseAction() {
  return <TabPrimaryAction label="Add expense" onPress={() => router.push('/(app)/expenses/create')} />;
}

export function TabPrimaryAction({ label, onPress, showLabel = false }: { label: string; onPress: () => void; showLabel?: boolean }) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const bottom = Platform.OS === 'android' ? 16 : Platform.OS === 'ios' ? insets.bottom + 64 : 96;
  return (
    <View pointerEvents="box-none" style={[styles.position, { bottom }]}>
      <AnimatedPressable accessibilityRole="button" accessibilityLabel={label}
        onPress={() => { selectionHaptic(); onPress(); }}
        style={[styles.button, showLabel && styles.labeledButton, { backgroundColor: theme.primary }]}>
        <SymbolView name={{ ios: 'plus', android: 'add', web: 'add' }} size={24} tintColor={theme.primaryText} />
        {showLabel ? <ThemedText style={[styles.label, { color: theme.primaryText }]}>{label}</ThemedText> : null}
      </AnimatedPressable>
    </View>
  );
}

export const tabContentBottomPadding = Platform.OS === 'android' ? 104 : 200;

const styles = StyleSheet.create({
  position: { position: 'absolute', end: 24, start: 24, alignItems: 'flex-end' },
  labeledButton: { width: 'auto', maxWidth: '100%', flexDirection: 'row', gap: 10, paddingHorizontal: 20, paddingVertical: 14 },
  label: { ...Typography.button, flexShrink: 1 },
  button: {
    width: 56, minHeight: 56,
    alignItems: 'center', justifyContent: 'center',
    borderRadius: Platform.OS === 'android' ? 16 : 28,
    elevation: 3, shadowColor: '#0A1128', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 10,
  },
});
