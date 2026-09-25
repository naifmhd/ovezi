import { HeaderAction } from '@/components/ui/header-action';
import { useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { SocialSignIn } from '@/components/auth/social-sign-in';
import * as Linking from 'expo-linking';
import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Spacing } from '@/constants/theme';
import {
  deleteAccount,
  disconnectSocialAccount,
  logoutAllSessions,
  updateEmail,
  updatePassword,
} from '@/lib/auth-api';
import { apiBaseUrl, errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

type FormSection = 'password' | 'email' | null;

export default function SecurityScreen() {
  const token = useAuthStore((state) => state.token)!;
  const user = useAuthStore((state) => state.user)!;
  const refreshUser = useAuthStore((state) => state.refreshUser);
  const logout = useAuthStore((state) => state.logout);
  const [section, setSection] = useState<FormSection>(null);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [emailConfirmation, setEmailConfirmation] = useState('');
  const [success, setSuccess] = useState<string | null>(null);
  const [disconnectTarget, setDisconnectTarget] = useState<'google' | 'apple' | null>(null);
  const [confirmAllSessions, setConfirmAllSessions] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');
  const [deleteError, setDeleteError] = useState<string | null>(null);

  function clearCredentials() {
    setCurrentPassword('');
    setNewPassword('');
    setPasswordConfirmation('');
    setNewEmail('');
    setEmailConfirmation('');
  }

  const passwordMutation = useMutation({
    mutationFn: () => updatePassword(token, {
      currentPassword,
      password: newPassword,
      passwordConfirmation,
    }),
    onSuccess: () => {
      clearCredentials();
      setSection(null);
      setSuccess('Your password was changed and other sessions were signed out.');
    },
  });
  const emailMutation = useMutation({
    mutationFn: () => updateEmail(token, {
      currentPassword,
      email: newEmail.trim().toLowerCase(),
      emailConfirmation: emailConfirmation.trim().toLowerCase(),
    }),
    onSuccess: async () => {
      await refreshUser();
      clearCredentials();
      setSection(null);
      setSuccess('Your email was changed. Check your inbox to verify it.');
    },
  });
  const sessionsMutation = useMutation({
    mutationFn: () => logoutAllSessions(token),
    onSuccess: () => logout(),
  });
  const disconnectMutation = useMutation({
    mutationFn: (provider: 'google' | 'apple') => disconnectSocialAccount(token, provider),
    onSuccess: async () => {
      await refreshUser();
      setDisconnectTarget(null);
      setSuccess('The login provider was disconnected.');
    },
  });
  const deleteMutation = useMutation({
    mutationFn: () => deleteAccount(token, deletePassword),
    onSuccess: () => logout(),
  });
  const mutationError = passwordMutation.error
    ?? emailMutation.error
    ?? sessionsMutation.error
    ?? disconnectMutation.error
    ?? deleteMutation.error;

  return (
    <ThemedView style={styles.screen}>
      <SafeAreaView edges={['top']} style={styles.safeArea}>
        <View style={styles.header}>
          <HeaderAction onPress={() => router.back()}>
            <ThemedText style={styles.back} themeColor="primary">‹ Back</ThemedText>
          </HeaderAction>
          <ThemedText style={styles.headerTitle}>Security & account</ThemedText>
          <View style={styles.headerSpacer} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
          <ScrollView
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}>
            {success ? (
              <ThemedView type="backgroundSelected" style={styles.successCard}>
                <ThemedText style={styles.successText} themeColor="primary">{success}</ThemedText>
              </ThemedView>
            ) : null}
            {mutationError ? <ThemedText themeColor="danger">{errorMessage(mutationError)}</ThemedText> : null}

            <SectionTitle title="Sign-in details" />
            <ThemedView type="backgroundElement" style={styles.card}>
              <SettingRow
                label="Email"
                onPress={user.has_password ? () => openSection('email') : undefined}
                value={user.email}
              />
              <Divider />
              <SettingRow
                label="Password"
                onPress={user.has_password ? () => openSection('password') : undefined}
                value={user.has_password ? 'Change password' : 'No password set'}
              />
            </ThemedView>
            {!user.has_password ? (
              <ThemedText style={styles.hint} themeColor="textSecondary">
                This account currently signs in through a connected provider, so password-confirmed actions are unavailable.
              </ThemedText>
            ) : null}

            {section === 'password' ? (
              <ThemedView type="backgroundElement" style={styles.formCard}>
                <ThemedText style={styles.formTitle}>Change password</ThemedText>
                <PasswordField label="Current password" onChangeText={setCurrentPassword} value={currentPassword} />
                <PasswordField label="New password" onChangeText={setNewPassword} value={newPassword} />
                <PasswordField label="Confirm new password" onChangeText={setPasswordConfirmation} value={passwordConfirmation} />
                <PrimaryButton
                  disabled={!currentPassword || !newPassword || newPassword !== passwordConfirmation}
                  label="Update password"
                  loading={passwordMutation.isPending}
                  onPress={() => passwordMutation.mutate()}
                />
              </ThemedView>
            ) : null}

            {section === 'email' ? (
              <ThemedView type="backgroundElement" style={styles.formCard}>
                <ThemedText style={styles.formTitle}>Change email</ThemedText>
                <PasswordField label="Current password" onChangeText={setCurrentPassword} value={currentPassword} />
                <FormField
                  autoCapitalize="none"
                  keyboardType="email-address"
                  label="New email"
                  onChangeText={setNewEmail}
                  value={newEmail}
                />
                <FormField
                  autoCapitalize="none"
                  keyboardType="email-address"
                  label="Confirm new email"
                  onChangeText={setEmailConfirmation}
                  value={emailConfirmation}
                />
                <PrimaryButton
                  disabled={!currentPassword || !newEmail || newEmail.toLowerCase() !== emailConfirmation.toLowerCase()}
                  label="Update email"
                  loading={emailMutation.isPending}
                  onPress={() => emailMutation.mutate()}
                />
              </ThemedView>
            ) : null}

            <SectionTitle title="Connected accounts" />
            <ThemedView type="backgroundElement" style={styles.card}>
              {user.connected_providers.length === 0 ? (
                <ThemedText style={styles.empty} themeColor="textSecondary">No connected providers.</ThemedText>
              ) : user.connected_providers.map((provider, index) => {
                const canDisconnect = user.has_password || user.connected_providers.length > 1;
                return (
                  <View key={provider}>
                    {index > 0 ? <Divider /> : null}
                    <View style={styles.providerRow}>
                      <View style={styles.rowCopy}>
                        <ThemedText style={styles.rowLabel}>{provider === 'google' ? 'Google' : 'Apple'}</ThemedText>
                        <ThemedText style={styles.rowValue} themeColor="textSecondary">Connected</ThemedText>
                      </View>
                      <Pressable
                        disabled={!canDisconnect}
                        onPress={() => setDisconnectTarget(provider)}>
                        <ThemedText
                          style={[styles.rowAction, !canDisconnect && styles.disabled]}
                          themeColor={canDisconnect ? 'danger' : 'textSecondary'}>
                          Disconnect
                        </ThemedText>
                      </Pressable>
                    </View>
                    {disconnectTarget === provider ? (
                      <ConfirmRow
                        confirmLabel="Disconnect"
                        loading={disconnectMutation.isPending}
                        message={`Stop using ${provider === 'google' ? 'Google' : 'Apple'} to sign in?`}
                        onCancel={() => setDisconnectTarget(null)}
                        onConfirm={() => disconnectMutation.mutate(provider)}
                      />
                    ) : null}
                  </View>
                );
              })}
            </ThemedView>

            <SectionTitle title="Sessions" />
            <ThemedView type="backgroundElement" style={styles.formCard}>
              <ThemedText style={styles.formTitle}>Sign out everywhere</ThemedText>
              <ThemedText style={styles.hint} themeColor="textSecondary">
                Revokes every Ovezi session, including this device.
              </ThemedText>
              {confirmAllSessions ? (
                <ConfirmRow
                  confirmLabel="Sign out everywhere"
                  loading={sessionsMutation.isPending}
                  message="You will need to sign in again on every device."
                  onCancel={() => setConfirmAllSessions(false)}
                  onConfirm={() => sessionsMutation.mutate()}
                />
              ) : (
                <Pressable onPress={() => setConfirmAllSessions(true)} style={styles.outlineButton}>
                  <ThemedText style={styles.outlineLabel} themeColor="danger">Sign out all devices</ThemedText>
                </Pressable>
              )}
            </ThemedView>

            <SectionTitle title="Delete account" />
            <ThemedView type="backgroundElement" style={styles.formCard}>
              <ThemedText style={styles.hint} themeColor="textSecondary">
                Your personal profile and sign-in access will be removed. Shared history stays under “Deleted member” to keep everyone’s balances accurate. Group ownership transfers to another member, or the group is archived. This does not cancel money owed.
              </ThemedText>
              {deleteError ? <ThemedText themeColor="danger" accessibilityRole="alert">{deleteError}</ThemedText> : null}
              {!user.has_password ? (
                <View style={{ gap: 12 }}>
                  {!confirmDelete ? <PrimaryButton label="Delete my account" onPress={() => setConfirmDelete(true)} /> : <>
                    <ThemedText>Confirm with your connected account to permanently delete your Ovezi account.</ThemedText>
                    <SocialSignIn providers={user.connected_providers} onError={setDeleteError} onIdentity={async (credentials) => {
                      await deleteAccount(token, credentials);
                      await logout();
                    }} />
                    <PrimaryButton label="Cancel" onPress={() => setConfirmDelete(false)} />
                  </>}
                  <Pressable accessibilityRole="link" style={{ minHeight: 48, justifyContent: 'center' }} onPress={() => void Linking.openURL(`${new URL(apiBaseUrl).origin}/delete-account`)}>
                    <ThemedText themeColor="primary">Confirm deletion by email instead</ThemedText>
                  </Pressable>
                </View>
              ) : confirmDelete ? (
                <>
                  <PasswordField label="Confirm your password" onChangeText={setDeletePassword} value={deletePassword} />
                  <ConfirmRow
                    confirmLabel="Permanently delete account"
                    loading={deleteMutation.isPending}
                    message="This removes access to your account and cannot be undone."
                    onCancel={() => {
                      setConfirmDelete(false);
                      setDeletePassword('');
                    }}
                    onConfirm={() => deleteMutation.mutate()}
                  />
                </>
              ) : (
                <Pressable onPress={() => setConfirmDelete(true)} style={styles.dangerButton}>
                  <ThemedText style={styles.dangerLabel}>Delete account</ThemedText>
                </Pressable>
              )}
            </ThemedView>
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );

  function openSection(nextSection: Exclude<FormSection, null>) {
    setSuccess(null);
    clearCredentials();
    setSection(section === nextSection ? null : nextSection);
  }
}

