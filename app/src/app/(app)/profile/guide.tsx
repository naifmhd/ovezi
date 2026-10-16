import { router } from 'expo-router';
import { StyleSheet, View } from 'react-native';
import { AppScreen } from '@/components/app-screen';
import { ThemedText } from '@/components/themed-text';
import { NavigationRow } from '@/components/ui/screen-controls';

export default function NavigationGuide() {
  return <AppScreen title="Find your way around" backFallback="/(app)/(tabs)/profile">
    <ThemedText themeColor="textSecondary">A place for shared expenses, a place for loans, and a clear overview of both.</ThemedText>
    <View>
      <NavigationRow label="Home" detail="See what you’ll receive, what you’ll pay, and what needs your attention." onPress={() => router.replace('/(app)/(tabs)')} />
      <NavigationRow label="Groups" detail="Split expenses for trips, home and everything you share. Pin the groups you use most." onPress={() => router.replace('/(app)/(tabs)/groups')} />
      <NavigationRow label="Lending" detail="Track money lent or borrowed, even with people who don’t use Ovezi. Each currency stays separate." onPress={() => router.replace('/(app)/(tabs)/lending')} />
      <NavigationRow label="Profile" detail="Find your settings, personal expenses and previous 1-on-1 records." onPress={() => router.replace('/(app)/(tabs)/profile')} />
    </View>
    <View style={styles.section}>
      <ThemedText accessibilityRole="header" style={styles.heading}>Your earlier records are still here</ThemedText>
      <ThemedText themeColor="textSecondary">Existing personal and 1-on-1 expenses keep their history and balances. New loans belong in Lending.</ThemedText>
      <NavigationRow label="Open previous records" onPress={() => router.push('/(app)/profile/history')} />
      <NavigationRow label="View all activity" detail="Search expenses, loans and repayments together, or filter by date." onPress={() => router.push('/(app)/profile/activity')} />
    </View>
    <ThemedText themeColor="textSecondary">You can return to this guide from Profile whenever you need it.</ThemedText>
  </AppScreen>;
}
const styles = StyleSheet.create({ section: { gap: 8, marginTop: 16 }, heading: { fontSize: 20, lineHeight: 28, fontWeight: '600' } });
