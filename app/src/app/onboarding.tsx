import { Redirect, router } from 'expo-router';
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { PrimaryButton } from '@/components/auth/primary-button';
import { BrandMark } from '@/components/brand-mark';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';
import { useOnboardingStore } from '@/stores/onboarding-store';

const steps = [
  {
    marker: '01',
    title: 'Split without limits',
    description: 'Share group or one-to-one expenses using equal, exact, percentage, or share-based splits.',
  },
  {
    marker: '02',
    title: 'Keep everyone together',
    description: 'Invite friends or add guests now. Balances and activity stay clear as your group changes.',
  },
  {
    marker: '03',
    title: 'Settle with clarity',
    description: 'See the simplest way to settle, then record payments made outside Ovezi. Your money never moves through the app.',
  },
] as const;

export default function OnboardingScreen() {
  const theme = useTheme();
  const token = useAuthStore((state) => state.token);
  const completed = useOnboardingStore((state) => state.completed);
  const complete = useOnboardingStore((state) => state.complete);
  const [stepIndex, setStepIndex] = useState(0);
  const [finishing, setFinishing] = useState(false);
  const step = steps[stepIndex];

  if (token) return <Redirect href="/(app)" />;
  if (completed) return <Redirect href="/(auth)/sign-in" />;

  async function finish(destination: '/(auth)/sign-in' | '/(auth)/sign-up') {
    setFinishing(true);
    await complete();
    router.replace(destination);
  }

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.topBar}>
          <View style={styles.brandRow}>
            <BrandMark size={36} />
            <ThemedText style={styles.wordmark}>Ovezi</ThemedText>
          </View>
          <Pressable disabled={finishing} onPress={() => void finish('/(auth)/sign-in')}>
            <ThemedText style={styles.skip} themeColor="textSecondary">Skip</ThemedText>
          </Pressable>
        </View>

        <View style={styles.content}>
          <View style={[styles.visual, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
            <View style={[styles.marker, { backgroundColor: theme.backgroundSelected }]}>
              <ThemedText style={styles.markerText} themeColor="primary">{step.marker}</ThemedText>
            </View>
            <BrandMark size={116} />
            <ThemedText style={styles.tagline} themeColor="textSecondary">Split. Share. Settle.</ThemedText>
          </View>

          <View style={styles.copy}>
            <ThemedText style={styles.title}>{step.title}</ThemedText>
            <ThemedText style={styles.description} themeColor="textSecondary">
              {step.description}
            </ThemedText>
          </View>

          <View accessibilityLabel={`Step ${stepIndex + 1} of ${steps.length}`} style={styles.dots}>
            {steps.map((item, index) => (
              <View
                key={item.marker}
                style={[
                  styles.dot,
                  { backgroundColor: index === stepIndex ? theme.primary : theme.border },
                  index === stepIndex && styles.activeDot,
                ]}
              />
            ))}
          </View>
        </View>

        <View style={styles.actions}>
          <PrimaryButton
            disabled={finishing}
            label={stepIndex === steps.length - 1 ? 'Create an account' : 'Continue'}
            loading={finishing}
            onPress={() => {
              if (stepIndex < steps.length - 1) {
                setStepIndex((current) => current + 1);
              } else {
                void finish('/(auth)/sign-up');
              }
            }}
          />
          <Pressable disabled={finishing} onPress={() => void finish('/(auth)/sign-in')} style={styles.signIn}>
            <ThemedText themeColor="textSecondary">
              Already use Ovezi? <ThemedText style={styles.signInLabel} themeColor="primary">Sign in</ThemedText>
            </ThemedText>
          </Pressable>
        </View>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1, width: '100%', maxWidth: 620, alignSelf: 'center' },
  topBar: {
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.three,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  brandRow: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  wordmark: { fontSize: 22, lineHeight: 28, fontWeight: '800', letterSpacing: -0.5 },
  skip: { fontSize: 14, lineHeight: 20, fontWeight: '700', padding: Spacing.two },
  content: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: Spacing.four,
    paddingBottom: Spacing.four,
    gap: Spacing.four,
  },
  visual: {
    minHeight: 285,
    borderWidth: StyleSheet.hairlineWidth,
    borderRadius: 32,
    alignItems: 'center',
    justifyContent: 'center',
    gap: Spacing.three,
    overflow: 'hidden',
  },
  marker: {
    position: 'absolute',
    top: 18,
    right: 18,
    minWidth: 46,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  markerText: { fontSize: 13, lineHeight: 17, fontWeight: '900', letterSpacing: 1 },
  tagline: { fontSize: 13, lineHeight: 18, fontWeight: '700', letterSpacing: 1.1 },
  copy: { gap: Spacing.two },
  title: { fontSize: 34, lineHeight: 40, fontWeight: '900', letterSpacing: -1 },
  description: { fontSize: 16, lineHeight: 24 },
  dots: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 },
  dot: { width: 7, height: 7, borderRadius: 4 },
  activeDot: { width: 24 },
  actions: { paddingHorizontal: Spacing.four, paddingBottom: Spacing.three, gap: Spacing.three },
  signIn: { minHeight: 40, alignItems: 'center', justifyContent: 'center' },
  signInLabel: { fontWeight: '800' },
});
