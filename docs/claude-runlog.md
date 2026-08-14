# Claude Run Log

Low-level execution log. High-level phase status: `app-build-progress.md`.

## 2026-07-26 — Wave 5: Identity + GHL sync

**Goal:** deterministic visitor identification + GoHighLevel enrichment/push-back.

- [x] **Recon** — Read repo (hub.php, bundle.js, AspendoraSearchKeywords as the
  plugin template), GHL API docs at `~/code/apis/highlevel-api/endpoints.md`
  (base `https://services.leadconnectorhq.com`, `Version: 2021-07-28` header,
  PIT auth; upsert/get/add-tags documented, search body NOT documented → used
  upsert as lookup).
- [x] **Plugin** — Created `plugins/AspendoraIdentity/`:
  - `plugin.json`, `AspendoraIdentity.php` (install: `aspendora_identity` table,
    PK (idsite, user_id); env helpers for hot-page regexp + hot-score threshold)
  - `GhlClient.php` (curl, no SDK; upsertContactByEmail / getContact / addTags)
  - `Sync.php` (aggregate log_visit by user_id over 365d → upsert identities;
    flag high-intent via log_link_visit_action REGEXP over 30d; score 0–100;
    enrich ≤100 identities/run against GHL, re-check weekly; push
    `website-hot-lead` tag once per identity at score ≥ threshold)
  - `Tasks.php` (hourly), `Commands/SyncIdentities.php`
    (`aspendora-identity:sync`), `API.php` + `Reports/GetKnownVisitors.php`
    (Known Visitors under Visitors menu), `lang/en.json`
- [x] **bundle.js** — Added identity section: `asp_c` URL param → `ghl:<id>`,
  localStorage `asp_uid` persistence, email capture on any form submit
  (capture-phase listener, first valid email input), `setUserId` + single
  `ping` when identity newly learned. Wired into `init()`.
- [x] **Docs** — `docs/secrets-required.md` (GHL PIT token + location id, GSC,
  REC key), `docs/app-build-progress.md` (waves 1–5 + deploy steps).
- [x] **Verify** — `php -l` on all 7 plugin PHP files via `docker run php:8.2-cli`
  (no PHP on host): no syntax errors. `node --check bundle.js`: passes. Both
  JSON files parse. Self-review caught + fixed one bug: hourly re-aggregation
  overwrote GHL-enriched emails with NULL for `ghl:` identities
  (`email = COALESCE(VALUES(email), email)` in Sync.php).

**Next:** await user approval of Wave 5 (phase gate), then deploy steps in
app-build-progress.md; Wave 6 = reverse-IP company reports + alerts.

## 2026-07-26 — Wave 5 deploy (approved)

- [x] Committed Wave 5 (f587e1d), pushed to origin/aspendora.
- [x] Added `ASPENDORA_GHL_*` / `ASPENDORA_IDENTITY_*` env passthrough to
  `~/code/vultr-proxmox/services/aspendora-matomo/docker-compose.prod.yml`,
  scp'd to docker-apps.
- [x] Deployed per service README: rsync src → build `aspendora/matomo:local` →
  `up -d --force-recreate --renew-anon-volumes matomo-web`. Activated
  AspendoraIdentity.
- [x] **Bug found in prod:** first sync failed with mixed-collation error —
  MariaDB 11 creates Matomo tables as `utf8mb4_uca1400_ai_ci`; our table was
  `utf8mb4_general_ci` and the sync joins on user_id. Fix: install() now
  inherits log_visit's collation from information_schema; live table converted
  via `ALTER TABLE … CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci`.
