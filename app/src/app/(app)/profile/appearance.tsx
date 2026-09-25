import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { StyleSheet, View } from 'react-native';

import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { HeaderAction } from '@/components/ui/header-action';
import { Radius } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { type AppearancePreference } from '@/lib/appearance';
import { selectionHaptic } from '@/lib/haptics';
import { useAppearanceStore } from '@/stores/appearance-store';

const options: { value: AppearancePreference; title: string; description: string; icon: Parameters<typeof SymbolView>[0]['name'] }[] = [
  { value: 'auto', title: 'Auto', description: 'Follow your device’s light or dark setting.', icon: { ios: 'circle.lefthalf.filled', android: 'contrast', web: 'contrast' } },
  { value: 'light', title: 'Light', description: 'A bright background, any time of day.', icon: { ios: 'sun.max.fill', android: 'light_mode', web: 'light_mode' } },
  { value: 'dark', title: 'Dark', description: 'A darker background, any time of day.', icon: { ios: 'moon.fill', android: 'dark_mode', web: 'dark_mode' } },
];

export default function AppearanceScreen() {
  const theme = useTheme();
  const { preference, saving, error, setPreference } = useAppearanceStore();

  return (
    <AppScreen title="Appearance" action={
      <HeaderAction onPress={() => router.dismissTo('/(app)/(tabs)/profile')}><ThemedText themeColor="interactive">Done</ThemedText></HeaderAction>
    }>
      <ThemedText themeColor="textSecondary">Make Ovezi feel right for you. Changes apply instantly and are saved on this device.</ThemedText>
      <View accessibilityRole="radiogroup" accessibilityLabel="Appearance" style={styles.options}>
        {options.map((option) => {
          const selected = preference === option.value;
          return (
            <AnimatedPressable
              key={option.value}
              accessibilityRole="radio"
              accessibilityLabel={option.title}
              accessibilityHint={option.description}
              accessibilityState={{ checked: selected, disabled: saving }}
              aria-checked={selected}
              disabled={saving}
              onPress={() => { selectionHaptic(); void setPreference(option.value); }}
              style={[styles.option, { backgroundColor: selected ? theme.backgroundSelected : theme.surface, borderColor: selected ? theme.interactive : theme.border }]}>
              <View style={[styles.icon, { backgroundColor: theme.surface }]}>
                <SymbolView name={option.icon} size={24} tintColor={theme.interactive} />
              </View>
              <View style={styles.copy}>
                <ThemedText style={styles.title}>{option.title}</ThemedText>
                <ThemedText style={styles.description} themeColor="textSecondary">{option.description}</ThemedText>
              </View>
              <View accessible={false} style={[styles.radio, { borderColor: selected ? theme.interactive : theme.controlBorder }]}>
                {selected ? <View style={[styles.selected, { backgroundColor: theme.interactive }]} /> : null}
              </View>
            </AnimatedPressable>
          );
        })}
      </View>
      {error ? <ThemedText accessibilityRole="alert" themeColor="danger">{error}</ThemedText> : null}
    </AppScreen>
  );
}

const styles = StyleSheet.create({
  options: { gap: 12, marginTop: 8 },
  option: { minHeight: 100, padding: 16, borderRadius: Radius.card, borderWidth: 1, flexDirection: 'row', alignItems: 'center', gap: 14 },
  icon: { width: 44, height: 44, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
  copy: { flex: 1 },
  title: { fontSize: 17, lineHeight: 24, fontWeight: '600' },
  description: { fontSize: 13, lineHeight: 20, marginTop: 4 },
  radio: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: 'center', justifyContent: 'center' },
  selected: { width: 10, height: 10, borderRadius: 5 },
});
