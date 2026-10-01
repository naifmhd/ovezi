import { PropsWithChildren, ReactNode } from 'react';
import { ScrollView, ScrollViewProps, StyleSheet, View } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';

import { BrandLockup } from '@/components/brand-mark';
import { TabExpenseAction, tabContentBottomPadding } from '@/components/tab-expense-action';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing, Typography } from '@/constants/theme';

type AppScreenProps = PropsWithChildren<{
  title: string;
  eyebrow?: string;
  action?: ReactNode;
  branded?: boolean;
  tabScreen?: boolean;
  scrollProps?: ScrollViewProps;
}>;

export function AppScreen({ title, eyebrow, action, branded = false, tabScreen = false, children, scrollProps }: AppScreenProps) {
  const insets = useSafeAreaInsets();
  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        {branded ? <View style={styles.brandBar}><BrandLockup size={24} />{action}</View> : null}
        <ScrollView
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          {...scrollProps}
          contentContainerStyle={[styles.content, { paddingBottom: tabScreen ? tabContentBottomPadding : insets.bottom + 32 }, scrollProps?.contentContainerStyle]}>
          <View style={styles.header}>
          <View style={styles.heading}>
            {eyebrow ? (
              <ThemedText style={styles.eyebrow} themeColor="textSecondary">
                {eyebrow}
              </ThemedText>
            ) : null}
            <ThemedText accessibilityRole="header" style={styles.title}>{title}</ThemedText>
          </View>
          {!branded ? action : null}
          </View>
          {children}
        </ScrollView>
        {tabScreen ? <TabExpenseAction /> : null}
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  brandBar: { paddingHorizontal: Spacing.four, paddingTop: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 16, minHeight: 56 },
  header: {
    minHeight: 72,
    paddingTop: Spacing.three,
    paddingBottom: Spacing.three,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.three,
  },
  heading: { flex: 1 },
  eyebrow: { fontSize: 14, lineHeight: 20, marginBottom: 6 },
  title: { ...Typography.heading },
  content: {
    flexGrow: 1,
    paddingHorizontal: Spacing.four,
    gap: 12,
  },
});
