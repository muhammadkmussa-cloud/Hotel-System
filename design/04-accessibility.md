# Accessibility, device fit, and localisation

Proposed baseline: WCAG 2.2 AA for applicable web criteria, plus 48 px preferred touch targets for customer interaction. This document is a build target, not a compliance certificate. [W3C target-size reference](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum).

## Required behaviours

- All customer tasks work by touch and keyboard; visible focus never hidden by sticky bars.
- Normal text contrast at least 4.5:1; qualifying large text and meaningful UI boundaries meet their applicable 3:1 criteria. Test rendered combinations, including disabled explanations.
- Names, symbols, and text accompany colour states. Ingredient images always have visible labels.
- Text can enlarge to 200%; small layouts reflow without losing actions. A labelled list is equivalent to the orbit.
- Modal focus is contained appropriately and restored on close. Validation names the field and correction.
- Announce cart changes and payment state without reading sensitive phone information aloud automatically.
- No time limit for reading ordinary meal details. Kiosk inactivity timeout has a warning and extension action; staff sessions have separate security settings.
- Honour reduced motion. No flashing error banners or automatically rotating galleries.
- Tablet supports both orientations. Kiosk physical reach and seated use are checked with the selected enclosure; web layout alone cannot solve mounting height.

## Language proposal D11

Prepare message keys and locale-aware currency/time formatting from the beginning. English is initial authoring language; Kiswahili needs a competent human review before enabling publicly. Hotel menu names/translations come from the hotel. Never silently machine-translate allergens or preparation constraints as if professionally approved.

Currency is KES. Customer text may say 'KSh 1,200'; internal contracts use minor units. Use Africa/Nairobi for business presentation and UTC instants in storage. Business-day cutoff is a configurable operational setting, not an assumption that midnight closes every visit.

## Test matrix

Proposed viewports: 360 × 800 fallback, 768 × 1024 tablet portrait, 1024 × 768 tablet landscape, 1080 × 1920 kiosk portrait, and 1440 × 900 cashier. Also test the actual hardware's CSS viewport and zoom, not just native pixel resolution.

Manual reviews cover 200% zoom, keyboard flow, screen-reader meal selection and payment state, glare, wet/large fingers, long names, translation expansion, and disabled network. Automated checks supplement these reviews but cannot prove a complete accessible experience.
