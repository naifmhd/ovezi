import { router, type Href } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import type { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import { HeaderAction } from '@/components/ui/header-action';
import { Spacing, TouchTarget } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

/** Leading navigation control; form dismissal uses Cancel instead. */
export function BackButton({ fallback = '/(app)', onPress }: { fallback?: Href; onPress?: () => void }) {
  const theme = useTheme();
  return <HeaderAction accessibilityLabel="Back" onPress={onPress ?? (() => router.canGoBack() ? router.back() : router.replace(fallback))} style={styles.button}>
    <SymbolView name={{ ios: 'chevron.left', android: 'arrow_back', web: 'arrow_back' }} size={24} tintColor={theme.interactive} />
  </HeaderAction>;
}
export function BackNavigation({ fallback, children }: { fallback?: Href; children?: ReactNode }) {
  return <View style={styles.navigation}><BackButton fallback={fallback} />{children}</View>;
}
const styles = StyleSheet.create({ navigation: { minHeight: 60, paddingHorizontal: Spacing.four, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: Spacing.two }, button: { width: TouchTarget, minHeight: TouchTarget, paddingVertical: 0, alignItems: 'center', justifyContent: 'center' } });
