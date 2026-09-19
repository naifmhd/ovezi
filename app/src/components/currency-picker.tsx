import { useQuery } from '@tanstack/react-query';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { FormField } from '@/components/auth/form-field';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { fetchCurrencies } from '@/lib/currencies-api';
import { useAuthStore } from '@/stores/auth-store';

export function CurrencyPicker({
  disabled = false,
  excludeCode,
  label,
  onChange,
  value,
}: {
  disabled?: boolean;
  excludeCode?: string;
  label: string;
  onChange: (currencyCode: string) => void;
  value: string | null;
}) {
  const token = useAuthStore((state) => state.token)!;
  const theme = useTheme();
  const normalizedValue = value?.trim().toUpperCase() ?? '';
  const query = useQuery({
    queryKey: ['currencies'],
    queryFn: () => fetchCurrencies(token),
  });
  const currencies = (query.data ?? []).filter((currency) => currency.code !== excludeCode);
  const selected = currencies.find((currency) => currency.code === normalizedValue);

  if (!query.isLoading && currencies.length === 0) {
    return (
      <FormField
        autoCapitalize="characters"
        editable={!disabled}
        label={label}
        maxLength={3}
        onChangeText={onChange}
        value={value ?? ''}
      />
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.labelRow}>
        <ThemedText style={styles.label}>{label}</ThemedText>
        {selected ? (
          <ThemedText style={styles.selectedName} themeColor="textSecondary">
            {selected.name}
          </ThemedText>
        ) : null}
      </View>
      {query.isLoading ? (
        <ThemedText style={styles.loading} themeColor="textSecondary">Loading currencies…</ThemedText>
      ) : (
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          <View style={styles.row}>
            {currencies.map((currency) => {
              const active = currency.code === normalizedValue;
              return (
                <Pressable
                  accessibilityRole="radio"
                  accessibilityState={{ checked: active }}
                  disabled={disabled}
                  key={currency.code}
                  onPress={() => onChange(currency.code)}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? theme.primary : theme.backgroundElement,
                      borderColor: active ? theme.primary : theme.border,
                    },
                    disabled && styles.disabled,
                  ]}>
                  <ThemedText style={[styles.code, active && { color: theme.primaryText }]}>
                    {currency.code}
                  </ThemedText>
                  {currency.symbol ? (
                    <ThemedText
                      style={[styles.symbol, active && { color: theme.primaryText }]}
                      themeColor={active ? undefined : 'textSecondary'}>
                      {currency.symbol}
                    </ThemedText>
                  ) : null}
                </Pressable>
              );
            })}
          </View>
        </ScrollView>
      )}
      {query.error ? (
        <ThemedText style={styles.error} themeColor="danger">
          Currency choices could not be loaded. Pull to refresh and try again.
        </ThemedText>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { gap: 7 },
  labelRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  label: { fontSize: 14, lineHeight: 20, fontWeight: '700' },
  selectedName: { flex: 1, fontSize: 12, textAlign: 'right' },
  loading: { minHeight: 48, textAlignVertical: 'center' },
  row: { flexDirection: 'row', gap: 8, paddingRight: 8 },
  chip: { minHeight: 48, minWidth: 72, borderWidth: 1, borderRadius: 15, paddingHorizontal: 13, alignItems: 'center', justifyContent: 'center' },
  code: { fontSize: 14, fontWeight: '900' },
  symbol: { fontSize: 11 },
  error: { fontSize: 12, lineHeight: 17 },
  disabled: { opacity: 0.55 },
});
