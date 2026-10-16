# Expo HAS CHANGED

Read the exact versioned docs at https://docs.expo.dev/versions/v57.0.0/ before writing any code.


# Standing UI quality requirements

Keep Ovezi polished and consistent whenever adding or changing UI. Read `../DESIGN.md` and reuse the Tide theme and existing components before introducing new styles. The latest user-approved interaction patterns below take precedence over older examples in design documentation.

- Give each screen one clear primary task. Use mint filled buttons for that task; use text actions or quiet navigation rows for secondary tasks. Do not render unrelated selections, navigation, and actions as identical pill buttons.
- Home prioritizes group balances and group expenses before lending. Keep four navigation destinations: Home, Groups, Lending, Profile.
- Use the shared floating task action for tab-level creation: icon-only plus buttons for Add expense in Groups and Record a loan in Lending, with descriptive accessibility labels. Keep Add person secondary; a person can be added while recording a loan. Keep task actions separate from navigation and reserve scroll space above them.
- Use BackButton in the leading/top-left navigation position on detail screens (or AppScreen backFallback). Use the platform back icon with an accessible Back label, a 48-point target and a safe fallback destination. Never place Back as a trailing page action or use text glyphs for arrows. Forms use Cancel for dismissal.
- Present expense and loan creation as native form sheets on iOS and modals on Android, with a compact title, Cancel and a reachable save action. Keep person selection within loan entry; preserve drafts when dismissed. Use FormScreen for this form shell rather than a full-page detail header.
- Expense entry keeps amount, description, date, category, payer and split summaries easy to reach. Date remains visible; recurrence stays under More details. Use focused payer, split and category editors rather than exposing every option on the main form.
- Reuse BrandHeader, AppScreen, Typography, Spacing, Radius and semantic theme colors. Reuse PrimaryButton, FormField, the shared TextInput, SearchField, NavigationRow and existing sheets instead of creating near-duplicates. Fix a shared component when the issue is shared.
- Preserve native system typography, text scaling, 48-point minimum touch targets, safe areas, keyboard avoidance, reduced-motion support, and readable light/dark states. Inputs must keep placeholder, caret and entered text aligned. Long labels and amounts must remain usable on narrow screens.
- Before finishing a UI change, inspect the affected screens with realistic content, empty/loading/error states as applicable, light/dark themes and a narrow phone viewport. Check large text and native rendering when relevant. Use synthetic data for financial writes; preserve real drafts and records.
- Run TypeScript/lint and the relevant existing interaction checks for changed flows. Visual inspection is required for layout changes; passing code checks alone does not establish polish. Report any unverified platform or state honestly.
- Keep financial calculations, authorization, currencies, draft recovery and validation intact during visual work. Do not widen a polish task into a new visual identity or unrelated features.
