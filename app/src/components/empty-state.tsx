import { StyleSheet } from 'react-native';

import { PrimaryButton } from '@/components/auth/primary-button';
import { BrandMark } from '@/components/brand-mark';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius } from '@/constants/theme';

type EmptyStateProps = {
  title: string;
  description: string;
  action?: { label: string; onPress: () => void };
};

export function EmptyState({ title, description, action }: EmptyStateProps) {
  return (
    <ThemedView type="surface" style={styles.card}>
      <BrandMark decorative finish="soft" size={56} />
      <ThemedText accessibilityRole="header" style={styles.title}>{title}</ThemedText>
      <ThemedText themeColor="textSecondary" style={styles.description}>{description}</ThemedText>
      {action ? <PrimaryButton label={action.label} onPress={action.onPress} /> : null}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  card: { padding: 24, gap: 12, borderRadius: Radius.card },
  title: { fontSize: 20, lineHeight: 27, fontWeight: '600' },
  description: { fontSize: 15, lineHeight: 23, marginBottom: 6 },
});
