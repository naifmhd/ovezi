import { Image } from 'expo-image';

import { useColorScheme } from '@/hooks/use-color-scheme';

const lightMark = require('@/assets/images/logo-mark-navy.png');
const darkMark = require('@/assets/images/logo-mark-mint-glow.png');

export function BrandMark({ size = 64 }: { size?: number }) {
  const colorScheme = useColorScheme();

  return (
    <Image
      accessibilityLabel="Ovezi"
      contentFit="contain"
      source={colorScheme === 'dark' ? darkMark : lightMark}
      style={{ width: size, height: size }}
    />
  );
}
