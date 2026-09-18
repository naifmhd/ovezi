import { StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';

export function FormMessage({ children, success = false }: { children: string; success?: boolean }) {
  return (
    <ThemedText
      accessibilityLiveRegion="polite"
      style={[styles.message, success ? styles.success : undefined]}
      themeColor={success ? 'primary' : 'danger'}>
      {children}
    </ThemedText>
  );
}

const styles = StyleSheet.create({
  message: { fontSize: 14, lineHeight: 20 },
  success: { fontWeight: '600' },
});
