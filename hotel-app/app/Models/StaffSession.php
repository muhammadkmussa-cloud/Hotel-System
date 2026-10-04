<?php

declare(strict_types=1);

namespace App\Models;

final class StaffSession extends Record
{
    protected $table = 'staff_sessions';

    protected $fillable = [
        'staff_user_id',
        'token_hash',
        'issued_at',
        'expires_at',
        'revoked_at',
        'last_seen_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
