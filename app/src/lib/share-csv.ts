import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

function safeFilename(name: string) {
  const normalized = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  return `${normalized || 'ovezi-group'}-history.csv`;
}

export async function shareCsv(csv: string, groupName: string) {
  const filename = safeFilename(groupName);

  if (Platform.OS === 'web') {
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(url);
    return;
  }

  if (!FileSystem.cacheDirectory || !(await Sharing.isAvailableAsync())) {
    throw new Error('File sharing is not available on this device.');
  }

  const file = `${FileSystem.cacheDirectory}${filename}`;
  await FileSystem.writeAsStringAsync(file, csv);
  await Sharing.shareAsync(file, {
    dialogTitle: `Export ${groupName}`,
    mimeType: 'text/csv',
    UTI: 'public.comma-separated-values-text',
  });
}
