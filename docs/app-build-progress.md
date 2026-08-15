# Aspendora Matomo — Build Progress

High-level phased plan and status. Low-level step log lives in `claude-runlog.md`.

## Completed waves (pre-existing, for context)

- **Wave 1** — GSC keywords, Web Vitals, Activity Log; vendored LoginOIDC (commit 22b8ab5)
- **Wave 2** — Form Analytics (GF funnel), Users Flow (commit 840bc52)
- **Wave 3** — Cohorts, Attribution (6 models), Custom Reports (commit 13fe8b7)
- **Wave 4** — Media Analytics, Roll-Up, Ad Export, White Label, Session Recording + heatmaps; hub.php ingest + site bundle.js (commit a85d5e2)

## Wave 5 — Identity + GHL sync (IN PROGRESS, this session 2026-07-26)

**Goal:** turn anonymous visitors into known visitors — deterministic identification,
GoHighLevel CRM enrichment, lead scoring, hot-lead push-back.

**Deliverables:**

- [x] `bundle.js` identity capture: email from any submitted form (Gravity Forms
      included), `?asp_c=<GHL contact id>` from decorated email-campaign links,
      localStorage persistence, `setUserId` + one ping when identity is newly learned.
- [x] `plugins/AspendoraIdentity` — identity table (`aspendora_identity`), hourly
      sync task, `GhlClient` (upsert/get/add-tags, documented endpoints only),
      lead scoring (0–100: visits + actions + recency + high-intent pages),
      Known Visitors report under Visitors, console command `aspendora-identity:sync`.
- [x] Verification: PHP lint (docker php:8.2-cli, all 7 files clean), `node --check
      bundle.js` clean, JSON parses; one self-review bug fixed (see runlog).

**Decisions / assumptions:**

- GHL lookup uses `POST /contacts/upsert` (dedupes by email) instead of
  `/contacts/search` — the search body shape is undocumented in
  `~/code/apis/highlevel-api/endpoints.md`, and a web form submitter belongs in
  the CRM anyway (`source: Website (Matomo Identity)`, tagged `website-visitor`).
- Email-campaign links must be decorated in GHL templates with
  `?asp_c={{contact.id}}` (opaque contact id — raw emails stay out of URLs/logs).
- Score threshold ≥ 50 (env-tunable) adds the `website-hot-lead` tag once per
  identity; build a GHL workflow on that tag for SMS/email alerts.
- Sync is a full 365-day recompute each run — idempotent, fine at current volume.

**Deploy steps (when approved):**

1. Set `ASPENDORA_GHL_TOKEN` + `ASPENDORA_GHL_LOCATION_ID` on the container
   (see `secrets-required.md`).
2. `./console plugin:activate AspendoraIdentity` (creates the table).
3. Redeploy `bundle.js` to the hub domain.
4. Add `?asp_c={{contact.id}}` to links in GHL email templates.
5. Smoke test: submit a GF form, then `./console aspendora-identity:sync` and
   check the Known Visitors report.

## Wave 6 — Company identification + alerts (2026-07-26)

**Goal:** identify companies visiting the sites (reverse-IP, probabilistic) and
surface hot activity daily.

**Deliverables:**

- [x] `plugins/AspendoraCompanies` — `OrgPrefix` VisitDimension captures /24
      (IPv6 /48) network prefix at tracking time (stored IPs are anonymized;
      pure string math, no tracker I/O; private ranges skipped). Nightly
      resolver: rDNS + optional IPinfo → org, ISP/hosting keyword filter.
      Companies report under Visitors. Daily hot-activity email digest
      (hot leads from AspendoraIdentity + new company visits) to
      `ASPENDORA_ALERT_EMAIL`. Console: `aspendora-companies:resolve
      [--send-digest]`.
- [x] Verification: PHP lint clean (8 files), prefix derivation edge-cases
      tested (private/invalid → null, IPv4 /24, IPv6 /48), deployed + smoke
      tested (see runlog).

