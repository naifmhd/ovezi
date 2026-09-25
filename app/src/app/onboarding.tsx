import { Redirect, router } from 'expo-router';
import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Animated, {
  FadeInDown,
  ReduceMotion,
} from 'react-native-reanimated';
import { SafeAreaView } from 'react-native-safe-area-context';

import { PrimaryButton } from '@/components/auth/primary-button';
import { BrandLockup } from '@/components/brand-mark';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Motion, Radius, Spacing } from '@/constants/theme';
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
          <BrandLockup size={44} />
          <Pressable disabled={finishing} onPress={() => void finish('/(auth)/sign-in')}>
            <ThemedText style={styles.skip} themeColor="textSecondary">Skip</ThemedText>
          </Pressable>
        </View>

        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={[styles.visual, { backgroundColor: theme.backgroundElement, borderColor: theme.border }]}>
            <View style={[styles.marker, { backgroundColor: theme.backgroundSelected }]}>
              <ThemedText style={styles.markerText} themeColor="interactive">{step.marker}</ThemedText>
            </View>
            <Animated.View
              entering={FadeInDown.duration(Motion.standard).reduceMotion(ReduceMotion.System)}
              key={stepIndex}
              style={styles.demo}>
              {stepIndex === 0 ? <ExpenseDemo /> : null}
              {stepIndex === 1 ? <BalanceDemo /> : null}
              {stepIndex === 2 ? <SettlementDemo /> : null}
            </Animated.View>
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
        </ScrollView>

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
              Already use Ovezi? <ThemedText style={styles.signInLabel} themeColor="interactive">Sign in</ThemedText>
            </ThemedText>
          </Pressable>
        </View>
      </SafeAreaView>
    </ThemedView>
  );
}

function ExpenseDemo() {
  return (
    <>
      <ThemedText style={styles.demoEyebrow} themeColor="textSecondary">DINNER</ThemedText>
      <ThemedText style={styles.demoAmount}>MVR 1,200.00</ThemedText>
      <View style={styles.demoRule} />
      <ThemedText style={styles.demoSentence}>Paid by you · Split equally</ThemedText>
      <View style={styles.avatarRow}>
        {['You', 'M', 'A'].map((label) => <DemoAvatar key={label} label={label} />)}
      </View>
    </>
  );
}

function BalanceDemo() {
  return (
    <>
      <ThemedText style={styles.demoEyebrow} themeColor="textSecondary">TRIP BALANCES</ThemedText>
      <DemoBalance label="Maya owes you" value="$38.00" positive />
      <DemoBalance label="You owe Amir" value="$22.00" />
      <View style={styles.demoRule} />
      <View style={styles.balanceLine}>
        <ThemedText themeColor="textSecondary">Your net position</ThemedText>
        <ThemedText style={styles.netAmount} themeColor="positive">+$16.00</ThemedText>
      </View>
    </>
  );
}

function SettlementDemo() {
  return (
    <>
      <View style={styles.settleAvatars}>
        <DemoAvatar label="A" />
        <View style={styles.arrowLine}><ThemedText themeColor="interactive">→</ThemedText></View>
        <DemoAvatar label="You" />
      </View>
      <ThemedText style={styles.demoAmount}>$22.00</ThemedText>
      <ThemedText style={styles.demoSentence}>Amir pays you</ThemedText>
      <View style={styles.savedPill}>
        <ThemedText style={styles.savedText} themeColor="positive">✓ Ready to settle</ThemedText>
      </View>
    </>
  );
}

function DemoAvatar({ label }: { label: string }) {
  const theme = useTheme();
  return (
    <View style={[styles.demoAvatar, { backgroundColor: theme.backgroundSelected, borderColor: theme.border }]}>
      <ThemedText style={styles.demoAvatarText} themeColor="interactive">{label}</ThemedText>
    </View>
  );
}

function DemoBalance({ label, positive = false, value }: { label: string; positive?: boolean; value: string }) {
  return (
    <View style={styles.balanceLine}>
      <ThemedText>{label}</ThemedText>
      <ThemedText style={styles.balanceValue} themeColor={positive ? 'positive' : 'danger'}>{value}</ThemedText>
    </View>
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
  wordmark: { fontSize: 22, lineHeight: 28, fontWeight: '600', letterSpacing: -0.5 },
  skip: { fontSize: 14, lineHeight: 20, fontWeight: '600', padding: 14 },
  content: {
    flexGrow: 1,
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
    padding: Spacing.four,
    overflow: 'hidden',
  },
  demo: { width: '100%', maxWidth: 360, alignItems: 'center', gap: 12 },
  demoEyebrow: { fontSize: 12, lineHeight: 16, fontWeight: '600', letterSpacing: 1.2 },
  demoAmount: { fontSize: 40, lineHeight: 48, fontWeight: '600', letterSpacing: -1.2 },
  demoSentence: { fontSize: 15, lineHeight: 21, fontWeight: '600' },
  demoRule: { height: StyleSheet.hairlineWidth, width: '100%', backgroundColor: '#7A8498', opacity: 0.32 },
  avatarRow: { flexDirection: 'row', gap: 8 },
  demoAvatar: {
    minWidth: 42,
    height: 42,
    paddingHorizontal: 9,
    borderRadius: Radius.pill,
    borderWidth: StyleSheet.hairlineWidth,
    alignItems: 'center',
    justifyContent: 'center',
  },
  demoAvatarText: { fontSize: 12, lineHeight: 16, fontWeight: '600' },
  balanceLine: { width: '100%', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  balanceValue: { fontWeight: '600' },
  netAmount: { fontSize: 18, lineHeight: 24, fontWeight: '600' },
  settleAvatars: { flexDirection: 'row', alignItems: 'center' },
  arrowLine: { width: 72, height: 1, alignItems: 'center', justifyContent: 'center' },
  savedPill: { borderRadius: Radius.pill, backgroundColor: 'rgba(6, 118, 71, 0.10)', paddingHorizontal: 14, paddingVertical: 8 },
  savedText: { fontSize: 13, lineHeight: 18, fontWeight: '600' },
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
  markerText: { fontSize: 13, lineHeight: 17, fontWeight: '600', letterSpacing: 1 },
  tagline: { fontSize: 13, lineHeight: 18, fontWeight: '600', letterSpacing: 1.1 },
  copy: { gap: Spacing.two },
  title: { fontSize: 34, lineHeight: 40, fontWeight: '600', letterSpacing: -1 },
  description: { fontSize: 16, lineHeight: 24 },
  dots: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 },
  dot: { width: 7, height: 7, borderRadius: 4 },
  activeDot: { width: 24 },
  actions: { paddingHorizontal: Spacing.four, paddingBottom: Spacing.three, gap: Spacing.three },
  signIn: { minHeight: 48, alignItems: 'center', justifyContent: 'center' },
  signInLabel: { fontWeight: '600' },
});
