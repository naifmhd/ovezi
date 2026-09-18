import { useQuery } from '@tanstack/react-query';
import { RefreshControl, StyleSheet } from 'react-native';

import { ActivityRow } from '@/components/activity-row';
import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { fetchActivity } from '@/lib/activity-api';
import { errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

export default function ActivityScreen() {
  const token = useAuthStore((state) => state.token)!;
  const query = useQuery({
    queryKey: ['activity', 'global', 50],
    queryFn: () => fetchActivity(token, undefined, 50),
  });
  const activities = query.data?.data ?? [];

  return (
    <AppScreen
      title="Activity"
      scrollProps={{
        refreshControl: (
          <RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />
        ),
      }}>
      {query.error ? <ThemedText themeColor="danger">{errorMessage(query.error)}</ThemedText> : null}
      {query.isLoading ? (
        <ThemedText style={styles.empty} themeColor="textSecondary">
          Loading activity…
        </ThemedText>
      ) : null}
      {activities.map((activity) => (
        <ActivityRow activity={activity} key={activity.id} />
      ))}
      {!query.isLoading && activities.length === 0 ? (
        <ThemedText style={styles.empty} themeColor="textSecondary">
          Your group and expense activity will appear here.
        </ThemedText>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({ empty: { textAlign: 'center', marginTop: 80 } });
