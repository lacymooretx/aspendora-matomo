# Aspendora Matomo — deploy (Matomo fork)

Self-hosted analytics for aspendora.com properties. Fork of Matomo (`upstream` = matomo-org/matomo
— NOT `matomo/matomo`, that's a squatter repo), branch `aspendora` based on stable **5.12.0**.
Built as a **custom image from source** so our changes ship. Runs at https://analytics.aspendora.com.

## What this is
- `deploy/Dockerfile` — multi-stage: composer install over our source, overlaid onto the official
  matomo:5-apache runtime. Code is baked into the image at /var/www/html; only config/tmp/misc
  are volumes (see compose in `~/code/vultr-proxmox/services/aspendora-matomo/`).
- Deployed on the docker-apps VM at `/opt/services/aspendora-matomo/` (rsync source to `src/`,
  `docker build -f src/deploy/Dockerfile -t aspendora/matomo:local src/`).

## Submodules — why the build restores three paths from the base image
Matomo keeps several directories as **git submodules**, and this checkout never initializes them
(`git submodule status` → all 18 paths uninitialized/empty). Because our source *replaces*
`/usr/src/matomo`, anything the release tarball ships from a submodule would be dropped. Three of
them are actually bundled by `matomo:5-apache`, so the Dockerfile keeps the upstream tree aside and
copies them back into any path our checkout left empty:

| path | what breaks without it |
|---|---|
| `plugins/Morpheus/icons` | **every** browser/OS/device/country/plugin icon in the UI 404s → broken images in the visitor log and most reports (this bit us 2026-08-14) |
| `plugins/TagManager` | Tag Manager absent (files only — activation is still manual) |
| `misc/log-analytics` | server-log import script absent |

The restore is skip-if-populated, so initializing or vendoring any of these in the fork just takes
over. Keep the `FROM matomo:5-apache` tag on the same Matomo version as the fork base (5.12.0) —
that's what makes borrowing these assets safe.

## Customizing
- **Prefer plugins over core edits** — Matomo's plugin API is the supported extension path and
  survives upstream merges. Create under `plugins/<AspendoraThing>/`, commit on `aspendora`.
- Marketplace plugin installs write to the container layer and are LOST on redeploy — pin any
  needed third-party plugin by vendoring it into `plugins/` in this repo instead.
- Core changes are possible (that's why the fork exists) but keep them minimal and documented.
- Pull upstream releases: `git fetch upstream tag <X.Y.Z> && git merge <X.Y.Z>` then rebuild.
  Keep the deployed image version in sync with the DB (Matomo runs schema migrations on upgrade).
