const staticConfig = require('./app.json');

function googleIosUrlScheme(clientId) {
  if (!clientId) return null;
  if (!clientId.endsWith('.apps.googleusercontent.com')) {
    throw new Error('EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID must be a Google iOS OAuth client ID.');
  }

  return clientId.split('.').reverse().join('.');
}

module.exports = () => {
  const iosUrlScheme = googleIosUrlScheme(process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID);
  const easProjectId = process.env.EXPO_PUBLIC_EAS_PROJECT_ID?.trim();
  const plugins = [...staticConfig.expo.plugins];

  if (iosUrlScheme) {
    plugins.push([
      'react-native-nitro-google-signin',
      { iosUrlScheme },
    ]);
  }

  return {
    ...staticConfig.expo,
    plugins,
    extra: {
      ...staticConfig.expo.extra,
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
