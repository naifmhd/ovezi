---
name: Ovezi — Tide
description: Calm shared spending in mint and navy, with native iOS and Android behavior.
colors:
  light-text: "#0A1128"
  light-background: "#F4F7F5"
  light-background-element: "#FFFFFF"
  light-background-selected: "#E2F2EB"
  light-surface: "#FFFFFF"
  light-surface-raised: "#FFFFFF"
  light-surface-subtle: "#E2F2EB"
  light-text-secondary: "#58685F"
  light-border: "#D8E1DC"
  light-control-border: "#7A8B84"
  light-primary: "#00F5A0"
  light-primary-text: "#0A1128"
  light-interactive: "#006A50"
  light-positive: "#17663F"
  light-danger: "#AE3048"
  light-warning: "#965300"
  light-information: "#2859C5"
  light-positive-surface: "#E2F2EB"
  light-danger-surface: "#FDEDEC"
  light-information-surface: "#EAF0FF"
  light-warning-surface: "#FFF3D8"
  light-accent-blue: "#355F9C"
  light-accent-blue-surface: "#E7EEFB"
  light-accent-violet: "#775291"
  light-accent-violet-surface: "#EFE7F6"
  light-accent-coral: "#A64A22"
  light-accent-coral-surface: "#FCEBDD"
  light-accent-amber: "#965300"
  light-accent-amber-surface: "#FFF3D8"
  light-overlay: "rgba(23, 32, 51, 0.42)"
  dark-text: "#F5F7FC"
  dark-background: "#0D1422"
  dark-background-element: "#172231"
  dark-background-selected: "#1B3833"
  dark-surface: "#172231"
  dark-surface-raised: "#172231"
  dark-surface-subtle: "#1B3833"
  dark-text-secondary: "#B4BFCE"
  dark-border: "#344356"
  dark-control-border: "#687F96"
  dark-primary: "#00F5A0"
  dark-primary-text: "#0A1128"
  dark-interactive: "#70E8BF"
  dark-positive: "#9CDEB8"
  dark-danger: "#FFA7B5"
  dark-warning: "#F6C56B"
  dark-information: "#8BAEFF"
  dark-positive-surface: "#1B3833"
  dark-danger-surface: "#43252D"
  dark-information-surface: "#192B54"
  dark-warning-surface: "#43351B"
  dark-accent-blue: "#ADC9FF"
  dark-accent-blue-surface: "#24354F"
  dark-accent-violet: "#D9BAF1"
  dark-accent-violet-surface: "#392D47"
  dark-accent-coral: "#FFC09E"
  dark-accent-coral-surface: "#413028"
  dark-accent-amber: "#F6C56B"
  dark-accent-amber-surface: "#43351B"
  dark-overlay: "rgba(3, 6, 15, 0.64)"
typography:
  heading:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "32px"
    lineHeight: "39px"
    fontWeight: 600
    letterSpacing: "-0.8px"
  amount:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "42px"
    lineHeight: "52px"
    fontWeight: 500
    letterSpacing: "-1.2px"
    fontFeature: "tabular-nums"
  body-ios:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "17px"
    lineHeight: "24px"
    fontWeight: 400
  body-android:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "16px"
    lineHeight: "24px"
    fontWeight: 400
  label:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "13px"
    lineHeight: "19px"
    fontWeight: 500
  section-title:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "17px"
    lineHeight: "25px"
    fontWeight: 600
  row-title:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "15px"
    lineHeight: "22px"
    fontWeight: 600
  row-amount:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "14px"
    lineHeight: "21px"
    fontWeight: 500
    fontFeature: "tabular-nums"
  button:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Roboto, sans-serif"
    fontSize: "16px"
    fontWeight: 600
rounded:
  control: "14px"
  card: "16px"
  balance: "20px"
  sheet: "28px"
  pill: "999px"
spacing:
  half: "2px"
  one: "4px"
  two: "8px"
  three: "16px"
  four: "24px"
  five: "32px"
  six: "64px"
components:
  button-primary:
    backgroundColor: "{colors.light-primary}"
    textColor: "{colors.light-primary-text}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "14px 18px"
  header-action:
    textColor: "{colors.light-interactive}"
    padding: "10px 0"
  form-field:
    backgroundColor: "{colors.light-surface}"
    textColor: "{colors.light-text}"
    rounded: "{rounded.control}"
    padding: "0 16px"
  choice-chip:
    backgroundColor: "{colors.light-surface}"
    textColor: "{colors.light-text}"
    rounded: "24px"
    padding: "12px 15px"
  choice-chip-selected:
    backgroundColor: "{colors.light-surface-subtle}"
    textColor: "{colors.light-interactive}"
    rounded: "24px"
    padding: "12px 15px"
  balance-panel:
    backgroundColor: "{colors.light-surface-subtle}"
    textColor: "{colors.light-text}"
    rounded: "{rounded.balance}"
    padding: "22px"
  group-row:
    textColor: "{colors.light-text}"
    padding: "16px 0"
  add-expense:
    backgroundColor: "{colors.light-primary}"
    textColor: "{colors.light-primary-text}"
    rounded: "{rounded.sheet}"
    padding: "0"
    height: "56px"
  navigation-web:
    backgroundColor: "{colors.light-surface}"
    textColor: "{colors.light-interactive}"
    height: "76px"
    padding: "8px 0 12px"
