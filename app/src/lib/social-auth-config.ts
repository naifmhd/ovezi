import Constants, { ExecutionEnvironment } from 'expo-constants';

export const googleWebClientId = process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID?.trim() || Constants.expoConfig?.extra?.googleWebClientId;
export const googleIosClientId = process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID?.trim() || Constants.expoConfig?.extra?.googleIosClientId;
export const isExpoGo = Constants.executionEnvironment === ExecutionEnvironment.StoreClient;
