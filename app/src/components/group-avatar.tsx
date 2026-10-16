import { Image } from 'expo-image';
import { useState } from 'react';
import { useReducedMotion } from 'react-native-reanimated';
import { StyleSheet } from 'react-native';

import { ThemedView } from '@/components/themed-view';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { groupInitials, groupTone } from '@/lib/group-identity';
import { useAuthStore } from '@/stores/auth-store';
import type { Group } from '@/types/api';

export function GroupAvatar({ group, size = 40 }: { group: Group; size?: number }) {
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
  const tone = groupTone(group.id, theme);

  return (
    <ThemedView
      aria-hidden
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
        <ThemedText maxFontSizeMultiplier={1.3} style={{ color: tone.foreground, fontSize: size * 0.36, lineHeight: size * 0.5, fontWeight: '700' }}>
          {groupInitials(group.name)}
        </ThemedText>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  avatar: { alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  image: { width: '100%', height: '100%' },
});
