import { Image } from 'expo-image';
import { SymbolView } from 'expo-symbols';
import { useState } from 'react';
import { useReducedMotion } from 'react-native-reanimated';
import { StyleSheet } from 'react-native';

import { ThemedView } from '@/components/themed-view';
import { useTheme } from '@/hooks/use-theme';
import { useAuthStore } from '@/stores/auth-store';
import type { Group } from '@/types/api';

export function GroupAvatar({ group, size = 46 }: { group: Group; size?: number }) {
  const token = useAuthStore((state) => state.token)!;

  return (
    <AvatarContent
      group={group}
      key={group.photo_url ?? `initial:${group.name}`}
      size={size}
      token={token}
    />
  );
}

function AvatarContent({ group, size, token }: { group: Group; size: number; token: string }) {
  const [imageFailed, setImageFailed] = useState(false);
  const reduceMotion = useReducedMotion();
  const theme = useTheme();
  const tone = groupTone(group.name, theme);

  return (
    <ThemedView
      type="backgroundSelected"
      style={[
        styles.avatar,
        { backgroundColor: tone.background, borderRadius: size * 0.3, height: size, width: size },
      ]}>
      {group.photo_url && !imageFailed ? (
        <Image
          accessibilityLabel={`${group.name} group photo`}
          cachePolicy="memory"
          contentFit="cover"
          onError={() => setImageFailed(true)}
          source={{
            uri: group.photo_url,
            headers: { Authorization: `Bearer ${token}` },
          }}
          style={styles.image}
          transition={reduceMotion ? 0 : 150}
        />
      ) : (
        <SymbolView
          name={groupSymbol(group.name)}
          size={size * 0.42}
          tintColor={tone.foreground}
          weight="semibold"
        />
      )}
    </ThemedView>
  );
}

function groupTone(name: string, theme: ReturnType<typeof useTheme>) {
  const normalized = name.toLowerCase();
  if (/trip|travel|holiday|vacation|bali/.test(normalized)) {
    return { background: theme.accentBlueSurface, foreground: theme.accentBlue };
  }
  if (/home|house|family|apartment/.test(normalized)) {
    return { background: theme.accentVioletSurface, foreground: theme.accentViolet };
  }
  if (/work|office|team/.test(normalized)) {
    return { background: theme.accentVioletSurface, foreground: theme.accentViolet };
  }
  if (/food|dinner|lunch|cafe|coffee|grocer/.test(normalized)) {
    return { background: theme.accentCoralSurface, foreground: theme.accentCoral };
  }
  return { background: theme.accentVioletSurface, foreground: theme.accentViolet };
}

function groupSymbol(name: string): Parameters<typeof SymbolView>[0]['name'] {
  const normalized = name.toLowerCase();
  if (/trip|travel|holiday|vacation|bali/.test(normalized)) {
    return { ios: 'airplane', android: 'flight', web: 'flight' };
  }
  if (/home|house|family|apartment/.test(normalized)) {
    return { ios: 'house.fill', android: 'home', web: 'home' };
  }
  if (/work|office|team/.test(normalized)) {
    return { ios: 'briefcase.fill', android: 'work', web: 'work' };
  }
  if (/food|dinner|lunch|cafe|coffee|grocer/.test(normalized)) {
    return { ios: 'fork.knife', android: 'restaurant', web: 'restaurant' };
  }
  return { ios: 'person.3.fill', android: 'groups', web: 'groups' };
}

const styles = StyleSheet.create({
  avatar: { alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  image: { width: '100%', height: '100%' },
});
