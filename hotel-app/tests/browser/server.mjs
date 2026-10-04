import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { cpSync, mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { address, origin } from './settings.mjs';

// Never boot the working installation or copy its private settings/cache.
const source = fileURLToPath(new URL('../../', import.meta.url));
const fixture = mkdtempSync(join(tmpdir(), 'hotel-browser-'));
let child;
let stopping = false;

function cleanup() {
  rmSync(fixture, { recursive: true, force: true });
}

function stop() {
  stopping = true;
  if (child) child.kill('SIGTERM');
}

process.on('SIGTERM', stop);
process.on('SIGINT', stop);

try {
  for (const directory of ['app', 'bootstrap', 'config', 'routes', 'resources', 'public']) {
    cpSync(join(source, directory), join(fixture, directory), {
      recursive: true,
      filter: (path) => !['cache', 'media'].includes(basename(path)),
    });
  }
  for (const directory of ['bootstrap/cache', 'storage/logs', 'storage/framework/views', 'storage/framework/sessions']) {
    mkdirSync(join(fixture, directory), { recursive: true, mode: 0o700 });
  }
  symlinkSync(join(source, 'vendor'), join(fixture, 'vendor'), 'dir');
  writeFileSync(join(fixture, '.env'), [
    'APP_ENV=testing',
    `APP_URL=${origin}`,
    `APP_KEY=base64:${randomBytes(32).toString('base64')}`,
    'APP_NAME="Browser Fixture Hotel"',
    'INSTALLATION_SETUP_ENABLED=true',
    'INSTALLER_SECRET=' + 'a1b2c3d4e5f6'.repeat(5) + 'a1b2c3d4',
    '',
  ].join('\n'), { mode: 0o600 });
  child = spawn('php', ['-S', address, '-t', join(fixture, 'public'),
    join(source, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: join(fixture, 'public'),
    env: { PATH: process.env.PATH ?? '/usr/bin:/bin' },
    stdio: ['ignore', 'ignore', 'pipe'],
  });
  child.stderr.pipe(process.stderr);
  child.on('error', () => {
    console.error('Could not start PHP for the isolated browser fixture.');
    process.exitCode = 1;
  });
  child.on('close', (code) => {
    cleanup();
    process.exitCode = stopping ? 0 : (code || 1);
  });
} catch (error) {
  cleanup();
  throw error;
}