- [x] **Verified end-to-end:** sync clean (only expected "GHL credentials not
  set" warning); identity table has 1 row (existing form-32 userId), score 20;
  `AspendoraIdentity.getKnownVisitors` API returns it over HTTPS.
- **User actions pending (not blocking):**
  1. Generate GHL Private Integration token (Settings → Integrations → Private
     Integrations, scopes contacts.readonly + contacts.write) and add
     `ASPENDORA_GHL_TOKEN` + `ASPENDORA_GHL_LOCATION_ID` to
     docker-apps:/opt/services/aspendora-matomo/.env, then
     `docker compose … up -d matomo-web` to pick up env.
  2. Decorate GHL email-template links with `?asp_c={{contact.id}}`.

## 2026-07-26 — Wave 6: Companies + alerts

- [x] Built `plugins/AspendoraCompanies` (8 files): OrgPrefix VisitDimension
  (log_visit column `aspendora_org_prefix`, tracking-time /24 capture, no I/O),
  Resolver (rDNS + optional IPinfo, ISP/hosting keyword blocklist, 200/run cap),
  Alerts (daily digest email via Piwik\Mail to ASPENDORA_ALERT_EMAIL — hot leads
  from AspendoraIdentity [class_exists-guarded] + non-ISP company visits last
  24h), Tasks (daily), API + GetCompanies report (Visitors → Companies),
  `aspendora-companies:resolve [--send-digest]`, lang. Collation-inherit
  pattern reused from the Wave 5 fix.
- [x] Verified locally: php -l clean on all 8; prefixForIp edge cases
  (private → null, invalid → null, IPv4 → /24, IPv6 → /48) pass in php:8.2-cli;
  `addNoValueOption` + VisitDimension auto-migration confirmed in core.
- [x] Compose: added ASPENDORA_IPINFO_TOKEN / ASPENDORA_ALERT_EMAIL passthrough.
- [x] Deployed: rsync → build → recreate (renew-anon-volumes) → activate.
  **Gotcha learned:** VisitDimension columns are NOT added by plugin:activate —
  required `./console core:update --yes` (added `aspendora_org_prefix` to
  log_visit). Resolver then runs clean; prefixes populate for new visits only.
- [x] Smoke tested: Companies API returns [] (expected pre-traffic), report
  metadata lists both "Known Visitors" and "Companies". Host .env got
  ASPENDORA_ALERT_EMAIL=lacy@aspendora.com.
- [x] IT Glue: appended Wave 5–6 section to "Aspendora Analytics & CRM Stack —
  Matomo + EspoCRM Runbook" (doc 24419246, org Aspendora Technologies LLC).

## 2026-07-26 — Wave 7: Offline conversions, Funnels, A/B testing

- [x] **AspendoraAdExport → 1.1.0**: WonOpportunities importer (GHL
  /opportunities/search, status=won, paginated ≤20 pages; identity match =
  contact email OR ghl:<id> against log_visit.user_id; most recent AdClick
  event ≤ won time within 90d), `aspendora_offline_conversions` table
  (collation-inherit; created via Updates/1.1.0.php since the plugin is
  already installed), sales-export APIs (Google/Microsoft CSV with revenue),
  daily task + `aspendora-adexport:import-won`. PIT needs
  opportunities.readonly added (secrets-required.md updated).
- [x] **AspendoraFunnels**: env-defined (ASPENDORA_FUNNELS JSON) ordered-step
  funnels over PathLoader sequences; Behaviour → Funnels.
- [x] **AspendoraExperiments** + bundle.js initExperiments(): persistent
  variant assignment, html class, Experiment event; report with per-variant
  conversion rate + two-proportion z significance; conversions = goals +
  AspendoraAttribution event categories.
- [x] **Verify (local)**: php -l clean on all three plugins;
  funnel-matcher harness → [3,2,1] as expected; z-test math checked (1.98 for
  10/100 vs 20/100); node --check bundle.js OK.
- [x] Deployed. **Bug found in prod:** Matomo update classes must live in the
  PLUGIN ROOT namespace (`Piwik\Plugins\AspendoraAdExport\Updates_1_1_0`), not
  an `Updates` sub-namespace — core:update threw "class not found". Fixed
  (c54de15), rebuilt, `core:update --yes` then created
  `matomo_aspendora_offline_conversions`.
- [x] Smoke tested over HTTPS: Funnels returns live data (10 visits at step 1,
  0 at contact step — default funnel), Experiments [] (none configured on
  pages yet), sales export [] (GHL token pending), both reports in metadata.

## 2026-07-26 — Wave 8: JS error tracking + AI insights digest

- [x] **AspendoraCrash**: bundle.js initErrors() (onerror + unhandledrejection,
  ≤10/page) → hub.php 'err' branch (2000/day cap) →
  `matomo_aspendora_js_errors`; Behaviour → JS Errors report; daily 60d prune.
- [x] **AspendoraInsights**: StatsCollector (internal Request::processRequest
  across all Aspendora + core reports, WoW), ClaudeClient (claude-opus-5, raw
  curl per repo convention — noted deviation from the claude-api skill's
  SDK preference to keep composer untouched for upstream merges; no thinking
  param [on by default], stop_reason refusal check, `fallbacks: "default"` +
  `server-side-fallback-2026-07-01` beta), Digest (per-site HTML narrative,
  branded white-label email via Piwik\Mail), weekly task +
  `aspendora-insights:send`. claude-api skill loaded before writing per
  its trigger rules.
- [x] Verify (local): php -l clean (hub.php + both plugins), node --check OK.
- [x] Deployed. **Blocker found + fixed in prod:** Piwik Mail falls back to
  PHP mail() and the container has no MTA → "Could not instantiate mail
  function" (this also silently affected the Wave 6 alert digest, which had
  never had content to send). Fix (a5c56b7): new
  AspendoraInsights\Mailer — SMTP2GO HTTP API (POST /v3/email/send, key from
  ASPENDORA_SMTP2GO_API_KEY, sender analytics@aspendora.com overridable via
  ASPENDORA_MAIL_FROM) with Piwik Mail fallback; digest + Wave 6 alerts both
  use it. Host .env got ASPENDORA_ANTHROPIC_KEY (from local ANTHROPIC_API_KEY)
  and ASPENDORA_SMTP2GO_API_KEY (from local SMTP2GO_API_KEY).
