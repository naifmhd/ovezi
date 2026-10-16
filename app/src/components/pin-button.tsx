import { useMutation, useQueryClient } from '@tanstack/react-query';
import { SymbolView } from 'expo-symbols';
import { View } from 'react-native';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { apiRequest, errorMessage } from '@/lib/api-client';
import { useAuthStore } from '@/stores/auth-store';

export function PinButton({ kind, id, name, pinned = false }: { kind: 'groups' | 'lending'; id: number; name: string; pinned?: boolean }) {
  const token = useAuthStore(s => s.token)!;
  const client = useQueryClient();
  const theme = useTheme();
  const pin = useMutation({
    mutationFn: () => apiRequest(`/${kind === 'groups' ? 'groups' : 'lending/contacts'}/${id}/pin`, { token, method: 'PUT', body: { pinned: !pinned } }),
    onSuccess: () => client.invalidateQueries({ queryKey: [kind] }),
  });
  return <View>
    <AnimatedPressable accessibilityRole="button" accessibilityLabel={`${pinned ? 'Unpin' : 'Pin'} ${name}`} accessibilityState={{ selected: pinned, disabled: pin.isPending }} disabled={pin.isPending} onPress={() => pin.mutate()}
      style={{ minHeight: 48, minWidth: 48, alignItems: 'center', justifyContent: 'center' }}>
      <SymbolView name={{ ios: pinned ? 'pin.fill' : 'pin', android: 'push_pin', web: 'push_pin' }} size={20} tintColor={pinned ? theme.interactive : theme.textSecondary} />
      {pinned ? <ThemedText themeColor="interactive" style={{ fontSize: 11 }}>Pinned</ThemedText> : null}
    </AnimatedPressable>
    {pin.error ? <ThemedText themeColor="danger" style={{ maxWidth: 180 }}>{errorMessage(pin.error)}</ThemedText> : null}
  </View>;
}
