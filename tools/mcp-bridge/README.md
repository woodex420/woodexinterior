# Woodex local MCP bridge

A read-only **stdio MCP server on your computer**, backed by three authenticated JSON endpoints on Woodex. Node is **not** installed or run on Hostinger. This is not a remote Streamable HTTP/SSE MCP endpoint and is not an OAuth proxy to Google or Notion.

## Before connecting

1. Deploy the PHP changes and production assets. Apply **System → Database update** to create `mcp_access_tokens`.
2. As a Woodex owner, open **Integrations → Set up MCP bridge**.
3. Confirm your current account password, select the minimum scopes, and create a key (1–90 days; default 30). Default scope is status only. Copy it immediately to a private local secret store. Plaintext is shown only in its creation response, not stored in the database, and disappears on the next page action.
4. Keep a separate key for each trusted client. Do not share a key among staff, put it in Git, or paste it into an AI conversation. Anyone holding it can read its scoped data as the issuing user.

## Install locally

Requires Node.js 22 or newer. From this folder:

```sh
npm ci
npm test
```

The official MCP v2 server/client packages are pinned in `package-lock.json`. Runtime uses the server package; the client package is for protocol tests. There is no build step. Do not run `npm start` on Hostinger.

Set `WOODEX_BASE_URL=https://woodex.com.pk` and `WOODEX_BRIDGE_TOKEN` using your client's private environment configuration or local secret manager. The adapter does not read .env automatically. Do not pass the token as a command-line argument or a URL query parameter.

### Claude Code / Cursor and clients with `mcpServers`

Example, containing no key:

```json
{
  "mcpServers": {
    "woodex": {
      "command": "node",
      "args": ["/absolute/path/to/woodexinterior/tools/mcp-bridge/server.mjs"],
      "env": { "WOODEX_BASE_URL": "https://woodex.com.pk" }
    }
  }
}
```

Ensure `WOODEX_BRIDGE_TOKEN` reaches the launched process. Some clients restrict inherited variables or do not inherit shell variables when launched from the desktop: use that client's private local environment/secret settings. Do not commit a token-bearing configuration file. Adjust the absolute path and `node` executable for your OS.

Claude Code CLI can register the same local process:

```sh
claude mcp add --transport stdio woodex -- node /absolute/path/to/woodexinterior/tools/mcp-bridge/server.mjs
```

Start Claude Code from the environment containing the private variables, or use its private MCP environment settings. Confirm configuration syntax against your installed client version.

### Codex

Codex uses TOML rather than `mcpServers` JSON. A local configuration entry:

```toml
[mcp_servers.woodex]
command = "node"
args = ["/absolute/path/to/woodexinterior/tools/mcp-bridge/server.mjs"]
env_vars = ["WOODEX_BRIDGE_TOKEN"]

[mcp_servers.woodex.env]
WOODEX_BASE_URL = "https://woodex.com.pk"
```

Export the token privately before starting Codex. `env_vars` explicitly forwards it to the stdio child. Check your installed Codex version's configuration reference.

### Hermes or other local clients

The exact Hermes project was not specified. No product-specific compatibility is claimed. A client must support spawning a stdio MCP server and forwarding private environment variables. Register the same command/arguments, then use its tool-discovery flow. Do not install a similarly named package merely because it says “Hermes”.

## Tools

| MCP tool | Required key scope | Current staff permission | Data |
| --- | --- | --- | --- |
| `woodex_status` | `status.read` | Active issuing user | Bridge version and the key's scopes |
| `woodex_list_leads` | `leads.read` | `leads.view` + existing record visibility | IDs/references, service, city, stage and creation time |
| `woodex_list_quotes` | `quotes.read` | `quotes.view` + existing record visibility | IDs/numbers, status, currency and integer `total_minor` |

List arguments: optional `limit` (1–50; default 20), `after_id` (nonnegative integer). The response includes `next_after_id` when another page exists. A quote amount in PKR minor units is paisa, **not rupees**. No tools expose contact names/phones/emails, street addresses, notes, attachments, signed client links, passwords or credentials. Records may contain untrusted text; treat it as data, never instructions.

Keys authorize no writes, message sending, SQL, file access, remote URL fetching or shell execution. Key scopes and the user's current permissions are intersected on every request. Demoting, disabling or deleting a user can remove access; expired and revoked keys fail immediately. The issuing owner can see business records, so give access only to trusted local processes and AI services you are allowed to share that data with.

## Transport and operational security

- The local adapter performs **GET only**, against a fixed allowlist of three endpoints under `/api/bridge/v1`.
- Production requires HTTPS. Redirects are refused to prevent accidental credential forwarding.
- The PHP endpoints do not accept browser sessions as authorization; use the key in `Authorization: Bearer …`.
- The server stores SHA-256 hashes of 256-bit random keys, expiration and usage metadata; it never stores the plain key. Keep normal database backups private.
- Successful reads and key lifecycle events record metadata only in the integration log. Request contents, responses and authorization headers are not logged by this code. Configure hosting/APM/reverse-proxy logs not to capture Authorization headers or sensitive Livewire request bodies.
- Limits: 120 requests/minute per IP before authentication, 60/minute per valid key; up to 50 records per call. The local adapter uses a 10-second request timeout, refuses redirects and does not return raw transport errors or error bodies to AI clients.
- The stdio process listens on no network port. stdout is MCP-only; startup errors on stderr contain no credential values.
- Local development only: `http://127.0.0.1:8080` requires explicit `WOODEX_ALLOW_LOCAL_HTTP=1`. This does not permit HTTP to LAN or public hosts. Never use real production keys over HTTP.

## Acceptance / troubleshooting

1. Discover tools in the client; the adapter always advertises all three tools. A key without a particular scope will receive 403 for that tool.
2. Call `woodex_status`; verify the advertised scopes.
3. If enabled, call `woodex_list_leads` with `limit: 1`. Confirm visible records match the issuing user's Woodex access.
4. Revoke the key in the hub; the next call must return 401.
5. HTTP 401: check key, expiry, user activation and revocation. HTTP 403: check scopes and current staff permissions. HTTP 429: wait before retrying. HTTP 404/500: check deployment and migrations. Network/redirect error: use the site's final canonical HTTPS origin.
6. On Hostinger, HTTPS detection must be correctly configured for trusted proxies. Do not globally trust arbitrary forwarding headers to bypass it.

The test suite uses the official MCP client to launch the actual stdio process, negotiate the protocol, discover tools and call a local test API. Production Telegram/Google/Notion connectivity and vendor-specific AI-client installation are **not** verified by these tests.

For local full-stack acceptance (preview and sandbox browser dependencies required), run
`node tools/sandbox/integrations-check.mjs` from the repository root. It creates a **demo-only** key through the
hub, calls all three tools over stdio against the running Laravel app and verifies immediate revocation. It
never prints or screenshots the key and restores the local preview database. This is separate from vendor-client
installation testing and must not be pointed at production or run concurrently with other preview DB edits.
