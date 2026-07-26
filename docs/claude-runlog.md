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
