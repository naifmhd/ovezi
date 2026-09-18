import { StyleSheet, View } from 'react-native';

import { useTheme } from '@/hooks/use-theme';

export function BrandMark({ size = 64 }: { size?: number }) {
  const theme = useTheme();
  const stroke = Math.max(4, Math.round(size * 0.09));
  const arcSize = Math.round(size * 0.72);

  return (
    <View accessibilityLabel="Ovezi" style={[styles.mark, { width: size, height: size }]}>
      <View
        style={[
          styles.arc,
          {
            width: arcSize,
            height: arcSize,
            borderColor: theme.primary,
            borderRightColor: 'transparent',
            borderWidth: stroke,
            left: 0,
            top: 1,
          },
        ]}
      />
      <View
        style={[
          styles.arc,
          {
            width: arcSize,
            height: arcSize,
            borderColor: theme.primary,
            borderLeftColor: 'transparent',
            borderWidth: stroke,
            bottom: 1,
            right: 0,
          },
        ]}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  mark: { position: 'relative' },
  arc: { position: 'absolute', borderRadius: 999, transform: [{ rotate: '-28deg' }] },
});
