import { Image } from 'expo-image';
import { StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useColorScheme } from '@/hooks/use-color-scheme';

const lightMark = require('@/assets/images/logo-mark-navy.png');
const darkMark = require('@/assets/images/logo-mark-mint.png');
const lightIcon = require('@/assets/images/icon-light.png');
const darkIcon = require('@/assets/images/icon-dark.png');

export function BrandMark({ size = 64, finish = 'flat', decorative = false }: { size?: number; finish?: 'flat' | 'soft'; decorative?: boolean }) {
  const colorScheme = useColorScheme();
  const dark = colorScheme === 'dark';

  return (
    <Image
      accessible={!decorative}
      accessibilityLabel={decorative ? undefined : 'Ovezi'}
      contentFit="contain"
      source={finish === 'soft' ? dark ? darkIcon : lightIcon : dark ? darkMark : lightMark}
      style={{ width: size, height: size, borderRadius: finish === 'soft' ? size * 0.25 : 0 }}
    />
  );
}

export function BrandLockup({ size = 36, tagline = false }: { size?: number; tagline?: boolean }) {
  return (
    <View style={styles.lockup}>
      <BrandMark decorative finish="soft" size={size} />
      <View style={styles.copy}>
        <ThemedText accessibilityLabel="Ovezi" style={[styles.wordmark, { fontSize: size * 0.65, lineHeight: size * 0.82 }]}>ovezi</ThemedText>
        {tagline ? <ThemedText style={styles.tagline} themeColor="textSecondary">Split. Share. Settle.</ThemedText> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  lockup: { flexDirection: 'row', alignItems: 'center', gap: 12, flexShrink: 1 },
  copy: { flexShrink: 1, gap: 3 },
  wordmark: { fontWeight: '600', letterSpacing: -1.2 },
  tagline: { fontSize: 13, lineHeight: 19 },
});
