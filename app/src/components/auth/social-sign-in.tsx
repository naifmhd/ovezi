import { googleWebClientId, googleIosClientId, isExpoGo } from '@/lib/social-auth-config';
import * as AppleAuthentication from 'expo-apple-authentication';
import * as Crypto from 'expo-crypto';
import { useEffect, useRef, useState } from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';

import { socialLogin, type SocialIdentityCredentials } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { ThemedText } from '@/components/themed-text';
import { useAuthStore } from '@/stores/auth-store';

type GoogleSignInModule = typeof import('react-native-nitro-google-signin');

let googleSignInModule: Promise<GoogleSignInModule> | undefined;
let googleSignInConfigured = false;

async function configuredGoogleSignIn(): Promise<GoogleSignInModule> {
  if (!googleWebClientId) {
    throw new Error('Google sign-in is not configured.');
  }

  googleSignInModule ??= import('react-native-nitro-google-signin');
  const googleSignIn = await googleSignInModule;

  if (!googleSignInConfigured) {
    googleSignIn.GoogleOneTapSignIn.configure({
      webClientId: googleWebClientId,
      iosClientId: googleIosClientId,
      offlineAccess: false,
      autoSelectOnSignIn: false,
    });
    googleSignInConfigured = true;
  }

  return googleSignIn;
}

export type SocialSignInProps = {
  mode?: 'sign-in' | 'connect';
  disabled?: boolean;
  onBusyChange?: (busy: boolean) => void;
  onIdentity?: (credentials: SocialIdentityCredentials) => Promise<void>;
  providers?: ('google' | 'apple')[];
  onError: (message: string) => void;
  onSuccess?: () => void;
};

function GoogleButton({ onError, onSuccess, onIdentity, mode, disabled, onBusyChange }: SocialSignInProps) {
  const setSession = useAuthStore((state) => state.setSession);
  const [pending, setPending] = useState(false);
  const busy = useRef(false);

  async function handlePress() {
    if (disabled || busy.current) return;
    busy.current = true;
    onBusyChange?.(true);
    let googleSignIn: GoogleSignInModule | undefined;

    try {
      setPending(true);
      googleSignIn = await configuredGoogleSignIn();
      const {
        GoogleOneTapSignIn,
        isCancelledResponse,
        isNoSavedCredentialFoundResponse,
        isSuccessResponse,
      } = googleSignIn;

      await GoogleOneTapSignIn.checkPlayServices();
      let result = await GoogleOneTapSignIn.signIn();

      if (isNoSavedCredentialFoundResponse(result)) {
        result = await GoogleOneTapSignIn.createAccount();
      }

      if (isCancelledResponse(result)) {
        return;
      }

      if (isSuccessResponse(result)) {
        if (onIdentity) await onIdentity({ provider: 'google', id_token: result.data.idToken });
        else await setSession(await socialLogin('google', result.data.idToken));
        onSuccess?.();
      }
    } catch (error) {
      if (!googleSignIn?.isErrorWithCode(error)
        || error.code !== googleSignIn.statusCodes.SIGN_IN_CANCELLED) {
        onError(errorMessage(error));
      }
    } finally {
      busy.current = false;
      setPending(false);
      onBusyChange?.(false);
    }
  }

  return (
    <Pressable
      accessibilityRole="button"
      disabled={pending || disabled}
      accessibilityState={{ disabled: pending || disabled, busy: pending }}
      onPress={() => void handlePress()}
      style={({ pressed }) => [styles.googleButton, (pressed || pending || disabled) && styles.pressed]}>
      <Text style={styles.googleGlyph}>G</Text>
      <Text style={styles.googleLabel}>{pending ? (mode === 'connect' ? 'Connecting…' : 'Signing in…') : mode === 'connect' ? 'Connect Google' : 'Continue with Google'}</Text>
    </Pressable>
  );
}

