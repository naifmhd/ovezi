import { BlurView } from 'expo-blur';
import {
  GlassView,
  isGlassEffectAPIAvailable,
  isLiquidGlassAvailable,
  type GlassViewProps,
} from 'expo-glass-effect';
import { PropsWithChildren, useEffect, useState } from 'react';
import { AccessibilityInfo, Platform, StyleSheet, View, type ViewProps } from 'react-native';

import { Radius } from '@/constants/theme';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { useTheme } from '@/hooks/use-theme';

type PlatformMaterialProps = PropsWithChildren<ViewProps> & {
  interactive?: boolean;
  glassStyle?: GlassViewProps['glassEffectStyle'];
};

export function PlatformMaterial({
  children,
  glassStyle = 'regular',
  interactive = false,
  style,
  ...props
}: PlatformMaterialProps) {
  const theme = useTheme();
  const scheme = useColorScheme();
  const [reduceTransparency, setReduceTransparency] = useState(true);

  useEffect(() => {
    if (Platform.OS !== 'ios') return;
    void AccessibilityInfo.isReduceTransparencyEnabled().then(setReduceTransparency).catch(() => setReduceTransparency(true));
    const subscription = AccessibilityInfo.addEventListener(
      'reduceTransparencyChanged',
      setReduceTransparency,
    );
    return () => subscription.remove();
  }, []);

  const canUseGlass = Platform.OS === 'ios'
    && !reduceTransparency
    && isGlassEffectAPIAvailable()
    && isLiquidGlassAvailable();

  if (canUseGlass) {
    return (
      <GlassView
        colorScheme={scheme === 'dark' ? 'dark' : 'light'}
        glassEffectStyle={glassStyle}
        isInteractive={interactive}
        style={[styles.clip, style]}
        {...props}>
        {children}
      </GlassView>
    );
  }

  if (Platform.OS === 'ios' && !reduceTransparency) {
    return (
      <BlurView
        intensity={72}
        style={[styles.clip, styles.fallbackBorder, { borderColor: theme.border }, style]}
        tint={scheme === 'dark' ? 'systemMaterialDark' : 'systemMaterialLight'}
        {...props}>
        {children}
      </BlurView>
    );
  }

  return (
    <View
      style={[
        styles.fallback,
        styles.fallbackBorder,
        { backgroundColor: theme.surfaceRaised, borderColor: theme.border },
        style,
      ]}
      {...props}>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  clip: { overflow: 'hidden', borderRadius: Radius.card },
  fallback: {
    elevation: 5,
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.14,
    shadowRadius: 18,
  },
  fallbackBorder: { borderWidth: StyleSheet.hairlineWidth },
});
