import { Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SymbolView } from 'expo-symbols';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useReducedMotion } from 'react-native-reanimated';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';

export function ActionSheet({ title, visible, onClose, actions }: {
  title: string; visible: boolean; onClose: () => void;
  actions: { label: string; onPress: () => void; destructive?: boolean; selected?: boolean }[];
}) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const reduceMotion = useReducedMotion();
  return (
    <Modal transparent visible={visible} animationType={reduceMotion ? 'none' : 'fade'} onRequestClose={onClose}>
      <View style={[styles.overlay, { backgroundColor: theme.overlay }]}>
        <Pressable accessibilityRole="button" accessibilityLabel="Dismiss menu" onPress={onClose} style={StyleSheet.absoluteFill} />
        <View accessibilityViewIsModal style={[styles.sheet, { backgroundColor: theme.surface, paddingBottom: Math.max(insets.bottom, 20) }]}>
          <ThemedText accessibilityRole="header" style={styles.title}>{title}</ThemedText>
          <ScrollView>
            {actions.map((action) => (
              <AnimatedPressable accessibilityRole="button" accessibilityState={action.selected === undefined ? undefined : { selected: action.selected }} key={action.label} style={[styles.action, { borderBottomColor: theme.border }]} onPress={() => { onClose(); action.onPress(); }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                  <ThemedText style={{ flex: 1 }} themeColor={action.destructive ? 'danger' : action.selected === false ? 'text' : 'interactive'}>{action.label}</ThemedText>
                  {action.selected ? <SymbolView name={{ ios: 'checkmark', android: 'check', web: 'check' }} size={20} tintColor={theme.interactive} /> : null}
                </View>
              </AnimatedPressable>
            ))}
          </ScrollView>
          <AnimatedPressable accessibilityRole="button" style={styles.action} onPress={onClose}><ThemedText>Cancel</ThemedText></AnimatedPressable>
        </View>
      </View>
    </Modal>
  );
}
const styles = StyleSheet.create({
  overlay: { flex: 1, justifyContent: 'flex-end' },
  sheet: { maxHeight: '80%', borderTopStartRadius: 28, borderTopEndRadius: 28, padding: 24, gap: 8 },
  title: { fontSize: 20, lineHeight: 28, fontWeight: '600', marginBottom: 8 },
  action: { minHeight: 52, justifyContent: 'center', paddingVertical: 14, borderBottomWidth: StyleSheet.hairlineWidth },
});
