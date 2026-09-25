import { PropsWithChildren } from 'react';
import { Pressable, type PressableProps, type StyleProp, type ViewStyle } from 'react-native';
import Animated, {
  useAnimatedStyle,
  useReducedMotion,
  useSharedValue,
  withTiming,
} from 'react-native-reanimated';

import { Motion } from '@/constants/theme';

const AnimatedPressableBase = Animated.createAnimatedComponent(Pressable);

type AnimatedPressableProps = PropsWithChildren<Omit<PressableProps, 'style'>> & {
  style?: StyleProp<ViewStyle>;
  pressedScale?: number;
};

export function AnimatedPressable({
  children,
  disabled,
  onPressIn,
  onPressOut,
  pressedScale = 0.98,
  style,
  ...props
}: AnimatedPressableProps) {
  const reduceMotion = useReducedMotion();
  const pressed = useSharedValue(0);
  const animatedStyle = useAnimatedStyle(() => ({
    opacity: withTiming(pressed.value ? 0.82 : 1, { duration: Motion.instant }),
    transform: [{
      scale: reduceMotion
        ? 1
        : withTiming(pressed.value ? pressedScale : 1, { duration: Motion.instant }),
    }],
  }));

  return (
    <AnimatedPressableBase
      accessibilityRole="button"
      disabled={disabled}
      onPressIn={(event) => {
        pressed.value = 1;
        onPressIn?.(event);
      }}
      onPressOut={(event) => {
        pressed.value = 0;
        onPressOut?.(event);
      }}
      style={[style, animatedStyle]}
      {...props}>
      {children}
    </AnimatedPressableBase>
  );
}
