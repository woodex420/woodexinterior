#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/server/stdio';
import { createBridgeServer } from './bridge.mjs';

try {
  const server = createBridgeServer();
  await server.connect(new StdioServerTransport());
} catch {
  // stdout belongs exclusively to MCP protocol messages. Do not log configuration values.
  process.stderr.write('Woodex MCP could not start. Check Node.js 22+, dependencies, WOODEX_BASE_URL and the private WOODEX_BRIDGE_TOKEN.\n');
  process.exitCode = 1;
}
