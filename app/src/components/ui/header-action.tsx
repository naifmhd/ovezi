import { Pressable, type PressableProps, StyleSheet } from 'react-native';

export function HeaderAction({ style, ...props }: PressableProps) {
  return <Pressable accessibilityRole="button" {...props} style={(state) => [styles.target, typeof style === 'function' ? style(state) : style]} />;
}
const styles = StyleSheet.create({ target: { minWidth: 48, minHeight: 48, justifyContent: 'center', paddingVertical: 10 } });
