import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

type TextFileOptions = {
  contents: string;
  filename: string;
  mimeType: string;
  dialogTitle: string;
  uti: string;
};

async function shareTextFile(options: TextFileOptions) {
  if (Platform.OS === 'web') {
    const url = URL.createObjectURL(new Blob([options.contents], { type: options.mimeType }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = options.filename;
    anchor.click();
    URL.revokeObjectURL(url);
    return;
  }

  if (!FileSystem.cacheDirectory || !(await Sharing.isAvailableAsync())) {
    throw new Error('File sharing is not available on this device.');
  }

  const file = `${FileSystem.cacheDirectory}${options.filename}`;
  await FileSystem.writeAsStringAsync(file, options.contents);
  await Sharing.shareAsync(file, {
    dialogTitle: options.dialogTitle,
    mimeType: options.mimeType,
    UTI: options.uti,
  });
}

function safeSlug(value: string) {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

export function shareCsv(csv: string, groupName: string) {
  return shareTextFile({
    contents: csv,
    filename: `${safeSlug(groupName) || 'ovezi-group'}-history.csv`,
    mimeType: 'text/csv;charset=utf-8',
    dialogTitle: `Export ${groupName}`,
    uti: 'public.comma-separated-values-text',
  });
}

export function sharePersonalData(json: string) {
  return shareTextFile({
    contents: json,
    filename: `ovezi-personal-data-${new Date().toISOString().slice(0, 10)}.json`,
    mimeType: 'application/json;charset=utf-8',
    dialogTitle: 'Export Ovezi personal data',
    uti: 'public.json',
  });
}
