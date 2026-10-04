import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const localesDir = join(here, '..', '..', 'public', 'locales');

test('locale bundles parse and use unique dot-namespaced keys', () => {
  for (const file of readdirSync(localesDir).filter((f) => f.endsWith('.json'))) {
    const raw = readFileSync(join(localesDir, file), 'utf8');
    let parsed;
    assert.doesNotThrow(() => { parsed = JSON.parse(raw); }, `${file} must be valid JSON`);
    for (const key of Object.keys(parsed)) {
      assert.match(key, /^[a-z0-9]+(\.[a-z0-9]+)+$/, `${file} key ${key} must be dot-namespaced`);
      assert.equal(typeof parsed[key], 'string', `${file} ${key} must be a string`);
    }
  }
});

test('state UI keys and connection keys exist in the English bundle', () => {
  const en = JSON.parse(readFileSync(join(localesDir, 'en.json'), 'utf8'));
  for (const key of ['state.loading', 'state.empty', 'state.denied', 'state.error', 'connection.offline', 'connection.unreachable', 'connection.provider']) {
    assert.ok(en[key], `en.json must define ${key}`);
  }
});
