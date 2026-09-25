import { useQuery } from '@tanstack/react-query';
import { SymbolView } from 'expo-symbols';
import { useMemo, useState } from 'react';
import { useReducedMotion } from 'react-native-reanimated';
import {
  FlatList,
  Modal,
  Platform,
  Pressable,
  SafeAreaView,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { fetchCurrencies } from '@/lib/currencies-api';
import { selectionHaptic } from '@/lib/haptics';
import { useAuthStore } from '@/stores/auth-store';

export function CurrencyPicker({
  compact = false,
  disabled = false,
  excludeCode,
  label,
  onChange,
  value,
}: {
  compact?: boolean;
  disabled?: boolean;
  excludeCode?: string;
  label: string;
  onChange: (currencyCode: string) => void;
  value: string | null;
}) {
  const token = useAuthStore((state) => state.token)!;
  const defaultCurrency = useAuthStore((state) => state.user?.default_currency_code);
  const theme = useTheme();
  const reduceMotion = useReducedMotion();
  const [visible, setVisible] = useState(false);
  const [search, setSearch] = useState('');
  const normalizedValue = value?.trim().toUpperCase() ?? '';
  const query = useQuery({ queryKey: ['currencies'], queryFn: () => fetchCurrencies(token) });
  const currencies = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase();
    return (query.data ?? [])
      .filter((currency) => currency.code !== excludeCode)
      .filter((currency) => !needle
        || currency.code.toLocaleLowerCase().includes(needle)
        || currency.name.toLocaleLowerCase().includes(needle))
      .sort((a, b) => {
        const rank = (code: string) => code === normalizedValue ? 0 : code === defaultCurrency ? 1 : 2;
        return rank(a.code) - rank(b.code) || a.code.localeCompare(b.code);
      });
  }, [defaultCurrency, excludeCode, normalizedValue, query.data, search]);
  const selected = (query.data ?? []).find((currency) => currency.code === normalizedValue);
  const selectedTone = currencyTone(normalizedValue || defaultCurrency || 'USD', theme);

  function close() {
    setVisible(false);
    setSearch('');
  }

  return (
    <View style={styles.container}>
      {!compact ? <ThemedText style={styles.label}>{label}</ThemedText> : null}
      <AnimatedPressable
        accessibilityLabel={`${label}, ${selected?.name ?? (normalizedValue || 'not selected')}`}
        accessibilityRole="button"
        disabled={disabled}
        onPress={() => setVisible(true)}
        style={[
          styles.trigger,
          compact && styles.compactTrigger,
          { backgroundColor: theme.surface, borderColor: theme.border },
          disabled && styles.disabled,
        ]}>
        {!compact ? <View style={[styles.symbol, { backgroundColor: selectedTone.background }]}>
          <ThemedText style={[styles.symbolText, { color: selectedTone.foreground }]}>
            {selected?.symbol ?? (normalizedValue.slice(0, 1) || '$')}
          </ThemedText>
        </View>
        : null}
        <View style={!compact && styles.triggerCopy}>
          <ThemedText style={styles.code}>{normalizedValue || 'Choose currency'}</ThemedText>
          {!compact ? <ThemedText numberOfLines={1} style={styles.name} themeColor="textSecondary">
            {selected?.name ?? (query.isLoading ? 'Loading currencies…' : 'Tap to search')}
          </ThemedText> : null}
        </View>
        <SymbolView
          name={{ ios: 'chevron.up.chevron.down', android: 'unfold_more', web: 'unfold_more' }}
          size={18}
          tintColor={theme.textSecondary}
        />
      </AnimatedPressable>

      {query.error ? (
        <ThemedText style={styles.error} themeColor="danger">
          Currency choices could not be loaded. Try again.
        </ThemedText>
      ) : null}

      <Modal
        animationType={reduceMotion ? "none" : "slide"}
        onRequestClose={close}
        presentationStyle={Platform.OS === 'ios' ? 'pageSheet' : 'fullScreen'}
        visible={visible}>
        <SafeAreaView style={[styles.sheet, { backgroundColor: theme.background }]}>
          <View style={styles.sheetHeader}>
            <View>
              <ThemedText style={styles.sheetTitle}>Choose currency</ThemedText>
              <ThemedText style={styles.sheetSubtitle} themeColor="textSecondary">
                Current and default currencies appear first
              </ThemedText>
            </View>
            <Pressable accessibilityRole="button" hitSlop={10} onPress={close} style={styles.done}>
              <ThemedText style={styles.doneText} themeColor="interactive">Done</ThemedText>
            </Pressable>
          </View>
          <View style={[styles.searchWrap, { backgroundColor: theme.surface, borderColor: theme.border }]}>
            <SymbolView
              name={{ ios: 'magnifyingglass', android: 'search', web: 'search' }}
              size={18}
              tintColor={theme.textSecondary}
            />
            <TextInput
              accessibilityLabel="Search currencies"
              autoCapitalize="characters"
              autoFocus
              onChangeText={setSearch}
              placeholder="Search code or currency"
              placeholderTextColor={theme.textSecondary}
              selectionColor={theme.interactive}
              style={[styles.searchInput, { color: theme.text }]}
              value={search}
            />
          </View>
          <FlatList
            contentContainerStyle={styles.list}
            data={currencies}
            keyboardShouldPersistTaps="handled"
            keyExtractor={(currency) => currency.code}
            renderItem={({ item }) => {
              const active = item.code === normalizedValue;
              const tone = currencyTone(item.code, theme);
              return (
                <AnimatedPressable
                  accessibilityRole="radio"
                  accessibilityState={{ checked: active }}
                  onPress={() => {
                    selectionHaptic();
                    onChange(item.code);
                    close();
                  }}
                  style={[
                    styles.currencyRow,
                    { backgroundColor: active ? theme.surfaceSubtle : theme.surface },
                  ]}>
                  <View style={[styles.currencySymbol, { backgroundColor: tone.background }]}>
                    <ThemedText style={[styles.currencySymbolText, { color: tone.foreground }]}>
                      {item.symbol ?? item.code.slice(0, 1)}
                    </ThemedText>
                  </View>
                  <View style={styles.triggerCopy}>
                    <ThemedText style={styles.currencyName}>{item.name}</ThemedText>
                    <ThemedText style={styles.currencyCode} themeColor="textSecondary">{item.code}</ThemedText>
                  </View>
                  {active ? (
                    <SymbolView
                      name={{ ios: 'checkmark.circle.fill', android: 'check_circle', web: 'check_circle' }}
                      size={22}
                      tintColor={theme.positive}
                    />
                  ) : null}
                </AnimatedPressable>
              );
            }}
          />
        </SafeAreaView>
      </Modal>
    </View>
  );
}

