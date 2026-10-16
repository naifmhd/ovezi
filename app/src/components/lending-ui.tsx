import { PropsWithChildren } from 'react';
import { StyleSheet, View } from 'react-native';
import { useQueryClient } from '@tanstack/react-query';
import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius, Typography } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { currencyFractionDigits } from '@/lib/format';
import type { LendingBalance } from '@/lib/lending-api';

export function LendingScreen({ title, children }: PropsWithChildren<{ title: string }>) {
  return <AppScreen title={title} backFallback="/(app)/(tabs)/lending">{children}</AppScreen>;
}
export function LendingAction({ label, onPress, disabled, danger = false, variant = 'text' }: { label: string; onPress: () => void; disabled?: boolean; danger?: boolean; variant?: 'text' | 'secondary' | 'primary' }) {
  const theme = useTheme();
  return <AnimatedPressable accessibilityRole="button" accessibilityLabel={label} accessibilityState={{ disabled: !!disabled }} disabled={disabled} onPress={onPress}
    style={[lendingStyles.action, variant !== 'text' && { borderRadius: Radius.control, paddingHorizontal: 16, borderWidth: variant === 'secondary' ? 1 : 0, borderColor: theme.controlBorder, backgroundColor: variant === 'primary' ? theme.primary : theme.surface }, disabled && { opacity: 0.5 }]}>
    <ThemedText style={{ ...Typography.button, flexShrink: 1, color: danger ? theme.danger : variant === 'primary' ? theme.primaryText : theme.interactive }}>{label}</ThemedText>
  </AnimatedPressable>;
}
function formatLendingMoney(amount: number, currency: string) {
  const digits = currencyFractionDigits(currency);
  const formatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(amount / 10 ** digits);
  return `${currency} ${formatted}`;
}
export function LendingMoney({ amount, currency, large = false }: { amount: number; currency: string; large?: boolean }) {
  return <ThemedText style={{ fontSize: large ? 28 : 17, lineHeight: large ? 36 : 25, fontWeight: '600', fontVariant: ['tabular-nums'] }}>{formatLendingMoney(amount, currency)}</ThemedText>;
}
export function LendingBalanceSummary({ balance, large = false }: { balance: LendingBalance; large?: boolean }) {
  const lent = balance.direction === 'lent';
  return <View style={{ gap: 4, paddingVertical: 8 }}>
    <ThemedText themeColor="textSecondary" style={lendingStyles.muted}>{lent ? 'Still to receive' : 'Still to pay'}</ThemedText>
    <LendingMoney amount={balance.outstanding_minor} currency={balance.currency_code} large={large} />
    <ThemedText themeColor="textSecondary" style={lendingStyles.muted}>{lent ? 'Lent' : 'Borrowed'}: {formatLendingMoney(balance.amount_minor, balance.currency_code)}</ThemedText>
    <ThemedText themeColor="textSecondary" style={lendingStyles.muted}>{lent ? 'Received back' : 'Paid back'}: {formatLendingMoney(balance.repaid_minor, balance.currency_code)}</ThemedText>
  </View>;
}
export function LendingRow({ children }: PropsWithChildren) {
  const theme = useTheme();
  return <View style={[lendingStyles.row, { borderBottomColor: theme.border }]}>{children}</View>;
}
export function useRefreshLending() {
  const client = useQueryClient();
  return () => Promise.all([client.invalidateQueries({ queryKey: ['lending'] }), client.invalidateQueries({ queryKey: ['activity'] })]);
}
export const lendingStyles = StyleSheet.create({
  row: { paddingVertical: 18, borderBottomWidth: StyleSheet.hairlineWidth, gap: 6 },
  title: { fontSize: 18, lineHeight: 25, fontWeight: '600' },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  action: { minHeight: 48, paddingHorizontal: 4, paddingVertical: 12, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, alignSelf: 'flex-start', maxWidth: '100%' },
  muted: { fontSize: 14, lineHeight: 21 },
});
