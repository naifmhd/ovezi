import { StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { groupTone } from '@/lib/group-identity';

export function GroupLabel({ groupId, name }: { groupId: number; name?: string | null }) {
  const tone = groupTone(groupId, useTheme());
  return (
    <View style={[styles.label, { backgroundColor: tone.background }]}>
      <ThemedText style={[styles.name, { color: tone.foreground }]}>
        {name || `Group #${groupId}`}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  label: { alignSelf: 'flex-start', maxWidth: '100%', borderRadius: 8, paddingHorizontal: 8, paddingVertical: 3 },
  name: { fontSize: 12, lineHeight: 18, fontWeight: '600', flexShrink: 1 },
});
