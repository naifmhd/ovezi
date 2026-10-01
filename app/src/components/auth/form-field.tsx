import { StyleSheet, TextInput, TextInputProps, useWindowDimensions, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

type FormFieldProps = TextInputProps & {
  label: string;
};

export function FormField({ label, style, multiline = false, ...props }: FormFieldProps) {
  const theme = useTheme();
  const { fontScale } = useWindowDimensions();
  const inputFontSize = StyleSheet.flatten(style)?.fontSize ?? 16;
  const scale = props.allowFontScaling === false ? 1 : props.maxFontSizeMultiplier
    ? Math.min(fontScale, props.maxFontSizeMultiplier) : fontScale;

  return (
    <View style={styles.container}>
      <ThemedText style={styles.label} themeColor="textSecondary">{label}</ThemedText>
      <TextInput
        autoCapitalize="none"
        placeholderTextColor={theme.textSecondary}
        accessibilityLabel={label}
        selectionColor={theme.interactive}
        multiline={multiline}
        style={[
          styles.input,
          multiline ? styles.multiline : { height: Math.max(54, Math.ceil(inputFontSize * 1.5 * scale) + 28) },
          { color: theme.text, backgroundColor: theme.backgroundElement, borderColor: theme.controlBorder },
          style,
        ]}
        {...props}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { gap: 7 },
  label: { fontSize: 13, lineHeight: 20, fontWeight: '500' },
  input: {
    minHeight: 54,
    paddingVertical: 0,
    textAlignVertical: 'center',
    borderWidth: 1,
    borderRadius: Radius.control,
    paddingHorizontal: 16,
    fontSize: 16,
  },
  multiline: { paddingVertical: 14, textAlignVertical: 'top' },
});
