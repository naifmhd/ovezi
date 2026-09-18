const appJson = require('./app.json');

function googleIosUrlScheme(clientId) {
  const suffix = '.apps.googleusercontent.com';

  if (!clientId?.endsWith(suffix)) {
    return null;
  }

  return `com.googleusercontent.apps.${clientId.slice(0, -suffix.length)}`;
}

module.exports = () => {
  const iosUrlScheme = googleIosUrlScheme(process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID);
  const plugins = [...appJson.expo.plugins];

  if (iosUrlScheme) {
    plugins.push(['react-native-nitro-google-signin', { iosUrlScheme }]);
  }

  return {
    ...appJson.expo,
    plugins,
  };
};
