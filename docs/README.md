# Documentation index

**Start with current status and revision49 below. Earlier audits remain historical evidence, not proof a new request is implemented.**

| Order | Document | What it is |
|---|---|---|
| 1 | [`../PROJECT_STATUS.md`](../PROJECT_STATUS.md) | What the system is today, what works, what is blocked, and the next slices |
| 2 | [`audit/49_WEBSITE_OS_FOUR_PHASE_MASTER_PLAN_2026-10-29.md`](audit/49_WEBSITE_OS_FOUR_PHASE_MASTER_PLAN_2026-10-29.md) | Current Website-first four-phase plan; decision pending |
| 3 | [`audit/49_WEBSITE_PAGE_QA_2026-10-29.md`](audit/49_WEBSITE_PAGE_QA_2026-10-29.md) | Seven-page native baseline, v2.1 source gaps, screenshots and verification limits |
| 4 | [`audit/31_DEEP_QA_2026-10-21.md`](audit/31_DEEP_QA_2026-10-21.md) | The 21 Oct deep QA: findings, evidence, fixed items, ordered improvement plan |
| 5 | [`audit/16_MASTER_TODO.md`](audit/16_MASTER_TODO.md) | Append-only build log — what was done in each step and how it was verified |
| 6 | [`audit/32_MASTER_PLAN_2026-10-26.md`](audit/32_MASTER_PLAN_2026-10-26.md) | The forward plan (26 Oct): the gap matrix per module, what is taken from Gauzy / mcp-gsc / awesome-llm-apps, and slices I–N |

## Rules of authority

1. **Status and findings:** `PROJECT_STATUS.md` + the newest applicable dated `docs/audit/` report. If an older document disagrees, the newer one wins.
2. **Owner decisions:** `07-DECISIONS.md` (D-…). A decision only changes by a newer dated entry.
3. **Product requirements:** `02-MASTER-PRD.md` and `01-MASTER-PLAN.md` — requirements, not status. A requirement listed there is not proof that it ships.
4. **Build log:** `audit/16_MASTER_TODO.md` is append-only. Never rewrite past steps; add a new step.
5. Documents dated before 21 Oct 2026 describe earlier states of the system (port 8080, older test counts, modules that have since changed). They are kept for traceability, **not** as current truth.

## Current reference set

| Document | Use |
|---|---|
| [`09-WEBSITE-FRONTEND-PLAN.md`](09-WEBSITE-FRONTEND-PLAN.md) | The 142-URL map, page types and redirects |
| [`07-DECISIONS.md`](07-DECISIONS.md) | Owner answers and technical decisions |
| [`06-DEPLOYMENT-HOSTINGER.md`](06-DEPLOYMENT-HOSTINGER.md) | Server layout, cron, webhooks, go-live |
| [`INTEGRATIONS-MCP-PLAN.md`](INTEGRATIONS-MCP-PLAN.md) | Connectors, OAuth, MCP server and keys |
| [`GOOGLE-OAUTH-SETUP.md`](GOOGLE-OAUTH-SETUP.md) | Google sign-in and Workspace consent |
| [`TELEGRAM-SECURITY-REPAIR.md`](TELEGRAM-SECURITY-REPAIR.md) | Telegram webhook hardening |

## Historical (kept for traceability, do not follow as instructions)

`00-DEEP-ANALYSIS`, `08-V21-IMPORT-RUNBOOK`, `10-WEBSITE-LAUNCH-AUDIT`, `11-FRONTEND-V2-PLAN`, `12-FRONTEND-V2-MASTER-PLAN`, `13-V1-TO-V2-CONVERSION-PLAN`, `14-PENDING-TASKS`, `15`–`30`, `docs/audit/00`–`15` and `17`–`30` (the audits and plans those steps replaced), `docs/audit-evidence/`, `docs/ci/`. `docs/audit/16_MASTER_TODO.md` and `docs/audit/31_DEEP_QA_2026-10-21.md` are **not** history — they are the baseline QA and append-only build-log entries above.

`docs/audit/evidence/` holds screenshots and captures used as proof at the time; it is reference material, not a spec. The 33 screenshots were re-encoded from PNG to JPEG (quality 88) on 26 Oct 2026 in Slice G — same pixels at 100%, 13 MB → 5.6 MB — and every reference in these docs was repointed to the new extension. It can be pruned when the corresponding step is no longer in living memory.
