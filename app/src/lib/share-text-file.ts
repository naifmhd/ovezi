import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Platform } from 'react-native';

import { apiBaseUrl } from '@/lib/api-client';

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

export async function shareGroupHistoryPdf(token: string, groupId: number, groupName: string) {
  return shareDownload(token, `/groups/${groupId}/export?format=pdf`, `${safeSlug(groupName) || 'ovezi-group'}-history.pdf`, 'pdf', `Export ${groupName}`);
}

export async function shareLendingStatement(token: string, id: string, name: string, format: 'csv' | 'pdf', from: string, to: string) {
  const query = new URLSearchParams({ format, ...(from ? { from } : {}), ...(to ? { to } : {}) });
  return shareDownload(token, `/lending/contacts/${id}/statement?${query}`, `${safeSlug(name) || 'ovezi'}-statement.${format}`, format, `Statement for ${name}`);
}

async function shareDownload(token: string, path: string, filename: string, format: 'csv' | 'pdf', title: string) {
  const url = `${apiBaseUrl}${path}`;
  const mimeType = format === 'pdf' ? 'application/pdf' : 'text/csv';

  if (Platform.OS === 'web') {
    const response = await fetch(url, {
      headers: { Accept: mimeType, Authorization: `Bearer ${token}` },
    });
    if (!response.ok) {
      const error = await response.json().catch(() => ({}));
      throw new Error(Object.values(error.errors ?? {}).flat()[0] as string || error.message || 'Couldn’t prepare the export. Try again.');
    }

    const objectUrl = URL.createObjectURL(await response.blob());
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(objectUrl);
    return;
  }

  if (!FileSystem.cacheDirectory || !(await Sharing.isAvailableAsync())) {
    throw new Error('File sharing is not available on this device.');
  }

  const file = `${FileSystem.cacheDirectory}${filename}`;
  const result = await FileSystem.downloadAsync(url, file, {
    headers: { Accept: mimeType, Authorization: `Bearer ${token}` },
  });
  if (result.status < 200 || result.status >= 300) {
    let error: { errors?: Record<string, string[]>; message?: string } = {};
    try { error = JSON.parse(await FileSystem.readAsStringAsync(file)); } catch { /* A proxy may return HTML instead of API errors. */ }
    throw new Error(Object.values(error.errors ?? {}).flat()[0] as string || error.message || 'Couldn’t prepare the export. Try a shorter date range.');
  }

  await Sharing.shareAsync(result.uri, {
    dialogTitle: title,
    mimeType,
    UTI: format === 'pdf' ? 'com.adobe.pdf' : 'public.comma-separated-values-text',
  });
}
