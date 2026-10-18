# Mustang Design System

Mustang is a CRM for car dealers, and it is used all day on a sales floor, on phones and on desktop. The design system is built on **shadcn/ui** (Radix primitives and Tailwind CSS v4). The look is **clean, light, modern SaaS**, with a matching **dark theme**.

- Tokens: [`design/tokens.css`](design/tokens.css), which holds both the light and dark themes.
- Requirements baseline: [Mustang Requirements Baseline](https://claude.ai/code/artifact/451f9e95-18fd-45ad-977f-b364ba233a9e).

## Principles

1. **The next action is the loudest thing on screen.** Each screen has one primary button. The "contact now" signal (`hot`) is the only warm accent used for emphasis.
2. **Dense, but calm.** Reps scan lists of customers, conversations and vehicles. Use white space, quiet borders and muted secondary text, and keep color for meaning.
3. **Phone first for reps, desktop first for managers.** Salesperson flows must work one-handed on a phone. Manager views can assume a wide screen and degrade to a single column.
4. **Never color alone.** Every stage, status and channel carries a label or an icon as well as its color.
5. **Compliance is visible.** Consent state appears before anything is sent, never after.

## Themes

Both themes use the same token names. Components use only the semantic tokens and never raw hex values, so switching themes needs no component changes.

- The theme follows the user's choice (Light, Dark or System), which is stored per user. System follows `prefers-color-scheme`.
- Dark mode is applied with the `.dark` class on `<html>`, which is shadcn's convention.
- Every text-on-background pair below passes **WCAG AA (4.5:1)**. Form control borders (`input`) meet the 3:1 non-text minimum in both themes.

### Core

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `background` | `#ffffff` | `#0b1220` | Page and main surfaces |
| `canvas` | `#f8fafc` | `#0b1220` | App background behind cards |
| `foreground` | `#0f172a` | `#e2e8f0` | Body text |
| `card` | `#ffffff` | `#111a2e` | Cards, panels, popovers |
| `muted` | `#f1f5f9` | `#1e293b` | Subtle fills, table header, hover |
| `muted-foreground` | `#475569` | `#94a3b8` | Secondary text, labels, timestamps |
| `primary` | `#1d4ed8` | `#60a5fa` | Primary buttons, links, selection |
| `primary-foreground` | `#ffffff` | `#0b1220` | Text on primary |
| `accent` | `#eff6ff` | `#172554` | Selected row, active nav item |
| `destructive` | `#dc2626` | `#dc2626` | Delete and irreversible actions |
| `border` | `#e2e8f0` | `#1e293b` | Dividers and card edges |
| `input` | `#64748b` | `#64748b` | Form control borders |
| `ring` | `#2563eb` | `#60a5fa` | Focus ring |
| `sidebar` | `#0f172a` | `#070d18` | Desktop navigation rail, which is dark in both themes |

### Status and priority

| Token | Light (text / surface) | Dark (text / surface) | Use |
| --- | --- | --- | --- |
| `success` | `#15803d` / `#f0fdf4` | `#4ade80` / `#052e16` | Saved, sent, consent granted |
| `warning` | `#b45309` / `#fffbeb` | `#fbbf24` / `#451a03` | Needs attention soon, consent missing |
| `danger` | `#b91c1c` / `#fef2f2` | `#f87171` / `#450a0a` | Overdue, failed, opted out |
| `info` | `#1d4ed8` / `#eff6ff` | `#93c5fd` / `#172554` | Neutral notices |
| `hot` | `#c2410c` / `#fff7ed` | `#fb923c` / `#431407` | "Contact now" priority, unanswered customer |

### Deal stages

Stages appear as a `StageBadge`: the stage name in the stage color on its surface tint.

| Stage | Light (text / surface) | Dark (text / surface) |
| --- | --- | --- |
| New | `#475569` / `#f1f5f9` | `#cbd5e1` / `#1e293b` |
| Engaged | `#1d4ed8` / `#eff6ff` | `#93c5fd` / `#172554` |
| Appointment | `#6d28d9` / `#f5f3ff` | `#c4b5fd` / `#2e1065` |
| Visit | `#0f766e` / `#f0fdfa` | `#5eead4` / `#042f2e` |
| Proposal | `#b45309` / `#fffbeb` | `#fcd34d` / `#451a03` |
| Sold | `#15803d` / `#f0fdf4` | `#86efac` / `#052e16` |
| Delivered | `#166534` / `#dcfce7` | `#bbf7d0` / `#14532d` |
| Lost | `#4b5563` / `#f3f4f6` | `#9ca3af` / `#1f2937` |

### Channels

Channels are always shown with their icon (Lucide `phone`, `message-square`, `mail`, `video`).

| Channel | Light | Dark |
| --- | --- | --- |
| Call | `#15803d` | `#4ade80` |
| Text | `#1d4ed8` | `#93c5fd` |
| Email | `#4338ca` | `#a5b4fc` |
| Video | `#be123c` | `#fda4af` |

Charts use `chart-1` to `chart-5` in that order. A single-series chart uses `chart-1`.

## Typography

**Inter** for all UI text, with tabular numerals (`.tabular`) for tables, KPIs, prices and mileage. **JetBrains Mono** is used only for IDs such as VINs and stock numbers.

| Style | Size / line height | Weight | Use |
| --- | --- | --- | --- |
| Display | 30 / 36 | 600 | KPI numbers |
| H1 | 24 / 32 | 600 | Page title |
| H2 | 20 / 28 | 600 | Section and card title |
| H3 | 16 / 24 | 600 | Sub-section, list item name |
| Body | 14 / 20 | 400 | Default text on desktop |
| Body large | 16 / 24 | 400 | Default text on phones; all inputs (prevents iOS zoom) |
| Small | 12 / 16 | 500 | Labels, badges, timestamps |

Use sentence case everywhere, including buttons and headings. Never use all caps except in short badges.

## Spacing, radius, elevation

- **Spacing:** a 4 px grid. Use 4, 8, 12, 16, 24, 32 and 48 (Tailwind `1`, `2`, `3`, `4`, `6`, `8`, `12`). Card padding is 16 on phones and 24 on desktop.
- **Radius:** `--radius` is 8 px. Inputs and buttons use `rounded-md` (6), cards `rounded-lg` (8), dialogs and sheets `rounded-xl` (12), and avatars and pills are fully rounded.
- **Elevation:** cards use `shadow-card` or a border, not both. Menus, popovers, dialogs and sheets use `shadow-overlay`. Nothing else casts a shadow.
- **Touch targets:** at least 44 × 44 px on phones, including icon buttons.

## Layout

| Breakpoint | Width | Layout |
| --- | --- | --- |
| Phone | < 640 | Bottom tab bar (Today, Inbox, Customers, Inventory, More); full-screen detail views |
| Tablet | 640–1023 | Collapsible sidebar; list and detail stacked |
| Desktop | ≥ 1024 | Dark sidebar rail and top bar with quick search; list, detail and context panel side by side |

Content is capped at 1440 px wide. Tables scroll horizontally inside their card, never the page.

## Components

Use shadcn/ui components as installed, themed only through tokens. Don't fork a primitive to change its color.

**From shadcn/ui:** Button, Input, Textarea, Select, Combobox, Checkbox, Switch, Radio group, Label, Form, Badge, Avatar, Card, Table (with TanStack Table), Tabs, Dialog, Alert dialog, Sheet, Drawer (phone), Dropdown menu, Command (quick search, ⌘K), Popover, Tooltip, Calendar and Date picker, Toast (Sonner), Skeleton, Separator, Scroll area.

**Button variants:** `default` (primary, one per screen), `secondary`, `outline`, `ghost`, `destructive` and `link`. Sizes are `sm` (32), `default` (36) and `lg` (44, the phone default).

**Mustang components**, built from the above and mapped to the MVP:

| Component | What it is | Used in |
| --- | --- | --- |
| `PriorityItem` | A customer row with the reason to act now ("asked about a Camry 47 min ago, trade question unanswered"), a `hot` marker and one-tap channel actions | "Contact now" list (FR-03, FR-18) |
| `StageBadge` | A deal stage name with its stage colors and days in stage | Deals, customer panel, lists |
| `ChannelActions` | Call, text, email and video icon buttons, which are disabled with a reason when consent is missing | Lists, customer panel |
| `ConsentIndicator` | Per-channel consent state (granted, missing, opted out) with source and date in a tooltip | Composer, customer panel (FR-11 to FR-13) |
| `MessageBubble` | Inbound bubbles in `muted` on the left, outbound in `primary` on the right, with channel and time below | Inbox thread (FR-17) |
| `Composer` | Channel selector, consent check, template picker and send | Inbox thread |
| `CustomerPanel` | Contact details, assigned team, open deal, vehicle of interest and trade-in | Inbox, customer view (CRM-01) |
| `Timeline` | Every touch with a customer in one stream, grouped by day | Customer view (FR-04) |
| `KpiTile` | Label, a Display-size number, and the change versus the previous period with an arrow and text | KPI dashboard (FR-02) |
| `VehicleCard` | Photo, year, make and model, trim, stock number, mileage and price | Inventory (FR-21) |
| `InterventionItem` | A deal at risk, the reason, the assigned rep and an "Assign to me" action | Manager queue (FR-08) |
| `EmptyState` | An icon, one sentence and one action | Every list |

## States and feedback

- **Loading:** skeletons shaped like the content, never a full-page spinner.
- **Empty:** say what will appear here and offer the one action that fills it.
- **Errors:** inline, next to the field or action that failed, saying what happened and what to do. Use toasts only for background events.
- **Destructive actions:** an Alert dialog that names the object ("Delete Khari's deal?"), with the destructive button on the right.
- **Times:** relative times ("5 min ago") for under 24 hours and dates after that. The full timestamp appears in a tooltip.

## Accessibility

- AA contrast in both themes. Re-check any new color pair before adding it.
- A visible focus ring (`ring`, 2 px, 2 px offset) on everything focusable.
- Full keyboard use on desktop. Quick search opens with `⌘K` / `Ctrl K`.
- Motion is limited to 150–200 ms ease-out for overlays and none for data updates, and it respects `prefers-reduced-motion`.
- Icon-only buttons have an `aria-label` and a tooltip.

## Voice

Plain, short and direct, written for a salesperson between customers. Lead with the action ("Call Sarah now"), say why in a few words, and avoid jargon and exclamation marks. Copy is written so that Spanish can be added later: no text in images and no string concatenation.
