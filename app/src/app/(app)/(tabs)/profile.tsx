import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { UserAvatar } from '@/components/user-avatar';
import { DEFAULT_CURRENCY_CODE } from '@/constants/currencies';
import { exportPersonalData, sendVerificationEmail } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { sharePersonalData } from '@/lib/share-text-file';
import { useAuthStore } from '@/stores/auth-store';

export default function ProfileScreen() {
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
    <AppScreen title="Profile">
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
            themeColor={user.email_verified_at ? 'primary' : 'danger'}>
            {user.email_verified_at ? 'Verified' : 'Not verified'}
          </ThemedText>
        </View>
        {!user.email_verified_at ? (
          <Pressable onPress={() => verificationMutation.mutate()}>
            <ThemedText style={styles.verifyAction} themeColor="primary">
              {verificationMutation.isPending ? 'Sending…' : 'Resend verification email'}
            </ThemedText>
          </Pressable>
        ) : null}
      </ThemedView>

      {verificationMutation.isSuccess ? (
        <ThemedText themeColor="primary">A new verification link was sent.</ThemedText>
      ) : null}
      {verificationMutation.error ? (
        <ThemedText themeColor="danger">{errorMessage(verificationMutation.error)}</ThemedText>
      ) : null}
      {exportMutation.error ? (
        <ThemedText themeColor="danger">{errorMessage(exportMutation.error)}</ThemedText>
      ) : null}

      <ThemedView type="backgroundElement" style={styles.menuCard}>
        <ProfileLink
          label="Edit profile"
          onPress={() => router.push('/(app)/profile/edit')}
          subtitle="Name and default currency"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Friends"
          onPress={() => router.push('/(app)/friends')}
          subtitle="Requests and people you split with"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Security & account"
          onPress={() => router.push('/(app)/profile/security')}
          subtitle="Password, email, sessions, and deletion"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Notifications"
          onPress={() => router.push('/(app)/profile/notifications')}
          subtitle="Device, event, and group preferences"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Recurring expenses"
          onPress={() => router.push('/(app)/profile/recurring-expenses')}
          subtitle="Edit, pause, or cancel future expenses"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Export my data"
          onPress={() => {
            if (!exportMutation.isPending) exportMutation.mutate();
          }}
          subtitle={exportMutation.isPending ? 'Preparing your archive…' : 'Download a JSON archive'}
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Claim previous history"
          onPress={() => router.push('/(app)/profile/placeholder-claims')}
          subtitle="Find expenses recorded for your verified email"
        />
      </ThemedView>

      <ThemedView type="backgroundElement" style={styles.menuCard}>
        <ProfileLink
          label="About Ovezi"
          onPress={() => router.push('/(app)/profile/about')}
          subtitle="Version and product information"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Privacy"
          onPress={() => router.push('/(app)/profile/privacy')}
          subtitle="How Ovezi handles information"
        />
        <View style={styles.divider} />
        <ProfileLink
          label="Terms"
          onPress={() => router.push('/(app)/profile/terms')}
          subtitle="Rules for using Ovezi"
        />
      </ThemedView>

      <Pressable onPress={() => void logout()}>
        {({ pressed }) => (
          <ThemedView type="backgroundElement" style={[styles.signOut, pressed && styles.pressed]}>
            <ThemedText style={styles.signOutText} themeColor="danger">
              Sign out
            </ThemedText>
          </ThemedView>
        )}
      </Pressable>
    </AppScreen>
  );
}

function ProfileLink({ label, onPress, subtitle }: { label: string; onPress: () => void; subtitle: string }) {
  return (
    <Pressable onPress={onPress} style={styles.menuRow}>
      <View style={styles.profileCopy}>
        <ThemedText style={styles.menuLabel}>{label}</ThemedText>
        <ThemedText style={styles.menuSubtitle} themeColor="textSecondary">{subtitle}</ThemedText>
      </View>
      <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  profileCard: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 18, borderRadius: 22 },
  profileCopy: { flex: 1 },
  name: { fontSize: 19, lineHeight: 26, fontWeight: '800' },
  email: { fontSize: 14, lineHeight: 20 },
  detailsCard: { borderRadius: 22, padding: 18, gap: 14 },
  detailRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  detailValue: { fontWeight: '800' },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.35 },
  verifyAction: { fontSize: 14, fontWeight: '800' },
  signOut: { padding: 17, borderRadius: 18, alignItems: 'center', marginTop: 10 },
  signOutText: { fontWeight: '800' },
  pressed: { opacity: 0.65 },
  menuCard: { borderRadius: 22, paddingHorizontal: 17 },
  menuRow: { minHeight: 68, flexDirection: 'row', alignItems: 'center', gap: 12 },
  menuLabel: { fontSize: 14, fontWeight: '800' },
  menuSubtitle: { fontSize: 12, lineHeight: 17 },
  chevron: { fontSize: 23, fontWeight: '500' },
});
