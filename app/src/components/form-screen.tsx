import { PropsWithChildren, ReactNode } from 'react';
import { router, type Href } from 'expo-router';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { HeaderAction } from '@/components/ui/header-action';
import { PlatformMaterial } from '@/components/ui/platform-material';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

/** Content shell for a form presented by the native router as a sheet/modal. */
export function FormScreen({ title, children, footer, fallback = '/(app)' }: PropsWithChildren<{ title: string; footer?: ReactNode; fallback?: Href }>) {
  const insets = useSafeAreaInsets();
  return <ThemedView style={styles.flex}>
    <SafeAreaView edges={['top']} style={styles.flex}>
      <View style={styles.header}>
        <HeaderAction onPress={() => router.canGoBack() ? router.back() : router.replace(fallback)}>
          <ThemedText themeColor="interactive" style={styles.cancel}>Cancel</ThemedText>
        </HeaderAction>
        <ThemedText accessibilityRole="header" style={styles.title}>{title}</ThemedText>
        <View style={styles.spacer} />
      </View>
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}
          contentContainerStyle={[styles.content, { paddingBottom: footer ? Spacing.four : insets.bottom + Spacing.four }]}>
          {children}
        </ScrollView>
        {footer ? <PlatformMaterial glassStyle="regular" style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) }]}>{footer}</PlatformMaterial> : null}
      </KeyboardAvoidingView>
    </SafeAreaView>
  </ThemedView>;
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  header: { minHeight: 60, paddingHorizontal: Spacing.four, flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  cancel: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  title: { flex: 1, fontSize: 17, lineHeight: 24, fontWeight: '600', textAlign: 'center' },
  spacer: { width: 48 },
  content: { padding: Spacing.four, gap: Spacing.three },
  footer: { paddingHorizontal: Spacing.four, paddingTop: 12, gap: Spacing.two },
});
