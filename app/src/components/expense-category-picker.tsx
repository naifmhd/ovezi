import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { SymbolView } from 'expo-symbols';
import { View } from 'react-native';
import { FormField } from '@/components/auth/form-field';
import { PrimaryButton } from '@/components/auth/primary-button';
import { ExpenseEditorSheet, ExpenseSelectionRow } from '@/components/expense-editor-controls';
import { LendingAction } from '@/components/lending-ui';
import { QueryErrorCard } from '@/components/query-error-card';
import { ThemedText } from '@/components/themed-text';
import { SearchField } from '@/components/ui/screen-controls';
import { apiRequest, errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';
import { useTheme } from '@/hooks/use-theme';

type Category = { id: number | null; name: string; archived_at: string | null };
type Categories = { data: Category[]; recent: string[] };
export function ExpenseCategoryPicker({ visible, value, onClose, onChange }: { visible: boolean; value: string; onClose: () => void; onChange: (name: string) => void }) {
  const token = useAuthStore(s => s.token)!;
  const userId = useAuthStore(s => s.user!.id);
  const theme = useTheme();
  const client = useQueryClient();
  const [search, setSearch] = useState('');
  const [managing, setManaging] = useState(false);
  const [editing, setEditing] = useState<Category | null>(null);
  const [name, setName] = useState('');
  const key = ['expense-categories', userId];
  const query = useQuery({ queryKey: key, enabled: visible, queryFn: ({ signal }) => apiRequest<Categories>('/expense-categories', { token, signal }) });
  const mutation = useMutation({ mutationFn: (change: { id?: number; name?: string; archived?: boolean }) => apiRequest<{ data: Category }>(`/expense-categories${change.id ? `/${change.id}` : ''}`, { token, method: change.id ? 'PATCH' : 'POST', body: { ...(change.name ? { name: change.name } : {}), ...(change.archived === undefined ? {} : { archived: change.archived }) } }), onSuccess: async (result, change) => {
    await client.invalidateQueries({ queryKey: key });
    if (change.id === undefined) { onChange(result.data.name); onClose(); setSearch(''); }
    setEditing(null);
  } });
  const all = query.data?.data ?? [];
  const recent = query.data?.recent ?? [];
  const matches = all.filter(item => (managing ? item.id !== null : !item.archived_at) && item.name.toLowerCase().includes(search.trim().toLowerCase()));
  const sorted = [...matches].sort((a, b) => { const ai = recent.indexOf(a.name.toLowerCase()), bi = recent.indexOf(b.name.toLowerCase()); return (ai < 0 ? 999 : ai) - (bi < 0 ? 999 : bi) || a.name.localeCompare(b.name); });
  return <ExpenseEditorSheet title={managing ? 'Manage categories' : 'Category'} visible={visible} onClose={() => { setManaging(false); setEditing(null); mutation.reset(); onClose(); }}>
    {managing ? <><LendingAction label="Back to categories" onPress={() => { setManaging(false); setEditing(null); mutation.reset(); }} /><ThemedText themeColor="textSecondary">Your custom categories sync with your account. Renaming or archiving keeps past expense labels unchanged.</ThemedText></> : null}
    <SearchField label="Search categories" placeholder="Find or add a category" value={search} onChange={setSearch} />
    {query.isLoading ? <ThemedText>Loading categories…</ThemedText> : null}
    {query.error ? <QueryErrorCard error={query.error} onRetry={() => void query.refetch()} /> : null}
    {!managing ? <ExpenseSelectionRow label="No category" selected={!value} onPress={() => { onChange(''); onClose(); }} /> : null}
    {!managing && recent.some(item => sorted.some(category => category.name.toLowerCase() === item)) ? <ThemedText themeColor="textSecondary">Recently used first</ThemedText> : null}
    {sorted.map(item => managing ? <View key={item.id} style={{ gap: 4, paddingVertical: 10, borderBottomWidth: 0.5, borderBottomColor: theme.border }}>
      <ThemedText style={{ fontWeight: '600' }}>{item.name}{item.archived_at ? ' · Archived' : ''}</ThemedText>
      {editing?.id === item.id ? <><FormField label="Category name" value={name} onChangeText={setName} maxLength={50} /><PrimaryButton label="Save category name" loadingLabel="Saving category…" loading={mutation.isPending} disabled={!name.trim()} onPress={() => mutation.mutate({ id: item.id!, name: name.trim() })} /></> : <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 12 }}><LendingAction label={`Rename ${item.name}`} disabled={mutation.isPending} onPress={() => { setEditing(item); setName(item.name); mutation.reset(); }} /><LendingAction label={`${item.archived_at ? 'Restore' : 'Archive'} ${item.name}`} disabled={mutation.isPending} onPress={() => mutation.mutate({ id: item.id!, archived: !item.archived_at })} /></View>}
    </View> : <ExpenseSelectionRow key={item.id ?? item.name} label={item.name} selected={item.name.toLowerCase() === value.toLowerCase()} leading={<SymbolView name={{ ios: 'tag', android: 'sell', web: 'sell' }} size={20} tintColor={theme.textSecondary} />} onPress={() => { onChange(item.name); onClose(); }} />)}
    {managing && !matches.length ? <ThemedText themeColor="textSecondary">No custom categories found.</ThemedText> : null}
    {!managing && search.trim() && query.data && !all.some(item => item.name.toLowerCase() === search.trim().toLowerCase()) ? <PrimaryButton label={`Add “${search.trim()}”`} loadingLabel="Adding category…" disabled={search.trim().length > 50} loading={mutation.isPending} onPress={() => mutation.mutate({ name: search.trim() })} /> : null}
    {!managing ? <LendingAction label="Manage categories" onPress={() => { setManaging(true); setSearch(''); mutation.reset(); }} /> : null}
    {mutation.error ? <ThemedText accessibilityLiveRegion="polite" themeColor="danger">{errorMessage(mutation.error)}</ThemedText> : null}
  </ExpenseEditorSheet>;
}
