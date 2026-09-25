import { Pressable, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { errorMessage } from '@/lib/api-client';

type QueryErrorCardProps = {
  error: unknown;
  onRetry: () => void;
  retrying?: boolean;
  title?: string;
};

export function QueryErrorCard({
  error,
  onRetry,
  retrying = false,
  title = 'Couldn’t load this yet',
}: QueryErrorCardProps) {
  return (
    <ThemedView accessibilityLiveRegion="polite" type="dangerSurface" style={styles.card}>
      <View style={styles.copy}>
        <ThemedText style={styles.title}>{title}</ThemedText>
        <ThemedText style={styles.message} themeColor="textSecondary">
          {errorMessage(error)}
        </ThemedText>
      </View>
      <Pressable disabled={retrying} onPress={onRetry} style={styles.retry}>
        <ThemedText style={styles.retryLabel} themeColor="primary">
          {retrying ? 'Retrying…' : 'Try again'}
        </ThemedText>
      </Pressable>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  card: { borderRadius: 20, padding: 17, flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 14 },
  copy: { flex: 1, gap: 3 },
  title: { fontSize: 15, lineHeight: 21, fontWeight: '600' },
  message: { fontSize: 13, lineHeight: 19 },
  retry: { minHeight: 48, justifyContent: 'center', paddingHorizontal: 5 },
  retryLabel: { fontSize: 13, fontWeight: '600' },
});
