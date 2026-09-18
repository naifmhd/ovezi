import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';

import { useTheme } from '@/hooks/use-theme';

type PrimaryButtonProps = {
  label: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
};

export function PrimaryButton({ label, onPress, loading, disabled }: PrimaryButtonProps) {
  const theme = useTheme();
  const unavailable = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      disabled={unavailable}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        { backgroundColor: theme.primary },
        (pressed || unavailable) && styles.dimmed,
      ]}>
      {loading ? (
        <ActivityIndicator color={theme.primaryText} />
      ) : (
        <Text style={[styles.label, { color: theme.primaryText }]}>{label}</Text>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  button: { height: 54, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  label: { fontSize: 16, fontWeight: '800' },
  dimmed: { opacity: 0.65 },
});
