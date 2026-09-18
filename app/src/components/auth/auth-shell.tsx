import { PropsWithChildren, ReactNode } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BrandMark } from '@/components/brand-mark';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

type AuthShellProps = PropsWithChildren<{
  title: string;
  subtitle: string;
  footer?: ReactNode;
}>;

export function AuthShell({ title, subtitle, children, footer }: AuthShellProps) {
  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView style={styles.safeArea}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.keyboard}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            <View style={styles.brand}>
              <BrandMark size={58} />
              <ThemedText style={styles.wordmark}>Ovezi</ThemedText>
              <ThemedText themeColor="textSecondary" style={styles.tagline}>
                Split. Share. Settle.
              </ThemedText>
            </View>

            <View style={styles.heading}>
              <ThemedText style={styles.title}>{title}</ThemedText>
              <ThemedText themeColor="textSecondary">{subtitle}</ThemedText>
            </View>

            <View style={styles.form}>{children}</View>
            {footer ? <View style={styles.footer}>{footer}</View> : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  keyboard: { flex: 1 },
  content: {
    flexGrow: 1,
    width: '100%',
    maxWidth: 520,
    alignSelf: 'center',
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.five,
  },
  brand: { alignItems: 'center', marginBottom: 44 },
  wordmark: { fontSize: 28, lineHeight: 34, fontWeight: '800', letterSpacing: -0.6 },
  tagline: { fontSize: 13, lineHeight: 18, fontWeight: '600', letterSpacing: 0.8 },
  heading: { gap: Spacing.two, marginBottom: Spacing.four },
  title: { fontSize: 30, lineHeight: 38, fontWeight: '800', letterSpacing: -0.7 },
  form: { gap: Spacing.three },
  footer: { marginTop: 'auto', paddingTop: Spacing.five, alignItems: 'center' },
});
