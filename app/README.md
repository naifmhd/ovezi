# Ovezi mobile app

Expo 57 / React Native client for Ovezi.

## Local setup

1. Install dependencies with `npm install`.
2. Copy `.env.example` to `.env` and set `EXPO_PUBLIC_API_URL`.
3. Start the app with `npx expo start`.

Google sign-in uses native code and does not work in Expo Go. Use an Expo development build or an EAS build when testing Google authentication.

## Google OAuth

Create separate OAuth clients in Google Cloud for Web, iOS, and Android.

- `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` is the Web client ID. The app sends its Google ID token to the Laravel API, and the same client ID must be included in the API's `GOOGLE_CLIENT_IDS` setting.
- `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` is the iOS client ID ending in `.apps.googleusercontent.com`. The dynamic Expo config derives and registers its reversed callback URL scheme.
- Configure the Android OAuth client with the final Android package name and the signing certificate SHA-1. Android uses the explicit Web client ID at runtime, so Firebase configuration files are not required.

After changing OAuth configuration, rebuild the native app. Restarting Metro is not sufficient for native config-plugin changes.

## Apple sign-in

Sign in with Apple is enabled for iOS through `expo-apple-authentication`. The API's `APPLE_CLIENT_IDS` setting must include the app's final Apple client or bundle identifier before release builds are tested.

## Reverb

Set the `EXPO_PUBLIC_REVERB_*` variables to the Laravel Cloud Reverb application. Shared group, expense, settlement, and balance queries reconnect through private channels after authentication.

## Checks

```bash
npx expo lint
npx tsc --noEmit
npx expo export --platform all
```
