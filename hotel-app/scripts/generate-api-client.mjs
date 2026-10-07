#!/usr/bin/env node
// Deterministic browser contract generator for api/openapi.json (P03.10).
// Output is committed; rerun and diff to verify it is up to date.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const contractPath = join(here, '..', '..', 'api', 'openapi.json');
const outPath = join(here, '..', 'public', 'assets', 'js', 'generated', 'api-client.js');

const openapi = JSON.parse(readFileSync(contractPath, 'utf8'));

if (openapi.openapi !== '3.1.1') {
  throw new Error(`Unsupported contract version: ${openapi.openapi}`);
}

const operations = [];
for (const path of Object.keys(openapi.paths).sort()) {
  for (const method of Object.keys(openapi.paths[path]).sort()) {
    const op = openapi.paths[path][method];
    if (typeof op !== 'object' || op === null || !op.operationId) {
      throw new Error(`Missing operationId at ${method.toUpperCase()} ${path}`);
    }
    operations.push({
      operationId: op.operationId,
      method: method.toUpperCase(),
      path,
      tags: [...(op.tags ?? [])].sort(),
      summary: op.summary ?? '',
      requiresAuth: Array.isArray(op.security) ? op.security.length > 0 && op.security.every((rule) => Object.keys(rule).length > 0) : true,
      hasJsonBody: Boolean(op.requestBody?.content?.['application/json']),
      successCodes: Object.keys(op.responses).filter((code) => code.startsWith('2')).sort(),
    });
  }
}

const names = new Set();
for (const op of operations) {
  if (names.has(op.operationId)) throw new Error(`Duplicate operationId ${op.operationId}`);
  names.add(op.operationId);
}

const banner = `// Generated from api/openapi.json by scripts/generate-api-client.mjs. Do not edit by hand.\n`;
const body = `export const contractVersion = ${JSON.stringify(openapi.info.version)};\n\nexport const operations = ${JSON.stringify(operations, null, 2)};\n\n/**\n * Look up a contract operation by id.\n * @param {string} operationId\n */\nexport function findOperation(operationId) {\n  const op = operations.find((candidate) => candidate.operationId === operationId);\n  if (!op) throw new Error(\`Unknown operation \${operationId}\`);\n  return op;\n}\n\n/**\n * Expand \`{param}\` placeholders in an OpenAPI path.\n * @param {string} path\n * @param {Record<string, string | number>} [params]\n */\nexport function buildPath(path, params = {}) {\n  return path.replace(/\\{([^}]+)\\}/g, (match, name) => {\n    if (!(name in params)) throw new Error(\`Missing path parameter \${name}\`);\n    return encodeURIComponent(String(params[name]));\n  });\n}\n`;

mkdirSync(dirname(outPath), { recursive: true });
writeFileSync(outPath, banner + '\n' + body);
console.log(`Wrote ${operations.length} operations to ${outPath}`);
