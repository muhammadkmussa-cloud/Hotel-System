<?php

declare(strict_types=1);

namespace App\Models;

final class Role extends Record
{
    protected $table = 'roles';

    protected $fillable = ['key', 'name', 'description'];
}
