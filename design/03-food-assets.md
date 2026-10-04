# Food and ingredient asset specification

Status: production asset requirements. No food photographs are included in this documentation-only delivery. Use [asset manifest](../templates/asset-manifest.md) for handover.

## Photography brief

Use the hotel's actual dishes, portions, plating, and ingredients. Soft directional light, accurate white balance, uncluttered backgrounds, and consistent viewing angle. Preserve appetising texture without misleading portions or colour. Do not depict ingredients absent from the recipe. Keep original files and usage-rights records.

Dish catalogue images: 4:3 landscape framing, plate fully visible with crop-safe margins; master at least 2000 px wide as a proposed production target. Meal-detail hero: square or 4:3 master with enough neutral background for ingredient portraits around the image. Ingredient portraits: consistent square crop, recognisable single subject, optionally transparent background, at least 512 × 512 master. These are asset targets, not a hardware guarantee.

Do not bake labels into photographs. Store name/alt text as editable content. Photograph ambiguous ingredients in a recognisable form and accompany them with plain text.

## Delivery variants

Generate responsive widths approximately 320/640/960/1440 for dishes and 96/192/384 for ingredient portraits. AVIF/WebP where supported, with JPEG/PNG fallback as appropriate. Preserve transparency for ingredient cutouts. Proposed budgets: card variants below 150 KB, hero below 350 KB, ingredient thumbnail below 35 KB; adjust when visual fidelity needs it and measure total screen load.

Dimensions/aspect ratio are included in metadata to reserve layout space. Do not send full-size originals to every tablet. Lazy-load offscreen cards and preload only the primary visible meal image. Serve published asset versions from the DirectAdmin domain. Browser caching can retain previously loaded public images, but uncached images require connectivity and offline menus must be marked stale.

## Upload and publishing pipeline

Authenticate menu editors; validate file signature as well as claimed type, file size, pixel dimensions, and decode limits. Accept raster formats only in the first release. Re-encode server-side, strip unnecessary metadata, and reject executable/vector uploads. Preserve originals outside the public web root with least-privilege access. Media records identify owner, checksum, rights, variants, alt text, crop focal point, and publication usage.

A missing optional photo uses an honest, elegant text/plate placeholder. A broken essential hero fails catalogue readiness for featured launch dishes. Never replace a missing ingredient with a random picture.

## Content handover

For each meal deliver: exact name, description, displayed price, actual recipe, photo, permitted removals, extras, availability, preparation station, and chef approval. A photographer's photo approval and a chef's recipe approval are separate checks.

Demo imagery must be marked 'Demonstration menu' and kept separate from production. No production customer should infer that a generated or borrowed dish image represents the hotel's actual serving unless the hotel has explicitly validated that representation and its rights.
