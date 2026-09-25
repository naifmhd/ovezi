import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Pressable, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { activityDescription, formatRelativeDate } from '@/lib/format';
import { useTheme } from '@/hooks/use-theme';
import type { Activity } from '@/types/api';

export function ActivityRow({ activity }: { activity: Activity }) {
  const theme = useTheme();
  const tone = activityTone(activity.event, theme);
  const expenseId = activity.subject.type === 'expense' && activity.event !== 'expense.deleted'
    ? activity.subject.id
    : null;
  const groupId = expenseId === null ? activity.group_id : null;
  const canOpen = expenseId !== null || groupId !== null;

  function openActivity() {
    if (expenseId !== null) {
      router.push({ pathname: '/(app)/expenses/[id]', params: { id: expenseId } });
    } else if (groupId !== null) {
      router.push({ pathname: '/(app)/groups/[id]', params: { id: groupId } });
    }
  }

  return (
    <Pressable
      disabled={!canOpen}
      onPress={openActivity}>
      <ThemedView type="backgroundElement" style={[styles.row, { backgroundColor: theme.background, borderBottomColor: theme.border, borderBottomWidth: StyleSheet.hairlineWidth }]}>
        <View style={[styles.icon, { backgroundColor: tone.background }]}>
          <SymbolView name={tone.symbol} size={18} tintColor={tone.foreground} weight="semibold" />
        </View>
        <View style={styles.copy}>
          <ThemedText style={styles.description}>{activityDescription(activity)}</ThemedText>
          <ThemedText style={styles.date} themeColor="textSecondary">
            {formatRelativeDate(activity.created_at)}
          </ThemedText>
        </View>
        {canOpen ? <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText> : null}
      </ThemedView>
    </Pressable>
  );
}

function activityTone(event: string, theme: ReturnType<typeof useTheme>) {
  if (event.startsWith('expense')) {
    return {
      background: theme.accentCoralSurface,
      foreground: theme.accentCoral,
      symbol: { ios: 'receipt.fill', android: 'receipt_long', web: 'receipt_long' } as const,
    };
  }
  if (event.startsWith('member') || event.startsWith('placeholder')) {
    return {
      background: theme.accentVioletSurface,
      foreground: theme.accentViolet,
      symbol: { ios: 'person.2.fill', android: 'group', web: 'group' } as const,
    };
  }
  if (event.startsWith('settlement')) {
    return {
      background: theme.accentBlueSurface,
      foreground: theme.accentBlue,
      symbol: { ios: 'arrow.left.arrow.right', android: 'swap_horiz', web: 'swap_horiz' } as const,
    };
  }
  return {
    background: theme.accentAmberSurface,
    foreground: theme.accentAmber,
    symbol: { ios: 'sparkles', android: 'auto_awesome', web: 'auto_awesome' } as const,
  };
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 12, padding: 14, borderRadius: 18, alignItems: 'center' },
  icon: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  copy: { flex: 1 },
  description: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  date: { fontSize: 12, lineHeight: 17 },
  chevron: { fontSize: 22, fontWeight: '500' },
});