function PasswordField({ label, onChangeText, value }: { label: string; onChangeText: (value: string) => void; value: string }) {
  return <FormField label={label} onChangeText={onChangeText} secureTextEntry value={value} />;
}

function SectionTitle({ title }: { title: string }) {
  return <ThemedText style={styles.sectionTitle}>{title}</ThemedText>;
}

function Divider() {
  return <View style={styles.divider} />;
}

function SettingRow({ label, onPress, value }: { label: string; onPress?: () => void; value: string }) {
  return (
    <Pressable disabled={!onPress} onPress={onPress} style={styles.settingRow}>
      <View style={styles.rowCopy}>
        <ThemedText style={styles.rowLabel}>{label}</ThemedText>
        <ThemedText numberOfLines={1} style={styles.rowValue} themeColor="textSecondary">{value}</ThemedText>
      </View>
      {onPress ? <ThemedText style={styles.chevron} themeColor="textSecondary">›</ThemedText> : null}
    </Pressable>
  );
}

function ConfirmRow({
  confirmLabel,
  loading,
  message,
  onCancel,
  onConfirm,
}: {
  confirmLabel: string;
  loading: boolean;
  message: string;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <ThemedView type="backgroundSelected" style={styles.confirmRow}>
      <ThemedText style={styles.confirmMessage}>{message}</ThemedText>
      <View style={styles.confirmActions}>
        <Pressable disabled={loading} onPress={onCancel}>
          <ThemedText style={styles.confirmLink} themeColor="textSecondary">Cancel</ThemedText>
        </Pressable>
        <Pressable disabled={loading} onPress={onConfirm}>
          <ThemedText style={styles.confirmLink} themeColor="danger">
            {loading ? 'Working…' : confirmLabel}
          </ThemedText>
        </Pressable>
      </View>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  safeArea: { flex: 1 },
  flex: { flex: 1 },
  header: { minHeight: 60, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: Spacing.four },
  back: { fontSize: 14, fontWeight: '600' },
  headerTitle: { fontSize: 17, fontWeight: '600' },
  headerSpacer: { width: 48 },
  content: { padding: Spacing.four, paddingBottom: 80, gap: 13, maxWidth: 620, width: '100%', alignSelf: 'center' },
  successCard: { borderRadius: 17, padding: 14 },
  successText: { fontSize: 13, lineHeight: 19, fontWeight: '600' },
  sectionTitle: { fontSize: 18, lineHeight: 25, fontWeight: '600', marginTop: 8 },
  card: { borderRadius: 20, paddingHorizontal: 16 },
  formCard: { borderRadius: 20, padding: 16, gap: 14 },
  formTitle: { fontSize: 15, fontWeight: '600' },
  settingRow: { minHeight: 66, flexDirection: 'row', alignItems: 'center', gap: 12 },
  providerRow: { minHeight: 66, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowCopy: { flex: 1, gap: 2 },
  rowLabel: { fontSize: 14, fontWeight: '600' },
  rowValue: { fontSize: 12, lineHeight: 17 },
  rowAction: { fontSize: 12, fontWeight: '600' },
  disabled: { opacity: 0.55 },
  chevron: { fontSize: 22, fontWeight: '500' },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: '#7A8498', opacity: 0.3 },
  hint: { fontSize: 13, lineHeight: 19 },
  empty: { paddingVertical: 20, textAlign: 'center' },
  outlineButton: { height: 48, borderRadius: 15, borderWidth: 1, borderColor: '#C63E4E', alignItems: 'center', justifyContent: 'center' },
  outlineLabel: { fontWeight: '600' },
  dangerButton: { height: 48, borderRadius: 15, backgroundColor: '#C63E4E', alignItems: 'center', justifyContent: 'center' },
  dangerLabel: { color: '#FFFFFF', fontWeight: '600' },
  confirmRow: { borderRadius: 15, padding: 13, gap: 9 },
  confirmMessage: { fontSize: 13, lineHeight: 19, fontWeight: '600' },
  confirmActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 20 },
  confirmLink: { fontSize: 12, fontWeight: '600' },
});
