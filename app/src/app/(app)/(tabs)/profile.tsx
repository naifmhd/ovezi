import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { StyleSheet, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { UserAvatar } from '@/components/user-avatar';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { DEFAULT_CURRENCY_CODE } from '@/constants/currencies';
import { exportPersonalData, sendVerificationEmail } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { sharePersonalData } from '@/lib/share-text-file';
import { useAuthStore } from '@/stores/auth-store';

export default function ProfileScreen() {
  const theme = useTheme();
  const user = useAuthStore((state) => state.user)!;
  const token = useAuthStore((state) => state.token)!;
  const logout = useAuthStore((state) => state.logout);
  const verificationMutation = useMutation({
    mutationFn: () => sendVerificationEmail(token),
  });
  const exportMutation = useMutation({
    mutationFn: async () => sharePersonalData(await exportPersonalData(token)),
  });

  return (
    <AppScreen branded title="Profile">
      <ThemedView type="backgroundElement" style={styles.profileCard}>
        <UserAvatar imageUrl={user.avatar_url} name={user.name} size={58} />
        <View style={styles.profileCopy}>
          <ThemedText style={styles.name}>{user.name}</ThemedText>
          <ThemedText style={styles.email} themeColor="textSecondary">
            {user.email}
          </ThemedText>
        </View>
      </ThemedView>

      <ThemedView type="backgroundElement" style={styles.detailsCard}>
        <View style={styles.detailRow}>
          <ThemedText themeColor="textSecondary">Default currency</ThemedText>
          <ThemedText style={styles.detailValue}>
            {user.default_currency_code ?? DEFAULT_CURRENCY_CODE}
          </ThemedText>
        </View>
        <View style={styles.divider} />
        <View style={styles.detailRow}>
          <ThemedText themeColor="textSecondary">Email status</ThemedText>
          <ThemedText
            style={styles.detailValue}
            themeColor={user.email_verified_at ? 'positive' : 'danger'}>
            {user.email_verified_at ? 'Verified' : 'Not verified'}
          </ThemedText>
        </View>
        {!user.email_verified_at ? (
          <AnimatedPressable accessibilityRole="button" onPress={() => verificationMutation.mutate()}>
            <ThemedText style={styles.verifyAction} themeColor="interactive">
              {verificationMutation.isPending ? 'Sending…' : 'Resend verification email'}
            </ThemedText>
          </AnimatedPressable>
        ) : null}
      </ThemedView>

      {verificationMutation.isSuccess ? (
        <ThemedText accessibilityLiveRegion="polite" themeColor="positive">A new verification link was sent.</ThemedText>
      ) : null}
      {verificationMutation.error ? (
        <ThemedText themeColor="danger">{errorMessage(verificationMutation.error)}</ThemedText>
      ) : null}
      {exportMutation.error ? (
        <ThemedText themeColor="danger">{errorMessage(exportMutation.error)}</ThemedText>
      ) : null}

      <ThemedText style={styles.sectionLabel} themeColor="textSecondary">ACCOUNT & PREFERENCES</ThemedText>
      <ThemedView type="backgroundElement" style={styles.menuCard}>
        <ProfileLink
          icon={{ ios: 'person.crop.circle', android: 'account_circle', web: 'account_circle' }}
          label="Edit profile"
          tone="blue"
          onPress={() => router.push('/(app)/profile/edit')}
          subtitle="Name and default currency"
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'person.2.fill', android: 'group', web: 'group' }}
          label="Friends"
          tone="violet"
          onPress={() => router.push('/(app)/friends')}
          subtitle="Requests and people you split with"
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'lock.shield.fill', android: 'shield_lock', web: 'shield_lock' }}
          label="Security & account"
          tone="blue"
          onPress={() => router.push('/(app)/profile/security')}
          subtitle="Password, email, sessions, and deletion"
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'bell.fill', android: 'notifications', web: 'notifications' }}
          label="Notifications"
          tone="amber"
          onPress={() => router.push('/(app)/profile/notifications')}
          subtitle="Device, event, and group preferences"
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'repeat', android: 'event_repeat', web: 'event_repeat' }}
          label="Recurring expenses"
          tone="coral"
          onPress={() => router.push('/(app)/profile/recurring-expenses')}
          subtitle="Edit, pause, or cancel future expenses"
        />
      </ThemedView>

      <ThemedText style={styles.sectionLabel} themeColor="textSecondary">DATA & PRIVACY</ThemedText>
      <ThemedView type="backgroundElement" style={styles.menuCard}>
        <ProfileLink
          icon={{ ios: 'square.and.arrow.down', android: 'download', web: 'download' }}
          label="Export my data"
          tone="blue"
          onPress={() => {
            if (!exportMutation.isPending) exportMutation.mutate();
          }}
          subtitle={exportMutation.isPending ? 'Preparing your archive…' : 'Download a JSON archive'}
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'person.badge.clock.fill', android: 'manage_history', web: 'manage_history' }}
          label="Claim previous history"
          tone="violet"
          onPress={() => router.push('/(app)/profile/placeholder-claims')}
          subtitle="Find expenses recorded for your verified email"
        />
      </ThemedView>

      <ThemedText style={styles.sectionLabel} themeColor="textSecondary">SUPPORT</ThemedText>
      <ThemedView type="backgroundElement" style={styles.menuCard}>
        <ProfileLink
          icon={{ ios: 'info.circle.fill', android: 'info', web: 'info' }}
          label="About Ovezi"
          onPress={() => router.push('/(app)/profile/about')}
          subtitle="Version and product information"
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'hand.raised.fill', android: 'privacy_tip', web: 'privacy_tip' }}
          label="Privacy"
          tone="blue"
          onPress={() => router.push('/(app)/profile/privacy')}
          subtitle="How Ovezi handles information"
        />
        <View style={styles.divider} />
        <ProfileLink
          icon={{ ios: 'doc.text.fill', android: 'description', web: 'description' }}
          label="Terms"
          tone="amber"
          onPress={() => router.push('/(app)/profile/terms')}
          subtitle="Rules for using Ovezi"
        />
      </ThemedView>

      <AnimatedPressable accessibilityRole="button" onPress={() => void logout()}>
        <ThemedView type="backgroundElement" style={[styles.signOut, { borderColor: theme.danger }]}>
          <ThemedText style={styles.signOutText} themeColor="danger">Sign out</ThemedText>
        </ThemedView>
      </AnimatedPressable>
    </AppScreen>
  );
}

