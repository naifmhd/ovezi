import '@/global.css';

import { Platform } from 'react-native';

export const Colors = {
  light: {
    text: '#0A1128',
    background: '#F7F8F6',
    backgroundElement: '#FFFFFF',
    backgroundSelected: '#E0F5EC',
    surface: '#FFFFFF',
    surfaceRaised: '#FFFFFF',
    surfaceSubtle: '#E0F5EC',
    textSecondary: '#5D6975',
    border: '#D5DDE0',
    controlBorder: '#7A8B84',
    primary: '#00F5A0',
    primaryText: '#0A1128',
    interactive: '#006A50',
    positive: '#17663F',
    danger: '#AE3048',
    warning: '#965300',
    information: '#2859C5',
    positiveSurface: '#E0F5EC',
    dangerSurface: '#FDEDEC',
    informationSurface: '#EAF0FF',
    warningSurface: '#FFF3D8',
    accentBlue: '#355F9C',
    accentBlueSurface: '#E7EEFB',
    accentViolet: '#775291',
    accentVioletSurface: '#EFE7F6',
    accentCoral: '#A64A22',
    accentCoralSurface: '#FCEBDD',
    accentAmber: '#965300',
    accentAmberSurface: '#FFF3D8',
    overlay: 'rgba(23, 32, 51, 0.42)',
  },
  dark: {
    text: '#F5F7FC',
    background: '#0D1422',
    backgroundElement: '#172231',
    backgroundSelected: '#1B3833',
    surface: '#172231',
    surfaceRaised: '#172231',
    surfaceSubtle: '#1B3833',
    textSecondary: '#B4BFCE',
    border: '#344356',
    controlBorder: '#687F96',
    primary: '#00F5A0',
    primaryText: '#0A1128',
    interactive: '#70E8BF',
    positive: '#9CDEB8',
    danger: '#FFA7B5',
    warning: '#F6C56B',
    information: '#8BAEFF',
    positiveSurface: '#1B3833',
    dangerSurface: '#43252D',
    informationSurface: '#192B54',
    warningSurface: '#43351B',
    accentBlue: '#ADC9FF',
    accentBlueSurface: '#24354F',
    accentViolet: '#D9BAF1',
    accentVioletSurface: '#392D47',
    accentCoral: '#FFC09E',
    accentCoralSurface: '#413028',
    accentAmber: '#F6C56B',
    accentAmberSurface: '#43351B',
    overlay: 'rgba(3, 6, 15, 0.64)',
  },
} as const;

export type ThemeColor = keyof typeof Colors.light & keyof typeof Colors.dark;

export const Fonts = Platform.select({
  ios: {
    /** iOS `UIFontDescriptorSystemDesignDefault` */
    sans: 'system-ui',
    /** iOS `UIFontDescriptorSystemDesignSerif` */
    serif: 'ui-serif',
    /** iOS `UIFontDescriptorSystemDesignRounded` */
    rounded: 'ui-rounded',
    /** iOS `UIFontDescriptorSystemDesignMonospaced` */
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: 'var(--font-display)',
    serif: 'var(--font-serif)',
    rounded: 'var(--font-rounded)',
    mono: 'var(--font-mono)',
  },
});

export const Spacing = {
  half: 2,
  one: 4,
  two: 8,
  three: 16,
  four: 24,
  five: 32,
  six: 64,
} as const;

export const Radius = {
  control: 15,
  card: 22,
  sheet: 28,
  pill: 999,
} as const;

export const Motion = {
  instant: 100,
  fast: 160,
  standard: 240,
  emphasized: 380,
} as const;

export const BottomTabInset = Platform.select({ ios: 50, android: 80 }) ?? 0;
export const MaxContentWidth = 800;

export const Typography = {
  body: { fontSize: 16, lineHeight: 24, fontWeight: '400' },
  label: { fontSize: 13, lineHeight: 19, fontWeight: '500' },
  heading: { fontSize: 28, lineHeight: 35, fontWeight: '600' },
  amount: { fontSize: 36, lineHeight: 44, fontWeight: '500', fontVariant: ['tabular-nums'] },
} as const;

export const TouchTarget = 48;