function AppleButton({ onError, onSuccess, onIdentity, mode, disabled, onBusyChange }: SocialSignInProps) {
  const setSession = useAuthStore((state) => state.setSession);
  const [available, setAvailable] = useState<boolean | null>(null);
  const [pending, setPending] = useState(false);
  const busy = useRef(false);

  useEffect(() => {
    void AppleAuthentication.isAvailableAsync().then(setAvailable).catch(() => setAvailable(false));
  }, []);

  if (!available) {
    return mode === 'connect' ? <ThemedText themeColor="textSecondary" style={styles.developmentBuildHint}>{available === null ? 'Checking Apple availability…' : 'Apple sign-in is not available on this device.'}</ThemedText> : null;
  }

  async function handlePress() {
    if (disabled || busy.current) return;
    busy.current = true;
    setPending(true);
    onBusyChange?.(true);
    try {
      const rawNonce = Crypto.randomUUID();
      const hashedNonce = await Crypto.digestStringAsync(
        Crypto.CryptoDigestAlgorithm.SHA256,
        rawNonce,
      );
      const credential = await AppleAuthentication.signInAsync({
        nonce: hashedNonce,
        requestedScopes: [
          AppleAuthentication.AppleAuthenticationScope.FULL_NAME,
          AppleAuthentication.AppleAuthenticationScope.EMAIL,
        ],
      });

      if (!credential.identityToken) {
        throw new Error('Apple did not return an identity token.');
      }

      const name = credential.fullName
        ? AppleAuthentication.formatFullName(credential.fullName).trim()
        : undefined;

      if (!credential.authorizationCode) throw new Error('Apple did not return a confirmation code. Please try again.');
      if (onIdentity) {
        await onIdentity({ provider: 'apple', id_token: credential.identityToken, nonce: rawNonce, authorization_code: credential.authorizationCode });
      } else await setSession(
        await socialLogin('apple', credential.identityToken, {
          nonce: rawNonce,
          authorization_code: credential.authorizationCode,
          ...(name ? { name } : {}),
        }),
      );
      onSuccess?.();
    } catch (error) {
      if ((error as { code?: string }).code !== 'ERR_REQUEST_CANCELED') {
        onError(errorMessage(error));
      }
    } finally {
      busy.current = false;
      setPending(false);
      onBusyChange?.(false);
    }
  }

  return (
    <View pointerEvents={disabled || pending ? 'none' : 'auto'} accessibilityState={{ disabled: disabled || pending, busy: pending }} style={(disabled || pending) && styles.pressed}>
      <AppleAuthentication.AppleAuthenticationButton
        buttonStyle={AppleAuthentication.AppleAuthenticationButtonStyle.BLACK}
        buttonType={AppleAuthentication.AppleAuthenticationButtonType.CONTINUE}
        cornerRadius={16}
        onPress={() => void handlePress()}
        style={styles.appleButton}
      />
      {pending ? <ThemedText themeColor="textSecondary" style={styles.developmentBuildHint}>{mode === 'connect' ? 'Connecting…' : 'Signing in…'}</ThemedText> : null}
    </View>
  );
}

export function SocialSignIn({ providers, ...props }: SocialSignInProps) {
  const googleConfigured = Boolean(
    googleWebClientId && (Platform.OS !== 'ios' || googleIosClientId),
  );
  const googleAvailable = googleConfigured && !isExpoGo;

  return (
    <View style={styles.container}>
      {googleAvailable && (!providers || providers.includes('google')) ? <GoogleButton {...props} /> : null}
      {Platform.OS === 'ios' && (!providers || providers.includes('apple')) ? <AppleButton {...props} /> : null}
      {(!providers || providers.includes('google')) && googleConfigured && isExpoGo ? (
        <ThemedText themeColor="textSecondary" style={styles.developmentBuildHint}>
          Google sign-in is available in Ovezi development and release builds.
        </ThemedText>
      ) : null}
      {props.mode === 'connect' && (!providers || providers.includes('google')) && !googleConfigured ? <ThemedText themeColor="textSecondary" style={styles.developmentBuildHint}>Google sign-in is not configured in this build.</ThemedText> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { gap: 12 },
  googleButton: {
    height: 52,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#D4D8E0',
    backgroundColor: '#FFFFFF',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 18,
  },
  googleGlyph: { position: 'absolute', left: 20, color: '#4285F4', fontSize: 20, fontWeight: '600' },
  googleLabel: { color: '#182033', fontSize: 16, fontWeight: '600' },
  appleButton: { width: '100%', height: 52 },
  developmentBuildHint: { fontSize: 12, lineHeight: 17, textAlign: 'center' },
  pressed: { opacity: 0.65 },
});
