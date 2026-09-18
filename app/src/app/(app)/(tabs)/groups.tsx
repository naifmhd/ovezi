import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Pressable, RefreshControl, StyleSheet } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { GroupCard } from '@/components/group-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { errorMessage } from '@/lib/api-client';
import { fetchGroups } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export default function GroupsScreen() {
  const token = useAuthStore((state) => state.token)!;
  const query = useQuery({
    queryKey: ['groups', 'active'],
    queryFn: () => fetchGroups(token),
  });
  const groups = query.data?.data ?? [];

  return (
    <AppScreen
      title="Groups"
      action={
        <Pressable onPress={() => router.push('/(app)/groups/create')}>
          <ThemedText style={styles.action} themeColor="primary">
            + New
          </ThemedText>
        </Pressable>
      }
      scrollProps={{
        refreshControl: (
          <RefreshControl refreshing={query.isRefetching} onRefresh={() => void query.refetch()} />
        ),
      }}>
      {query.error ? <ThemedText themeColor="danger">{errorMessage(query.error)}</ThemedText> : null}
      {query.isLoading ? (
        <ThemedText style={styles.loading} themeColor="textSecondary">
          Loading groups…
        </ThemedText>
      ) : null}
      {groups.map((group) => (
        <GroupCard group={group} key={group.id} />
      ))}
      {!query.isLoading && groups.length === 0 ? (
        <ThemedView type="backgroundElement" style={styles.empty}>
          <ThemedText style={styles.emptyTitle}>No groups yet</ThemedText>
          <ThemedText style={styles.copy} themeColor="textSecondary">
            Start a group for a trip, household, event, or anything shared.
          </ThemedText>
          <Pressable onPress={() => router.push('/(app)/groups/create')}>
            <ThemedText style={styles.action} themeColor="primary">
              Create a group
            </ThemedText>
          </Pressable>
        </ThemedView>
      ) : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  action: { fontSize: 14, lineHeight: 20, fontWeight: '800' },
  empty: { padding: 22, borderRadius: 22, gap: 7, marginTop: 30 },
  emptyTitle: { fontSize: 20, lineHeight: 28, fontWeight: '800' },
  copy: { fontSize: 14, lineHeight: 21, marginBottom: 8 },
  loading: { textAlign: 'center', marginTop: 60 },
});
