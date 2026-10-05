<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class StaffIdentityTest extends TestCase
{
    public function testStaffRolesAndSessionsEnforceUniqueIdentityAndPreserveHistory(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-staff-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $run = null;
        try {
            foreach (['config', 'routes', 'database/migrations'] as $directory) {
                $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            }
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) {
                $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source.'/'.$file, $fixture.'/'.$file);
            }
            $files->copy($source.'/tests/Fixtures/staff-identity-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, $env, timeout: 60);
                $process->run();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };
            self::assertTrue($run('empty')['empty'], 'Requires an otherwise empty isolated schema.');
            $owned = true;
            $result = $run('probe');
            self::assertSame(0, $result['status']);
            self::assertSame([
                'staff_users' => true,
                'roles' => true,
                'staff_role_grants' => true,
                'staff_sessions' => true,
            ], $result['tables']);
            self::assertSame(['auditor', 'cashier', 'kitchen_lead', 'kitchen_staff', 'manager', 'menu_editor', 'owner', 'waiter'], $result['roles']);
            self::assertTrue($result['duplicateEmail'], 'A duplicate staff email must be rejected.');
            self::assertTrue($result['duplicateEmailCase'], 'A case-insensitive duplicate staff email must be rejected.');
            self::assertTrue($result['duplicateToken'], 'A duplicate session token hash must be rejected.');
            self::assertTrue($result['duplicateGrant'], 'A duplicate role grant must be rejected.');
            self::assertTrue($result['referencedDeleteBlocked'], 'Deleting a staff user with sessions/grants must be blocked.');
            self::assertTrue($result['preserved'], 'Deactivation must preserve the staff row.');
            self::assertTrue($result['granterDeleteBlocked'], 'Deleting a staff user referenced as a role granter must be blocked.');
            self::assertTrue($result['roleDeleteBlocked'], 'Deleting a referenced role must be blocked.');
        } finally {
            try {
                if ($owned && $run !== null) {
                    self::assertTrue($run('cleanup')['cleaned']);
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
