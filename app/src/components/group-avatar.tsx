import { Image } from 'expo-image';
import { useState } from 'react';
import { StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
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

  return (
    <ThemedView
      type="backgroundSelected"
      style={[styles.avatar, { borderRadius: size * 0.35, height: size, width: size }]}>
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
          transition={150}
        />
      ) : (
        <ThemedText style={[styles.initial, { fontSize: size * 0.43 }]} themeColor="primary">
          {group.name.slice(0, 1).toUpperCase()}
        </ThemedText>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  avatar: { alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  image: { width: '100%', height: '100%' },
  initial: { fontWeight: '800' },
});
