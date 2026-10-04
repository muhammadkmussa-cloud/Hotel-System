<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StaffSession;
use App\Models\StaffUser;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class StaffIdentityModelTest extends TestCase
{
    public function testCredentialFieldsAreHiddenAndIdentifiersAreUuidV7(): void
    {
        $user = new StaffUser(['email' => 'owner@example.test', 'name' => 'Owner']);
        $user->password_hash = 'hash-fixture';
        self::assertArrayNotHasKey('password_hash', $user->toArray());
        self::assertTrue(Str::isUuid($user->newUniqueId(), 7));
        self::assertFalse($user->isFillable('id'));
        self::assertSame('boolean', $user->getCasts()['active']);

        $session = new StaffSession(['token_hash' => 'a'.str_repeat('0', 63)]);
        self::assertArrayNotHasKey('token_hash', $session->toArray());
        self::assertTrue(Str::isUuid($session->newUniqueId(), 7));

        self::assertTrue(Str::isUuid((new Role)->newUniqueId(), 7));
    }
}
