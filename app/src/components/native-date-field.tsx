import DateTimePicker, { type DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { SymbolView } from 'expo-symbols';
import { useState } from 'react';
import { Platform, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { AnimatedPressable } from '@/components/ui/animated-pressable';
import { Radius } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { selectionHaptic } from '@/lib/haptics';

type NativeDateFieldProps = {
  label: string;
  value: string;
  onChange: (value: string) => void;
  minimumDate?: Date;
  optional?: boolean;
  emptyLabel?: string;
};

function parseDate(value: string) {
  const [year, month, day] = value.split('-').map(Number);
  const parsed = new Date(year, month - 1, day, 12);
  return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
}

function dateValue(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function NativeDateField({ label, minimumDate, onChange, optional = false, value, emptyLabel = 'No end date' }: NativeDateFieldProps) {
  const theme = useTheme();
  const [open, setOpen] = useState(false);
  const selectedDate = parseDate(value);

  function handleChange(event: DateTimePickerEvent, date?: Date) {
    if (Platform.OS === 'android') setOpen(false);
    if (event.type !== 'dismissed' && date) onChange(dateValue(date));
  }

  return (
    <View style={styles.container}>
      <ThemedText style={styles.label}>{label}</ThemedText>
      <AnimatedPressable
        accessibilityLabel={`${label}, ${value ? selectedDate.toLocaleDateString() : 'not set'}`}
        accessibilityRole="button"
        onPress={() => {
          selectionHaptic();
          setOpen((current) => !current);
        }}
        style={[styles.trigger, { backgroundColor: theme.surface, borderColor: theme.controlBorder }]}>
        <SymbolView
          name={{ ios: 'calendar', android: 'calendar_month', web: 'calendar_month' }}
          size={20}
          tintColor={theme.interactive}
        />
        <ThemedText style={styles.value} themeColor={value ? 'text' : 'textSecondary'}>
          {value ? selectedDate.toLocaleDateString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
          }) : emptyLabel}
        </ThemedText>
        <ThemedText themeColor="interactive">{open ? 'Hide' : 'Change'}</ThemedText>
      </AnimatedPressable>
      {open ? (
        <View style={[styles.pickerWrap, { backgroundColor: theme.surfaceSubtle }]}>
          <DateTimePicker
            display={Platform.OS === 'ios' ? 'inline' : 'default'}
            minimumDate={minimumDate}
            mode="date"
            onChange={handleChange}
            value={selectedDate}
          />
          {optional && value ? (
            <AnimatedPressable onPress={() => onChange('')} style={styles.clearAction}>
              <ThemedText style={styles.clearText} themeColor="interactive">Clear date</ThemedText>
            </AnimatedPressable>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { gap: 7 },
  label: { fontSize: 14, lineHeight: 20, fontWeight: '600' },
  trigger: {
    minHeight: 54,
    borderWidth: 1,
    borderRadius: Radius.control,
    paddingHorizontal: 15,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },
  value: { flex: 1, fontSize: 15, fontWeight: '600' },
  pickerWrap: { borderRadius: Radius.card, overflow: 'hidden', padding: 6 },
  clearAction: { minHeight: 44, alignItems: 'center', justifyContent: 'center' },
  clearText: { fontSize: 14, fontWeight: '600' },
});
