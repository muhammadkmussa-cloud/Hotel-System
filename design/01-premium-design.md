# Premium visual direction

Status: proposed design specification for C17. Validate on actual tablets and kiosk before approval. [Ingredient behaviour](02-ingredient-experience.md) and [accessibility](04-accessibility.md) are part of this standard.

## Direction: a contemporary restaurant menu

The first screen presents food customers can order immediately. A calm editorial layout, warm neutral surfaces, restrained typography, and accurate photographs create the premium feel. The memorable detail is a carefully composed meal photograph with ingredient portraits around it. Staff screens use the same brand but favour dense, legible operational information.

Avoid promotional homepages, oversized slogans, floating decorative shapes, heavy gradients, excessive gold, stock hospitality imagery, nested cards, and animation that delays ordering. Premium means coherent hierarchy, excellent imagery, accurate totals, and reliable interaction.

## Proposed tokens

| Token | Value | Use |
|---|---|---|
| Canvas | `#F7F4EE` | Warm ivory background |
| Surface | `#FFFFFF` | Menus, drawers, clear order panels |
| Primary ink | `#17241F` | Titles, body text |
| Secondary ink | `#4C5B53` | Secondary copy on light backgrounds |
| Action | `#164A37` | Primary buttons; white text |
| Accent | `#986B32` | Restrained decorative rule/icon; not unverified small text |
| Border | `#D9DFD7` | Quiet separation; not sole control boundary |
| Warning | `#855000` | Pending/problem text with icon |
| Danger | `#A22B2B` | Destructive/error text and icon |

Verify every actual foreground/background pair; listed values do not certify the finished interface. Use spacing steps 4/8/12/16/24/32/48/64 CSS px, control radius 12 px, panels 20 px, circular ingredient portraits. Shadows should define overlays, not cover every tile.

Typeface proposal: self-hosted, properly licensed Source Serif 4 for a small number of menu headings and Source Sans 3 for controls, descriptions, totals, and all staff surfaces. Use tabular numerals for money. Font choice remains replaceable with the hotel's licensed brand fonts. No runtime dependency on a public font CDN.

Body 18 px on table tablets, 20 px on kiosk; labels at least 16 px. Dish titles 24–32 px, meal-detail title 32–44 px depending on space. Prices are prominent without overpowering dish names. Staff dense tables may use 16 px with adequate line height.

## Composition

Table menu: persistent Table/Guest identity at top, category navigation below, 2–3 columns of large meal photography where width permits, and a sticky cart action. Dish tiles have one image, name, short description, visible full price, and availability. Avoid wrapping the entire page in a decorative card.

Meal detail: 60–65% of landscape width for photograph/orbit, 35–40% for title, price, choices, quantity, and add action. At narrow widths, put the photo first, then an ingredient grid/list and sticky footer. Never force the circular layout to overlap text just to retain its shape.

Kiosk portrait: generous top identification, 2-column food grid, visible review action in reachable lower area. Payment entry and primary actions must be usable without reaching the top of a tall screen. No customer keyboard should cover amount, error, or confirmation controls.

Kitchen: compact, high-contrast ticket columns with time, table, guest, preparation state, and prominent exclusions. No decorative food photos that reduce ticket capacity. Cashier: guest balances, payment status, and method separated visually; no ambiguous green button that means both 'payment received' and 'close visit'.

## Motion and feedback

Suggested durations 120–180 ms for selection and 180–240 ms for drawers. Ingredient removal uses cross-out and a short fade, not disappearance. Reduced-motion disables movement while preserving state feedback. Never rotate ingredients continuously, spin the plate, or move touch targets under a finger.

## Premium acceptance

- Real images dominate customer screens without cropping the identifying food.
- Consistent food lighting, plate scale, colour treatment, and ingredient crops.
- No text overlap at 200% zoom or in long translated labels.
- No layout shift as photographs load; errors get designed fallback content.
- All selected/removed/locked/unavailable states are understandable without colour.
- A person can see dish price and permitted changes before adding it.
- Review covers portrait kiosk, landscape/portrait tablet, small fallback screen, kitchen, and cashier.
- Prototype comparison measures completion, mis-taps, and confidence; an attractive orbit does not excuse worse usability.
