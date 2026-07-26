# Required Secrets

No secret values live in this repo. Set these as environment variables on the
Matomo container (docker compose env / `~/.secrets/.env` on the host). See each
plugin's header comment for exact usage.

## AspendoraIdentity (Wave 5)

| Variable | Purpose | Where to get it | Rotation |
|---|---|---|---|
| `ASPENDORA_GHL_TOKEN` | GoHighLevel Private Integration token (`pit-…`) used by the hourly identity sync (contacts) AND the daily won-opportunity import (Wave 7) | GHL sub-account → Settings → Integrations → Private Integrations. Scopes required: `contacts.readonly`, `contacts.write`, `opportunities.readonly` | Recommended every 90 days (does not auto-expire) |
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

## AspendoraSearchKeywords (Wave 1)

| Variable | Purpose |
|---|---|
| `ASPENDORA_GSC_CLIENT_ID` / `ASPENDORA_GSC_CLIENT_SECRET` / `ASPENDORA_GSC_REFRESH_TOKEN` | Google Search Console OAuth (refresh-token flow) |
| `ASPENDORA_GSC_PROPERTY_MAP` | JSON map of Matomo idSite → GSC property |

## hub.php ingest

| Variable | Purpose |
|---|---|
| `ASPENDORA_REC_KEY` | Shared key for session-recording/heatmap beacons (public-by-design, ships in page source; caps in hub.php bound abuse) |
