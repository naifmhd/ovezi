type GoogleSignInModule = typeof import('react-native-nitro-google-signin');

const incompleteMessage = 'Google sign-in closed before it completed. Please try again or sign in with email.';

export async function requestGoogleIdToken(googleSignIn: GoogleSignInModule, platform: string): Promise<string> {
  try {
    if (platform === 'android') {
      await googleSignIn.GoogleOneTapSignIn.checkPlayServices();
    }

    // A button tap must open the interactive picker, even when One Tap prompts are suppressed.
    const result = await googleSignIn.GoogleOneTapSignIn.presentExplicitSignIn();

    if (googleSignIn.isCancelledResponse(result)) {
      throw new Error(incompleteMessage);
    }

    if (googleSignIn.isNoSavedCredentialFoundResponse(result)) {
      throw new Error('Google could not find an available account. Add a Google account on your device, then try again, or sign in with email.');
    }

    if (!googleSignIn.isSuccessResponse(result) || !result.data.idToken?.trim()) {
      throw new Error('Google did not complete sign-in. Please try again or sign in with email.');
    }

    return result.data.idToken;
  } catch (error) {
    if (googleSignIn.isErrorWithCode(error)) {
      switch (error.code) {
        case googleSignIn.statusCodes.SIGN_IN_CANCELLED:
          throw new Error(incompleteMessage);
        case googleSignIn.statusCodes.DEVELOPER_ERROR:
          throw new Error('Google sign-in is not configured correctly in this app build. Please sign in with email for now.');
        case googleSignIn.statusCodes.PLAY_SERVICES_NOT_AVAILABLE:
          throw new Error('Google Play services is unavailable or needs an update. Update it, then try again, or sign in with email.');
      }
    }

    throw error;
  }
}
