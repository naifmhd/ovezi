import { StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';

export function NativeDateField({ label, value, onChange, minimumDate, optional = false }: {
  label: string; value: string; onChange: (value: string) => void; minimumDate?: Date; optional?: boolean; emptyLabel?: string;
}) {
  const theme = useTheme();
  const minimum = minimumDate ? `${minimumDate.getFullYear()}-${String(minimumDate.getMonth() + 1).padStart(2, '0')}-${String(minimumDate.getDate()).padStart(2, '0')}` : undefined;
  return <View style={styles.container}>
    <ThemedText style={styles.label}>{label}</ThemedText>
    <input type="date" aria-label={label} value={value} min={minimum} required={!optional} onChange={(event) => onChange(event.target.value)}
      style={{ width: '100%', boxSizing: 'border-box', minHeight: 54, padding: 14, borderRadius: 15, border: `1px solid ${theme.controlBorder}`, color: theme.text, background: theme.surface, font: 'inherit', colorScheme: theme.background === '#0D1422' ? 'dark' : 'light' }} />
  </View>;
}
const styles = StyleSheet.create({ container: { gap: 7 }, label: { fontSize: 14, lineHeight: 20, fontWeight: '600' } });
