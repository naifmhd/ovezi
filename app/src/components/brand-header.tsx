import { ReactNode } from 'react';
import { StyleSheet, View } from 'react-native';
import { BrandLockup } from '@/components/brand-mark';
import { Spacing } from '@/constants/theme';

/** Shared brand and secondary-action placement across the main tabs. */
export function BrandHeader({ children }: { children?: ReactNode }) {
  return <View style={styles.header}>
    <BrandLockup size={24} />
    {children ? <View style={styles.actions}>{children}</View> : null}
  </View>;
}

const styles = StyleSheet.create({
  header: { paddingHorizontal: Spacing.four, paddingTop: Spacing.two, minHeight: 56, flexDirection: 'row', flexWrap: 'wrap', alignContent: 'center', alignItems: 'center', justifyContent: 'space-between', columnGap: Spacing.three },
  actions: { flexShrink: 1, maxWidth: '100%', marginStart: 'auto', flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'flex-end' },
});
