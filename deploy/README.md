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

## Customizing
- **Prefer plugins over core edits** — Matomo's plugin API is the supported extension path and
  survives upstream merges. Create under `plugins/<AspendoraThing>/`, commit on `aspendora`.
- Marketplace plugin installs write to the container layer and are LOST on redeploy — pin any
  needed third-party plugin by vendoring it into `plugins/` in this repo instead.
- Core changes are possible (that's why the fork exists) but keep them minimal and documented.
- Pull upstream releases: `git fetch upstream tag <X.Y.Z> && git merge <X.Y.Z>` then rebuild.
  Keep the deployed image version in sync with the DB (Matomo runs schema migrations on upgrade).
