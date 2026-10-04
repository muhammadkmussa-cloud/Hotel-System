<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Creates the single owner principal exactly once, guarded by a private
 * installer secret and a database singleton slot. No shared/default secret.
 */
final class OwnerBootstrap
{
    public const MIN_SECRET_LENGTH = 32;

    public const MIN_PASSWORD_LENGTH = PasswordHasher::MIN_LENGTH;

    public function __construct(
        private readonly Repository $config,
        private readonly DatabaseManager $database,
        private readonly PasswordHasher $hasher,
    ) {}

    /** @return string one of created|refused_unconfigured|refused_secret|refused_exists|invalid_input|failed */
    public function bootstrap(#[\SensitiveParameter] string $secret, string $email, string $name, #[\SensitiveParameter] string $password): string
    {
        $configured = $this->config->get('installation.installer_secret');
        if (! is_string($configured) || strlen($configured) < self::MIN_SECRET_LENGTH) {
            return 'refused_unconfigured';
        }
        if (! hash_equals($configured, $secret)) {
            return 'refused_secret';
        }

        $email = strtolower(trim($email));
        $name = trim($name);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || $name === '' || mb_strlen($name) > 150
            || mb_strlen($password) < self::MIN_PASSWORD_LENGTH
            || strlen($password) > PasswordHasher::MAX_BYTES
            || str_contains($password, "\0")
        ) {
            return 'invalid_input';
        }

        try {
            $hash = $this->hasher->hash($password);

            $connection = $this->database->connection('mysql');
            DatabaseTransaction::run($connection, function () use ($connection, $email, $name, $hash): void {
                if ($connection->table('installation_bootstrap')->exists()
                    || $connection->table('staff_users')->exists()
                ) {
                    throw new BootstrapAlreadyCompleted;
                }
                $roleId = $connection->table('roles')->where('key', 'owner')->value('id');
                if (! is_string($roleId) || $roleId === '') {
                    throw new RuntimeException('Owner role is unavailable.');
                }
                $staffId = (string) Str::uuid7();
                $now = now('UTC');
                $connection->table('staff_users')->insert([
                    'id' => $staffId,
                    'email' => $email,
                    'name' => $name,
                    'password_hash' => $hash,
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $connection->table('staff_role_grants')->insert([
                    'id' => (string) Str::uuid7(),
                    'staff_user_id' => $staffId,
                    'role_id' => $roleId,
                    'granted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $connection->table('installation_bootstrap')->insert([
                    'id' => (string) Str::uuid7(),
                    'owner_staff_user_id' => $staffId,
                    'bootstrapped_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

            return 'created';
        } catch (BootstrapAlreadyCompleted) {
            return 'refused_exists';
        } catch (QueryException $error) {
            // 1062 duplicate key: another bootstrap won the singleton slot or email.
            return ($error->errorInfo[1] ?? null) === 1062 ? 'refused_exists' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }
}

final class BootstrapAlreadyCompleted extends RuntimeException {}
