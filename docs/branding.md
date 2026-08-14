# Branding — what this repo uses and where it comes from

**Source: `~/code/aspendora-branding` (v1.0.0, extracted 2026-08-14) — the single source of
truth for the Aspendora Technologies brand. Imported: 2026-08-14.**

Only the parts this fork actually depends on are reproduced here. For anything else — voice,
messaging, business facts, web component patterns — read the source folder; don't re-derive it.
If the live site's palette or type changes, that folder is re-pulled from Bricks first and this
file follows.

## Colours in use

From `tokens/tokens.json`. Set in `plugins/AspendoraTheme/AspendoraTheme.php`, never hard-coded
anywhere else in this repo.

| Token | Hex | Where it lands in Matomo |
|---|---|---|
| `brand.blue` | `#2563eb` | links, active menu item, primary buttons, chart series 1, focus ring |
| `brand.blue-hover` | `#1d4ed8` | hover, chart series 2 |
| `brand.blue-light` | `#60a5fa` | brand + links in dark mode, chart series 3 |
| `brand.navy-hero` | `#0f172a` | top bar and login nav (light mode), dark-mode borders |
| `brand.navy` | `#0c1a36` | top bar (dark mode), dark-mode page background |
| `brand.navy-deep` | `#142850` | dark-mode widget/card surface |
| `neutral.ink` | `#0f1729` | body text, widget titles, PDF/email table header fill |
| `neutral.slate-600/500/400` | `#475569` `#64748b` `#94a3b8` | secondary text, muted labels, placeholders |
| `neutral.slate-300/200/100/50` | `#cbd5e1` `#e2e8f0` `#f1f5f9` `#f8fafc` | borders, hairlines, page background, zebra rows |
| `identity.crimson` | `#b71a28` | **logo only.** Never a UI colour — brand rule 1. Interface red means danger. |

## Type

`font.family.base` = `"Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto,
sans-serif`. Self-hosted in `plugins/AspendoraTheme/fonts/` (latin subset, variable weight axis
200–800, ~27KB, SIL OFL 1.1) — no external font CDN, matching the first-party/cookieless posture
of the tracker itself.

**Gap:** PDF reports still render in DejaVu Sans. TCPDF needs a converted TTF, and the brand
folder ships woff2 only. Fixing this means adding a Plus Jakarta Sans TTF to the repo and running
it through `TCPDF_FONTS::addTTFfont`.

## Logo files

Copied into `plugins/AspendoraTheme/images/` — copied, not derived. The gradient in the peak mark
is fixed; **recolouring it, faking a reverse with a CSS filter, or rebuilding the lockup are all
explicitly off-limits.**

| Theme file | Brand source | Used for |
|---|---|---|
| `logo.png` | `assets/logo/logo.webp` (447×180, full colour) | PDF report cover, any light background |
| `logo-header.png` | `assets/logo/letterhead-logo-full-reverse-sourcefw_.png` (all-white reverse) | top bar, login nav, report email header — all navy surfaces |
| `favicon.png`, `favicon-256.png` | `assets/favicon/mark-icon-{32,512}-navy.png` | browser tab. The isolated peak is the only element allowed to stand alone, and only in square icon slots |

**Known deviation:** the brand minimum on-screen width for the full lockup is 120px. Matomo's top
bar cannot give the lockup that much height while keeping its clear space, so it renders smaller
in app chrome than the brand standard. Accepted for internal chrome; do not repeat it in
customer-facing output.

## PDF / document reports

The brand keeps a separate **print** convention from the web one — see
`templates/document.css`. This fork follows it in `core/ReportRenderer.php` (a documented core
edit; there is no event or DI seam on those constants):

- **US Letter**, not A4 — `plugins/ScheduledReports/config/tcpdf_config.php`. Letter is 17.6mm
  shorter than A4, which is why `Pdf::MAX_ROW_COUNT` drops from 28 to 26.
- **Headings in burgundy `#660000`** — the established Aspendora document look, deliberately
  *not* the web blue. Body copy in ink `#0f1729`.
- **Table header** filled ink `#0f1729` with white uppercase bold text; **cell borders**
  slate-200; **zebra rows** slate-50.
- **Graph series** in brand blue and its supporting blues.

Because `EmailStyles` seeds itself from those same constants, the HTML email report inherits the
table styling — deliberate, it should be the same report either way. The *title* colour is put
back to web ink for email in `AspendoraTheme::configureEmailStyle`, since burgundy is print-only.

## The five brand rules

Reproduced verbatim because they are short and constrain everything above:

1. The logo red is not a UI color. Interface red means danger.
2. One primary CTA per page, in `#2563eb`, repeated hero / mid / end.
3. Three steps in the plan. Never four.
4. Stakes and Transformation ship together.
5. NAP strings match exactly, everywhere.

## Open gaps (upstream of this repo — not ours to fix here)

- **No vector logo master exists anywhere.** Every file is raster. Called out in the brand folder
  as the highest-value branding fix available.
- The live site favicon is the full lockup, illegible at 32px; the `mark-icon-*` files this repo
  uses are the better stopgap.
- Print logo red (`#ab0534`) and web logo red (`#b71a28`) don't match — inherited drift.