- [x] **Smoke tested end-to-end:** synthetic 'err' beacon → hub.php 204 → row
  in matomo_aspendora_js_errors (then removed); JS Errors report in metadata;
  `aspendora-insights:send` generated Claude narratives for both sites and
  DELIVERED the digest email to lacy@aspendora.com via SMTP2GO.

## 2026-07-26 — GHL credentials deployed (user: "you already have ghl creds")

- [x] Found existing PIT in ~/.secrets/.env: `GHL_API_KEY` (pit-…) +
  `GHL_LOCATION_ID` (primary Aspendora sub-account). Validated read-only
  before deploying: GET /opportunities/pipelines → 200 (opportunities scope);
  GET /contacts/<bogus-id> → 400 not 401/403 (contacts scope authorized).
- [x] Deployed to docker-apps host .env as ASPENDORA_GHL_TOKEN /
  ASPENDORA_GHL_LOCATION_ID; recreated matomo-web.
- [x] Verified live: identity sync enriched 1 identity (ghl_contact_id set,
  website-visitor tag pushed; contact has no name in CRM — expected);
  won-opp import clean (0 rows — no won opportunities in the location yet).
- **Remaining user actions:** only `?asp_c={{contact.id}}` decoration in GHL
  email templates + optional ipinfo.io token.

## 2026-08-14 — Fix: broken images across the UI (Morpheus icons never shipped)

**Goal:** the Visits-in-real-time widget (and every report with a browser / OS /
device / country / plugin icon) rendered broken-image placeholders.

- [x] **Diagnosed** — every icon URL 404s in prod:
  `curl https://analytics.aspendora.com/plugins/Morpheus/icons/dist/{browsers/CH,os/WIN,flags/us,devices/desktop,plugins/cookie}.png`
  → 404 ×5. `plugins/Morpheus/icons` is empty both in `/var/www/html` and in
  `/usr/src/matomo` inside `aspendora-matomo-web`.
