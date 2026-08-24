# Dashboard Asset Family Carousel Design

## Objective

Keep every asset-family metric on one horizontal row, fit at least seven cards on a wide desktop, and provide clear horizontal navigation when more cards exist.

## Visual Direction

Use a restrained KAI operational-dashboard style. Cards use a neutral white surface, crisp one-pixel border, small corner radius, and almost no shadow. The Excel/KAI asset color remains identifiable through a top accent strip and compact code badge instead of a large saturated header block. Avoid gradients, glass effects, decorative icons, oversized pills, and excessive rounding.

Each card contains:

1. A colored top accent strip.
2. A compact asset code badge.
3. The asset-family name, clamped to two lines.
4. Two aligned rows for Reliability and Availability.

The information hierarchy prioritizes the code and metric values while keeping long family names readable without increasing card height.

## Horizontal Layout

The card track never wraps. It uses native horizontal overflow and scroll snapping:

- Wide desktop: seven cards visible within the section.
- Tablet: approximately four cards visible.
- Mobile: approximately one-and-a-half cards visible to signal horizontal continuation.
- More cards remain on the same row and are reached through scrolling.

Use native scrolling rather than a carousel dependency. Arrow buttons scroll by one visible viewport group and update their disabled state at the start and end of the track.

## Interaction and Accessibility

- Touch users can swipe horizontally.
- Mouse and trackpad scrolling remain native.
- Previous and next buttons have explicit Indonesian accessible labels.
- Buttons provide visible focus states and meet a minimum 44-pixel target.
- Disabled buttons clearly indicate the beginning or end of the track.
- The track is keyboard-focusable and supports horizontal keyboard scrolling through native browser behavior.
- Reduced-motion preference disables smooth scrolling.

Arrow controls appear only when the content overflows. They remain visually subordinate to the section title and latest-import badge.

## Responsive Behavior

Card width is controlled by CSS custom properties at responsive breakpoints. The layout changes card width only; DOM order and reading order never change. Long names are clamped, missing metric values continue showing `Belum ada data`, and an empty group list continues showing the existing empty state.

## Implementation Scope

Modify only the dashboard family-metrics surface and its focused JavaScript tests:

- `resources/js/pages/dashboard/Dashboard.vue`
- `tests/js/Dashboard.test.js`

Preserve all existing uncommitted behavior in both files, including the no-invented-family-cards empty state.

## Verification

1. Component tests cover overflow controls, scrolling direction, disabled boundary states, and existing family-card behavior.
2. Vitest passes for the dashboard component.
3. The production frontend build passes.
4. Desktop and mobile screenshots confirm a single row, correct visible density, readable cards, and functional arrows.
5. The Impeccable mechanical detector reports no unexplained layout or accessibility findings.

## Out of Scope

- Changing backend reliability data.
- Changing asset-family colors or abbreviations.
- Adding a third-party carousel dependency.
- Redesigning other dashboard sections.
