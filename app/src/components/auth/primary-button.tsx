import { ActivityIndicator, StyleSheet, Text } from 'react-native';

import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius, Typography } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

type PrimaryButtonProps = {
  label: string;
  onPress: () => void;
  loading?: boolean;
  loadingLabel?: string;
  disabled?: boolean;
};

export function PrimaryButton({ label, onPress, loading, loadingLabel = 'Working…', disabled }: PrimaryButtonProps) {
  const theme = useTheme();
  const unavailable = disabled || loading;

  return (
    <AnimatedPressable
      accessibilityRole="button"
      accessibilityState={{ disabled: Boolean(unavailable), busy: Boolean(loading) }}
      disabled={unavailable}
      onPress={onPress}
      style={[
        styles.button,
        { backgroundColor: theme.primary },
        unavailable && styles.dimmed,
      ]}>
      {loading ? <ActivityIndicator color={theme.primaryText} /> : null}
      <Text accessibilityLiveRegion="polite" style={[styles.label, { color: theme.primaryText }]}>{loading ? loadingLabel : label}</Text>
    </AnimatedPressable>
  );
}

const styles = StyleSheet.create({
  button: { minHeight: 52, paddingHorizontal: 18, paddingVertical: 14, borderRadius: Radius.control, flexDirection: 'row', gap: 10, alignItems: 'center', justifyContent: 'center' },
  label: { ...Typography.button, flexShrink: 1, textAlign: 'center' },
  dimmed: { opacity: 0.65 },
});
