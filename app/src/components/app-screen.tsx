import { PropsWithChildren, ReactNode } from 'react';
import { ScrollView, ScrollViewProps, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

type AppScreenProps = PropsWithChildren<{
  title: string;
  eyebrow?: string;
  action?: ReactNode;
  scrollProps?: ScrollViewProps;
}>;

export function AppScreen({ title, eyebrow, action, children, scrollProps }: AppScreenProps) {
  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <View style={styles.heading}>
            {eyebrow ? (
              <ThemedText style={styles.eyebrow} themeColor="textSecondary">
                {eyebrow}
              </ThemedText>
            ) : null}
            <ThemedText style={styles.title}>{title}</ThemedText>
          </View>
          {action}
        </View>
        <ScrollView
          contentContainerStyle={styles.content}
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
  title: { fontSize: 30, lineHeight: 38, fontWeight: '800', letterSpacing: -0.7 },
  content: {
    flexGrow: 1,
    paddingHorizontal: Spacing.four,
    paddingBottom: 120,
    gap: Spacing.three,
  },
});
