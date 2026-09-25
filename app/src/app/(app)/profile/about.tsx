import Constants from 'expo-constants';
import { StyleSheet, View } from 'react-native';

import { BrandLockup } from '@/components/brand-mark';
import { DocumentScreen } from '@/components/document-screen';

const version = Constants.expoConfig?.version ?? '1.0.0';

export default function AboutScreen() {
  return (
    <DocumentScreen
      eyebrow={`Ovezi ${version}`}
      hero={(
        <View style={styles.brand}>
          <BrandLockup size={88} />
        </View>
      )}
      intro="Split. Share. Settle."
      sections={[
        {
          title: 'Why Ovezi',
          paragraphs: [
            'Ovezi is a mobile-first expense-splitting app built to make shared costs understandable without ads, daily limits, or paywalled core features.',
          ],
        },
        {
          title: 'What it does',
          paragraphs: [
            'Create groups, split expenses in flexible ways, track 1-on-1 debts, record personal spending, simplify balances, and record settlements made outside the app.',
            'Multiple currencies are supported with captured conversion rates, so historical expenses remain stable when market rates change.',
          ],
        },
        {
          title: 'Payments',
          paragraphs: [
            'Ovezi does not move money or connect to bank accounts. Settlement records are confirmations of payments made elsewhere.',
          ],
        },
      ]}
      title="About"
    />
  );
}

const styles = StyleSheet.create({
  brand: { alignItems: 'center', gap: 8 },
  name: { fontSize: 24, lineHeight: 30, fontWeight: '600' },
});