- [x] **Root cause** — `plugins/Morpheus/icons` is a **git submodule**
  (matomo-org/matomo-icons). Our source tree is never
  `git submodule update --init`ed, so all 18 submodule paths are empty
  directories (`git submodule status` → all `-` prefixed). `deploy/Dockerfile`
  did `rm -rf /usr/src/matomo` and replaced the upstream tree with our
  checkout, so everything the 5.12.0 release tarball ships *from a submodule*
  was dropped: `plugins/Morpheus/icons` (5.3M — all UI icons),
  `plugins/TagManager` (6.6M) and `misc/log-analytics` (192K). The other 15
  submodules are optional plugins the official image doesn't ship either, so
  they were never a regression.
- [x] **Fix** — `deploy/Dockerfile`: `mv /usr/src/matomo /usr/src/matomo-upstream`
  instead of `rm -rf`, then after the source COPY restore any of those three
  bundled paths our checkout left empty from the upstream tree
  (`cp -a`, `chown www-data`), then drop the upstream copy. Idempotent: if a
  path is ever populated in the fork (submodule initialized, or vendored) the
  restore skips it. Base image is `matomo:5-apache` = 5.12.0, identical to the
  fork base — versions must stay in step.
- [x] **Files changed** — `deploy/Dockerfile`, `deploy/README.md`,
  `docs/claude-runlog.md`, `~/code/vultr-proxmox/services/aspendora-matomo/README.md`.
- [x] **Deployed** — rsync src → `docker build -t aspendora/matomo:local` →
  `up -d --force-recreate --renew-anon-volumes matomo-web` (documented flow).
  Verified in the image before recreating the container.
- [x] **Verified live** — pulled `Live.getLastVisitsDetails` (10 visits), walked
  every icon path the API returned (21 distinct: browsers, devices, flags, os,
  plugins) and curled each → **21/21 HTTP 200, 0 broken**. Matomo API 200,
  AspendoraIdentity/AspendoraCompanies APIs still 200, no errors in
  container logs. `plugins/Morpheus/icons/dist` = 11 entries, TagManager 40,
  misc/log-analytics 6.

**Note (not fixed, pre-existing):** `token_auth` is passed in the query string
for API calls, so it lands in the Apache access log. Prefer POSTing `token_auth`
in the request body for anything scripted.

**Next:** none required. TagManager files now ship but the plugin stays
DEACTIVATED (it was never in the DB); activate from Administration → Plugins
only if Tag Manager is actually wanted.

## 2026-08-14 — Wave 9: white-label rebrand (build + deploy)

Phase plan and verification table: `app-build-progress.md`. Execution notes and the
things that bit us:

- [x] **Brand source** — user pointed at `~/code/aspendora-existingwebsite` (the live
  WordPress/Bricks site) rather than the `aspendora-site` Next.js rebuild. Palette taken
  from actual usage frequency in `bricks-work/*.css`: `#2563eb` brand / `#1d4ed8` hover /
  `#0f172a`–`#0f1729` navy ink / slate 50–700 / `#22c55e` success / `#ef4444` error, font
  "Plus Jakarta Sans". Logo master found at 1800x894 in `~/code/controlr/.../logo-dark.png`
  (the `aspendora-site/public/brand` copies are 148px and 447px).
- [x] **Knockout logo** — the wordmark is black on transparent, so it is invisible on the
  navy top bar. Generated with ImageMagick `-fuzz 20% -fill "#F8FAFC" -opaque black`, which
  recolours the wordmark and leaves the crimson arc; verified visually before shipping.
  Favicon composed from the "A" glyph + the arc on navy (the 512px brand "favicon" is the
  whole wordmark squeezed into a square — illegible at 32px).
