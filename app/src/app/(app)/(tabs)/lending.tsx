import { useInfiniteQuery, useMutation } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { PinButton } from '@/components/pin-button';
import { BrandLockup } from '@/components/brand-mark';
import { EmptyState } from '@/components/empty-state';
import { LendingAction, LendingBalanceSummary, LendingRow, useRefreshLending } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { SearchField, SegmentedControl, ViewFilter } from '@/components/ui/screen-controls';
import { SymbolView } from 'expo-symbols';
import { UserAvatar } from '@/components/user-avatar';
import { useTheme } from '@/hooks/use-theme';
import { errorMessage } from '@/lib/api-client';
import { LendingContact, LendingDirection, LendingInvitation, lendingNextPage, lendingPage, lendingWrite } from '@/lib/lending-api';
import { useAuthStore } from '@/stores/auth-store';

export default function LendingScreen() {
  const token = useAuthStore(s => s.token)!;
  const verified = useAuthStore(s => s.user?.email_verified_at);
  const theme = useTheme();
  const refresh = useRefreshLending();
  const [search, setSearch] = useState('');
  const [q, setQ] = useState('');
  const [direction, setDirection] = useState<LendingDirection>('lent');
  const [status, setStatus] = useState<'outstanding' | 'settled' | 'all'>('outstanding');
  useEffect(() => { const timer = setTimeout(() => setQ(search.trim()), 280); return () => clearTimeout(timer); }, [search]);
  const people = useInfiniteQuery({ queryKey: ['lending', 'contacts', q, direction, status], initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => lendingPage<LendingContact>(token, `contacts?q=${encodeURIComponent(q)}&direction=${direction}&status=${status}`, pageParam, signal), getNextPageParam: lendingNextPage });
  const invitations = useInfiniteQuery({ queryKey: ['lending', 'invitations'], initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => lendingPage<LendingInvitation>(token, 'invitations', pageParam, signal), getNextPageParam: lendingNextPage });
  const respond = useMutation({ mutationFn: ({ id, action }: { id: number; action: string }) => lendingWrite(token, `invitations/${id}/actions`, { action }), onSuccess: refresh });
  const rows = (people.data?.pages.flatMap(p => p.data) ?? []).filter(person => {
    const balances = person.balances.filter(b => b.direction === direction);
    return status === 'all' || (status === 'settled' ? balances.length > 0 && balances.every(b => b.outstanding_minor === 0) : balances.some(b => b.outstanding_minor > 0));
  });
  return <ThemedView style={{ flex: 1 }}><SafeAreaView edges={['top']} style={{ flex: 1 }}>
    <View style={styles.brand}><BrandLockup size={24} /><LendingAction variant="primary" label="Record a loan" onPress={() => router.push('/(app)/lending/create')} /></View>
    <FlatList data={rows} keyExtractor={item => String(item.id)} contentContainerStyle={styles.list} keyboardShouldPersistTaps="handled"
      refreshControl={<RefreshControl tintColor={theme.interactive} refreshing={people.isRefetching} onRefresh={() => void refresh()} />}
      ListHeaderComponent={<View style={{ gap: 12 }}>
        <ThemedText accessibilityRole="header" style={styles.heading}>Lending</ThemedText>
        <ThemedText themeColor="textSecondary">Track what comes back. Know what you owe.</ThemedText>
        <SegmentedControl label="Money direction" value={direction} options={[{ value: 'lent', label: 'To receive' }, { value: 'borrowed', label: 'To pay' }]} onChange={setDirection} />
        <SearchField label="Find a person" value={search} onChange={setSearch} placeholder="Search people" />
        <ViewFilter label="Show lending records" value={status} options={[{ value: 'outstanding', label: 'Outstanding' }, { value: 'settled', label: 'Settled' }, { value: 'all', label: 'All people' }]} onChange={setStatus} />
        {!verified ? <LendingAction label="Verify your email to connect with people" onPress={() => router.push('/(app)/(tabs)/profile')} /> : null}
        {(invitations.data?.pages.flatMap(p => p.data) ?? []).map(invite => <LendingRow key={invite.id}>
          <ThemedText style={styles.name}>{invite.sender_name} wants to connect</ThemedText>
          <ThemedText themeColor="textSecondary">{invite.loan_count} selected loan{invite.loan_count === 1 ? '' : 's'}. Accepting lets you review them; it does not acknowledge a debt.</ThemedText>
          <View style={styles.filters}><LendingAction variant="secondary" label="Accept connection" disabled={respond.isPending} onPress={() => respond.mutate({ id: invite.id, action: 'accept' })} /><LendingAction label="Decline" disabled={respond.isPending} onPress={() => respond.mutate({ id: invite.id, action: 'decline' })} /></View>
        </LendingRow>)}
        {invitations.hasNextPage ? <LendingAction label="More invitations" disabled={invitations.isFetchingNextPage} onPress={() => void invitations.fetchNextPage()} /> : null}
        {respond.error ? <ThemedText themeColor="danger">{errorMessage(respond.error)}</ThemedText> : null}
        {invitations.error ? <QueryErrorCard error={invitations.error} onRetry={() => void invitations.refetch()} /> : null}
        {people.error ? <QueryErrorCard error={people.error} onRetry={() => void people.refetch()} /> : null}
        {people.isLoading ? <ThemedText>Loading people…</ThemedText> : null}
      </View>}
      renderItem={({ item }) => <View style={{ flexDirection: 'row', alignItems: 'center', gap: 4 }}><AnimatedPressable style={{ flex: 1 }} accessibilityRole="button" accessibilityLabel={`Open ${item.name}`} onPress={() => router.push({ pathname: '/(app)/lending/[id]', params: { id: item.id } })}>
        <LendingRow><View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
          <UserAvatar name={item.name} size={40} />
          <View style={{ flex: 1 }}><ThemedText style={styles.name}>{item.name}</ThemedText><ThemedText themeColor="textSecondary" style={styles.small}>{item.is_connected ? 'Connected' : item.invitation_status === 'pending' ? 'Invitation sent' : 'Personal tracker'}</ThemedText></View>
          <SymbolView name={{ ios: 'chevron.right', android: 'chevron_right', web: 'chevron_right' }} size={20} tintColor={theme.textSecondary} />
          </View>
          {item.balances.filter(b => b.direction === direction).map(b => <LendingBalanceSummary key={b.currency_code} balance={b} />)}
          {!item.balances.length ? <ThemedText themeColor="textSecondary">No loans recorded yet</ThemedText> : null}
        </LendingRow></AnimatedPressable><PinButton kind="lending" id={item.id} name={item.name} pinned={item.is_pinned} /></View>}
      ListEmptyComponent={!people.isLoading && !people.error ? <EmptyState title={q ? 'No matching people' : status === 'settled' ? 'No settled records yet' : 'Nothing outstanding here'} description="Add a person to record money lent or borrowed. They don’t need an Ovezi account." action={{ label: 'Add person', onPress: () => router.push('/(app)/lending/person') }} /> : null}
      ListFooterComponent={people.hasNextPage ? <LendingAction label={people.isFetchingNextPage ? 'Loading…' : 'Load more people'} disabled={people.isFetchingNextPage} onPress={() => void people.fetchNextPage()} /> : null}
      onEndReached={() => { if (people.hasNextPage && !people.isFetching && !people.isFetchNextPageError) void people.fetchNextPage(); }} onEndReachedThreshold={0.4} />
  </SafeAreaView></ThemedView>;
}
const styles = StyleSheet.create({ brand: { paddingHorizontal: 24, paddingTop: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', minHeight: 60 }, list: { paddingHorizontal: 24, paddingBottom: 130, flexGrow: 1 }, heading: { fontSize: 32, lineHeight: 40, fontWeight: '600', marginTop: 20 }, filters: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 }, name: { fontSize: 18, lineHeight: 26, fontWeight: '600' }, small: { fontSize: 13 } });
