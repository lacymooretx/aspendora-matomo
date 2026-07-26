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

## Planned next waves (approved roadmap, not started)

- **Wave 7** — Offline ad conversions (GHL won → gclid upload), Funnels, A/B testing
- **Wave 8** — AI insights digest, JS error tracking, client-facing report polish