**Notes:** company identification is probabilistic — it names the network's
organization, not a person. Only /24 prefixes are stored, coarser than a full
IP. ISP/hosting/cloud networks are flagged and excluded from reports.

## Wave 7 — Offline conversions, Funnels, A/B testing (2026-07-26)

**Deliverables:**

- [x] **AspendoraAdExport 1.1.0** — daily import of won GHL opportunities
      (`GET /opportunities/search`, snake_case `location_id`, needs the
      `opportunities.readonly` scope on the PIT), matched to the identity's
      most recent ad click id within 90 days; stored in
      `aspendora_offline_conversions`; new `getGoogleAdsSalesExport` /
      `getMicrosoftAdsSalesExport` APIs emit upload-ready CSV with real
      revenue. Console: `aspendora-adexport:import-won`. Table added via
      `Updates/1.1.0.php` (runs with core:update).
- [x] **AspendoraFunnels** — ordered multi-step URL funnels over per-visit
      pageview sequences (reuses UsersFlow PathLoader); definitions in
      `ASPENDORA_FUNNELS` env JSON (default Contact funnel);
      Behaviour → Funnels: reached / step % / overall % / drop-off.
- [x] **AspendoraExperiments + bundle.js** — persistent client-side variant
      assignment (`window.__asp.exp`, localStorage, html class
      `asp-exp-<name>-<variant>`, Experiment event); Behaviour → A/B Tests:
      visits/conversions/rate per variant + two-proportion z-test significance.
- [x] Verification: lint clean, funnel matcher + z-test behavioral tests pass,
      deployed + smoke tested (see runlog).

## Wave 8 — JS error tracking + AI insights digest (2026-07-26)

**Deliverables:**

- [x] **AspendoraCrash** — bundle.js captures window.onerror + unhandledrejection
      (max 10/page), hub.php 'err' branch stores them (2000/day cap),
      Behaviour → JS Errors groups by message+source, daily 60-day retention prune.
- [x] **AspendoraInsights** — weekly task gathers a WoW stats bundle per site
      (traffic, pages, referrers, GSC keywords, known visitors, companies,
      funnels, JS errors) via internal APIs, Claude (claude-opus-5, raw curl by
      repo convention, server-side refusal fallback enabled) writes a
      plain-language narrative ("What happened / Leads & opportunities / Do this
      next"), emailed as a branded, client-presentable HTML digest — the
      white-label report polish deliverable. Console:
      `aspendora-insights:send`. Requires `ASPENDORA_ANTHROPIC_KEY`.
- [x] Verification: lint clean; deployed + smoke tested (see runlog).

## Roadmap complete

Waves 5–8 delivered. Future candidates (not committed): rank tracking, AI-bot
crawl analytics, per-client scheduled PDF reports, GA4 import.

## Stack reconciliation (2026-07-27)

Parallel sessions had diverged: Waves 5/7 treated GHL as the CRM of record while the
EspoCRM program built its own site tracker. Reconciled per the actual stack decisions
(see `~/code/aspendora-existingwebsite/docs/stack-map.md`, mirrored to IT Glue doc 24419246):

- **EspoCRM is the CRM of record.** AspendoraIdentity 1.1.0 syncs identified visitors to
  Espo via the CRM's keyed `AspBridgeIngest` entry point (feeds Espo's own TrackingService —
  its engagement fields are readOnly over REST by design, so REST writes were not an option).
  GHL enrichment/tags remain env-gated for the migration window only.
- **AdExport** imports won opportunities from BOTH: Espo (stage=Closed Won, keys `espo:<id>`)
  and GHL (legacy). Same offline-conversion table and exports.
- Matomo `bundle.js` remains THE site tracker; Espo's AspTrackJs is dormant by decision.
- Matomo's `lead_score` stays internal (Known Visitors report + GHL legacy tag); Espo's own
  scoring/lifecycle react to the bridged data — no duplicated scoring.

