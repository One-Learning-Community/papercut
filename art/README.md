# Quoin logo kit

Mark: two pages split by a "cut here" scissor line (a page break). Wordmark: Inter ExtraBold, -4% tracking, outlined to paths so no font is needed.

| Color | Hex | Use |
|---|---|---|
| Ink | `#18181B` | mark and wordmark on light |
| Paper | `#FAFAFA` | mark and wordmark on dark, backgrounds |
| Cut red | `#EF4444` | the cut line only |

## Files

- `quoin-logo.svg` / `quoin-logo-dark.svg` — horizontal lockup
- `quoin-mark.svg` / `quoin-mark-dark.svg` — mark alone (text lines are transparent cutouts)
- `favicon.svg`, `favicon.ico`, `favicon-16/32.png`, `favicon-180.png` (apple-touch) — mark simplified for small sizes
- `quoin-mark-256/512.png`, `quoin-mark-dark-512.png`
- `quoin-avatar-512.png` — GitHub org / Packagist avatar
- `quoin-social.png` — GitHub social preview (Settings → Social preview), 1280×640

## README header with light/dark support

```html
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/quoin-logo-dark.svg">
  <img alt="Quoin" src="art/quoin-logo.svg" width="236">
</picture>
```

Size steps: full mark at 48px and up; one line per page with a heavier cut around 32px; at 16px, plain pages and a solid cut.
