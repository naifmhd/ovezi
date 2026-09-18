import { useMutation } from '@tanstack/react-query';
import { Pressable, StyleSheet, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { sendVerificationEmail } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

export default function ProfileScreen() {
  const user = useAuthStore((state) => state.user)!;
  const token = useAuthStore((state) => state.token)!;
  const logout = useAuthStore((state) => state.logout);
  const verificationMutation = useMutation({
    mutationFn: () => sendVerificationEmail(token),
  });

  return (
    <AppScreen title="Profile">
      <ThemedView type="backgroundElement" style={styles.profileCard}>
        <ThemedView type="backgroundSelected" style={styles.avatar}>
          <ThemedText style={styles.initial} themeColor="primary">
            {user.name.slice(0, 1).toUpperCase()}
          </ThemedText>
        </ThemedView>
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
          <ThemedText style={styles.detailValue}>{user.default_currency_code}</ThemedText>
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

const styles = StyleSheet.create({
  profileCard: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 18, borderRadius: 22 },
  avatar: { width: 58, height: 58, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
  initial: { fontSize: 25, lineHeight: 31, fontWeight: '800' },
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
});
