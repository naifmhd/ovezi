import * as Sentry from '@sentry/react-native';

const dsn = process.env.EXPO_PUBLIC_SENTRY_DSN?.trim();

export function initializeSentry() {
  Sentry.init({
    dsn,
    enabled: Boolean(dsn),
    environment: process.env.EXPO_PUBLIC_SENTRY_ENVIRONMENT?.trim() || 'development',
    sendDefaultPii: false,
    tracesSampleRate: 0,
    profilesSampleRate: 0,
    enableAutoSessionTracking: false,
    maxBreadcrumbs: 0,
    beforeSend: scrubSentryEvent,
  });
}

export function setSentryUserId(userId: number | null) {
  Sentry.setUser(userId === null ? null : { id: String(userId) });
}

export function scrubSentryEvent(event: Sentry.ErrorEvent): Sentry.ErrorEvent {
  const userId = event.user?.id;

  return {
    ...event,
    breadcrumbs: undefined,
    extra: undefined,
    request: undefined,
    user: userId === undefined ? undefined : { id: userId },
  };
}

export const withSentry = Sentry.wrap;