## Wave 9 — Aspendora white-label rebrand (2026-08-14)

**Goal:** the instance reads as an Aspendora product, not a recoloured Matomo. User decisions
recorded up front: full white-label **plus** theme; product name **"Aspendora Analytics"**;
Matomo attribution **removed from the UI but kept in the source, LEGALNOTICE and the
About/System Check pages** (Matomo is GPLv3 — rebranding is permitted; stripping the source
notices would not be). Brand reference: `~/code/aspendora-existingwebsite` (the live site) —
brand blue `#2563eb` / hover `#1d4ed8` on a slate scale, navy `#0f172a` chrome, Plus Jakarta Sans.

**Deliverables:**

- [x] **AspendoraTheme** (new, `"theme": true`) — the look:
  - `Theme.configureThemeVariables` sets the full palette as `[light, dark]` pairs (Matomo 5
    resolves the theme once per mode). Both modes verified present in the compiled CSS.
  - Plus Jakarta Sans self-hosted (latin subset, variable weight, 27KB, SIL OFL) — no external
    font CDN, matching the cookieless/first-party posture of the tracker.
  - Brand assets: `images/logo.png` (full-colour wordmark, large/PDF placements),
    `images/logo-header.png` (knocked out to near-white — every surface it lands on, top bar,
    login nav and report email header, is painted with `colorHeaderBackground` = navy),
    `images/favicon.png` + `favicon-256.png` ("A" + arc mark on navy). Core's `CustomLogo`
    picks the logos up automatically from the active theme; favicons have no such fallback, so
    `_favicon.twig` is overridden.
  - Template overrides for the places the wordmark is hardcoded rather than translated:
    `layout.twig` (4-line `extends` + `pageTitle` block — deliberately not a copy, so upstream
    merges stay cheap), `@CoreHome/_logo.twig`, `_favicon.twig`, `_htmlEmailHeader.twig`,
    `_htmlEmailFooter.twig`, `@Login/loginLayout.twig`, `@ScheduledReports/unsubscribe.twig`.
    The last two extend `@Morpheus/layout.twig` **by name** in core, walking past the theme's
    own layout override — they now extend the unqualified `layout.twig` instead.
  - `Email.configureEmailStyle` sets `brandNameLong`.
- [x] **AspendoraWhiteLabel 2.0.0** — the naming:
  - `tools/rebrand-translations.php` generates `lang/en.json` from the whole English catalogue
    (335 strings / 32 namespaces). Re-runnable after an upstream merge — that's what makes the
    rebrand survive one. Skips admin/diagnostic namespaces (Installation, CoreUpdater,
    Diagnostics, CorePluginsAdmin) and the deactivated promo plugins, per the attribution
    decision; skips legal-entity and other-product strings ("… GmbH", "Matomo Cloud",
    "formerly known as"); protects printf placeholders and lowercase URLs/filenames.
  - `TranslationLoader` + `config/config.php` — **required**, not incidental: Matomo registers
    plugin lang directories in `_glob()` (alphabetical) order and merges with
    `array_replace_recursive`, so "AspendoraWhiteLabel" losing to "CoreAdminHome" silently
    reverted every override. A DI decorator moves this plugin's directory to the end of the list.
  - CSS also hides CorePluginsAdmin's permanent "Tag Manager" top-menu item, which links to
    `action=tagManagerTeaser` — an ad for a plugin that isn't enabled here.
- [x] **Deactivated** (DB state, not code): Marketplace, ProfessionalServices, Tour, Feedback,
  RssWidget. **Note:** the top-level Funnels / Forms / Media / A/B Tests / Heatmaps / Session
  Recordings / Custom Reports / Crashes menu entries that disappeared were
  ProfessionalServices **upsell ads for Matomo's paid plugins**, not features. The Aspendora
  equivalents are unchanged, under Behaviour and Visitors (verified via
  `API.getReportMetadata`: 90 reports, all 17 Aspendora ones present).