function currencyTone(code: string, theme: ReturnType<typeof useTheme>) {
  const index = [...code].reduce((total, character) => total + character.charCodeAt(0), 0) % 4;
  if (index === 0) return { background: theme.accentBlueSurface, foreground: theme.accentBlue };
  if (index === 1) return { background: theme.accentVioletSurface, foreground: theme.accentViolet };
  if (index === 2) return { background: theme.accentCoralSurface, foreground: theme.accentCoral };
  return { background: theme.accentAmberSurface, foreground: theme.accentAmber };
}

const styles = StyleSheet.create({
  container: { gap: 7 },
  compactTrigger: { minHeight: 48, paddingHorizontal: 10, gap: 5, borderWidth: 0 },
  label: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  trigger: {
    minHeight: 58,
    borderWidth: 1,
    borderRadius: Radius.control,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },
  symbol: { width: 36, height: 36, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  symbolText: { fontSize: 16, fontWeight: '600' },
  triggerCopy: { flex: 1, minWidth: 0 },
  code: { fontSize: 15, lineHeight: 20, fontWeight: '600' },
  name: { fontSize: 12, lineHeight: 17 },
  disabled: { opacity: 0.55 },
  error: { fontSize: 12, lineHeight: 17 },
  sheet: { flex: 1 },
  sheetHeader: {
    flexWrap: 'wrap',
    paddingVertical: 12,
    minHeight: 76,
    paddingHorizontal: Spacing.four,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 16,
  },
  sheetTitle: { fontSize: 24, lineHeight: 31, fontWeight: '600' },
  sheetSubtitle: { fontSize: 12, lineHeight: 17 },
  done: { minWidth: 48, minHeight: 48, alignItems: 'flex-end', justifyContent: 'center' },
  doneText: { fontSize: 15, fontWeight: '600' },
  searchWrap: {
    height: 50,
    borderWidth: 1,
    borderRadius: Radius.control,
    marginHorizontal: Spacing.four,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
  },
  searchInput: { flex: 1, fontSize: 16, height: '100%' },
  list: { padding: Spacing.four, gap: 8, paddingBottom: 40 },
  currencyRow: {
    minHeight: 64,
    borderRadius: Radius.card,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  currencySymbol: { width: 40, height: 40, borderRadius: 13, alignItems: 'center', justifyContent: 'center' },
  currencySymbolText: { fontSize: 16, fontWeight: '600' },
  currencyName: { fontSize: 15, lineHeight: 20, fontWeight: '600' },
  currencyCode: { fontSize: 12, lineHeight: 17 },
});
