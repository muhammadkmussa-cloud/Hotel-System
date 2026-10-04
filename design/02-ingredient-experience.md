# Meal and ingredient interaction

Basis: C15–C17; D09–D10. Source recipe details belong to staff-maintained catalogue data, not image recognition.

## Layout policy

For 1–6 top-level ingredients, place larger portraits around a generous meal image. For 7–12, use a wider ring or two staggered bands only when labels and touch areas fit. For more than 12, or any constrained viewport, use a labelled ingredient tray/grid under or beside the meal. These counts are prototype defaults, not universal geometry rules.

Every displayed interactive ingredient must retain at least a 48 × 48 CSS-pixel hit target, visible name, and spacing. Prefer 64–88 px portraits when room permits. Determine available layout from container size and measured labels, not only total count. Full ingredients remain discoverable even when the orbit shows only the most recognisable components.

Compound ingredients have a 'Contains…' detail view. 'House sauce' cannot hide egg or other recipe components. The chef determines whether the entire sauce is removable; customers cannot remove an already-mixed component independently.

## States

| State | Visual | Behaviour |
|---|---|---|
| Included/removable | Full image, name, selected indicator | Tap switches to removed |
| Removed | Image still present, cross-out, 'Removed' text | Tap restores |
| Fixed ingredient | Image, lock indicator, 'Part of the recipe' | Opens explanation; no toggle |
| Unavailable extra | Image, 'Unavailable' text | Cannot select; explanation available |
| Allergy review | Clear help notice | Requests staff review; never infers safety |

Always show a textual modification summary next to Add to order. State is per cart line: two burgers with different removals remain distinct. 'Reset changes' restores the published recipe. Back/cancel does not mutate the catalogue.

## Price behaviour

Proposed default: removals do not discount the meal; paid additions show their price before selection. Show updated total including quantity. Source of truth is server pricing. If a price changed while browsing, explain the difference and require another confirmation rather than silently accepting a higher price.

## Component inputs and outputs

Input: published meal version, image variants, ingredients with composition and allowed action, modifier constraints, base/extra prices, availability, and locale. Output: ingredient IDs and explicit selected actions for that line, quantity, and optional staff-review request. Do not send a customer-calculated authoritative total.

Accessible output: semantic checkbox/switch behaviour for removal, readable labels such as 'Onion, included; activate to remove', polite announced summary, and full keyboard order. DOM order is stable irrespective of ring positioning.

## Preview and validation

Catalogue editor must preview 3, 8, 12, and 20 ingredient layouts, long names, missing pictures, fixed ingredients, sauces with nested contents, and portrait/landscape modes. Reject empty names and ingredients referencing themselves through compound recipes. Prevent circular ingredient composition.

Chef review is required before publication of a recipe/removability change. Store the approved version with submitted orders so later edits cannot rewrite historical instructions.