---

# Design System: Ovezi — Tide

## Overview

**Creative North Star: "Tide"**

Tide makes shared spending calm and immediately understandable. Mint identifies actions and soft balance surfaces; navy anchors the amounts and the Ovezi identity. Open lists leave financial information room to breathe, while bounded surfaces group summaries and forms.

This is a native mobile system. Use the platform’s system type, navigation, symbols, and input behavior. iOS native tabs use the available system material, including Liquid Glass on supported systems; Android uses native Material tabs. Financial content stays on stable, opaque surfaces. The user explicitly selected Tide and retained mint and navy.

**Key Characteristics:**
- Mint actions and navy financial hierarchy.
- Open rows with quiet dividers; rounded, bounded summary surfaces.
- Four labelled destinations and a separate icon-only Add expense action.
- Light and dark semantic roles, scalable text, and platform accessibility preferences.

This record describes the implemented shared system in `app/src/constants/theme.ts`, sampled components, and the native/web tab layouts. `PRODUCT.md` owns product behavior; `output/tide-review/direction.md` records the selected direction. This visual change leaves existing financial behavior and validation rules intact.

## Colors

The palette combines clear mint, deep navy, quiet green-tinted light surfaces, and cool dark surfaces. The frontmatter records exact source values; each light token has a dark counterpart. Same-valued semantic aliases remain separate because they have different purposes.

### Primary

- **Ovezi Mint** (`primary`): primary actions, including Add expense. Its navy `primaryText` foreground stays consistent across themes.
- **Deep Interactive Green** (`interactive`): readable links, selected controls, and active navigation. It becomes light mint in dark mode.

### Secondary

- **Financial states** (`positive`, `danger`, `warning`, `information`): readable status foregrounds paired with their named surface roles.
- **Supporting accents** (`accentBlue`, `accentViolet`, `accentCoral`, `accentAmber` and their surfaces): available semantic accent pairs in the theme; the standard expense row currently uses the mint surface and interactive foreground.

### Neutral

- **Navy Ink / Pale Ink** (`text`): primary financial values, headings, and body text.
- **Quiet Secondary Ink** (`textSecondary`): metadata, labels, and currency codes.
- **Tide Ground** (`background`): light green-tinted ground or the dark navy ground.
- **Stable Surface** (`surface`, `surfaceRaised`, `backgroundElement`): opaque cards and fields.
- **Soft Mint Surface** (`surfaceSubtle`, `backgroundSelected`): balances and selected controls.
- **Quiet Divider / Control Edge** (`border`, `controlBorder`): hairline separators versus visible input and choice boundaries.
- **Modal Scrim** (`overlay`): mode-specific dimming behind sheets.

**The Readable Mint Rule.** Use primary mint as a fill with navy primaryText. Use interactive for text links and selected icons; do not use bright mint as ordinary text on light surfaces.

**The Meaning Beyond Color Rule.** Pair owing, owed, and settled color treatments with explicit text. Keep currencies separate and show the currency with each amount.

## Typography

**Display and body font:** platform system type. Native text inherits iOS/Android system rendering; the frontmatter font stack is its portable browser approximation, not a bundled font requirement. Web code includes a display-family fallback variable, but native headings do not require a downloaded display face.

Amounts have tabular numerals and calm medium weight. Page headings use `heading`; large balance values use `amount`. Body text differs between iOS (`body-ios`) and Android (`body-android`). `section-title`, `row-title`, `row-amount`, and `label` capture the observed supporting hierarchy. ThemedText also exposes legacy title/subtitle variants; those are not the screen-heading source.

**The Native Type Rule.** Use native system typography for app content and headings. Preserve platform text scaling and tabular numerals for financial values.

## Layout

React Native dimensions are logical points/dp. The frontmatter uses px strings for portable token consumers; do not disable native font scaling to reproduce a browser preview.

AppScreen uses shared `four` spacing for horizontal gutters, a top safe area, a scrollable content area with 12-point gaps, and a minimum 72-point heading region. Main groups and expense rows are open, with minimum 76-point row height, quiet bottom dividers, and 16-point vertical padding. Bounded cards use the card radius; the balance panel uses the balance radius and 22-point padding.

Group and expense rows stack their copy and amounts when width is below 360 points or fontScale exceeds 1.2. MoneyAmount exposes the complete formatted value to accessibility and allows very long amounts to scroll horizontally. Screen content reserves 104 points below tab content on Android and 200 points on iOS/web for the separate task action and navigation. Other screens include the bottom safe area plus 32 points.

Phone layouts are the target. A shared maximum-content-width constant exists, but AppScreen does not enforce a desktop grid or a tablet-specific layout. Do not infer breakpoints beyond the observed row adaptation.

## Elevation & Depth

