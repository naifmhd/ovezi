import { router } from 'expo-router';
import type { ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';

type DocumentSection = {
  title: string;
  paragraphs: string[];
};

export function DocumentScreen({
  eyebrow,
  hero,
  intro,
  sections,
  title,
}: {
  eyebrow?: string;
  hero?: ReactNode;
  intro: string;
  sections: DocumentSection[];
  title: string;
}) {
  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <Pressable accessibilityRole="button" onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </Pressable>
          <ThemedText style={styles.headerTitle}>{title}</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          {hero}
          {eyebrow ? (
            <ThemedText style={styles.eyebrow} themeColor="primary">{eyebrow}</ThemedText>
          ) : null}
          <ThemedText style={styles.intro}>{intro}</ThemedText>
          {sections.map((section) => (
            <ThemedView key={section.title} type="backgroundElement" style={styles.card}>
              <ThemedText style={styles.sectionTitle}>{section.title}</ThemedText>
              {section.paragraphs.map((paragraph) => (
                <ThemedText key={paragraph} style={styles.paragraph} themeColor="textSecondary">
                  {paragraph}
                </ThemedText>
              ))}
            </ThemedView>
          ))}
        </ScrollView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  header: {
    height: 60,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: Spacing.four,
  },
  back: { fontSize: 14, fontWeight: '800' },
  headerTitle: { fontSize: 17, fontWeight: '800' },
  headerSpacer: { width: 48 },
  content: {
    padding: Spacing.four,
    paddingBottom: 80,
    gap: 14,
    maxWidth: 680,
    width: '100%',
    alignSelf: 'center',
  },
  eyebrow: { fontSize: 12, lineHeight: 18, fontWeight: '900', letterSpacing: 0.8, textTransform: 'uppercase' },
  intro: { fontSize: 18, lineHeight: 27, fontWeight: '700' },
  card: { borderRadius: 20, padding: 18, gap: 9 },
  sectionTitle: { fontSize: 16, lineHeight: 22, fontWeight: '900' },
  paragraph: { fontSize: 14, lineHeight: 21 },
});
