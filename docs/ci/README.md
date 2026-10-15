# GitHub Actions workflows (ready to activate)

These two workflows are complete. They live here because the Arena GitHub connection that pushed this branch
does not have permission to write files inside `.github/workflows/`. GitHub requires the special "workflows"
permission for that.

| File | What it does |
|---|---|
| `platform-ci.yml` | On every push/PR touching `platform/`: PHP 8.4, `composer install`/`update`, Pest tests (blocking), Pint (blocking), Larastan (report only in P0), and the Vite build (uploads `public/build` as an artifact). |
| `deploy-hostinger.yml` | Manual "Run workflow" button. Builds assets, uploads `public/build` over SSH, then on the server: DB backup → `git checkout` → `composer install --no-dev` → `migrate --force` → `optimize`. Needs the `HOSTINGER_*` secrets (docs/06 §6.1). |

## How to activate (pick one)

**A. GitHub website (2 minutes, no tools needed)**
1. Open the repository on github.com → **Add file → Create new file**.
2. Name it `.github/workflows/platform-ci.yml` and paste the content of `docs/ci/platform-ci.yml`. Commit.
3. Repeat for `.github/workflows/deploy-hostinger.yml`.

**B. Command line (on your computer)**
```bash
mkdir -p .github/workflows
cp docs/ci/platform-ci.yml docs/ci/deploy-hostinger.yml .github/workflows/
git add .github/workflows && git commit -m "ci: activate workflows" && git push
```

**C. Let the agent do it:** reconnect GitHub in Arena with the *workflows* permission, then ask the agent to
activate CI.

The first CI run prints the resolved `composer.lock` (gzip + base64 between `WOODEX_LOCK_BEGIN/END`). Commit it
afterwards so Hostinger installs exact, tested versions with low memory use.
