import { DocumentScreen } from '@/components/document-screen';

export default function PrivacyScreen() {
  return (
    <DocumentScreen
      eyebrow="Draft for legal review"
      intro="Ovezi uses the information needed to run shared expenses, calculate balances, secure accounts, and deliver invitations."
      sections={[
        {
          title: 'Information you provide',
          paragraphs: [
            'This includes your profile, login details, default currency, groups, expenses, settlements, friends, and any receipt images you choose to upload.',
            'A placeholder creator may record a name plus either an email address or phone number for someone who does not yet have an Ovezi account.',
          ],
        },
        {
          title: 'How information is used',
          paragraphs: [
            'Ovezi uses this data to authenticate you, calculate and simplify balances, show group history, process invitations, send requested notifications, prevent abuse, and maintain the service.',
          ],
        },
        {
          title: 'Who can see shared data',
          paragraphs: [
            'Members of a group can see that group’s members, expenses, splits, settlements, balances, and activity. Personal “Just for me” entries are not shared and never affect Ovezi balances.',
            'Service providers may process limited data for hosting, authentication, email, notifications, analytics, crash reporting, currency rates, and optional receipt processing.',
          ],
        },
        {
          title: 'Control and retention',
          paragraphs: [
            'You can export your personal data and request account deletion in the app. Account deletion removes access and personal profile use while retaining the minimum historical records needed to keep shared group history accurate.',
            'Placeholder history is claimed only after a verified email or phone match and an explicit confirmation of the records to merge.',
          ],
        },
        {
          title: 'Before release',
          paragraphs: [
            'This product summary is not the final legal privacy policy. Data processors, retention periods, contact details, and jurisdiction-specific rights must be reviewed and published before the public launch.',
          ],
        },
      ]}
      title="Privacy"
    />
  );
}
