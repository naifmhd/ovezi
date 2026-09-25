import { router } from 'expo-router';
import { StyleSheet, View, useWindowDimensions } from 'react-native';

import { GroupAvatar } from '@/components/group-avatar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';
import { formatMoney } from '@/lib/format';
import type { Group } from '@/types/api';

type GroupCardProps = {
  group: Group;
  balanceMinor?: number;
};

export function GroupCard({ group, balanceMinor }: GroupCardProps) {
  const theme = useTheme();
  const { width, fontScale } = useWindowDimensions();
  const stacked = width < 360 || fontScale > 1.2;
  const hasBalance = balanceMinor !== undefined && balanceMinor !== 0;

  return (
    <AnimatedPressable
      accessibilityRole="button"
      onPress={() =>
        router.push({ pathname: '/(app)/groups/[id]', params: { id: group.id.toString() } })
      }>
        <ThemedView type="backgroundElement" style={[styles.card, { borderBottomColor: theme.border }]}>
          <GroupAvatar group={group} />
          <View style={[styles.content, stacked && styles.contentStacked]}>
          <View style={styles.copy}>
            <ThemedText style={styles.name}>
              {group.name}
            </ThemedText>
            <ThemedText style={styles.meta} themeColor="textSecondary">
              {group.active_member_count ?? 0} members · {group.reporting_currency_code}
            </ThemedText>
          </View>
          {balanceMinor !== undefined ? (
            <View style={[styles.balance, stacked && styles.balanceStacked]}>
              <ThemedText
                style={styles.amount}
                themeColor={hasBalance ? (balanceMinor > 0 ? 'positive' : 'danger') : 'textSecondary'}>
                {formatMoney(Math.abs(balanceMinor), group.reporting_currency_code)}
              </ThemedText>
              <ThemedText style={styles.balanceLabel} themeColor="textSecondary">
                {balanceMinor > 0 ? 'you are owed' : balanceMinor < 0 ? 'you owe' : 'settled'}
              </ThemedText>
            </View>
          ) : null}
          </View>
        </ThemedView>
    </AnimatedPressable>
  );
}

const styles = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'center', paddingVertical: 15, borderBottomWidth: StyleSheet.hairlineWidth, backgroundColor: 'transparent', gap: 12 },
  content: { flex: 1, minWidth: 0, flexDirection: 'row', alignItems: 'center', gap: 10 },
  contentStacked: { flexDirection: 'column', alignItems: 'stretch' },
  copy: { flex: 1, minWidth: 0 },
  name: { fontSize: 15, lineHeight: 22, fontWeight: '500' },
  meta: { fontSize: 12, lineHeight: 18, marginTop: 3 },
  balance: { alignItems: 'flex-end', maxWidth: '45%' },
  balanceStacked: { alignItems: 'flex-start', maxWidth: '100%' },
  amount: { fontSize: 14, lineHeight: 21, fontWeight: '500', fontVariant: ['tabular-nums'] },
  balanceLabel: { fontSize: 12, lineHeight: 18 },
});
