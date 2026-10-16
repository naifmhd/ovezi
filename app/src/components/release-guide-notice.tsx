import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import { LendingAction } from '@/components/lending-ui';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { dismissReleaseGuide, isReleaseGuideDismissed } from '@/lib/release-guide';

export function ReleaseGuideNotice({ userId }: { userId: number }) {
  const theme = useTheme();
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    let active = true;
    void isReleaseGuideDismissed(userId).then(seen => { if (active) setVisible(!seen); });
    return () => { active = false; };
  }, [userId]);
  function dismiss() { setVisible(false); void dismissReleaseGuide(userId); }
  if (!visible) return null;
  return <View style={[styles.notice, { borderColor: theme.border }]}>
    <ThemedText accessibilityRole="header" style={styles.title}>Find your way around</ThemedText>
    <ThemedText themeColor="textSecondary">Home, Groups and Lending have their own place. Your previous records are in Profile.</ThemedText>
    <View style={styles.actions}>
      <LendingAction label="See what’s changed" onPress={() => { dismiss(); router.push('/(app)/profile/guide'); }} />
      <LendingAction label="Dismiss introduction" onPress={dismiss} />
    </View>
  </View>;
}
const styles = StyleSheet.create({
  notice: { paddingVertical: 16, gap: 8, borderBottomWidth: StyleSheet.hairlineWidth },
  title: { fontSize: 20, lineHeight: 28, fontWeight: '600' },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
});
