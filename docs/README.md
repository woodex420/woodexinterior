# Documentation

| File | What it covers |
| --- | --- |
| [`01-QA-REPORT.md`](01-QA-REPORT.md) | Every bug found in the v1 site and Woodex OS, severity, evidence, fix, and the issues still open. |
| [`02-GAP-REPORT.md`](02-GAP-REPORT.md) | Repository-by-repository inventory, what Woodex OS now covers, and what is still missing from the Website group, the Content group and the API. |
| [`03-BACKEND-API.md`](03-BACKEND-API.md) | Architecture, data model, auth/roles, full API reference, status codes, admin routes, security controls, limitations. |
| [`04-COMPLETION-PLAN.md`](04-COMPLETION-PLAN.md) | Phased plan with the remaining tasks, complex fixes that need decisions, and the launch checklist. |
| [`qa/`](qa/) | Machine-generated crawl artefacts: `v1-site.json`/`.md` and `os-export.json`/`.md`, plus the summary tables. |

**Re-generate the QA artefacts**

```bash
cd woodex-os
npm run qa:site -- --base http://localhost:4321 --label dynamic \
  --json ../docs/qa/os-dynamic.json --md ../docs/qa/os-dynamic.md
npm run export
npm run qa:site -- --dir dist --label export \
  --json ../docs/qa/os-export.json --md ../docs/qa/os-export.md
npm run qa:site -- --dir ../woodex --label v1 \
  --json ../docs/qa/v1-site.json --md ../docs/qa/v1-site.md
```

See [`../woodex-os/README.md`](../woodex-os/README.md) for application setup.
