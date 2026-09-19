import * as AppleAuthentication from 'expo-apple-authentication';
import Constants from 'expo-constants';
import * as Crypto from 'expo-crypto';
import { useEffect, useState } from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';

import { socialLogin } from '@/lib/auth-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

const googleWebClientId = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID;
const googleIosClientId = process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID;
const isExpoGo = Constants.expoGoConfig !== null;

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

type SocialSignInProps = {
  onError: (message: string) => void;
  onSuccess?: () => void;
};

function GoogleButton({ onError, onSuccess }: SocialSignInProps) {
  const setSession = useAuthStore((state) => state.setSession);
  const [pending, setPending] = useState(false);

  async function handlePress() {
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
        await setSession(await socialLogin('google', result.data.idToken));
        onSuccess?.();
      }
    } catch (error) {
      if (!googleSignIn?.isErrorWithCode(error)
        || error.code !== googleSignIn.statusCodes.SIGN_IN_CANCELLED) {
        onError(errorMessage(error));
      }
    } finally {
      setPending(false);
    }
  }

  return (
    <Pressable
      accessibilityRole="button"
      disabled={pending}
      onPress={() => void handlePress()}
      style={({ pressed }) => [styles.googleButton, (pressed || pending) && styles.pressed]}>
      <Text style={styles.googleGlyph}>G</Text>
      <Text style={styles.googleLabel}>{pending ? 'Signing in…' : 'Continue with Google'}</Text>
    </Pressable>
  );
}

function AppleButton({ onError, onSuccess }: SocialSignInProps) {
  const setSession = useAuthStore((state) => state.setSession);
  const [available, setAvailable] = useState(false);

  useEffect(() => {
    void AppleAuthentication.isAvailableAsync().then(setAvailable);
  }, []);

  if (!available) {
    return null;
  }

  async function handlePress() {
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

      await setSession(
        await socialLogin('apple', credential.identityToken, {
          nonce: rawNonce,
          ...(name ? { name } : {}),
        }),
      );
      onSuccess?.();
    } catch (error) {
      if ((error as { code?: string }).code !== 'ERR_REQUEST_CANCELED') {
        onError(errorMessage(error));
      }
    }
  }

  return (
    <AppleAuthentication.AppleAuthenticationButton
      buttonStyle={AppleAuthentication.AppleAuthenticationButtonStyle.BLACK}
      buttonType={AppleAuthentication.AppleAuthenticationButtonType.CONTINUE}
      cornerRadius={16}
      onPress={() => void handlePress()}
      style={styles.appleButton}
    />
  );
}

export function SocialSignIn({ onError, onSuccess }: SocialSignInProps) {
  const googleConfigured = Boolean(
    googleWebClientId && (Platform.OS !== 'ios' || googleIosClientId),
  );
  const googleAvailable = googleConfigured && !isExpoGo;

  return (
    <View style={styles.container}>
      {googleAvailable ? <GoogleButton onError={onError} onSuccess={onSuccess} /> : null}
      {Platform.OS === 'ios' ? <AppleButton onError={onError} onSuccess={onSuccess} /> : null}
      {googleConfigured && isExpoGo ? (
        <Text style={styles.developmentBuildHint}>
          Google sign-in is available in Ovezi development and release builds.
        </Text>
      ) : null}
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
  googleGlyph: { position: 'absolute', left: 20, color: '#4285F4', fontSize: 20, fontWeight: '800' },
  googleLabel: { color: '#182033', fontSize: 16, fontWeight: '700' },
  appleButton: { width: '100%', height: 52 },
  developmentBuildHint: { color: '#9AA3B5', fontSize: 12, lineHeight: 17, textAlign: 'center' },
  pressed: { opacity: 0.65 },
});
