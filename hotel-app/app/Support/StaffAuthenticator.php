<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StaffUser;
use Illuminate\Database\DatabaseManager;

/**
 * Verifies staff credentials against the stored hash. Never reveals which of
 * email/password was wrong, and never checks inactive accounts.
 */
final class StaffAuthenticator
{
    /**
     * A valid bcrypt hash used to equalise timing when no active account is
     * found, so responses do not reveal whether an email is registered.
     */
    private const DUMMY_HASH = '$2y$12$BAGgGy2gU9ObPvdI1lznG.fhXvV7Plc6OGc2MfyKLjBfgqwwG1zDe';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly PasswordHasher $hasher,
    ) {}

    /** Re-verify an already-identified active staff member (used for unlock). */
    public function confirmById(string $staffUserId, #[\SensitiveParameter] string $password): bool
    {
        if ($staffUserId === '' || $password === '') {
            return false;
        }
        $row = $this->database->connection('mysql')->table('staff_users')
            ->where('id', $staffUserId)->where('active', 1)->first(['password_hash']);

        return $row !== null && is_string($row->password_hash)
            && $this->hasher->verify($password, $row->password_hash);
    }

    public function attempt(string $email, string $password): ?StaffUser
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return null;
        }

        $row = $this->database->connection('mysql')->table('staff_users')
            ->where('email', $email)
            ->where('active', 1)
            ->first();

        if ($row === null || ! is_string($row->password_hash)) {
            // Perform equivalent work so timing does not reveal a missing account.
            $this->hasher->verify($password, self::DUMMY_HASH);

            return null;
        }
        if (! $this->hasher->verify($password, $row->password_hash)) {
            return null;
        }

        if ($this->hasher->needsRehash($row->password_hash)) {
            try {
                $this->database->connection('mysql')->table('staff_users')->where('id', $row->id)->update([
                    'password_hash' => $this->hasher->hash($password),
                    'updated_at' => now('UTC'),
                ]);
            } catch (\Throwable) {
                // A failed opportunistic rehash must not block a valid sign-in.
            }
        }

        return StaffUser::query()->find($row->id);
    }
}
