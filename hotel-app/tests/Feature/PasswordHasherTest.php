<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PasswordHasher;
use Illuminate\Hashing\BcryptHasher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    private const OPTIONS = ['rounds' => 12, 'verify' => false, 'limit' => 72];

    private function hasher(array $options = self::OPTIONS): PasswordHasher
    {
        return new PasswordHasher(new BcryptHasher($options));
    }

    public function testHashNeverStoresPlaintextAndIsSalted(): void
    {
        $plain = 'correct-horse-battery';
        $hash = $this->hasher()->hash($plain);

        self::assertNotSame($plain, $hash);
        self::assertStringNotContainsString($plain, $hash);
        self::assertMatchesRegularExpression('/\A\$2[aby]\$/', $hash);
        self::assertNotSame($hash, $this->hasher()->hash($plain), 'Each hash must use a fresh salt.');
    }

    public function testVerificationAcceptsCorrectAndRejectsWrongEmptyOrMalformedPasswords(): void
    {
        $hasher = $this->hasher();
        $hash = $hasher->hash('correct-horse-battery');

        self::assertTrue($hasher->verify('correct-horse-battery', $hash));
        self::assertFalse($hasher->verify('wrong-password-000', $hash));
        self::assertFalse($hasher->verify('', $hash));
        self::assertFalse($hasher->verify('correct-horse-battery', ''));
        self::assertFalse($hasher->verify('correct-horse-battery', 'not-a-hash'));
        self::assertFalse($hasher->verify('correct-horse-battery', '$2y$12$short'));
        self::assertFalse($hasher->needsRehash($hash));
        self::assertTrue($hasher->needsRehash('not-a-hash'), 'An unrecognised hash should be flagged for rehash.');
    }

    public function testLengthPolicyBoundaries(): void
    {
        $hasher = $this->hasher();

        $this->expectException(InvalidArgumentException::class);
        $hasher->hash(str_repeat('a', PasswordHasher::MIN_LENGTH - 1));
    }

    public function testExactlyMinimumLengthIsAccepted(): void
    {
        $plain = str_repeat('a', PasswordHasher::MIN_LENGTH);
        self::assertTrue($this->hasher()->verify($plain, $this->hasher()->hash($plain)));
    }

    public function testOverlongAndNulBytePasswordsAreRejected(): void
    {
        $hasher = $this->hasher();

        foreach ([str_repeat('a', PasswordHasher::MAX_BYTES + 1), "valid-length-\0-tail"] as $bad) {
            try {
                $hasher->hash($bad);
                self::fail('An unacceptable password should have been rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testWeaklyHashedPasswordsAreDetectedForRehash(): void
    {
        $weak = $this->hasher(['rounds' => 4, 'verify' => false, 'limit' => 72]);
        $strong = $this->hasher();
        $hash = $weak->hash('correct-horse-battery');

        self::assertTrue($strong->verify('correct-horse-battery', $hash));
        self::assertTrue($strong->needsRehash($hash));
    }
}
