import { Image } from 'expo-image';
import { useState } from 'react';
import { useReducedMotion } from 'react-native-reanimated';
import { StyleSheet } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';

export function UserAvatar({ imageUrl, name, size = 48 }: { imageUrl?: string | null; name: string; size?: number }) {
  return <AvatarContent imageUrl={imageUrl} key={imageUrl ?? 'initials'} name={name} size={size} />;
}

function AvatarContent({ imageUrl, name, size }: { imageUrl?: string | null; name: string; size: number }) {
  const [imageFailed, setImageFailed] = useState(false);
  const reduceMotion = useReducedMotion();

  return (
    <ThemedView
      type="backgroundSelected"
      style={[styles.avatar, { borderRadius: size * 0.38, height: size, width: size }]}>
      {imageUrl && !imageFailed ? (
        <Image
          accessibilityLabel={`${name}'s profile photo`}
          contentFit="cover"
          onError={() => setImageFailed(true)}
          source={imageUrl}
          style={styles.image}
          transition={reduceMotion ? 0 : 150}
        />
      ) : (
        <ThemedText style={[styles.initial, { fontSize: size * 0.43 }]} themeColor="primary">
          {name.slice(0, 1).toUpperCase()}
        </ThemedText>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  avatar: { alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  image: { width: '100%', height: '100%' },
  initial: { fontWeight: '600' },
});
