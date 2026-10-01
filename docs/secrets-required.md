# Required Secrets

No secret values live in this repo. Set these as environment variables on the
Matomo container (docker compose env / `~/.secrets/.env` on the host). See each
plugin's header comment for exact usage.

## AspendoraIdentity (Wave 5)

| Variable | Purpose | Where to get it | Rotation |
|---|---|---|---|
| `ASPENDORA_GHL_TOKEN` | GoHighLevel Private Integration token (`pit-…`) used by the hourly identity sync (contacts) AND the daily won-opportunity import (Wave 7) | Deployed 2026-07-26 from `GHL_API_KEY` in `~/.secrets/.env` (primary Aspendora sub-account; pairs with `GHL_LOCATION_ID`). To regenerate: GHL sub-account → Settings → Integrations → Private Integrations. Scopes: `contacts.readonly`, `contacts.write`, `opportunities.readonly` (all verified live) | Recommended every 90 days (does not auto-expire) |
| `ASPENDORA_GHL_LOCATION_ID` | GHL sub-account (location) id the contacts belong to | GHL sub-account → Settings → Business Profile (or the URL when inside the sub-account) | n/a (not secret, but env-configured) |

Optional (non-secret) tuning:

- `ASPENDORA_IDENTITY_HOT_PAGES` — MySQL REGEXP matched against page URLs to flag
  high-intent visitors (default `pricing|contact|quote|demo|compliance`).
- `ASPENDORA_IDENTITY_HOT_SCORE` — lead score threshold for the
  `website-hot-lead` tag push (default `50`).

Validate: `./console aspendora-identity:sync` on the container — logs a warning
if credentials are missing, errors if the token/scopes are wrong.

## AspendoraCompanies (Wave 6)

| Variable | Purpose | Where to get it | Rotation |
|---|---|---|---|
| `ASPENDORA_IPINFO_TOKEN` | Optional ipinfo.io token for network→organization lookups (falls back to reverse DNS only) | https://ipinfo.io/signup (free tier: 50k req/mo) | Rotate on suspicion; low sensitivity |

Non-secret: `ASPENDORA_ALERT_EMAIL` — recipient of the daily hot-activity digest
(skipped when unset).

## AspendoraInsights (Wave 8)

| Variable | Purpose | Where to get it | Rotation |
|---|---|---|---|
| `ASPENDORA_ANTHROPIC_KEY` | Claude API key for the weekly AI-written analytics digest (model claude-opus-5, ~1 small call per site per week) | console.anthropic.com → API Keys | Rotate on suspicion |

Non-secret: `ASPENDORA_INSIGHTS_EMAIL` — digest recipient (falls back to
`ASPENDORA_ALERT_EMAIL`).

## AspendoraSearchKeywords (Wave 1)

| Variable | Purpose |
|---|---|
| `ASPENDORA_GSC_CLIENT_ID` / `ASPENDORA_GSC_CLIENT_SECRET` / `ASPENDORA_GSC_REFRESH_TOKEN` | Google Search Console OAuth (refresh-token flow) |
| `ASPENDORA_GSC_PROPERTY_MAP` | JSON map of Matomo idSite → GSC property |
| `ASPENDORA_BING_API_KEY` | Bing Webmaster Tools API key (v1.1.0). Account-level; same value as `BING_WEBMASTER_API_KEY` in `~/.secrets/.env` (Keeper is the source of truth). Get/rotate: bing.com/webmasters → Settings → API access. Validate: `./console aspendora-bing:import` logs `stored N rows`; a bad key logs `HTTP 400 ... NotAuthorized` |
| `ASPENDORA_BING_SITE_MAP` | Non-secret. JSON map of Matomo idSite → verified Bing siteUrl (must match exactly, trailing slash included), e.g. `{"1":"https://www.aspendora.com/","2":"https://aspendoracompliance.com/"}` |
| `ASPENDORA_AUDIT_SITE_MAP` | Non-secret (AspendoraSiteAudit). JSON map idSite → crawl start URL, e.g. `{"1":"https://www.aspendora.com/","2":"https://aspendoracompliance.com/"}`. Unmapped sites are not crawled |
| `ASPENDORA_AUDIT_MAX_PAGES` | Non-secret. Crawl cap per site (default 2000) |
| `ASPENDORA_AUDIT_ORPHAN_OK` | Non-secret. JSON map idSite → array of path regexes for pages intentionally unlinked (campaign landing pages); matching orphans are not reported |

## hub.php ingest

| Variable | Purpose |
|---|---|
| `ASPENDORA_REC_KEY` | Shared key for session-recording/heatmap beacons (public-by-design, ships in page source; caps in hub.php bound abuse) |

> **2026-09-27:** GHL decommissioned. `ASPENDORA_GHL_TOKEN` and `ASPENDORA_GHL_LOCATION_ID` were removed from the deploy `.env` and are no longer required; the GHL half of AspendoraIdentity is disabled.
