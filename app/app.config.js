const fs = require('node:fs');
const path = require('node:path');
const staticConfig = require('./app.json');

function requiredBuildValue(name, value, buildProfile) {
  const normalized = value?.trim();

  if (buildProfile && !normalized) {
    throw new Error(`${name} is required for the ${buildProfile} EAS build profile.`);
  }

  return normalized;
}

function googleIosUrlScheme(clientId) {
  if (!clientId) return null;
  if (!clientId.endsWith('.apps.googleusercontent.com')) {
    throw new Error('EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID must be a Google iOS OAuth client ID.');
  }

  return clientId.split('.').reverse().join('.');
}

module.exports = () => {
  const buildProfile = process.env.EAS_BUILD_PROFILE?.trim();
  const apiUrl = requiredBuildValue('EXPO_PUBLIC_API_URL', process.env.EXPO_PUBLIC_API_URL, buildProfile);
  const easProjectId = requiredBuildValue(
    'EXPO_PUBLIC_EAS_PROJECT_ID',
    process.env.EXPO_PUBLIC_EAS_PROJECT_ID,
    buildProfile,
  );
  const iosBundleIdentifier = requiredBuildValue(
    'EXPO_IOS_BUNDLE_IDENTIFIER',
    process.env.EXPO_IOS_BUNDLE_IDENTIFIER,
    buildProfile,
  );
  const androidPackage = requiredBuildValue(
    'EXPO_ANDROID_PACKAGE',
    process.env.EXPO_ANDROID_PACKAGE,
    buildProfile,
  );
  const googleServicesFile = process.env.GOOGLE_SERVICES_JSON?.trim()
    || (fs.existsSync(path.join(__dirname, 'google-services.json')) ? './google-services.json' : undefined);
  if (buildProfile && process.env.EAS_BUILD_PLATFORM === 'android' && !googleServicesFile) {
    throw new Error('GOOGLE_SERVICES_JSON must point to the Firebase google-services.json file for Android builds. Set it as an EAS file environment variable.');
  }
  if (googleServicesFile && fs.existsSync(path.resolve(__dirname, googleServicesFile))) {
    const firebase = JSON.parse(fs.readFileSync(path.resolve(__dirname, googleServicesFile), 'utf8'));
    const packageName = androidPackage || staticConfig.expo.android.package;
    if (!firebase.client?.some((client) => client.client_info?.android_client_info?.package_name === packageName)) {
      throw new Error('The Firebase google-services.json file does not contain this Android package.');
    }
  } else if (googleServicesFile && process.env.EAS_BUILD === 'true' && process.env.EAS_BUILD_PLATFORM === 'android') {
    throw new Error('GOOGLE_SERVICES_JSON does not point to an existing file on the Android build worker.');
  }
  const googleIosClientId = requiredBuildValue(
    'EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID',
    process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID,
    buildProfile,
  );
  const googleWebClientId = requiredBuildValue(
    'EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID',
    process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID,
    buildProfile,
  );
  requiredBuildValue(
    'EXPO_PUBLIC_REVERB_APP_KEY',
    process.env.EXPO_PUBLIC_REVERB_APP_KEY,
    buildProfile,
  );
  requiredBuildValue(
    'EXPO_PUBLIC_REVERB_HOST',
    process.env.EXPO_PUBLIC_REVERB_HOST,
    buildProfile,
  );
  requiredBuildValue(
    'EXPO_PUBLIC_SENTRY_DSN',
    process.env.EXPO_PUBLIC_SENTRY_DSN,
    buildProfile,
  );
  const sentryOrganization = requiredBuildValue(
    'SENTRY_ORG',
    process.env.SENTRY_ORG,
    buildProfile,
  );
  const sentryProject = requiredBuildValue(
    'SENTRY_PROJECT',
    process.env.SENTRY_PROJECT,
    buildProfile,
  );

  if (buildProfile === 'production' && apiUrl && !apiUrl.startsWith('https://')) {
    throw new Error('EXPO_PUBLIC_API_URL must use HTTPS for production builds.');
  }

  const linkHost = apiUrl ? new URL(apiUrl).hostname : null;
  const linkPaths = ['/group-invites/accept', '/auth/reset-password', '/auth/verify-email'];
  const iosUrlScheme = googleIosUrlScheme(googleIosClientId);
  const plugins = [...staticConfig.expo.plugins];

  if (iosUrlScheme) {
    plugins.push([
      'react-native-nitro-google-signin',
      { iosUrlScheme },
    ]);
  }

  plugins.push([
    '@sentry/react-native',
    {
      ...(sentryOrganization ? { organization: sentryOrganization } : {}),
      ...(sentryProject ? { project: sentryProject } : {}),
    },
  ]);

  return {
    ...staticConfig.expo,
    ios: {
      ...staticConfig.expo.ios,
      ...(linkHost && apiUrl.startsWith('https://') ? { associatedDomains: [`applinks:${linkHost}`] } : {}),
      ...(iosBundleIdentifier ? { bundleIdentifier: iosBundleIdentifier } : {}),
    },
    android: {
      ...staticConfig.expo.android,
      ...(googleServicesFile ? { googleServicesFile } : {}),
      ...(linkHost && apiUrl.startsWith('https://') ? { intentFilters: [{ action: 'VIEW', autoVerify: true, category: ['BROWSABLE', 'DEFAULT'], data: linkPaths.map((pathPrefix) => ({ scheme: 'https', host: linkHost, pathPrefix })) }] } : {}),
      ...(androidPackage ? { package: androidPackage } : {}),
    },
    plugins,
    extra: {
      ...staticConfig.expo.extra,
      googleWebClientId,
      googleIosClientId,
      ...(easProjectId
        ? {
            eas: {
              ...staticConfig.expo.extra?.eas,
              projectId: easProjectId,
            },
          }
        : {}),
    },
  };
};
