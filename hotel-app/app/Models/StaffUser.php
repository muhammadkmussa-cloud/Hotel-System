<?php

declare(strict_types=1);

namespace App\Models;

final class StaffUser extends Record
{
    protected $table = 'staff_users';

    protected $fillable = ['email', 'name', 'active', 'deactivated_at'];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'deactivated_at' => 'immutable_datetime'];
    }
}
