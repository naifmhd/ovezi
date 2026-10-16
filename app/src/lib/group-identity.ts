import type { Colors } from '@/constants/theme';

type Theme = (typeof Colors)['light'] | (typeof Colors)['dark'];

export function groupTone(groupId: number, theme: Theme) {
  const tones = [
    { background: theme.surfaceSubtle, foreground: theme.interactive },
    { background: theme.accentBlueSurface, foreground: theme.accentBlue },
    { background: theme.accentVioletSurface, foreground: theme.accentViolet },
    { background: theme.accentCoralSurface, foreground: theme.accentCoral },
    { background: theme.accentAmberSurface, foreground: theme.accentAmber },
  ];
  return tones[Math.abs(groupId) % tones.length];
}

export function groupInitials(name: string) {
  const words = name.trim().split(/\s+/u).filter(Boolean);
  return words.slice(0, 2).map((word) => Array.from(word)[0]).join('').toLocaleUpperCase() || 'G';
}
