# Papercut logo kit

Mark: two pages split by a "cut here" scissor line (a page break). Wordmark: Inter ExtraBold, -4% tracking, outlined to paths so no font is needed.

| Color | Hex | Use |
|---|---|---|
| Ink | `#18181B` | mark and wordmark on light |
| Paper | `#FAFAFA` | mark and wordmark on dark, backgrounds |
| Cut red | `#EF4444` | the cut line only |

## Files

- `papercut-logo.svg` / `papercut-logo-dark.svg` — horizontal lockup
- `papercut-mark.svg` / `papercut-mark-dark.svg` — mark alone (text lines are transparent cutouts)
- `favicon.svg`, `favicon.ico`, `favicon-16/32.png`, `favicon-180.png` (apple-touch) — mark simplified for small sizes
- `papercut-mark-256/512.png`, `papercut-mark-dark-512.png`
- `papercut-avatar-512.png` — GitHub org / Packagist avatar
- `papercut-social.png` — GitHub social preview (Settings → Social preview), 1280×640

## README header with light/dark support

```html
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/papercut-logo-dark.svg">
  <img alt="Papercut" src="art/papercut-logo.svg" width="320">
</picture>
```

Size steps: full mark at 48px and up; one line per page with a heavier cut around 32px; at 16px, plain pages and a solid cut.
