import { useInfiniteQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { GroupAvatar } from '@/components/group-avatar';
import { ExpenseEditorSheet, ExpenseSelectionRow } from '@/components/expense-editor-controls';
import { SearchField } from '@/components/ui/screen-controls';
import { LendingAction } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { fetchGroupsPage } from '@/lib/groups-api';
import { useAuthStore } from '@/stores/auth-store';

export function ExpenseGroupPicker({ visible, selectedId, onClose, onSelect }: { visible: boolean; selectedId: number | null; onClose: () => void; onSelect: (id: number) => void }) {
  const token = useAuthStore(s => s.token)!;
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  useEffect(() => { const timer = setTimeout(() => setQuery(search.trim()), 250); return () => clearTimeout(timer); }, [search]);
  const groups = useInfiniteQuery({ queryKey: ['groups', 'expense-picker', query], enabled: visible, initialPageParam: 1, queryFn: ({ pageParam, signal }) => fetchGroupsPage(token, 'active', query, pageParam, signal), getNextPageParam: page => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined });
  return <ExpenseEditorSheet title="Choose a group" visible={visible} onClose={onClose}>
    <SearchField label="Search groups for expense" placeholder="Find a group" value={search} onChange={setSearch} />
    {groups.isLoading ? <ThemedText>Loading groups…</ThemedText> : null}
    {groups.data?.pages.flatMap(page => page.data).map(group => <ExpenseSelectionRow key={group.id} label={group.name} selected={selectedId === group.id} leading={<GroupAvatar group={group} size={36} />} onPress={() => { onSelect(group.id); onClose(); }} />)}
    {groups.data && !groups.data.pages.some(page => page.data.length) ? <ThemedText themeColor="textSecondary">No matching groups.</ThemedText> : null}
    {groups.error ? <QueryErrorCard error={groups.error} onRetry={() => void groups.refetch()} /> : null}
    {groups.hasNextPage ? <LendingAction label={groups.isFetchingNextPage ? 'Loading…' : 'More groups'} disabled={groups.isFetchingNextPage} onPress={() => void groups.fetchNextPage()} /> : null}
    <LendingAction label="Create a new group" onPress={() => { onClose(); router.push('/(app)/groups/create'); }} />
  </ExpenseEditorSheet>;
}
