# Integrations hub and MCP bridge

Date: 2026-10-17. Scope selected by the owner: **hub + MCP first**, **AI tools access Woodex**, **company connections**, **Hostinger + a local computer**.

## Google follow-up

The owner subsequently selected **Google first + both company connection and staff sign-in**, with no registered
OAuth app yet. A basic-identity Google flow is now implemented locally, with setup-required UI, encrypted company
grants and explicit prelinked-staff sign-in. It grants no Workspace data access and is separate from MCP.
See `docs/GOOGLE-OAUTH-SETUP.md`. The baseline verification below records the preceding MCP delivery; the latest
Google verification is in `platform/STATE.md`.

## Recommendation and references

Use the existing Laravel/Livewire application for the hub and a small local stdio adapter built with the **official MCP TypeScript SDK v2**. Do not add a server daemon to shared hosting or expose a general-purpose shell/filesystem/GitHub MCP server to business data. Do not replace the app with a React/Next.js dashboard just to obtain integration cards.

Reviewed sources:

- [Official MCP TypeScript SDK](https://github.com/modelcontextprotocol/typescript-sdk), its v2 stdio example and split server/client packages. npm publishes `@modelcontextprotocol/server` and `@modelcontextprotocol/client` 2.3.1; this release pins them and Zod 4.6.7 in the local adapter lockfile.
- [MCP reference servers](https://github.com/modelcontextprotocol/servers): useful protocol examples, not a ready-made permission boundary for Woodex business records. Broad reference-tool capabilities are intentionally not bundled.
- [TailAdmin integrations reference](https://demo.tailadmin.com/integrations): compact app cards, descriptions and per-app actions. This implementation is original Blade using the repository's existing tokens and components, not copied premium page source.
- [TailAdmin open-source projects](https://github.com/TailAdmin), including [TailAdmin Laravel](https://github.com/TailAdmin/tailadmin-laravel): Laravel/Blade is the appropriate ecosystem reference. No extra dashboard framework or premium dependency is introduced.

“Best” here means the smallest auditable architecture matching the chosen hosting and access requirements, not a claim that every GitHub bridge was benchmarked.

## Shipped locally

- Searchable/category-filtered hub with accurate status labels, service requirements and working existing controls.
- Preserved Telegram configuration-file/cache diagnostics, webhook registration checks and test-message flow. No exposed bot token is reused, and a redesign does not fix absent live secrets.
- Google Drive, Sheets, Gmail, Calendar, Meet, Notion, Slack, Zoom, WhatsApp, Facebook, Instagram and LinkedIn appear explicitly as **Planned**. Their requirements dialogs are real; OAuth/synchronization is not implemented and there are no fake connect/remove toggles.
- A local stdio MCP server with `woodex_status`, `woodex_list_leads` and `woodex_list_quotes`.
- Read-only PHP JSON endpoints backed by current Woodex permission/visibility rules, bounded results and metadata-only successful-read/key-lifecycle auditing.
- Owner-only expiring key creation with account-password confirmation, hashed storage, scope selection, one-time plaintext display and revocation. Default scope is status only. Maximum lifetime is 90 days.
- Setup documentation and client examples in `tools/mcp-bridge/README.md`. The exact Hermes product is unknown, so only generic stdio capability requirements are documented.

## Deliberately not implemented

No external MCP-server consumption, arbitrary URL proxying, shell execution, remote public MCP/OAuth server, write tools, email sending through MCP, OAuth for Workspace data or non-Google providers, Google/Notion data sharing or social publishing. Arena conversation app connections are separate from this site's integrations and are not imported or reused.

## Next production phases

1. Resolve Telegram's active-environment/cached-configuration issue using the owner-only diagnostics. Register the webhook, link a staff chat and confirm a visible test reply.
2. Google Workspace: register the company OAuth app and live-test the implemented basic identity/state/PKCE/encrypted-token/refresh/revocation flow; separately define actual Workspace features, exact scopes and restricted-scope verification as required. Drive, Sheets, Gmail and Calendar/Meet need separate capability definitions; a single green Google badge must not imply every API is enabled.
3. Notion: choose internal business integration or public OAuth based on workspace needs; require explicit page sharing, encrypted credentials and revocation. Do not let AI browse every workspace by default.
4. Social providers: implement provider-specific account eligibility, business review, OAuth, webhook signatures and least-privilege publishing/messaging approvals.
5. Add MCP write tools only after separate human-approval and audit requirements are agreed. Keep sending/quote/payment domain actions as the only writers.

## Deployment and acceptance

This session is closed after the previous deployment PR merged. These additional files are local changes only. A new coding session is needed to publish them; no push, PR or production deployment is attempted here.

After a future deployment, apply **System → Database update** (new `mcp_access_tokens` migration), confirm owner access, create a minimum-scope key, install the adapter **locally** with Node.js 22+ and `npm ci`, discover tools, verify status and scoped reads, and verify revocation immediately denies the next request. Keep all real keys out of screenshots, prompts, repositories and support reports.

## Local verification — 2026-10-17

- Full PHP suite: **478 passed, 3,318 assertions, 1 skipped**. MCP-specific coverage: **18 tests / 75 assertions**.
- Pint on changed PHP and Larastan level 6 on the bridge, hub and configuration diagnostic pass. Production assets rebuilt (existing chart-bundle size warning only).
- Node adapter tests: **3/3**, with the official client launching the stdio process, negotiating/discovering/calling tools, validating arguments and checking redacted errors. Runtime dependency audit: zero reported vulnerabilities.
- `node tools/sandbox/integrations-check.mjs`: demo owner creates a key in the UI; the official stdio client calls **all three tools against the real local Laravel app**; UI revocation immediately yields 401. Also checks hub search, requirements modal, preserved Telegram diagnostics and responsive layouts without exposing keys in test output/screenshots.
- `node tools/sandbox/iframe-check.mjs`: both cookie-restricted iframe modes pass, including hub search and MCP setup.
- General `browser-check.mjs`: admin/quote/PDF/mobile assertions pass. Its two existing inline-homepage-form expectations fail against the restored v1 homepage, which has no such form; unrelated public markup is unchanged.

These are local acceptance results, not vendor-specific Claude/Cursor/Codex/Hermes installation, real Telegram delivery, Google/Notion connectivity or Hostinger deployment verification.
