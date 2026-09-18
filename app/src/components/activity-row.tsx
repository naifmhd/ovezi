import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { activityDescription, formatRelativeDate } from '@/lib/format';
import type { Activity } from '@/types/api';

export function ActivityRow({ activity }: { activity: Activity }) {
  const expenseId = activity.subject.type === 'expense' ? activity.subject.id : null;

  return (
    <Pressable
      disabled={!expenseId}
      onPress={() => expenseId && router.push({ pathname: '/(app)/expenses/[id]', params: { id: expenseId } })}>
      <ThemedView type="backgroundElement" style={styles.row}>
        <ThemedView type="backgroundSelected" style={styles.icon}>
          <ThemedText style={styles.iconText} themeColor="primary">
            {activity.event.startsWith('expense') ? '$' : activity.event.startsWith('member') ? '+' : '•'}
          </ThemedText>
        </ThemedView>
        <View style={styles.copy}>
          <ThemedText style={styles.description}>{activityDescription(activity)}</ThemedText>
          <ThemedText style={styles.date} themeColor="textSecondary">
            {formatRelativeDate(activity.created_at)}
          </ThemedText>
        </View>
        {expenseId ? <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText> : null}
      </ThemedView>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 12, padding: 14, borderRadius: 18, alignItems: 'center' },
  icon: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  iconText: { fontSize: 20, fontWeight: '800' },
  copy: { flex: 1 },
  description: { fontSize: 14, lineHeight: 20, fontWeight: '700' },
  date: { fontSize: 12, lineHeight: 17 },
  chevron: { fontSize: 22, fontWeight: '500' },
});
