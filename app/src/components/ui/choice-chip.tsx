import { StyleSheet } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';
import { selectionHaptic } from '@/lib/haptics';

export function ChoiceChip({ active, label, onPress, disabled = false }: { active: boolean; label: string; onPress: () => void; disabled?: boolean }) {
  const theme = useTheme();
  return (
    <AnimatedPressable accessibilityRole="button" accessibilityState={{ selected: active, disabled }} disabled={disabled}
      onPress={() => { selectionHaptic(); onPress(); }}
      style={[styles.chip, { backgroundColor: active ? theme.surfaceSubtle : theme.surface, borderColor: active ? theme.interactive : theme.controlBorder }]}>
      <ThemedText style={[styles.label, active && { color: theme.interactive }]}>{label}</ThemedText>
    </AnimatedPressable>
  );
}
const styles = StyleSheet.create({
  chip: { minHeight: 48, maxWidth: '100%', paddingHorizontal: 15, paddingVertical: 12, borderWidth: 1, borderRadius: 24, justifyContent: 'center', alignItems: 'center' },
  label: { flexShrink: 1, fontSize: 13, lineHeight: 20, fontWeight: '600' },
});
