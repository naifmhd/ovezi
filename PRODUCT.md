# Ovezi

Ovezi is a mobile expense-sharing app for groups, friends, and personal expenses. The existing product is implemented with Expo / React Native for iOS and Android, with a web fallback.

Users create and manage groups, add shared or personal expenses, inspect balances and activity, and record settlements. Recording a settlement records a payment made elsewhere; the app does not move money.

Existing workflows include multiple payers, participant exclusions, equal/exact/percentage/share splits, multiple currencies with captured conversion rates, drafts, duplicate detection, recurring expenses, receipt attachments, placeholder-member claims, group roles, notifications, and account/security preferences. Preserve these capabilities and their existing authorization and validation rules during visual changes.

Balances in different currencies remain separate. Owed and owing states require readable labels in addition to semantic colors. Loading, failed requests, genuinely empty data, and settled balances remain distinct.

## Confirmed design commitments

The user requested three design templates after reviewing the existing screens, using Liquid Glass and Android conventions. They confirmed: “Keep mint and navy, vary layout and styling.” On 2026-10-01 they selected the Tide direction: “Tide is good.”

Retain Ovezi's name, logo, mint and navy identity. Use platform-native behavior, accessible tap targets, scalable text, light/dark themes, and reduced-motion / reduced-transparency fallbacks where supported. Glass belongs to iOS navigation and controls, while financial content stays readable on stable surfaces.

The three-direction decision package and the screen inventory are in `output/design-templates/`. Demonstration names, amounts, and balances used in review are synthetic fixtures, not commercial claims or live customer data.
