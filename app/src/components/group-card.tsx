import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { GroupAvatar } from '@/components/group-avatar';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { formatMoney } from '@/lib/format';
import type { Group } from '@/types/api';

type GroupCardProps = {
  group: Group;
  balanceMinor?: number;
};

export function GroupCard({ group, balanceMinor }: GroupCardProps) {
  const hasBalance = balanceMinor !== undefined && balanceMinor !== 0;

  return (
    <Pressable
      onPress={() =>
        router.push({ pathname: '/(app)/groups/[id]', params: { id: group.id.toString() } })
      }>
      {({ pressed }) => (
        <ThemedView type="backgroundElement" style={[styles.card, pressed && styles.pressed]}>
          <GroupAvatar group={group} />
          <View style={styles.copy}>
            <ThemedText numberOfLines={1} style={styles.name}>
              {group.name}
            </ThemedText>
            <ThemedText style={styles.meta} themeColor="textSecondary">
              {group.active_member_count ?? 0} members · {group.reporting_currency_code}
            </ThemedText>
          </View>
          {balanceMinor !== undefined ? (
            <View style={styles.balance}>
              <ThemedText
                style={styles.amount}
                themeColor={hasBalance ? (balanceMinor > 0 ? 'primary' : 'danger') : 'textSecondary'}>
                {formatMoney(Math.abs(balanceMinor), group.reporting_currency_code)}
              </ThemedText>
              <ThemedText style={styles.balanceLabel} themeColor="textSecondary">
                {balanceMinor > 0 ? 'you are owed' : balanceMinor < 0 ? 'you owe' : 'settled'}
              </ThemedText>
            </View>
          ) : null}
        </ThemedView>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'center', padding: 15, borderRadius: 20, gap: 12 },
  pressed: { opacity: 0.7 },
  copy: { flex: 1 },
  name: { fontSize: 16, lineHeight: 22, fontWeight: '800' },
  meta: { fontSize: 12, lineHeight: 18 },
  balance: { alignItems: 'flex-end', maxWidth: 110 },
  amount: { fontSize: 15, lineHeight: 21, fontWeight: '800' },
  balanceLabel: { fontSize: 11, lineHeight: 16 },
});
