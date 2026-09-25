import { PropsWithChildren, ReactNode } from 'react';
import { ScrollView, ScrollViewProps, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { BrandMark } from '@/components/brand-mark';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

type AppScreenProps = PropsWithChildren<{
  title: string;
  eyebrow?: string;
  action?: ReactNode;
  branded?: boolean;
  scrollProps?: ScrollViewProps;
}>;

export function AppScreen({ title, eyebrow, action, branded = false, children, scrollProps }: AppScreenProps) {
  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          {branded ? <BrandMark decorative finish="soft" size={36} /> : null}
          <View style={styles.heading}>
            {eyebrow ? (
              <ThemedText style={styles.eyebrow} themeColor="textSecondary">
                {eyebrow}
              </ThemedText>
            ) : null}
            <ThemedText accessibilityRole="header" style={styles.title}>{title}</ThemedText>
          </View>
          {action}
        </View>
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          {...scrollProps}>
          {children}
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  brandBar: { paddingHorizontal: Spacing.four, paddingTop: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 16, minHeight: 56 },
  header: {
    minHeight: 82,
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.three,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.three,
  },
  heading: { flex: 1 },
  eyebrow: { fontSize: 13, lineHeight: 18, fontWeight: '600' },
  title: { fontSize: 28, lineHeight: 36, fontWeight: '600', letterSpacing: -0.7 },
  content: {
    flexGrow: 1,
    paddingHorizontal: Spacing.four,
    paddingBottom: 120,
    gap: Spacing.three,
  },
});
