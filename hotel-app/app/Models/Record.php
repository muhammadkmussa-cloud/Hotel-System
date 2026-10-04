<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Shared persistence conventions; record IDs never grant access. */
abstract class Record extends Model
{
    use HasUuids;

    public function freshTimestamp(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
