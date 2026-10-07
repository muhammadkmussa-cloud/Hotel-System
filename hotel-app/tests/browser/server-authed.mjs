import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { cpSync, copyFileSync, mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { authedAddress, authedOrigin } from './settings.mjs';

// A second, isolated fixture with a seeded SQLite demo database so that
// authenticated P06+ specs can sign in. Anonymous specs use server.mjs.
const source = fileURLToPath(new URL('../../', import.meta.url));
const fixture = mkdtempSync(join(tmpdir(), 'hotel-browser-authed-'));
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
  for (const directory of ['app', 'bootstrap', 'config', 'routes', 'resources', 'public', 'database']) {
    cpSync(join(source, directory), join(fixture, directory), {
      recursive: true,
      filter: (path) => !['cache', 'media'].includes(basename(path)),
    });
  }
  copyFileSync(join(source, 'artisan'), join(fixture, 'artisan'));
  for (const directory of ['bootstrap/cache', 'storage/logs', 'storage/framework/views', 'storage/framework/sessions']) {
    mkdirSync(join(fixture, directory), { recursive: true, mode: 0o700 });
  }
  symlinkSync(join(source, 'vendor'), join(fixture, 'vendor'), 'dir');
  writeFileSync(join(fixture, '.env'), [
    'APP_ENV=testing',
    `APP_URL=${authedOrigin}`,
    `APP_KEY=base64:${randomBytes(32).toString('base64')}`,
    'APP_NAME="Browser Authed Fixture Hotel"',
    'INSTALLATION_SETUP_ENABLED=true',
    'INSTALLER_SECRET=' + 'a1b2c3d4e5f6'.repeat(5) + 'a1b2c3d4',
    'DB_DRIVER=sqlite',
    `DB_DATABASE=${join(fixture, 'storage', 'hotel.sqlite')}`,
    'STAFF_IDLE_MINUTES=240',
    'PRINTER_BRIDGE_HOSTS=bridge.local',
    'MPESA_MODE=simulator',
    'FISCAL_ENABLED=false',
    '',
  ].join('\n'), { mode: 0o600 });

  const phpEnv = { PATH: process.env.PATH ?? '/usr/bin:/bin' };
  for (const args of [['artisan', 'migrate', '--force', '--no-interaction'], ['artisan', 'hotel:demo-seed']]) {
    const setup = spawnSync('php', args, { cwd: fixture, env: phpEnv, stdio: ['ignore', 'ignore', 'inherit'] });
    if (setup.status !== 0) {
      console.error('Authenticated browser fixture setup failed: php ' + args.join(' '));
      cleanup();
      process.exit(1);
    }
  }

  child = spawn('php', ['-S', authedAddress, '-t', join(fixture, 'public'),
    join(source, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
    cwd: join(fixture, 'public'),
    env: phpEnv,
    stdio: ['ignore', 'ignore', 'pipe'],
  });
  child.stderr.pipe(process.stderr);
  child.on('error', () => {
    console.error('Could not start PHP for the authenticated browser fixture.');
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
