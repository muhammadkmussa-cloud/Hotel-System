<?php

declare(strict_types=1);

namespace App\Models;

final class HotelSettings extends Record
{
    protected $table = 'hotel_settings';

    protected $fillable = [
        'name',
        'timezone',
        'currency',
        'business_day_cutoff',
        'fiscal_configuration_version',
    ];

    protected function casts(): array
    {
        return ['fiscal_configuration_version' => 'integer'];
    }
}