- [x] **Verification:** `php -l` clean on all new PHP; login page, dashboard, General settings,
  Anonymize data all render 0 occurrences of "Matomo" (19 and 11 "Aspendora" on the two
  settings pages) with branded titles, logo and favicon; Manage Plugins still says Matomo 85
  times **by design**; light+dark palettes both compiled; font and all four image assets serve
  200; no PHP errors in the container log; Aspendora plugin APIs still 200.

**PHASE COMPLETE — awaiting approval to proceed.**

Deliberately left for a follow-up decision: PDF/scheduled-report body styling still uses
Matomo's `ReportRenderer` colour constants (the email header/footer are branded, the PDF
interior is not), and Tag Manager remains deactivated — see the runlog.

### Wave 9b — logo correction + branded PDF (2026-08-14)

Follow-up within the same phase, after the user identified `~/code/aspendora-branding` as the
brand source of truth and asked for the PDF.

- [x] **Logo corrected.** Wave 9's header logo was an ImageMagick derivation (white wordmark,
  crimson arc retained) — the brand explicitly forbids recolouring the mark, and a proper
  all-white reverse lockup already existed. All four image assets are now straight copies of
  brand files, not derivations. Favicon is the sanctioned peak-only square mark.
- [x] **`docs/branding.md`** added: the brand repo assimilated per the repo standard, covering
  the tokens, logo files, PDF/document spec and the deviations this fork knowingly takes.
  Theme palette repointed at canonical tokens (dark mode now uses the real navy family).
- [x] **PDF reports are US Letter** — set at TCPDF construction in `core/ReportRenderer/Pdf.php`,
  *not* via `PDF_PAGE_FORMAT` (the bundled TCPDF hardcodes `$format='A4'` in its constructor
  signature and never reads the constant). Verified via `/MediaBox`: `612 x 792`.
  `MAX_ROW_COUNT` 28 → 26 for the shorter page.
- [x] **PDF/report palette branded** to the document spec — burgundy titles, ink table header
  with white bold uppercase text, slate borders and zebra, brand-blue graph series. HTML email
  shares the table styling; its title colour is put back to web ink, since burgundy is
  print-only.
- [x] **Verified on a real generated report** (temporary `period=never` report, no mail sent,
  deleted afterwards): before/after PDFs rendered and compared page by page.

**PHASE COMPLETE — awaiting approval to proceed.** Remaining known gap: PDF body font is still
DejaVu Sans; embedding Plus Jakarta Sans needs a TTF added to the repo.

### Wave 9c/9d — vector logo + PDF typeface (2026-08-14, approved to proceed)

- [x] **Vector logo master adopted.** The brand repo gained a full SVG set mid-phase, closing the
  "no vector master" gap. `images/logo.svg` = `aspendora-logo-dark.svg`; SVG-first branch restored
  in `_logo.twig`; email raster regenerated from the same SVG with the brand's `render-png.py`.
- [x] **Logo at the brand header standard** — 48px tall / 119px wide, which is simultaneously the
  48px header standard and the 120px minimum width. The deviation recorded in Wave 9b is gone.
  Needed two-ID selectors to beat core's `#root #logo img { max-height: 32px }`, which loses
  silently. Raising the top bar to 72px was tried and backed out (Morpheus's `.nav-wrapper`
  isn't flex).
- [x] **PDF reports in Plus Jakarta Sans** — TTFs committed, converted for TCPDF at image build
  time, `DEFAULT_REPORT_FONT_FAMILY` repointed. Verified via `/BaseFont` in a generated report.

**All Wave 9 open items are closed.** Verified end state: US Letter PDFs in the brand typeface
with burgundy headings, ink table headers and brand-blue charts; vector logo at brand size in the
UI, on the login page and in report emails; no "Matomo" in any client-facing surface.

**PHASE COMPLETE — awaiting approval to proceed.**
