import { PropsWithChildren } from 'react';
import { SymbolView } from 'expo-symbols';
import { KeyboardAvoidingView, Modal, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useReducedMotion } from 'react-native-reanimated';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';

type Symbol = Parameters<typeof SymbolView>[0]['name'];
export function ExpenseOptionRow({ label, value, icon, onPress, expanded }: { label: string; value: string; icon?: Symbol; onPress: () => void; expanded?: boolean }) {
  const theme = useTheme();
  return <AnimatedPressable accessibilityRole="button" accessibilityLabel={`${label}: ${value}`} accessibilityState={expanded === undefined ? undefined : { expanded }} onPress={onPress} style={[styles.row, { borderBottomColor: theme.border }]}>
    {icon ? <SymbolView name={icon} size={21} tintColor={theme.textSecondary} /> : null}
    <ThemedText themeColor="textSecondary" style={{ flexShrink: 0 }}>{label}</ThemedText>
    <ThemedText style={{ flex: 1, textAlign: 'right', fontWeight: '500' }}>{value}</ThemedText>
    <SymbolView name={{ ios: expanded ? 'chevron.up' : 'chevron.right', android: expanded ? 'expand_less' : 'chevron_right', web: expanded ? 'expand_less' : 'chevron_right' }} size={18} tintColor={theme.textSecondary} />
  </AnimatedPressable>;
}
export function ExpenseSelectionRow({ label, selected, onPress, leading }: { label: string; selected: boolean; onPress: () => void; leading?: React.ReactNode }) {
  const theme = useTheme();
  return <AnimatedPressable accessibilityRole="radio" accessibilityLabel={label} accessibilityState={{ checked: selected }} onPress={onPress} style={[styles.row, { borderBottomColor: theme.border }]}>
    {leading}<ThemedText style={{ flex: 1 }}>{label}</ThemedText>
    <SymbolView name={{ ios: selected ? 'largecircle.fill.circle' : 'circle', android: selected ? 'radio_button_checked' : 'radio_button_unchecked', web: selected ? 'radio_button_checked' : 'radio_button_unchecked' }} size={23} tintColor={selected ? theme.interactive : theme.controlBorder} />
  </AnimatedPressable>;
}
export function ExpenseEditorSheet({ title, visible, onClose, children }: PropsWithChildren<{ title: string; visible: boolean; onClose: () => void }>) {
  const theme = useTheme();
  const reduceMotion = useReducedMotion();
  return <Modal visible={visible} presentationStyle="pageSheet" animationType={reduceMotion ? 'none' : 'slide'} onRequestClose={onClose}>
    <SafeAreaView style={{ flex: 1, backgroundColor: theme.background }}>
      <View style={styles.header}><ThemedText accessibilityRole="header" style={styles.title}>{title}</ThemedText><AnimatedPressable accessibilityRole="button" accessibilityLabel={`Done with ${title.toLowerCase()}`} onPress={onClose} style={styles.done}><ThemedText themeColor="interactive" style={{ fontWeight: '600' }}>Done</ThemedText></AnimatedPressable></View>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}><ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={styles.content}>{children}</ScrollView></KeyboardAvoidingView>
    </SafeAreaView>
  </Modal>;
}
const styles = StyleSheet.create({ row: { minHeight: 58, flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 13, borderBottomWidth: StyleSheet.hairlineWidth }, header: { minHeight: 64, paddingHorizontal: 24, flexDirection: 'row', alignItems: 'center', gap: 12 }, title: { fontSize: 20, lineHeight: 28, fontWeight: '600', flex: 1 }, done: { minHeight: 48, minWidth: 48, alignItems: 'flex-end', justifyContent: 'center' }, content: { padding: 24, paddingTop: 6, paddingBottom: 40, gap: 12 } });