Tide uses opaque tonal surfaces for financial information and subtle separators for lists. Native navigation owns its platform material. PlatformMaterial uses supported iOS GlassView, then system blur when available and permitted, and otherwise an opaque themed surface. Its reduced-transparency state starts conservatively opaque and tracks accessibility changes.

The Add expense action uses Android elevation 3 and an iOS navy shadow (offset 0/4, opacity 0.1, radius 10). PlatformMaterial's opaque fallback uses elevation 2 and a black shadow (offset 0/6, opacity 0.08, radius 18). These are functional depth cues, not a requirement to shadow every card. The sidecar records browser approximations alongside their native purpose.

**The Stable Money Rule.** Keep financial content on opaque surfaces. Reserve native glass and blur for navigation and appropriate controls, with platform fallbacks.

## Shapes

Controls, cards, balance panels, and sheets use the named radii in the frontmatter. ChoiceChip uses a 24-point radius; the circular search control is 48 points across. Add expense is rounded to 28 points on iOS/web and 16 points on Android. Open list rows retain square, transparent edges. Action sheets round their top corners and remain scrollable within 80% of the available height.

## Components

### Buttons

PrimaryButton uses mint and navy, control corners, a minimum height of 52 points, and the `button` type role. Disabled/loading state lowers opacity to 0.65, blocks interaction, and exposes disabled/busy state; loading replaces the label with an activity indicator. HeaderAction has minimum width and height of 48 points, including the expense Edit action.

AnimatedPressable moves to opacity 0.82 and scale 0.98 over the shared instant duration. Reduced motion disables scaling; the brief opacity change remains. Native controls do not implement desktop hover styling. Sidecar hover and keyboard focus styling are preview affordances, not evidence of new native states.

### Inputs / Fields

FormField has an explicit visible label, matching accessibility label, themed placeholder, opaque surface, control-border outline, control radius, and minimum height of 54 points. Single-line inputs use zero vertical padding and an explicit height that grows with the effective font scale to keep text vertically centered; multiline inputs retain top alignment and vertical padding. Selection uses the interactive role. Native input behavior owns focus and keyboard behavior; no custom native focus glow is implemented.

### Chips

ChoiceChip has a minimum height of 48 points, 24-point corners, and a one-point control border. Selection switches to surfaceSubtle with interactive text and border. Accessibility exposes selected and disabled states, and labels can shrink/wrap within available width.

### Cards / Containers

The balance panel uses surfaceSubtle with the balance radius. The first balance keeps its large value neutral, alongside its explicit owing/owed/settled label and separate currency. Additional currencies remain separate rows. Error cards use dangerSurface and distinguish retrying from normal state; failed loading is not an empty or settled state.

### Rows

Group and expense rows use open surfaces and hairline separators. Names lead, metadata recedes, and amounts remain readable with explicit state labels where relevant. Category icons use mint-tonal backplates and native symbols. Preserve the compact-width and enlarged-text stacking behavior.

### Navigation

NativeTabs owns Home, Groups, Activity, and Profile. It uses interactive selected tint, secondary inactive tint, and visible labels. Android receives the opaque surface and mint-tonal indicator; iOS receives the native system material rather than a drawn glass imitation. Native OS availability governs the exact material. Web uses its own opaque 76-point tab bar.

Add expense is a separate, icon-only mint action with a 24-point plus symbol, a 56-by-56-point touch target, an accessible “Add expense” label, and platform-specific corner shape. It is positioned clear of the bottom navigation and routes to expense creation.

### Accessibility and preview scope

Use visible state labels in addition to color, preserve text scaling, and use the shared 48-point target floor for new controls. Existing components include button roles, selected/disabled/busy state, input labels, heading roles, modal semantics, reduced motion, and reduced-transparency fallbacks. These are observed implementation measures, not proof that all screens pass an accessibility audit. Validate large text, VoiceOver/TalkBack, contrast, focus order, long translations, and native materials on supported devices before release.

The sidecar's self-contained HTML/CSS components are representative browser translations. They cannot reproduce native tabs, platform fonts, haptics, or accessibility APIs exactly. Its navigation sample represents the web fallback, not a native-material acceptance test. Sample names and financial amounts are synthetic.

## Do's and Don'ts

### Do:
- Do select semantic light/dark roles from the shared theme.
- Do use 24-point screen gutters, open rows, and bounded summaries according to their information role.
- Do retain explicit currency and owing/owed/settled labels when presenting money.
- Do preserve the four labelled destinations and keep Add expense a separate task action.
- Do retain scalable text, 48-point minimum targets for new controls, and reduced-motion/transparency behavior.
- Do verify new screens on both native platforms, in dark mode, with enlarged text and assistive technology.

### Don't:
- Don't replace the approved mint and navy identity with a new palette.
- Don't use primary mint as light-theme body text or remove semantic balance labels.
- Don't use translucent financial cards or turn every list row into an elevated card.
- Don't add an Add expense destination to the bottom navigation.
- Don't imply that recording a settlement transfers money.
- Don't treat these source-derived constraints or HTML previews as a completed accessibility or native-rendering audit.
