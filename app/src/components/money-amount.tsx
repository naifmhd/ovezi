import { ScrollView, StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import type { ThemeColor } from '@/constants/theme';
import { currencyFractionDigits, formatMoney } from '@/lib/format';

export function MoneyAmount({ minor, currency, tone = 'text' }: { minor: number; currency: string; tone?: ThemeColor }) {
  const digits = currencyFractionDigits(currency);
  const number = new Intl.NumberFormat(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(Math.abs(minor) / 10 ** digits);
  return <View accessible accessibilityLabel={formatMoney(Math.abs(minor), currency)} style={styles.wrap}>
    <ThemedText style={styles.currency} themeColor={tone}>{currency}</ThemedText>
    <ScrollView horizontal showsHorizontalScrollIndicator={false}>
      <ThemedText numberOfLines={1} style={styles.amount} themeColor={tone}>{number}</ThemedText>
    </ScrollView>
  </View>;
}
const styles = StyleSheet.create({ wrap: { gap: 2 }, currency: { fontSize: 13, lineHeight: 20 }, amount: { fontSize: 34, lineHeight: 44, fontWeight: '500', fontVariant: ['tabular-nums'], letterSpacing: -0.7 } });
