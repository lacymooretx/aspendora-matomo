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
