import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const here = dirname(fileURLToPath(import.meta.url));
const generatedPath = join(here, '..', '..', 'public', 'assets', 'js', 'generated', 'api-client.js');
const contractPath = join(here, '..', '..', '..', 'api', 'openapi.json');

const { operations, findOperation, buildPath, contractVersion } = await import('file://' + generatedPath);
const { api, ApiError } = await import('file://' + join(here, '..', '..', 'public', 'assets', 'js', 'lib', 'api-client.js'));

test('generated client is up to date with the contract', () => {
  const before = readFileSync(generatedPath, 'utf8');
  execFileSync('node', [join(here, '..', '..', 'scripts', 'generate-api-client.mjs')]);
  assert.equal(readFileSync(generatedPath, 'utf8'), before);
});

test('all contract operations are represented', () => {
  const contract = JSON.parse(readFileSync(contractPath, 'utf8'));
  const expected = [];
  for (const path of Object.keys(contract.paths)) {
    for (const method of Object.keys(contract.paths[path])) expected.push([method.toUpperCase(), path]);
  }
  assert.deepEqual(operations.map((op) => [op.method, op.path]).sort(), expected.sort());
  assert.equal(contractVersion, contract.info.version);
});

test('buildPath expands and encodes parameters', () => {
  assert.equal(buildPath('/tables/{id}/guests', { id: 'a b' }), '/tables/a%20b/guests');
  assert.throws(() => buildPath('/a/{id}', {}), /Missing path parameter/);
});

test('GET requests carry no CSRF token', async () => {
  documentMeta('tok_123');
  const seen = [];
  const fetchImpl = async (url, init) => {
    seen.push({ url, init });
    return jsonResponse(200, { data: { status: 'ok' }, requestId: 'r1' });
  };
  await api('getLiveness', { fetchImpl });
  assert.equal(seen[0].init.headers['X-CSRF-TOKEN'], undefined);
  assert.equal(seen[0].init.method, 'GET');
});

test('command options set idempotency and version headers', async () => {
  operations.push({ operationId: 'tmpMutation', method: 'POST', path: '/tmp', tags: [], summary: '', requiresAuth: true, hasJsonBody: true, successCodes: ['200'] });
  try {
    documentMeta('tok_123');
    const seen = [];
    const fetchImpl = async (url, init) => { seen.push(init); return jsonResponse(200, { data: {}, requestId: 'r2' }); };
    await api('tmpMutation', { idempotencyKey: 'k1', ifMatch: '"v1"', body: { a: 1 }, fetchImpl });
    assert.equal(seen[0].headers['Idempotency-Key'], 'k1');
    assert.equal(seen[0].headers['If-Match'], '"v1"');
    assert.equal(seen[0].headers['X-CSRF-TOKEN'], 'tok_123');
    assert.equal(seen[0].headers['Content-Type'], 'application/json');
  } finally {
    const index = operations.findIndex((op) => op.operationId === 'tmpMutation');
    if (index >= 0) operations.splice(index, 1);
  }
});

test('transport failure maps to a retryable network_error', async () => {
  const fetchImpl = async () => { throw new TypeError('fetch failed'); };
  await assert.rejects(api('getLiveness', { fetchImpl }), (error) => error instanceof ApiError && error.code === 'network_error' && error.retryable === true);
});

test('error envelope maps to ApiError with retry metadata', async () => {
  const fetchImpl = async () => new Response(JSON.stringify({ error: { code: 'rate_limited', message: 'Slow down.', retryable: true, fieldErrors: [] }, requestId: 'r3' }), {
    status: 429, headers: { 'Content-Type': 'application/json', 'Retry-After': '7' },
  });
  await assert.rejects(api('getLiveness', { fetchImpl }), (error) => {
    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 429);
    assert.equal(error.code, 'rate_limited');
    assert.equal(error.retryable, true);
    assert.equal(error.retryAfter, 7);
    return true;
  });
});

test('non-JSON error responses surface a safe code', async () => {
  const fetchImpl = async () => new Response('<html>oops</html>', { status: 502 });
  await assert.rejects(api('getLiveness', { fetchImpl }), (error) => error instanceof ApiError && error.code === 'invalid_json');
});

function jsonResponse(status, body) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

function documentMeta(token) {
  globalThis.document = { querySelector: () => ({ content: token }) };
}

test('AbortError propagates untouched', async () => {
  const fetchImpl = async () => { throw new DOMException('aborted', 'AbortError'); };
  await assert.rejects(api('getLiveness', { fetchImpl }), (e) => e instanceof DOMException && e.name === 'AbortError');
});

test('invalid Retry-After falls back to the envelope value or null', async () => {
  const fetchImpl = async () => new Response(JSON.stringify({ error: { code: 'x', message: 'm' } }), { status: 503, headers: { 'Retry-After': 'soon' } });
  await assert.rejects(api('getLiveness', { fetchImpl }), (e) => e.retryAfter === null);
});