- [x] **No local PHP or Docker on this host** — the translation generator is run on
  docker-apps against the rsynced source: `docker run --rm -v /opt/services/aspendora-matomo/src:/src
  -w /src php:8.2-cli php plugins/AspendoraWhiteLabel/tools/rebrand-translations.php`, then
  `scp` the generated `lang/en.json` back. Same route used for `php -l`.
- [x] **Bug 1 (generator)** — first pass produced "Aspendora.org" and "Aspendora, formerly
  known as Aspendora". Fixed with a `(?!\.[a-z])` lookahead and a "formerly known as" skip.
- [x] **Bug 2 (generator)** — overrides for our own and vendored plugins (Aspendora*,
  LoginOIDC) are pointless: third-party plugins load *after* this one, so they overwrite the
  override. Those namespaces are now skipped and the two affected strings were rebranded at
  source instead.
- [x] **Bug 3 — the real one.** Overrides had no effect at all in the UI. Cause:
  `Plugin\Manager::getAllPluginsNames()` returns `readPluginsDirectory()` — a `_glob()`, i.e.
  **alphabetical** — and `JsonFileLoader` merges directories with `array_replace_recursive`,
  last wins. "AspendoraWhiteLabel" < "CoreAdminHome", so core reloaded its own strings on top
  of ours. Fixed with `TranslationLoader`, a DI decorator on
  `Piwik\Translation\Loader\LoaderInterface` (registered in the plugin's `config/config.php`)
  that moves this plugin's lang directory to the end of the list. Decorating the *outer*
  loader means the reorder happens before `LoaderCache`, so the merged result is still cached
  and under a cache key that reflects the new order. Verified: General settings went from
  14 "Matomo" to 0.
- [x] **Deploy gotcha (new, worth remembering)** — `--renew-anon-volumes` replaces the webroot
  but `tmp` is a **named** volume, so Matomo's merged CSS/JS assets survive the redeploy. A
  stylesheet-only change with no `plugin.json` version bump does not change the asset cache
  buster and simply does not appear. **Run `./console core:clear-caches` after every deploy**
  (added to the service runbook).
- [x] **Not a regression, investigated as one** — after deactivating ProfessionalServices the
  left menu lost Funnels/Forms/Media/A-B Tests/Heatmaps/Session Recordings/Custom Reports/
  Crashes. Those were ProfessionalServices *promos for Matomo's paid plugins*
  (`shouldShowPromoForPlugin`), not our features. Confirmed via `API.getReportMetadata` that
  all 17 Aspendora reports are still registered under Behaviour/Visitors/Acquisition.
- [x] **TagManager** — correcting the note in the icons-fix entry above: restoring the bundled
  files did NOT leave it deactivated in the UI sense; CorePluginsAdmin adds a permanent
  "Tag Manager" top-menu item pointing at `action=tagManagerTeaser` whenever the files are on
  disk. The plugin itself is genuinely deactivated (`plugin:deactivate` reports "already
  deactivated", it is absent from `config.ini.php`); only the teaser link was showing. The
  link is now hidden by the white-label CSS. To actually use Tag Manager:
  `./console plugin:activate TagManager` and drop the `#topmenu-corepluginsadmin` rule.

**Deploy sequence used (4 rebuild/recreate cycles as issues were found):** rsync → build →
`up -d --force-recreate --renew-anon-volumes matomo-web` → wait for webroot → `plugin:activate
AspendoraTheme` → `plugin:deactivate Tour ProfessionalServices Marketplace Feedback RssWidget`
→ `core:clear-caches`.

**Next:** phase gate — awaiting approval. Open items: PDF report interiors still use Matomo's
`ReportRenderer` colour constants; Tag Manager left off.

## 2026-08-14 — Wave 9b: correct the logo, adopt the brand repo, brand the PDF

**Trigger:** user flagged the logo as wrong and pointed at
`~/code/aspendora-branding/assets/logo/letterhead-logo-full-reverse-sourcefw_.png`, then asked
for the PDF to be branded and set to US Letter.

- [x] **The logo was wrong, and the way it was wrong mattered.** Wave 9 derived a header logo
  with ImageMagick (`-fuzz 20% -fill "#F8FAFC" -opaque black`), producing a white wordmark with
  the crimson arc still in place. `~/code/aspendora-branding/docs/logo-usage.md` forbids exactly
  that: *"Recolor the mark. The gradient is fixed"*, and there is already a proper **all-white
  reverse** lockup at 1800×894. Replaced with straight copies of the real files — no derivation:
  - `images/logo.png` ← `assets/logo/logo.webp` (447×180 full colour, native size, no upscale)
  - `images/logo-header.png` ← the reverse lockup, resized to 120px tall
  - `images/favicon.png` / `favicon-256.png` ← `assets/favicon/mark-icon-{32,512}-navy.png`
    (byte-identical for the 32px). My hand-made "A + arc" favicon also broke the lockup in a way
    the brand doesn't sanction — only *the peak* may stand alone, and only in square icon slots.
- [x] **Assimilated the brand repo** into `docs/branding.md` per the repo standard (source note +
  only what this fork depends on). Repointed the theme's palette at the canonical tokens: dark
  mode now uses the real navy family (`navy #0c1a36` page / `navy-deep #142850` card /
  `navy-hero #0f172a` borders) instead of the three values I had invented.
- [x] **Documented deviation:** brand minimum on-screen width for the full lockup is 120px;
  Matomo's top bar can't give it that much height with clear space, so it renders smaller
  (34px tall). Written down in `docs/branding.md` rather than left as a silent compromise.
- [x] **PDF reports — US Letter.** First attempt edited `PDF_PAGE_FORMAT` in
  `plugins/ScheduledReports/config/tcpdf_config.php` and had **no effect**: the bundled TCPDF
  declares `__construct($orientation='P', $unit='mm', $format='A4', …)` with *hardcoded*
  defaults and never reads that constant. Reverted that edit (left a note in place so nobody
  repeats it) and passed the format at construction in `core/ReportRenderer/Pdf.php` instead.
  Verified by reading `/MediaBox` out of the generated PDF: was `595.276 x 841.89` (A4), now
  `612 x 792` (US Letter). `MAX_ROW_COUNT` 28 → 26 for the 17.6mm shorter page.
- [x] **PDF/report palette** — `core/ReportRenderer.php` constants set to the brand's *document*
  spec (`~/code/aspendora-branding/templates/document.css`): burgundy `#660000` titles, ink body,
  ink table header with white bold uppercase text, slate-200 borders, slate-50 zebra. Core edit
  because Pdf.php reads the constants directly in its constructor — no event, no DI seam.
  Upstream values recorded in the comment for future merge conflicts. Also added
  `GRAPH_SERIES_COLORS` and passed it into `getStaticGraph`, since ImageGraph carries its own
  palette and never consults the UI theme.
- [x] **Print vs web split honoured:** `EmailStyles` seeds from those same constants, so the HTML
  email report inherits the table styling (deliberate), but
  `AspendoraTheme::configureEmailStyle` puts the title colour back to web ink — burgundy is a
  print-only convention per the brand's color doc.
- [x] **Verified** by generating a real report: created a temporary scheduled report
  (`period=never`, `emailMe=0` — no mail sent), rendered before/after PDFs to PNG and compared,
  then deleted the report (`ScheduledReports.getReports` → `[]`). Cover page carries the
  full-colour lockup; Country table (22 rows) fits Letter with no overflow; chart line is brand
  blue.

**Still open:** PDF body font is DejaVu Sans. TCPDF needs a converted TTF and the brand folder
ships woff2 only — fixing it means adding a Plus Jakarta Sans TTF to the repo and running
`TCPDF_FONTS::addTTFfont`. Noted in `docs/branding.md`.
