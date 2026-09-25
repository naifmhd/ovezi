import { PropsWithChildren, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { useTheme } from '@/hooks/use-theme';

export function Disclosure({ title, children, initiallyOpen = false }: PropsWithChildren<{ title: string; initiallyOpen?: boolean }>) {
  const [open, setOpen] = useState(initiallyOpen);
  const theme = useTheme();
  return <View style={styles.container}>
    <AnimatedPressable accessibilityState={{ expanded: open }} onPress={() => setOpen(!open)} style={[styles.trigger, { borderColor: theme.border }]}>
      <ThemedText style={styles.title}>{title}</ThemedText>
      <ThemedText accessible={false} themeColor="interactive">{open ? '−' : '+'}</ThemedText>
    </AnimatedPressable>
    {open ? <View style={styles.content}>{children}</View> : null}
  </View>;
}
const styles = StyleSheet.create({
  container: { gap: 12 }, trigger: { minHeight: 56, paddingVertical: 14, borderBottomWidth: StyleSheet.hairlineWidth, flexDirection: 'row', alignItems: 'center', gap: 16 },
  title: { flex: 1, fontWeight: '600' }, content: { gap: 14 },
});
