import { useLocalSearchParams } from 'expo-router';
import { ActivityFeed } from '@/components/activity-feed';
import { DocumentScreen } from '@/components/document-screen';
export default function GroupActivityScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const groupId = Number(id);
  if (!Number.isInteger(groupId) || groupId < 1) return <DocumentScreen title="Group activity" intro="This group link is invalid." sections={[]} />;
  return <ActivityFeed groupId={groupId} />;
}
