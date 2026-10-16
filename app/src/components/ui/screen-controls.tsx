import { useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { TextInput } from '@/components/ui/text-input';
import { SymbolView } from 'expo-symbols';
import { ThemedText } from '@/components/themed-text';
import { ActionSheet } from '@/components/ui/action-sheet';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';
import { selectionHaptic } from '@/lib/haptics';

type Option<T extends string> = { value: T; label: string };

export function SegmentedControl<T extends string>({ label, value, options, onChange }: {
  label: string; value: T; options: readonly Option<T>[]; onChange: (value: T) => void;
}) {
  const theme = useTheme();
  return <View accessibilityLabel={label} style={[styles.segments, { backgroundColor: theme.backgroundElement }]}>
    {options.map(option => <AnimatedPressable key={option.value} accessibilityRole="button" accessibilityState={{ selected: option.value === value }}
      onPress={() => { selectionHaptic(); onChange(option.value); }}
      style={[styles.segment, option.value === value && { backgroundColor: theme.surfaceSubtle, borderColor: theme.interactive }]}>
      <ThemedText style={styles.controlLabel} themeColor={option.value === value ? 'interactive' : 'textSecondary'}>{option.label}</ThemedText>
    </AnimatedPressable>)}
  </View>;
}

export function ViewFilter<T extends string>({ label, value, options, onChange }: {
  label: string; value: T; options: readonly Option<T>[]; onChange: (value: T) => void;
}) {
  const theme = useTheme();
  const [open, setOpen] = useState(false);
  return <>
    <AnimatedPressable accessibilityRole="button" accessibilityLabel={`${label}: ${options.find(option => option.value === value)?.label}`} accessibilityState={{ expanded: open }}
      onPress={() => setOpen(true)} style={styles.filter}>
      <ThemedText style={styles.controlLabel}>{options.find(option => option.value === value)?.label}</ThemedText>
      <SymbolView name={{ ios: 'chevron.down', android: 'expand_more', web: 'expand_more' }} size={18} tintColor={theme.textSecondary} />
    </AnimatedPressable>
    <ActionSheet title={label} visible={open} onClose={() => setOpen(false)} actions={options.map(option => ({ label: option.label, selected: option.value === value, onPress: () => onChange(option.value) }))} />
  </>;
}

export function SearchField({ label, placeholder, value, onChange }: { label: string; placeholder: string; value: string; onChange: (value: string) => void }) {
  const theme = useTheme();
  return <View style={[styles.search, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
    <SymbolView name={{ ios: 'magnifyingglass', android: 'search', web: 'search' }} size={20} tintColor={theme.textSecondary} />
    <TextInput accessibilityLabel={label} placeholder={placeholder} value={value} onChangeText={onChange} autoCapitalize="none" autoCorrect={false} maxLength={120} returnKeyType="search"
      placeholderTextColor={theme.textSecondary} selectionColor={theme.interactive} style={[styles.searchInput, { color: theme.text }]} />
    {value ? <AnimatedPressable accessibilityRole="button" accessibilityLabel={`Clear ${label.toLowerCase()}`} onPress={() => onChange('')} style={styles.clear}>
      <SymbolView name={{ ios: 'xmark.circle.fill', android: 'cancel', web: 'cancel' }} size={20} tintColor={theme.textSecondary} />
    </AnimatedPressable> : null}
  </View>;
}

export function NavigationRow({ label, detail, onPress, icon }: { label: string; detail?: string; onPress: () => void; icon?: Parameters<typeof SymbolView>[0]['name'] }) {
  const theme = useTheme();
  return <AnimatedPressable accessibilityRole="button" accessibilityLabel={label} accessibilityHint={detail} onPress={onPress} style={[styles.navigation, { borderBottomColor: theme.border }]}>
    {icon ? <SymbolView name={icon} size={22} tintColor={theme.textSecondary} /> : null}
    <View style={{ flex: 1, gap: 3 }}><ThemedText style={{ fontWeight: '500' }}>{label}</ThemedText>{detail ? <ThemedText themeColor="textSecondary" style={styles.detail}>{detail}</ThemedText> : null}</View>
    <SymbolView name={{ ios: 'chevron.right', android: 'chevron_right', web: 'chevron_right' }} size={20} tintColor={theme.textSecondary} />
  </AnimatedPressable>;
}

const styles = StyleSheet.create({
  segments: { flexDirection: 'row', borderRadius: 14, padding: 4, gap: 4 },
  segment: { flex: 1, minWidth: 0, minHeight: 48, borderRadius: 10, borderWidth: 1, borderColor: 'transparent', paddingHorizontal: 8, paddingVertical: 12, alignItems: 'center', justifyContent: 'center' },
  controlLabel: { fontSize: 14, lineHeight: 21, fontWeight: '600' },
  filter: { minHeight: 48, flexDirection: 'row', gap: 6, alignItems: 'center', alignSelf: 'flex-start', paddingVertical: 10 },
  search: { minHeight: 52, flexDirection: 'row', alignItems: 'center', gap: 10, paddingLeft: 14, paddingRight: 4, borderRadius: 14, borderWidth: StyleSheet.hairlineWidth },
  searchInput: { flex: 1, minWidth: 0, fontSize: 16, paddingVertical: 14 },
  clear: { minWidth: 48, minHeight: 48, alignItems: 'center', justifyContent: 'center' },
  navigation: { flexDirection: 'row', alignItems: 'center', gap: 12, minHeight: 64, paddingVertical: 14, borderBottomWidth: StyleSheet.hairlineWidth },
  detail: { fontSize: 13, lineHeight: 19 },
});
