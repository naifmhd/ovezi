export function redirectSystemPath({ path }: { path: string; initial: boolean }): string {
  try {
    const url = new URL(path);
    if (url.protocol !== 'ovezi:') return path;

    const pathname = `/${url.host}${url.pathname}`;
    if (!['/auth/verify-email', '/auth/reset-password', '/group-invites/accept'].includes(pathname)) {
      return path;
    }

    // Keep encoded query values intact: Expo's custom-scheme parser otherwise
    // turns the nested verification URL's &signature into a separate route param.
    return `${pathname}${url.search}${url.hash}`;
  } catch {
    return path;
  }
}
