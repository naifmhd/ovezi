import { DocumentScreen } from '@/components/document-screen';

export default function TermsScreen() {
  return (
    <DocumentScreen
      eyebrow="Draft for legal review"
      intro="Ovezi helps people record shared expenses and settlements. It does not hold, transfer, or process money between users."
      sections={[
        {
          title: 'Using Ovezi',
          paragraphs: [
            'Use Ovezi only for lawful purposes and provide accurate information when recording expenses, splits, settlements, invitations, and placeholder contacts.',
            'You are responsible for protecting your account and for activity performed through your login methods.',
          ],
        },
        {
          title: 'Shared records',
          paragraphs: [
            'Group members can edit or add records according to their role and the group’s permissions. Changes may affect balances and are shown in group activity.',
            'A settlement records a payment that happened outside Ovezi. Recording it does not cause a bank transfer or prove that money was received.',
          ],
        },
        {
          title: 'Accounts and history',
          paragraphs: [
            'You may export your information or delete your account from the app. Shared financial history may be preserved in a de-identified or inactive form so other members’ records remain consistent.',
          ],
        },
        {
          title: 'Service availability',
          paragraphs: [
            'Ovezi may change, suspend, or discontinue features and may restrict abusive or unsafe use. Keep independent records for information you cannot afford to lose.',
          ],
        },
        {
          title: 'Before release',
          paragraphs: [
            'These terms are a product draft, not final legal terms. Governing law, dispute handling, liability, eligibility, contact details, and other required provisions must be approved before public launch.',
          ],
        },
      ]}
      title="Terms"
    />
  );
}
