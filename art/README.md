# Quoin logo kit

Mark: a block of text lines set against a red wedge, the quoin that locks type into the page. Each line ends at the wedge's angle, so the block closes square. Wordmark: Inter ExtraBold, -4% tracking, outlined to paths so no font is needed.

| Color | Hex | Use |
|---|---|---|
| Ink | `#18181B` | mark and wordmark on light |
| Paper | `#FAFAFA` | mark and wordmark on dark, backgrounds |
| Wedge red | `#EF4444` | the wedge only |

## Files

- `quoin-logo.svg` / `quoin-logo-dark.svg`: horizontal lockup
- `quoin-mark.svg` / `quoin-mark-dark.svg`: mark alone
- `favicon.svg`, `favicon.ico`, `favicon-16/32.png`, `favicon-180.png` (apple-touch): mark simplified for small sizes
- `quoin-mark-256/512.png`, `quoin-mark-dark-512.png`
- `quoin-avatar-512.png`: GitHub org / Packagist avatar
- `quoin-social.png`: GitHub social preview (Settings → Social preview), 1280×640

## README header with light/dark support

```html
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="art/quoin-logo-dark.svg">
  <img alt="Quoin" src="art/quoin-logo.svg" width="236">
</picture>
```

Size steps: five lines at 48px and up; three heavier lines in the favicons (16 and 32px).
