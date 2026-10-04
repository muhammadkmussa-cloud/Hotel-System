<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Staff administration: scoped listing and creation. Authorization is enforced
 * separately by the capability middleware; this class performs the work.
 */
final class StaffAdmin
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly PasswordHasher $hasher,
        private readonly SecurityAudit $audit,
    ) {}

    /** @return list<array{id:string,email:string,name:string,active:bool,roles:list<string>}> */
    public function list(): array
    {
        $staff = $this->database->connection('mysql')->table('staff_users')
            ->orderBy('email')->get(['id', 'email', 'name', 'active'])->all();

        $grants = $this->database->connection('mysql')->table('staff_role_grants')
            ->join('roles', 'roles.id', '=', 'staff_role_grants.role_id')
            ->get(['staff_role_grants.staff_user_id', 'roles.key'])->all();

        $rolesByStaff = [];
        foreach ($grants as $grant) {
            $rolesByStaff[$grant->staff_user_id][] = $grant->key;
        }

        return array_map(static function (object $user) use ($rolesByStaff): array {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'active' => (bool) $user->active,
                'roles' => $rolesByStaff[$user->id] ?? [],
            ];
        }, $staff);
    }

    public function staffHasRole(string $staffUserId, string $roleKey): bool
    {
        if ($staffUserId === '' || $roleKey === '') {
            return false;
        }

        return $this->database->connection('mysql')->table('staff_role_grants')
            ->join('roles', 'roles.id', '=', 'staff_role_grants.role_id')
            ->where('staff_role_grants.staff_user_id', $staffUserId)
            ->where('roles.key', $roleKey)
            ->exists();
    }

    /**
     * Grant roles to a staff member. No self-escalation (an actor cannot grant
     * themselves a role) and only an owner may grant the owner role.
     * @param list<string> $roleKeys @return string granted|invalid_input|refused_self|refused_owner|failed
     */
    public function grantRoles(string $staffUserId, array $roleKeys, string $actorId): string
    {
        if ($staffUserId === '' || $actorId === '' || $roleKeys === []) {
            return 'invalid_input';
        }
        if ($staffUserId === $actorId) {
            return 'refused_self';
        }
        if (in_array('owner', $roleKeys, true) && ! $this->staffHasRole($actorId, 'owner')) {
            return 'refused_owner';
        }
        if (! $this->database->connection('mysql')->table('staff_users')->where('id', $staffUserId)->exists()) {
            return 'invalid_input';
        }
        $roleIds = $this->database->connection('mysql')->table('roles')
            ->whereIn('key', $roleKeys)->pluck('key', 'id')->all();
        if (count($roleIds) !== count(array_unique($roleKeys))) {
            return 'invalid_input';
        }

        $now = now('UTC');
        foreach (array_keys($roleIds) as $roleId) {
            $this->database->connection('mysql')->table('staff_role_grants')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'staff_user_id' => $staffUserId,
                'role_id' => $roleId,
                'granted_by' => $actorId,
                'granted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return 'granted';
    }

    /**
     * Revoke roles. The last active owner cannot be stripped of the owner role.
     * @param list<string> $roleKeys @return string revoked|invalid_input|refused_last_owner|failed
     */
    public function revokeRoles(string $staffUserId, array $roleKeys, string $actorId): string
    {
        if ($staffUserId === '' || $actorId === '' || $roleKeys === []) {
            return 'invalid_input';
        }
        if (in_array('owner', $roleKeys, true) && ! $this->staffHasRole($actorId, 'owner')) {
            return 'refused_owner';
        }
        if (! $this->database->connection('mysql')->table('staff_users')->where('id', $staffUserId)->exists()) {
            return 'invalid_input';
        }

        $connection = $this->database->connection('mysql');

        return $connection->transaction(function () use ($connection, $staffUserId, $roleKeys): string {
            // Lock the evaluated owner rows so concurrent revokes cannot both pass.
            if (in_array('owner', $roleKeys, true) && $this->isLastActiveOwner($staffUserId)) {
                return 'refused_last_owner';
            }
            $roleIds = $connection->table('roles')
                ->whereIn('key', $roleKeys)->pluck('key', 'id')->all();
            if (count($roleIds) !== count(array_unique($roleKeys))) {
                return 'invalid_input';
            }
            $connection->table('staff_role_grants')
                ->where('staff_user_id', $staffUserId)
                ->whereIn('role_id', array_keys($roleIds))
                ->delete();

            return 'revoked';
        });
    }

    /**
     * Update a staff member's name/email. Email must stay unique.
     * @return string updated|invalid_input|duplicate_email|failed
     */
    public function update(string $staffUserId, string $name, string $email, string $actorId): string
    {
        if ($staffUserId === '' || $actorId === '') {
            return 'invalid_input';
        }
        $email = strtolower(trim($email));
        $name = trim($name);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $name === '' || mb_strlen($name) > 150) {
            return 'invalid_input';
        }
        $connection = $this->database->connection('mysql');
        if (! $connection->table('staff_users')->where('id', $staffUserId)->exists()) {
            return 'invalid_input';
        }
        if ($connection->table('staff_users')->where('email', $email)->where('id', '!=', $staffUserId)->exists()) {
            return 'duplicate_email';
        }

        try {
            $connection->transaction(function () use ($connection, $staffUserId, $name, $email): void {
                $connection->table('staff_users')->where('id', $staffUserId)->update([
                    'name' => $name,
                    'email' => $email,
                    'updated_at' => now('UTC'),
                ]);
            });

            return 'updated';
        } catch (QueryException) {
            return 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /**
     * Reactivate a deactivated staff member.
     * @return string activated|invalid_input|already_active|failed
     */
    public function activate(string $staffUserId, string $actorId): string
    {
        if ($staffUserId === '' || $actorId === '') {
            return 'invalid_input';
        }
        $connection = $this->database->connection('mysql');
        $row = $connection->table('staff_users')->where('id', $staffUserId)->first(['active']);
        if ($row === null) {
            return 'invalid_input';
        }
        if ((int) $row->active === 1) {
            return 'already_active';
        }

        try {
            $connection->transaction(function () use ($connection, $staffUserId): void {
                $connection->table('staff_users')->where('id', $staffUserId)->update([
                    'active' => 1,
                    'deactivated_at' => null,
                    'updated_at' => now('UTC'),
                ]);
            });
            $this->audit->record('staff_activated', $staffUserId, null, ['actor' => $actorId]);

            return 'activated';
        } catch (QueryException) {
            return 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /**
     * Deactivate a staff member and revoke every server-side session so access
     * is lost immediately. No self-deactivation and no last-owner removal.
     * @return string deactivated|invalid_input|refused_self|refused_last_owner|failed
     */
    public function deactivate(string $staffUserId, string $actorId): string
    {
        if ($staffUserId === '' || $actorId === '') {
            return 'invalid_input';
        }
        if ($staffUserId === $actorId) {
            return 'refused_self';
        }

        $connection = $this->database->connection('mysql');
        $sessions = new StaffSessions($this->database);

        try {
            return $connection->transaction(function () use ($connection, $staffUserId, $sessions, $actorId): string {
                // Lock the evaluated owner rows so concurrent deactivations cannot both pass.
                if ($this->isLastActiveOwner($staffUserId)) {
                    return 'refused_last_owner';
                }
                $row = $connection->table('staff_users')->where('id', $staffUserId)->first(['active']);
                if ($row === null) {
                    return 'invalid_input';
                }
                if ((int) $row->active === 0) {
                    return 'already_inactive';
                }
                $connection->table('staff_users')->where('id', $staffUserId)->update([
                    'active' => 0,
                    'deactivated_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
                $sessions->revokeAllForStaff($staffUserId);
                $this->audit->record('staff_deactivated', $staffUserId, null, ['actor' => $actorId]);

                return 'deactivated';
            });
        } catch (QueryException) {
            return 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function isLastActiveOwner(string $staffUserId): bool
    {
        if (! $this->staffHasRole($staffUserId, 'owner')) {
            return false;
        }
        $activeOwners = $this->database->connection('mysql')->table('staff_users')
            ->where('active', 1)
            ->whereIn('id', function ($query): void {
                $query->select('staff_user_id')->from('staff_role_grants')
                    ->join('roles', 'roles.id', '=', 'staff_role_grants.role_id')
                    ->where('roles.key', 'owner');
            })
            ->lockForUpdate()
            ->count();

        return $activeOwners <= 1;
    }

    /** @param list<string> $roleKeys @return string created|invalid_input|duplicate_email|failed */
    public function create(string $email, string $name, string $password, array $roleKeys): string
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $name === '' || mb_strlen($name) > 150) {
            return 'invalid_input';
        }
        if ($roleKeys === []) {
            return 'invalid_input';
        }
        $roleIds = $this->database->connection('mysql')->table('roles')
            ->whereIn('key', $roleKeys)->pluck('key', 'id')->all();
        if (count($roleIds) !== count(array_unique($roleKeys))) {
            return 'invalid_input';
        }

        try {
            $hash = $this->hasher->hash($password);
        } catch (Throwable) {
            return 'invalid_input';
        }

        try {
            $this->database->connection('mysql')->transaction(function () use ($email, $name, $hash, $roleIds): void {
                $id = (string) Str::uuid7();
                $now = now('UTC');
                $this->database->connection('mysql')->table('staff_users')->insert([
                    'id' => $id,
                    'email' => $email,
                    'name' => $name,
                    'password_hash' => $hash,
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach (array_keys($roleIds) as $roleId) {
                    $this->database->connection('mysql')->table('staff_role_grants')->insert([
                        'id' => (string) Str::uuid7(),
                        'staff_user_id' => $id,
                        'role_id' => $roleId,
                        'granted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

            return 'created';
        } catch (QueryException $error) {
            return ($error->errorInfo[1] ?? null) === 1062 ? 'duplicate_email' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
