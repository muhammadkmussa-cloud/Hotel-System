<?php

declare(strict_types=1);

namespace App\Security;

/** A verified server-side identity; never construct it from submitted identity fields. */
interface Principal
{
    public function identifier(): string;
}
