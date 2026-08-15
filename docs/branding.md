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

**PDF reports** use the same typeface. TCPDF can't read woff2 (or a TTF directly — it needs a
converted `.php`/`.z`/`.ctg.z` trio in its own fonts dir), so
`plugins/AspendoraTheme/fonts/PlusJakartaSans-{Regular,Bold}.ttf` are committed alongside the
webfont and converted at **image build time** by a step in `deploy/Dockerfile`. That produces the
font keys `plusjakartasans` / `plusjakartasansb`, which is what
`ReportRenderer::DEFAULT_REPORT_FONT_FAMILY` points at; `Pdf.php` asks for style `B` on table
headers, which is why both weights are needed.

Two things to know if you touch that build step: the out path **must** end in a slash (TCPDF
concatenates it straight onto the font name, so without it the files land next to the directory),
and `Pdf::setLocale()` only falls back to this font for latin locales — CJK, Arabic and Indic
reports keep their own bundled fonts, which is correct.

Fonts are SIL OFL 1.1 — `fonts/LICENSE-PlusJakartaSans.txt`.

## Logo files

Copied into `plugins/AspendoraTheme/images/` — copied or rendered with the brand's own
`assets/logo/source/render-png.py`, never hand-derived. The gradient in the peak mark is fixed;
**recolouring it, faking a reverse with a CSS filter, or rebuilding the lockup are all explicitly
off-limits.** The SVGs in `assets/logo/svg/` are the master; everything else is an export.

| Theme file | Brand source | Used for |
|---|---|---|
| `logo.svg` | `assets/logo/svg/aspendora-logo-dark.svg` (white wordmark, gradient mark) | top bar and login nav — the sanctioned file for navy grounds. Matomo prefers the SVG when the theme ships one |
| `logo.png` | `assets/logo/png/aspendora-logo-894.png` (full colour) | PDF report cover, any light background |
| `logo-header.png` | the same `aspendora-logo-dark.svg`, rendered to 894px via `render-png.py` | report email header — HTML email can't render SVG |
| `favicon.png`, `favicon-256.png` | `assets/favicon/mark-icon-{32,512}-navy.png` | browser tab. The isolated peak is the only element allowed to stand alone, and only in square icon slots |

**Sizing:** 48px tall in the top bar and on the login page — the brand's header standard, which at
the 2.4845:1 lockup ratio lands the width on ~119px, i.e. exactly the 120px minimum below which
*technologies* stops resolving. Both rules are satisfied at that one size; it isn't a number to
nudge. Matomo's 64px bar leaves 8px of clear space rather than the 25%-of-height (12px) the brand
asks for, but nothing encroaches on it. Raising the bar to 72px was tried and rejected —
`.nav-wrapper` isn't a flex container in Morpheus and the top-menu items collapse onto the logo.

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
- **Plus Jakarta Sans** throughout, converted for TCPDF at build time (see Type above).

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

- Print logo red (`#ab0534` / `#aa0c31` in the Illustrator masters) and web logo red (`#b71a28`)
  don't match — inherited drift. The SVG set follows the web. Old printed stock won't match new
  work. `assets/logo/source/red-options/` holds candidate resolutions; until one is picked, this
  repo follows the web red, which is what the SVG masters use.
- The dark end of the mark's gradient also differs between print and web (neutral near-black vs
  dark burgundy). Same resolution.
- *technologies* tracking is forked between the Illustrator masters and the web logo — the SVGs
  match the web, which is what customers have seen since 2022.

The "no vector master" gap this file originally recorded was **closed on 2026-08-14** — the brand
folder now ships a full SVG set built from the Illustrator master, and this repo uses it.