function ProfileLink({
  icon,
  label,
  onPress,
  subtitle,
  tone = 'mint',
}: {
  icon: Parameters<typeof SymbolView>[0]['name'];
  label: string;
  onPress: () => void;
  subtitle: string;
  tone?: 'mint' | 'blue' | 'violet' | 'coral' | 'amber';
}) {
  const theme = useTheme();
  const colors = {
    mint: [theme.surfaceSubtle, theme.interactive],
    blue: [theme.accentBlueSurface, theme.accentBlue],
    violet: [theme.accentVioletSurface, theme.accentViolet],
    coral: [theme.accentCoralSurface, theme.accentCoral],
    amber: [theme.accentAmberSurface, theme.accentAmber],
  }[tone];
  return (
    <AnimatedPressable accessibilityHint={subtitle} accessibilityRole="button" onPress={onPress} style={styles.menuRow}>
      <View style={[styles.menuIcon, { backgroundColor: colors[0] }]}>
        <SymbolView name={icon} size={19} tintColor={colors[1]} weight="semibold" />
      </View>
      <View style={styles.profileCopy}>
        <ThemedText style={styles.menuLabel}>{label}</ThemedText>
        <ThemedText style={styles.menuSubtitle} themeColor="textSecondary">{subtitle}</ThemedText>
      </View>
      <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText>
    </AnimatedPressable>
  );
}

const styles = StyleSheet.create({
  profileCard: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 18, borderRadius: Radius.card },
  profileCopy: { flex: 1 },
  name: { fontSize: 19, lineHeight: 26, fontWeight: '600' },
  email: { fontSize: 14, lineHeight: 20 },
  detailsCard: { borderRadius: Radius.card, padding: 18, gap: 14 },
  detailRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  detailValue: { fontWeight: '600' },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.35 },
  verifyAction: { fontSize: 14, fontWeight: '600' },
  signOut: { padding: 17, borderRadius: Radius.card, borderWidth: StyleSheet.hairlineWidth, alignItems: 'center', marginTop: 10 },
  signOutText: { fontWeight: '600' },
  sectionLabel: { marginTop: 8, fontSize: 12, lineHeight: 17, fontWeight: '600', letterSpacing: 0.7 },
  menuCard: { borderRadius: Radius.card, paddingHorizontal: 14 },
  menuRow: { minHeight: 68, flexDirection: 'row', alignItems: 'center', gap: 12 },
  menuIcon: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  menuLabel: { fontSize: 14, fontWeight: '600' },
  menuSubtitle: { fontSize: 12, lineHeight: 17 },
  chevron: { fontSize: 23, fontWeight: '500' },
});
