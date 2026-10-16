import { useState } from 'react';
import { Platform, StyleSheet, Text, TextInput as NativeTextInput, TextInputProps, useWindowDimensions, View, ViewStyle } from 'react-native';

/** Keep iOS's placeholder on the same baseline as the editor, including at large text sizes. */
export function TextInput({ style, placeholder, value, defaultValue, onChangeText, ref, ...props }: TextInputProps & { ref?: React.Ref<NativeTextInput> }) {
  const [text, setText] = useState(defaultValue ?? '');
  const { fontScale } = useWindowDimensions();
  if (Platform.OS !== 'ios' || props.multiline || !placeholder) {
    return <NativeTextInput ref={ref} {...props} style={style} placeholder={placeholder} value={value} defaultValue={defaultValue} onChangeText={onChangeText} />;
  }
  const flat = StyleSheet.flatten(style) ?? {};
  const scale = props.allowFontScaling === false ? 1 : props.maxFontSizeMultiplier ? Math.min(fontScale, props.maxFontSizeMultiplier) : fontScale;
  const height = Math.max(52, Math.ceil((flat.fontSize ?? 16) * scale * 1.5) + 28);
  const inset = flat.paddingHorizontal ?? flat.padding ?? 0;
  const typography = { fontSize: flat.fontSize ?? 16, fontFamily: flat.fontFamily, fontWeight: flat.fontWeight, fontVariant: flat.fontVariant, letterSpacing: flat.letterSpacing, textAlign: flat.textAlign };
  return <View style={[{ minHeight: height }, flat as ViewStyle, { padding: 0, paddingHorizontal: 0, paddingVertical: 0 }]}>
    <NativeTextInput ref={ref} {...props} accessibilityLabel={props.accessibilityLabel ?? placeholder}
      value={value} defaultValue={defaultValue} onChangeText={next => { setText(next); onChangeText?.(next); }}
      style={[StyleSheet.absoluteFill, typography, { color: flat.color, paddingHorizontal: inset, paddingVertical: 0, textAlignVertical: 'center' }]} />
    {!(value ?? text) ? <View pointerEvents="none" accessible={false} accessibilityElementsHidden importantForAccessibility="no-hide-descendants"
      style={[StyleSheet.absoluteFill, { justifyContent: 'center', paddingHorizontal: inset }]}>
      <Text numberOfLines={1} allowFontScaling={props.allowFontScaling} maxFontSizeMultiplier={props.maxFontSizeMultiplier}
        style={[typography, { color: props.placeholderTextColor ?? flat.color }]}>{placeholder}</Text>
    </View> : null}
  </View>;
}
